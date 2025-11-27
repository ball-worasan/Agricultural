"use client";

import { useState, useEffect, useCallback } from "react";
import { getCookie, getJsonCookie, deleteCookie } from "@/lib/utils/cookie";
import { isTokenValid } from "@/lib/utils/auth";
import type { User } from "@/types";

export function useAuth() {
  const [user, setUser] = useState<User | null>(null);
  const [isLoggedIn, setIsLoggedIn] = useState(false);
  const [loading, setLoading] = useState(true);

  const syncAuth = useCallback(() => {
    const token = getCookie("token");
    const userData = getJsonCookie<User>("user");
    const valid = token ? isTokenValid(token) : false;

    if (!valid) {
      deleteCookie("token");
      deleteCookie("user");
    }

    setIsLoggedIn(valid);
    setUser(valid ? userData : null);
    setLoading(false);
  }, []);

  useEffect(() => {
    syncAuth();

    const onFocus = () => syncAuth();
    const onVisible = () =>
      document.visibilityState === "visible" && syncAuth();

    window.addEventListener("focus", onFocus);
    document.addEventListener("visibilitychange", onVisible);
    
    return () => {
      window.removeEventListener("focus", onFocus);
      document.removeEventListener("visibilitychange", onVisible);
    };
  }, [syncAuth]);

  return { user, isLoggedIn, loading, syncAuth };
}
