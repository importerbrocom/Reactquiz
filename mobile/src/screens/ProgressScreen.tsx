import React from 'react';
import { View, Text, ScrollView, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '../api/client';
import { colors, radius, spacing } from '../theme';

interface TopicProgress {
  topic: string;
  correct: number;
  total: number;
  accuracy_percent: number;
  strength: 'strong' | 'moderate' | 'weak';
}

/**
 * Progress screen — redesigned to match the ERO mockups: a large accuracy
 * gauge, a stat grid, and subject-wise accuracy bars. All values come from the
 * existing /student/progress endpoints.
 *
 * The accuracy gauge is drawn without react-native-svg on purpose: adding a
 * new native dependency risks destabilising the SDK-52 EAS build (see the
 * mobile steering guide). A ring built from plain Views needs no native module.
 */
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

  const { data: topics } = useQuery<TopicProgress[]>({
    queryKey: ['progress-topics', courseId],
    queryFn: async () => (await apiClient.get(`/student/progress/${courseId}/topics`)).data.data,
    enabled: !!courseId,
  });

  if (!progress) {
    return (
      <View style={s.container}>
        <Text style={s.loading}>Loading...</Text>
      </View>
    );
  }

  return (
    <ScrollView style={s.container} contentContainerStyle={s.content}>
      <Text style={s.title}>Progress</Text>

      {/* Accuracy gauge */}
      <View style={s.ringCard}>
        <AccuracyRing percent={progress.accuracy_percent} />
        <View style={{ flex: 1 }}>
          <Text style={s.ringLabel}>Overall Accuracy</Text>
          <Text style={s.ringValue}>{progress.accuracy_percent}%</Text>
          <Text style={s.ringSub}>
            {progress.total_mastered} of {progress.total_questions_seen} mastered
          </Text>
        </View>
      </View>

      {/* Stat grid */}
      <View style={s.grid}>
        <StatTile icon="📅" value={progress.days_completed} label="Days Completed" color={colors.primaryBright} />
        <StatTile icon="🔥" value={`${progress.current_streak}d`} label="Current Streak" color={colors.amber} />
        <StatTile icon="📄" value={progress.total_questions_seen} label="Questions Seen" color={colors.teal} />
        <StatTile icon="⏱️" value={`${progress.time_spent_hours}h`} label="Time Spent" color={colors.purple} />
      </View>

      {/* Subject-wise accuracy */}
      {topics && topics.length > 0 && (
        <View style={s.card}>
          <Text style={s.cardTitle}>Subject-wise Accuracy</Text>
          <View style={{ gap: spacing.lg }}>
            {topics.map((t) => (
              <SubjectBar key={t.topic} topic={t} />
            ))}
          </View>
        </View>
      )}
    </ScrollView>
  );
}

/**
 * Ring gauge built from two rotated half-circles over a track — a common
 * no-dependency technique. The percentage reads in the centre regardless.
 */
function AccuracyRing({ percent }: { percent: number }) {
  const p = Math.min(100, Math.max(0, percent));
  const SIZE = 88;
  const rightDeg = p <= 50 ? (p / 50) * 180 : 180;
  const leftDeg = p > 50 ? ((p - 50) / 50) * 180 : 0;

  return (
    <View style={[ring.wrap, { width: SIZE, height: SIZE }]}>
      {/* track */}
      <View style={[ring.track, { width: SIZE, height: SIZE, borderRadius: SIZE / 2 }]} />
      {/* right half fill */}
      <View style={[ring.half, { width: SIZE, height: SIZE }]}>
        <View style={[ring.clipRight, { width: SIZE / 2, height: SIZE }]}>
          <View
            style={[
              ring.fill,
              { width: SIZE, height: SIZE, borderRadius: SIZE / 2, transform: [{ rotate: `${rightDeg}deg` }] },
            ]}
          />
        </View>
      </View>
      {/* left half fill (only > 50%) */}
      {p > 50 && (
        <View style={[ring.half, { width: SIZE, height: SIZE }]}>
          <View style={[ring.clipLeft, { width: SIZE / 2, height: SIZE }]}>
            <View
              style={[
                ring.fill,
                { width: SIZE, height: SIZE, borderRadius: SIZE / 2, transform: [{ rotate: `${leftDeg}deg` }], left: -SIZE / 2 },
              ]}
            />
          </View>
        </View>
      )}
      {/* inner hole + label */}
      <View style={[ring.hole, { width: SIZE - 18, height: SIZE - 18, borderRadius: (SIZE - 18) / 2 }]}>
        <Text style={ring.label}>{p}%</Text>
      </View>
    </View>
  );
}

