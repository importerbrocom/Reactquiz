import React from 'react';
import { View, Text, FlatList, TouchableOpacity, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { useNavigation } from '@react-navigation/native';
import { apiClient } from '../api/client';
import type { DayTimelineEntry } from '../types/models';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RootStackParamList } from '../navigation/AppNavigator';

type Nav = NativeStackNavigationProp<RootStackParamList>;

export function DaysScreen() {
  const navigation = useNavigation<Nav>();

  const { data: dashboard } = useQuery({
    queryKey: ['dashboard'],
    queryFn: async () => (await apiClient.get('/student/dashboard')).data.data,
  });

  const courseId = dashboard?.enrolment?.course_id;

  const { data: days } = useQuery<DayTimelineEntry[]>({
    queryKey: ['days', courseId],
    queryFn: async () => (await apiClient.get(`/student/courses/${courseId}/days`)).data.data,
    enabled: !!courseId,
  });

  const statusColors: Record<string, string> = {
    completed: '#22c55e',
    in_progress: '#818cf8',
    available: '#475569',
    locked: '#1e293b',
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Your Days</Text>
      <FlatList
        data={days || []}
        numColumns={5}
        keyExtractor={(item) => String(item.day_number)}
        contentContainerStyle={styles.grid}
        renderItem={({ item }) => (
          <TouchableOpacity
            style={[styles.cell, { borderColor: statusColors[item.status] || '#1e293b' }]}
            disabled={item.status === 'locked'}
            onPress={() => courseId && navigation.navigate('Quiz', { courseId, day: item.day_number })}
          >
            <Text style={[styles.cellText, item.status === 'locked' && styles.locked]}>
              {item.day_number}
            </Text>
            {item.status === 'completed' && item.score !== null && (
              <Text style={styles.cellScore}>{item.score}/10</Text>
            )}
          </TouchableOpacity>
        )}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#0B1120', paddingTop: 60, paddingHorizontal: 16 },
  title: { fontSize: 22, fontWeight: '700', color: '#f8fafc', marginBottom: 20 },
  grid: { paddingBottom: 40 },
  cell: { flex: 1, aspectRatio: 1, margin: 4, borderRadius: 10, borderWidth: 2, alignItems: 'center', justifyContent: 'center', backgroundColor: '#0f172a' },
  cellText: { color: '#f8fafc', fontSize: 14, fontWeight: '600' },
  cellScore: { color: '#22c55e', fontSize: 9, marginTop: 2 },
  locked: { color: '#475569' },
});
