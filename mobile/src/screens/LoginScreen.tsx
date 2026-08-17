import React, { useState } from 'react';
import { View, Text, TextInput, TouchableOpacity, StyleSheet, Image, Alert, KeyboardAvoidingView, Platform, ScrollView } from 'react-native';
import { authApi } from '../api/auth';

export default function LoginScreen({ navigation }: any) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);

  const handleLogin = async () => {
    if (!email || !password) { Alert.alert('Error', 'Please fill all fields'); return; }
    setLoading(true);
    try {
      const result = await authApi.login(email, password);
      if (result?.access_token) {
        navigation.replace('Main');
      }
    } catch (e: any) {
      Alert.alert('Login Failed', e.response?.data?.message || 'Invalid credentials');
    }
    setLoading(false);
  };

  return (
    <KeyboardAvoidingView style={styles.container} behavior={Platform.OS === 'ios' ? 'padding' : 'height'}>
      <ScrollView contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
        <Image source={require('../../assets/icon.png')} style={styles.logo} resizeMode="contain" />
        
        <View style={styles.card}>
          <Text style={styles.label}>EMAIL</Text>
          <TextInput style={styles.input} value={email} onChangeText={setEmail} placeholder="Enter your email" placeholderTextColor="#64748b" keyboardType="email-address" autoCapitalize="none" />
          
          <Text style={styles.label}>PASSWORD</Text>
          <TextInput style={styles.input} value={password} onChangeText={setPassword} placeholder="Enter password" placeholderTextColor="#64748b" secureTextEntry />
          
          <TouchableOpacity style={styles.button} onPress={handleLogin} disabled={loading}>
            <Text style={styles.buttonText}>{loading ? 'Signing in...' : 'Sign In'}</Text>
          </TouchableOpacity>

          <TouchableOpacity onPress={() => navigation.navigate('Register')}>
            <Text style={styles.linkText}>Don't have an account? <Text style={styles.link}>Register</Text></Text>
          </TouchableOpacity>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#0B1120' },
  scroll: { flexGrow: 1, justifyContent: 'center', padding: 24 },
  logo: { width: 120, height: 120, alignSelf: 'center', marginBottom: 24 },
  card: { backgroundColor: '#1e293b', borderRadius: 20, padding: 24, borderWidth: 1, borderColor: '#334155' },
  label: { color: '#94a3b8', fontSize: 12, fontWeight: '600', letterSpacing: 0.5, marginBottom: 6 },
  input: { backgroundColor: '#0f172a', borderWidth: 1.5, borderColor: '#334155', borderRadius: 12, padding: 14, color: '#f8fafc', fontSize: 16, marginBottom: 16 },
  button: { backgroundColor: '#3b82f6', borderRadius: 12, padding: 16, alignItems: 'center', marginTop: 8, marginBottom: 16 },
  buttonText: { color: '#fff', fontSize: 16, fontWeight: '700' },
  linkText: { color: '#94a3b8', textAlign: 'center', fontSize: 14 },
  link: { color: '#3b82f6', fontWeight: '600' },
});
