"use client";

import { useEffect, useMemo, useState } from "react";
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

  // เดิมใช้กับการเช่าจริง — คงฟิลด์ไว้เผื่อ backend เดิม
  fromDate?: string;
  toDate?: string;
  totalDays?: number;
  totalPrice?: number;

  // ฟิลด์นัดชม
  visitDate?: string;
};

export default function ReserveVisit() {
  const tomorrow = dayjs().startOf("day").add(1, "day");

  const [draft, setDraft] = useState<ReserveDraft | null>(null);
  const [user, setUser] = useState<UserLite | null>(null);
  const displayName = useMemo(
    () => user?.fullname || user?.username || user?.email || "ผู้ใช้",
    [user]
  );

  // เลือกได้ 1 วัน (วันนี้หรืออนาคต)
  const [visitDate, setVisitDate] = useState<Dayjs | null>(null);
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
    if (!visitDate) {
      setErrorText("");
      return;
    }
    if (visitDate.isBefore(tomorrow, "day")) {
      setErrorText("ต้องเลือกตั้งแต่วันพรุ่งนี้ขึ้นไป");
      return;
    }
    setErrorText("");
  }, [visitDate]);

  const canSubmit = !!draft && !!visitDate && !errorText;

  const handleConfirm = () => {
    if (!draft || !visitDate) return;

    // ถ้าหลังบ้านเดิมต้องการ from/to — เซตเป็นวันเดียวกัน
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
    window.location.href = `/checkout/${draft.listingId}/method`;
  };

  return (
    <>
      <Header />
      <Container maxWidth="lg" sx={{ py: { xs: 4, md: 6 } }}>
        <Paper sx={{ p: { xs: 2, md: 4 } }}>
          <Typography variant="h6" fontWeight={900} gutterBottom>
            นัดวันเข้าชมสถานที่ (เลือกได้ 1 วัน)
          </Typography>

          {!draft ? (
            <Alert severity="warning">
              ไม่พบข้อมูลรายการที่จะนัดชม —{" "}
              <Link href="/">ย้อนกลับหน้าแรก</Link>
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
                    รายละเอียดการนัดชม
                  </Typography>

                  <Box sx={{ display: "grid", gap: 2 }}>
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
                          ? `วันนัดชม: ${visitDate.format("DD/MM/YYYY")}`
                          : "โปรดเลือกวันที่ต้องการนัดชม (เลือกได้ 1 วัน — วันนี้หรืออนาคต)"}
                      </Alert>
                    )}

                    <TextField
                      label="หมายเหตุเพิ่มเติม"
                      multiline
                      minRows={3}
                    />

                    <Box
                      sx={{
                        display: "flex",
                        gap: 1,
                        justifyContent: "flex-end",
                      }}
                    >
                      <Button disabled={!canSubmit} onClick={handleConfirm}>
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
