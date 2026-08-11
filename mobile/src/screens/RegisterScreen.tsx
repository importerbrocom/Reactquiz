import React, { useState } from 'react';
import { View, Text, TextInput, TouchableOpacity, StyleSheet, Alert } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { useMutation } from '@tanstack/react-query';
import * as SecureStore from 'expo-secure-store';
import { apiClient, setAccessToken } from '../api/client';
import { useAuthStore } from '../store/auth.store';

export function RegisterScreen() {
  const navigation = useNavigation();
  const { setUser } = useAuthStore();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');

  const registerMutation = useMutation({
    mutationFn: async () => {
      const { data } = await apiClient.post('/auth/register', {
        name, email, password,
        password_confirmation: password,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
      });
      return data.data;
    },
    onSuccess: async (data) => {
      setAccessToken(data.access_token);
      if (data.refresh_token) {
        await SecureStore.setItemAsync('refresh_token', data.refresh_token);
      }
      setUser(data.user);
    },
    onError: (err: any) => {
      Alert.alert('Registration Failed', err?.response?.data?.message || 'Something went wrong');
    },
  });

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Create Account</Text>
      <TextInput style={styles.input} placeholder="Full Name" placeholderTextColor="#64748b" value={name} onChangeText={setName} />
      <TextInput style={styles.input} placeholder="Email" placeholderTextColor="#64748b" value={email} onChangeText={setEmail} autoCapitalize="none" keyboardType="email-address" />
      <TextInput style={styles.input} placeholder="Password" placeholderTextColor="#64748b" value={password} onChangeText={setPassword} secureTextEntry />

      <TouchableOpacity style={[styles.button, registerMutation.isPending && styles.buttonDisabled]} onPress={() => registerMutation.mutate()} disabled={registerMutation.isPending}>
        <Text style={styles.buttonText}>{registerMutation.isPending ? 'Creating...' : 'Create Account'}</Text>
      </TouchableOpacity>

      <TouchableOpacity onPress={() => navigation.goBack()}>
        <Text style={styles.link}>Already have an account? Sign in</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#0B1120', justifyContent: 'center', padding: 24 },
  title: { fontSize: 28, fontWeight: '700', color: '#f8fafc', textAlign: 'center', marginBottom: 32 },
  input: { backgroundColor: '#1e293b', borderRadius: 12, padding: 16, fontSize: 16, color: '#f8fafc', marginBottom: 12, borderWidth: 1, borderColor: '#334155' },
  button: { backgroundColor: '#4f46e5', borderRadius: 12, padding: 16, alignItems: 'center', marginTop: 8 },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontSize: 16, fontWeight: '600' },
  link: { color: '#818cf8', textAlign: 'center', marginTop: 16, fontSize: 14 },
});
