import { setCookie } from "./cookie";
import { parseJwtExp } from "./auth";
import type { AuthResponse } from "@/types";

export function saveAuthResponse(response: AuthResponse): void {
  const expSec = parseJwtExp(response.token);
  const nowSec = Math.floor(Date.now() / 1000);
  const maxAge = expSec && expSec > nowSec ? expSec - nowSec : 3600;

  setCookie("token", response.token, maxAge);
  setCookie("user", JSON.stringify(response.user), maxAge);
}

export function redirectToHome(): void {
  window.location.href = "/";
}
