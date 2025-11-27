"use client";

import React, {
  createContext,
  useContext,
  useState,
  useEffect,
  useMemo,
  useCallback,
} from "react";
import { authService, type UpdateProfileDTO } from "@/services/auth";
import { getCookie, setCookie, deleteCookie } from "@/lib/utils/cookie";
import { parseJwtExp } from "@/lib/utils/auth";
import type { User, LoginDTO, RegisterDTO } from "@/types";

interface AuthContextType {
  user: User | null;
  loading: boolean;
  login: (username: string, password: string) => Promise<void>;
  register: (data: RegisterDTO) => Promise<void>;
  logout: () => void;
  updateProfile: (data: UpdateProfileDTO) => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  const checkAuth = useCallback(async () => {
    try {
      const token = getCookie("token");
      if (token) {
        const profile = await authService.getProfile();
        setUser(profile);
      }
    } catch {
      deleteCookie("token");
      deleteCookie("user");
      setUser(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void checkAuth();
  }, [checkAuth]);

  const login = useCallback(async (username: string, password: string) => {
    const payload: LoginDTO = { username, password };
    const response = await authService.login(payload);

    const expSec = parseJwtExp(response.token);
    const nowSec = Math.floor(Date.now() / 1000);
    const maxAge = expSec && expSec > nowSec ? expSec - nowSec : 3600;

    setCookie("token", response.token, maxAge);
    setCookie("user", JSON.stringify(response.user), maxAge);
    setUser(response.user);
  }, []);

  const register = useCallback(async (data: RegisterDTO) => {
    const response = await authService.register(data);

    const expSec = parseJwtExp(response.token);
    const nowSec = Math.floor(Date.now() / 1000);
    const maxAge = expSec && expSec > nowSec ? expSec - nowSec : 3600;

    setCookie("token", response.token, maxAge);
    setCookie("user", JSON.stringify(response.user), maxAge);
    setUser(response.user);
  }, []);

  const logout = useCallback(() => {
    deleteCookie("token");
    deleteCookie("user");
    setUser(null);
  }, []);

  const updateProfile = useCallback(async (data: UpdateProfileDTO) => {
    const updatedProfile = await authService.updateProfile(data);
    setUser(updatedProfile);

    // Update user cookie
    const token = getCookie("token");
    if (token) {
      const expSec = parseJwtExp(token);
      const nowSec = Math.floor(Date.now() / 1000);
      const maxAge = expSec && expSec > nowSec ? expSec - nowSec : 3600;
      setCookie("user", JSON.stringify(updatedProfile), maxAge);
    }
  }, []);

  const value = useMemo(
    () => ({ user, loading, login, register, logout, updateProfile }),
    [user, loading, login, register, logout, updateProfile]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
}
