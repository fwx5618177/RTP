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
  Button,
} from "@mui/material";
import {
  Mic,
  MicOff,
  ExitToApp,
  VolumeUp,
  VolumeOff,
} from "@mui/icons-material";
import { JanusClient } from "../services/JanusClient";
import { roomApi } from "../services/api";
import { Room as RoomType, ParticipantListResponse } from "@/types/api";
import AudioMeter from "@/components/AudioMeter";
import ParticipantList from "@/components/ParticipantList";
import LoadingState from "@/components/LoadingState";
import ErrorState from "@/components/ErrorState";
import { Room as RoomComponent } from "../components/Room";

export default function Room() {
  const { roomId } = useParams<{ roomId: string }>();
  const navigate = useNavigate();
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down("sm"));

  const [room, setRoom] = useState<RoomType | null>(null);
  const [participants, setParticipants] =
    useState<ParticipantListResponse | null>(null);
  const [isMuted, setIsMuted] = useState(false);
  const [isDeafened, setIsDeafened] = useState(false);
  const [volume, setVolume] = useState(100);
  const [audioStream, setAudioStream] = useState<MediaStream | null>(null);
  const [error, setError] = useState<string>("");
  const [loading, setLoading] = useState(true);
  const [janusClient, setJanusClient] = useState<JanusClient | null>(null);
  const [audioUnlocked, setAudioUnlocked] = useState(false);

  // 生成随机用户ID
  const userId = useRef(Math.random().toString(36).substring(7));

  useEffect(() => {
    if (!roomId) {
      console.error("No room ID provided");
      navigate("/");
      return;
    }

    const initializeAudio = async () => {
      try {
        console.log("Requesting audio permissions...");
        const stream = await navigator.mediaDevices.getUserMedia({
          audio: {
            echoCancellation: true,
            noiseSuppression: true,
            autoGainControl: true,
          },
        });
        console.log("Audio permissions granted, stream:", stream);
        setAudioStream(stream);
      } catch (err) {
        console.error("Failed to get audio stream:", err);
        setError("Failed to access microphone");
      }
    };

    initializeAudio();

    return () => {
      if (audioStream) {
        console.log("Cleaning up audio stream");
        audioStream.getTracks().forEach((track) => track.stop());
      }
    };
  }, [roomId, navigate]);

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
    let retryCount = 0;
    const maxRetries = 3;
    const retryDelay = 2000;

    while (retryCount < maxRetries) {
      try {
        setLoading(true);
        const response = await roomApi.getRoom(roomId!);
        console.log("Room response:", response);

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
        if (!roomData?.janusSessionId || !roomData?.janusHandleId) {
          throw new Error("Missing Janus configuration");
        }

        const client = new JanusClient({
          sessionId: roomData.janusSessionId,
          handleId: roomData.janusHandleId,
        });

        await client.initializeMedia();

        // 修正 joinRoom 调用
        await client.joinRoom(roomId!, `User-${userId.current}`); // 直接传入字符串参数

        const stream = client.getLocalStream();
        setAudioStream(stream);

        client.setOnStateChange(({ isMuted: muted }) => {
          setIsMuted(muted);
          if (stream) {
            stream.getAudioTracks().forEach((track: MediaStreamTrack) => {
              track.enabled = !muted;
            });
          }
        });

        setJanusClient(client);

        // 加载参与者列表
        await loadParticipants();
        break; // 成功后跳出循环
      } catch (error) {
        retryCount++;
        console.warn(
          `Attempt ${retryCount}/${maxRetries} failed:`,
          error instanceof Error ? error.message : "Unknown error"
        );

        if (retryCount === maxRetries) {
          setError(
            `Failed to initialize Janus client after ${maxRetries} attempts: ${
              error instanceof Error ? error.message : "Unknown error"
            }`
          );
          throw error;
        }

        await new Promise((resolve) => setTimeout(resolve, retryDelay));
      } finally {
        setLoading(false);
      }
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
        audioStream.getAudioTracks().forEach((track: MediaStreamTrack) => {
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

  const handleUnlockAudio = async () => {
    if (janusClient) {
      try {
        await janusClient.tryUnlockAudio();
        setAudioUnlocked(true);
      } catch (error) {
        console.error("Failed to unlock audio:", error);
      }
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
    <div>
      {error ? (
        <div className="error">{error}</div>
      ) : loading ? (
        <div className="loading">Loading...</div>
      ) : (
        <RoomComponent
          roomId={roomId!}
          isCreator={room?.creator === localStorage.getItem("userId")}
          currentUser={{
            id: localStorage.getItem("userId") || "",
            display: localStorage.getItem("display") || "",
          }}
          onLeave={() => navigate("/")}
          audioStream={audioStream}
          janusClient={janusClient}
        />
      )}
      {!audioUnlocked && (
        <Button onClick={handleUnlockAudio}>点击启用音频</Button>
      )}
    </div>
  );
}
