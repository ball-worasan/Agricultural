"use client";

import { useState, FormEvent } from "react";
import Grid from "@mui/material/Grid";
import Paper from "@mui/material/Paper";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Typography from "@mui/material/Typography";
import Box from "@mui/material/Box";

import PageContainer from "@/components/PageContainer";
import AuthImageBanner from "@/components/auth/AuthImageBanner";
import LoadingButton from "@/components/LoadingButton";
import PasswordField from "@/components/form/PasswordField";
import PhoneField from "@/components/form/PhoneField";
import { apiClient } from "@/lib/api-client";
import {
  validateRegisterForm,
  type ValidationError,
} from "@/lib/validators/auth.validator";
import type { AuthResponse } from "@/types";

export default function RegisterPage() {
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<ValidationError>({});

  // ฟังก์ชันจัดการการส่งฟอร์ม
  const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setErrors({});
    const fd = new FormData(e.currentTarget);

    const firstName = String(fd.get("firstName") ?? "").trim();
    const lastName = String(fd.get("lastName") ?? "").trim();
    const address = String(fd.get("address") ?? "").trim();
    const phone = String(fd.get("phone") ?? "").replace(/\D/g, ""); // เอา - ออก
    const username = String(fd.get("username") ?? "").trim();
    const email = String(fd.get("email") ?? "").trim();
    const password = String(fd.get("password") ?? "");
    const confirm = String(fd.get("confirm") ?? "");

    // Validate form
    const validationErrors = validateRegisterForm({
      firstName,
      lastName,
      email,
      address,
      phone,
      username,
      password,
      confirm,
    });

    setErrors(validationErrors);
    if (Object.keys(validationErrors).length > 0) return;

    // ส่งข้อมูลไปยัง API
    setLoading(true);
    try {
      const data = await apiClient.post<AuthResponse>("/auth/register", {
        firstName,
        lastName,
        address: address || undefined,
        contactPhone: phone,
        username,
        email,
        password,
      });

      // บันทึก token และ user ใน localStorage
      if (data.token) {
        localStorage.setItem("token", data.token);
      }
      if (data.user) {
        localStorage.setItem("user", JSON.stringify(data.user));
      }

      // เปลี่ยนเส้นทางไปยังหน้าหลัก
      window.location.href = "/";
    } catch (err: any) {
      const errorMessage = err.message || "ลงทะเบียนไม่สำเร็จ";
      alert(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  return (
    <PageContainer sx={{ py: { xs: 4, md: 6 } }}>
      <Grid container spacing={3}>
        {/* กล่องภาพ */}
        <Grid size={{ xs: 12, md: 6 }}>
          <AuthImageBanner title="เริ่มต้นเช่าที่ดินเกษตร" />
        </Grid>

        {/* ฟอร์มสมัครสมาชิก */}
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
              <Grid container spacing={2}>
                <Grid size={{ xs: 12, md: 6 }}>
                  <TextField
                    label="ชื่อ"
                    name="firstName"
                    autoComplete="given-name"
                    required
                    fullWidth
                    error={Boolean(errors.firstName)}
                    helperText={errors.firstName}
                  />
                </Grid>
                <Grid size={{ xs: 12, md: 6 }}>
                  <TextField
                    label="นามสกุล"
                    name="lastName"
                    autoComplete="family-name"
                    required
                    fullWidth
                    error={Boolean(errors.lastName)}
                    helperText={errors.lastName}
                  />
                </Grid>
              </Grid>

              <TextField
                label="อีเมล"
                name="email"
                type="email"
                autoComplete="email"
                required
                fullWidth
                error={Boolean(errors.email)}
                helperText={errors.email || "ตัวอย่าง: user@example.com"}
              />

              <TextField
                label="ที่อยู่ (ไม่บังคับ)"
                name="address"
                autoComplete="street-address"
                multiline
                minRows={2}
                fullWidth
                error={Boolean(errors.address)}
                helperText={errors.address}
              />

              <PhoneField
                error={Boolean(errors.phone)}
                helperText={errors.phone || "ตัวอย่าง: 081-234-5678"}
              />

              <TextField
                label="ชื่อผู้ใช้งาน"
                name="username"
                autoComplete="username"
                required
                fullWidth
                error={Boolean(errors.username)}
                helperText={
                  errors.username ||
                  "ใช้ a-z, 0-9 และ _ เท่านั้น (อย่างน้อย 3 ตัว)"
                }
              />

              <Grid container spacing={2}>
                <Grid size={{ xs: 12, md: 6 }}>
                  <PasswordField
                    label="รหัสผ่าน"
                    name="password"
                    autoComplete="new-password"
                    required
                    error={Boolean(errors.password)}
                    helperText={errors.password || "อย่างน้อย 6 ตัวอักษร"}
                  />
                </Grid>
                <Grid size={{ xs: 12, md: 6 }}>
                  <PasswordField
                    label="ยืนยันรหัสผ่าน"
                    name="confirm"
                    autoComplete="new-password"
                    required
                    error={Boolean(errors.confirm)}
                    helperText={errors.confirm}
                  />
                </Grid>
              </Grid>

              <LoadingButton
                variant="contained"
                size="large"
                type="submit"
                loading={loading}
                loadingText="กำลังสมัคร..."
              >
                สมัครสมาชิก
              </LoadingButton>

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
    </PageContainer>
  );
}
