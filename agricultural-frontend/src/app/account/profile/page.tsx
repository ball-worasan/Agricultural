"use client";

import Header from "@/components/Header";
import Container from "@mui/material/Container";
import Grid from "@mui/material/Grid";
import Paper from "@mui/material/Paper";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Typography from "@mui/material/Typography";
import Avatar from "@mui/material/Avatar";
import Box from "@mui/material/Box";
import { FormEvent, useEffect, useState } from "react";

type Profile = {
  _id: string;
  fullname: string;
  username: string;
  email: string;
  address?: string;
  phone?: string;
};

/** อ่านค่า cookie ตามชื่อ */
function getCookie(name: string): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.match(
    new RegExp("(^|; )" + encodeURIComponent(name) + "=([^;]*)")
  );
  return match ? decodeURIComponent(match[2]) : null;
}

/** รวม base + path ให้ถูกต้อง (กัน // และกัน /api ซ้ำ) */
function joinUrl(base: string, path: string) {
  const b = base.replace(/\/+$/, "");
  const p = path.replace(/^\/+/, "");
  return `${b}/${p}`;
}

/** fetch ที่แนบ Bearer token จาก cookie ให้อัตโนมัติ */
async function authFetch(inputPath: string, init: RequestInit = {}) {
  const base = process.env.NEXT_PUBLIC_API_BASE ?? "";
  const url = joinUrl(base, inputPath);
  const token = getCookie("token");

  const headers = new Headers(init.headers || {});
  if (token) headers.set("Authorization", `Bearer ${token}`);
  // คุณยังใช้ cookie แค่เก็บ token ฝั่ง client; ถ้า server ใช้ cookie auth ด้วย ให้เปิดบรรทัดนี้
  // init.credentials = "include";

  return fetch(url, { ...init, headers });
}

