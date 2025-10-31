"use client";

import Header from "@/components/Header";
import Container from "@mui/material/Container";
import Grid from "@mui/material/Grid";
import Paper from "@mui/material/Paper";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Typography from "@mui/material/Typography";
import Box from "@mui/material/Box";
import { FormEvent, useState } from "react";

function parseJwtExp(token: string): number | null {
  try {
    const [, payload] = token.split(".");
    const json = JSON.parse(
      atob(payload.replace(/-/g, "+").replace(/_/g, "/"))
    );
    if (typeof json?.exp === "number") return json.exp; // seconds since epoch
    return null;
  } catch {
    return null;
  }
}

function setCookie(name: string, value: string, maxAgeSeconds: number) {
  const secure =
    typeof window !== "undefined" && window.location.protocol === "https:";
  const parts = [
    `${name}=${encodeURIComponent(value)}`,
    `Path=/`,
    `SameSite=Lax`,
    `Max-Age=${Math.max(1, Math.floor(maxAgeSeconds))}`,
  ];
  if (secure) parts.push("Secure");
  document.cookie = parts.join("; ");
}

export default function LoginPage() {
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    const username = String(fd.get("username") ?? "").trim();
    const password = String(fd.get("password") ?? "");

    if (!username || !password) return;

    setLoading(true);
    try {
      const base = process.env.NEXT_PUBLIC_API_BASE ?? "";
      const res = await fetch(`${base}/api/auth/login`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ identifier: username, password }),
      });

      const data = await res.json().catch(() => null);
      if (!res.ok) {
        alert(data?.message ?? "เข้าสู่ระบบไม่สำเร็จ");
        return;
      }

      // === เก็บลง cookie ===
      const token: string = data?.token ?? "";
      const user = data?.user ?? null;
      if (token && user) {
        const expSec = parseJwtExp(token);
        const nowSec = Math.floor(Date.now() / 1000);
        const maxAge = expSec && expSec > nowSec ? expSec - nowSec : 3600; // fallback 1h

        setCookie("token", token, maxAge);
        setCookie("user", JSON.stringify(user), maxAge);
      }

      // นำทางหลังเข้าสู่ระบบสำเร็จ
      window.location.href = "/";
    } catch (err) {
      console.error(err);
      alert("ไม่สามารถเข้าสู่ระบบได้");
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <Header />
      <Container component="main" maxWidth="lg" sx={{ py: { xs: 4, md: 6 } }}>
        <Grid container spacing={3}>
          <Grid size={{ xs: 12, md: 6 }}>
            <Paper
              sx={{
                height: { xs: 260, md: 420 },
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                bgcolor: "rgba(0,0,0,.04)",
              }}
              aria-label="ภาพประกอบหน้าล็อกอิน"
            >
              รูปภาพ
            </Paper>
          </Grid>

          <Grid size={{ xs: 12, md: 6 }}>
            <Paper sx={{ p: { xs: 3, md: 4 } }}>
              <Typography variant="h5" fontWeight={900} gutterBottom>
                เข้าสู่ระบบ
              </Typography>

              <Box
                component="form"
                onSubmit={handleSubmit}
                sx={{ display: "grid", gap: 2 }}
              >
                <TextField
                  label="ชื่อผู้ใช้งาน หรืออีเมล"
                  name="username"
                  autoComplete="username"
                  autoFocus
                  required
                  fullWidth
                />
                <TextField
                  label="รหัสผ่าน"
                  name="password"
                  type="password"
                  autoComplete="current-password"
                  required
                  fullWidth
                />

                <Box sx={{ display: "flex", justifyContent: "flex-end" }}>
                  <Button size="small" href="/auth/forgot">
                    ลืมรหัสผ่าน?
                  </Button>
                </Box>

                <Button
                  variant="contained"
                  size="large"
                  type="submit"
                  disabled={loading}
                >
                  {loading ? "กำลังเข้าสู่ระบบ..." : "เข้าสู่ระบบ"}
                </Button>

                <Box sx={{ textAlign: "center", mt: 1 }}>
                  ยังไม่มีบัญชี?{" "}
                  <Button href="/auth/register" variant="text">
                    สมัครสมาชิก
                  </Button>
                </Box>
              </Box>
            </Paper>
          </Grid>
        </Grid>
      </Container>
    </>
  );
}
