"use client";

import { useEffect, useMemo, useState, useCallback } from "react";
import { useRouter } from "next/navigation";
import Container from "@mui/material/Container";
import Grid from "@mui/material/Grid";
import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import Paper from "@mui/material/Paper";
import Button from "@mui/material/Button";
import Chip from "@mui/material/Chip";
import Alert from "@mui/material/Alert";
import Header from "@/components/Header";

/* ===== Helpers (เหมือน Header) ===== */
type UserLite = { username?: string; email?: string; fullname?: string };

const readCookie = (name: string): string | null => {
  const m = document.cookie.match(
    new RegExp(String.raw`(?:^|;\s*)${name}=([^;]+)`)
  );
  return m ? decodeURIComponent(m[1]) : null;
};

const readUserCookie = (): UserLite | null => {
  try {
    const raw = readCookie("user");
    if (!raw) return null;
    return JSON.parse(raw);
  } catch {
    return null;
  }
};

const parseJwtExp = (token: string): number | null => {
  try {
    const [, payload] = token.split(".");
    const json = JSON.parse(
      atob(payload.replace(/-/g, "+").replace(/_/g, "/"))
    );
    return typeof json?.exp === "number" ? json.exp : null;
  } catch {
    return null;
  }
};

export default function ListingDetail() {
  const router = useRouter();

  // ---- mock ข้อมูลรายการ (ในงานจริงดึงจาก API/params) ----
  const listing = {
    id: "123",
    title: "ปล่อยเช่าที่ดินเปล่า ต.ภูสิงห์ อ.สหัสขันธ์ ติดเชื่อมลำปาว",
    locationText: "อ.สหัสขันธ์ จ.กาฬสินธุ์",
    postedAt: "08/09/2568",
    price: 45000,
    unit: "ปี",
    status: "available" as const,
    image: "", // url รูปถ้ามี
  };

  // ---- auth state ----
  const [user, setUser] = useState<UserLite | null>(null);
  const [tokenValid, setTokenValid] = useState(false);
  const isLoggedIn = useMemo(
    () => Boolean(user) && tokenValid,
    [user, tokenValid]
  );

  useEffect(() => {
    const syncAuth = () => {
      const token = readCookie("token");
      const u = readUserCookie();
      let valid = false;
      if (token) {
        const exp = parseJwtExp(token);
        const now = Math.floor(Date.now() / 1000);
        valid = !exp || exp > now;
      }
      setTokenValid(!!valid);
      setUser(valid ? u : null);
    };

    syncAuth();
    const onFocus = () => syncAuth();
    const onVisible = () =>
      document.visibilityState === "visible" && syncAuth();

    window.addEventListener("focus", onFocus);
    document.addEventListener("visibilitychange", onVisible);
    return () => {
      window.removeEventListener("focus", onFocus);
      document.removeEventListener("visibilitychange", onVisible);
    };
  }, []);

  // ---- ส่ง draft แล้วไปหน้า inline ----
  const handleReserve = useCallback(() => {
    const draft = {
      listingId: listing.id,
      title: listing.title,
      locationText: listing.locationText,
      price: listing.price,
      unit: listing.unit as "วัน" | "เดือน" | "ปี",
      image: listing.image,
    };
    sessionStorage.setItem("reserveDraft", JSON.stringify(draft));
    router.push(`/reserve/${listing.id}/inline`);
  }, [listing, router]);

  return (
    <>
      <Header />
      <Container maxWidth="lg" sx={{ py: { xs: 4, md: 6 } }}>
        <Paper sx={{ p: { xs: 2, md: 4 } }}>
          <Typography variant="h5" fontWeight={900} gutterBottom>
            {listing.title}
          </Typography>

          <Box sx={{ display: "flex", gap: 2, color: "text.secondary", mb: 2 }}>
            <span>📍 {listing.locationText}</span>
            <span>•</span>
            <span>ลงประกาศ : {listing.postedAt}</span>
          </Box>

          <Grid container spacing={3}>
            <Grid size={{ xs: 12, md: 7 }}>
              <Paper
                sx={{
                  height: 380,
                  bgcolor: "rgba(0,0,0,.04)",
                  border: "1px solid rgba(0,0,0,.06)",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  fontSize: 22,
                  color: "text.secondary",
                }}
              >
                &lt; รูปภาพพื้นที่ให้เช่า &gt;
              </Paper>
            </Grid>

            <Grid size={{ xs: 12, md: 5 }}>
              <Paper
                sx={{
                  p: 3,
                  height: "100%",
                  display: "flex",
                  flexDirection: "column",
                }}
              >
                <Box
                  sx={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    mb: 2,
                  }}
                >
                  <Typography variant="h5" fontWeight={900} color="primary">
                    {listing.price.toLocaleString("th-TH")}/{listing.unit}
                  </Typography>
                  <Chip label="ว่าง" color="success" variant="outlined" />
                </Box>

                <Typography variant="h6" fontWeight={800} gutterBottom>
                  รายละเอียดประกาศ
                </Typography>
                <Typography color="text.secondary" sx={{ lineHeight: 1.8 }}>
                  พื้นที่ติดชุมชน เข้าออกสะดวก เหมาะสำหรับทำการเกษตร/โกดัง
                  มีทางน้ำใกล้เคียง ระบบไฟเข้าถึง
                </Typography>

                <Box
                  sx={{
                    mt: "auto",
                    pt: 3,
                    display: "flex",
                    gap: 1.5,
                    alignItems: "center",
                  }}
                >
                  {isLoggedIn ? (
                    <>
                      <Button
                        variant="contained"
                        size="large"
                        onClick={handleReserve}
                      >
                        จองพื้นที่นี้
                      </Button>
                      <Button variant="outlined" size="large">
                        แชร์ประกาศ
                      </Button>
                    </>
                  ) : (
                    <>
                      <Alert severity="info" sx={{ flex: 1 }}>
                        กรุณาเข้าสู่ระบบเพื่อทำการจองพื้นที่นี้
                      </Alert>
                      <Button variant="outlined" size="large">
                        แชร์ประกาศ
                      </Button>
                    </>
                  )}
                </Box>
              </Paper>
            </Grid>
          </Grid>
        </Paper>
      </Container>
    </>
  );
}
