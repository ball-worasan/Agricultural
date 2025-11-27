// Centralized auth utility functions

export function parseJwtExp(token: string): number | null {
  try {
    const payload = token.split(".")[1];
    if (!payload) return null;

    const json = JSON.parse(
      Buffer.from(
        payload.replace(/-/g, "+").replace(/_/g, "/"),
        "base64"
      ).toString("utf8")
    );
    return typeof json?.exp === "number" ? json.exp : null;
  } catch {
    return null;
  }
}

export function isTokenValid(token: string | null): boolean {
  if (!token) return false;
  const exp = parseJwtExp(token);
  if (!exp) return true; // No expiry = valid
  return exp > Math.floor(Date.now() / 1000);
}
