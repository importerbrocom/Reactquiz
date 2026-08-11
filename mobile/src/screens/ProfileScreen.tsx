import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet, Alert } from 'react-native';
import * as SecureStore from 'expo-secure-store';
import { useQueryClient } from '@tanstack/react-query';
import { apiClient, setAccessToken } from '../api/client';
import { useAuthStore } from '../store/auth.store';

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
    <View style={s.container}>
      <Text style={s.title}>Profile</Text>

      <View style={s.card}>
        <View style={s.avatar}>
          <Text style={s.avatarText}>{user.name.charAt(0).toUpperCase()}</Text>
        </View>
        <Text style={s.name}>{user.name}</Text>
        <Text style={s.email}>{user.email}</Text>
      </View>

      <View style={s.card}>
        <Row label="Timezone" value={user.timezone} />
      </View>

      <TouchableOpacity style={s.logoutBtn} onPress={() => Alert.alert('Sign Out', 'Are you sure?', [
        { text: 'Cancel', style: 'cancel' },
        { text: 'Sign Out', style: 'destructive', onPress: handleLogout },
      ])}>
        <Text style={s.logoutText}>Sign Out</Text>
      </TouchableOpacity>
    </View>
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
  container: { flex: 1, backgroundColor: '#0B1120', padding: 20, paddingTop: 60 },
  title: { fontSize: 22, fontWeight: '700', color: '#f8fafc', marginBottom: 20 },
  card: { backgroundColor: '#1e293b', borderRadius: 12, padding: 20, marginBottom: 12, alignItems: 'center' },
  avatar: { width: 56, height: 56, borderRadius: 28, backgroundColor: '#4f46e5', alignItems: 'center', justifyContent: 'center', marginBottom: 12 },
  avatarText: { color: '#fff', fontSize: 22, fontWeight: '700' },
  name: { color: '#f8fafc', fontSize: 18, fontWeight: '600' },
  email: { color: '#94a3b8', fontSize: 14, marginTop: 4 },
  row: { flexDirection: 'row', justifyContent: 'space-between', width: '100%', paddingVertical: 8 },
  rowLabel: { color: '#94a3b8', fontSize: 14 },
  rowValue: { color: '#f8fafc', fontSize: 14 },
  logoutBtn: { backgroundColor: '#450a0a', borderRadius: 12, padding: 16, alignItems: 'center', marginTop: 20 },
  logoutText: { color: '#ef4444', fontSize: 16, fontWeight: '600' },
});
