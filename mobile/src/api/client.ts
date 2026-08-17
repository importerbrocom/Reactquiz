import axios from 'axios';
import * as SecureStore from 'expo-secure-store';

const API_BASE_URL = 'https://mediprep.nokkoo.in/api/v1';

export const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 15000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
});

// Add auth token to every request
api.interceptors.request.use(async (config) => {
  const token = await SecureStore.getItemAsync('access_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Handle 401 - token expired
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      await SecureStore.deleteItemAsync('access_token');
    }
    return Promise.reject(error);
  }
);

export const setToken = async (token: string) => {
  await SecureStore.setItemAsync('access_token', token);
};

export const getToken = async () => {
  return await SecureStore.getItemAsync('access_token');
};

export const removeToken = async () => {
  await SecureStore.deleteItemAsync('access_token');
};
