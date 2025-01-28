import { WebSocketManager, JanusMessage } from "../utils/websocket";

interface JanusConfig {
  sessionId: string;
  handleId: string;
  wsUrl: string;
}

export class JanusClient {
  private wsManager: WebSocketManager;
  private config: JanusConfig;
  private connected: boolean = false;
  private onStateChange?: (state: { isMuted: boolean }) => void;

  constructor(config: JanusConfig) {
    this.config = config;
    this.wsManager = new WebSocketManager(config.wsUrl);
    this.setupMessageHandlers();
  }

  private setupMessageHandlers(): void {
    this.wsManager.addMessageHandler("success", (message: JanusMessage) => {
      console.log("Janus success:", message);
    });

    this.wsManager.addMessageHandler("error", (message: JanusMessage) => {
      console.error("Janus error:", message);
    });

    this.wsManager.addMessageHandler("event", (message: JanusMessage) => {
      if (message.plugindata?.data.audiobridge === "event") {
        // 处理音频事件
        const event = message.plugindata.data;
        if (event.result?.participants) {
          // 更新参与者状态
          console.log("Participants updated:", event.result.participants);
        }
      }
    });
  }

  public async connect(): Promise<void> {
    try {
      console.log("Connecting to Janus with config:", {
        wsUrl: this.config.wsUrl,
        sessionId: this.config.sessionId,
        handleId: this.config.handleId,
      });

      await this.wsManager.connect();

      // 发送 attach 请求
      this.wsManager.send({
        janus: "attach",
        session_id: this.config.sessionId,
        handle_id: this.config.handleId,
        plugin: "janus.plugin.audiobridge",
        transaction: this.generateTransactionId(),
      });

      // 等待 attach 成功响应
      await new Promise<void>((resolve, reject) => {
        const timeout = setTimeout(() => {
          reject(new Error("Attach request timeout"));
        }, 5000);

        this.wsManager.addMessageHandler("success", (message: JanusMessage) => {
          if (
            message.transaction &&
            message.session_id === this.config.sessionId
          ) {
            clearTimeout(timeout);
            this.connected = true;
            resolve();
          }
        });

        this.wsManager.addMessageHandler("error", (message: JanusMessage) => {
          clearTimeout(timeout);
          reject(new Error(`Janus error: ${JSON.stringify(message)}`));
        });
      });
    } catch (error) {
      const errorMessage = `Failed to connect to Janus: ${error instanceof Error ? error.message : "Unknown error"}`;
      console.error(errorMessage);
      throw new Error(errorMessage);
    }
  }

  public async joinRoom(roomId: string, display: string): Promise<void> {
    if (!this.connected) {
      throw new Error("Not connected to Janus");
    }

    this.wsManager.send({
      janus: "message",
      session_id: this.config.sessionId,
      handle_id: this.config.handleId,
      body: {
        request: "join",
        room: parseInt(roomId),
        display: display,
        muted: false,
      },
      transaction: this.generateTransactionId(),
    });
  }

  public async configure(options: {
    muted: boolean;
    quality?: number;
  }): Promise<void> {
    if (!this.connected) {
      throw new Error("Not connected to Janus");
    }

    this.wsManager.send({
      janus: "message",
      session_id: this.config.sessionId,
      handle_id: this.config.handleId,
      body: {
        request: "configure",
        muted: options.muted,
        quality: options.quality || 1.0,
      },
      transaction: this.generateTransactionId(),
    });

    if (this.onStateChange) {
      this.onStateChange({ isMuted: options.muted });
    }
  }

  public setOnStateChange(
    callback: (state: { isMuted: boolean }) => void
  ): void {
    this.onStateChange = callback;
  }

  public disconnect(): void {
    if (this.connected) {
      // 发送 detach 请求
      this.wsManager.send({
        janus: "detach",
        session_id: this.config.sessionId,
        handle_id: this.config.handleId,
        transaction: this.generateTransactionId(),
      });

      this.wsManager.disconnect();
      this.connected = false;
    }
  }

  private generateTransactionId(): string {
    return Math.random().toString(36).substring(2, 15);
  }
}
