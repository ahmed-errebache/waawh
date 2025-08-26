import React, { useState, useEffect } from 'react';
import { ArrowLeft, Plus, Trash2, Upload, Star } from 'lucide-react';
import { Poll, Question, QuestionType, QuestionOption } from '../types';
import { supabase } from '../lib/supabase';
import { v4 as uuidv4 } from 'uuid';

interface PollEditorProps {
  poll: Poll | null;
  onSave: () => void;
  onCancel: () => void;
}

const PollEditor: React.FC<PollEditorProps> = ({ poll, onSave, onCancel }) => {
  const [title, setTitle] = useState(poll?.title || '');
  const [theme, setTheme] = useState(poll?.theme || '');
  const [backgroundColor, setBackgroundColor] = useState(poll?.background_color || '#ffffff');
  const [textColor, setTextColor] = useState(poll?.text_color || '#000000');
  const [questions, setQuestions] = useState<Question[]>(poll?.questions || []);
  const [saving, setSaving] = useState(false);

  const questionTypes: { value: QuestionType; label: string }[] = [
    { value: 'multiple_choice', label: 'Choix multiple' },
    { value: 'single_choice', label: 'Choix unique' },
    { value: 'true_false', label: 'Vrai/Faux' },
    { value: 'short_answer', label: 'Réponse courte' },
    { value: 'long_answer', label: 'Réponse longue' },
    { value: 'rating', label: 'Évaluation (étoiles)' },
    { value: 'date', label: 'Date' },
    { value: 'number', label: 'Nombre' }
  ];

  const addQuestion = () => {
    const newQuestion: Question = {
      id: uuidv4(),
      poll_id: poll?.id || '',
      order_index: questions.length,
      question_text: '',
      question_type: 'multiple_choice',
      options: [
        { id: uuidv4(), text: '', is_correct: false },
        { id: uuidv4(), text: '', is_correct: false }
      ],
      points: 1,
      is_scored: true
    };
    setQuestions([...questions, newQuestion]);
  };

  const updateQuestion = (index: number, updates: Partial<Question>) => {
    const updatedQuestions = [...questions];
    updatedQuestions[index] = { ...updatedQuestions[index], ...updates };
    setQuestions(updatedQuestions);
  };

  const deleteQuestion = (index: number) => {
    setQuestions(questions.filter((_, i) => i !== index));
  };

  const addOption = (questionIndex: number) => {
    const updatedQuestions = [...questions];
    const question = updatedQuestions[questionIndex];
    if (question.options) {
      question.options.push({
        id: uuidv4(),
        text: '',
        is_correct: false
      });
    }
    setQuestions(updatedQuestions);
  };

  const updateOption = (questionIndex: number, optionIndex: number, updates: Partial<QuestionOption>) => {
    const updatedQuestions = [...questions];
    const question = updatedQuestions[questionIndex];
    if (question.options) {
      question.options[optionIndex] = { ...question.options[optionIndex], ...updates };
    }
    setQuestions(updatedQuestions);
  };

  const deleteOption = (questionIndex: number, optionIndex: number) => {
    const updatedQuestions = [...questions];
    const question = updatedQuestions[questionIndex];
    if (question.options) {
      question.options = question.options.filter((_, i) => i !== optionIndex);
    }
    setQuestions(updatedQuestions);
  };

  const handleFileUpload = async (file: File, questionIndex: number, type: 'question' | 'explanation') => {
    try {
      const fileExt = file.name.split('.').pop();
      const fileName = `${uuidv4()}.${fileExt}`;
      const filePath = `poll-media/${fileName}`;

      const { error: uploadError } = await supabase.storage
        .from('media')
        .upload(filePath, file);

      if (uploadError) throw uploadError;

      const { data } = supabase.storage
        .from('media')
        .getPublicUrl(filePath);

      const mediaType = file.type.startsWith('image/') ? 'image' :
                       file.type.startsWith('video/') ? 'video' :
                       file.type.startsWith('audio/') ? 'audio' : 'pdf';

      if (type === 'question') {
        updateQuestion(questionIndex, {
          media_type: mediaType,
          media_url: data.publicUrl
        });
      } else {
        updateQuestion(questionIndex, {
          explanation_media_type: mediaType,
          explanation_media_url: data.publicUrl
        });
      }
    } catch (error) {
      console.error('Error uploading file:', error);
      alert('Erreur lors du téléchargement du fichier');
    }
  };

  const savePoll = async () => {
    if (!title.trim()) {
      alert('Veuillez saisir un titre pour le sondage');
      return;
    }

    setSaving(true);
    try {
      const pollData = {
        title: title.trim(),
        theme: theme.trim(),
        background_color: backgroundColor,
        text_color: textColor,
        updated_at: new Date().toISOString()
      };

      let pollId = poll?.id;

      if (poll) {
        // Update existing poll
        const { error } = await supabase
          .from('polls')
          .update(pollData)
          .eq('id', poll.id);

        if (error) throw error;
      } else {
        // Create new poll
        const { data, error } = await supabase
          .from('polls')
          .insert({
            ...pollData,
            id: uuidv4(),
            created_at: new Date().toISOString()
          })
          .select()
          .single();

        if (error) throw error;
        pollId = data.id;
      }

      // Delete existing questions if updating
      if (poll) {
        await supabase
          .from('questions')
          .delete()
          .eq('poll_id', poll.id);
      }

      // Insert questions
      for (let i = 0; i < questions.length; i++) {
        const question = questions[i];
        const questionData = {
          id: uuidv4(),
          poll_id: pollId,
          order_index: i,
          question_text: question.question_text,
          question_type: question.question_type,
          media_type: question.media_type,
          media_url: question.media_url,
          correct_answer: question.correct_answer,
          explanation_text: question.explanation_text,
          explanation_media_type: question.explanation_media_type,
          explanation_media_url: question.explanation_media_url,
          points: question.points,
          is_scored: question.is_scored
        };

        const { data: questionResult, error: questionError } = await supabase
          .from('questions')
          .insert(questionData)
          .select()
          .single();

        if (questionError) throw questionError;

        // Insert options if they exist
        if (question.options && question.options.length > 0) {
          const optionsData = question.options.map(option => ({
            id: uuidv4(),
            question_id: questionResult.id,
            text: option.text,
            is_correct: option.is_correct
          }));

          const { error: optionsError } = await supabase
            .from('question_options')
            .insert(optionsData);

          if (optionsError) throw optionsError;
        }
      }

      onSave();
    } catch (error) {
      console.error('Error saving poll:', error);
      alert('Erreur lors de la sauvegarde du sondage');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-4xl mx-auto px-4 py-8">
        <div className="flex items-center gap-4 mb-8">
          <button
            onClick={onCancel}
            className="text-gray-600 hover:text-gray-800 transition-colors"
          >
            <ArrowLeft className="w-6 h-6" />
          </button>
          <h1 className="text-3xl font-bold text-gray-900">
            {poll ? 'Modifier le sondage' : 'Créer un sondage'}
          </h1>
        </div>

        <div className="bg-white rounded-lg shadow-md p-6 mb-6">
          <h2 className="text-xl font-semibold mb-4">Informations générales</h2>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Titre du sondage
              </label>
              <input
                type="text"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Entrez le titre du sondage"
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Thème
              </label>
              <input
                type="text"
                value={theme}
                onChange={(e) => setTheme(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Thème du sondage"
              />
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Couleur de fond
              </label>
              <input
                type="color"
                value={backgroundColor}
                onChange={(e) => setBackgroundColor(e.target.value)}
                className="w-full h-10 border border-gray-300 rounded-lg"
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Couleur du texte
              </label>
              <input
                type="color"
                value={textColor}
                onChange={(e) => setTextColor(e.target.value)}
                className="w-full h-10 border border-gray-300 rounded-lg"
              />
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow-md p-6 mb-6">
          <div className="flex justify-between items-center mb-4">
            <h2 className="text-xl font-semibold">Questions</h2>
            <button
              onClick={addQuestion}
              className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors"
            >
              <Plus className="w-4 h-4" />
              Ajouter une question
            </button>
          </div>

          {questions.map((question, questionIndex) => (
            <div key={question.id} className="border border-gray-200 rounded-lg p-4 mb-4">
              <div className="flex justify-between items-start mb-4">
                <h3 className="text-lg font-medium">Question {questionIndex + 1}</h3>
                <button
                  onClick={() => deleteQuestion(questionIndex)}
                  className="text-red-600 hover:text-red-800 transition-colors"
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Type de question
                  </label>
                  <select
                    value={question.question_type}
                    onChange={(e) => updateQuestion(questionIndex, { question_type: e.target.value as QuestionType })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  >
                    {questionTypes.map(type => (
                      <option key={type.value} value={type.value}>
                        {type.label}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="flex items-center gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Points
                    </label>
                    <input
                      type="number"
                      min="0"
                      value={question.points}
                      onChange={(e) => updateQuestion(questionIndex, { points: parseInt(e.target.value) || 0 })}
                      className="w-20 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    />
                  </div>
                  <div className="flex items-center mt-6">
                    <input
                      type="checkbox"
                      checked={question.is_scored}
                      onChange={(e) => updateQuestion(questionIndex, { is_scored: e.target.checked })}
                      className="mr-2"
                    />
                    <label className="text-sm text-gray-700">Question notée</label>
                  </div>
                </div>
              </div>

              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Texte de la question
                </label>
                <textarea
                  value={question.question_text}
                  onChange={(e) => updateQuestion(questionIndex, { question_text: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  rows={3}
                  placeholder="Entrez votre question"
                />
              </div>

              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Média pour la question
                </label>
                <input
                  type="file"
                  accept="image/*,video/*,audio/*,.pdf"
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) handleFileUpload(file, questionIndex, 'question');
                  }}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                />
                {question.media_url && (
                  <div className="mt-2">
                    <span className="text-sm text-green-600">✓ Fichier téléchargé</span>
                  </div>
                )}
              </div>

              {(question.question_type === 'multiple_choice' || question.question_type === 'single_choice') && (
                <div className="mb-4">
                  <div className="flex justify-between items-center mb-2">
                    <label className="block text-sm font-medium text-gray-700">
                      Options de réponse
                    </label>
                    <button
                      onClick={() => addOption(questionIndex)}
                      className="text-blue-600 hover:text-blue-800 text-sm"
                    >
                      + Ajouter une option
                    </button>
                  </div>
                  
                  {question.options?.map((option, optionIndex) => (
                    <div key={option.id} className="flex items-center gap-2 mb-2">
                      <input
                        type="checkbox"
                        checked={option.is_correct}
                        onChange={(e) => updateOption(questionIndex, optionIndex, { is_correct: e.target.checked })}
                        className="flex-shrink-0"
                      />
                      <input
                        type="text"
                        value={option.text}
                        onChange={(e) => updateOption(questionIndex, optionIndex, { text: e.target.value })}
                        className="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Texte de l'option"
                      />
                      <button
                        onClick={() => deleteOption(questionIndex, optionIndex)}
                        className="text-red-600 hover:text-red-800 flex-shrink-0"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  ))}
                </div>
              )}

              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Explication de la réponse
                </label>
                <textarea
                  value={question.explanation_text || ''}
                  onChange={(e) => updateQuestion(questionIndex, { explanation_text: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  rows={2}
                  placeholder="Explication optionnelle"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Média pour l'explication
                </label>
                <input
                  type="file"
                  accept="image/*,video/*,audio/*,.pdf"
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) handleFileUpload(file, questionIndex, 'explanation');
                  }}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                />
                {question.explanation_media_url && (
                  <div className="mt-2">
                    <span className="text-sm text-green-600">✓ Fichier téléchargé</span>
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>

        <div className="flex justify-end gap-4">
          <button
            onClick={onCancel}
            className="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
          >
            Annuler
          </button>
          <button
            onClick={savePoll}
            disabled={saving}
            className="px-6 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white rounded-lg transition-colors"
          >
            {saving ? 'Sauvegarde...' : 'Sauvegarder'}
          </button>
        </div>
      </div>
    </div>
  );
};

export default PollEditor;