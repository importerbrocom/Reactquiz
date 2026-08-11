import React from 'react';
import { View, Text, ScrollView, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '../api/client';

export function ProgressScreen() {
  const { data: dashboard } = useQuery({
    queryKey: ['dashboard'],
    queryFn: async () => (await apiClient.get('/student/dashboard')).data.data,
  });

  const courseId = dashboard?.enrolment?.course_id;

  const { data: progress } = useQuery({
    queryKey: ['progress', courseId],
    queryFn: async () => (await apiClient.get(`/student/progress/${courseId}`)).data.data,
    enabled: !!courseId,
  });

  if (!progress) {
    return <View style={s.container}><Text style={s.loading}>Loading...</Text></View>;
  }

  return (
    <ScrollView style={s.container} contentContainerStyle={s.content}>
      <Text style={s.title}>Progress</Text>
      <View style={s.grid}>
        <StatCard label="Days Done" value={progress.days_completed} />
        <StatCard label="Accuracy" value={`${progress.accuracy_percent}%`} />
        <StatCard label="Questions" value={progress.total_questions_seen} />
        <StatCard label="Mastered" value={progress.total_mastered} />
        <StatCard label="Streak" value={`${progress.current_streak}d`} />
        <StatCard label="Time" value={`${progress.time_spent_hours}h`} />
      </View>
    </ScrollView>
  );
}

function StatCard({ label, value }: { label: string; value: string | number }) {
  return (
    <View style={s.card}>
      <Text style={s.cardValue}>{value}</Text>
      <Text style={s.cardLabel}>{label}</Text>
    </View>
  );
}

const s = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#0B1120' },
  content: { padding: 20, paddingTop: 60 },
  loading: { color: '#94a3b8', textAlign: 'center', marginTop: 200 },
  title: { fontSize: 22, fontWeight: '700', color: '#f8fafc', marginBottom: 20 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  card: { width: '47%', backgroundColor: '#1e293b', borderRadius: 12, padding: 16, alignItems: 'center' },
  cardValue: { fontSize: 22, fontWeight: '700', color: '#f8fafc' },
  cardLabel: { fontSize: 11, color: '#94a3b8', marginTop: 4 },
});
