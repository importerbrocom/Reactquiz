import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Alert } from 'react-native';
import { authApi, User } from '../api/auth';
import { removeToken } from '../api/client';

export default function ProfileScreen({ navigation }: any) {
  const [user, setUser] = useState<User | null>(null);

  useEffect(() => {
    authApi.me().then(setUser).catch(() => {});
  }, []);

  const handleLogout = () => {
    Alert.alert('Sign Out', 'Are you sure?', [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Sign Out', style: 'destructive', onPress: async () => {
        await authApi.logout();
        navigation.replace('Login');
      }}
    ]);
  };

  return (
    <View style={styles.container}>
      <View style={styles.card}>
        <View style={styles.avatar}><Text style={styles.avatarText}>{user?.name?.[0] || '?'}</Text></View>
        <Text style={styles.name}>{user?.name || '—'}</Text>
        <Text style={styles.email}>{user?.email || '—'}</Text>
      </View>

      <View style={styles.infoCard}>
        <View style={styles.row}><Text style={styles.label}>Role</Text><Text style={styles.value}>Student</Text></View>
        <View style={styles.row}><Text style={styles.label}>Status</Text><Text style={[styles.value, { color: '#86efac' }]}>Active</Text></View>
        <View style={styles.row}><Text style={styles.label}>Timezone</Text><Text style={styles.value}>{user?.timezone || 'Asia/Kolkata'}</Text></View>
      </View>

      <TouchableOpacity style={styles.logoutBtn} onPress={handleLogout}>
        <Text style={styles.logoutText}>Sign Out</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#0B1120', padding: 16 },
  card: { backgroundColor: '#1e293b', borderRadius: 20, padding: 24, alignItems: 'center', marginBottom: 16, borderWidth: 1, borderColor: '#334155' },
  avatar: { width: 70, height: 70, borderRadius: 35, backgroundColor: '#3b82f6', justifyContent: 'center', alignItems: 'center', marginBottom: 12 },
  avatarText: { color: '#fff', fontSize: 28, fontWeight: '700' },
  name: { color: '#f8fafc', fontSize: 20, fontWeight: '700', marginBottom: 4 },
  email: { color: '#94a3b8', fontSize: 14 },
  infoCard: { backgroundColor: '#1e293b', borderRadius: 16, padding: 16, borderWidth: 1, borderColor: '#334155', marginBottom: 16 },
  row: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: '#0f172a' },
  label: { color: '#94a3b8', fontSize: 14 },
  value: { color: '#e2e8f0', fontSize: 14, fontWeight: '500' },
  logoutBtn: { backgroundColor: '#450a0a', borderWidth: 1, borderColor: '#dc2626', borderRadius: 14, padding: 16, alignItems: 'center' },
  logoutText: { color: '#fca5a5', fontSize: 16, fontWeight: '600' },
});
