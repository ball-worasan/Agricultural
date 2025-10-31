"use client";

import { useEffect, useState, useMemo } from "react";
import Header from "@/components/Header";
import Container from "@mui/material/Container";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import Grid from "@mui/material/Grid";
import Box from "@mui/material/Box";
import Alert from "@mui/material/Alert";
import Button from "@mui/material/Button";
import dayjs from "dayjs";

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
  // totalPrice?: number;
};

export default function ReserveStatusCard() {
  const [draft, setDraft] = useState<ReserveDraft | null>(null);

  useEffect(() => {
    try {
      const raw = sessionStorage.getItem("reserveDraft");
      setDraft(raw ? (JSON.parse(raw) as ReserveDraft) : null);
    } catch {
      setDraft(null);
    }
  }, []);

  const dateText = useMemo(() => {
    if (!draft?.fromDate || !draft?.toDate || !draft?.totalDays) return null;
    const from = dayjs(draft.fromDate).format("DD/MM/YYYY");
    const to = dayjs(draft.toDate).format("DD/MM/YYYY");
    return `ช่วงที่จอง: ${from} ถึง ${to} • รวม ${draft.totalDays} วัน`;
  }, [draft]);

  return (
    <>
      <Header />
      <Container maxWidth="lg" sx={{ py: { xs: 4, md: 6 } }}>
        {!draft ? (
          <Alert severity="warning">ไม่พบข้อมูลการจองล่าสุด</Alert>
        ) : (
          <Grid container spacing={3}>
            <Grid size={{ xs: 12, md: 6 }}>
              <Paper
                sx={{ height: 260, display: "grid", placeItems: "center" }}
              >
                &lt; รูปภาพพื้นที่ให้เช่า &gt;
              </Paper>
              <Box sx={{ mt: 2, color: "text.secondary" }}>
                <Typography fontWeight={800}>{draft.title}</Typography>
                <Typography variant="body2">📍 {draft.locationText}</Typography>
                <Typography variant="body2" color="primary">
                  {draft.price.toLocaleString("th-TH")}/{draft.unit}
                </Typography>
              </Box>
            </Grid>

            <Grid size={{ xs: 12, md: 6 }}>
              <Paper sx={{ p: 3, display: "grid", gap: 12 }}>
                <Box sx={{ display: "grid", gap: 1 }}>
                  <Typography variant="h6" fontWeight={900}>
                    สถานะการจองพื้นที่
                  </Typography>
                  {dateText ? (
                    <Alert severity="success">{dateText}</Alert>
                  ) : (
                    <Alert severity="info">กำลังรอเลือกช่วงวันที่</Alert>
                  )}

                  {typeof window !== "undefined" &&
                    sessionStorage.getItem("reserveSlip") && (
                      <Box sx={{ mt: 2 }}>
                        <Typography fontWeight={800} gutterBottom>
                          สลิปการชำระเงิน
                        </Typography>
                        <img
                          src={sessionStorage.getItem("reserveSlip") as string}
                          alt="สลิปการชำระเงิน"
                          style={{
                            width: "100%",
                            maxWidth: 360,
                            objectFit: "contain",
                            borderRadius: 8,
                          }}
                        />
                      </Box>
                    )}
                </Box>

                <Box
                  sx={{ display: "flex", gap: 1, justifyContent: "flex-end" }}
                >
                  <Button
                    variant="contained"
                    href="/contract"
                    onClick={() => sessionStorage.removeItem("reserveDraft")}
                  >
                    ไปทำสัญญา
                  </Button>
                  <Button variant="outlined" onClick={() => history.back()}>
                    แก้ไขช่วงวันที่
                  </Button>
                </Box>
              </Paper>
            </Grid>
          </Grid>
        )}
      </Container>
    </>
  );
}
