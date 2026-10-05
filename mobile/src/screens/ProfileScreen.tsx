import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet, Alert, ScrollView } from 'react-native';
import * as SecureStore from 'expo-secure-store';
import { useQueryClient } from '@tanstack/react-query';
import { apiClient, setAccessToken } from '../api/client';
import { useAuthStore } from '../store/auth.store';
import { colors, radius, spacing } from '../theme';

export function ProfileScreen() {
  const { user, clearUser } = useAuthStore();
  const queryClient = useQueryClient();

  const handleLogout = async () => {
    try {
      await apiClient.post('/auth/logout');
    } catch { /* ignore */ }
    setAccessToken(null);
    await SecureStore.deleteItemAsync('refresh_token');
    queryClient.clear();
    clearUser();
  };

  if (!user) return null;

  return (
    <ScrollView style={s.container} contentContainerStyle={s.content}>
      <Text style={s.title}>Profile</Text>

      {/* Gradient-style profile header */}
      <View style={s.hero}>
        <View style={s.avatar}>
          <Text style={s.avatarText}>{user.name.charAt(0).toUpperCase()}</Text>
        </View>
        <View style={{ flex: 1 }}>
          <Text style={s.name}>{user.name}</Text>
          <Text style={s.email}>{user.email}</Text>
        </View>
      </View>

      {/* Account details */}
      <View style={s.card}>
        <Row label="Timezone" value={user.timezone} />
      </View>

      <TouchableOpacity
        style={s.logoutBtn}
        onPress={() =>
          Alert.alert('Sign Out', 'Are you sure?', [
            { text: 'Cancel', style: 'cancel' },
            { text: 'Sign Out', style: 'destructive', onPress: handleLogout },
          ])
        }
        activeOpacity={0.9}
      >
        <Text style={s.logoutText}>Sign Out</Text>
      </TouchableOpacity>
    </ScrollView>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <View style={s.row}>
      <Text style={s.rowLabel}>{label}</Text>
      <Text style={s.rowValue}>{value}</Text>
    </View>
  );
}

const s = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.bg },
  content: { padding: spacing.xl, paddingTop: 56, gap: spacing.md },
  title: { fontSize: 24, fontWeight: '700', color: colors.textPrimary, marginBottom: spacing.xs },

  hero: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.lg,
    backgroundColor: colors.primary,
    borderRadius: radius.lg,
    padding: spacing.xl,
  },
  avatar: {
    width: 60,
    height: 60,
    borderRadius: 30,
    backgroundColor: 'rgba(255,255,255,0.22)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: { color: '#fff', fontSize: 24, fontWeight: '700' },
  name: { color: '#fff', fontSize: 20, fontWeight: '700' },
  email: { color: '#dbeafe', fontSize: 14, marginTop: 2 },

  card: {
    backgroundColor: colors.bgCard,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    padding: spacing.lg,
  },
  row: { flexDirection: 'row', justifyContent: 'space-between', width: '100%', paddingVertical: 6 },
  rowLabel: { color: colors.textSecondary, fontSize: 14 },
  rowValue: { color: colors.textPrimary, fontSize: 14, fontWeight: '500' },

  logoutBtn: {
    borderWidth: 1,
    borderColor: 'rgba(239,68,68,0.4)',
    backgroundColor: 'rgba(239,68,68,0.1)',
    borderRadius: radius.lg,
    padding: spacing.lg,
    alignItems: 'center',
    marginTop: spacing.sm,
  },
  logoutText: { color: colors.danger, fontSize: 16, fontWeight: '600' },
});
