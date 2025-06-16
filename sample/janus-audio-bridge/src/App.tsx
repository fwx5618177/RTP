import React, { useState } from "react";
import { Container, Grid, Paper, Box, Typography } from "@mui/material";
import SipCall, { CallStatus } from "./components/SipCall";
import AudioRoom, { RoomStatus } from "./components/AudioRoom";

const App: React.FC = () => {
  const [callStatus, setCallStatus] = useState<CallStatus>(CallStatus.IDLE);
  const [roomStatus, setRoomStatus] = useState<RoomStatus>(RoomStatus.IDLE);

  return (
    <Container maxWidth="lg" sx={{ py: 4 }}>
      <Typography variant="h4" component="h1" gutterBottom>
        Janus Audio Bridge Demo
      </Typography>

      <Grid container spacing={3}>
        <Grid item xs={12} md={6}>
          <Paper elevation={3}>
            <SipCall onCallStatusChange={setCallStatus} />
          </Paper>
        </Grid>

        <Grid item xs={12} md={6}>
          <Paper elevation={3}>
            <AudioRoom onRoomStatusChange={setRoomStatus} />
          </Paper>
        </Grid>

        <Grid item xs={12}>
          <Paper elevation={3} sx={{ p: 2 }}>
            <Box sx={{ display: "flex", gap: 4 }}>
              <Box>
                <Typography variant="subtitle2" color="text.secondary">
                  Call Status
                </Typography>
                <Typography variant="body1">
                  {callStatus.charAt(0).toUpperCase() + callStatus.slice(1)}
                </Typography>
              </Box>

              <Box>
                <Typography variant="subtitle2" color="text.secondary">
                  Room Status
                </Typography>
                <Typography variant="body1">
                  {roomStatus.charAt(0).toUpperCase() + roomStatus.slice(1)}
                </Typography>
              </Box>
            </Box>
          </Paper>
        </Grid>
      </Grid>
    </Container>
  );
};

export default App;
