import React, { useState, useRef, useCallback } from 'react';
import { View, Text, TouchableOpacity, ScrollView, StyleSheet, Alert, Image } from 'react-native';
import { useRoute, useNavigation } from '@react-navigation/native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../api/client';
import { colors, radius, spacing } from '../theme';
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
        <TouchableOpacity onPress={() => navigation.goBack()} style={s.closeWrap}>
          <Text style={s.closeBtn}>✕</Text>
        </TouchableOpacity>
        <Text style={s.headerText}>Day {day}</Text>
        <View style={s.scorePill}>
          <Text style={s.score}>✓ {mastered}/10</Text>
        </View>
      </View>

      {/* Progress bar */}
      <View style={s.progressTrack}>
        <View style={[s.progressFill, { width: `${(mastered / 10) * 100}%` }]} />
      </View>

      <ScrollView style={s.body} contentContainerStyle={s.bodyContent}>
        {/* Question card */}
        <View style={s.qCard}>
          <Text style={s.qNum}>
            Q{currentIdx + 1}.
          </Text>
          <Text style={s.qText}>{currentQ.text}</Text>
          {currentQ.image_url ? (
            <View style={s.qImageWrap}>
              <Image source={{ uri: currentQ.image_url }} style={s.qImage} resizeMode="contain" />
            </View>
          ) : null}
          <Text style={s.qCount}>
            Question {currentIdx + 1} of {questions.length}
          </Text>
        </View>

        {/* Options */}
        <View style={s.options}>
          {currentQ.options
            .slice()
            .sort((a, b) => a.display_position - b.display_position)
            .map((opt) => {
              let bg: string = colors.bgCard;
              let border: string = colors.border;
              let badgeBg: string = colors.bgElevated;
              let badgeColor: string = colors.textSecondary;
              if (result) {
                if (opt.key === result.correct_option) {
                  bg = 'rgba(34,197,94,0.15)';
                  border = colors.success;
                  badgeBg = colors.success;
                  badgeColor = '#fff';
                } else if (opt.key === selected && !result.is_correct) {
                  bg = 'rgba(239,68,68,0.15)';
                  border = colors.danger;
                  badgeBg = colors.danger;
                  badgeColor = '#fff';
                }
              } else if (opt.key === selected) {
                bg = colors.primaryTint;
                border = colors.primary;
                badgeBg = colors.primary;
                badgeColor = '#fff';
              }

              const showCheck = !!result && opt.key === result.correct_option;
              const showX = !!result && opt.key === selected && !result.is_correct;

              return (
                <TouchableOpacity
                  key={opt.key}
                  style={[s.option, { backgroundColor: bg, borderColor: border }]}
                  onPress={() => !result && setSelected(opt.key)}
                  disabled={!!result}
                  activeOpacity={0.85}
                >
                  <View style={[s.optKey, { backgroundColor: badgeBg }]}>
                    <Text style={[s.optKeyText, { color: badgeColor }]}>{opt.key.toUpperCase()}</Text>
                  </View>
                  <Text style={s.optText}>{opt.text}</Text>
                  {showCheck ? <Text style={s.markCorrect}>✓</Text> : null}
                  {showX ? <Text style={s.markWrong}>✕</Text> : null}
                </TouchableOpacity>
              );
            })}
        </View>

        {/* Submit */}
        {!result && selected && (
          <TouchableOpacity style={s.submitBtn} onPress={() => submitMutation.mutate()} activeOpacity={0.9}>
            <Text style={s.submitText}>Submit Answer</Text>
          </TouchableOpacity>
        )}

        {/* Feedback */}
        {result && (
          <View style={s.feedbackWrap}>
            <View
              style={[
                s.banner,
                result.is_correct ? s.bannerCorrect : s.bannerWrong,
              ]}
            >
              <View style={[s.bannerIcon, { backgroundColor: result.is_correct ? colors.success : colors.danger }]}>
                <Text style={s.bannerIconText}>{result.is_correct ? '✓' : '✕'}</Text>
              </View>
              <View style={{ flex: 1 }}>
                <Text style={[s.bannerTitle, { color: result.is_correct ? colors.success : colors.danger }]}>
                  {result.is_correct ? 'Correct!' : 'Incorrect'}
                </Text>
                {result.correct_answer_text ? (
                  <Text style={s.bannerSub}>
                    <Text style={s.bannerSubLabel}>Correct answer: </Text>
                    {result.correct_answer_text}
                  </Text>
                ) : null}
              </View>
            </View>

            {result.explanation ? (
              <View style={s.explCard}>
                <Text style={s.explTitle}>📖 Explanation</Text>
                <Text style={s.explText}>{result.explanation}</Text>
              </View>
            ) : null}

            {!result.is_correct && (
              <TouchableOpacity style={s.nextBtn} onPress={handleNext} activeOpacity={0.9}>
                <Text style={s.submitText}>Continue</Text>
              </TouchableOpacity>
            )}
          </View>
        )}
      </ScrollView>
    </View>
  );
}

