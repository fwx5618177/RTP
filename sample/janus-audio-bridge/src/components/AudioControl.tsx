import { IconButton, Box, Tooltip } from "@mui/material";
import { Mic, MicOff, VolumeUp, VolumeOff } from "@mui/icons-material";

interface AudioControlProps {
  isMuted: boolean;
  onToggleMute: () => void;
  isDeafened: boolean;
  onToggleDeafen: () => void;
}

export default function AudioControl({
  isMuted,
  onToggleMute,
  isDeafened,
  onToggleDeafen,
}: AudioControlProps) {
  return (
    <Box sx={{ display: "flex", gap: 1 }}>
      <Tooltip title={isMuted ? "Unmute" : "Mute"}>
        <IconButton
          onClick={onToggleMute}
          color={isMuted ? "error" : "primary"}
          size="large"
        >
          {isMuted ? <MicOff /> : <Mic />}
        </IconButton>
      </Tooltip>

      <Tooltip title={isDeafened ? "Undeafen" : "Deafen"}>
        <IconButton
          onClick={onToggleDeafen}
          color={isDeafened ? "error" : "primary"}
          size="large"
        >
          {isDeafened ? <VolumeOff /> : <VolumeUp />}
        </IconButton>
      </Tooltip>
    </Box>
  );
}
