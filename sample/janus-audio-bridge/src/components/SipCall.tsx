import React, { useState, useEffect } from "react";
import { Button, TextField, Box, Typography } from "@mui/material";
import { useJanus } from "../services/useJanus";

export enum CallStatus {
  IDLE = "idle",
  CONNECTING = "connecting",
  CONNECTED = "connected",
  DISCONNECTING = "disconnecting",
  ERROR = "error",
}

interface SipCallProps {
  onCallStatusChange?: (status: CallStatus) => void;
}

const SipCall: React.FC<SipCallProps> = ({ onCallStatusChange }) => {
  const [extension, setExtension] = useState("");
  const [roomId, setRoomId] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [callStatus, setCallStatus] = useState<CallStatus>(CallStatus.IDLE);
  const [isMuted, setIsMuted] = useState(false);

  const {
    janus,
    error: janusError,
    isInitializing,
    createSipBridge,
    updateSipBridge,
    disconnectSipBridge,
  } = useJanus();

  useEffect(() => {
    if (janusError) {
      setError(janusError);
      setCallStatus(CallStatus.ERROR);
    }
  }, [janusError]);

  useEffect(() => {
    if (!janus) return;

    const handleSipEvent = (event: any) => {
      switch (event.type) {
        case "connected":
          setCallStatus(CallStatus.CONNECTED);
          break;
        case "disconnected":
          setCallStatus(CallStatus.IDLE);
          break;
        case "error":
          setError(event.error);
          setCallStatus(CallStatus.ERROR);
          break;
      }
    };

    janus.on("sip", handleSipEvent);
    return () => janus.off("sip", handleSipEvent);
  }, [janus]);

  useEffect(() => {
    onCallStatusChange?.(callStatus);
  }, [callStatus, onCallStatusChange]);

  const handleMakeCall = async () => {
    if (!extension || !roomId) {
      setError("Please enter both extension and room ID");
      return;
    }

    try {
      setError(null);
      setCallStatus(CallStatus.CONNECTING);

      await createSipBridge({
        roomId: parseInt(roomId),
        uri: `sip:${extension}@localhost`,
        muted: isMuted,
      });

      setCallStatus(CallStatus.CONNECTED);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to make call");
      setCallStatus(CallStatus.ERROR);
    }
  };

  const handleHangup = async () => {
    try {
      setCallStatus(CallStatus.DISCONNECTING);
      await disconnectSipBridge();
      setCallStatus(CallStatus.IDLE);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to hangup call");
      setCallStatus(CallStatus.ERROR);
    }
  };

  const handleToggleMute = async () => {
    try {
      await updateSipBridge({ muted: !isMuted });
      setIsMuted(!isMuted);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to toggle mute");
    }
  };

  const isCallActive = callStatus === CallStatus.CONNECTED;
  const isLoading =
    isInitializing ||
    callStatus === CallStatus.CONNECTING ||
    callStatus === CallStatus.DISCONNECTING;

  return (
    <Box sx={{ display: "flex", flexDirection: "column", gap: 2, p: 2 }}>
      <Typography variant="h6">SIP Call</Typography>

      {error && (
        <Typography color="error" variant="body2">
          {error}
        </Typography>
      )}

      <TextField
        label="Extension"
        value={extension}
        onChange={(e) => setExtension(e.target.value)}
        disabled={isCallActive || isLoading}
      />

      <TextField
        label="Room ID"
        value={roomId}
        onChange={(e) => setRoomId(e.target.value)}
        disabled={isCallActive || isLoading}
      />

      <Box sx={{ display: "flex", gap: 1 }}>
        <Button
          variant="contained"
          onClick={isCallActive ? handleHangup : handleMakeCall}
          disabled={isLoading}
          color={isCallActive ? "error" : "primary"}
        >
          {isCallActive ? "Hangup" : "Call"}
        </Button>

        {isCallActive && (
          <Button
            variant="outlined"
            onClick={handleToggleMute}
            disabled={isLoading}
          >
            {isMuted ? "Unmute" : "Mute"}
          </Button>
        )}
      </Box>
    </Box>
  );
};

export default SipCall;