const s = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.bg },
  loading: { color: colors.textSecondary, textAlign: 'center', marginTop: 200 },

  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: spacing.xl,
    paddingTop: 56,
    paddingBottom: spacing.md,
  },
  closeWrap: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.bgCard,
  },
  closeBtn: { color: colors.textSecondary, fontSize: 18 },
  headerText: { color: colors.textPrimary, fontSize: 16, fontWeight: '600' },
  scorePill: {
    backgroundColor: colors.primaryTint,
    borderRadius: radius.pill,
    paddingHorizontal: spacing.md,
    paddingVertical: 6,
  },
  score: { color: colors.primaryBright, fontSize: 14, fontWeight: '700' },

  progressTrack: {
    height: 8,
    backgroundColor: colors.bgElevated,
    marginHorizontal: spacing.xl,
    borderRadius: 4,
  },
  progressFill: { height: 8, backgroundColor: colors.success, borderRadius: 4 },

  body: { flex: 1 },
  bodyContent: { padding: spacing.xl, gap: spacing.lg },

  qCard: {
    backgroundColor: colors.bgCard,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    padding: spacing.lg,
  },
  qNum: { color: colors.primaryBright, fontSize: 18, fontWeight: '700', marginBottom: spacing.sm },
  qText: { color: colors.textPrimary, fontSize: 17, lineHeight: 26 },
  qImageWrap: {
    marginTop: spacing.md,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.bg,
    overflow: 'hidden',
  },
  qImage: { width: '100%', height: 200 },
  qCount: { color: colors.textMuted, fontSize: 11, textAlign: 'right', marginTop: spacing.md },

  options: { gap: spacing.md },
  option: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: radius.lg,
    borderWidth: 1,
    padding: spacing.lg,
    minHeight: 56,
  },
  optKey: {
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: spacing.md,
  },
  optKeyText: { fontWeight: '700', fontSize: 14 },
  optText: { flex: 1, color: colors.textPrimary, fontSize: 15, lineHeight: 21 },
  markCorrect: { color: colors.success, fontSize: 18, fontWeight: '700', marginLeft: spacing.sm },
  markWrong: { color: colors.danger, fontSize: 18, fontWeight: '700', marginLeft: spacing.sm },

  submitBtn: {
    backgroundColor: colors.primary,
    borderRadius: radius.lg,
    padding: spacing.lg,
    alignItems: 'center',
  },
  submitText: { color: '#fff', fontSize: 16, fontWeight: '600' },

  feedbackWrap: { gap: spacing.md },
  banner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderRadius: radius.lg,
    borderWidth: 1,
    padding: spacing.lg,
  },
  bannerCorrect: { borderColor: 'rgba(34,197,94,0.4)', backgroundColor: 'rgba(34,197,94,0.1)' },
  bannerWrong: { borderColor: 'rgba(239,68,68,0.4)', backgroundColor: 'rgba(239,68,68,0.1)' },
  bannerIcon: { width: 32, height: 32, borderRadius: 16, alignItems: 'center', justifyContent: 'center' },
  bannerIconText: { color: '#fff', fontSize: 16, fontWeight: '700' },
  bannerTitle: { fontSize: 15, fontWeight: '700' },
  bannerSub: { color: colors.textPrimary, fontSize: 13, marginTop: 2 },
  bannerSubLabel: { color: colors.textSecondary },

  explCard: {
    backgroundColor: colors.bgCard,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    padding: spacing.lg,
  },
  explTitle: { color: colors.textPrimary, fontSize: 14, fontWeight: '600', marginBottom: 6 },
  explText: { color: colors.textSecondary, fontSize: 13, lineHeight: 20 },

  nextBtn: {
    backgroundColor: colors.bgElevated,
    borderRadius: radius.lg,
    padding: spacing.lg,
    alignItems: 'center',
  },
});
