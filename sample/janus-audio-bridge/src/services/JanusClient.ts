import axios from "axios";

interface JanusConfig {
  sessionId: string;
  handleId: string;
  roomId?: string;
  display?: string;
}

interface AudioSource {
  type: "local" | "remote";
  stream: MediaStream;
  audio: HTMLAudioElement;
}

export class JanusClient {
  private peerConnection: RTCPeerConnection | null = null;
  private localStream: MediaStream | null = null;
  private config: JanusConfig;
  private onStateChange?: (state: { isMuted: boolean }) => void;
  private connected: boolean = false;
  private participants: Map<string, any> = new Map();
  private onParticipantsChange?: (participants: any[]) => void;
  private remoteAudios: HTMLAudioElement[] = [];
  private localAudio: HTMLAudioElement | null = null;
  private currentAudioSource: "local" | "remote" = "remote";
  private audioContainer: HTMLDivElement;
  private heartbeatInterval: number | null = null;
  private reconnectAttempts: number = 0;
  private maxReconnectAttempts: number = 3;
  private keepaliveInterval: NodeJS.Timeout | null = null;
  private currentRoomId: string | null = null;
  private currentDisplay: string | null = null;
  private volume: number = 100;
  private senders: RTCRtpSender[] = [];

  constructor(config: JanusConfig) {
    this.config = config;
    this.currentRoomId = config.roomId || null;
    this.currentDisplay = config.display || null;
    // 创建一个隐藏的音频容器
    this.audioContainer = document.createElement("div");
    this.audioContainer.style.display = "none";
    document.body.appendChild(this.audioContainer);
    this.initializePeerConnection();
    this.startKeepalive();
  }

  private initializePeerConnection() {
    // 如果已存在连接，先清理
    if (this.peerConnection) {
      // 只有在连接处于活动状态时才尝试移除轨道
      if (this.peerConnection.signalingState !== "closed") {
        this.senders.forEach((sender) => {
          this.peerConnection?.removeTrack(sender);
        });
      }
      this.senders = [];
      this.peerConnection.close();
    }

    this.peerConnection = new RTCPeerConnection({
      iceServers: [], // 局域网内不需要 STUN/TURN
    });

    // 添加本地音频轨道
    if (this.localStream) {
      this.localStream.getTracks().forEach((track) => {
        console.log("[Janus] Adding local track:", track.kind);
        const sender = this.peerConnection!.addTrack(track, this.localStream!);
        this.senders.push(sender);
      });
    }

    // 添加更详细的连接状态日志
    this.peerConnection.onconnectionstatechange = () => {
      console.log(
        "[Janus] Connection state changed:",
        this.peerConnection?.connectionState
      );

      // 当连接建立时更新参与者列表
      if (this.peerConnection?.connectionState === "connected") {
        this.updateParticipantsList();
      }
    };

    this.peerConnection.oniceconnectionstatechange = () => {
      console.log(
        "[Janus] ICE connection state:",
        this.peerConnection?.iceConnectionState
      );
    };

    this.peerConnection.onnegotiationneeded = async () => {
      console.log("[Janus] Negotiation needed");
      try {
        await this.handleNegotiation();
      } catch (error) {
        console.error("[Janus] Failed to handle negotiation:", error);
      }
    };

    // 添加 ICE 候选日志
    this.peerConnection.onicecandidate = (event) => {
      console.log("[Janus] New ICE candidate:", event.candidate);
    };

    this.peerConnection.ontrack = (event) => {
      console.log("[Janus] Received remote track:", event.track.kind);
      if (event.track.kind === "audio") {
        const audio = new Audio();
        audio.srcObject = event.streams[0];
        audio.autoplay = true;
        this.audioContainer.appendChild(audio);
        this.remoteAudios.push(audio);

        // 添加更多音频调试信息
        audio.onloadedmetadata = () => {
          console.log("[Janus] Audio metadata loaded");
          // 自动播放可能需要用户交互，所以我们主动尝试播放
          audio.play().catch((e) => {
            console.warn("[Janus] Auto-play failed:", e);
          });
        };

        // 监听音频状态
        audio.onplay = () => console.log("[Janus] Audio started playing");
        audio.onpause = () => console.log("[Janus] Audio paused");
        audio.onerror = (e) => console.error("[Janus] Audio error:", e);

        // 设置初始音量
        audio.volume = this.volume / 100;
        audio.muted = false;
      }
    };
  }

