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
    if (typeof json?.exp === "number") return json.exp;
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

export default function RegisterPage() {
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<{
    phone?: string;
    password?: string;
    email?: string;
  }>({});

  const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setErrors({});
    const fd = new FormData(e.currentTarget);

    const fullName = String(fd.get("fullName") ?? "").trim();
    const address = String(fd.get("address") ?? "").trim();
    const phone = String(fd.get("phone") ?? "").trim();
    const username = String(fd.get("username") ?? "").trim();
    const email = String(fd.get("email") ?? "").trim();
    const password = String(fd.get("password") ?? "");
    const confirm = String(fd.get("confirm") ?? "");

    const phoneOk = /^[0-9]{8,15}$/.test(phone);
    const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    const pwOk = password.length > 0 && password === confirm;

    const newErrors: typeof errors = {};
    if (!phoneOk) newErrors.phone = "กรุณากรอกตัวเลข 8–15 หลัก";
    if (!emailOk) newErrors.email = "อีเมลไม่ถูกต้อง";
    if (!pwOk) newErrors.password = "รหัสผ่านไม่ตรงกัน";
    setErrors(newErrors);
    if (!phoneOk || !emailOk || !pwOk) return;

    setLoading(true);
    try {
      const base = process.env.NEXT_PUBLIC_API_BASE ?? "";
      const res = await fetch(`${base}/api/auth/register`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          fullName,
          address,
          contactPhone: phone,
          username,
          email,
          password,
        }),
      });

      const data = await res.json().catch(() => null);
      if (!res.ok) {
        alert(data?.message ?? "ลงทะเบียนไม่สำเร็จ");
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

      // ไปหน้า Dashboard หรือหน้าแรก
      window.location.href = "/";
    } catch (err) {
      console.error(err);
      alert("ไม่สามารถสมัครสมาชิกได้");
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
                height: { xs: 300, md: 520 },
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                bgcolor: "rgba(0,0,0,.04)",
              }}
              aria-label="ภาพประกอบหน้าสมัครสมาชิก"
            >
              รูปภาพ
            </Paper>
          </Grid>

          <Grid size={{ xs: 12, md: 6 }}>
            <Paper sx={{ p: { xs: 3, md: 4 } }}>
              <Typography variant="h5" fontWeight={900} gutterBottom>
                สมัครสมาชิก
              </Typography>

              <Box
                component="form"
                onSubmit={handleSubmit}
                sx={{ display: "grid", gap: 2 }}
              >
                <TextField
                  label="ชื่อ–นามสกุล"
                  name="fullName"
                  autoComplete="name"
                  required
                  fullWidth
                />
                <TextField
                  label="อีเมล"
                  name="email"
                  autoComplete="email"
                  required
                  fullWidth
                  error={Boolean(errors.email)}
                  helperText={errors.email}
                />
                <TextField
                  label="ที่อยู่"
                  name="address"
                  autoComplete="street-address"
                  multiline
                  minRows={2}
                  fullWidth
                />
                <TextField
                  label="เบอร์โทร (ตัวเลข 8–15)"
                  name="phone"
                  required
                  fullWidth
                  inputMode="numeric"
                  inputProps={{ pattern: "[0-9]{8,15}", maxLength: 15 }}
                  error={Boolean(errors.phone)}
                  helperText={errors.phone}
                />
                <TextField
                  label="ชื่อผู้ใช้งาน"
                  name="username"
                  autoComplete="username"
                  required
                  fullWidth
                />

                <Grid container spacing={2}>
                  <Grid size={{ xs: 12, md: 6 }}>
                    <TextField
                      label="รหัสผ่าน"
                      name="password"
                      type="password"
                      autoComplete="new-password"
                      required
                      fullWidth
                      error={Boolean(errors.password)}
                    />
                  </Grid>
                  <Grid size={{ xs: 12, md: 6 }}>
                    <TextField
                      label="ยืนยันรหัสผ่าน"
                      name="confirm"
                      type="password"
                      autoComplete="new-password"
                      required
                      fullWidth
                      error={Boolean(errors.password)}
                      helperText={errors.password}
                    />
                  </Grid>
                </Grid>

                <Button
                  variant="contained"
                  size="large"
                  type="submit"
                  disabled={loading}
                >
                  {loading ? "กำลังสมัคร..." : "สมัครสมาชิก"}
                </Button>

                <Box sx={{ textAlign: "center" }}>
                  มีบัญชีอยู่แล้ว?{" "}
                  <Button href="/auth/login" variant="text">
                    เข้าสู่ระบบ
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
