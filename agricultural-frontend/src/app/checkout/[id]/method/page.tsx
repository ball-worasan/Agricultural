"use client";

import { useEffect, useMemo, useState, useCallback } from "react";
import { useRouter, useParams } from "next/navigation";
import Image from "next/image";

import Header from "@/components/Header";
import { getListingById } from "@/data/listings";

import Container from "@mui/material/Container";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import Radio from "@mui/material/Radio";
import RadioGroup from "@mui/material/RadioGroup";
import FormControlLabel from "@mui/material/FormControlLabel";
import Button from "@mui/material/Button";
import Box from "@mui/material/Box";
import Alert from "@mui/material/Alert";
import Stack from "@mui/material/Stack";
import Divider from "@mui/material/Divider";
import Chip from "@mui/material/Chip";
import IconButton from "@mui/material/IconButton";
import Tooltip from "@mui/material/Tooltip";
import Snackbar from "@mui/material/Snackbar";
import Skeleton from "@mui/material/Skeleton";

import ContentCopyIcon from "@mui/icons-material/ContentCopy";
import InfoOutlinedIcon from "@mui/icons-material/InfoOutlined";
import DownloadIcon from "@mui/icons-material/Download";
import ArrowForwardIcon from "@mui/icons-material/ArrowForward";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import PlaceIcon from "@mui/icons-material/Place";
import HourglassBottomIcon from "@mui/icons-material/HourglassBottom";

/* ==== Types ==== */
interface ReserveDraft {
  listingId: string;
  title: string;
  locationText: string;
  price: number;
  unit: "วัน" | "เดือน" | "ปี";
  image?: string;
  fromDate?: string;
  toDate?: string;
  totalDays?: number;
  totalPrice?: number;
}

/** รูปทรงขั้นต่ำของ listing ที่ UI ใช้จริง เพื่อเลิกใช้ `any` */
interface ListingLite {
  id: string;
  title: string;
  district: string;
  province: string;
  price: number;
  unit?: string;
  image?: string;
  fromDate?: string;
  toDate?: string;
}

/* ==== Utils ==== */
const THB = (n: number) =>
  new Intl.NumberFormat("th-TH", {
    style: "currency",
    currency: "THB",
    maximumFractionDigits: 0,
  }).format(n);

const safeParse = <T,>(raw: string | null): T | null => {
  try {
    return raw ? (JSON.parse(raw) as T) : null;
  } catch {
    return null;
  }
};

const fmtClock = (ms: number) => {
  const s = Math.max(0, Math.floor(ms / 1000));
  const hh = Math.floor(s / 3600)
    .toString()
    .padStart(2, "0");
  const mm = Math.floor((s % 3600) / 60)
    .toString()
    .padStart(2, "0");
  const ss = Math.floor(s % 60)
    .toString()
    .padStart(2, "0");
  return `${hh}:${mm}:${ss}`;
};

const PROMPTPAY_ID = process.env.NEXT_PUBLIC_PROMPTPAY_ID || "0640163043";
const DEADLINE_KEY = "reserveDeadline"; // timestamp ms

