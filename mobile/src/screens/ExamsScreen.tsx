import React from 'react';
import { View, Text, ScrollView, TouchableOpacity, StyleSheet, Image } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '../api/client';
import { colors, radius, spacing } from '../theme';
import type { ExamCategory } from '../types/models';

/**
 * "Choose Your Exam" — exam-category picker matching the ERO mockups:
 * colourful full-width cards, one per category. Mirrors the web ExamsScreen.
 * Data comes from the existing /exam-categories endpoint.
 */
export function ExamsScreen() {
  const { data: categories, isLoading } = useQuery<ExamCategory[]>({
    queryKey: ['exam-categories'],
    queryFn: async () => {
      const { data } = await apiClient.get('/exam-categories');
      return data.data;
    },
  });

  if (isLoading || !categories) {
    return (
      <View style={styles.container}>
        <Text style={styles.loading}>Loading...</Text>
      </View>
    );
  }

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.title}>Choose Your Exam</Text>
      <Text style={styles.subtitle}>Select an exam to start your preparation</Text>

      {categories.length === 0 ? (
        <View style={styles.empty}>
          <Text style={styles.emptyText}>No exams available yet. Check back soon.</Text>
        </View>
      ) : (
        categories.map((cat, i) => <ExamCard key={cat.id} category={cat} index={i} />)
      )}
    </ScrollView>
  );
}

/** Accent colours cycled across cards — mirrors the colourful mockup tiles. */
const CARD_COLORS = [colors.primary, colors.pink, colors.purple, colors.teal, colors.amber, colors.cyan];

function ExamCard({ category, index }: { category: ExamCategory; index: number }) {
  const bg = CARD_COLORS[index % CARD_COLORS.length];

  return (
    <TouchableOpacity style={[styles.card, { backgroundColor: bg }]} activeOpacity={0.9}>
      <View style={styles.cardTop}>
        <View style={styles.iconWrap}>
          {category.icon_url ? (
            <Image source={{ uri: category.icon_url }} style={styles.iconImg} />
          ) : (
            <Text style={styles.iconEmoji}>🎓</Text>
          )}
        </View>
        <View style={styles.arrow}>
          <Text style={styles.arrowText}>→</Text>
        </View>
      </View>

      <Text style={styles.cardTitle}>{category.name}</Text>
      {category.description ? (
        <Text style={styles.cardDesc} numberOfLines={2}>
          {category.description}
        </Text>
      ) : null}
      <View style={styles.countPill}>
        <Text style={styles.countText}>
          📄 {category.courses_count} {category.courses_count === 1 ? 'course' : 'courses'}
        </Text>
      </View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.bg },
  content: { padding: spacing.xl, paddingTop: 56, gap: spacing.md },
  loading: { color: colors.textSecondary, textAlign: 'center', marginTop: 200 },
  title: { fontSize: 24, fontWeight: '700', color: colors.textPrimary },
  subtitle: { fontSize: 13, color: colors.textSecondary, marginTop: 2, marginBottom: spacing.sm },

  empty: {
    backgroundColor: colors.bgCard,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.lg,
    padding: spacing.xxl,
    alignItems: 'center',
  },
  emptyText: { color: colors.textSecondary },

  card: { borderRadius: radius.xl, padding: spacing.xl, minHeight: 132, justifyContent: 'space-between' },
  cardTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' },
  iconWrap: {
    width: 44,
    height: 44,
    borderRadius: radius.md,
    backgroundColor: 'rgba(255,255,255,0.18)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  iconImg: { width: 28, height: 28, resizeMode: 'contain' },
  iconEmoji: { fontSize: 22 },
  arrow: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: 'rgba(255,255,255,0.22)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  arrowText: { color: '#fff', fontSize: 16, fontWeight: '700' },

  cardTitle: { fontSize: 18, fontWeight: '700', color: '#fff', marginTop: spacing.md },
  cardDesc: { fontSize: 12, color: 'rgba(255,255,255,0.85)', marginTop: 2 },
  countPill: {
    alignSelf: 'flex-start',
    marginTop: spacing.sm,
    backgroundColor: 'rgba(0,0,0,0.2)',
    borderRadius: radius.sm,
    paddingHorizontal: spacing.sm,
    paddingVertical: 4,
  },
  countText: { fontSize: 12, color: '#fff', fontWeight: '500' },
});
