import { useCallback } from "react";
import { api } from "./api";

interface CallResponse {
  success: boolean;
  message?: string;
  data?: {
    actionId: string;
    extension: string;
    roomId: string;
    status: string;
  };
}

interface StatusResponse {
  success: boolean;
  message?: string;
  data?: {
    channel: string;
    status: Record<string, any>;
  };
}

export const useSipCall = () => {
  const makeCall = useCallback(
    async (extension: string, roomId: string): Promise<CallResponse> => {
      try {
        const response = await api.post("/pbx/call", {
          extension,
          roomId,
        });

        return response.data;
      } catch (error) {
        if (error instanceof Error) {
          return {
            success: false,
            message: error.message,
          };
        }
        return {
          success: false,
          message: "Failed to make call",
        };
      }
    },
    []
  );

  const getCallStatus = useCallback(
    async (channel: string): Promise<StatusResponse> => {
      try {
        const response = await api.post("/pbx/status", {
          channel,
        });

        return response.data;
      } catch (error) {
        if (error instanceof Error) {
          return {
            success: false,
            message: error.message,
          };
        }
        return {
          success: false,
          message: "Failed to get call status",
        };
      }
    },
    []
  );

  const getActiveChannels = useCallback(async (): Promise<StatusResponse> => {
    try {
      const response = await api.get("/pbx/channels");

      return response.data;
    } catch (error) {
      if (error instanceof Error) {
        return {
          success: false,
          message: error.message,
        };
      }
      return {
        success: false,
        message: "Failed to get active channels",
      };
    }
  }, []);

  return {
    makeCall,
    getCallStatus,
    getActiveChannels,
  };
};
