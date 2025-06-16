import React, { useEffect, useState } from "react";
import {
  Box,
  Typography,
  List,
  ListItem,
  ListItemText,
  ListItemIcon,
  IconButton,
  Paper,
} from "@mui/material";
import {
  Mic,
  MicOff,
  ExitToApp,
  VolumeUp,
  VolumeMute,
} from "@mui/icons-material";
import { RoomParticipant } from "../types/janus";
import { JanusClient } from "../services/JanusClient";
import "../styles/room.css";

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

const Room: React.FC<RoomProps> = ({
  roomId,
  isCreator,
  currentUser,
  onLeave,
  audioStream,
  janusClient,
}) => {
  const [participants, setParticipants] = useState<RoomParticipant[]>([]);
  const [isMuted, setIsMuted] = useState(false);

  useEffect(() => {
    // Add current user to participants
    setParticipants([
      {
        id: currentUser.id,
        display: currentUser.display,
        muted: isMuted,
        talking: false,
        joined_at: new Date().toISOString(),
      },
    ]);
  }, [currentUser, isMuted]);

  const handleToggleMute = () => {
    if (audioStream) {
      const audioTracks = audioStream.getAudioTracks();
      audioTracks.forEach((track) => {
        track.enabled = !track.enabled;
      });
      setIsMuted(!isMuted);
    }
  };

  const handleLeave = () => {
    janusClient?.disconnect();
    onLeave();
  };

  return (
    <Paper elevation={3} sx={{ p: 2 }}>
      <Box sx={{ mb: 2 }}>
        <Typography variant="h6">Room {roomId}</Typography>
        <Typography variant="body2" color="text.secondary">
          {isCreator ? "You are the room creator" : "You are a participant"}
        </Typography>
      </Box>

      <List>
        {participants.map((participant) => (
          <ListItem
            key={participant.id}
            secondaryAction={
              <IconButton
                edge="end"
                onClick={handleToggleMute}
                disabled={participant.id !== currentUser.id}
              >
                {participant.muted ? <MicOff /> : <Mic />}
              </IconButton>
            }
          >
            <ListItemIcon>
              {participant.talking ? <VolumeUp /> : <VolumeMute />}
            </ListItemIcon>
            <ListItemText
              primary={participant.display}
              secondary={
                participant.id === currentUser.id ? "You" : "Participant"
              }
            />
          </ListItem>
        ))}
      </List>

      <Box sx={{ mt: 2, display: "flex", justifyContent: "flex-end" }}>
        <IconButton color="error" onClick={handleLeave}>
          <ExitToApp />
        </IconButton>
      </Box>
    </Paper>
  );
};

export default Room;
