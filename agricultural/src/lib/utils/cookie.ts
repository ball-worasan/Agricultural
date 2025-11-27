// Centralized cookie utility functions

export function getCookie(name: string): string | null {
  if (typeof document === "undefined") return null;

  const match = document.cookie.match(
    new RegExp(String.raw`(?:^|;\s*)${name}=([^;]+)`)
  );
  return match ? decodeURIComponent(match[1]) : null;
}

export function setCookie(
  name: string,
  value: string,
  maxAgeSeconds: number
): void {
  if (typeof document === "undefined") return;

  const secure =
    typeof window !== "undefined" && window.location.protocol === "https:";
  const parts = [
    `${encodeURIComponent(name)}=${encodeURIComponent(value)}`,
    "Path=/",
    "SameSite=Lax",
    `Max-Age=${Math.max(1, Math.floor(maxAgeSeconds))}`,
  ];
  if (secure) parts.push("Secure");
  document.cookie = parts.join("; ");
}

export function deleteCookie(name: string): void {
  if (typeof document === "undefined") return;
  document.cookie = `${encodeURIComponent(
    name
  )}=; Path=/; Max-Age=0; SameSite=Lax`;
}

export function getJsonCookie<T>(name: string): T | null {
  try {
    const raw = getCookie(name);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}
