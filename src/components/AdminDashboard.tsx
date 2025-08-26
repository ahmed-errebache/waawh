import React, { useState, useEffect } from 'react';
import { Plus, Edit, Trash2, Play, Users } from 'lucide-react';
import { Poll, Session } from '../types';
import { supabase } from '../lib/supabase';
import PollEditor from './PollEditor';
import SessionManager from './SessionManager';

const AdminDashboard: React.FC = () => {
  const [polls, setPolls] = useState<Poll[]>([]);
  const [sessions, setSessions] = useState<Session[]>([]);
  const [showEditor, setShowEditor] = useState(false);
  const [editingPoll, setEditingPoll] = useState<Poll | null>(null);
  const [activeSession, setActiveSession] = useState<Session | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadPolls();
    loadSessions();
  }, []);

  const loadPolls = async () => {
    try {
      const { data, error } = await supabase
        .from('polls')
        .select(`
          *,
          questions (
            *,
            options:question_options (*)
          )
        `)
        .order('created_at', { ascending: false });

      if (error) throw error;
      setPolls(data || []);
    } catch (error) {
      console.error('Error loading polls:', error);
    } finally {
      setLoading(false);
    }
  };

  const loadSessions = async () => {
    try {
      const { data, error } = await supabase
        .from('sessions')
        .select('*')
        .eq('is_active', true);

      if (error) throw error;
      setSessions(data || []);
      
      if (data && data.length > 0) {
        setActiveSession(data[0]);
      }
    } catch (error) {
      console.error('Error loading sessions:', error);
    }
  };

  const deletePoll = async (pollId: string) => {
    if (!confirm('Êtes-vous sûr de vouloir supprimer ce sondage ?')) return;

    try {
      const { error } = await supabase
        .from('polls')
        .delete()
        .eq('id', pollId);

      if (error) throw error;
      loadPolls();
    } catch (error) {
      console.error('Error deleting poll:', error);
    }
  };

  const startSession = async (poll: Poll) => {
    if (activeSession) {
      alert('Une session est déjà active. Veuillez la terminer avant d\'en commencer une nouvelle.');
      return;
    }

    try {
      const pin = Math.floor(100000 + Math.random() * 900000).toString();
      
      const { data, error } = await supabase
        .from('sessions')
        .insert({
          poll_id: poll.id,
          pin,
          is_active: true,
          current_question_index: 0,
          show_results: false
        })
        .select()
        .single();

      if (error) throw error;
      
      setActiveSession(data);
      loadSessions();
    } catch (error) {
      console.error('Error starting session:', error);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-xl text-gray-600">Chargement...</div>
      </div>
    );
  }

  if (showEditor) {
    return (
      <PollEditor
        poll={editingPoll}
        onSave={() => {
          setShowEditor(false);
          setEditingPoll(null);
          loadPolls();
        }}
        onCancel={() => {
          setShowEditor(false);
          setEditingPoll(null);
        }}
      />
    );
  }

  if (activeSession) {
    return (
      <SessionManager
        session={activeSession}
        onEndSession={() => {
          setActiveSession(null);
          loadSessions();
        }}
      />
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 py-8">
        <div className="flex justify-between items-center mb-8">
          <h1 className="text-3xl font-bold text-gray-900">Dashboard Admin</h1>
          <button
            onClick={() => setShowEditor(true)}
            className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg flex items-center gap-2 transition-colors"
          >
            <Plus className="w-5 h-5" />
            Créer un sondage
          </button>
        </div>

        {polls.length === 0 ? (
          <div className="text-center py-12">
            <div className="text-gray-500 text-lg mb-4">Aucun sondage créé</div>
            <button
              onClick={() => setShowEditor(true)}
              className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg"
            >
              Créer votre premier sondage
            </button>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {polls.map((poll) => (
              <div
                key={poll.id}
                className="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow"
              >
                <div className="flex justify-between items-start mb-4">
                  <h3 className="text-xl font-semibold text-gray-900 truncate">
                    {poll.title}
                  </h3>
                  <div className="flex gap-2">
                    <button
                      onClick={() => {
                        setEditingPoll(poll);
                        setShowEditor(true);
                      }}
                      className="text-gray-600 hover:text-blue-600 transition-colors"
                    >
                      <Edit className="w-4 h-4" />
                    </button>
                    <button
                      onClick={() => deletePoll(poll.id)}
                      className="text-gray-600 hover:text-red-600 transition-colors"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                </div>

                <div className="mb-4">
                  <div className="text-sm text-gray-600 mb-2">
                    Thème: {poll.theme}
                  </div>
                  <div className="text-sm text-gray-600 mb-2">
                    Questions: {poll.questions?.length || 0}
                  </div>
                  <div className="text-sm text-gray-600">
                    Créé le: {new Date(poll.created_at).toLocaleDateString()}
                  </div>
                </div>

                <button
                  onClick={() => startSession(poll)}
                  disabled={activeSession !== null}
                  className="w-full bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white px-4 py-2 rounded-lg flex items-center justify-center gap-2 transition-colors"
                >
                  <Play className="w-4 h-4" />
                  Démarrer Session
                </button>
              </div>
            ))}
          </div>
        )}

        {activeSession && (
          <div className="mt-8 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div className="flex items-center gap-2 text-yellow-800">
              <Users className="w-5 h-5" />
              <span className="font-medium">
                Session active - PIN: {activeSession.pin}
              </span>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default AdminDashboard;