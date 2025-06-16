import React from "react";
import { Box, Typography, Alert } from "@mui/material";

interface ErrorStateProps {
  message: string;
}

const ErrorState: React.FC<ErrorStateProps> = ({ message }) => {
  return (
    <Box
      sx={{
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        justifyContent: "center",
        p: 4,
      }}
    >
      <Alert severity="error" sx={{ width: "100%", maxWidth: 500 }}>
        <Typography variant="body1">{message}</Typography>
      </Alert>
    </Box>
  );
};

export default ErrorState;
