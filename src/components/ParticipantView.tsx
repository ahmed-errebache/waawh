import React, { useState, useEffect } from 'react';
import { Star, Send } from 'lucide-react';
import { Session, Poll, Question, Participant } from '../types';
import { supabase } from '../lib/supabase';
import { v4 as uuidv4 } from 'uuid';

interface ParticipantViewProps {
  session: Session;
  participant: Participant;
}

const ParticipantView: React.FC<ParticipantViewProps> = ({ session, participant }) => {
  const [poll, setPoll] = useState<Poll | null>(null);
  const [currentQuestion, setCurrentQuestion] = useState<Question | null>(null);
  const [answer, setAnswer] = useState<string>('');
  const [rating, setRating] = useState<number>(0);
  const [hasAnswered, setHasAnswered] = useState(false);
  const [showResults, setShowResults] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadSessionData();
    
    // Subscribe to session updates
    const sessionSubscription = supabase
      .channel('session_updates')
      .on('postgres_changes',
        { event: 'UPDATE', schema: 'public', table: 'sessions', filter: `id=eq.${session.id}` },
        (payload) => {
          const updatedSession = payload.new as Session;
          setShowResults(updatedSession.show_results);
          
          if (updatedSession.current_question_index !== session.current_question_index) {
            session.current_question_index = updatedSession.current_question_index;
            loadCurrentQuestion();
            setHasAnswered(false);
            setAnswer('');
            setRating(0);
          }
        }
      )
      .subscribe();

    return () => {
      sessionSubscription.unsubscribe();
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

      await loadCurrentQuestion();
      await checkIfAnswered();
    } catch (error) {
      console.error('Error loading session data:', error);
    } finally {
      setLoading(false);
    }
  };

  const loadCurrentQuestion = async () => {
    try {
      const { data: pollData, error } = await supabase
        .from('polls')
        .select(`
          questions (
            *,
            options:question_options (*)
          )
        `)
        .eq('id', session.poll_id)
        .single();

      if (error) throw error;

      if (pollData.questions && pollData.questions.length > 0) {
        const sortedQuestions = pollData.questions.sort((a, b) => a.order_index - b.order_index);
        setCurrentQuestion(sortedQuestions[session.current_question_index] || null);
      }
    } catch (error) {
      console.error('Error loading current question:', error);
    }
  };

  const checkIfAnswered = async () => {
    if (!currentQuestion) return;

    try {
      const { data, error } = await supabase
        .from('answers')
        .select('*')
        .eq('session_id', session.id)
        .eq('participant_id', participant.id)
        .eq('question_id', currentQuestion.id)
        .single();

      if (error && error.code !== 'PGRST116') throw error;
      setHasAnswered(!!data);
    } catch (error) {
      console.error('Error checking if answered:', error);
    }
  };

  const submitAnswer = async () => {
    if (!currentQuestion || !answer.trim()) return;

    try {
      let isCorrect = false;
      let pointsEarned = 0;

      // Check if answer is correct
      if (currentQuestion.question_type === 'multiple_choice' || currentQuestion.question_type === 'single_choice') {
        const selectedOption = currentQuestion.options?.find(opt => opt.id === answer);
        isCorrect = selectedOption?.is_correct || false;
      } else if (currentQuestion.question_type === 'true_false') {
        isCorrect = answer === currentQuestion.correct_answer;
      }

      if (isCorrect && currentQuestion.is_scored) {
        pointsEarned = currentQuestion.points;
      }

      // Submit answer
      const { error: answerError } = await supabase
        .from('answers')
        .insert({
          id: uuidv4(),
          session_id: session.id,
          participant_id: participant.id,
          question_id: currentQuestion.id,
          answer: currentQuestion.question_type === 'rating' ? rating.toString() : answer,
          is_correct: isCorrect,
          points_earned: pointsEarned,
          answered_at: new Date().toISOString()
        });

      if (answerError) throw answerError;

      // Update participant score
      const { error: scoreError } = await supabase
        .from('participants')
        .update({ score: participant.score + pointsEarned })
        .eq('id', participant.id);

      if (scoreError) throw scoreError;

      participant.score += pointsEarned;
      setHasAnswered(true);
    } catch (error) {
      console.error('Error submitting answer:', error);
      alert('Erreur lors de l\'envoi de la réponse');
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-xl text-gray-600">Chargement...</div>
      </div>
    );
  }

  if (!poll || !currentQuestion) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="text-xl text-gray-600 mb-4">En attente de la prochaine question...</div>
          <div className="text-lg text-gray-500">Score actuel: {participant.score} points</div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen" style={{ backgroundColor: poll.background_color, color: poll.text_color }}>
      <div className="max-w-2xl mx-auto px-4 py-8">
        {/* Header */}
        <div className="text-center mb-8">
          <h1 className="text-2xl font-bold mb-2">{poll.title}</h1>
          <div className="text-lg opacity-75">
            Bonjour {participant.name} | Score: {participant.score} points
          </div>
        </div>

        {/* Question */}
        <div className="bg-white bg-opacity-90 rounded-lg p-6 mb-6">
          <h2 className="text-xl font-semibold text-gray-900 mb-4">
            {currentQuestion.question_text}
          </h2>

          {/* Media */}
          {currentQuestion.media_url && (
            <div className="mb-6">
              {currentQuestion.media_type === 'image' && (
                <img
                  src={currentQuestion.media_url}
                  alt="Question media"
                  className="max-w-full h-auto rounded-lg mx-auto"
                />
              )}
              {currentQuestion.media_type === 'video' && (
                <video
                  src={currentQuestion.media_url}
                  controls
                  className="max-w-full h-auto rounded-lg mx-auto"
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

          {/* Answer Input */}
          {!hasAnswered && !showResults && (
            <div className="space-y-4">
              {/* Multiple Choice / Single Choice */}
              {(currentQuestion.question_type === 'multiple_choice' || currentQuestion.question_type === 'single_choice') && (
                <div className="space-y-3">
                  {currentQuestion.options?.map((option, index) => (
                    <button
                      key={option.id}
                      onClick={() => setAnswer(option.id)}
                      className={`w-full p-4 text-left rounded-lg border-2 transition-colors ${
                        answer === option.id
                          ? 'border-blue-500 bg-blue-50 text-blue-900'
                          : 'border-gray-200 bg-white text-gray-900 hover:border-gray-300'
                      }`}
                    >
                      <span className="font-medium">{String.fromCharCode(65 + index)}.</span> {option.text}
                    </button>
                  ))}
                </div>
              )}

              {/* True/False */}
              {currentQuestion.question_type === 'true_false' && (
                <div className="grid grid-cols-2 gap-4">
                  <button
                    onClick={() => setAnswer('true')}
                    className={`p-4 rounded-lg border-2 transition-colors ${
                      answer === 'true'
                        ? 'border-green-500 bg-green-50 text-green-900'
                        : 'border-gray-200 bg-white text-gray-900 hover:border-gray-300'
                    }`}
                  >
                    Vrai
                  </button>
                  <button
                    onClick={() => setAnswer('false')}
                    className={`p-4 rounded-lg border-2 transition-colors ${
                      answer === 'false'
                        ? 'border-red-500 bg-red-50 text-red-900'
                        : 'border-gray-200 bg-white text-gray-900 hover:border-gray-300'
                    }`}
                  >
                    Faux
                  </button>
                </div>
              )}

              {/* Short Answer */}
              {currentQuestion.question_type === 'short_answer' && (
                <input
                  type="text"
                  value={answer}
                  onChange={(e) => setAnswer(e.target.value)}
                  className="w-full p-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900"
                  placeholder="Votre réponse..."
                />
              )}

              {/* Long Answer */}
              {currentQuestion.question_type === 'long_answer' && (
                <textarea
                  value={answer}
                  onChange={(e) => setAnswer(e.target.value)}
                  className="w-full p-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900"
                  rows={4}
                  placeholder="Votre réponse..."
                />
              )}

              {/* Rating */}
              {currentQuestion.question_type === 'rating' && (
                <div className="flex justify-center space-x-2">
                  {[1, 2, 3, 4, 5].map((star) => (
                    <button
                      key={star}
                      onClick={() => {
                        setRating(star);
                        setAnswer(star.toString());
                      }}
                      className="p-2"
                    >
                      <Star
                        className={`w-8 h-8 ${
                          star <= rating
                            ? 'text-yellow-400 fill-current'
                            : 'text-gray-300'
                        }`}
                      />
                    </button>
                  ))}
                </div>
              )}

              {/* Date */}
              {currentQuestion.question_type === 'date' && (
                <input
                  type="date"
                  value={answer}
                  onChange={(e) => setAnswer(e.target.value)}
                  className="w-full p-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900"
                />
              )}

              {/* Number */}
              {currentQuestion.question_type === 'number' && (
                <input
                  type="number"
                  value={answer}
                  onChange={(e) => setAnswer(e.target.value)}
                  className="w-full p-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900"
                  placeholder="Votre réponse..."
                />
              )}

              <button
                onClick={submitAnswer}
                disabled={!answer.trim() && currentQuestion.question_type !== 'rating'}
                className="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white px-6 py-3 rounded-lg flex items-center justify-center gap-2 transition-colors"
              >
                <Send className="w-4 h-4" />
                Envoyer la réponse
              </button>
            </div>
          )}

          {/* Waiting State */}
          {hasAnswered && !showResults && (
            <div className="text-center py-8">
              <div className="text-lg text-gray-600 mb-2">✓ Réponse envoyée</div>
              <div className="text-gray-500">En attente des autres participants...</div>
            </div>
          )}

          {/* Results */}
          {showResults && (
            <div className="bg-gray-50 rounded-lg p-4">
              <h4 className="font-medium text-gray-900 mb-2">Résultats</h4>
              
              {currentQuestion.explanation_text && (
                <div className="mb-4 p-3 bg-blue-50 rounded-lg">
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
    </div>
  );
};

export default ParticipantView;