/*
  # Système de sondage complet

  1. Nouvelles tables
    - `polls` - Stocke les sondages avec titre, thème, couleurs
    - `questions` - Questions avec différents types et médias
    - `question_options` - Options pour les questions à choix multiple
    - `sessions` - Sessions actives avec PIN
    - `participants` - Participants aux sessions
    - `answers` - Réponses des participants

  2. Sécurité
    - RLS activé sur toutes les tables
    - Politiques pour lecture/écriture publique (pour simplicité)

  3. Fonctionnalités
    - Support de différents types de questions
    - Upload de médias (images, vidéos, audio, PDF)
    - Système de points et classement
    - Sessions en temps réel avec PIN
*/

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Create storage bucket for media files
INSERT INTO storage.buckets (id, name, public) 
VALUES ('media', 'media', true)
ON CONFLICT (id) DO NOTHING;

-- Polls table
CREATE TABLE IF NOT EXISTS polls (
  id uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  title text NOT NULL,
  theme text DEFAULT '',
  background_color text DEFAULT '#ffffff',
  text_color text DEFAULT '#000000',
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

-- Questions table
CREATE TABLE IF NOT EXISTS questions (
  id uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  poll_id uuid REFERENCES polls(id) ON DELETE CASCADE,
  order_index integer NOT NULL DEFAULT 0,
  question_text text NOT NULL,
  question_type text NOT NULL CHECK (question_type IN (
    'multiple_choice', 'single_choice', 'true_false', 
    'short_answer', 'long_answer', 'rating', 'date', 'number'
  )),
  media_type text CHECK (media_type IN ('image', 'video', 'audio', 'pdf')),
  media_url text,
  correct_answer text,
  explanation_text text,
  explanation_media_type text CHECK (explanation_media_type IN ('image', 'video', 'audio', 'pdf')),
  explanation_media_url text,
  points integer DEFAULT 1,
  is_scored boolean DEFAULT true,
  created_at timestamptz DEFAULT now()
);

-- Question options table
CREATE TABLE IF NOT EXISTS question_options (
  id uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  question_id uuid REFERENCES questions(id) ON DELETE CASCADE,
  text text NOT NULL,
  is_correct boolean DEFAULT false,
  created_at timestamptz DEFAULT now()
);

-- Sessions table
CREATE TABLE IF NOT EXISTS sessions (
  id uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  poll_id uuid REFERENCES polls(id) ON DELETE CASCADE,
  pin text NOT NULL UNIQUE,
  is_active boolean DEFAULT true,
  current_question_index integer DEFAULT 0,
  show_results boolean DEFAULT false,
  created_at timestamptz DEFAULT now()
);

-- Participants table
CREATE TABLE IF NOT EXISTS participants (
  id uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  session_id uuid REFERENCES sessions(id) ON DELETE CASCADE,
  name text NOT NULL,
  score integer DEFAULT 0,
  joined_at timestamptz DEFAULT now(),
  UNIQUE(session_id, name)
);

-- Answers table
CREATE TABLE IF NOT EXISTS answers (
  id uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  session_id uuid REFERENCES sessions(id) ON DELETE CASCADE,
  participant_id uuid REFERENCES participants(id) ON DELETE CASCADE,
  question_id uuid REFERENCES questions(id) ON DELETE CASCADE,
  answer text NOT NULL,
  is_correct boolean DEFAULT false,
  points_earned integer DEFAULT 0,
  answered_at timestamptz DEFAULT now(),
  UNIQUE(session_id, participant_id, question_id)
);

-- Enable RLS
ALTER TABLE polls ENABLE ROW LEVEL SECURITY;
ALTER TABLE questions ENABLE ROW LEVEL SECURITY;
ALTER TABLE question_options ENABLE ROW LEVEL SECURITY;
ALTER TABLE sessions ENABLE ROW LEVEL SECURITY;
ALTER TABLE participants ENABLE ROW LEVEL SECURITY;
ALTER TABLE answers ENABLE ROW LEVEL SECURITY;

-- Create policies for public access (simplified for demo)
CREATE POLICY "Public access to polls" ON polls FOR ALL USING (true);
CREATE POLICY "Public access to questions" ON questions FOR ALL USING (true);
CREATE POLICY "Public access to question_options" ON question_options FOR ALL USING (true);
CREATE POLICY "Public access to sessions" ON sessions FOR ALL USING (true);
CREATE POLICY "Public access to participants" ON participants FOR ALL USING (true);
CREATE POLICY "Public access to answers" ON answers FOR ALL USING (true);

-- Storage policies
CREATE POLICY "Public access to media bucket" ON storage.objects FOR ALL USING (bucket_id = 'media');

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_questions_poll_id ON questions(poll_id);
CREATE INDEX IF NOT EXISTS idx_questions_order ON questions(poll_id, order_index);
CREATE INDEX IF NOT EXISTS idx_question_options_question_id ON question_options(question_id);
CREATE INDEX IF NOT EXISTS idx_sessions_pin ON sessions(pin);
CREATE INDEX IF NOT EXISTS idx_sessions_active ON sessions(is_active);
CREATE INDEX IF NOT EXISTS idx_participants_session_id ON participants(session_id);
CREATE INDEX IF NOT EXISTS idx_participants_score ON participants(session_id, score DESC);
CREATE INDEX IF NOT EXISTS idx_answers_session_participant ON answers(session_id, participant_id);
CREATE INDEX IF NOT EXISTS idx_answers_question ON answers(question_id);