import React, { useState } from "react";
import { makeCall } from "../services/api";
import { Box, Button, TextField, Typography } from "@mui/material";

const PbxCall: React.FC = () => {
  const [extension, setExtension] = useState("");
  const [roomId, setRoomId] = useState("");
  const [loading, setLoading] = useState(false);

  const handleCall = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      setLoading(true);
      await makeCall({
        extension,
        roomId,
      });
      alert("Call initiated successfully");
    } catch (error) {
      alert("Failed to initiate call");
      console.error("Call error:", error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Box sx={{ maxWidth: 400, margin: "40px auto", padding: "0 20px" }}>
      <Typography variant="h5" gutterBottom>
        Make PBX Call to Room
      </Typography>
      <form onSubmit={handleCall}>
        <Box sx={{ mb: 2 }}>
          <TextField
            fullWidth
            label="Extension Number"
            value={extension}
            onChange={(e) => setExtension(e.target.value)}
            placeholder="Enter extension (e.g. 6001)"
            required
          />
        </Box>
        <Box sx={{ mb: 2 }}>
          <TextField
            fullWidth
            label="Room Number"
            value={roomId}
            onChange={(e) => setRoomId(e.target.value)}
            placeholder="Enter room number (e.g. 1234)"
            required
          />
        </Box>
        <Button type="submit" variant="contained" fullWidth disabled={loading}>
          {loading ? "Making Call..." : "Make Call"}
        </Button>
      </form>
    </Box>
  );
};

export default PbxCall;
