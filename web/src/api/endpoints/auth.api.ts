import { apiClient } from '../client';
import { setAccessToken } from '../interceptors/auth.interceptor';
import type { ApiEnvelope } from '@/types/api';
import type { LoginResponse, RegisterResponse, User } from '@/types/models';

export interface LoginPayload {
  email: string;
  password: string;
  device_name?: string;
}

export interface RegisterPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  timezone?: string;
  locale?: string;
}

export interface ForgotPasswordPayload {
  email: string;
}

export interface ResetPasswordPayload {
  email: string;
  token: string;
  password: string;
  password_confirmation: string;
}

export async function login(payload: LoginPayload): Promise<LoginResponse> {
  const { data } = await apiClient.post<ApiEnvelope<LoginResponse>>('/auth/login', payload);
  setAccessToken(data.data.access_token);
  return data.data;
}

export async function register(payload: RegisterPayload): Promise<RegisterResponse> {
  const { data } = await apiClient.post<ApiEnvelope<RegisterResponse>>('/auth/register', payload);
  setAccessToken(data.data.access_token);
  return data.data;
}

export async function logout(): Promise<void> {
  await apiClient.post('/auth/logout');
  setAccessToken(null);
}

export async function logoutAll(): Promise<void> {
  await apiClient.post('/auth/logout-all');
  setAccessToken(null);
}

export async function getMe(): Promise<User> {
  const { data } = await apiClient.get<ApiEnvelope<User>>('/auth/me');
  return data.data;
}

export async function updateProfile(payload: Partial<Pick<User, 'name' | 'timezone' | 'locale'>>): Promise<User> {
  const { data } = await apiClient.patch<ApiEnvelope<User>>('/auth/me', payload);
  return data.data;
}

export async function forgotPassword(payload: ForgotPasswordPayload): Promise<string> {
  const { data } = await apiClient.post<ApiEnvelope<{ message: string }>>('/auth/forgot-password', payload);
  return data.message;
}

export async function resetPassword(payload: ResetPasswordPayload): Promise<string> {
  const { data } = await apiClient.post<ApiEnvelope<{ message: string }>>('/auth/reset-password', payload);
  return data.message;
}

export async function verifyEmail(payload: { id: string; hash: string }): Promise<void> {
  await apiClient.post('/auth/email/verify', payload);
}

export async function resendVerificationEmail(): Promise<void> {
  await apiClient.post('/auth/email/resend');
}

export async function refreshToken(): Promise<string> {
  const { data } = await apiClient.post<ApiEnvelope<{ access_token: string }>>('/auth/refresh');
  const token = data.data.access_token;
  setAccessToken(token);
  return token;
}
