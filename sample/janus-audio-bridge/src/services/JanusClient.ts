import axios from "axios";
import { api } from "./api";
import { JanusResponse } from "../types/janus";

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
    const configuration: RTCConfiguration = {
      iceServers: [
        {
          urls: "stun:stun.l.google.com:19302",
        },
      ],
    };

    this.peerConnection = new RTCPeerConnection(configuration);

    this.peerConnection.onicecandidate = async (event) => {
      if (event.candidate) {
        await this.sendTrickle(event.candidate);
      }
    };

    this.peerConnection.ontrack = (event) => {
      const audio = new Audio();
      audio.srcObject = event.streams[0];
      audio.autoplay = true;
      this.remoteAudios.push(audio);
      this.audioContainer.appendChild(audio);
    };

    this.peerConnection.oniceconnectionstatechange = () => {
      switch (this.peerConnection?.iceConnectionState) {
        case "disconnected":
        case "failed":
          this.handleConnectionError();
          break;
        case "connected":
          this.connected = true;
          this.reconnectAttempts = 0;
          break;
      }
    };
  }

  private async retryOperation<T>(
    operation: () => Promise<T>,
    maxRetries: number = 3,
    delayMs: number = 2000
  ): Promise<T> {
    let lastError: Error | null = null;
    for (let i = 0; i < maxRetries; i++) {
      try {
        return await operation();
      } catch (error) {
        lastError = error instanceof Error ? error : new Error(String(error));
        if (i < maxRetries - 1) {
          await new Promise((resolve) => setTimeout(resolve, delayMs));
        }
      }
    }
    throw lastError;
  }

  // 初始化媒体流
  public async initializeMedia(): Promise<void> {
    try {
      this.localStream = await navigator.mediaDevices.getUserMedia({
        audio: true,
        video: false,
      });

      if (!this.peerConnection) {
        this.initializePeerConnection();
      }

      this.localStream.getTracks().forEach((track) => {
        if (this.peerConnection && this.localStream) {
          const sender = this.peerConnection.addTrack(track, this.localStream);
          this.senders.push(sender);
        }
      });

      // 创建本地音频预览
      if (!this.localAudio) {
        this.localAudio = new Audio();
        this.localAudio.muted = true;
        this.localAudio.srcObject = this.localStream;
        this.localAudio.play().catch(console.error);
      }
    } catch (error) {
      console.error("Failed to initialize media:", error);
      throw error;
    }
  }

  private generateTransactionId(): string {
    return Math.random().toString(36).substring(2, 15);
  }

  // 加入房间
  public async joinRoom(roomId: string, display: string): Promise<void> {
    try {
      await this.initializeMedia();

      const offer = await this.peerConnection?.createOffer({
        offerToReceiveAudio: true,
      });

      if (!offer) {
        throw new Error("Failed to create offer");
      }

      await this.peerConnection?.setLocalDescription(offer);

      const response = await api.post(`/api/janus/room/join`, {
        roomId,
        display,
        jsep: offer,
      });

      if (!response.data.success) {
        throw new Error(response.data.error || "Failed to join room");
      }

      const { jsep } = response.data;
      if (jsep) {
        await this.peerConnection?.setRemoteDescription(
          new RTCSessionDescription(jsep)
        );
      }

      this.currentRoomId = roomId;
      this.currentDisplay = display;
      this.startKeepalive();
    } catch (error) {
      console.error("Failed to join room:", error);
      throw error;
    }
  }

  private async sendTrickle(candidate: RTCIceCandidate): Promise<void> {
    try {
      await api.post(
        `/api/janus/${this.config.sessionId}/${this.config.handleId}/trickle`,
        {
          candidate,
          transaction: this.generateTransactionId(),
        }
      );
    } catch (error) {
      console.error("Failed to send trickle:", error);
    }
  }

  public async configure(options: {
    muted: boolean;
    quality?: number;
  }): Promise<void> {
    try {
      if (this.localStream) {
        this.localStream.getAudioTracks().forEach((track) => {
          track.enabled = !options.muted;
        });
      }

      const response = await api.post(
        `/api/janus/${this.config.sessionId}/${this.config.handleId}/message`,
        {
          janus: "message",
          body: {
            request: "configure",
            muted: options.muted,
            quality: options.quality,
          },
          transaction: this.generateTransactionId(),
        }
      );

      if (!response.data.success) {
        throw new Error(response.data.error || "Failed to configure");
      }

      this.onStateChange?.({ isMuted: options.muted });
    } catch (error) {
      console.error("Failed to configure:", error);
      throw error;
    }
  }

  public setOnStateChange(
    callback: (state: { isMuted: boolean }) => void
  ): void {
    this.onStateChange = callback;
  }

  public disconnect(): void {
    try {
      if (this.keepaliveInterval) {
        clearInterval(this.keepaliveInterval);
        this.keepaliveInterval = null;
      }

      if (this.localStream) {
        this.localStream.getTracks().forEach((track) => track.stop());
        this.localStream = null;
      }

      this.senders.forEach((sender) => {
        if (this.peerConnection) {
          this.peerConnection.removeTrack(sender);
        }
      });
      this.senders = [];

      if (this.peerConnection) {
        this.peerConnection.close();
        this.peerConnection = null;
      }

      this.remoteAudios.forEach((audio) => {
        audio.pause();
        audio.remove();
      });
      this.remoteAudios = [];

      if (this.localAudio) {
        this.localAudio.pause();
        this.localAudio.remove();
        this.localAudio = null;
      }

      this.connected = false;
      this.currentRoomId = null;
      this.currentDisplay = null;

      api
        .delete(`/api/janus/session/${this.config.sessionId}`)
        .catch(console.error);
    } catch (error) {
      console.error("Error during disconnect:", error);
    }
  }

  public getLocalStream(): MediaStream | null {
    return this.localStream;
  }

  public async createRoom(roomId: string, display: string): Promise<void> {
    try {
      const response = await api.post("/api/janus/room", {
        roomId,
        display,
        sessionId: this.config.sessionId,
        handleId: this.config.handleId,
      });

      if (!response.data.success) {
        throw new Error(response.data.error || "Failed to create room");
      }

      await this.joinRoom(roomId, display);
    } catch (error) {
      console.error("Failed to create room:", error);
      throw error;
    }
  }

  public setParticipantsChangeHandler(handler: (participants: any[]) => void) {
    this.onParticipantsChange = handler;
  }

  private updateParticipants(participantsList: any[]) {
    this.participants.clear();
    participantsList.forEach((participant) => {
      this.participants.set(participant.id, participant);
    });
    this.onParticipantsChange?.(participantsList);
  }

  private processSdp(sdp: string): string {
    const lines = sdp.split("\r\n");
    const processedLines = lines.map((line) => {
      // 添加 opus 的首选项
      if (line.startsWith("a=rtpmap") && line.includes("opus")) {
        return line + "\r\na=fmtp:111 minptime=10;useinbandfec=1";
      }
      // 设置音频比特率
      if (line.startsWith("m=audio")) {
        return line + "\r\nb=AS:64";
      }
      return line;
    });
    return processedLines.join("\r\n");
  }

  private async handleNegotiation() {
    try {
      if (!this.peerConnection) {
        throw new Error("PeerConnection not initialized");
      }

      const offer = await this.peerConnection.createOffer({
        offerToReceiveAudio: true,
      });

      // 处理 SDP
      const processedSdp = this.processSdp(offer.sdp || "");
      const processedOffer = new RTCSessionDescription({
        type: offer.type,
        sdp: processedSdp,
      });

      await this.peerConnection.setLocalDescription(processedOffer);

      // 等待 ICE 收集完成
      if (this.peerConnection.iceGatheringState !== "complete") {
        await new Promise<void>((resolve) => {
          const checkState = () => {
            if (this.peerConnection?.iceGatheringState === "complete") {
              resolve();
            } else {
              setTimeout(checkState, 100);
            }
          };
          checkState();
        });
      }

      await this.sendOffer(processedOffer);
    } catch (error) {
      console.error("Negotiation failed:", error);
      throw error;
    }
  }

  private async sendOffer(offer: RTCSessionDescription) {
    try {
      const response = await api.post(
        `/api/janus/${this.config.sessionId}/${this.config.handleId}/message`,
        {
          janus: "message",
          body: {
            request: "configure",
            audio: true,
          },
          jsep: offer,
          transaction: this.generateTransactionId(),
        }
      );

      if (response.data.jsep) {
        await this.peerConnection?.setRemoteDescription(
          new RTCSessionDescription(response.data.jsep)
        );
      }
    } catch (error) {
      console.error("Failed to send offer:", error);
      throw error;
    }
  }

  // 音频源切换方法
  public switchAudioSource(source: "local" | "remote"): void {
    this.currentAudioSource = source;
    if (this.localAudio) {
      this.localAudio.muted = source === "remote";
    }
    this.remoteAudios.forEach((audio) => {
      audio.muted = source === "local";
    });
  }

  // 音量控制方法
  public setVolume(volume: number): void {
    this.volume = Math.max(0, Math.min(100, volume));
    const normalizedVolume = this.volume / 100;

    if (this.localAudio) {
      this.localAudio.volume = normalizedVolume;
    }
    this.remoteAudios.forEach((audio) => {
      audio.volume = normalizedVolume;
    });
  }

  // 静音控制方法
  public setMuted(muted: boolean): void {
    if (this.localStream) {
      this.localStream.getAudioTracks().forEach((track) => {
        track.enabled = !muted;
      });
      this.onStateChange?.({ isMuted: muted });
    }
  }

  private startKeepalive() {
    if (this.keepaliveInterval) {
      clearInterval(this.keepaliveInterval);
    }

    this.keepaliveInterval = setInterval(async () => {
      try {
        await this.sendKeepalive();
      } catch (error) {
        console.error("Keepalive failed:", error);
        this.handleConnectionError();
      }
    }, 30000); // Send keepalive every 30 seconds
  }

  private async sendKeepalive() {
    try {
      await api.post(
        `/api/janus/${this.config.sessionId}/${this.config.handleId}/message`,
        {
          janus: "message",
          body: {
            request: "keepalive",
          },
          transaction: this.generateTransactionId(),
        }
      );
    } catch (error) {
      console.error("Failed to send keepalive:", error);
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
        // Stop keepalive during reconnection
        if (this.keepaliveInterval) {
          clearInterval(this.keepaliveInterval);
          this.keepaliveInterval = null;
        }

        // Create new session
        const response = await api.post("/api/janus/session");
        if (response.data.success) {
          this.config.sessionId = response.data.data.sessionId;
          this.config.handleId = response.data.data.handleId;

          // Restart keepalive
          this.startKeepalive();

          // Reinitialize peer connection
          this.initializePeerConnection();

          // Rejoin room if was in one
          if (this.currentRoomId && this.currentDisplay) {
            await this.joinRoom(this.currentRoomId, this.currentDisplay);
          }

          console.log("Successfully reconnected to Janus server");
          this.reconnectAttempts = 0;
        }
      } catch (error) {
        console.error("Reconnection attempt failed:", error);
        // Try again after delay
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
        if (!track.enabled) {
          console.warn("Audio track is disabled");
        }
        if (track.muted) {
          console.warn("Audio track is muted");
        }
      });
    }

    this.remoteAudios.forEach((audio, index) => {
      if (audio.paused) {
        console.warn(`Remote audio ${index} is paused`);
      }
      if (audio.muted) {
        console.warn(`Remote audio ${index} is muted`);
      }
    });
  }

  // 添加新的方法来处理参与者列表更新
  private async updateParticipantsList() {
    if (!this.currentRoomId) return;

    try {
      const response = await api.get(
        `/api/rooms/${this.currentRoomId}/participants`
      );

      if (response.data.success) {
        this.updateParticipants(response.data.data.participants);
      }
    } catch (error) {
      console.error("Failed to update participants list:", error);
    }
  }

  // 添加新方法来处理音频自动播放限制
  public async tryUnlockAudio() {
    try {
      if (this.localAudio) {
        await this.localAudio.play();
      }
      this.remoteAudios.forEach((audio) => {
        audio.play().catch(console.error);
      });
    } catch (error) {
      console.error("Failed to unlock audio:", error);
    }
  }
}
