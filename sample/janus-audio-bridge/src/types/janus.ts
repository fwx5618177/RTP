export interface JanusResponse {
  success: boolean;
  error?: string;
  data?: any;
  jsep?: RTCSessionDescriptionInit;
}

export interface SipBridgeConfig {
  roomId: number;
  uri: string;
  muted?: boolean;
  quality?: number;
}

export interface BridgeUpdateConfig {
  muted: boolean;
  quality?: number;
}

export interface JanusInstance {
  sessionId: string;
  audioBridgeHandleId: string;
  sipHandleId: string;
  on: (event: string, callback: (data: any) => void) => void;
  off: (event: string, callback: (data: any) => void) => void;
}

export enum CallStatus {
  IDLE = "idle",
  CONNECTING = "connecting",
  CONNECTED = "connected",
  DISCONNECTING = "disconnecting",
  ERROR = "error",
}

export enum RoomStatus {
  IDLE = "idle",
  JOINING = "joining",
  JOINED = "joined",
  LEAVING = "leaving",
  ERROR = "error",
}

export interface JanusEvent {
  type: string;
  status?: string;
  error?: string;
  code?: number;
}

export interface RoomParticipant {
  id: string;
  display: string;
  muted: boolean;
  talking: boolean;
  joined_at: string;
}