  private async retryOperation<T>(
    operation: () => Promise<T>,
    maxRetries: number = 3,
    delayMs: number = 2000
  ): Promise<T> {
    let lastError: Error;

    for (let attempt = 1; attempt <= maxRetries; attempt++) {
      try {
        return await operation();
      } catch (error) {
        lastError = error instanceof Error ? error : new Error(String(error));
        console.warn(
          `Attempt ${attempt}/${maxRetries} failed:`,
          lastError.message
        );

        if (attempt === maxRetries) {
          throw new Error(
            `Operation failed after ${maxRetries} attempts: ${lastError.message}`
          );
        }

        await new Promise((resolve) => setTimeout(resolve, delayMs));
      }
    }

    throw lastError!; // TypeScript 需要这行，实际上不会执行到这里
  }

  // 初始化媒体流
  public async initializeMedia(): Promise<void> {
    try {
      if (this.localStream) {
        // 如果已经有流，先停止所有轨道
        this.localStream.getTracks().forEach((track) => track.stop());
      }

      this.localStream = await navigator.mediaDevices.getUserMedia({
        audio: {
          echoCancellation: true,
          noiseSuppression: true,
          autoGainControl: true,
          sampleRate: 48000,
          channelCount: 1,
        },
        video: false,
      });

      console.log("[Janus] Media initialized:", this.localStream.getTracks());

      // 创建本地音频播放器
      this.localAudio = new Audio();
      this.localAudio.autoplay = true;
      this.localAudio.srcObject = this.localStream;
      this.localAudio.muted = this.currentAudioSource === "remote";

      // 确保本地音频被正确创建和播放
      this.localAudio.oncanplay = () => {
        console.log("Local audio can play");
        const playPromise = this.localAudio?.play();
        if (playPromise) {
          playPromise.catch((error) => {
            console.error("Failed to play local audio:", error);
          });
        }
      };

      this.audioContainer.appendChild(this.localAudio);

      if (this.peerConnection) {
        this.localStream.getTracks().forEach((track) => {
          if (this.localStream && this.peerConnection) {
            this.peerConnection.addTrack(track, this.localStream);
          }
        });
      }
    } catch (error) {
      console.error("Failed to get user media:", error);
      throw error;
    }
  }

  private generateTransactionId(): string {
    return Math.random().toString(36).substring(2, 15);
  }

  // 加入房间
  public async joinRoom(roomId: string, display: string): Promise<void> {
    try {
      console.log("[Janus] Joining room:", roomId, "as:", display);

      // 确保媒体流已初始化
      if (!this.localStream) {
        await this.initializeMedia();
      }

      // 尝试解除浏览器的自动播放限制
      await this.tryUnlockAudio();

      // 发送加入房间请求
      const joinResponse = await axios.post(
        `/api/janus/${this.config.sessionId}/${this.config.handleId}`,
        {
          janus: "message",
          body: {
            request: "join",
            room: parseInt(roomId),
            display: display,
            muted: false,
            quality: 1,
          },
          transaction: this.generateTransactionId(),
        }
      );

      console.log("[Janus] Join response:", joinResponse);

      // 如果有 jsep，需要处理 offer/answer
      if (joinResponse?.data?.jsep) {
        await this.peerConnection?.setRemoteDescription(
          new RTCSessionDescription(joinResponse.data.jsep)
        );

        // 创建 answer
        const answer = await this.peerConnection!.createAnswer();
        await this.peerConnection!.setLocalDescription(answer);

        // 发送 answer
        await axios.post(
          `/api/janus/${this.config.sessionId}/${this.config.handleId}`,
          {
            janus: "message",
            body: {
              request: "configure",
              muted: false,
            },
            jsep: answer,
            transaction: this.generateTransactionId(),
          }
        );
      }

      this.currentRoomId = roomId;
      this.currentDisplay = display;
      this.connected = true;

      // 开始心跳检测
      this.startKeepalive();

      console.log("[Janus] Successfully joined room");
    } catch (error) {
      console.error("[Janus] Failed to join room:", error);
      throw error;
    }
  }

  private async sendTrickle(candidate: RTCIceCandidate | null) {
    if (!this.config.sessionId || !this.config.handleId) {
      throw new Error("No session or handle");
    }

    // 移除 /trickle
    await axios.post(
      `/api/janus/${this.config.sessionId}/${this.config.handleId}`,
      {
        janus: "trickle",
        transaction: this.generateTransactionId(),
        candidate: candidate
          ? {
              candidate: candidate.candidate,
              sdpMid: candidate.sdpMid,
              sdpMLineIndex: candidate.sdpMLineIndex,
            }
          : null,
      }
    );
  }

