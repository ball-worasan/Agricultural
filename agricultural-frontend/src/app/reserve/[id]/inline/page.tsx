"use client";

import { useEffect, useMemo, useState, useCallback } from "react";
import Header from "@/components/Header";
import Container from "@mui/material/Container";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import Grid from "@mui/material/Grid";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Box from "@mui/material/Box";
import Alert from "@mui/material/Alert";
import Link from "next/link";

import { LocalizationProvider } from "@mui/x-date-pickers/LocalizationProvider";
import { AdapterDayjs } from "@mui/x-date-pickers/AdapterDayjs";
import { DatePicker } from "@mui/x-date-pickers/DatePicker";

import dayjs, { Dayjs } from "dayjs";
import isBetween from "dayjs/plugin/isBetween";
dayjs.extend(isBetween);

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
};

/** ช่วงไม่ว่าง (ตัวอย่าง) */
const BOOKED: Array<{ start: string; end: string }> = [
  { start: "2025-11-05", end: "2025-11-07" },
  { start: "2025-11-15", end: "2025-11-18" },
  { start: "2025-12-02", end: "2025-12-03" },
];

const isBooked = (d: Dayjs): boolean =>
  BOOKED.some((r) =>
    d.isBetween(
      dayjs(r.start).startOf("day"),
      dayjs(r.end).endOf("day"),
      null,
      "[]"
    )
  );

const hasBookedBetween = (start: Dayjs, end: Dayjs): boolean => {
  const a = start.startOf("day");
  const b = end.startOf("day");
  if (b.isBefore(a)) return true;
  let cur = a;
  while (cur.isSame(b) || cur.isBefore(b)) {
    if (isBooked(cur)) return true;
    cur = cur.add(1, "day");
  }
  return false;
};

const countDaysInclusive = (start: Dayjs, end: Dayjs) =>
  end.startOf("day").diff(start.startOf("day"), "day") + 1;

/** คิดราคารวมแบบง่าย: แปลงเป็นราคาต่อวัน */
const computeTotalPrice = (
  price: number,
  unit: "วัน" | "เดือน" | "ปี",
  totalDays: number
) => {
  const perDay =
    unit === "วัน" ? price : unit === "เดือน" ? price / 30 : price / 365;
  return Math.max(0, Math.round(perDay * totalDays)); // ปัดเป็นจำนวนเต็มสำหรับ QR
};

