import React, { useState } from 'react';
import { Users, ArrowRight } from 'lucide-react';
import { Session, Participant } from '../types';
import { supabase } from '../lib/supabase';
import { v4 as uuidv4 } from 'uuid';

interface JoinSessionProps {
  onJoinSuccess: (session: Session, participant: Participant) => void;
}

const JoinSession: React.FC<JoinSessionProps> = ({ onJoinSuccess }) => {
  const [pin, setPin] = useState('');
  const [name, setName] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const joinSession = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!pin.trim() || !name.trim()) {
      setError('Veuillez saisir le PIN et votre nom');
      return;
    }

    setLoading(true);
    setError('');

    try {
      // Find active session with PIN
      const { data: sessionData, error: sessionError } = await supabase
        .from('sessions')
        .select('*')
        .eq('pin', pin.trim())
        .eq('is_active', true)
        .single();

      if (sessionError) {
        if (sessionError.code === 'PGRST116') {
          setError('PIN invalide ou session inactive');
        } else {
          throw sessionError;
        }
        return;
      }

      // Check if name is already taken in this session
      const { data: existingParticipant, error: checkError } = await supabase
        .from('participants')
        .select('*')
        .eq('session_id', sessionData.id)
        .eq('name', name.trim())
        .single();

      if (checkError && checkError.code !== 'PGRST116') {
        throw checkError;
      }

      if (existingParticipant) {
        setError('Ce nom est déjà pris dans cette session');
        return;
      }

      // Create participant
      const participantData = {
        id: uuidv4(),
        session_id: sessionData.id,
        name: name.trim(),
        score: 0,
        joined_at: new Date().toISOString()
      };

      const { data: participant, error: participantError } = await supabase
        .from('participants')
        .insert(participantData)
        .select()
        .single();

      if (participantError) throw participantError;

      onJoinSuccess(sessionData, participant);
    } catch (error) {
      console.error('Error joining session:', error);
      setError('Erreur lors de la connexion à la session');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center px-4">
      <div className="max-w-md w-full bg-white rounded-lg shadow-lg p-8">
        <div className="text-center mb-8">
          <div className="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
            <Users className="w-8 h-8 text-blue-600" />
          </div>
          <h1 className="text-2xl font-bold text-gray-900 mb-2">
            Rejoindre une session
          </h1>
          <p className="text-gray-600">
            Entrez le PIN de la session et votre nom pour participer
          </p>
        </div>

        <form onSubmit={joinSession} className="space-y-6">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              PIN de la session
            </label>
            <input
              type="text"
              value={pin}
              onChange={(e) => setPin(e.target.value)}
              className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-center text-2xl font-mono tracking-wider"
              placeholder="123456"
              maxLength={6}
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Votre nom
            </label>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="Entrez votre nom"
              maxLength={50}
            />
          </div>

          {error && (
            <div className="bg-red-50 border border-red-200 rounded-lg p-3">
              <div className="text-red-800 text-sm">{error}</div>
            </div>
          )}

          <button
            type="submit"
            disabled={loading || !pin.trim() || !name.trim()}
            className="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white px-6 py-3 rounded-lg flex items-center justify-center gap-2 transition-colors font-medium"
          >
            {loading ? (
              'Connexion...'
            ) : (
              <>
                Rejoindre
                <ArrowRight className="w-4 h-4" />
              </>
            )}
          </button>
        </form>

        <div className="mt-8 text-center">
          <div className="text-sm text-gray-500">
            Vous êtes l'organisateur ?{' '}
            <a href="/admin" className="text-blue-600 hover:text-blue-800 font-medium">
              Accéder au dashboard admin
            </a>
          </div>
        </div>
      </div>
    </div>
  );
};

export default JoinSession;