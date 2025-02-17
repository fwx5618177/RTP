import React, { useState, useEffect } from "react";
import {
  Button,
  TextField,
  Box,
  Typography,
  List,
  ListItem,
  ListItemText,
} from "@mui/material";
import { useJanus } from "../services/useJanus";

export enum RoomStatus {
  IDLE = "idle",
  JOINING = "joining",
  JOINED = "joined",
  LEAVING = "leaving",
  ERROR = "error",
}

interface AudioRoomProps {
  onRoomStatusChange?: (status: RoomStatus) => void;
}

interface RoomState {
  roomId: string;
  displayName: string;
  isCreator: boolean;
  isJoined: boolean;
  loading: boolean;
  error: string | null;
}

const AudioRoom: React.FC<AudioRoomProps> = ({ onRoomStatusChange }) => {
  const [state, setState] = useState<RoomState>({
    roomId: "",
    displayName: "",
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
      setState((prev) => ({ ...prev, error: janusError, loading: false }));
      onRoomStatusChange?.(RoomStatus.ERROR);
    }
  }, [janusError, onRoomStatusChange]);

  useEffect(() => {
    if (!janus) return;

    const handleRoomEvent = (event: any) => {
      switch (event.type) {
        case "joined":
          setState((prev) => ({
            ...prev,
            isJoined: true,
            loading: false,
            error: null,
          }));
          onRoomStatusChange?.(RoomStatus.JOINED);
          break;
        case "left":
          setState((prev) => ({
            ...prev,
            isJoined: false,
            loading: false,
            error: null,
          }));
          onRoomStatusChange?.(RoomStatus.IDLE);
          break;
        case "error":
          setState((prev) => ({
            ...prev,
            loading: false,
            error: event.error,
          }));
          onRoomStatusChange?.(RoomStatus.ERROR);
          break;
      }
    };

    janus.on("room", handleRoomEvent);
    return () => janus.off("room", handleRoomEvent);
  }, [janus, onRoomStatusChange]);

  const handleJoinRoom = async () => {
    if (!state.roomId || !state.displayName) {
      setState((prev) => ({
        ...prev,
        error: "Please enter both room ID and display name",
      }));
      return;
    }

    try {
      setState((prev) => ({
        ...prev,
        loading: true,
        error: null,
      }));
      onRoomStatusChange?.(RoomStatus.JOINING);

      await createSipBridge({
        roomId: parseInt(state.roomId),
        uri: `sip:${state.displayName}@conference`,
        muted: false,
      });
    } catch (err) {
      setState((prev) => ({
        ...prev,
        loading: false,
        error: err instanceof Error ? err.message : "Failed to join room",
      }));
      onRoomStatusChange?.(RoomStatus.ERROR);
    }
  };

  const handleLeaveRoom = async () => {
    try {
      setState((prev) => ({
        ...prev,
        loading: true,
        error: null,
      }));
      onRoomStatusChange?.(RoomStatus.LEAVING);

      // Implement leave room logic here
      setState((prev) => ({
        ...prev,
        isJoined: false,
        loading: false,
      }));
      onRoomStatusChange?.(RoomStatus.IDLE);
    } catch (err) {
      setState((prev) => ({
        ...prev,
        loading: false,
        error: err instanceof Error ? err.message : "Failed to leave room",
      }));
      onRoomStatusChange?.(RoomStatus.ERROR);
    }
  };

  return (
    <Box sx={{ display: "flex", flexDirection: "column", gap: 2, p: 2 }}>
      <Typography variant="h6">Audio Room</Typography>

      {state.error && (
        <Typography color="error" variant="body2">
          {state.error}
        </Typography>
      )}

      <TextField
        label="Room ID"
        value={state.roomId}
        onChange={(e) =>
          setState((prev) => ({ ...prev, roomId: e.target.value }))
        }
        disabled={state.isJoined || state.loading}
      />

      <TextField
        label="Display Name"
        value={state.displayName}
        onChange={(e) =>
          setState((prev) => ({ ...prev, displayName: e.target.value }))
        }
        disabled={state.isJoined || state.loading}
      />

      <Box sx={{ display: "flex", gap: 1 }}>
        <Button
          variant="contained"
          onClick={state.isJoined ? handleLeaveRoom : handleJoinRoom}
          disabled={state.loading || isInitializing}
          color={state.isJoined ? "error" : "primary"}
        >
          {state.isJoined ? "Leave Room" : "Join Room"}
        </Button>
      </Box>

      {state.isJoined && (
        <Box sx={{ mt: 2 }}>
          <Typography variant="subtitle1">Room Participants</Typography>
          <List>
            <ListItem>
              <ListItemText primary={state.displayName} secondary="You" />
            </ListItem>
            {/* Add other participants here */}
          </List>
        </Box>
      )}
    </Box>
  );
};

export default AudioRoom;
