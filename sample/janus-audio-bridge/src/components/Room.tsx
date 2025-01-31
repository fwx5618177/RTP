import { useState, useEffect } from "react";
import AudioMeter from "./AudioMeter";
import { JanusClient } from "../services/JanusClient";

interface RoomProps {
  roomId: string;
  isCreator: boolean;
  currentUser: {
    id: string;
    display: string;
  };
  onLeave: () => void;
  audioStream: MediaStream | null;
  janusClient: JanusClient | null;
}

export const Room = ({
  roomId,
  isCreator,
  currentUser,
  onLeave,
  audioStream,
  janusClient,
}: RoomProps) => {
  const [localVolume, setLocalVolume] = useState(0);
  const [volume, setVolume] = useState(100);
  const [isMuted, setIsMuted] = useState(false);
  const [participants, setParticipants] = useState<any[]>([]);
  const [audioSource, setAudioSource] = useState<"local" | "remote">("local");

  // 加载参与者列表
  useEffect(() => {
    const loadParticipants = async () => {
      try {
        const response = await fetch(`/api/rooms/${roomId}/participants`);
        const data = await response.json();
        if (data.data) {
          setParticipants(data.data.participants);
        }
      } catch (error) {
        console.error("Failed to load participants:", error);
      }
    };

    loadParticipants();
    const interval = setInterval(loadParticipants, 5000);
    return () => clearInterval(interval);
  }, [roomId]);

  useEffect(() => {
    // 确保音频设备正确初始化
    const initAudio = async () => {
      try {
        await audioStream?.getAudioTracks()[0].applyConstraints({
          echoCancellation: true,
          noiseSuppression: true,
          autoGainControl: true,
        });
        console.log("Audio constraints applied successfully");
      } catch (error) {
        console.error("Failed to apply audio constraints:", error);
      }
    };

    if (audioStream) {
      initAudio();
    }
  }, [audioStream]);

  useEffect(() => {
    // 定期检查音频状态
    const audioCheckInterval = setInterval(() => {
      if (audioStream) {
        console.log(
          "[Room] Local audio tracks:",
          audioStream.getAudioTracks().map((track) => ({
            enabled: track.enabled,
            muted: track.muted,
            readyState: track.readyState,
          }))
        );
      }

      // 检查所有音频元素
      const audioElements = document.getElementsByTagName("audio");
      Array.from(audioElements).forEach((audio, index) => {
        console.log(`[Room] Audio element ${index}:`, {
          readyState: audio.readyState,
          paused: audio.paused,
          muted: audio.muted,
          volume: audio.volume,
          currentTime: audio.currentTime,
        });
      });
    }, 5000);

    return () => clearInterval(audioCheckInterval);
  }, [audioStream]);

  const handleMuteToggle = () => {
    setIsMuted(!isMuted);
    janusClient?.setMuted(!isMuted);
  };

  const handleVolumeChange = (value: number) => {
    setVolume(value);
    janusClient?.setVolume(value);
  };

  const handleAudioSourceChange = (source: "local" | "remote") => {
    setAudioSource(source);
    janusClient?.switchAudioSource(source);
  };

  return (
    <div className="room-container">
      {/* 房间信息头部 */}
      <div className="room-header">
        <div className="room-info">
          <h2>Room: {roomId}</h2>
          <span className="creator-badge">
            {isCreator ? "Creator" : "Participant"}
          </span>
        </div>
        <button className="leave-button" onClick={onLeave}>
          Leave Room
        </button>
      </div>

      {/* 参与者列表 */}
      <div className="participants-section">
        <h3>Participants ({participants.length})</h3>
        <div className="participants-list">
          {participants.map((participant) => (
            <div key={participant.userId} className="participant-card">
              <div className="participant-info">
                <span className="participant-name">{participant.display}</span>
                <div className="participant-status">
                  <span
                    className={`status ${participant.setup ? "connected" : "connecting"}`}
                  >
                    {participant.setup ? "Connected" : "Connecting"}
                  </span>
                  {participant.audioMuted && (
                    <span className="muted-badge">🔇</span>
                  )}
                </div>
              </div>
              {participant.userId === currentUser.id && (
                <span className="current-user-badge">You</span>
              )}
            </div>
          ))}
        </div>
      </div>

      {/* 音频控制面板 */}
      <div className="audio-controls">
        <h3>Audio Controls</h3>

        {/* 音频源切换 */}
        <div className="audio-source-controls">
          <div className="tab-group">
            <button
              className={`tab ${audioSource === "local" ? "active" : ""}`}
              onClick={() => handleAudioSourceChange("local")}
            >
              <span className="icon">🎤</span>
              Local Audio
            </button>
            <button
              className={`tab ${audioSource === "remote" ? "active" : ""}`}
              onClick={() => handleAudioSourceChange("remote")}
            >
              <span className="icon">🔊</span>
              Remote Audio
            </button>
          </div>
        </div>

        {/* 音量显示 */}
        <div className="volume-meter">
          <div className="meter-label">
            <span>Input Level</span>
            <span className="volume-value">{localVolume}%</span>
          </div>
          <div className="meter-bar">
            <div className="meter-fill" style={{ width: `${localVolume}%` }} />
          </div>
        </div>

        {/* 音量控制 */}
        <div className="volume-controls">
          <label>Output Volume: {volume}%</label>
          <input
            type="range"
            min="0"
            max="100"
            value={volume}
            onChange={(e) => handleVolumeChange(Number(e.target.value))}
            className="volume-slider"
          />
        </div>

        {/* 静音控制 */}
        <div className="mute-control">
          <button
            className={`mute-button ${isMuted ? "muted" : ""}`}
            onClick={handleMuteToggle}
          >
            <span className="icon">{isMuted ? "🔇" : "🔊"}</span>
            {isMuted ? "Unmute" : "Mute"}
          </button>
        </div>
      </div>

      {/* 音频计量器 */}
      <AudioMeter stream={audioStream} onVolumeChange={setLocalVolume} />
    </div>
  );
};
