"use client";

import { useEffect, useMemo, useState, useCallback } from "react";
import { useRouter, useParams } from "next/navigation";

import Header from "@/components/Header";
import { getListingById } from "@/data/listings";
import Container from "@mui/material/Container";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Alert from "@mui/material/Alert";
import Link from "next/link";
import TextField from "@mui/material/TextField";
import Snackbar from "@mui/material/Snackbar";
import Grid from "@mui/material/Grid";

import { LocalizationProvider } from "@mui/x-date-pickers/LocalizationProvider";
import { AdapterDayjs } from "@mui/x-date-pickers/AdapterDayjs";
import { DatePicker } from "@mui/x-date-pickers/DatePicker";
import dayjs, { Dayjs } from "dayjs";

import Place from "@mui/icons-material/Place";
import Photo from "@mui/icons-material/Photo";
import ArrowForward from "@mui/icons-material/ArrowForward";

/* helpers */
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

type ReserveDraft = {
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
  visitDate?: string;
};

const formatThaiDate = (d: dayjs.Dayjs) => d.format("DD/MM/YYYY");

export default function ReserveVisit() {
  const router = useRouter();
  const params = useParams();
  const listingId = params.id as string;
  const tomorrow = dayjs().startOf("day").add(1, "day");

  const [draft, setDraft] = useState<ReserveDraft | null>(null);
  const [user, setUser] = useState<UserLite | null>(null);
  const displayName = useMemo(
    () => user?.fullname || user?.username || user?.email || "ผู้ใช้",
    [user]
  );

  // เลือกได้ 1 วัน (ตั้งแต่พรุ่งนี้ขึ้นไป)
  const [visitDate, setVisitDate] = useState<Dayjs | null>(null);
  const [errorText, setErrorText] = useState("");
  const [snack, setSnack] = useState<{ open: boolean; msg: string }>({
    open: false,
    msg: "",
  });

  useEffect(() => {
    if (!listingId) return;

    try {
      const raw = sessionStorage.getItem("reserveDraft");
      if (raw) {
        setDraft(JSON.parse(raw) as ReserveDraft);
      } else {
        // ถ้าไม่มี draft ให้สร้างจาก listings.ts
        const listing = getListingById(listingId);
        if (listing) {
          const newDraft: ReserveDraft = {
            listingId: listing.id,
            title: listing.title,
            locationText: `อ.${listing.district} จ.${listing.province}`,
            price: listing.price,
            unit: listing.unit as "วัน" | "เดือน" | "ปี",
            image: listing.image,
          };
          setDraft(newDraft);
          sessionStorage.setItem("reserveDraft", JSON.stringify(newDraft));
        }
      }
    } catch {
      setDraft(null);
    }
    setUser(readUserCookie());
  }, [listingId]);

  useEffect(() => {
    if (!visitDate) {
      setErrorText("");
      return;
    }
    if (visitDate.isBefore(tomorrow, "day")) {
      setErrorText("ต้องเลือกตั้งแต่วันพรุ่งนี้ขึ้นไป");
      return;
    }
    setErrorText("");
  }, [visitDate, tomorrow]);

  const canSubmit = !!draft && !!visitDate && !errorText;

  const handleConfirm = useCallback(() => {
    if (!draft || !visitDate) return;

    const from = visitDate.startOf("day");
    const to = visitDate.endOf("day");

    const updated: ReserveDraft = {
      ...draft,
      visitDate: visitDate.format("YYYY-MM-DD"),
      fromDate: from.format("YYYY-MM-DD"),
      toDate: to.format("YYYY-MM-DD"),
      totalDays: 1,
      totalPrice: 1,
    };

    sessionStorage.setItem("reserveDraft", JSON.stringify(updated));
    router.push(`/checkout/${draft.listingId}/method`);
  }, [draft, visitDate, router]);

  // ให้ Enter คอนเฟิร์มได้เมื่อพร้อมส่ง (บนเดสก์ท็อป)
  const onKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === "Enter" && canSubmit) {
      e.preventDefault();
      handleConfirm();
    }
  };

  return (
    <>
      <Header />

      <Container maxWidth="lg" sx={{ py: { xs: 2, md: 4 } }}>
        <Paper sx={{ p: { xs: 2, md: 4 }, borderRadius: 3 }}>
          <Typography variant="h6" fontWeight={900} gutterBottom>
            นัดวันเข้าชมสถานที่ (เลือกได้ 1 วัน)
          </Typography>

          {!draft ? (
            <Alert severity="warning">
              ไม่พบข้อมูลรายการที่จะนัดชม —{" "}
              <Link href="/">ย้อนกลับหน้าแรก</Link>
            </Alert>
          ) : (
            <Grid container spacing={{ xs: 2, md: 3 }} onKeyDown={onKeyDown}>
              {/* ซ้าย: ภาพ + ข้อมูลสรุป */}
              <Grid size={{ xs: 12, md: 7 }}>
                <Paper
                  variant="outlined"
                  sx={{ p: { xs: 1.5, md: 2 }, borderRadius: 2 }}
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
                        <Photo /> ไม่มีรูปภาพ
                      </Box>
                    )}
                  </Box>

                  <Box sx={{ mt: 2, color: "text.secondary" }}>
                    <Typography fontWeight={800}>{draft.title}</Typography>
                    <Typography
                      variant="body2"
                      sx={{ display: "flex", alignItems: "center", gap: 0.5 }}
                    >
                      <Place fontSize="small" /> {draft.locationText}
                    </Typography>
                    <Typography variant="body2" color="primary">
                      {draft.price.toLocaleString("th-TH")}/{draft.unit}
                    </Typography>
                  </Box>
                </Paper>
              </Grid>

              {/* ขวา: ฟอร์มเลือกวัน */}
              <Grid size={{ xs: 12, md: 5 }}>
                <Paper
                  variant="outlined"
                  sx={{
                    p: { xs: 2, md: 3 },
                    borderRadius: 2,
                    display: "grid",
                    gap: 2,
                  }}
                >
                  <Typography fontWeight={800}>รายละเอียดการนัดชม</Typography>

                  <TextField
                    label="ผู้ติดต่อ"
                    value={displayName}
                    disabled
                    fullWidth
                  />

                  <LocalizationProvider dateAdapter={AdapterDayjs}>
                    <DatePicker
                      label="วันที่ต้องการเข้าชม"
                      value={visitDate}
                      onChange={(v: Dayjs | null) => setVisitDate(v)}
                      minDate={tomorrow}
                      slotProps={{ textField: { fullWidth: true } }}
                    />
                  </LocalizationProvider>

                  {errorText ? (
                    <Alert severity="warning">{errorText}</Alert>
                  ) : (
                    <Alert severity={visitDate ? "success" : "info"}>
                      {visitDate
                        ? `วันนัดชม: ${formatThaiDate(visitDate)}`
                        : "โปรดเลือกวันที่ต้องการนัดชม (ตั้งแต่พรุ่งนี้ขึ้นไป)"}
                    </Alert>
                  )}

                  <TextField
                    label="หมายเหตุเพิ่มเติม"
                    multiline
                    minRows={3}
                    placeholder="ระบุเวลาที่สะดวก ฯลฯ"
                  />

                  {/* ปุ่มสำหรับเดสก์ท็อป/แท็บเล็ต */}
                  <Box
                    sx={{
                      display: { xs: "none", md: "flex" },
                      gap: 1,
                      justifyContent: "flex-end",
                      mt: 1,
                    }}
                  >
                    <Button
                      variant="contained"
                      disabled={!canSubmit}
                      endIcon={<ArrowForward />}
                      onClick={handleConfirm}
                    >
                      ยืนยันนัด
                    </Button>
                    <Button
                      variant="outlined"
                      onClick={() => {
                        sessionStorage.removeItem("reserveDraft");
                        history.back();
                      }}
                    >
                      ยกเลิก
                    </Button>
                  </Box>
                </Paper>
              </Grid>

              {/* Spacer กันชนให้ sticky bar ไม่ทับเนื้อหา */}
              <Grid
                size={{ xs: 12, md: 0 }}
                sx={{ display: { xs: "block", md: "none" } }}
              >
                <Box sx={{ height: 84 }} />
              </Grid>
            </Grid>
          )}
        </Paper>
      </Container>

      {/* Sticky CTA เฉพาะมือถือ */}
      {draft && (
        <Paper
          elevation={8}
          sx={{
            position: "fixed",
            left: 0,
            right: 0,
            bottom: 0,
            zIndex: (t) => t.zIndex.appBar + 1,
            borderTopLeftRadius: 12,
            borderTopRightRadius: 12,
            display: { xs: "block", md: "none" },
            p: 1.5,
            backdropFilter: "saturate(1.2) blur(6px)",
          }}
        >
          <Box
            sx={{
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between",
              gap: 1,
            }}
          >
            <Box>
              <Typography fontWeight={800} sx={{ lineHeight: 1 }}>
                นัดชม
              </Typography>
              <Typography variant="body2" color="text.secondary">
                {visitDate
                  ? `วันที่ ${formatThaiDate(visitDate)}`
                  : "ยังไม่ได้เลือกวันที่"}
              </Typography>
            </Box>
            <Box sx={{ display: "flex", gap: 1 }}>
              <Button
                variant="contained"
                size="large"
                disabled={!canSubmit}
                endIcon={<ArrowForward />}
                onClick={handleConfirm}
                sx={{ minWidth: 140 }}
              >
                ยืนยันนัด
              </Button>
            </Box>
          </Box>
        </Paper>
      )}

      {/* ข้อความแจ้งเตือนสั้น ๆ */}
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
