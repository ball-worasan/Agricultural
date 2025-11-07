import { api } from './api';

export interface LoginDTO {
  username: string;
  password: string;
}

export interface RegisterDTO {
  username: string;
  email: string;
  password: string;
  confirmPassword: string;
}

export interface AuthResponse {
  token: string;
  user: {
    id: string;
    username: string;
    email: string;
  };
}

export const authService = {
  async login(data: LoginDTO): Promise<AuthResponse> {
    return api.post('/auth/login', data);
  },

  async register(data: RegisterDTO): Promise<AuthResponse> {
    return api.post('/auth/register', data);
  },

  async getProfile(): Promise<any> {
    return api.get('/users/profile');
  },

  async updateProfile(data: any): Promise<any> {
    return api.patch('/users/profile', data);
  },

  async changePassword(data: { oldPassword: string; newPassword: string }): Promise<void> {
    return api.patch('/users/change-password', data);
  },
};