"use client";

import { useEffect, useMemo, useState } from "react";
import Header from "@/components/Header";

import Container from "@mui/material/Container";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import Grid from "@mui/material/Grid";
import Box from "@mui/material/Box";
import Alert from "@mui/material/Alert";
import Button from "@mui/material/Button";
import Divider from "@mui/material/Divider";
import Chip from "@mui/material/Chip";
import Skeleton from "@mui/material/Skeleton";
import Stack from "@mui/material/Stack";
import Tooltip from "@mui/material/Tooltip";

import PlaceIcon from "@mui/icons-material/Place";
import CalendarMonthIcon from "@mui/icons-material/CalendarMonth";
import AttachMoneyIcon from "@mui/icons-material/AttachMoney";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import ImageIcon from "@mui/icons-material/Image";
import CloseIcon from "@mui/icons-material/Close";

import dayjs from "dayjs";
import { useRouter } from "next/navigation";

// ------- Types -------
type ReserveDraft = {
  listingId: string;
  title: string;
  locationText: string;
  price: number;
  unit: "วัน" | "เดือน" | "ปี";
  image?: string;
  fromDate?: string; // YYYY-MM-DD
  toDate?: string; // YYYY-MM-DD
  totalDays?: number;
};

// ------- Utils -------
const THB = (n: number) =>
  new Intl.NumberFormat("th-TH", {
    style: "currency",
    currency: "THB",
    maximumFractionDigits: 0,
  }).format(n);

const daysBetween = (from?: string, to?: string): number | null => {
  if (!from || !to) return null;
  const a = new Date(from + "T00:00:00");
  const b = new Date(to + "T00:00:00");
  const diff =
    Math.round((b.getTime() - a.getTime()) / (24 * 60 * 60 * 1000)) + 1; // รวมวันแรก
  return diff > 0 ? diff : null;
};

