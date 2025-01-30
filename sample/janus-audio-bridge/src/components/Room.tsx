import { useState, useEffect } from "react";

interface Participant {
  id: string;
  display: string;
  setup: boolean;
  muted: boolean;
}

interface RoomProps {
  roomId: string;
  isCreator: boolean;
  currentUser: {
    id: string;
    display: string;
  };
  onLeave: () => void;
}

export const Room = ({
  roomId,
  isCreator,
  currentUser,
  onLeave,
}: RoomProps) => {
  const [participants, setParticipants] = useState<Participant[]>([]);
  const [isCopied, setIsCopied] = useState(false);

  const copyInviteLink = () => {
    const inviteText = `Join my audio room! Room ID: ${roomId}`;
    navigator.clipboard.writeText(inviteText);
    setIsCopied(true);
    setTimeout(() => setIsCopied(false), 2000);
  };

  return (
    <div className="room-container">
      <div className="room-header">
        <h2>{isCreator ? "Your Room" : "Joined Room"}</h2>
        <div className="room-info">
          <span>Room ID: {roomId}</span>
          <button onClick={copyInviteLink}>
            {isCopied ? "Copied!" : "Copy Invite"}
          </button>
        </div>
      </div>

      <div className="participants-list">
        <h3>Participants</h3>
        <div className="current-user">
          <div className="participant-info">
            <span>{currentUser.display} (You)</span>
            {/* Add mute controls here */}
          </div>
        </div>

        {participants.map((participant) => (
          <div key={participant.id} className="participant">
            <div className="participant-info">
              <span>{participant.display}</span>
              <span
                className={`status ${participant.setup ? "connected" : "connecting"}`}
              >
                {participant.setup ? "Connected" : "Connecting..."}
              </span>
              {participant.muted && <span className="muted">Muted</span>}
            </div>
          </div>
        ))}
      </div>

      <button className="leave-button" onClick={onLeave}>
        Leave Room
      </button>
    </div>
  );
};
