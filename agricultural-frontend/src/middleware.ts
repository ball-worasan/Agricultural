// src/middleware.ts
import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";

/** อ่าน exp จาก JWT (base64url) ถ้าไม่มี exp คืน null */
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

/** เคลียร์คุกกี้ token ฝั่ง client */
function clearTokenCookie(res: NextResponse) {
  res.cookies.set({
    name: "token",
    value: "",
    maxAge: 0,
    path: "/",
    sameSite: "lax",
  });
  // ถ้าเคยเก็บ user เป็นคุกกี้ด้วย เคลียร์ด้วย
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

  // ==== ตรวจสถานะล็อกอินจากคุกกี้ + exp ====
  let isLoggedIn = false;
  if (token) {
    const exp = parseJwtExp(token);
    isLoggedIn = !exp || exp > nowSec; // ถ้าไม่มี exp จะถือว่า valid
  }

  // กลุ่มเพจที่ "ต้องล็อกอิน"
  const mustAuth =
    pathname.startsWith("/account") ||
    pathname.startsWith("/admin") ||
    pathname.startsWith("/checkout") ||
    pathname === "/contract" ||
    pathname.startsWith("/contract/") ||
    pathname.startsWith("/reserve");

  // กลุ่มเพจ "auth" ที่ไม่ควรเข้าเมื่อ login แล้ว
  const isAuthPage = pathname.startsWith("/auth");

  // สร้าง response เริ่มต้น
  const res = NextResponse.next();

  // ถ้า token หมดอายุ → เคลียร์คุกกี้
  if (token && !isLoggedIn) {
    clearTokenCookie(res);
  }

  // 1) กันหน้า protected ถ้ายังไม่ล็อกอิน → ส่งไป /auth/login พร้อม redirect param
  if (mustAuth && !isLoggedIn) {
    const login = req.nextUrl.clone();
    login.pathname = "/auth/login";
    login.searchParams.set("redirect", pathname + url.search);
    return NextResponse.redirect(login);
  }

  // 2) กันหน้า auth ถ้าล็อกอินแล้ว → ส่งออกไปโปรไฟล์ (หรือหน้าแรก)
  if (isAuthPage && isLoggedIn) {
    const dest = req.nextUrl.clone();
    dest.pathname = "/account/profile"; // ปรับเป็น "/" ได้ตามต้องการ
    dest.search = "";
    return NextResponse.redirect(dest);
  }

  return res;
}

// ตั้ง matcher ให้ทำงานเฉพาะเส้นทางที่เกี่ยวข้อง
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