export default function ReserveStatusCard() {
  const router = useRouter();
  const [loading, setLoading] = useState(true);
  const [draft, setDraft] = useState<ReserveDraft | null>(null);
  const [slip, setSlip] = useState<string | null>(null);

  useEffect(() => {
    try {
      const raw = sessionStorage.getItem("reserveDraft");
      const parsed = raw ? (JSON.parse(raw) as ReserveDraft) : null;
      setDraft(parsed);
      setSlip(sessionStorage.getItem("reserveSlip"));
    } catch {
      setDraft(null);
      setSlip(null);
    } finally {
      setLoading(false);
    }
  }, []);

  const derivedDays = useMemo(() => {
    return (
      draft?.totalDays ??
      daysBetween(draft?.fromDate, draft?.toDate) ??
      undefined
    );
  }, [draft?.totalDays, draft?.fromDate, draft?.toDate]);

  const dateText = useMemo(() => {
    if (!draft?.fromDate || !draft?.toDate || !derivedDays) return null;
    const from = dayjs(draft.fromDate).format("DD/MM/YYYY");
    const to = dayjs(draft.toDate).format("DD/MM/YYYY");
    return `ช่วงที่จอง: ${from} ถึง ${to} • รวม ${derivedDays} วัน`;
  }, [draft?.fromDate, draft?.toDate, derivedDays]);

  const totalPrice = useMemo(() => {
    if (!draft?.price || !derivedDays) return undefined;
    return draft.price * derivedDays;
  }, [draft?.price, derivedDays]);

  const clearDraft = () => {
    sessionStorage.removeItem("reserveDraft");
    sessionStorage.removeItem("reserveSlip");
    setDraft(null);
    setSlip(null);
  };

  if (loading) {
    return (
      <>
        <Header />
        <Container maxWidth="lg" sx={{ py: { xs: 4, md: 6 } }}>
          <Paper sx={{ p: { xs: 2, md: 3 }, borderRadius: 3 }}>
            <Skeleton variant="text" width={240} height={36} />
            <Grid container spacing={3} sx={{ mt: 1 }}>
              <Grid size={{ xs: 12, md: 6 }}>
                <Skeleton
                  variant="rectangular"
                  height={260}
                  sx={{ borderRadius: 2 }}
                />
              </Grid>
              <Grid size={{ xs: 12, md: 6 }}>
                <Skeleton
                  variant="rectangular"
                  height={220}
                  sx={{ borderRadius: 2 }}
                />
              </Grid>
            </Grid>
          </Paper>
        </Container>
      </>
    );
  }

  return (
    <>
      <Header />
      <Container maxWidth="lg" sx={{ py: { xs: 4, md: 6 } }}>
        {!draft ? (
          <Alert severity="warning">ไม่พบข้อมูลการจองล่าสุด</Alert>
        ) : (
          <Grid container spacing={3}>
            {/* Left: Preview */}
            <Grid size={{ xs: 12, md: 6 }}>
              <Paper
                variant="outlined"
                sx={{ borderRadius: 2, overflow: "hidden" }}
              >
                <Box
                  sx={{
                    position: "relative",
                    width: "100%",
                    pt: "56.25%",
                    bgcolor: "rgba(0,0,0,.04)",
                  }}
                >
                  {draft.image ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                      src={draft.image}
                      alt={draft.title}
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
                      <Stack direction="row" gap={1} alignItems="center">
                        <ImageIcon />
                        ไม่มีรูปภาพ
                      </Stack>
                    </Box>
                  )}
                </Box>
              </Paper>

              <Paper variant="outlined" sx={{ mt: 2, p: 2, borderRadius: 2 }}>
                <Typography fontWeight={900}>{draft.title}</Typography>
                <Stack
                  direction="row"
                  spacing={2}
                  sx={{ mt: 0.75, color: "text.secondary" }}
                >
                  <Stack direction="row" gap={0.5} alignItems="center">
                    <PlaceIcon fontSize="small" />
                    <Typography variant="body2">
                      {draft.locationText}
                    </Typography>
                  </Stack>
                  <Divider
                    orientation="vertical"
                    flexItem
                    sx={{ display: { xs: "none", sm: "block" } }}
                  />
                  <Stack direction="row" gap={0.5} alignItems="center">
                    <AttachMoneyIcon fontSize="small" />
                    <Typography variant="body2" color="primary">
                      {draft.price.toLocaleString("th-TH")}/{draft.unit}
                    </Typography>
                  </Stack>
                </Stack>
              </Paper>
            </Grid>

            {/* Right: Status card */}
            <Grid size={{ xs: 12, md: 6 }}>
              <Paper
                variant="outlined"
                sx={{
                  p: { xs: 2, md: 3 },
                  borderRadius: 2,
                  display: "grid",
                  gap: 2,
                }}
              >
                <Stack
                  direction="row"
                  alignItems="center"
                  justifyContent="space-between"
                >
                  <Typography variant="h6" fontWeight={900}>
                    สถานะการจองพื้นที่
                  </Typography>
                  <Stack direction="row" gap={1} alignItems="center">
                    <Chip
                      icon={<CheckCircleIcon />}
                      color="success"
                      label="Draft พร้อมส่ง"
                      variant="outlined"
                      sx={{ fontWeight: 700 }}
                    />
                    <Tooltip title="ปิดหน้าต่าง">
                      <Button
                        size="small"
                        variant="outlined"
                        color="inherit"
                        onClick={() => router.push("/")}
                        sx={{ minWidth: "auto", p: 0.75 }}
                      >
                        <CloseIcon fontSize="small" />
                      </Button>
                    </Tooltip>
                  </Stack>
                </Stack>

                {dateText ? (
                  <Alert severity="success" icon={<CalendarMonthIcon />}>
                    {dateText}
                  </Alert>
                ) : (
                  <Alert severity="info">กำลังรอเลือกช่วงวันที่</Alert>
                )}

                {/* Summary price */}
                <Paper variant="outlined" sx={{ p: 2, borderRadius: 2 }}>
                  <Typography fontWeight={800} gutterBottom>
                    สรุปยอด
                  </Typography>
                  <Stack direction="row" alignItems="baseline" gap={1}>
                    <Typography variant="h4" fontWeight={900} color="primary">
                      {totalPrice ? THB(totalPrice) : "—"}
                    </Typography>
                  </Stack>
                </Paper>

                {/* Slip preview (if any) */}
                {slip && (
                  <Box>
                    <Typography fontWeight={800} gutterBottom>
                      สลิปการชำระเงิน
                    </Typography>
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img
                      src={slip}
                      alt="สลิปการชำระเงิน"
                      style={{
                        width: "100%",
                        maxHeight: 360,
                        objectFit: "contain",
                        borderRadius: 8,
                      }}
                    />
                  </Box>
                )}

                <Divider sx={{ my: 1 }} />

                <Stack
                  direction={{ xs: "column", sm: "row" }}
                  gap={1}
                  justifyContent="space-between"
                >
                  <Button color="error" variant="text" onClick={clearDraft}>
                    ล้างข้อมูลการจอง
                  </Button>
                </Stack>
              </Paper>
            </Grid>
          </Grid>
        )}
      </Container>
    </>
  );
}
