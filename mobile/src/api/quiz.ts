import { api } from './client';

export interface Question {
  id: string | number;
  question_text: string;
  options: { key: string; text: string }[];
  subject?: string;
  image_url?: string;
}

export interface AttemptResponse {
  uuid: string;
  day_number: number;
  status: string;
  questions: Question[];
  mastered_count: number;
  required_count: number;
}

export interface AnswerResult {
  is_correct: boolean;
  correct_option?: string;
  correct_answer_text?: string;
  explanation?: string;
  mastered_count: number;
}

export interface ProgressData {
  days_completed: number;
  current_streak: number;
  current_day: number;
  current_level: number;
  questions_mastered: number;
  total_questions: number;
}

export const quizApi = {
  getDays: async () => {
    const { data } = await api.get('/student/days');
    return data.data;
  },

  getDay: async (day: number) => {
    const { data } = await api.get(`/student/days/${day}`);
    return data.data;
  },

  startAttempt: async (day: number): Promise<AttemptResponse> => {
    const { data } = await api.post(`/student/days/${day}/attempt`);
    return data.data;
  },

  submitAnswer: async (attemptUuid: string, questionId: string | number, selectedOption: string): Promise<AnswerResult> => {
    const clientUuid = `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    const { data } = await api.post(`/student/attempts/${attemptUuid}/answers`, {
      question_id: questionId,
      selected_option: selectedOption,
      client_answer_uuid: clientUuid,
    });
    return data.data;
  },

  completeAttempt: async (attemptUuid: string) => {
    const { data } = await api.post(`/student/attempts/${attemptUuid}/complete`);
    return data.data;
  },

  getProgress: async (): Promise<ProgressData> => {
    const { data } = await api.get('/student/progress');
    return data.data;
  },

  getDashboard: async () => {
    const { data } = await api.get('/student/dashboard');
    return data.data;
  },
};
