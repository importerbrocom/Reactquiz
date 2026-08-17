import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView, RefreshControl } from 'react-native';
import { quizApi, ProgressData } from '../api/quiz';
import { authApi, User } from '../api/auth';

export default function DashboardScreen({ navigation }: any) {
  const [user, setUser] = useState<User | null>(null);
  const [progress, setProgress] = useState<ProgressData | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const loadData = async () => {
    try {
      const [u, p] = await Promise.all([authApi.me(), quizApi.getProgress()]);
      setUser(u);
      setProgress(p);
    } catch (e) {}
  };

  useEffect(() => { loadData(); }, []);

  const onRefresh = async () => { setRefreshing(true); await loadData(); setRefreshing(false); };

  return (
    <ScrollView style={styles.container} refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor="#3b82f6" />}>
      <View style={styles.greeting}>
        <Text style={styles.greetingText}>Hello, {user?.name || 'Student'}!</Text>
        <Text style={styles.streakText}>
          {progress?.current_streak ? `🔥 ${progress.current_streak}-day streak!` : 'Start your first quiz!'}
        </Text>
      </View>

      <View style={styles.statsGrid}>
        <View style={styles.statCard}>
          <Text style={styles.statValue}>{progress?.days_completed || 0}</Text>
          <Text style={styles.statLabel}>DAYS DONE</Text>
        </View>
        <View style={styles.statCard}>
          <Text style={styles.statValue}>{progress?.questions_mastered || 0}</Text>
          <Text style={styles.statLabel}>MASTERED</Text>
        </View>
        <View style={styles.statCard}>
          <Text style={styles.statValue}>{progress?.current_streak || 0}</Text>
          <Text style={styles.statLabel}>STREAK</Text>
        </View>
        <View style={styles.statCard}>
          <Text style={styles.statValue}>Day {progress?.current_day || 1}</Text>
          <Text style={styles.statLabel}>CURRENT</Text>
        </View>
      </View>

      <TouchableOpacity style={styles.quizButton} onPress={() => navigation.navigate('Quiz')}>
        <Text style={styles.quizButtonText}>Start Today's Quiz</Text>
        <Text style={styles.quizButtonSub}>10 questions · Day {progress?.current_day || 1}</Text>
      </TouchableOpacity>

      <View style={styles.courseCard}>
        <View style={styles.courseHeader}>
          <Text style={styles.courseTitle}>Course Progress</Text>
          <View style={styles.badge}><Text style={styles.badgeText}>In Progress</Text></View>
        </View>
        <Text style={styles.courseDay}>FMGE · Level {progress?.current_level || 1} · Day {progress?.current_day || 1}</Text>
        <Text style={styles.courseSub}>{progress?.questions_mastered || 0} of {progress?.total_questions || 300} questions mastered</Text>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#0B1120', padding: 16 },
  greeting: { backgroundColor: '#1e293b', borderRadius: 16, padding: 20, marginBottom: 16, borderLeftWidth: 3, borderLeftColor: '#3b82f6' },
  greetingText: { color: '#f8fafc', fontSize: 20, fontWeight: '700', marginBottom: 4 },
  streakText: { color: '#3b82f6', fontSize: 14 },
  statsGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginBottom: 16 },
  statCard: { flex: 1, minWidth: '45%', backgroundColor: '#1e293b', borderRadius: 14, padding: 16, alignItems: 'center', borderWidth: 1, borderColor: '#334155' },
  statValue: { color: '#3b82f6', fontSize: 22, fontWeight: '700' },
  statLabel: { color: '#94a3b8', fontSize: 10, fontWeight: '600', marginTop: 4, letterSpacing: 0.5 },
  quizButton: { backgroundColor: '#3b82f6', borderRadius: 16, padding: 20, alignItems: 'center', marginBottom: 16, shadowColor: '#3b82f6', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.3, shadowRadius: 8, elevation: 5 },
  quizButtonText: { color: '#fff', fontSize: 18, fontWeight: '700' },
  quizButtonSub: { color: '#bfdbfe', fontSize: 13, marginTop: 4 },
  courseCard: { backgroundColor: '#1e293b', borderRadius: 16, padding: 20, borderWidth: 1, borderColor: '#334155' },
  courseHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 },
  courseTitle: { color: '#e2e8f0', fontSize: 16, fontWeight: '600' },
  badge: { backgroundColor: '#172554', paddingHorizontal: 10, paddingVertical: 4, borderRadius: 8 },
  badgeText: { color: '#93c5fd', fontSize: 11, fontWeight: '600' },
  courseDay: { color: '#e2e8f0', fontSize: 14, marginBottom: 4 },
  courseSub: { color: '#94a3b8', fontSize: 13 },
});
