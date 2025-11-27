import apiClient from "@/lib/api-client";
import type { AuthResponse, LoginDTO, RegisterDTO, User } from "@/types";

export type UpdateProfileDTO = Partial<
  Pick<User, "username" | "email" | "fullName" | "avatarUrl">
>;

export const authService = {
  login: (payload: LoginDTO) =>
    apiClient.post<AuthResponse>("/auth/login", payload),

  register: (payload: RegisterDTO) =>
    apiClient.post<AuthResponse>("/auth/register", payload),

  getProfile: () => apiClient.get<User>("/auth/me"),

  updateProfile: (payload: UpdateProfileDTO) =>
    apiClient.patch<User>("/account/profile", payload),

  changePassword: (payload: { oldPassword: string; newPassword: string }) =>
    apiClient.patch<void>("/account/change-password", payload),
};
