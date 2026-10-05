import React from 'react';
import { View, Text, ScrollView, TouchableOpacity, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { useNavigation } from '@react-navigation/native';
import { apiClient } from '../api/client';
import { colors, radius, spacing } from '../theme';
import { Logo } from '../components/Logo';
import type { Dashboard } from '../types/models';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RootStackParamList } from '../navigation/AppNavigator';

type Nav = NativeStackNavigationProp<RootStackParamList>;

/**
 * Student dashboard — redesigned to match the ERO mockups: gradient-style hero
 * banner, quick-action tiles, stat row, and the primary quiz CTA. Data wiring
 * (useQuery → /student/dashboard) is unchanged.
 */
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
    return (
      <View style={styles.container}>
        <Text style={styles.loading}>Loading...</Text>
      </View>
    );
  }

  const { user, enrolment, today, streak, next_action, recent_scores } = dashboard;
  const dayPct = Math.round((enrolment.current_day / enrolment.days_per_level) * 100);
  const avgScore =
    recent_scores.length > 0
      ? Math.round(
          (recent_scores.reduce((a, s) => a + s.score, 0) / (recent_scores.length * 10)) * 100,
        )
      : 0;

  const primaryLabel =
    next_action === 'start_day'
      ? `Start Day ${today.day_number}`
      : next_action === 'resume_day'
        ? `Resume Day ${today.day_number}`
        : next_action === 'take_level_test'
          ? 'Take Month-End Test'
          : next_action === 'programme_complete'
            ? 'Programme Complete!'
            : 'Day Locked';
  const primarySub =
    next_action === 'resume_day'
      ? `${today.mastered_count}/${today.required_count} mastered — keep going!`
      : `${today.required_count} questions to master today`;
  const locked = next_action === 'locked';

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      {/* Header */}
      <View style={styles.header}>
        <Logo size={34} showWordmark tagline="Daily Exam Practice" />
      </View>

      {/* Hero banner */}
      <View style={styles.hero}>
        <Text style={styles.heroHi}>Welcome back,</Text>
        <Text style={styles.heroName}>{user.name.split(' ')[0]}!</Text>
        <Text style={styles.heroSub}>Let's continue your {enrolment.course_name} prep</Text>
        <View style={styles.goalRow}>
          <Text style={styles.goalLabel}>Your monthly goal</Text>
          <Text style={styles.goalValue}>
            Day {enrolment.current_day}/{enrolment.days_per_level}
          </Text>
        </View>
        <View style={styles.heroBarTrack}>
          <View style={[styles.heroBarFill, { width: `${dayPct}%` }]} />
        </View>
      </View>

      {/* Quick actions */}
      <View style={styles.grid}>
        <QuickAction
          icon="📝"
          title="Today's Quiz"
          subtitle={`${today.required_count} Questions`}
          bg={colors.primary}
          onPress={() =>
            navigation.navigate('Quiz', { courseId: enrolment.course_id, day: today.day_number })
          }
          disabled={locked}
        />
        <QuickAction icon="🏆" title="Monthly Test" subtitle="Full Length" bg={colors.teal} />
        <QuickAction icon="📄" title="All Days" subtitle="Timeline" bg={colors.purple} />
        <QuickAction icon="📊" title="Progress" subtitle="Your stats" bg={colors.amber} />
      </View>

      {/* Stat row */}
      <View style={styles.card}>
        <Text style={styles.cardTitle}>Overall Progress</Text>
        <View style={styles.statRow}>
          <Stat value={`${avgScore}%`} label="Accuracy" color={colors.primaryBright} />
          <Stat value={streak.current} label="Streak" color={colors.amber} />
          <Stat value={enrolment.current_day} label="Days Done" color={colors.teal} />
          <Stat value={streak.longest} label="Best" color={colors.purple} />
        </View>
      </View>

      {/* Primary action */}
      <TouchableOpacity
        style={[styles.primary, locked && styles.primaryLocked]}
        onPress={() =>
          navigation.navigate('Quiz', { courseId: enrolment.course_id, day: today.day_number })
        }
        disabled={locked}
        activeOpacity={0.9}
      >
        <Text style={[styles.primaryEyebrow, locked && styles.mutedText]}>
          {locked ? 'Locked' : "Today's focus"}
        </Text>
        <Text style={[styles.primaryTitle, locked && styles.lockedText]}>{primaryLabel}</Text>
        <Text style={[styles.primarySub, locked && styles.mutedText]}>{primarySub}</Text>
      </TouchableOpacity>

      {/* Recent scores */}
      {recent_scores.length > 0 && (
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Recent Days</Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.scoresRow}>
            {recent_scores.map((s) => (
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

function QuickAction({
  icon,
  title,
  subtitle,
  bg,
  onPress,
  disabled,
}: {
  icon: string;
  title: string;
  subtitle: string;
  bg: string;
  onPress?: () => void;
  disabled?: boolean;
}) {
  return (
    <TouchableOpacity
      style={[styles.qa, { backgroundColor: bg }, disabled && { opacity: 0.5 }]}
      onPress={onPress}
      disabled={disabled || !onPress}
      activeOpacity={0.9}
    >
      <Text style={styles.qaIcon}>{icon}</Text>
      <View>
        <Text style={styles.qaTitle}>{title}</Text>
        <Text style={styles.qaSub}>{subtitle}</Text>
      </View>
    </TouchableOpacity>
  );
}

function Stat({ value, label, color }: { value: string | number; label: string; color: string }) {
  return (
    <View style={styles.stat}>
      <Text style={[styles.statValue, { color }]}>{value}</Text>
      <Text style={styles.statLabel}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.bg },
  content: { padding: spacing.xl, paddingTop: 56, gap: spacing.lg },
  loading: { color: colors.textSecondary, textAlign: 'center', marginTop: 200 },

  header: { marginBottom: spacing.xs },

  hero: {
    backgroundColor: colors.primary,
    borderRadius: radius.xl,
    padding: spacing.xl,
  },
  heroHi: { fontSize: 13, color: '#dbeafe', fontWeight: '500' },
  heroName: { fontSize: 24, fontWeight: '700', color: '#fff', marginTop: 2 },
  heroSub: { fontSize: 13, color: '#dbeafe', marginTop: 4 },
  goalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: spacing.lg,
    marginBottom: 6,
  },
  goalLabel: { fontSize: 12, color: '#dbeafe' },
  goalValue: { fontSize: 12, color: '#fff', fontWeight: '600' },
  heroBarTrack: { height: 8, backgroundColor: 'rgba(255,255,255,0.25)', borderRadius: 4 },
  heroBarFill: { height: 8, backgroundColor: '#fff', borderRadius: 4 },

  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.md },
  qa: {
    width: '47.8%',
    flexGrow: 1,
    borderRadius: radius.lg,
    padding: spacing.lg,
    minHeight: 104,
    justifyContent: 'space-between',
  },
  qaIcon: { fontSize: 24 },
  qaTitle: { fontSize: 15, fontWeight: '600', color: '#fff', marginTop: spacing.sm },
  qaSub: { fontSize: 12, color: 'rgba(255,255,255,0.85)' },

  card: {
    backgroundColor: colors.bgCard,
    borderRadius: radius.lg,
    padding: spacing.lg,
    borderWidth: 1,
    borderColor: colors.border,
  },
  cardTitle: { fontSize: 14, fontWeight: '600', color: colors.textPrimary, marginBottom: spacing.md },

  statRow: { flexDirection: 'row', gap: spacing.sm },
  stat: {
    flex: 1,
    backgroundColor: colors.bgElevated,
    borderRadius: radius.md,
    paddingVertical: spacing.md,
    alignItems: 'center',
  },
  statValue: { fontSize: 18, fontWeight: '700' },
  statLabel: { fontSize: 11, color: colors.textSecondary, marginTop: 2 },

  primary: {
    backgroundColor: colors.primary,
    borderRadius: radius.xl,
    padding: spacing.xl,
  },
  primaryLocked: {
    backgroundColor: colors.bgCard,
    borderWidth: 1,
    borderColor: colors.border,
  },
  primaryEyebrow: { fontSize: 12, color: '#dbeafe', fontWeight: '500' },
  primaryTitle: { fontSize: 18, fontWeight: '700', color: '#fff', marginTop: 4 },
  primarySub: { fontSize: 13, color: '#dbeafe', marginTop: 2 },
  mutedText: { color: colors.textMuted },
  lockedText: { color: colors.textSecondary },

  scoresRow: { marginTop: 2 },
  scoreChip: {
    backgroundColor: colors.bgElevated,
    borderRadius: radius.sm,
    padding: spacing.md,
    marginRight: spacing.sm,
    alignItems: 'center',
  },
  scoreDay: { fontSize: 10, color: colors.textSecondary },
  scoreValue: { fontSize: 14, fontWeight: '700', color: colors.success, marginTop: 2 },
});
