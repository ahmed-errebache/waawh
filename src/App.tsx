import React, { useState, useEffect } from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import AdminDashboard from './components/AdminDashboard';
import JoinSession from './components/JoinSession';
import ParticipantView from './components/ParticipantView';
import { Session, Participant } from './types';

function App() {
  const [currentSession, setCurrentSession] = useState<Session | null>(null);
  const [currentParticipant, setCurrentParticipant] = useState<Participant | null>(null);

  const handleJoinSuccess = (session: Session, participant: Participant) => {
    setCurrentSession(session);
    setCurrentParticipant(participant);
  };

  const handleLeaveSession = () => {
    setCurrentSession(null);
    setCurrentParticipant(null);
  };

  // If participant has joined a session, show participant view
  if (currentSession && currentParticipant) {
    return (
      <ParticipantView
        session={currentSession}
        participant={currentParticipant}
      />
    );
  }

  return (
    <Router>
      <Routes>
        <Route path="/admin" element={<AdminDashboard />} />
        <Route 
          path="/" 
          element={
            <JoinSession onJoinSuccess={handleJoinSuccess} />
          } 
        />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </Router>
  );
}

export default App;