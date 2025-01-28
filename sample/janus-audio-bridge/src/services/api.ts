import axios from "axios";
import {
  ApiResponse,
  Room,
  Participant,
  CreateRoomRequest,
  JoinRoomRequest,
} from "../types/api";

const api = axios.create({
  baseURL: "/api",
  headers: {
    "Content-Type": "application/json",
  },
});

export const roomApi = {
  createRoom: (data: {
    userId: string;
    roomName: string;
    config?: {
      maxParticipants?: number;
      audioEnabled?: boolean;
      videoEnabled?: boolean;
      audioConfig?: {
        sampleRate?: number;
        channels?: number;
        codec?: string;
      };
    };
  }) => api.post<ApiResponse<Room>>("/rooms", data),

  getRoom: async (roomId: string) => {
    try {
      const response = await api.get<ApiResponse<Room>>(`/rooms/${roomId}`);

      console.log("response", response);

      // 验证响应数据
      if (!response?.data?.data) {
        throw new Error("Invalid response format");
      }

      return response;
    } catch (error) {
      console.error("API Error:", error);
      throw error;
    }
  },

  joinRoom: (data: { roomId: string; userId: string; display?: string }) =>
    api.post<ApiResponse>("/rooms/join", data),

  leaveRoom: (data: { roomId: string; userId: string }) =>
    api.post<ApiResponse>("/rooms/leave", data),

  getParticipants: (roomId: string) =>
    api.get<ApiResponse<Participant[]>>(`/rooms/${roomId}/participants`),

  configure: (
    roomId: string,
    data: {
      userId: string;
      audio: {
        muted: boolean;
        quality?: number;
      };
    }
  ) => api.post<ApiResponse>(`/rooms/${roomId}/configure`, data),
};
