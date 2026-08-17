import React, { useState } from 'react';
import { View, Text, TextInput, TouchableOpacity, StyleSheet, Alert, KeyboardAvoidingView, Platform, ScrollView } from 'react-native';
import { authApi } from '../api/auth';

export default function RegisterScreen({ navigation }: any) {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [loading, setLoading] = useState(false);

  const handleRegister = async () => {
    if (!name || !email || !password) { Alert.alert('Error', 'Please fill all fields'); return; }
    if (password !== confirm) { Alert.alert('Error', 'Passwords do not match'); return; }
    if (password.length < 8) { Alert.alert('Error', 'Password must be at least 8 characters'); return; }
    setLoading(true);
    try {
      const result = await authApi.register(name, email, password);
      if (result?.access_token) {
        navigation.replace('Main');
      } else {
        Alert.alert('Success', 'Account created! Please sign in.');
        navigation.navigate('Login');
      }
    } catch (e: any) {
      const msg = e.response?.data?.message || 'Registration failed';
      Alert.alert('Error', msg);
    }
    setLoading(false);
  };

  return (
    <KeyboardAvoidingView style={styles.container} behavior={Platform.OS === 'ios' ? 'padding' : 'height'}>
      <ScrollView contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
        <Text style={styles.title}>Create Account</Text>
        <View style={styles.card}>
          <Text style={styles.label}>FULL NAME</Text>
          <TextInput style={styles.input} value={name} onChangeText={setName} placeholder="Enter your name" placeholderTextColor="#64748b" />
          <Text style={styles.label}>EMAIL</Text>
          <TextInput style={styles.input} value={email} onChangeText={setEmail} placeholder="Enter email" placeholderTextColor="#64748b" keyboardType="email-address" autoCapitalize="none" />
          <Text style={styles.label}>PASSWORD</Text>
          <TextInput style={styles.input} value={password} onChangeText={setPassword} placeholder="Min 8 characters" placeholderTextColor="#64748b" secureTextEntry />
          <Text style={styles.label}>CONFIRM PASSWORD</Text>
          <TextInput style={styles.input} value={confirm} onChangeText={setConfirm} placeholder="Confirm password" placeholderTextColor="#64748b" secureTextEntry />
          <TouchableOpacity style={styles.button} onPress={handleRegister} disabled={loading}>
            <Text style={styles.buttonText}>{loading ? 'Creating...' : 'Create Account'}</Text>
          </TouchableOpacity>
          <TouchableOpacity onPress={() => navigation.navigate('Login')}>
            <Text style={styles.linkText}>Already have an account? <Text style={styles.link}>Sign In</Text></Text>
          </TouchableOpacity>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#0B1120' },
  scroll: { flexGrow: 1, justifyContent: 'center', padding: 24 },
  title: { color: '#f8fafc', fontSize: 24, fontWeight: '700', textAlign: 'center', marginBottom: 20 },
  card: { backgroundColor: '#1e293b', borderRadius: 20, padding: 24, borderWidth: 1, borderColor: '#334155' },
  label: { color: '#94a3b8', fontSize: 12, fontWeight: '600', letterSpacing: 0.5, marginBottom: 6 },
  input: { backgroundColor: '#0f172a', borderWidth: 1.5, borderColor: '#334155', borderRadius: 12, padding: 14, color: '#f8fafc', fontSize: 16, marginBottom: 16 },
  button: { backgroundColor: '#3b82f6', borderRadius: 12, padding: 16, alignItems: 'center', marginTop: 8, marginBottom: 16 },
  buttonText: { color: '#fff', fontSize: 16, fontWeight: '700' },
  linkText: { color: '#94a3b8', textAlign: 'center', fontSize: 14 },
  link: { color: '#3b82f6', fontWeight: '600' },
});