export default function PaymentMethod() {
  const router = useRouter();
  const params = useParams();
  const listingId = (params?.id as string) || "";

  const [draft, setDraft] = useState<ReserveDraft | null>(null);
  const [slipPreview, setSlipPreview] = useState<string | null>(null);
  const [snack, setSnack] = useState<{ open: boolean; msg: string }>({
    open: false,
    msg: "",
  });
  const [loading, setLoading] = useState(true);

  // countdown
  const [deadline, setDeadline] = useState<number>(() => {
    const cached = Number(sessionStorage.getItem(DEADLINE_KEY) || 0);
    return Number.isFinite(cached) && cached > Date.now() ? cached : 0;
  });
  const [now, setNow] = useState<number>(Date.now());

  useEffect(() => {
    const t = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(t);
  }, []);

  // init draft + deadline
  useEffect(() => {
    if (!listingId) return;
    setLoading(true);
    const next = () => setLoading(false);

    try {
      const cached = safeParse<ReserveDraft>(
        sessionStorage.getItem("reserveDraft")
      );
      const cachedSlip = sessionStorage.getItem("reserveSlip");

      // deadline (1h per session)
      let dl = Number(sessionStorage.getItem(DEADLINE_KEY) || 0);
      if (!Number.isFinite(dl) || dl <= Date.now()) {
        dl = Date.now() + 60 * 60 * 1000;
        sessionStorage.setItem(DEADLINE_KEY, String(dl));
      }
      setDeadline(dl);

      if (cached && cached.listingId === listingId) {
        setDraft(cached);
        setSlipPreview(cachedSlip || null);
        next();
        return;
      }

      const listing = getListingById(listingId) as ListingLite | undefined;
      if (listing) {
        const normalizedUnit =
          listing.unit === "วัน" ||
          listing.unit === "เดือน" ||
          listing.unit === "ปี"
            ? (listing.unit as ReserveDraft["unit"])
            : "ปี";

        const newDraft: ReserveDraft = {
          listingId: listing.id,
          title: listing.title,
          locationText: `อ.${listing.district} จ.${listing.province}`,
          price: listing.price,
          unit: normalizedUnit,
          image: listing.image,
          fromDate: listing.fromDate ?? undefined,
          toDate: listing.toDate ?? undefined,
        };
        setDraft(newDraft);
        sessionStorage.setItem("reserveDraft", JSON.stringify(newDraft));
        setSlipPreview(cachedSlip || null);
      } else {
        setDraft(null);
      }
    } catch {
      setDraft(null);
    } finally {
      next();
    }
  }, [listingId]);

  // annual amount
  const amount = useMemo(
    () => Math.max(100, Math.round(draft?.price ?? 0)),
    [draft?.price]
  );
  const qrSrc = useMemo(
    // PROMPTPAY_ID เป็นคงที่จากโมดูล ไม่ต้องใส่เป็น deps
    () => `https://promptpay.io/${PROMPTPAY_ID}/${amount}.png`,
    [amount]
  );

  // countdown state
  const remaining = Math.max(0, deadline - now);
  const isExpired = remaining <= 0;

  const copyAmount = useCallback(async () => {
    try {
      await navigator.clipboard.writeText(String(amount));
      setSnack({ open: true, msg: "คัดลอกจำนวนเงินแล้ว" });
    } catch {
      setSnack({ open: true, msg: "คัดลอกไม่ได้ กรุณาทำเอง" });
    }
  }, [amount]);

  const downloadQR = useCallback(() => {
    const link = document.createElement("a");
    link.href = qrSrc;
    link.download = `promptpay-${amount}.png`;
    link.click();
  }, [qrSrc, amount]);

  const handleUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
      const base64 = String(reader.result);
      setSlipPreview(base64);
      sessionStorage.setItem("reserveSlip", base64);
    };
    reader.readAsDataURL(file);
  };

  if (loading) {
    return (
      <>
        <Header />
        <Container maxWidth="md" sx={{ py: { xs: 2, md: 4 } }}>
          <Paper sx={{ p: 2, borderRadius: 3 }}>
            <Skeleton variant="text" width={180} height={36} />
            <Skeleton
              variant="rectangular"
              height={140}
              sx={{ mt: 2, borderRadius: 2 }}
            />
            <Skeleton
              variant="rectangular"
              height={320}
              sx={{ mt: 2, borderRadius: 2 }}
            />
          </Paper>
        </Container>
      </>
    );
  }

  if (!draft) {
    return (
      <>
        <Header />
        <Container maxWidth="sm" sx={{ py: { xs: 4, md: 6 } }}>
          <Alert severity="warning">ไม่พบข้อมูลการจองของรายการนี้</Alert>
          <Stack direction="row" justifyContent="center" mt={2}>
            <Button variant="outlined" onClick={() => router.push("/")}>
              กลับหน้าแรก
            </Button>
          </Stack>
        </Container>
      </>
    );
  }

  /* ✅ ต้องอัปโหลดสลิปก่อนกดยืนยัน */
  const hasSlip = Boolean(slipPreview);
  const canSubmit = amount > 0 && !isExpired && hasSlip;

  return (
    <>
      <Header />

      <Container maxWidth="md" sx={{ py: { xs: 2, md: 4 } }}>
        <Paper
          elevation={0}
          sx={{
            p: { xs: 2, md: 3 },
            border: 1,
            borderColor: "divider",
            borderRadius: 3,
          }}
        >
          {/* Header */}
          <Stack
            direction={{ xs: "column", sm: "row" }}
            alignItems={{ xs: "flex-start", sm: "center" }}
            justifyContent="space-between"
            gap={1}
            sx={{ mb: 2 }}
          >
            <Stack direction="row" alignItems="center" gap={1}>
              <Typography variant="h6" fontWeight={900}>
                ยืนยันการชำระเงิน
              </Typography>
              <Chip
                icon={<InfoOutlinedIcon />}
                label="QR PromptPay"
                color="primary"
                variant="outlined"
                sx={{ fontWeight: 700 }}
              />
            </Stack>
            <Stack direction="row" gap={1} alignItems="center">
              <Chip
                icon={<HourglassBottomIcon />}
                color={isExpired ? "error" : "warning"}
                label={
                  isExpired
                    ? "หมดเวลา กรุณาทำรายการใหม่"
                    : `เหลือเวลา ${fmtClock(remaining)}`
                }
                sx={{ fontWeight: 700 }}
              />
              <Button
                variant="text"
                size="small"
                startIcon={<ArrowBackIcon />}
                onClick={() => router.push(`/checkout/${draft.listingId}`)}
              >
                กลับ
              </Button>
            </Stack>
          </Stack>

          {/* Summary */}
          <Box
            sx={{
              display: "grid",
              gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
              gap: 2.5,
              mb: 3,
            }}
          >
            <Paper variant="outlined" sx={{ p: 2, borderRadius: 2 }}>
              <Typography fontWeight={800} gutterBottom>
                รายการจอง
              </Typography>
              <Stack spacing={0.75} sx={{ color: "text.secondary" }}>
                <Typography sx={{ fontWeight: 700 }}>{draft.title}</Typography>
                <Typography
                  variant="body2"
                  sx={{ display: "flex", alignItems: "center", gap: 0.5 }}
                >
                  <PlaceIcon fontSize="small" /> {draft.locationText}
                </Typography>
                <Typography variant="body2">
                  ระยะเวลา {draft.fromDate || "—"} - {draft.toDate || "—"}
                </Typography>
              </Stack>
            </Paper>

            <Paper variant="outlined" sx={{ p: 2, borderRadius: 2 }}>
              <Typography fontWeight={800} gutterBottom>
                ยอดที่ต้องชำระ
              </Typography>
              <Stack direction="row" alignItems="center" spacing={1}>
                <Typography
                  variant="h4"
                  fontWeight={900}
                  sx={{ lineHeight: 1 }}
                  color="primary"
                >
                  {THB(amount)}
                </Typography>
                <Tooltip title="คัดลอกจำนวนเงิน">
                  <IconButton onClick={copyAmount} size="small">
                    <ContentCopyIcon fontSize="small" />
                  </IconButton>
                </Tooltip>
              </Stack>
              <Typography
                variant="body2"
                sx={{ mt: 0.5, color: "text.secondary" }}
              >
                ราคา {THB(draft.price)}/ปี
              </Typography>
            </Paper>
          </Box>

          <Divider sx={{ my: 2.5 }} />

          {/* Method */}
          <Box sx={{ textAlign: "center", mb: 2 }}>
            <Typography fontWeight={800} sx={{ mb: 1 }}>
              ช่องทางการชำระ
            </Typography>
            <RadioGroup
              row
              defaultValue="promptpay"
              sx={{ display: "inline-flex", gap: 2 }}
            >
              <FormControlLabel
                value="promptpay"
                control={<Radio />}
                label="QR PromptPay"
              />
            </RadioGroup>
          </Box>

          {/* QR */}
          <Box
            sx={{
              display: "grid",
              placeItems: "center",
              textAlign: "center",
              my: 2,
            }}
          >
            <Paper
              variant="outlined"
              sx={{
                p: 2.5,
                borderRadius: 3,
                width: { xs: 280, sm: 320 },
                opacity: isExpired ? 0.5 : 1,
              }}
            >
              <Typography
                variant="body2"
                sx={{ mb: 1, color: "text.secondary" }}
              >
                สแกนเพื่อชำระ (PromptPay: {PROMPTPAY_ID})
              </Typography>
              <Box
                sx={{
                  width: "100%",
                  aspectRatio: "1 / 1",
                  position: "relative",
                  bgcolor: "background.paper",
                  borderRadius: 2,
                  overflow: "hidden",
                }}
              >
                <Image
                  src={qrSrc}
                  alt={`QR PromptPay ${amount} บาท`}
                  fill
                  sizes="(max-width: 600px) 280px, 320px"
                  style={{ objectFit: "contain" }}
                  unoptimized
                />
              </Box>
              <Stack
                direction="row"
                justifyContent="center"
                gap={1}
                sx={{ mt: 1 }}
              >
                <Button
                  size="small"
                  variant="text"
                  startIcon={<DownloadIcon />}
                  onClick={downloadQR}
                  disabled={isExpired}
                >
                  ดาวน์โหลด QR
                </Button>
              </Stack>
              <Typography
                variant="caption"
                sx={{ mt: 0.5, display: "block", color: "text.secondary" }}
              >
                จำนวนเงินถูกฝังใน QR อัตโนมัติ
              </Typography>
            </Paper>
          </Box>

          {/* Upload Slip */}
          <Box sx={{ display: "grid", gap: 1.5, my: 3 }}>
            <Typography fontWeight={800}>อัปโหลดสลิปการโอน</Typography>
            <Stack
              direction={{ xs: "column", sm: "row" }}
              spacing={1}
              alignItems="center"
            >
              <Button variant="outlined" component="label" disabled={isExpired}>
                เลือกสลิปภาพ
                <input
                  type="file"
                  accept="image/*"
                  hidden
                  onChange={handleUpload}
                />
              </Button>
              <Typography variant="body2" sx={{ color: "text.secondary" }}>
                รองรับ JPG/PNG (จำเป็นต้องอัปโหลดก่อนยืนยันการชำระ)
              </Typography>
            </Stack>

            {slipPreview ? (
              <Paper variant="outlined" sx={{ p: 1.5, borderRadius: 2 }}>
                <Typography
                  variant="body2"
                  sx={{ mb: 0.5, color: "text.secondary" }}
                >
                  ตัวอย่างสลิปที่อัปโหลด
                </Typography>
                <Box
                  sx={{
                    position: "relative",
                    width: "100%",
                    maxHeight: 360,
                    aspectRatio: "3 / 4",
                    borderRadius: 2,
                    overflow: "hidden",
                  }}
                >
                  <Image
                    src={slipPreview}
                    alt="สลิปการโอน"
                    fill
                    sizes="(max-width: 600px) 100vw, 600px"
                    style={{ objectFit: "contain" }}
                    unoptimized
                  />
                </Box>
              </Paper>
            ) : (
              <Alert severity="warning">
                กรุณาอัปโหลดสลิปการโอนก่อนกดยืนยัน
              </Alert>
            )}
          </Box>

          {isExpired && (
            <Alert severity="error" sx={{ mb: 2 }}>
              หมดเวลาชำระเงิน (1 ชั่วโมง) — กรุณากลับไปเริ่มขั้นตอนใหม่
            </Alert>
          )}

          {/* Desktop actions */}
          <Stack
            direction="row"
            justifyContent="center"
            spacing={1}
            sx={{ display: { xs: "none", md: "flex" } }}
          >
            <Button
              variant="outlined"
              onClick={() => router.push(`/checkout/${draft.listingId}`)}
            >
              กลับ
            </Button>
            <Button
              variant="contained"
              disabled={!canSubmit}
              endIcon={<ArrowForwardIcon />}
              onClick={() => router.push(`/reserve/${draft.listingId}/status`)}
            >
              ชำระแล้ว
            </Button>
          </Stack>
        </Paper>

        {/* Spacer for sticky bar on mobile */}
        <Box sx={{ height: 92, display: { xs: "block", md: "none" } }} />
      </Container>

      {/* Sticky CTA (mobile) */}
      {draft && (
        <Paper
          elevation={8}
          sx={{
            position: "fixed",
            left: 0,
            right: 0,
            bottom: 0,
            zIndex: (t) => t.zIndex.appBar + 1,
            borderTopLeftRadius: 16,
            borderTopRightRadius: 16,
            display: { xs: "block", md: "none" },
            p: 1.5,
            backdropFilter: "saturate(1.2) blur(6px)",
            opacity: isExpired ? 0.85 : 1,
          }}
        >
          <Stack
            direction="row"
            alignItems="center"
            justifyContent="space-between"
            gap={1}
          >
            <Box>
              <Typography
                fontWeight={900}
                sx={{ lineHeight: 1 }}
                color="primary.main"
              >
                {THB(amount)}
              </Typography>
              <Typography
                variant="caption"
                sx={{ color: isExpired ? "error.main" : "text.secondary" }}
              >
                {isExpired ? "หมดเวลา" : `เหลือ ${fmtClock(remaining)}`}
              </Typography>
            </Box>
            <Stack direction="row" gap={1}>
              <Button
                variant="outlined"
                size="large"
                onClick={() => router.push(`/checkout/${draft.listingId}`)}
              >
                กลับ
              </Button>
              <Button
                variant="contained"
                size="large"
                disabled={!canSubmit}
                endIcon={<ArrowForwardIcon />}
                onClick={() =>
                  router.push(`/reserve/${draft.listingId}/status`)
                }
              >
                ชำระแล้ว
              </Button>
            </Stack>
          </Stack>
        </Paper>
      )}

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
