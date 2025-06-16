import {
  List,
  ListItem,
  ListItemText,
  ListItemIcon,
  ListItemSecondaryAction,
  Avatar,
  Typography,
  Paper,
  Box,
} from "@mui/material";
import { Mic, MicOff, Person } from "@mui/icons-material";
import { ParticipantListResponse } from "../types/api";

interface ParticipantListProps {
  participants: ParticipantListResponse;
  currentUserId?: string;
}

export default function ParticipantList({
  participants,
  currentUserId,
}: ParticipantListProps) {
  return (
    <Paper elevation={2} sx={{ mt: 2, p: 2 }}>
      <Typography variant="h6" gutterBottom>
        Participants ({participants.count})
      </Typography>
      <List>
        {participants?.participants?.map((participant) => (
          <ListItem
            key={participant.userId}
            sx={{
              bgcolor:
                participant.userId === currentUserId
                  ? "action.selected"
                  : "inherit",
              borderRadius: 1,
              mb: 1,
            }}
          >
            <ListItemIcon>
              <Avatar>
                <Person />
              </Avatar>
            </ListItemIcon>
            <ListItemText
              primary={
                <Box
                  component="span"
                  sx={{ display: "flex", alignItems: "center" }}
                >
                  {participant.display}
                  {participant.userId === currentUserId && (
                    <Typography variant="caption" sx={{ ml: 1 }}>
                      (You)
                    </Typography>
                  )}
                </Box>
              }
              secondary={`Joined: ${new Date(participant.joinedAt).toLocaleTimeString()}`}
            />
            <ListItemSecondaryAction>
              {participant.audioMuted ? (
                <MicOff color="error" />
              ) : (
                <Mic color="success" />
              )}
            </ListItemSecondaryAction>
          </ListItem>
        ))}
      </List>
    </Paper>
  );
}
