import { useState, useEffect, useCallback } from "react";
import { api } from "./api";
import {
  JanusResponse,
  JanusInstance,
  SipBridgeConfig,
  BridgeUpdateConfig,
} from "../types/janus";

export const useJanus = () => {
  const [janus, setJanus] = useState<JanusInstance | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isInitializing, setIsInitializing] = useState(true);

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
      } else {
        setError("Failed to initialize Janus session");
      }
    } catch (err) {
      setError("Failed to connect to Janus server");
      console.error("Janus initialization error:", err);
    } finally {
      setIsInitializing(false);
    }
  }, []);

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
        const errorMessage =
          err instanceof Error ? err.message : "Failed to create SIP bridge";
        console.error(errorMessage, err);
        throw new Error(errorMessage);
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
        const errorMessage =
          err instanceof Error ? err.message : "Failed to update SIP bridge";
        console.error(errorMessage, err);
        throw new Error(errorMessage);
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
      const errorMessage =
        err instanceof Error ? err.message : "Failed to disconnect SIP bridge";
      console.error(errorMessage, err);
      throw new Error(errorMessage);
    }
  }, [janus]);

  const reconnect = useCallback(async () => {
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
