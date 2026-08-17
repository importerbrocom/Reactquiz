import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView, Alert } from 'react-native';
import { quizApi, Question, AnswerResult } from '../api/quiz';

export default function QuizScreen({ navigation }: any) {
  const [questions, setQuestions] = useState<Question[]>([]);
  const [currentIndex, setCurrentIndex] = useState(0);
  const [selectedOption, setSelectedOption] = useState<string | null>(null);
  const [result, setResult] = useState<AnswerResult | null>(null);
  const [attemptUuid, setAttemptUuid] = useState('');
  const [score, setScore] = useState(0);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [quizDone, setQuizDone] = useState(false);

  useEffect(() => { startQuiz(); }, []);

  const startQuiz = async () => {
    try {
      const progress = await quizApi.getProgress();
      const attempt = await quizApi.startAttempt(progress?.current_day || 1);
      setAttemptUuid(attempt.uuid);
      setQuestions(attempt.questions || []);
      setLoading(false);
    } catch (e: any) {
      Alert.alert('Error', e.response?.data?.message || 'Could not start quiz');
      navigation.goBack();
    }
  };

  const handleSubmit = async () => {
    if (!selectedOption || submitting) return;
    setSubmitting(true);
    try {
      const q = questions[currentIndex];
      const res = await quizApi.submitAnswer(attemptUuid, q.id, selectedOption);
      setResult(res);
      if (res.is_correct) setScore(s => s + 1);
    } catch (e) {
      Alert.alert('Error', 'Failed to submit answer');
    }
    setSubmitting(false);
  };

  const handleNext = () => {
    if (currentIndex + 1 >= questions.length) {
      setQuizDone(true);
    } else {
      setCurrentIndex(i => i + 1);
      setSelectedOption(null);
      setResult(null);
    }
  };

  if (loading) return <View style={styles.container}><Text style={styles.loadingText}>Loading quiz...</Text></View>;

  if (quizDone) {
    const total = questions.length;
    const accuracy = Math.round((score / total) * 100);
    return (
      <View style={styles.container}>
        <ScrollView contentContainerStyle={styles.resultContainer}>
          <Text style={styles.resultEmoji}>🎉</Text>
          <Text style={styles.resultTitle}>Quiz Complete!</Text>
          <Text style={styles.resultText}>{score === total ? '🔥 Perfect! Day unlocked!' : `${score}/${total} correct. Need 10/10 to progress.`}</Text>
          <View style={styles.resultStats}>
            <View style={styles.resultStat}><Text style={styles.resultStatValue}>{score}</Text><Text style={styles.resultStatLabel}>Correct</Text></View>
            <View style={styles.resultStat}><Text style={[styles.resultStatValue, { color: '#ef4444' }]}>{total - score}</Text><Text style={styles.resultStatLabel}>Wrong</Text></View>
            <View style={styles.resultStat}><Text style={styles.resultStatValue}>{accuracy}%</Text><Text style={styles.resultStatLabel}>Accuracy</Text></View>
          </View>
          {score < total && (
            <TouchableOpacity style={[styles.button, { backgroundColor: '#dc2626' }]} onPress={() => { setCurrentIndex(0); setScore(0); setQuizDone(false); setSelectedOption(null); setResult(null); startQuiz(); }}>
              <Text style={styles.buttonText}>Retake Quiz</Text>
            </TouchableOpacity>
          )}
          <TouchableOpacity style={[styles.button, { backgroundColor: '#334155' }]} onPress={() => navigation.goBack()}>
            <Text style={styles.buttonText}>Back to Dashboard</Text>
          </TouchableOpacity>
        </ScrollView>
      </View>
    );
  }

  const question = questions[currentIndex];
  const options = question?.options || [];

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.qNum}>Question {currentIndex + 1} of {questions.length}</Text>
        <Text style={styles.subject}>{question?.subject || ''}</Text>
      </View>

      <ScrollView style={styles.questionArea}>
        <Text style={styles.questionText}>{question?.question_text || ''}</Text>

        {options.map((opt) => {
          let optStyle = styles.option;
          if (result) {
            if (opt.key === result.correct_option) optStyle = styles.optionCorrect;
            else if (opt.key === selectedOption && !result.is_correct) optStyle = styles.optionWrong;
            else optStyle = styles.optionDisabled;
          } else if (opt.key === selectedOption) {
            optStyle = styles.optionSelected;
          }
          return (
            <TouchableOpacity key={opt.key} style={optStyle} onPress={() => !result && setSelectedOption(opt.key)} disabled={!!result}>
              <Text style={styles.optLabel}>{opt.key}.</Text>
              <Text style={styles.optText}>{opt.text}</Text>
            </TouchableOpacity>
          );
        })}

        {result && (
          <View style={[styles.feedback, result.is_correct ? styles.feedbackCorrect : styles.feedbackWrong]}>
            <Text style={styles.feedbackText}>{result.is_correct ? '✓ Correct!' : `✗ Wrong! Answer: ${result.correct_option}`}</Text>
            {result.explanation && <Text style={styles.explanationText}>{result.explanation}</Text>}
          </View>
        )}
      </ScrollView>

      <View style={styles.footer}>
        {!result ? (
          <TouchableOpacity style={[styles.button, !selectedOption && styles.buttonDisabled]} onPress={handleSubmit} disabled={!selectedOption || submitting}>
            <Text style={styles.buttonText}>{submitting ? 'Submitting...' : 'Submit Answer'}</Text>
          </TouchableOpacity>
        ) : (
          <TouchableOpacity style={[styles.button, { backgroundColor: '#16a34a' }]} onPress={handleNext}>
            <Text style={styles.buttonText}>{currentIndex + 1 >= questions.length ? 'See Results' : 'Next Question →'}</Text>
          </TouchableOpacity>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#0B1120' },
  loadingText: { color: '#94a3b8', textAlign: 'center', marginTop: 100, fontSize: 16 },
  header: { flexDirection: 'row', justifyContent: 'space-between', padding: 16, borderBottomWidth: 1, borderBottomColor: '#334155' },
  qNum: { color: '#93c5fd', fontSize: 13, fontWeight: '600', backgroundColor: '#172554', paddingHorizontal: 10, paddingVertical: 4, borderRadius: 8 },
  subject: { color: '#5eead4', fontSize: 13, fontWeight: '600', backgroundColor: '#042f2e', paddingHorizontal: 10, paddingVertical: 4, borderRadius: 8 },
  questionArea: { flex: 1, padding: 16 },
  questionText: { color: '#f8fafc', fontSize: 17, fontWeight: '500', lineHeight: 26, marginBottom: 20 },
  option: { flexDirection: 'row', backgroundColor: '#0f172a', borderWidth: 2, borderColor: '#334155', borderRadius: 14, padding: 16, marginBottom: 10 },
  optionSelected: { flexDirection: 'row', backgroundColor: '#172554', borderWidth: 2, borderColor: '#3b82f6', borderRadius: 14, padding: 16, marginBottom: 10 },
  optionCorrect: { flexDirection: 'row', backgroundColor: '#052e16', borderWidth: 2, borderColor: '#16a34a', borderRadius: 14, padding: 16, marginBottom: 10 },
  optionWrong: { flexDirection: 'row', backgroundColor: '#450a0a', borderWidth: 2, borderColor: '#dc2626', borderRadius: 14, padding: 16, marginBottom: 10 },
  optionDisabled: { flexDirection: 'row', backgroundColor: '#0f172a', borderWidth: 2, borderColor: '#334155', borderRadius: 14, padding: 16, marginBottom: 10, opacity: 0.6 },
  optLabel: { color: '#3b82f6', fontSize: 16, fontWeight: '700', marginRight: 10, width: 24 },
  optText: { color: '#e2e8f0', fontSize: 15, flex: 1, lineHeight: 22 },
  feedback: { borderRadius: 12, padding: 16, marginTop: 10 },
  feedbackCorrect: { backgroundColor: '#052e16', borderWidth: 1, borderColor: '#16a34a' },
  feedbackWrong: { backgroundColor: '#450a0a', borderWidth: 1, borderColor: '#dc2626' },
  feedbackText: { color: '#f8fafc', fontSize: 15, fontWeight: '600', marginBottom: 6 },
  explanationText: { color: '#94a3b8', fontSize: 13, lineHeight: 20 },
  footer: { padding: 16, borderTopWidth: 1, borderTopColor: '#334155' },
  button: { backgroundColor: '#3b82f6', borderRadius: 14, padding: 16, alignItems: 'center' },
  buttonDisabled: { opacity: 0.5 },
  buttonText: { color: '#fff', fontSize: 16, fontWeight: '700' },
  resultContainer: { flexGrow: 1, justifyContent: 'center', padding: 24, alignItems: 'center' },
  resultEmoji: { fontSize: 60, marginBottom: 16 },
  resultTitle: { color: '#f8fafc', fontSize: 24, fontWeight: '700', marginBottom: 8 },
  resultText: { color: '#94a3b8', fontSize: 15, textAlign: 'center', marginBottom: 24 },
  resultStats: { flexDirection: 'row', gap: 16, marginBottom: 24 },
  resultStat: { backgroundColor: '#1e293b', borderRadius: 14, padding: 20, alignItems: 'center', minWidth: 80, borderWidth: 1, borderColor: '#334155' },
  resultStatValue: { color: '#3b82f6', fontSize: 24, fontWeight: '700' },
  resultStatLabel: { color: '#94a3b8', fontSize: 11, marginTop: 4 },
});
