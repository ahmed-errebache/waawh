export interface Poll {
  id: string;
  title: string;
  theme: string;
  background_color: string;
  text_color: string;
  created_at: string;
  updated_at: string;
  questions: Question[];
}

export interface Question {
  id: string;
  poll_id: string;
  order_index: number;
  question_text: string;
  question_type: QuestionType;
  media_type?: 'image' | 'video' | 'audio' | 'pdf';
  media_url?: string;
  options?: QuestionOption[];
  correct_answer?: string;
  explanation_text?: string;
  explanation_media_type?: 'image' | 'video' | 'audio' | 'pdf';
  explanation_media_url?: string;
  points: number;
  is_scored: boolean;
}

export interface QuestionOption {
  id: string;
  text: string;
  is_correct: boolean;
}

export type QuestionType = 
  | 'multiple_choice'
  | 'true_false'
  | 'short_answer'
  | 'long_answer'
  | 'rating'
  | 'date'
  | 'number'
  | 'single_choice';

export interface Session {
  id: string;
  poll_id: string;
  pin: string;
  is_active: boolean;
  current_question_index: number;
  show_results: boolean;
  created_at: string;
}

export interface Participant {
  id: string;
  session_id: string;
  name: string;
  score: number;
  joined_at: string;
}

export interface Answer {
  id: string;
  session_id: string;
  participant_id: string;
  question_id: string;
  answer: string;
  is_correct: boolean;
  points_earned: number;
  answered_at: string;
}