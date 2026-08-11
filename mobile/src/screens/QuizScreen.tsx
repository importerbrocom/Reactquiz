import React, { useState, useRef, useCallback } from 'react';
import { View, Text, TouchableOpacity, ScrollView, StyleSheet, Alert } from 'react-native';
import { useRoute, useNavigation } from '@react-navigation/native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../api/client';
import type { QuizQuestion, AnswerResult } from '../types/models';

function uuid() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
  });
}

export function QuizScreen() {
  const route = useRoute<any>();
  const navigation = useNavigation();
  const queryClient = useQueryClient();
  const { courseId, day } = route.params;

  const [currentIdx, setCurrentIdx] = useState(0);
  const [selected, setSelected] = useState<string | null>(null);
  const [result, setResult] = useState<AnswerResult | null>(null);
  const [mastered, setMastered] = useState(0);
  const timeRef = useRef(Date.now());

  // Fetch quiz day
  const { data: quizDay } = useQuery({
    queryKey: ['quiz', courseId, day],
    queryFn: async () => {
      const { data } = await apiClient.get(`/student/courses/${courseId}/quiz-days/${day}`);
      // Start attempt
      await apiClient.post(`/student/courses/${courseId}/quiz-days/${day}/attempts`);
      return data.data;
    },
  });

  const questions: QuizQuestion[] = quizDay?.questions || [];
  const attemptUuid = quizDay?.attempt?.uuid;
  const currentQ = questions[currentIdx];

  // Submit answer
  const submitMutation = useMutation({
    mutationFn: async () => {
      const { data } = await apiClient.post(
        `/student/quiz-attempts/${attemptUuid}/answers`,
        {
          question_id: currentQ.id,
          selected_option: selected,
          client_answer_uuid: uuid(),
          time_spent_ms: Date.now() - timeRef.current,
          answered_at: new Date().toISOString(),
          was_offline: false,
        },
        { headers: { 'Idempotency-Key': uuid() } },
      );
      return data.data as AnswerResult;
    },
    onSuccess: (data) => {
      setResult(data);
      setMastered(data.progress.mastered_count);
      if (data.is_correct) {
        setTimeout(() => handleNext(), 500);
      }
    },
  });

  // Complete
  const completeMutation = useMutation({
    mutationFn: async () => {
      const { data } = await apiClient.post(
        `/student/quiz-attempts/${attemptUuid}/complete`,
        {},
        { headers: { 'Idempotency-Key': uuid() } },
      );
      return data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      Alert.alert('Day Complete!', `${mastered}/10 mastered`, [
        { text: 'OK', onPress: () => navigation.goBack() },
      ]);
    },
  });

  const handleNext = useCallback(() => {
    setSelected(null);
    setResult(null);
    timeRef.current = Date.now();
    if (currentIdx + 1 < questions.length) {
      setCurrentIdx(currentIdx + 1);
    } else if (mastered >= 10) {
      completeMutation.mutate();
    }
  }, [currentIdx, questions.length, mastered]);

  if (!currentQ) {
    return <View style={s.container}><Text style={s.loading}>Loading quiz...</Text></View>;
  }

  return (
    <View style={s.container}>
      {/* Header */}
      <View style={s.header}>
        <TouchableOpacity onPress={() => navigation.goBack()}>
          <Text style={s.closeBtn}>✕</Text>
        </TouchableOpacity>
        <Text style={s.headerText}>Day {day}</Text>
        <Text style={s.score}>{mastered}/10</Text>
      </View>

      {/* Progress bar */}
      <View style={s.progressBar}>
        <View style={[s.progressFill, { width: `${(mastered / 10) * 100}%` }]} />
      </View>

      <ScrollView style={s.body}>
        {/* Question */}
        <Text style={s.qNum}>Q{currentIdx + 1} of {questions.length}</Text>
        <Text style={s.qText}>{currentQ.text}</Text>

        {/* Options */}
        {currentQ.options
          .sort((a, b) => a.display_position - b.display_position)
          .map((opt) => {
            let bg = '#1e293b';
            let border = '#334155';
            if (result) {
              if (opt.key === result.correct_option) { bg = '#052e16'; border = '#22c55e'; }
              else if (opt.key === selected && !result.is_correct) { bg = '#450a0a'; border = '#ef4444'; }
            } else if (opt.key === selected) { bg = '#1e1b4b'; border = '#818cf8'; }

            return (
              <TouchableOpacity
                key={opt.key}
                style={[s.option, { backgroundColor: bg, borderColor: border }]}
                onPress={() => !result && setSelected(opt.key)}
                disabled={!!result}
              >
                <Text style={s.optKey}>{opt.key.toUpperCase()}</Text>
                <Text style={s.optText}>{opt.text}</Text>
              </TouchableOpacity>
            );
          })}

        {/* Submit */}
        {!result && selected && (
          <TouchableOpacity style={s.submitBtn} onPress={() => submitMutation.mutate()}>
            <Text style={s.submitText}>Submit</Text>
          </TouchableOpacity>
        )}

        {/* Feedback */}
        {result && !result.is_correct && (
          <View style={s.feedback}>
            <Text style={s.feedbackTitle}>Incorrect</Text>
            {result.correct_answer_text && <Text style={s.feedbackCorrect}>Correct: {result.correct_answer_text}</Text>}
            {result.explanation && <Text style={s.feedbackExpl}>{result.explanation}</Text>}
            <TouchableOpacity style={s.nextBtn} onPress={handleNext}>
              <Text style={s.submitText}>Continue</Text>
            </TouchableOpacity>
          </View>
        )}
      </ScrollView>
    </View>
  );
}

