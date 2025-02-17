import axios from "axios";
import { ApiResponse, Room, ParticipantListResponse } from "../types/api";

const BASE_URL = process.env.REACT_APP_API_URL || "http://localhost:3000";

// 创建 axios 实例
export const api = axios.create({
  baseURL: BASE_URL,
  timeout: 10000,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
});

// 请求拦截器
api.interceptors.request.use(
  (config) => {
    // 在这里可以添加认证令牌等
    const token = localStorage.getItem("token");
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// 响应拦截器
api.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    if (error.response) {
      // 处理服务器错误
      switch (error.response.status) {
        case 401:
          // 未授权，清除 token 并重定向到登录页
          localStorage.removeItem("token");
          window.location.href = "/login";
          break;
        case 403:
          // 权限不足
          console.error("Permission denied");
          break;
        case 404:
          // 资源不存在
          console.error("Resource not found");
          break;
        case 500:
          // 服务器错误
          console.error("Server error");
          break;
        default:
          console.error("Network error");
      }
    } else if (error.request) {
      // 请求发送失败
      console.error("Request failed");
    } else {
      // 请求配置错误
      console.error("Request config error");
    }
    return Promise.reject(error);
  }
);

interface CallParams {
  extension: string;
  roomId: string;
}

export const makeCall = async (params: CallParams) => {
  const response = await axios.post(`${BASE_URL}/api/pbx/call`, params);
  return response.data;
};

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
    api.get<ApiResponse<ParticipantListResponse>>(
      `/rooms/${roomId}/participants`
    ),

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
