import { AxiosRequestConfig, AxiosResponse } from "axios";

export interface ApiResponse<T = any> {
  success: boolean;
  data: T;
  message?: string;
}

export interface Room {
  id: string;
  name: string;
  description?: string;
  participants: number;
  created_at: string;
  janusSessionId: string;
  janusHandleId: string;
  roomId: string;
}

export interface Participant {
  id: string;
  display: string;
  muted: boolean;
  talking: boolean;
  joined_at: string;
}

export interface ParticipantListResponse {
  participants: Participant[];
  total: number;
}

export interface SipCallResponse {
  success: boolean;
  callId?: string;
  message?: string;
  status?: string;
}

export interface CallStatusResponse {
  status: string;
  duration?: number;
  error?: string;
}

export interface ActiveChannel {
  id: string;
  extension: string;
  roomId: string;
  status: string;
  startTime: string;
}

declare module "axios" {
  export interface AxiosRequestConfig {
    _retry?: boolean;
  }
}

// 环境变量类型定义
interface ImportMetaEnv {
  readonly VITE_API_BASE_URL: string;
  readonly VITE_WS_URL: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