const s = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#0B1120' },
  loading: { color: '#94a3b8', textAlign: 'center', marginTop: 200 },
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 20, paddingTop: 60, paddingBottom: 12 },
  closeBtn: { color: '#94a3b8', fontSize: 20 },
  headerText: { color: '#f8fafc', fontSize: 16, fontWeight: '600' },
  score: { color: '#818cf8', fontSize: 16, fontWeight: '700' },
  progressBar: { height: 4, backgroundColor: '#334155', marginHorizontal: 20, borderRadius: 2 },
  progressFill: { height: 4, backgroundColor: '#22c55e', borderRadius: 2 },
  body: { flex: 1, padding: 20 },
  qNum: { color: '#64748b', fontSize: 12, marginBottom: 8 },
  qText: { color: '#f8fafc', fontSize: 17, lineHeight: 26, marginBottom: 24 },
  option: { flexDirection: 'row', alignItems: 'center', borderRadius: 12, borderWidth: 2, padding: 14, marginBottom: 10 },
  optKey: { width: 28, height: 28, borderRadius: 14, backgroundColor: '#334155', textAlign: 'center', lineHeight: 28, color: '#f8fafc', fontWeight: '700', fontSize: 12, marginRight: 12 },
  optText: { flex: 1, color: '#e2e8f0', fontSize: 15 },
  submitBtn: { backgroundColor: '#4f46e5', borderRadius: 12, padding: 16, alignItems: 'center', marginTop: 16 },
  submitText: { color: '#fff', fontSize: 16, fontWeight: '600' },
  feedback: { backgroundColor: '#1e293b', borderRadius: 12, padding: 16, marginTop: 16 },
  feedbackTitle: { color: '#ef4444', fontWeight: '600', marginBottom: 8 },
  feedbackCorrect: { color: '#22c55e', fontSize: 13, marginBottom: 4 },
  feedbackExpl: { color: '#94a3b8', fontSize: 13, lineHeight: 20, marginBottom: 12 },
  nextBtn: { backgroundColor: '#334155', borderRadius: 10, padding: 14, alignItems: 'center' },
});