function StatTile({
  icon,
  value,
  label,
  color,
}: {
  icon: string;
  value: string | number;
  label: string;
  color: string;
}) {
  return (
    <View style={s.tile}>
      <Text style={s.tileIcon}>{icon}</Text>
      <Text style={[s.tileValue, { color }]}>{value}</Text>
      <Text style={s.tileLabel}>{label}</Text>
    </View>
  );
}

function SubjectBar({ topic }: { topic: TopicProgress }) {
  const color =
    topic.strength === 'strong' ? colors.success : topic.strength === 'moderate' ? colors.warning : colors.danger;
  return (
    <View>
      <View style={s.subjectRow}>
        <Text style={s.subjectName}>{topic.topic}</Text>
        <Text style={[s.subjectPct, { color }]}>
          {topic.accuracy_percent}%{' '}
          <Text style={s.subjectCount}>
            ({topic.correct}/{topic.total})
          </Text>
        </Text>
      </View>
      <View style={s.barTrack}>
        <View style={[s.barFill, { width: `${topic.accuracy_percent}%`, backgroundColor: color }]} />
      </View>
    </View>
  );
}

const ring = StyleSheet.create({
  wrap: { alignItems: 'center', justifyContent: 'center' },
  track: { position: 'absolute', backgroundColor: colors.bgElevated },
  half: { position: 'absolute', overflow: 'hidden' },
  clipRight: { position: 'absolute', right: 0, overflow: 'hidden' },
  clipLeft: { position: 'absolute', left: 0, overflow: 'hidden' },
  fill: { position: 'absolute', backgroundColor: colors.primary },
  hole: {
    position: 'absolute',
    backgroundColor: colors.bgCard,
    alignItems: 'center',
    justifyContent: 'center',
  },
  label: { color: '#fff', fontSize: 18, fontWeight: '700' },
});

const s = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.bg },
  content: { padding: spacing.xl, paddingTop: 56, gap: spacing.lg },
  loading: { color: colors.textSecondary, textAlign: 'center', marginTop: 200 },
  title: { fontSize: 24, fontWeight: '700', color: colors.textPrimary },

  ringCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xl,
    backgroundColor: colors.bgCard,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    padding: spacing.lg,
  },
  ringLabel: { color: colors.textSecondary, fontSize: 13 },
  ringValue: { color: colors.textPrimary, fontSize: 28, fontWeight: '700' },
  ringSub: { color: colors.textSecondary, fontSize: 11, marginTop: 2 },

  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.md },
  tile: {
    width: '47.8%',
    flexGrow: 1,
    backgroundColor: colors.bgCard,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    padding: spacing.lg,
  },
  tileIcon: { fontSize: 18 },
  tileValue: { fontSize: 22, fontWeight: '700', marginTop: spacing.sm },
  tileLabel: { fontSize: 11, color: colors.textSecondary },

  card: {
    backgroundColor: colors.bgCard,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    padding: spacing.lg,
  },
  cardTitle: { fontSize: 15, fontWeight: '600', color: colors.textPrimary, marginBottom: spacing.lg },

  subjectRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 6 },
  subjectName: { color: colors.textPrimary, fontSize: 14 },
  subjectPct: { fontSize: 12, fontWeight: '600' },
  subjectCount: { color: colors.textMuted },
  barTrack: { height: 8, backgroundColor: colors.bgElevated, borderRadius: 4, overflow: 'hidden' },
  barFill: { height: 8, borderRadius: 4 },
});
