import React from 'react';
import { View, Text, ScrollView, TouchableOpacity, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { useNavigation } from '@react-navigation/native';
import { apiClient } from '../api/client';
import type { Dashboard } from '../types/models';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RootStackParamList } from '../navigation/AppNavigator';

type Nav = NativeStackNavigationProp<RootStackParamList>;

export function DashboardScreen() {
  const navigation = useNavigation<Nav>();
  const { data: dashboard, isLoading } = useQuery<Dashboard>({
    queryKey: ['dashboard'],
    queryFn: async () => {
      const { data } = await apiClient.get('/student/dashboard');
      return data.data;
    },
  });

  if (isLoading || !dashboard) {
    return <View style={styles.container}><Text style={styles.loading}>Loading...</Text></View>;
  }

  const { enrolment, today, streak, next_action } = dashboard;

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.greeting}>Hi, {dashboard.user.name.split(' ')[0]}!</Text>
      <Text style={styles.subtitle}>{enrolment.course_name} · Level {enrolment.current_level}</Text>

      {/* Primary action */}
      <TouchableOpacity
        style={styles.actionCard}
        onPress={() => navigation.navigate('Quiz', { courseId: enrolment.course_id, day: today.day_number })}
        disabled={next_action === 'locked'}
      >
        <Text style={styles.actionTitle}>
          {next_action === 'start_day' ? `Start Day ${today.day_number}` :
           next_action === 'resume_day' ? `Resume Day ${today.day_number}` :
           next_action === 'take_level_test' ? 'Take Month-End Test' :
           next_action === 'programme_complete' ? 'Programme Complete!' : 'Day Locked'}
        </Text>
        <Text style={styles.actionSub}>
          {next_action === 'resume_day' ? `${today.mastered_count}/${today.required_count} mastered` : '10 questions to master'}
        </Text>
      </TouchableOpacity>

      {/* Streak */}
      <View style={styles.card}>
        <View style={styles.row}>
          <View><Text style={styles.cardLabel}>Streak</Text><Text style={styles.cardValue}>{streak.current} days</Text></View>
          <View><Text style={styles.cardLabel}>Longest</Text><Text style={styles.cardValueSm}>{streak.longest} days</Text></View>
        </View>
      </View>

      {/* Progress */}
      <View style={styles.card}>
        <Text style={styles.cardLabel}>Level Progress</Text>
        <View style={styles.progressBar}>
          <View style={[styles.progressFill, { width: `${(enrolment.current_day / enrolment.days_per_level) * 100}%` }]} />
        </View>
        <Text style={styles.progressText}>Day {enrolment.current_day} / {enrolment.days_per_level}</Text>
      </View>

      {/* Recent scores */}
      {dashboard.recent_scores.length > 0 && (
        <View style={styles.card}>
          <Text style={styles.cardLabel}>Recent Days</Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.scoresRow}>
            {dashboard.recent_scores.map((s) => (
              <View key={s.day_number} style={styles.scoreChip}>
                <Text style={styles.scoreDay}>Day {s.day_number}</Text>
                <Text style={styles.scoreValue}>{s.score}/10</Text>
              </View>
            ))}
          </ScrollView>
        </View>
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#0B1120' },
  content: { padding: 20, paddingTop: 60 },
  loading: { color: '#94a3b8', textAlign: 'center', marginTop: 200 },
  greeting: { fontSize: 26, fontWeight: '700', color: '#f8fafc' },
  subtitle: { fontSize: 14, color: '#94a3b8', marginTop: 4, marginBottom: 24 },
  actionCard: { backgroundColor: '#1e293b', borderRadius: 16, padding: 20, marginBottom: 16, borderWidth: 1, borderColor: '#4f46e5' },
  actionTitle: { fontSize: 18, fontWeight: '600', color: '#f8fafc' },
  actionSub: { fontSize: 13, color: '#94a3b8', marginTop: 4 },
  card: { backgroundColor: '#1e293b', borderRadius: 12, padding: 16, marginBottom: 12 },
  row: { flexDirection: 'row', justifyContent: 'space-between' },
  cardLabel: { fontSize: 12, color: '#94a3b8', marginBottom: 4 },
  cardValue: { fontSize: 22, fontWeight: '700', color: '#f8fafc' },
  cardValueSm: { fontSize: 16, fontWeight: '600', color: '#94a3b8' },
  progressBar: { height: 6, backgroundColor: '#334155', borderRadius: 3, marginTop: 8 },
  progressFill: { height: 6, backgroundColor: '#4f46e5', borderRadius: 3 },
  progressText: { fontSize: 12, color: '#94a3b8', marginTop: 6 },
  scoresRow: { marginTop: 8 },
  scoreChip: { backgroundColor: '#0f172a', borderRadius: 8, padding: 10, marginRight: 8, alignItems: 'center' },
  scoreDay: { fontSize: 10, color: '#94a3b8' },
  scoreValue: { fontSize: 14, fontWeight: '700', color: '#22c55e', marginTop: 2 },
});
