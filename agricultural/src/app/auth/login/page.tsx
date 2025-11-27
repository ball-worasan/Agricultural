"use client";

import { useState, FormEvent } from "react";
import Grid from "@mui/material/Grid";
import Paper from "@mui/material/Paper";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Typography from "@mui/material/Typography";
import Box from "@mui/material/Box";

import PageContainer from "@/components/PageContainer";
import LoadingButton from "@/components/LoadingButton";
import AuthImageBanner from "@/components/auth/AuthImageBanner";
import { apiClient } from "@/lib/api-client";
import { saveAuthResponse, redirectToHome } from "@/lib/utils/api-helpers";
import type { AuthResponse } from "@/types";

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
      const data = await apiClient.post<AuthResponse>("/auth/login", {
        identifier: username,
        password,
      });
      saveAuthResponse(data);
      redirectToHome();
    } catch (err) {
      alert((err as Error).message || "เข้าสู่ระบบไม่สำเร็จ");
    } finally {
      setLoading(false);
    }
  };

  return (
    <PageContainer sx={{ py: { xs: 4, md: 6 } }}>
      <Grid container spacing={3}>
        <Grid size={{ xs: 12, md: 6 }}>
          <AuthImageBanner title="ยินดีต้อนรับกลับ" />
        </Grid>

        <Grid size={{ xs: 12, md: 6 }}>
          <Paper sx={{ p: { xs: 3, md: 4 } }}>
            <Typography variant="h5" fontWeight={900} gutterBottom>
              เข้าสู่ระบบ
            </Typography>

            <Box component="form" onSubmit={handleSubmit} sx={{ display: "grid", gap: 2 }}>
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

              <LoadingButton
                variant="contained"
                size="large"
                type="submit"
                loading={loading}
                loadingText="กำลังเข้าสู่ระบบ..."
              >
                เข้าสู่ระบบ
              </LoadingButton>

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
    </PageContainer>
  );
}
