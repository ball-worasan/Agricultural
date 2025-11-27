"use client";

import { useEffect, useMemo, useState, useCallback } from "react";
import { useRouter, useParams } from "next/navigation";

import Grid from "@mui/material/Grid";
import Container from "@mui/material/Container";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Paper from "@mui/material/Paper";
import Button from "@mui/material/Button";
import Chip from "@mui/material/Chip";
import Alert from "@mui/material/Alert";
import Breadcrumbs from "@mui/material/Breadcrumbs";
import Link from "@mui/material/Link";
import Snackbar from "@mui/material/Snackbar";
import Divider from "@mui/material/Divider";
import Tooltip from "@mui/material/Tooltip";
import Skeleton from "@mui/material/Skeleton";
import IconButton from "@mui/material/IconButton";

import Place from "@mui/icons-material/Place";
import CalendarMonth from "@mui/icons-material/CalendarMonth";
import Share from "@mui/icons-material/Share";
import ArrowForward from "@mui/icons-material/ArrowForward";
import Photo from "@mui/icons-material/Photo";
import ZoomIn from "@mui/icons-material/ZoomIn";
import CheckCircle from "@mui/icons-material/CheckCircle";
import HourglassBottom from "@mui/icons-material/HourglassBottom";
import LocalOfferIcon from "@mui/icons-material/LocalOffer";

import Header from "@/components/Header";
import { getListingById, type Listing } from "@/data/listings";

/* ===== Helpers (เหมือน Header) ===== */
type UserLite = { username?: string; email?: string; fullname?: string };