  public async configure(options: {
    muted: boolean;
    quality?: number;
  }): Promise<void> {
    return this.retryOperation(async () => {
      try {
        if (!this.connected) {
          throw new Error("Not connected to room");
        }

        // 更新本地音频轨道状态
        if (this.localStream) {
          this.localStream.getAudioTracks().forEach((track) => {
            track.enabled = !options.muted;
          });
        }

        // 发送配置请求到 Janus
        await axios.post(
          `/api/janus/${this.config.sessionId}/${this.config.handleId}`,
          {
            janus: "message",
            transaction: this.generateTransactionId(),
            body: {
              request: "configure",
              muted: options.muted,
              quality: options.quality || 1.0,
            },
          }
        );

        if (this.onStateChange) {
          this.onStateChange({ isMuted: options.muted });
        }
      } catch (error) {
        console.error("Failed to configure:", error);
        throw error;
      }
    });
  }

  public setOnStateChange(
    callback: (state: { isMuted: boolean }) => void
  ): void {
    this.onStateChange = callback;
  }

  public disconnect(): void {
    if (this.localStream) {
      this.localStream.getTracks().forEach((track) => track.stop());
      this.localStream = null;
    }

    if (this.peerConnection) {
      // 清理所有 sender
      this.senders.forEach((sender) => {
        this.peerConnection?.removeTrack(sender);
      });
      this.senders = [];

      this.peerConnection.close();
      this.peerConnection = null;
    }

    // 清理音频元素
    if (this.localAudio) {
      this.localAudio.srcObject = null;
      this.localAudio.remove();
      this.localAudio = null;
    }

    this.remoteAudios.forEach((audio) => {
      audio.srcObject = null;
      audio.remove();
    });
    this.remoteAudios = [];

    // 移除音频容器
    this.audioContainer.remove();

    // 清除房间信息
    this.currentRoomId = null;
    this.currentDisplay = null;
    this.connected = false;
    this.participants.clear();

    if (this.keepaliveInterval) {
      clearInterval(this.keepaliveInterval);
      this.keepaliveInterval = null;
    }

    this.reconnectAttempts = 0;
  }

  public getLocalStream(): MediaStream | null {
    return this.localStream;
  }

  public async createRoom(roomId: string, display: string): Promise<void> {
    // 如果已经连接，直接返回
    if (this.connected) {
      console.log("Already connected to room:", roomId);
      return;
    }

    try {
      // 1. 创建房间请求
      const createResponse = await axios.post(
        `/api/janus/${this.config.sessionId}/${this.config.handleId}`,
        {
          janus: "message",
          transaction: this.generateTransactionId(),
          body: {
            request: "create",
            room: parseInt(roomId),
            description: `Room ${roomId}`,
            sampling_rate: 16000,
            spatial_audio: false,
          },
        }
      );

      // 检查响应状态
      if (createResponse.data.success) {
        this.connected = true;
        console.log("Room created successfully:", roomId);

        // 更新参与者列表
        this.updateParticipants([
          {
            id: this.config.handleId,
            display: display,
            setup: true,
            muted: false,
          },
        ]);

        return;
      }

      throw new Error("Failed to create room: Invalid response");
    } catch (error) {
      // 如果是房间已存在的错误，不需要抛出异常
      if (error instanceof Error && error.message.includes("already exists")) {
        this.connected = true;
        console.log("Room already exists:", roomId);
        return;
      }
      throw error;
    }
  }

  public setParticipantsChangeHandler(handler: (participants: any[]) => void) {
    this.onParticipantsChange = handler;
  }

  private updateParticipants(participantsList: any[]) {
    participantsList.forEach((participant) => {
      this.participants.set(participant.id, participant);
    });

    if (this.onParticipantsChange) {
      this.onParticipantsChange(Array.from(this.participants.values()));
    }
  }

