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
  private baseUrl: string;

  constructor(config: JanusConfig) {
    this.config = config;
    this.baseUrl = "http://localhost:9501";
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

  // 加入房间
  public async joinRoom(roomId: string, display: string): Promise<void> {
    return this.retryOperation(async () => {
      try {
        // 添加请求配置
        const requestConfig = {
          headers: {
            "Content-Type": "application/json",
          },
          withCredentials: true,
        };

        // 1. 发送加入房间请求
        const joinResponse = await axios.post(
          `${this.baseUrl}/api/janus/${this.config.sessionId}/${this.config.handleId}`,
          {
            janus: "message",
            body: {
              request: "join",
              room: parseInt(roomId),
              display: display,
              muted: false,
            },
          },
          requestConfig
        );

        console.log("Join room response:", joinResponse);

        // 2. 创建并发送 Offer
        const offer = await this.peerConnection!.createOffer({
          offerToReceiveAudio: true,
        });
        await this.peerConnection!.setLocalDescription(offer);

        // 3. 发送 Offer 到 Janus（同样添加请求配置）
        const offerResponse = await axios.post(
          `${this.baseUrl}/api/janus/${this.config.sessionId}/${this.config.handleId}`,
          {
            janus: "message",
            body: {
              request: "configure",
              room: parseInt(roomId),
              audio: true,
              video: false,
            },
            jsep: offer,
          },
          requestConfig
        );

        console.log("Offer response:", offerResponse);

        // 4. 处理 Janus 的 Answer
        if (offerResponse.data.jsep) {
          await this.peerConnection!.setRemoteDescription(
            new RTCSessionDescription(offerResponse.data.jsep)
          );
          this.connected = true;
        } else {
          throw new Error("No JSEP in response");
        }

        // 5. 获取并处理房间状态信息
        if (offerResponse.data.plugindata?.data?.participants) {
          console.log(
            "Current participants:",
            offerResponse.data.plugindata.data.participants
          );
        }
      } catch (error) {
        console.error("Failed to join room:", error);
        throw error;
      }
    });
  }

  private async sendTrickle(candidate: RTCIceCandidate) {
    try {
      await axios.post(
        `${this.baseUrl}/api/janus/${this.config.sessionId}/${this.config.handleId}/trickle`,
        {
          candidate: candidate,
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
          `${this.baseUrl}/api/janus/${this.config.sessionId}/${this.config.handleId}`,
          {
            janus: "message",
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
    }
    if (this.peerConnection) {
      this.peerConnection.close();
    }
    this.peerConnection = null;
    this.localStream = null;
    this.connected = false;
  }

  public getLocalStream(): MediaStream | null {
    return this.localStream;
  }

  public async createRoom(roomId: string, display: string): Promise<void> {
    return this.retryOperation(async () => {
      try {
        // 1. 创建房间请求
        const createResponse = await axios.post(
          `${this.baseUrl}/api/janus/${this.config.sessionId}/${this.config.handleId}`,
          {
            janus: "message",
            body: {
              request: "create",
              room: parseInt(roomId),
              description: `Room created by ${display}`,
              sampling_rate: 16000,
              spatial_audio: false,
            },
          }
        );

        if (createResponse.data.error) {
          throw new Error(
            `Failed to create room: ${createResponse.data.error}`
          );
        }

        // 2. 创建成功后，作为创建者加入房间
        await this.joinRoom(roomId, display);
      } catch (error) {
        console.error("Failed to create room:", error);
        throw error;
      }
    });
  }
}
