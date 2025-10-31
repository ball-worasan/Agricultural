"use client";

import { useEffect, useState } from "react";
import Header from "@/components/Header";
import Container from "@mui/material/Container";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import Grid from "@mui/material/Grid";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Alert from "@mui/material/Alert";
import dayjs from "dayjs";

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

export default function CheckoutSummary() {
  const [draft, setDraft] = useState<ReserveDraft | null>(null);

  useEffect(() => {
    try {
      const raw = sessionStorage.getItem("reserveDraft");
      setDraft(raw ? (JSON.parse(raw) as ReserveDraft) : null);
    } catch {
      setDraft(null);
    }
  }, []);

  if (!draft) {
    return (
      <>
        <Header />
        <Container maxWidth="md" sx={{ py: { xs: 4, md: 6 } }}>
          <Alert severity="warning">ไม่พบข้อมูลการจอง</Alert>
        </Container>
      </>
    );
  }

  return (
    <>
      <Header />
      <Container maxWidth="md" sx={{ py: { xs: 4, md: 6 } }}>
        <Paper sx={{ p: { xs: 3, md: 4 } }}>
          <Typography variant="h6" fontWeight={900} gutterBottom>
            ตรวจสอบข้อมูลก่อนชำระเงิน
          </Typography>

          <Grid container spacing={2}>
            <Grid size={{ xs: 12 }}>
              <TextField label="ชื่อพื้นที่" value={draft.title} fullWidth />
            </Grid>
            <Grid size={{ xs: 12 }}>
              <TextField label="ที่ตั้ง" value={draft.locationText} fullWidth />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <TextField
                label="ช่วงวันที่จอง"
                value={`${dayjs(draft.fromDate).format("DD/MM/YYYY")} - ${dayjs(
                  draft.toDate
                ).format("DD/MM/YYYY")}`}
                fullWidth
              />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <TextField
                label="จำนวนวันรวม"
                value={draft.totalDays ?? 0}
                fullWidth
              />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <TextField
                label={`ราคา/ ${draft.unit}`}
                value={draft.price.toLocaleString("th-TH")}
                fullWidth
              />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <TextField
                label="ยอดรวมที่ต้องชำระ (บาท)"
                value={(draft.totalPrice ?? 0).toLocaleString("th-TH")}
                fullWidth
              />
            </Grid>

            <Grid
              size={{ xs: 12 }}
              sx={{
                display: "flex",
                gap: 1,
                justifyContent: "flex-end",
                mt: 2,
              }}
            >
              <Button
                variant="outlined"
                href={`/reserve/${draft.listingId}/inline`}
              >
                แก้ไขวันที่
              </Button>
              <Button
                variant="contained"
                href={`/checkout/${draft.listingId}/method`}
                size="large"
              >
                ยืนยันการชำระ
              </Button>
            </Grid>
          </Grid>
        </Paper>
      </Container>
    </>
  );
}