  private processSdp(sdp: string): string {
    const lines = sdp.split("\n");
    const processedLines = [];
    let audioMLineIndex = -1;
    let inAudioSection = false;

    // 添加必要的OPUS编解码器配置
    const opusConfig = [
      "a=rtpmap:111 opus/48000/2",
      "a=fmtp:111 minptime=10;useinbandfec=1;stereo=0",
      "a=rtcp-fb:111 transport-cc",
      "a=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level",
    ];

    for (let i = 0; i < lines.length; i++) {
      const line = lines[i];

      // 跳过视频相关的行
      if (line.startsWith("m=video")) {
        inAudioSection = false;
        continue;
      }

      // 标记音频部分的开始
      if (line.startsWith("m=audio")) {
        audioMLineIndex = processedLines.length;
        inAudioSection = true;
        // 使用标准的音频m-line
        processedLines.push("m=audio 9 UDP/TLS/RTP/SAVPF 111");
        // 添加OPUS编解码器配置
        opusConfig.forEach((config) => processedLines.push(config));
        continue;
      }

      // 在音频部分中
      if (inAudioSection) {
        // 跳过原有的编解码器配置行
        if (
          line.startsWith("a=rtpmap:") ||
          line.startsWith("a=fmtp:") ||
          line.startsWith("a=rtcp-fb:")
        ) {
          continue;
        }
      }

      // 保留其他必要的SDP行
      if (
        !line.includes("video") &&
        !line.startsWith("b=AS:") &&
        !line.startsWith("a=mid:video")
      ) {
        processedLines.push(line);
      }
    }

    // 如果没有找到音频部分，添加一个
    if (audioMLineIndex === -1) {
      processedLines.push("m=audio 9 UDP/TLS/RTP/SAVPF 111");
      opusConfig.forEach((config) => processedLines.push(config));
    }

    return processedLines.join("\n");
  }

  private async handleNegotiation() {
    try {
      console.log("[Janus] Starting negotiation");

      if (!this.peerConnection) {
        throw new Error("PeerConnection not initialized");
      }

      // 检查连接状态
      if (this.peerConnection.signalingState === "closed") {
        console.log("[Janus] Connection is closed, reinitializing...");
        this.initializePeerConnection();
      }

      // 创建新的 offer
      const offer = await this.peerConnection.createOffer({
        offerToReceiveAudio: true,
        offerToReceiveVideo: false,
      });

      console.log("[Janus] Original offer SDP:", offer.sdp);

      // 处理 SDP
      const processedSdp = this.processSdp(offer.sdp || "");
      console.log("[Janus] Processed offer SDP:", processedSdp);

      // 先设置本地描述
      await this.peerConnection.setLocalDescription(offer);
      console.log("[Janus] Local description set");

      // 发送offer并获取响应
      const response = await this.sendOffer(offer as RTCSessionDescription);

      if (response?.data?.jsep) {
        const answerSdp = response.data.jsep.sdp;
        console.log("[Janus] Answer SDP:", answerSdp);

        const answer = new RTCSessionDescription({
          type: "answer",
          sdp: answerSdp,
        });
        await this.peerConnection.setRemoteDescription(answer);
        console.log("[Janus] Remote description set");
      }
    } catch (error) {
      console.error("[Janus] Negotiation failed:", error);
      throw error;
    }
  }

  private async sendOffer(offer: RTCSessionDescription) {
    try {
      const message = {
        janus: "message",
        body: {
          request: "configure",
          muted: false,
          quality: 1,
        },
        jsep: offer,
      };

      const response = await axios.post(
        `/api/janus/${this.config.sessionId}/${this.config.handleId}`,
        message
      );
      return response;
    } catch (error) {
      console.error("[Janus] Failed to send offer:", error);
      throw error;
    }
  }

  // 音频源切换方法
  public switchAudioSource(source: "local" | "remote"): void {
    this.currentAudioSource = source;
    console.log("Switching audio source to:", source);

    // 切换本地音频
    if (this.localAudio) {
      this.localAudio.muted = source === "remote";
      console.log("Local audio muted:", source === "remote");
    }

    // 切换远程音频
    this.remoteAudios.forEach((audio) => {
      audio.muted = source === "local";
      console.log("Remote audio muted:", source === "local");
    });
  }

  // 音量控制方法
  public setVolume(volume: number): void {
    const normalizedVolume = Math.max(0, Math.min(1, volume / 100));
    console.log("Setting volume to:", normalizedVolume);

    if (this.currentAudioSource === "local" && this.localAudio) {
      this.localAudio.volume = normalizedVolume;
    } else {
      this.remoteAudios.forEach((audio) => {
        audio.volume = normalizedVolume;
      });
    }

    this.volume = volume;
  }

