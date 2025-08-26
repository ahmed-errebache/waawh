import React, { useState, useEffect } from 'react';
import { ArrowLeft, ArrowRight, Users, Trophy, Medal, Award } from 'lucide-react';
import { Session, Poll, Question, Participant, Answer } from '../types';
import { supabase } from '../lib/supabase';

interface SessionManagerProps {
  session: Session;
  onEndSession: () => void;
}

const SessionManager: React.FC<SessionManagerProps> = ({ session, onEndSession }) => {
  const [poll, setPoll] = useState<Poll | null>(null);
  const [participants, setParticipants] = useState<Participant[]>([]);
  const [answers, setAnswers] = useState<Answer[]>([]);
  const [currentQuestion, setCurrentQuestion] = useState<Question | null>(null);
  const [showResults, setShowResults] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadSessionData();
    
    // Subscribe to real-time updates
    const participantsSubscription = supabase
      .channel('participants')
      .on('postgres_changes', 
        { event: '*', schema: 'public', table: 'participants', filter: `session_id=eq.${session.id}` },
        () => loadParticipants()
      )
      .subscribe();

    const answersSubscription = supabase
      .channel('answers')
      .on('postgres_changes',
        { event: '*', schema: 'public', table: 'answers', filter: `session_id=eq.${session.id}` },
        () => loadAnswers()
      )
      .subscribe();

    return () => {
      participantsSubscription.unsubscribe();
      answersSubscription.unsubscribe();
    };
  }, [session.id]);

  const loadSessionData = async () => {
    try {
      // Load poll with questions
      const { data: pollData, error: pollError } = await supabase
        .from('polls')
        .select(`
          *,
          questions (
            *,
            options:question_options (*)
          )
        `)
        .eq('id', session.poll_id)
        .single();

      if (pollError) throw pollError;
      setPoll(pollData);

      if (pollData.questions && pollData.questions.length > 0) {
        const sortedQuestions = pollData.questions.sort((a, b) => a.order_index - b.order_index);
        setCurrentQuestion(sortedQuestions[session.current_question_index] || sortedQuestions[0]);
      }

      await loadParticipants();
      await loadAnswers();
    } catch (error) {
      console.error('Error loading session data:', error);
    } finally {
      setLoading(false);
    }
  };

  const loadParticipants = async () => {
    try {
      const { data, error } = await supabase
        .from('participants')
        .select('*')
        .eq('session_id', session.id)
        .order('score', { ascending: false });

      if (error) throw error;
      setParticipants(data || []);
    } catch (error) {
      console.error('Error loading participants:', error);
    }
  };

  const loadAnswers = async () => {
    try {
      const { data, error } = await supabase
        .from('answers')
        .select('*')
        .eq('session_id', session.id);

      if (error) throw error;
      setAnswers(data || []);
    } catch (error) {
      console.error('Error loading answers:', error);
    }
  };

  const nextQuestion = async () => {
    if (!poll || !poll.questions) return;

    const sortedQuestions = poll.questions.sort((a, b) => a.order_index - b.order_index);
    const nextIndex = session.current_question_index + 1;

    if (nextIndex < sortedQuestions.length) {
      try {
        const { error } = await supabase
          .from('sessions')
          .update({
            current_question_index: nextIndex,
            show_results: false
          })
          .eq('id', session.id);

        if (error) throw error;

        session.current_question_index = nextIndex;
        setCurrentQuestion(sortedQuestions[nextIndex]);
        setShowResults(false);
      } catch (error) {
        console.error('Error updating session:', error);
      }
    }
  };

  const previousQuestion = async () => {
    if (!poll || !poll.questions) return;

    const sortedQuestions = poll.questions.sort((a, b) => a.order_index - b.order_index);
    const prevIndex = session.current_question_index - 1;

    if (prevIndex >= 0) {
      try {
        const { error } = await supabase
          .from('sessions')
          .update({
            current_question_index: prevIndex,
            show_results: false
          })
          .eq('id', session.id);

        if (error) throw error;

        session.current_question_index = prevIndex;
        setCurrentQuestion(sortedQuestions[prevIndex]);
        setShowResults(false);
      } catch (error) {
        console.error('Error updating session:', error);
      }
    }
  };

  const toggleResults = async () => {
    try {
      const { error } = await supabase
        .from('sessions')
        .update({ show_results: !showResults })
        .eq('id', session.id);

      if (error) throw error;
      setShowResults(!showResults);
    } catch (error) {
      console.error('Error toggling results:', error);
    }
  };

  const endSession = async () => {
    if (!confirm('Êtes-vous sûr de vouloir terminer cette session ?')) return;

    try {
      const { error } = await supabase
        .from('sessions')
        .update({ is_active: false })
        .eq('id', session.id);

      if (error) throw error;
      onEndSession();
    } catch (error) {
      console.error('Error ending session:', error);
    }
  };

  const getCurrentQuestionAnswers = () => {
    if (!currentQuestion) return [];
    return answers.filter(answer => answer.question_id === currentQuestion.id);
  };

  const getLeaderboard = () => {
    return participants
      .sort((a, b) => b.score - a.score)
      .slice(0, 10);
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-xl text-gray-600">Chargement de la session...</div>
      </div>
    );
  }

  if (!poll || !currentQuestion) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-xl text-red-600">Erreur: Impossible de charger les données de la session</div>
      </div>
    );
  }

  const sortedQuestions = poll.questions?.sort((a, b) => a.order_index - b.order_index) || [];
  const currentQuestionAnswers = getCurrentQuestionAnswers();
  const leaderboard = getLeaderboard();

  return (
    <div className="min-h-screen" style={{ backgroundColor: poll.background_color, color: poll.text_color }}>
      <div className="max-w-6xl mx-auto px-4 py-8">
        {/* Header */}
        <div className="flex justify-between items-center mb-8">
          <div className="flex items-center gap-4">
            <button
              onClick={onEndSession}
              className="text-gray-600 hover:text-gray-800 transition-colors"
            >
              <ArrowLeft className="w-6 h-6" />
            </button>
            <div>
              <h1 className="text-3xl font-bold">{poll.title}</h1>
              <div className="text-lg opacity-75">PIN: {session.pin}</div>
            </div>
          </div>
          
          <div className="flex items-center gap-4">
            <div className="flex items-center gap-2 bg-white bg-opacity-20 px-4 py-2 rounded-lg">
              <Users className="w-5 h-5" />
              <span>{participants.length} participants</span>
            </div>
            <button
              onClick={endSession}
              className="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors"
            >
              Terminer Session
            </button>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Main Content */}
          <div className="lg:col-span-2">
            <div className="bg-white bg-opacity-90 rounded-lg p-6 mb-6">
              <div className="flex justify-between items-center mb-4">
                <h2 className="text-xl font-semibold text-gray-900">
                  Question {session.current_question_index + 1} sur {sortedQuestions.length}
                </h2>
                <div className="flex gap-2">
                  <button
                    onClick={previousQuestion}
                    disabled={session.current_question_index === 0}
                    className="bg-gray-600 hover:bg-gray-700 disabled:bg-gray-400 text-white px-3 py-2 rounded-lg transition-colors"
                  >
                    <ArrowLeft className="w-4 h-4" />
                  </button>
                  <button
                    onClick={toggleResults}
                    className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors"
                  >
                    {showResults ? 'Masquer résultats' : 'Afficher résultats'}
                  </button>
                  <button
                    onClick={nextQuestion}
                    disabled={session.current_question_index >= sortedQuestions.length - 1}
                    className="bg-gray-600 hover:bg-gray-700 disabled:bg-gray-400 text-white px-3 py-2 rounded-lg transition-colors"
                  >
                    <ArrowRight className="w-4 h-4" />
                  </button>
                </div>
              </div>

              {/* Question Display */}
              <div className="mb-6">
                <h3 className="text-lg font-medium text-gray-900 mb-4">
                  {currentQuestion.question_text}
                </h3>
                
                {currentQuestion.media_url && (
                  <div className="mb-4">
                    {currentQuestion.media_type === 'image' && (
                      <img
                        src={currentQuestion.media_url}
                        alt="Question media"
                        className="max-w-full h-auto rounded-lg"
                      />
                    )}
                    {currentQuestion.media_type === 'video' && (
                      <video
                        src={currentQuestion.media_url}
                        controls
                        className="max-w-full h-auto rounded-lg"
                      />
                    )}
                    {currentQuestion.media_type === 'audio' && (
                      <audio
                        src={currentQuestion.media_url}
                        controls
                        className="w-full"
                      />
                    )}
                  </div>
                )}

                {/* Options Display */}
                {currentQuestion.options && currentQuestion.options.length > 0 && (
                  <div className="space-y-2">
                    {currentQuestion.options.map((option, index) => (
                      <div
                        key={option.id}
                        className={`p-3 rounded-lg border ${
                          showResults && option.is_correct
                            ? 'bg-green-100 border-green-500 text-green-800'
                            : 'bg-gray-50 border-gray-200 text-gray-800'
                        }`}
                      >
                        <div className="flex justify-between items-center">
                          <span>{String.fromCharCode(65 + index)}. {option.text}</span>
                          {showResults && (
                            <span className="text-sm">
                              {currentQuestionAnswers.filter(a => a.answer === option.id).length} réponses
                            </span>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* Results Display */}
              {showResults && (
                <div className="bg-gray-50 rounded-lg p-4">
                  <h4 className="font-medium text-gray-900 mb-2">Statistiques</h4>
                  <div className="text-sm text-gray-600">
                    <div>Réponses reçues: {currentQuestionAnswers.length}</div>
                    <div>Réponses correctes: {currentQuestionAnswers.filter(a => a.is_correct).length}</div>
                    <div>Taux de réussite: {
                      currentQuestionAnswers.length > 0
                        ? Math.round((currentQuestionAnswers.filter(a => a.is_correct).length / currentQuestionAnswers.length) * 100)
                        : 0
                    }%</div>
                  </div>

                  {currentQuestion.explanation_text && (
                    <div className="mt-4 p-3 bg-blue-50 rounded-lg">
                      <h5 className="font-medium text-blue-900 mb-2">Explication</h5>
                      <p className="text-blue-800">{currentQuestion.explanation_text}</p>
                      
                      {currentQuestion.explanation_media_url && (
                        <div className="mt-2">
                          {currentQuestion.explanation_media_type === 'image' && (
                            <img
                              src={currentQuestion.explanation_media_url}
                              alt="Explanation media"
                              className="max-w-full h-auto rounded-lg"
                            />
                          )}
                          {currentQuestion.explanation_media_type === 'video' && (
                            <video
                              src={currentQuestion.explanation_media_url}
                              controls
                              className="max-w-full h-auto rounded-lg"
                            />
                          )}
                        </div>
                      )}
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>

          {/* Sidebar - Leaderboard */}
          <div className="lg:col-span-1">
            <div className="bg-white bg-opacity-90 rounded-lg p-6">
              <h3 className="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <Trophy className="w-5 h-5 text-yellow-500" />
                Classement
              </h3>
              
              {leaderboard.length === 0 ? (
                <div className="text-gray-500 text-center py-4">
                  Aucun participant pour le moment
                </div>
              ) : (
                <div className="space-y-3">
                  {leaderboard.map((participant, index) => (
                    <div
                      key={participant.id}
                      className={`flex items-center gap-3 p-3 rounded-lg ${
                        index === 0 ? 'bg-yellow-50 border border-yellow-200' :
                        index === 1 ? 'bg-gray-50 border border-gray-200' :
                        index === 2 ? 'bg-orange-50 border border-orange-200' :
                        'bg-white border border-gray-100'
                      }`}
                    >
                      <div className="flex-shrink-0">
                        {index === 0 && <Trophy className="w-5 h-5 text-yellow-500" />}
                        {index === 1 && <Medal className="w-5 h-5 text-gray-500" />}
                        {index === 2 && <Award className="w-5 h-5 text-orange-500" />}
                        {index > 2 && (
                          <div className="w-5 h-5 bg-gray-200 rounded-full flex items-center justify-center text-xs font-medium text-gray-600">
                            {index + 1}
                          </div>
                        )}
                      </div>
                      
                      <div className="flex-1 min-w-0">
                        <div className="font-medium text-gray-900 truncate">
                          {participant.name}
                        </div>
                        <div className="text-sm text-gray-500">
                          {participant.score} points
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default SessionManager;