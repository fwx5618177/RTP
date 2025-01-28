import { useEffect, useState, useRef } from "react";
import { useParams, useNavigate } from "react-router-dom";
import {
  Container,
  Paper,
  Typography,
  Box,
  Grid,
  useTheme,
  useMediaQuery,
  Slider,
  IconButton,
  Tooltip,
} from "@mui/material";
import {
  Mic,
  MicOff,
  ExitToApp,
  VolumeUp,
  VolumeOff,
} from "@mui/icons-material";
import { JanusClient } from "@/services/JanusClient";
import { roomApi } from "@/services/api";
import { Room as RoomType, Participant } from "@/types/api";
import AudioMeter from "@/components/AudioMeter";
import ParticipantList from "@/components/ParticipantList";
import LoadingState from "@/components/LoadingState";
import ErrorState from "@/components/ErrorState";

export default function Room() {
  const { roomId } = useParams<{ roomId: string }>();
  const navigate = useNavigate();
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down("sm"));

  const [room, setRoom] = useState<RoomType | null>(null);
  const [participants, setParticipants] = useState<Participant[]>([]);
  const [isMuted, setIsMuted] = useState(false);
  const [isDeafened, setIsDeafened] = useState(false);
  const [volume, setVolume] = useState(100);
  const [audioStream, setAudioStream] = useState<MediaStream | null>(null);
  const [error, setError] = useState<string>("");
  const [loading, setLoading] = useState(true);
  const [janusClient, setJanusClient] = useState<JanusClient | null>(null);

  useEffect(() => {
    loadRoomDetails();
    const interval = setInterval(loadParticipants, 5000);
    return () => {
      clearInterval(interval);
      janusClient?.disconnect();
      audioStream?.getTracks().forEach((track) => track.stop());
    };
  }, [roomId]);

  const loadRoomDetails = async () => {
    try {
      setLoading(true);
      const response = await roomApi.getRoom(roomId!);
      console.log("Room response:", response); // 用于调试

      // 检查响应结构
      if (!response?.data?.data) {
        throw new Error("Invalid response format");
      }

      const roomData = response.data.data;

      // 检查必要的字段
      if (!roomData.roomId || !roomData.name) {
        throw new Error("Missing required room data");
      }

      setRoom(roomData);

      // 检查 Janus 配置
      if (
        !roomData.janusSessionId ||
        !roomData.janusHandleId ||
        !roomData.wsUrl
      ) {
        throw new Error("Missing Janus configuration");
      }

      try {
        const client = new JanusClient({
          sessionId: roomData.janusSessionId,
          handleId: roomData.janusHandleId,
          wsUrl: roomData.wsUrl, // 直接使用接口返回的 wsUrl
        });

        await client.connect();

        // 获取音频流
        const stream = await navigator.mediaDevices.getUserMedia({
          audio: {
            echoCancellation: true,
            noiseSuppression: true,
            autoGainControl: true,
          },
        });
        setAudioStream(stream);

        // 设置状态变化回调
        client.setOnStateChange(({ isMuted: muted }) => {
          setIsMuted(muted);
          if (stream) {
            stream.getAudioTracks().forEach((track) => {
              track.enabled = !muted;
            });
          }
        });

        setJanusClient(client);

        // 先设置客户端，再加入房间
        await client.joinRoom(roomId!, roomData.creator);

        // 立即加载参与者列表
        await loadParticipants();
      } catch (error) {
        throw new Error(
          `Failed to initialize Janus client: ${
            error instanceof Error ? error.message : "Unknown error"
          }`
        );
      }
    } catch (error) {
      console.error("Failed to load room details:", error);
      setError(
        error instanceof Error
          ? error.message
          : "Failed to join the room. Please try again."
      );
    } finally {
      setLoading(false);
    }
  };

  const loadParticipants = async () => {
    try {
      const response = await roomApi.getParticipants(roomId!);
      setParticipants(response.data.data);
    } catch (error) {
      console.error("Failed to load participants:", error);
    }
  };

  const handleToggleMute = async () => {
    try {
      await janusClient?.configure({ muted: !isMuted });

      if (audioStream) {
        audioStream.getAudioTracks().forEach((track) => {
          track.enabled = !isMuted;
        });
      }

      setIsMuted(!isMuted);
    } catch (error) {
      console.error("Failed to toggle mute:", error);
      setError("Failed to change audio settings");
    }
  };

  const handleToggleDeafen = () => {
    setIsDeafened(!isDeafened);
    // 实现音频输出静音
    const audioElements = document.getElementsByTagName("audio");
    for (const audio of audioElements) {
      audio.muted = !isDeafened;
    }
  };

  const handleVolumeChange = (_: Event, newValue: number | number[]) => {
    const value = newValue as number;
    setVolume(value);
    // 实现音量调节
    const audioElements = document.getElementsByTagName("audio");
    for (const audio of audioElements) {
      audio.volume = value / 100;
    }
  };

  if (loading) {
    return (
      <Container maxWidth="lg">
        <Box
          sx={{
            minHeight: "100vh",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
          }}
        >
          <LoadingState message="Joining room..." />
        </Box>
      </Container>
    );
  }

  if (error) {
    return (
      <Container maxWidth="lg">
        <Box
          sx={{
            minHeight: "100vh",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
          }}
        >
          <ErrorState message={error} onRetry={loadRoomDetails} />
        </Box>
      </Container>
    );
  }

  return (
    <Container maxWidth="lg">
      <Box
        sx={{
          minHeight: "100vh",
          py: 4,
          display: "flex",
          flexDirection: "column",
          gap: 3,
        }}
      >
        <Paper
          elevation={3}
          sx={{
            p: 3,
            backgroundColor: "background.paper",
            borderRadius: 2,
          }}
        >
          {/* Room Header */}
          <Grid container spacing={3} alignItems="center" sx={{ mb: 3 }}>
            <Grid item xs={12} md={8}>
              <Typography
                variant="h4"
                gutterBottom
                sx={{
                  fontWeight: 500,
                  color: "primary.main",
                }}
              >
                {room?.name || "Loading..."}
              </Typography>
              <Typography
                variant="body2"
                sx={{
                  color: "text.secondary",
                  display: "flex",
                  alignItems: "center",
                  gap: 1,
                }}
              >
                Room ID: {roomId}
              </Typography>
            </Grid>

            {/* Audio Controls */}
            <Grid item xs={12} md={4}>
              <Box
                sx={{
                  display: "flex",
                  alignItems: "center",
                  gap: 2,
                  justifyContent: { xs: "center", md: "flex-end" },
                }}
              >
                <Tooltip title={isMuted ? "Unmute" : "Mute"}>
                  <IconButton
                    onClick={handleToggleMute}
                    color={isMuted ? "error" : "primary"}
                    sx={{
                      "&:hover": {
                        backgroundColor: isMuted
                          ? "error.dark"
                          : "primary.dark",
                        opacity: 0.9,
                      },
                    }}
                  >
                    {isMuted ? <MicOff /> : <Mic />}
                  </IconButton>
                </Tooltip>

                <Tooltip title={isDeafened ? "Undeafen" : "Deafen"}>
                  <IconButton
                    onClick={handleToggleDeafen}
                    color={isDeafened ? "error" : "primary"}
                    sx={{
                      "&:hover": {
                        backgroundColor: isDeafened
                          ? "error.dark"
                          : "primary.dark",
                        opacity: 0.9,
                      },
                    }}
                  >
                    {isDeafened ? <VolumeOff /> : <VolumeUp />}
                  </IconButton>
                </Tooltip>

                <Box sx={{ width: 100 }}>
                  <Slider
                    value={volume}
                    onChange={handleVolumeChange}
                    disabled={isDeafened}
                    aria-label="Volume"
                    sx={{
                      color: isDeafened ? "text.disabled" : "primary.main",
                      "& .MuiSlider-thumb": {
                        width: 12,
                        height: 12,
                      },
                    }}
                  />
                </Box>

                <Tooltip title="Leave Room">
                  <IconButton
                    onClick={() => navigate("/")}
                    color="error"
                    sx={{
                      "&:hover": {
                        backgroundColor: "error.dark",
                        opacity: 0.9,
                      },
                    }}
                  >
                    <ExitToApp />
                  </IconButton>
                </Tooltip>
              </Box>
            </Grid>
          </Grid>

          {/* Audio Meter with visual feedback */}
          {audioStream && (
            <Box sx={{ mb: 3 }}>
              <AudioMeter
                stream={audioStream}
                onVolumeChange={(level) => {
                  // 可以添加音量可视化效果
                  console.log("Current volume level:", level);
                }}
              />
            </Box>
          )}

          {/* Participants List with improved styling */}
          <ParticipantList
            participants={participants}
            currentUserId={room?.creator}
          />
        </Paper>
      </Box>
    </Container>
  );
}
