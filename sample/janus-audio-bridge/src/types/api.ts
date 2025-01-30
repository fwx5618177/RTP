export interface ApiResponse<T = any> {
  success: boolean;
  data: T;
  code: number;
  time: number;
}

export interface Room {
  roomId: string;
  name: string;
  createdAt: string;
  creator: string;
  maxParticipants: number;
  janusSessionId: string;
  janusHandleId: string;
  wsUrl: string;
  participantsCount: number;
}

export interface Participant {
  userId: string;
  display: string;
  joinedAt: string;
  audioMuted: boolean;
  setup: boolean;
}

export interface ParticipantListResponse {
  participants: Participant[];
  count: number;
  roomId: number;
}

export interface CreateRoomRequest {
  userId: string;
  roomName: string;
  config: {
    maxParticipants: number;
    audioEnabled: boolean;
    videoEnabled: boolean;
    audioConfig?: {
      sampleRate: number;
      channels: number;
      codec: string;
    };
  };
}

export interface JoinRoomRequest {
  roomId: string;
  userId: string;
  display: string;
}
