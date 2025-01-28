export interface JanusMessage {
  janus: string;
  session_id?: string;
  handle_id?: string;
  transaction?: string;
  sender?: string;
  type?: string;
  plugindata?: {
    plugin: string;
    data: any;
  };
  jsep?: any;
}

export class WebSocketManager {
  private ws: WebSocket | null = null;
  private messageHandlers: Map<string, (message: JanusMessage) => void> =
    new Map();

  constructor(private url: string) {}

  public connect(): Promise<void> {
    return new Promise((resolve, reject) => {
      try {
        this.ws = new WebSocket(this.url);

        this.ws.onopen = () => {
          console.log("WebSocket connected");
          resolve();
        };

        this.ws.onmessage = (event) => {
          const message: JanusMessage = JSON.parse(event.data);
          this.handleMessage(message);
        };

        this.ws.onerror = (error) => {
          console.error("WebSocket error:", error);
          reject(error);
        };

        this.ws.onclose = () => {
          console.log("WebSocket closed");
        };
      } catch (error) {
        reject(error);
      }
    });
  }

  public send(message: any): void {
    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
      this.ws.send(JSON.stringify(message));
    } else {
      throw new Error("WebSocket is not connected");
    }
  }

  public addMessageHandler(
    type: string,
    handler: (message: JanusMessage) => void
  ): void {
    this.messageHandlers.set(type, handler);
  }

  private handleMessage(message: JanusMessage): void {
    const handler = this.messageHandlers.get(message.janus);
    if (handler) {
      handler(message);
    }
  }

  public disconnect(): void {
    if (this.ws) {
      this.ws.close();
      this.ws = null;
    }
  }
}
