import { api } from "./api";

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

export interface UserProfile {
  id: string;
  username: string;
  email: string;
  fullName?: string;
  roles?: string[];
  avatarUrl?: string;
}

export interface AuthResponse {
  token: string;
  user: UserProfile;
}

export type UpdateProfileDTO = Partial<
  Pick<UserProfile, "username" | "email" | "fullName" | "avatarUrl">
>;

export const authService = {
  async login(payload: LoginDTO): Promise<AuthResponse> {
    const { data } = await api.post<AuthResponse>("/auth/login", payload);
    return data;
  },

  async register(payload: RegisterDTO): Promise<AuthResponse> {
    const { data } = await api.post<AuthResponse>("/auth/register", payload);
    return data;
  },

  async getProfile(): Promise<UserProfile> {
    const { data } = await api.get<UserProfile>("/users/profile");
    return data;
  },

  async updateProfile(payload: UpdateProfileDTO): Promise<UserProfile> {
    const { data } = await api.patch<UserProfile>("/users/profile", payload);
    return data;
  },

  async changePassword(payload: {
    oldPassword: string;
    newPassword: string;
  }): Promise<void> {
    await api.patch<void>("/users/change-password", payload);
  },
};
