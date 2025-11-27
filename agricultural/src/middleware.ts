import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";

function parseJwtExp(token: string): number | null {
  try {
    const payload = token.split(".")[1] || "";
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

function clearAuthCookies(res: NextResponse): void {
  res.cookies.set({
    name: "token",
    value: "",
    maxAge: 0,
    path: "/",
    sameSite: "lax",
  });
  res.cookies.set({
    name: "user",
    value: "",
    maxAge: 0,
    path: "/",
    sameSite: "lax",
  });
}

export function middleware(req: NextRequest) {
  const url = req.nextUrl;
  const pathname = url.pathname;
  const token = req.cookies.get("token")?.value ?? "";
  const nowSec = Math.floor(Date.now() / 1000);

  // Check if token is valid
  let isLoggedIn = false;
  if (token) {
    const exp = parseJwtExp(token);
    isLoggedIn = !exp || exp > nowSec;
  }

  // Protected routes
  const isProtected =
    pathname.startsWith("/account") ||
    pathname.startsWith("/admin") ||
    pathname.startsWith("/checkout") ||
    pathname === "/contract" ||
    pathname.startsWith("/contract/") ||
    pathname.startsWith("/reserve");

  const isAuthPage = pathname.startsWith("/auth");
  const res = NextResponse.next();

  // Clear expired token
  if (token && !isLoggedIn) {
    clearAuthCookies(res);
  }

  // Redirect to login if accessing protected route without auth
  if (isProtected && !isLoggedIn) {
    const login = req.nextUrl.clone();
    login.pathname = "/auth/login";
    login.searchParams.set("redirect", pathname + url.search);
    return NextResponse.redirect(login);
  }

  // Redirect to profile if accessing auth page while logged in
  if (isAuthPage && isLoggedIn) {
    const dest = req.nextUrl.clone();
    dest.pathname = "/account/profile";
    dest.search = "";
    return NextResponse.redirect(dest);
  }

  return res;
}

export const config = {
  matcher: [
    "/auth/:path*",
    "/account/:path*",
    "/admin/:path*",
    "/checkout/:path*",
    "/contract",
    "/contract/:path*",
    "/reserve/:path*",
  ],
};
