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
import Breadcrumbs from "@mui/material/Breadcrumbs";
import Link from "@mui/material/Link";
import Snackbar from "@mui/material/Snackbar";
import Divider from "@mui/material/Divider";
import Place from "@mui/icons-material/Place";
import CalendarMonth from "@mui/icons-material/CalendarMonth";
import Share from "@mui/icons-material/Share";
import ArrowForward from "@mui/icons-material/ArrowForward";
import Photo from "@mui/icons-material/Photo";

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
    title: "ปล่อยเช่าที่ดินเปล่า ต.ภูสิงห์ อ.สหัสขันธ์ ติดเขื่อนลำปาว",
    locationText: "อ.สหัสขันธ์ จ.กาฬสินธุ์",
    postedAt: "2025-09-08",
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

  // ---- แชร์ลิงก์ ----
  const [snack, setSnack] = useState<{ open: boolean; msg: string }>({
    open: false,
    msg: "",
  });
  const shareLink = useCallback(async () => {
    const url = typeof window !== "undefined" ? window.location.href : "";
    try {
      if (navigator.share) {
        await navigator.share({ title: listing.title, url });
      } else {
        await navigator.clipboard.writeText(url);
        setSnack({ open: true, msg: "คัดลอกลิงก์แล้ว" });
      }
    } catch {
      setSnack({ open: true, msg: "ไม่สามารถแชร์ได้" });
    }
  }, [listing.title]);

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

  const statusColor = listing.status === "available" ? "success" : "warning";
  const statusLabel = listing.status === "available" ? "ว่าง" : "ติดจอง";

  return (
    <>
      <Header />

      <Container maxWidth="lg" sx={{ py: { xs: 3, md: 5 } }}>
        {/* Breadcrumbs */}
        <Breadcrumbs
          aria-label="breadcrumb"
          sx={{ mb: { xs: 1.5, md: 2 }, color: "text.secondary" }}
        >
          <Link color="inherit" href="/">
            หน้าแรก
          </Link>
          <Link color="inherit" href="/reserve/list">
            รายการพื้นที่เช่า
          </Link>
          <Typography color="text.primary" sx={{ fontWeight: 700 }}>
            รายละเอียด
          </Typography>
        </Breadcrumbs>

        <Paper
          elevation={0}
          sx={{
            p: { xs: 2, md: 3 },
            border: 1,
            borderColor: "divider",
            borderRadius: 3,
          }}
        >
          {/* หัวเรื่อง */}
          <Box sx={{ mb: 1 }}>
            <Typography
              variant="h5"
              fontWeight={900}
              sx={{
                letterSpacing: "-0.3px",
                lineHeight: 1.22,
              }}
            >
              {listing.title}
            </Typography>
          </Box>

          <Box
            sx={{
              display: "flex",
              gap: 2,
              alignItems: "center",
              color: "text.secondary",
              flexWrap: "wrap",
              mb: 2,
            }}
          >
            <Box
              sx={{ display: "inline-flex", alignItems: "center", gap: 0.5 }}
            >
              <Place fontSize="small" />
              <Typography variant="body2">{listing.locationText}</Typography>
            </Box>
            <Divider
              orientation="vertical"
              flexItem
              sx={{ display: { xs: "none", sm: "block" } }}
            />
            <Box
              sx={{ display: "inline-flex", alignItems: "center", gap: 0.5 }}
            >
              <CalendarMonth fontSize="small" />
              <Typography variant="body2">
                ลงประกาศ :{" "}
                {new Date(listing.postedAt).toLocaleDateString("th-TH")}
              </Typography>
            </Box>
          </Box>

          <Grid container spacing={{ xs: 2, md: 3 }}>
            {/* ซ้าย: รูป */}
            <Grid size={{ xs: 12, md: 7 }}>
              <Paper
                variant="outlined"
                sx={{
                  borderRadius: 2,
                  overflow: "hidden",
                }}
              >
                <Box
                  sx={{
                    position: "relative",
                    width: "100%",
                    // อัตราส่วน 16:9
                    pt: "56.25%",
                    bgcolor: "rgba(0,0,0,.04)",
                  }}
                >
                  {listing.image ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                      src={listing.image}
                      alt={listing.title}
                      style={{
                        position: "absolute",
                        inset: 0,
                        width: "100%",
                        height: "100%",
                        objectFit: "cover",
                      }}
                    />
                  ) : (
                    <Box
                      sx={{
                        position: "absolute",
                        inset: 0,
                        display: "grid",
                        placeItems: "center",
                        color: "text.secondary",
                        border: "1px dashed rgba(0,0,0,.15)",
                      }}
                    >
                      <Box
                        sx={{ display: "flex", alignItems: "center", gap: 1 }}
                      >
                        <Photo />
                        ไม่มีรูปภาพ
                      </Box>
                    </Box>
                  )}
                </Box>
              </Paper>
            </Grid>

            {/* ขวา: รายละเอียด & ราคา */}
            <Grid size={{ xs: 12, md: 5 }}>
              <Paper
                variant="outlined"
                sx={{
                  p: { xs: 2, md: 3 },
                  borderRadius: 2,
                  height: "100%",
                  display: "flex",
                  flexDirection: "column",
                  gap: 1.25,
                }}
              >
                <Box
                  sx={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    mb: 0.5,
                  }}
                >
                  <Typography variant="h5" fontWeight={900} color="primary">
                    {listing.price.toLocaleString("th-TH")}/{listing.unit}
                  </Typography>
                  <Chip
                    label={statusLabel}
                    color={statusColor as any}
                    variant="outlined"
                    sx={{ fontWeight: 700 }}
                  />
                </Box>

                <Divider sx={{ my: 1 }} />

                <Typography variant="h6" fontWeight={800}>
                  รายละเอียดประกาศ
                </Typography>
                <Typography color="text.secondary" sx={{ lineHeight: 1.85 }}>
                  พื้นที่ติดชุมชน เข้าออกสะดวก เหมาะสำหรับทำการเกษตร/โกดัง
                  มีทางน้ำใกล้เคียง ระบบไฟเข้าถึง
                </Typography>

                <Box sx={{ mt: "auto", pt: 2, display: "flex", gap: 1 }}>
                  {isLoggedIn ? (
                    <>
                      <Button
                        variant="contained"
                        size="large"
                        onClick={handleReserve}
                        endIcon={<ArrowForward />}
                        sx={{ flex: 1 }}
                      >
                        จองพื้นที่นี้
                      </Button>
                      <Button
                        variant="outlined"
                        size="large"
                        onClick={shareLink}
                        startIcon={<Share />}
                      >
                        แชร์
                      </Button>
                    </>
                  ) : (
                    <>
                      <Alert severity="info" sx={{ flex: 1 }}>
                        กรุณาเข้าสู่ระบบเพื่อทำการจองพื้นที่นี้
                      </Alert>
                      <Button
                        variant="outlined"
                        size="large"
                        onClick={shareLink}
                        startIcon={<Share />}
                      >
                        แชร์
                      </Button>
                    </>
                  )}
                </Box>
              </Paper>
            </Grid>
          </Grid>
        </Paper>
      </Container>

      <Snackbar
        open={snack.open}
        autoHideDuration={2000}
        onClose={() => setSnack((s) => ({ ...s, open: false }))}
        message={snack.msg}
        anchorOrigin={{ vertical: "bottom", horizontal: "center" }}
      />
    </>
  );
}
