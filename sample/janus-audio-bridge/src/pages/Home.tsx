import { useState } from "react";
import {
  Container,
  Typography,
  TextField,
  Button,
  Box,
  useTheme,
  useMediaQuery,
  Card,
  CardContent,
  CardActions,
  Grid,
  Snackbar,
  Alert,
} from "@mui/material";
import { Add, Login } from "@mui/icons-material";
import { useNavigate } from "react-router-dom";
import { roomApi } from "@/services/api";
import { ApiResponse, Room } from "@/types/api";

export default function Home() {
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down("sm"));
  const navigate = useNavigate();

  const [createMode, setCreateMode] = useState(true);
  const [userId, setUserId] = useState("");
  const [roomName, setRoomName] = useState("");
  const [roomId, setRoomId] = useState("");
  const [display, setDisplay] = useState("");
  const [error, setError] = useState("");
  const [showError, setShowError] = useState(false);

  const handleCreateRoom = async () => {
    if (!userId || !roomName) {
      setError("Please fill in all required fields");
      setShowError(true);
      return;
    }

    try {
      const response = await roomApi.createRoom({
        userId,
        roomName,
        config: {
          maxParticipants: 10,
          audioEnabled: true,
          videoEnabled: false,
          audioConfig: {
            sampleRate: 16000,
            channels: 1,
            codec: "opus",
          },
        },
      });
      console.log("response:", response.data.data);

      navigate(`/room/${response.data.data.roomId}`);
    } catch (err) {
      setError("Failed to create room");
      setShowError(true);
    }
  };

  const handleJoinRoom = async () => {
    if (!userId || !roomId) {
      setError("Please fill in all required fields");
      setShowError(true);
      return;
    }

    try {
      await roomApi.joinRoom({
        roomId,
        userId,
        display: display || userId,
      });

      navigate(`/room/${roomId}`);
    } catch (err) {
      setError("Failed to join room");
      setShowError(true);
    }
  };

  return (
    <Container maxWidth="lg">
      <Box
        sx={{
          minHeight: "100vh",
          py: 4,
          display: "flex",
          flexDirection: "column",
          alignItems: "center",
        }}
      >
        <Typography variant="h3" component="h1" gutterBottom align="center">
          Audio Conference
        </Typography>

        <Grid container spacing={4} justifyContent="center" sx={{ mt: 4 }}>
          <Grid item xs={12} md={6}>
            <Card elevation={3}>
              <CardContent>
                <Typography variant="h5" gutterBottom>
                  {createMode ? "Create New Room" : "Join Existing Room"}
                </Typography>

                <TextField
                  fullWidth
                  label="Your Name (User ID)"
                  value={userId}
                  onChange={(e) => setUserId(e.target.value)}
                  margin="normal"
                  required
                />

                {createMode ? (
                  <TextField
                    fullWidth
                    label="Room Name"
                    value={roomName}
                    onChange={(e) => setRoomName(e.target.value)}
                    margin="normal"
                    required
                  />
                ) : (
                  <>
                    <TextField
                      fullWidth
                      label="Room ID"
                      value={roomId}
                      onChange={(e) => setRoomId(e.target.value)}
                      margin="normal"
                      required
                    />
                    <TextField
                      fullWidth
                      label="Display Name (optional)"
                      value={display}
                      onChange={(e) => setDisplay(e.target.value)}
                      margin="normal"
                    />
                  </>
                )}
              </CardContent>
              <CardActions sx={{ p: 2, justifyContent: "space-between" }}>
                <Button
                  variant="outlined"
                  onClick={() => setCreateMode(!createMode)}
                >
                  {createMode ? "Join Room Instead" : "Create Room Instead"}
                </Button>
                <Button
                  variant="contained"
                  startIcon={createMode ? <Add /> : <Login />}
                  onClick={createMode ? handleCreateRoom : handleJoinRoom}
                >
                  {createMode ? "Create Room" : "Join Room"}
                </Button>
              </CardActions>
            </Card>
          </Grid>
        </Grid>

        <Snackbar
          open={showError}
          autoHideDuration={6000}
          onClose={() => setShowError(false)}
        >
          <Alert severity="error" onClose={() => setShowError(false)}>
            {error}
          </Alert>
        </Snackbar>
      </Box>
    </Container>
  );
}
