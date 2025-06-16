import { useState, useCallback } from "react";
import { api } from "../services/api";

interface CallResponse {
  success: boolean;
  message?: string;
  callId?: string;
}

interface StatusResponse {
  status: string;
  duration?: number;
  error?: string;
}

interface ActiveChannel {
  id: string;
  extension: string;
  roomId: string;
  status: string;
  startTime: string;
}

export const useSipCall = () => {
  const [isLoading, setIsLoading] = useState(false);

  const makeCall = useCallback(
    async (extension: string, roomId: string): Promise<CallResponse> => {
      setIsLoading(true);
      try {
        const response = await api.post("/api/pbx/call", {
          extension,
          roomId,
        });

        return {
          success: response.data.success,
          message: response.data.message,
          callId: response.data.callId,
        };
      } catch (error) {
        return {
          success: false,
          message:
            error instanceof Error ? error.message : "Failed to make call",
        };
      } finally {
        setIsLoading(false);
      }
    },
    []
  );

  const getCallStatus = useCallback(
    async (extension: string): Promise<StatusResponse> => {
      try {
        const response = await api.get(`/api/pbx/call/status/${extension}`);
        return {
          status: response.data.status,
          duration: response.data.duration,
          error: response.data.error,
        };
      } catch (error) {
        return {
          status: "error",
          error:
            error instanceof Error
              ? error.message
              : "Failed to get call status",
        };
      }
    },
    []
  );

  const getActiveChannels = useCallback(async (): Promise<ActiveChannel[]> => {
    try {
      const response = await api.get("/api/pbx/channels");
      return response.data.channels;
    } catch (error) {
      console.error("Failed to get active channels:", error);
      return [];
    }
  }, []);

  return {
    makeCall,
    getCallStatus,
    getActiveChannels,
    isLoading,
  };
};
