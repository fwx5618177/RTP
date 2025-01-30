import { WebSocketManager, JanusMessage } from "../utils/websocket";
import axios from "axios";

interface JanusConfig {
  sessionId: string;
  handleId: string;
}

export class JanusClient {
  private peerConnection: RTCPeerConnection | null = null;
  private localStream: MediaStream | null = null;
  private config: JanusConfig;
  private onStateChange?: (state: { isMuted: boolean }) => void;
  private connected: boolean = false;
  private participants: Map<string, any> = new Map();
  private onParticipantsChange?: (participants: any[]) => void;

  constructor(config: JanusConfig) {
    this.config = config;
    this.initializePeerConnection();
  }

  private initializePeerConnection() {
    this.peerConnection = new RTCPeerConnection({
      iceServers: [], // 局域网不需要 STUN/TURN 服务器
    });

    this.peerConnection.onicecandidate = (event) => {
      if (event.candidate) {
        this.sendTrickle(event.candidate);
      }
    };

    this.peerConnection.ontrack = (event) => {
      console.log("Received remote track:", event.track);
      const audio = new Audio();
      audio.srcObject = event.streams[0];
      audio.play();
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
    return this.retryOperation(async () => {
      try {
        this.localStream = await navigator.mediaDevices.getUserMedia({
          audio: {
            echoCancellation: true,
            noiseSuppression: true,
            autoGainControl: true,
          },
          video: false,
        });

        this.localStream.getTracks().forEach((track) => {
          this.peerConnection?.addTrack(track, this.localStream!);
        });
      } catch (error) {
        console.error("Failed to initialize media:", error);
        throw error;
      }
    });
  }

  private generateTransactionId(): string {
    return (
      Math.random().toString(36).substring(2, 15) +
      Math.random().toString(36).substring(2, 15)
    );
  }

  // 加入房间
  public async joinRoom(roomId: string, display: string): Promise<void> {
    // 如果已经连接，直接返回
    if (this.connected) {
      console.log("Already connected to room:", roomId);
      return;
    }

    try {
      // 1. 发送加入房间请求
      const joinResponse = await axios.post(
        `/api/janus/${this.config.sessionId}/${this.config.handleId}`,
        {
          janus: "message",
          transaction: this.generateTransactionId(),
          body: {
            request: "join",
            room: parseInt(roomId),
            display: display,
            muted: false,
          },
        }
      );

      if (joinResponse.data.success) {
        this.connected = true;
        console.log("Successfully joined room:", roomId);

        // 其他初始化代码保持不变...
        await this.initializeWebRTC();
        return;
      }

      throw new Error("Failed to join room: Invalid response");
    } catch (error) {
      console.error("Error joining room:", error);
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
    if (this.peerConnection) {
      this.peerConnection.close();
      this.peerConnection = null;
    }

    if (this.localStream) {
      this.localStream.getTracks().forEach((track) => track.stop());
      this.localStream = null;
    }

    this.connected = false;
    this.participants.clear();
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

  private async initializeWebRTC(): Promise<void> {
    if (!this.peerConnection) {
      throw new Error("PeerConnection not initialized");
    }

    try {
      // 创建并发送 Offer
      const offer = await this.peerConnection.createOffer({
        offerToReceiveAudio: true,
      });
      await this.peerConnection.setLocalDescription(offer);

      // 发送 Offer 到 Janus
      const offerResponse = await axios.post(
        `/api/janus/${this.config.sessionId}/${this.config.handleId}`,
        {
          janus: "message",
          transaction: this.generateTransactionId(),
          body: {
            request: "configure",
            audio: true,
            video: false,
          },
          jsep: offer,
        }
      );

      if (offerResponse.data.jsep) {
        await this.peerConnection.setRemoteDescription(
          new RTCSessionDescription(offerResponse.data.jsep)
        );
      }
    } catch (error) {
      console.error("Failed to initialize WebRTC:", error);
      throw error;
    }
  }
}
