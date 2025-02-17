import React, { useState, useEffect } from "react";
import {
  Box,
  Button,
  TextField,
  Typography,
  Alert,
  CircularProgress,
} from "@mui/material";
import { useJanus } from "../services/useJanus";

type CallStatus =
  | "idle"
  | "initializing"
  | "connecting"
  | "connected"
  | "muted"
  | "failed"
  | "disconnected";

interface SipCallProps {
  onCallStatusChange?: (status: string) => void;
}

interface SipEvent {
  type: string;
  status: string;
  error?: string;
  code?: number;
}

const SipCall: React.FC<SipCallProps> = ({ onCallStatusChange }) => {
  const [extension, setExtension] = useState<string>("");
  const [roomId, setRoomId] = useState<string>("");
  const [error, setError] = useState<string | null>(null);
  const [callStatus, setCallStatus] = useState<CallStatus>("idle");
  const [isReconnecting, setIsReconnecting] = useState(false);
  const {
    janus,
    createSipBridge,
    updateSipBridge,
    disconnectSipBridge,
    error: janusError,
  } = useJanus();

  // 监听 Janus 错误
  useEffect(() => {
    if (janusError) {
      setError(janusError);
      if (callStatus !== "idle") {
        setCallStatus("failed");
      }
    }
  }, [janusError, callStatus]);

  // 处理 SIP 事件
  useEffect(() => {
    if (!janus) return;

    const handleSipEvent = (event: SipEvent) => {
      if (event.type === "sip") {
        switch (event.status) {
          case "registered":
            setCallStatus("idle");
            setError(null);
            break;
          case "calling":
            setCallStatus("connecting");
            break;
          case "connected":
            setCallStatus("connected");
            setError(null);
            break;
          case "hangup":
            setCallStatus("disconnected");
            if (event.error) {
              setError(`Call ended: ${event.error}`);
            }
            break;
          case "error":
            setCallStatus("failed");
            setError(event.error || "Unknown error occurred");
            break;
          default:
            console.log("Unhandled SIP event:", event);
        }
        onCallStatusChange?.(event.status);
      }
    };

    janus.on("event", handleSipEvent);
    return () => {
      janus.off("event", handleSipEvent);
    };
  }, [janus, onCallStatusChange]);

  // 自动重连逻辑
  useEffect(() => {
    if (callStatus === "failed" && !error?.includes("user hung up")) {
      const reconnectTimer = setTimeout(async () => {
        if (extension && roomId) {
          setIsReconnecting(true);
          try {
            await handleMakeCall();
          } finally {
            setIsReconnecting(false);
          }
        }
      }, 5000);

      return () => clearTimeout(reconnectTimer);
    }
  }, [callStatus, error, extension, roomId]);

  const handleMakeCall = async () => {
    if (!extension || !roomId) {
      setError("Please enter both extension and room ID");
      return;
    }

    try {
      setError(null);
      setCallStatus("initializing");

      const response = await createSipBridge({
        roomId: parseInt(roomId),
        uri: `sip:${extension}@localhost`,
        muted: false,
        quality: 4,
      });

      if (response.success) {
        setCallStatus("connected");
      } else {
        setCallStatus("failed");
        setError(response.error || "Failed to establish call");
      }
    } catch (err) {
      setCallStatus("failed");
      setError(err instanceof Error ? err.message : "Failed to make call");
    }
  };

  const handleHangup = async () => {
    try {
      await disconnectSipBridge();
      setCallStatus("idle");
      setError(null);
    } catch (err) {
      console.error("Failed to hangup:", err);
      setError("Failed to hangup call");
    }
  };

  const handleToggleMute = async () => {
    try {
      await updateSipBridge({
        muted: callStatus !== "muted",
        quality: 4,
      });
      setCallStatus(callStatus === "muted" ? "connected" : "muted");
    } catch (err) {
      console.error("Failed to toggle mute:", err);
      setError("Failed to toggle mute");
    }
  };

  const renderCallButton = () => {
    if (callStatus === "connected" || callStatus === "muted") {
      return (
        <Box sx={{ display: "flex", gap: 2 }}>
          <Button
            variant="contained"
            onClick={handleToggleMute}
            color={callStatus === "muted" ? "warning" : "primary"}
            fullWidth
          >
            {callStatus === "muted" ? "Unmute" : "Mute"}
          </Button>
          <Button
            variant="contained"
            onClick={handleHangup}
            color="error"
            fullWidth
          >
            Hangup
          </Button>
        </Box>
      );
    }

    return (
      <Button
        variant="contained"
        onClick={handleMakeCall}
        disabled={
          !extension ||
          !roomId ||
          ["initializing", "connecting"].includes(callStatus) ||
          isReconnecting
        }
        color="primary"
        fullWidth
        startIcon={
          ["initializing", "connecting"].includes(callStatus) ||
          isReconnecting ? (
            <CircularProgress size={20} />
          ) : undefined
        }
      >
        {callStatus === "idle" && "Make Call"}
        {callStatus === "initializing" && "Initializing..."}
        {callStatus === "connecting" && "Connecting..."}
        {callStatus === "failed" && "Retry Call"}
        {callStatus === "disconnected" && "Reconnect"}
        {isReconnecting && "Reconnecting..."}
      </Button>
    );
  };

  return (
    <Box sx={{ p: 3, maxWidth: 600, mx: "auto" }}>
      <Typography variant="h4" gutterBottom>
        SIP Call to WebRTC Room
      </Typography>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}

      <Box sx={{ mb: 2 }}>
        <TextField
          fullWidth
          label="SIP Extension"
          value={extension}
          onChange={(e) => setExtension(e.target.value)}
          disabled={["connected", "muted"].includes(callStatus)}
          sx={{ mb: 2 }}
        />

        <TextField
          fullWidth
          label="Room ID"
          value={roomId}
          onChange={(e) => setRoomId(e.target.value)}
          disabled={["connected", "muted"].includes(callStatus)}
          sx={{ mb: 2 }}
        />

        {renderCallButton()}
      </Box>

      <Typography variant="body2" color="textSecondary">
        Status: {callStatus}
        {isReconnecting && " (Attempting to reconnect...)"}
      </Typography>
    </Box>
  );
};

export default SipCall;