export default function ProfilePage() {
  const [saving, setSaving] = useState(false);
  const [changingPw, setChangingPw] = useState(false);
  const [profile, setProfile] = useState<Profile | null>(null);

  // โหลดโปรไฟล์จาก /api/auth/me (แนบ Bearer จาก cookie)
  useEffect(() => {
    (async () => {
      try {
        const res = await authFetch("/api/auth/me");
        const data = await res.json().catch(() => null);
        if (res.ok && data?.user) {
          setProfile(data.user as Profile);
        } else {
          console.warn("load profile failed", data);
        }
      } catch (e) {
        console.error(e);
      }
    })();
  }, []);

  const handleProfileSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!profile) return;
    setSaving(true);

    const fd = new FormData(e.currentTarget);
    const payload = {
      fullName: String(fd.get("fullName") ?? "").trim(),
      address: String(fd.get("address") ?? "").trim(),
      phone: String(fd.get("phone") ?? "").trim(),
    };

    try {
      const res = await authFetch("/api/account/profile", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const data = await res.json().catch(() => null);
      if (!res.ok) {
        alert(data?.message ?? "บันทึกไม่สำเร็จ");
        return;
      }
      if (data?.user) setProfile(data.user as Profile);
      alert("บันทึกข้อมูลสำเร็จ");
    } catch (err) {
      console.error(err);
      alert("ไม่สามารถบันทึกได้");
    } finally {
      setSaving(false);
    }
  };

  const handlePasswordSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setChangingPw(true);
    const fd = new FormData(e.currentTarget);
    const pw = String(fd.get("password") ?? "");
    const confirm = String(fd.get("confirm") ?? "");
    if (!pw || pw !== confirm) {
      alert("รหัสผ่านไม่ตรงกัน");
      setChangingPw(false);
      return;
    }

    try {
      const res = await authFetch("/api/account/change-password", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ password: pw, confirm }),
      });
      const data = await res.json().catch(() => null);
      if (!res.ok) {
        alert(data?.message ?? "เปลี่ยนรหัสผ่านไม่สำเร็จ");
        return;
      }
      (e.currentTarget as HTMLFormElement).reset();
      alert("เปลี่ยนรหัสผ่านสำเร็จ");
    } catch (err) {
      console.error(err);
      alert("ไม่สามารถเปลี่ยนรหัสผ่านได้");
    } finally {
      setChangingPw(false);
    }
  };

  return (
    <>
      <Header />
      <Container component="main" maxWidth="lg" sx={{ py: { xs: 4, md: 6 } }}>
        {/* จัดการข้อมูลส่วนตัว */}
        <Paper sx={{ p: { xs: 3, md: 4 }, mb: 4 }}>
          <Typography variant="h6" fontWeight={900} gutterBottom>
            จัดการข้อมูลส่วนตัว
          </Typography>

          <Grid container spacing={3} alignItems="center">
            <Grid size={{ xs: 12, md: 3 }}>
              <Avatar
                sx={{
                  width: 120,
                  height: 120,
                  bgcolor: "primary.main",
                  fontSize: 14,
                }}
                aria-label="รูปโปรไฟล์"
              >
                {profile?.username?.[0]?.toUpperCase() ?? "U"}
              </Avatar>
            </Grid>

            <Grid size={{ xs: 12, md: 9 }}>
              <Box
                component="form"
                onSubmit={handleProfileSubmit}
                sx={{ display: "grid", gap: 2 }}
              >
                <Grid container spacing={2}>
                  <Grid size={{ xs: 12, md: 6 }}>
                    <TextField
                      label="ชื่อ–นามสกุล"
                      name="fullName"
                      autoComplete="name"
                      required
                      fullWidth
                      value={profile?.fullname ?? ""}
                      onChange={(e) =>
                        setProfile((p) =>
                          p ? { ...p, fullname: e.target.value } : p
                        )
                      }
                    />
                  </Grid>

                  <Grid size={{ xs: 12, md: 6 }}>
                    <TextField
                      label="ชื่อผู้ใช้"
                      name="username"
                      autoComplete="username"
                      fullWidth
                      value={profile?.username ?? ""}
                      disabled
                      InputProps={{ readOnly: true }}
                      helperText="ชื่อผู้ใช้ไม่สามารถแก้ไขได้"
                    />
                  </Grid>

                  <Grid size={{ xs: 12, md: 6 }}>
                    <TextField
                      label="ที่อยู่"
                      name="address"
                      autoComplete="street-address"
                      fullWidth
                      value={profile?.address ?? ""}
                      onChange={(e) =>
                        setProfile((p) =>
                          p ? { ...p, address: e.target.value } : p
                        )
                      }
                    />
                  </Grid>

                  <Grid size={{ xs: 12, md: 6 }}>
                    <TextField
                      label="เบอร์โทร"
                      name="phone"
                      inputMode="numeric"
                      inputProps={{ pattern: "[0-9]{8,15}", maxLength: 15 }}
                      helperText="ตัวเลข 8–15 หลัก"
                      fullWidth
                      value={profile?.phone ?? ""}
                      onChange={(e) =>
                        setProfile((p) =>
                          p ? { ...p, phone: e.target.value } : p
                        )
                      }
                    />
                  </Grid>
                </Grid>

                <Box sx={{ mt: 1 }}>
                  <Button
                    variant="contained"
                    type="submit"
                    disabled={saving || !profile}
                  >
                    {saving ? "กำลังบันทึก..." : "แก้ไขข้อมูล/บันทึก"}
                  </Button>
                </Box>
              </Box>
            </Grid>
          </Grid>
        </Paper>

        <Paper sx={{ p: { xs: 3, md: 4 } }}>
          <Typography variant="h6" fontWeight={900} gutterBottom>
            เปลี่ยนรหัสผ่าน
          </Typography>

          <Box component="form" onSubmit={handlePasswordSubmit}>
            <Grid container spacing={2}>
              <Grid size={{ xs: 12, md: 4 }}>
                <TextField
                  label="รหัสผ่านใหม่"
                  name="password"
                  type="password"
                  autoComplete="new-password"
                  required
                  fullWidth
                />
              </Grid>

              <Grid size={{ xs: 12, md: 4 }}>
                <TextField
                  label="ยืนยันรหัสผ่าน"
                  name="confirm"
                  type="password"
                  autoComplete="new-password"
                  required
                  fullWidth
                />
              </Grid>

              <Grid size={{ xs: 12, md: 4 }}>
                <Button
                  fullWidth
                  size="large"
                  variant="contained"
                  type="submit"
                  disabled={changingPw}
                  sx={{ height: 56 }}
                >
                  {changingPw ? "กำลังเปลี่ยน..." : "เปลี่ยนรหัสผ่าน"}
                </Button>
              </Grid>
            </Grid>
          </Box>
        </Paper>
      </Container>
    </>
  );
}