export default function ReserveInline() {
  const [draft, setDraft] = useState<ReserveDraft | null>(null);
  const [user, setUser] = useState<UserLite | null>(null);
  const displayName = useMemo(
    () => user?.fullname || user?.username || user?.email || "ผู้ใช้",
    [user]
  );

  const [from, setFrom] = useState<Dayjs | null>(null);
  const [to, setTo] = useState<Dayjs | null>(null);
  const [errorText, setErrorText] = useState("");

  useEffect(() => {
    try {
      const raw = sessionStorage.getItem("reserveDraft");
      setDraft(raw ? (JSON.parse(raw) as ReserveDraft) : null);
    } catch {
      setDraft(null);
    }
    setUser(readUserCookie());
  }, []);

  useEffect(() => {
    if (!from || !to) {
      setErrorText("");
      return;
    }
    if (to.isBefore(from, "day")) {
      setErrorText("วันสิ้นสุดต้องไม่น้อยกว่าวันเริ่มต้น");
      return;
    }
    if (hasBookedBetween(from, to)) {
      setErrorText(
        "มีช่วงวันที่ไม่ว่างคั่นอยู่ ไม่สามารถจองข้ามช่วงที่ไม่ว่างได้"
      );
      return;
    }
    setErrorText("");
  }, [from, to]);

  const shouldDisableFrom = useCallback((d: Dayjs) => isBooked(d), []);
  const shouldDisableTo = useCallback(
    (d: Dayjs) => {
      if (isBooked(d)) return true;
      if (from && d.isBefore(from, "day")) return true;
      if (from && hasBookedBetween(from, d)) return true;
      return false;
    },
    [from]
  );

  const clearDates = () => {
    setFrom(null);
    setTo(null);
  };
  const quickPick = (days: number) => {
    if (!from) return;
    const end = from.add(days - 1, "day");
    if (!hasBookedBetween(from, end)) setTo(end);
  };

  const totalDays = useMemo(
    () => (from && to ? countDaysInclusive(from, to) : 0),
    [from, to]
  );
  const canSubmit = !!draft && !!from && !!to && !errorText;

  const handleConfirm = () => {
    if (!draft || !from || !to) return;
    const totalPrice = computeTotalPrice(draft.price, draft.unit, totalDays);
    const updated: ReserveDraft = {
      ...draft,
      fromDate: from.format("YYYY-MM-DD"),
      toDate: to.format("YYYY-MM-DD"),
      totalDays,
      totalPrice,
    };
    sessionStorage.setItem("reserveDraft", JSON.stringify(updated));
    window.location.href = `/checkout/${draft.listingId}`; // ไปหน้าเช็คข้อมูล
  };

  return (
    <>
      <Header />
      <Container maxWidth="lg" sx={{ py: { xs: 4, md: 6 } }}>
        <Paper sx={{ p: { xs: 2, md: 4 } }}>
          <Typography variant="h6" fontWeight={900} gutterBottom>
            จองพื้นที่เช่า
          </Typography>

          {!draft ? (
            <Alert severity="warning">
              ไม่พบข้อมูลรายการที่จะจอง — <Link href="/">ย้อนกลับหน้าแรก</Link>
            </Alert>
          ) : (
            <Grid container spacing={3}>
              <Grid size={{ xs: 12, md: 7 }}>
                <Paper
                  sx={{ height: 260, display: "grid", placeItems: "center" }}
                >
                  &lt; รูปภาพพื้นที่ให้เช่า &gt;
                </Paper>
                <Box sx={{ mt: 2, color: "text.secondary" }}>
                  <Typography fontWeight={800}>{draft.title}</Typography>
                  <Typography variant="body2">
                    📍 {draft.locationText}
                  </Typography>
                  <Typography variant="body2" color="primary">
                    {draft.price.toLocaleString("th-TH")}/{draft.unit}
                  </Typography>
                </Box>
              </Grid>

              <Grid size={{ xs: 12, md: 5 }}>
                <Paper sx={{ p: 3 }}>
                  <Typography fontWeight={800} gutterBottom>
                    รายละเอียดการจอง
                  </Typography>

                  <Box sx={{ display: "grid", gap: 2 }}>
                    <TextField
                      label="ผู้จอง"
                      value={displayName}
                      disabled
                      fullWidth
                    />

                    <LocalizationProvider dateAdapter={AdapterDayjs}>
                      <Grid container spacing={1.5}>
                        <Grid size={{ xs: 12, sm: 6 }}>
                          <DatePicker
                            label="วันเริ่มต้น"
                            value={from}
                            onChange={(v: Dayjs | null) => {
                              setFrom(v);
                              if (
                                v &&
                                to &&
                                (to.isBefore(v, "day") ||
                                  hasBookedBetween(v, to))
                              )
                                setTo(null);
                            }}
                            disablePast
                            shouldDisableDate={shouldDisableFrom}
                            slotProps={{ textField: { fullWidth: true } }}
                          />
                        </Grid>
                        <Grid size={{ xs: 12, sm: 6 }}>
                          <DatePicker
                            label="วันสิ้นสุด"
                            value={to}
                            onChange={(v: Dayjs | null) => setTo(v)}
                            disablePast
                            minDate={from ?? dayjs()}
                            shouldDisableDate={shouldDisableTo}
                            slotProps={{ textField: { fullWidth: true } }}
                          />
                        </Grid>
                      </Grid>
                    </LocalizationProvider>

                    <Box
                      sx={{
                        display: "flex",
                        gap: 1,
                        flexWrap: "wrap",
                        alignItems: "center",
                      }}
                    >
                      <Typography
                        variant="body2"
                        sx={{ color: "text.secondary" }}
                      >
                        เลือกช่วงเร็ว:
                      </Typography>
                      <Button
                        size="small"
                        variant="outlined"
                        disabled={!from}
                        onClick={() => quickPick(7)}
                      >
                        7 วัน
                      </Button>
                      <Button
                        size="small"
                        variant="outlined"
                        disabled={!from}
                        onClick={() => quickPick(15)}
                      >
                        15 วัน
                      </Button>
                      <Button
                        size="small"
                        variant="outlined"
                        disabled={!from}
                        onClick={() => quickPick(30)}
                      >
                        1 เดือน
                      </Button>
                      <Button size="small" color="inherit" onClick={clearDates}>
                        ล้างวันที่
                      </Button>
                    </Box>

                    {errorText ? (
                      <Alert severity="warning">{errorText}</Alert>
                    ) : (
                      <Alert severity={from && to ? "success" : "info"}>
                        {from && to
                          ? `เลือกช่วง: ${from.format(
                              "DD/MM/YYYY"
                            )} ถึง ${to.format(
                              "DD/MM/YYYY"
                            )} • รวม ${totalDays} วัน`
                          : "โปรดเลือกช่วงวันที่ที่จะจอง"}
                      </Alert>
                    )}

                    <TextField label="หมายเหตุ" multiline minRows={3} />

                    <Box
                      sx={{
                        display: "flex",
                        gap: 1,
                        justifyContent: "flex-end",
                      }}
                    >
                      <Button disabled={!canSubmit} onClick={handleConfirm}>
                        ยืนยัน
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
                  </Box>
                </Paper>
              </Grid>
            </Grid>
          )}
        </Paper>
      </Container>
    </>
  );
}
