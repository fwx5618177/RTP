import { useState } from "react";
import { Room } from "../components/Room";
import { JanusClient } from "../services/JanusClient";
import { roomApi } from "../services/api";
import LoadingState from "../components/LoadingState";
import ErrorState from "../components/ErrorState";

interface AudioRoomState {
  roomId?: string;
  isCreator: boolean;
  isJoined: boolean;
  loading: boolean;
  error?: string;
}

export const AudioRoom = () => {
  const [roomState, setRoomState] = useState<AudioRoomState>({
    isCreator: false,
    isJoined: false,
    loading: false,
  });

  const [currentUser, setCurrentUser] = useState({
    id: localStorage.getItem("userId") || "",
    display: localStorage.getItem("display") || "",
  });

  const [janusClient, setJanusClient] = useState<JanusClient | null>(null);
  const [audioStream, setAudioStream] = useState<MediaStream | null>(null);

  const handleCreateRoom = async (display: string) => {
    try {
      setRoomState((prev) => ({ ...prev, loading: true, error: undefined }));

      // 1. 创建房间
      const response = await roomApi.createRoom({
        userId: currentUser.id,
        roomName: `Room ${Date.now()}`,
        config: {
          audioConfig: {
            sampleRate: 16000,
            channels: 1,
            codec: "opus",
          },
        },
      });

      const roomData = response.data.data;

      // 2. 初始化 Janus 客户端
      const client = new JanusClient({
        sessionId: roomData.janusSessionId,
        handleId: roomData.janusHandleId,
      });

      // 3. 初始化媒体
      await client.initializeMedia();

      // 4. 创建并加入房间
      await client.createRoom(roomData.roomId, display);

      const stream = client.getLocalStream();
      setAudioStream(stream);

      setJanusClient(client);
      setCurrentUser((prev) => ({ ...prev, display }));
      setRoomState({
        roomId: roomData.roomId,
        isCreator: true,
        isJoined: true,
        loading: false,
      });
    } catch (error) {
      console.error("Failed to create room:", error);
      setRoomState((prev) => ({
        ...prev,
        loading: false,
        error: error instanceof Error ? error.message : "Failed to create room",
      }));
    }
  };

  const handleJoinRoom = async (roomId: string, display: string) => {
    try {
      setRoomState((prev) => ({ ...prev, loading: true, error: undefined }));

      // 1. 获取房间信息
      const response = await roomApi.getRoom(roomId);
      const roomData = response.data.data;

      // 2. 初始化 Janus 客户端
      const client = new JanusClient({
        sessionId: roomData.janusSessionId,
        handleId: roomData.janusHandleId,
      });

      // 3. 初始化媒体
      await client.initializeMedia();

      // 4. 加入房间
      await client.joinRoom(roomId, display);

      const stream = client.getLocalStream();
      setAudioStream(stream);

      setJanusClient(client);
      setCurrentUser((prev) => ({ ...prev, display }));
      setRoomState({
        roomId,
        isCreator: false,
        isJoined: true,
        loading: false,
      });
    } catch (error) {
      console.error("Failed to join room:", error);
      setRoomState((prev) => ({
        ...prev,
        loading: false,
        error: error instanceof Error ? error.message : "Failed to join room",
      }));
    }
  };

  const handleLeave = () => {
    janusClient?.disconnect?.();
    setJanusClient(null);
    setRoomState({
      isCreator: false,
      isJoined: false,
      loading: false,
    });
  };

  if (roomState.loading) {
    return <LoadingState message="Connecting to room..." />;
  }

  if (roomState.error) {
    return <ErrorState message={roomState.error} />;
  }

  return (
    <div className="audio-room">
      {!roomState.isJoined ? (
        <div className="join-controls">
          <button
            onClick={() => handleCreateRoom(currentUser.display || "Anonymous")}
          >
            Create New Room
          </button>
          {/* Add join room form */}
        </div>
      ) : (
        <Room
          roomId={roomState.roomId!}
          isCreator={roomState.isCreator}
          currentUser={currentUser}
          onLeave={handleLeave}
          audioStream={audioStream}
          janusClient={janusClient}
        />
      )}
    </div>
  );
};
