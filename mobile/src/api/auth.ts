import { api, setToken, removeToken } from './client';

export interface User {
  id: string;
  name: string;
  email: string;
  role: string;
  status: string;
  timezone: string;
}

export interface LoginResponse {
  user: User;
  access_token: string;
  token_type: string;
  expires_in: number;
}

export const authApi = {
  login: async (email: string, password: string): Promise<LoginResponse> => {
    const { data } = await api.post('/auth/login', { email, password, device_name: 'mobile' });
    if (data.data?.access_token) {
      await setToken(data.data.access_token);
    }
    return data.data;
  },

  register: async (name: string, email: string, password: string): Promise<LoginResponse> => {
    const { data } = await api.post('/auth/register', {
      name, email, password, password_confirmation: password, device_name: 'mobile'
    });
    if (data.data?.access_token) {
      await setToken(data.data.access_token);
    }
    return data.data;
  },

  me: async (): Promise<User> => {
    const { data } = await api.get('/auth/me');
    return data.data;
  },

  logout: async () => {
    try { await api.post('/auth/logout'); } catch (e) {}
    await removeToken();
  },
};
