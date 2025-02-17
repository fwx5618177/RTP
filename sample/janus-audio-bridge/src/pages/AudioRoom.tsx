import React, { useState, useEffect } from "react";
import {
  Box,
  Button,
  TextField,
  Typography,
  Alert,
  List,
  ListItem,
  ListItemText,
} from "@mui/material";
import { useJanus } from "../services/useJanus";
import Room from "../components/Room";
import { JanusClient } from "../services/JanusClient";
import { roomApi } from "../services/api";
import LoadingState from "../components/LoadingState";
import ErrorState from "../components/ErrorState";
import { RoomStatus } from "../types/janus";

interface AudioRoomProps {
  onRoomStatusChange?: (status: RoomStatus) => void;
}

interface RoomState {
  roomId: string;
  isCreator: boolean;
  isJoined: boolean;
  loading: boolean;
  error: string | null;
}

const AudioRoom: React.FC<AudioRoomProps> = ({ onRoomStatusChange }) => {
  const [roomId, setRoomId] = useState<string>("");
  const [display, setDisplay] = useState<string>("");
  const [error, setError] = useState<string | null>(null);
  const [roomStatus, setRoomStatus] = useState<RoomStatus>(RoomStatus.IDLE);
  const [currentUser, setCurrentUser] = useState({
    id: localStorage.getItem("userId") || "",
    display: localStorage.getItem("display") || "",
  });
  const [janusClient, setJanusClient] = useState<JanusClient | null>(null);
  const [audioStream, setAudioStream] = useState<MediaStream | null>(null);
  const [roomState, setRoomState] = useState<RoomState>({
    roomId: "",
    isCreator: false,
    isJoined: false,
    loading: false,
    error: null,
  });

  const {
    janus,
    error: janusError,
    isInitializing,
    createSipBridge,
  } = useJanus();

  useEffect(() => {
    if (janusError) {
      setError(janusError);
      setRoomStatus(RoomStatus.ERROR);
      onRoomStatusChange?.(RoomStatus.ERROR);
    }
  }, [janusError, onRoomStatusChange]);

  useEffect(() => {
    if (!janus) return;

    const handleRoomEvent = (event: any) => {
      switch (event.type) {
        case "joined":
          setRoomStatus(RoomStatus.JOINED);
          onRoomStatusChange?.(RoomStatus.JOINED);
          break;
        case "left":
          setRoomStatus(RoomStatus.IDLE);
          onRoomStatusChange?.(RoomStatus.IDLE);
          break;
        case "error":
          setError(event.error);
          setRoomStatus(RoomStatus.ERROR);
          onRoomStatusChange?.(RoomStatus.ERROR);
          break;
      }
    };

    janus.on("room", handleRoomEvent);
    return () => janus.off("room", handleRoomEvent);
  }, [janus, onRoomStatusChange]);

  const handleJoinRoom = async () => {
    if (!roomId || !display) {
      setError("Please enter both room ID and display name");
      return;
    }

    try {
      setError(null);
      setRoomStatus(RoomStatus.JOINING);
      onRoomStatusChange?.(RoomStatus.JOINING);

      await createSipBridge({
        roomId: parseInt(roomId),
        uri: `sip:${display}@conference`,
        muted: false,
      });

      setRoomStatus(RoomStatus.JOINED);
      onRoomStatusChange?.(RoomStatus.JOINED);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to join room");
      setRoomStatus(RoomStatus.ERROR);
      onRoomStatusChange?.(RoomStatus.ERROR);
    }
  };

  const handleLeaveRoom = async () => {
    try {
      setRoomStatus(RoomStatus.LEAVING);
      onRoomStatusChange?.(RoomStatus.LEAVING);

      // Implement leave room logic here
      setRoomStatus(RoomStatus.IDLE);
      onRoomStatusChange?.(RoomStatus.IDLE);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to leave room");
      setRoomStatus(RoomStatus.ERROR);
      onRoomStatusChange?.(RoomStatus.ERROR);
    }
  };

  const handleCreateRoom = async (display: string) => {
    try {
      setRoomState((prev) => ({ ...prev, loading: true, error: null }));
      setRoomStatus(RoomStatus.JOINING);
      onRoomStatusChange?.(RoomStatus.JOINING);

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

      const client = new JanusClient({
        sessionId: roomData.janusSessionId,
        handleId: roomData.janusHandleId,
      });

      await client.initializeMedia();
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
        error: null,
      });
      setRoomStatus(RoomStatus.JOINED);
      onRoomStatusChange?.(RoomStatus.JOINED);
    } catch (error) {
      console.error("Failed to create room:", error);
      setRoomState((prev) => ({
        ...prev,
        loading: false,
        error: error instanceof Error ? error.message : "Failed to create room",
      }));
      setRoomStatus(RoomStatus.ERROR);
      onRoomStatusChange?.(RoomStatus.ERROR);
    }
  };

  const handleLeave = () => {
    janusClient?.disconnect?.();
    setJanusClient(null);
    setAudioStream(null);
    setRoomState({
      roomId: "",
      isCreator: false,
      isJoined: false,
      loading: false,
      error: null,
    });
    setRoomStatus(RoomStatus.IDLE);
    onRoomStatusChange?.(RoomStatus.IDLE);
  };

  if (isInitializing) {
    return <LoadingState message="Initializing Janus..." />;
  }

  if (error) {
    return <ErrorState message={error} />;
  }

  return (
    <Box sx={{ display: "flex", flexDirection: "column", gap: 2, p: 2 }}>
      {roomState.isJoined ? (
        <Room
          roomId={roomId}
          isCreator={roomState.isCreator}
          currentUser={currentUser}
          onLeave={handleLeave}
          audioStream={audioStream}
          janusClient={janusClient}
        />
      ) : (
        <>
          <Typography variant="h6">Audio Room</Typography>

          <TextField
            label="Room ID"
            value={roomId}
            onChange={(e) => setRoomId(e.target.value)}
            disabled={roomState.isJoined}
          />

          <TextField
            label="Display Name"
            value={display}
            onChange={(e) => setDisplay(e.target.value)}
            disabled={roomState.isJoined}
          />

          <Box sx={{ display: "flex", gap: 1 }}>
            <Button
              variant="contained"
              onClick={() => handleJoinRoom()}
              disabled={roomState.loading}
              color="primary"
            >
              Join Room
            </Button>
            <Button
              variant="outlined"
              onClick={() => handleCreateRoom(display)}
              disabled={roomState.loading || !display}
            >
              Create Room
            </Button>
          </Box>
        </>
      )}
    </Box>
  );
};

export default AudioRoom;