const readCookie = (name: string): string | null => {
  const m = document.cookie.match(
    new RegExp(String.raw`(?:^|;\s*)${name}=([^;]+)`) // ปลอดภัยกับชื่อ cookie
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

const formatTHB = (n: number) =>
  new Intl.NumberFormat("th-TH", {
    style: "currency",
    currency: "THB",
    maximumFractionDigits: 0,
  }).format(n);

const formatThaiDate = (iso: string | number | Date) =>
  new Intl.DateTimeFormat("th-TH", { dateStyle: "long" }).format(new Date(iso));

// แท็กสถานะ
const getStatusChip = (status: "available" | "reserved") => {
  if (status === "available")
    return {
      label: "ว่าง",
      color: "success" as const,
      icon: <CheckCircle fontSize="small" />,
    };
  return {
    label: "ติดจอง",
    color: "warning" as const,
    icon: <HourglassBottom fontSize="small" />,
  };
};

export default function ListingDetail() {
  const router = useRouter();
  const params = useParams();
  const listingId = params.id as string;

  // ดึงข้อมูลจาก cache หรือ listings.ts
  const [listing, setListing] = useState<Listing | null>(null);

  useEffect(() => {
    if (!listingId) return;

    // ตรวจสอบ cache ก่อน
    const cacheKey = `listing_${listingId}`;
    const cached = sessionStorage.getItem(cacheKey);

    if (cached) {
      try {
        setListing(JSON.parse(cached));
        return;
      } catch {
        // cache เสีย ลบทิ้ง
        sessionStorage.removeItem(cacheKey);
      }
    }

    // ไม่มี cache หรือ cache เสีย ดึงจาก listings.ts
    const data = getListingById(listingId);
    if (data) {
      setListing(data);
      // เก็บลง cache
      sessionStorage.setItem(cacheKey, JSON.stringify(data));
    }
  }, [listingId]);

  // ภาพหลักที่เลือก
  const [currentIdx, setCurrentIdx] = useState(0);
  const [imgLoaded, setImgLoaded] = useState(false);

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
    if (!listing) return;
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
  }, [listing]);

  // ---- ส่ง draft แล้วไปหน้า inline ----
  const handleReserve = useCallback(() => {
    if (!listing) return;
    const draft = {
      listingId: listing.id,
      title: listing.title,
      locationText: `อ.${listing.district} จ.${listing.province}`,
      price: listing.price,
      unit: listing.unit as "วัน" | "เดือน" | "ปี",
      image: listing.image ?? "",
    };
    sessionStorage.setItem("reserveDraft", JSON.stringify(draft));
    router.push(`/reserve/${listing.id}/inline`);
  }, [listing, router]);

  // Loading state
  if (!listing) {
    return (
      <>
        <Header />
        <Container maxWidth="lg" sx={{ py: { xs: 3, md: 5 } }}>
          <Typography>กำลังโหลด...</Typography>
        </Container>
      </>
    );
  }

  const status = getStatusChip(listing.status);
  const images = listing.image ? [listing.image] : [];

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
          <Link color="inherit" href="/">
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
              sx={{ letterSpacing: "-0.3px", lineHeight: 1.22 }}
            >
              {listing.title}
            </Typography>
          </Box>

          <Stack
            direction="row"
            gap={2}
            alignItems="center"
            flexWrap="wrap"
            sx={{ color: "text.secondary", mb: 2 }}
          >
            <Stack direction="row" alignItems="center" gap={0.5}>
              <Place fontSize="small" />
              <Typography variant="body2">
                อ.{listing.district} จ.{listing.province}
              </Typography>
            </Stack>
            <Divider
              orientation="vertical"
              flexItem
              sx={{ display: { xs: "none", sm: "block" } }}
            />
            <Stack direction="row" alignItems="center" gap={0.5}>
              <CalendarMonth fontSize="small" />
              <Typography variant="body2">
                ลงประกาศ : {formatThaiDate(listing.postedAt)}
              </Typography>
            </Stack>
            <Chip
              size="small"
              color={status.color}
              icon={status.icon}
              label={status.label}
              sx={{ ml: { xs: 0, sm: 1 } }}
            />
          </Stack>

          {/* แสดง Tags ถ้ามี */}
          {listing.tags && listing.tags.length > 0 && (
            <Stack direction="row" gap={1} sx={{ mb: 2, flexWrap: "wrap" }}>
              {listing.tags.map((tag) => (
                <Chip
                  key={tag}
                  icon={<LocalOfferIcon />}
                  label={tag}
                  size="small"
                  variant="outlined"
                  color="primary"
                />
              ))}
            </Stack>
          )}

          <Grid container spacing={{ xs: 2, md: 3 }}>
            {/* ซ้าย: รูป/แกลเลอรี */}
            <Grid size={{ xs: 12, md: 7 }}>
              <Paper
                variant="outlined"
                sx={{
                  borderRadius: 2,
                  overflow: "hidden",
                  p: { xs: 1.5, md: 2 },
                }}
              >
                <Box
                  sx={{
                    position: "relative",
                    width: "100%",
                    pt: "56.25%",
                    bgcolor: "rgba(0,0,0,.04)",
                    borderRadius: 1.5,
                  }}
                >
                  {images.length > 0 ? (
                    <>
                      {!imgLoaded && (
                        <Skeleton
                          variant="rectangular"
                          sx={{ position: "absolute", inset: 0 }}
                        />
                      )}
                      {/* eslint-disable-next-line @next/next/no-img-element */}
                      <img
                        src={images[currentIdx]}
                        alt={`${listing.title} - รูปที่ ${currentIdx + 1}`}
                        onLoad={() => setImgLoaded(true)}
                        style={{
                          position: "absolute",
                          inset: 0,
                          width: "100%",
                          height: "100%",
                          objectFit: "cover",
                        }}
                      />
                      <Tooltip title="ขยายดูรูป">
                        <IconButton
                          aria-label="ขยายดูรูป"
                          size="small"
                          sx={{
                            position: "absolute",
                            right: 8,
                            bottom: 8,
                            bgcolor: "background.paper",
                          }}
                          onClick={() =>
                            window.open(images[currentIdx], "_blank")
                          }
                        >
                          <ZoomIn />
                        </IconButton>
                      </Tooltip>
                    </>
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
                      <Stack direction="row" alignItems="center" gap={1}>
                        <Photo />
                        <Typography>ไม่มีรูปภาพ</Typography>
                      </Stack>
                    </Box>
                  )}
                </Box>

                {/* แถบรูปย่อย */}
                {images.length > 1 && (
                  <Stack
                    direction="row"
                    gap={1}
                    mt={1.5}
                    sx={{ overflowX: "auto", pb: 0.5 }}
                  >
                    {images.map((src: string, idx: number) => (
                      <Box
                        key={src + idx}
                        onClick={() => {
                          setCurrentIdx(idx);
                          setImgLoaded(false);
                        }}
                        sx={{
                          width: 84,
                          height: 56,
                          flex: "0 0 auto",
                          borderRadius: 1,
                          border: 1,
                          borderColor:
                            idx === currentIdx ? "primary.main" : "divider",
                          overflow: "hidden",
                          cursor: "pointer",
                        }}
                      >
                        {/* eslint-disable-next-line @next/next/no-img-element */}
                        <img
                          src={src}
                          alt={`thumb-${idx + 1}`}
                          style={{
                            width: "100%",
                            height: "100%",
                            objectFit: "cover",
                          }}
                        />
                      </Box>
                    ))}
                  </Stack>
                )}
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
                <Stack
                  direction="row"
                  alignItems="center"
                  justifyContent="space-between"
                  mb={0.5}
                >
                  <Typography variant="h5" fontWeight={900} color="primary">
                    {formatTHB(listing.price)}/{listing.unit}
                  </Typography>
                </Stack>

                <Divider sx={{ my: 1 }} />

                <Typography variant="h6" fontWeight={800}>
                  รายละเอียดประกาศ
                </Typography>
                <Typography color="text.secondary" sx={{ lineHeight: 1.85 }}>
                  {listing.description ||
                    "พื้นที่ติดชุมชน เข้าออกสะดวก เหมาะสำหรับทำการเกษตร/โกดัง มีทางน้ำใกล้เคียง ระบบไฟเข้าถึง"}
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
