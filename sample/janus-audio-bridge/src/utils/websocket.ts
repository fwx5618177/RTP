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

  constructor(private url: string) {
    console.log("Initializing WebSocket with URL:", url);
  }

  public connect(): Promise<void> {
    return new Promise((resolve, reject) => {
      try {
        console.log("Attempting to connect to:", this.url);
        this.ws = new WebSocket(this.url);

        this.ws.onopen = () => {
          console.log("WebSocket successfully connected");
          resolve();
        };

        this.ws.onmessage = (event) => {
          try {
            const message: JanusMessage = JSON.parse(event.data);
            console.log("Received WebSocket message:", message);
            this.handleMessage(message);
          } catch (error) {
            console.error("Failed to parse WebSocket message:", error);
          }
        };

        this.ws.onerror = (error) => {
          const errorMessage = `WebSocket connection error: ${error instanceof Error ? error.message : "Unknown error"}`;
          console.error(errorMessage);
          reject(new Error(errorMessage));
        };

        this.ws.onclose = (event) => {
          console.log("WebSocket closed:", {
            code: event.code,
            reason: event.reason,
            wasClean: event.wasClean,
          });
        };

        setTimeout(() => {
          if (this.ws?.readyState !== WebSocket.OPEN) {
            const timeoutError = new Error("WebSocket connection timeout");
            console.error(timeoutError);
            this.ws?.close();
            reject(timeoutError);
          }
        }, 5000);
      } catch (error) {
        const errorMessage = `Failed to create WebSocket: ${error instanceof Error ? error.message : "Unknown error"}`;
        console.error(errorMessage);
        reject(new Error(errorMessage));
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