  // 静音控制方法
  public setMuted(muted: boolean): void {
    console.log("Setting muted state to:", muted);

    if (this.currentAudioSource === "local" && this.localAudio) {
      this.localAudio.muted = muted;
    } else {
      this.remoteAudios.forEach((audio) => {
        audio.muted = muted;
      });
    }
  }

  private startKeepalive() {
    // 清除现有的心跳定时器
    if (this.keepaliveInterval) {
      clearInterval(this.keepaliveInterval);
    }

    // 每30秒发送一次心跳
    this.keepaliveInterval = setInterval(async () => {
      try {
        await this.sendKeepalive();
      } catch (error) {
        console.error("Keepalive failed:", error);
        this.handleConnectionError();
      }
    }, 30000); // 30秒
  }

  private async sendKeepalive() {
    try {
      const response = await axios.post("/janus", {
        janus: "keepalive",
        session_id: this.config.sessionId,
        transaction: this.generateTransactionId(),
      });

      if (response.data.janus === "ack") {
        console.log("Keepalive successful");
        this.reconnectAttempts = 0; // 重置重连计数
      } else {
        throw new Error("Invalid keepalive response");
      }
    } catch (error) {
      throw error;
    }
  }

  private async handleConnectionError() {
    if (this.reconnectAttempts < this.maxReconnectAttempts) {
      this.reconnectAttempts++;
      console.log(
        `Attempting to reconnect (${this.reconnectAttempts}/${this.maxReconnectAttempts})`
      );

      try {
        // 重新初始化连接
        this.initializePeerConnection();
        await this.initializeMedia();

        // 使用保存的房间信息重新加入
        if (this.currentRoomId && this.currentDisplay) {
          await this.joinRoom(this.currentRoomId, this.currentDisplay);
        }

        this.startKeepalive();
        console.log("Reconnection successful");
      } catch (error) {
        console.error("Reconnection failed:", error);
        setTimeout(() => this.handleConnectionError(), 5000);
      }
    } else {
      console.error("Max reconnection attempts reached");
      this.disconnect();
    }
  }

  // 在组件卸载时清理
  public cleanup() {
    this.disconnect();
    if (this.audioContainer && this.audioContainer.parentNode) {
      this.audioContainer.parentNode.removeChild(this.audioContainer);
    }
  }

  // 添加音频状态检查方法
  private checkAudioState() {
    if (this.localStream) {
      const audioTracks = this.localStream.getAudioTracks();
      audioTracks.forEach((track) => {
        console.log("[Janus] Local audio track state:", {
          enabled: track.enabled,
          muted: track.muted,
          readyState: track.readyState,
          constraints: track.getConstraints(),
        });
      });
    }

    this.remoteAudios.forEach((audio, index) => {
      console.log(`[Janus] Remote audio ${index} state:`, {
        readyState: audio.readyState,
        paused: audio.paused,
        muted: audio.muted,
        volume: audio.volume,
        currentTime: audio.currentTime,
      });
    });
  }

  // 添加新的方法来处理参与者列表更新
  private async updateParticipantsList() {
    try {
      const response = await axios.post(
        `/api/janus/${this.config.sessionId}/${this.config.handleId}`,
        {
          janus: "message",
          body: {
            request: "listparticipants",
            room: parseInt(this.currentRoomId!),
          },
          transaction: this.generateTransactionId(),
        }
      );

      if (response?.data?.plugindata?.data?.participants) {
        this.updateParticipants(response.data.plugindata.data.participants);
      }
    } catch (error) {
      console.error("[Janus] Failed to update participants list:", error);
    }
  }

  // 添加新方法来处理音频自动播放限制
  public async tryUnlockAudio() {
    try {
      // 创建一个短暂的音频上下文来解除自动播放限制
      const audioContext = new (window.AudioContext ||
        (window as any).webkitAudioContext)();
      await audioContext.resume();

      // 确保所有现有的音频元素都尝试播放
      const playPromises = [...this.remoteAudios, this.localAudio]
        .filter((audio) => audio)
        .map((audio) => {
          if (audio!.paused) {
            return audio!.play().catch((e) => {
              console.warn("[Janus] Failed to play audio:", e);
            });
          }
          return Promise.resolve();
        });

      await Promise.all(playPromises);
      console.log("[Janus] Audio unlocked successfully");
    } catch (error) {
      console.warn("[Janus] Failed to unlock audio:", error);
    }
  }
}
