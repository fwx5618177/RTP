import { useState, useEffect, useCallback } from "react";
import { api } from "../services/api";

interface JanusResponse {
  success: boolean;
  error?: string;
  data?: any;
}

interface SipBridgeConfig {
  roomId: number;
  uri: string;
  muted?: boolean;
  quality?: number;
}

interface BridgeUpdateConfig {
  muted: boolean;
  quality?: number;
}

interface JanusInstance {
  sessionId: string;
  audioBridgeHandleId: string;
  sipHandleId: string;
  on: (event: string, callback: (data: any) => void) => void;
  off: (event: string, callback: (data: any) => void) => void;
}

export const useJanus = () => {
  const [janus, setJanus] = useState<JanusInstance | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const initJanus = async () => {
      try {
        const response = await api.post<JanusResponse>("/api/janus/session");
        if (response.data.success) {
          const instance: JanusInstance = {
            ...response.data.data,
            on: (event: string, callback: (data: any) => void) => {
              // 实现事件监听
              window.addEventListener(`janus:${event}`, (e: any) =>
                callback(e.detail)
              );
            },
            off: (event: string, callback: (data: any) => void) => {
              // 移除事件监听
              window.removeEventListener(`janus:${event}`, (e: any) =>
                callback(e.detail)
              );
            },
          };
          setJanus(instance);
        } else {
          setError("Failed to initialize Janus session");
        }
      } catch (err) {
        setError("Failed to connect to Janus server");
        console.error("Janus initialization error:", err);
      }
    };

    initJanus();

    return () => {
      if (janus) {
        // 清理 Janus 会话
        api
          .delete(`/api/janus/session/${janus.sessionId}`)
          .catch(console.error);
      }
    };
  }, []);

  const createSipBridge = useCallback(
    async (config: SipBridgeConfig): Promise<JanusResponse> => {
      if (!janus) {
        throw new Error("Janus not initialized");
      }

      try {
        const response = await api.post<JanusResponse>(
          `/api/janus/sip/bridge/${janus.sessionId}`,
          config
        );
        return response.data;
      } catch (err) {
        console.error("Failed to create SIP bridge:", err);
        throw err;
      }
    },
    [janus]
  );

  const updateSipBridge = useCallback(
    async (config: BridgeUpdateConfig): Promise<JanusResponse> => {
      if (!janus) {
        throw new Error("Janus not initialized");
      }

      try {
        const response = await api.patch<JanusResponse>(
          `/api/janus/sip/bridge/${janus.sessionId}`,
          config
        );
        return response.data;
      } catch (err) {
        console.error("Failed to update SIP bridge:", err);
        throw err;
      }
    },
    [janus]
  );

  const disconnectSipBridge = useCallback(async (): Promise<JanusResponse> => {
    if (!janus) {
      throw new Error("Janus not initialized");
    }

    try {
      const response = await api.delete<JanusResponse>(
        `/api/janus/sip/bridge/${janus.sessionId}`
      );
      return response.data;
    } catch (err) {
      console.error("Failed to disconnect SIP bridge:", err);
      throw err;
    }
  }, [janus]);

  return {
    janus,
    error,
    createSipBridge,
    updateSipBridge,
    disconnectSipBridge,
  };
};
