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
  const [isInitializing, setIsInitializing] = useState(true);
  const [retryCount, setRetryCount] = useState(0);
  const MAX_RETRIES = 3;

  const initJanus = useCallback(async () => {
    try {
      setIsInitializing(true);
      setError(null);

      const response = await api.post<JanusResponse>("/api/janus/session");
      if (response.data.success) {
        const instance: JanusInstance = {
          ...response.data.data,
          on: (event: string, callback: (data: any) => void) => {
            window.addEventListener(`janus:${event}`, (e: any) =>
              callback(e.detail)
            );
          },
          off: (event: string, callback: (data: any) => void) => {
            window.removeEventListener(`janus:${event}`, (e: any) =>
              callback(e.detail)
            );
          },
        };
        setJanus(instance);
        setRetryCount(0); // Reset retry count on success
      } else {
        throw new Error("Failed to initialize Janus session");
      }
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : "Unknown error";
      console.error("Janus initialization error:", errorMessage);

      if (retryCount < MAX_RETRIES) {
        setRetryCount((prev) => prev + 1);
        // Exponential backoff: 2^retry * 1000ms (1s, 2s, 4s)
        const retryDelay = Math.pow(2, retryCount) * 1000;
        console.log(
          `Retrying in ${retryDelay}ms... (Attempt ${retryCount + 1}/${MAX_RETRIES})`
        );
        setTimeout(initJanus, retryDelay);
      } else {
        setError(
          `Failed to connect to Janus server after ${MAX_RETRIES} attempts`
        );
      }
    } finally {
      setIsInitializing(false);
    }
  }, [retryCount]);

  useEffect(() => {
    initJanus();

    return () => {
      if (janus) {
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

  const reconnect = useCallback(async () => {
    setRetryCount(0); // Reset retry count before reconnecting
    await initJanus();
  }, [initJanus]);

  return {
    janus,
    error,
    isInitializing,
    createSipBridge,
    updateSipBridge,
    disconnectSipBridge,
    reconnect,
  };
};
