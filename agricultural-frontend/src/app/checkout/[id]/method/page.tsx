"use client";

import { useEffect, useMemo, useState } from "react";
import Header from "@/components/Header";
import Container from "@mui/material/Container";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import Radio from "@mui/material/Radio";
import RadioGroup from "@mui/material/RadioGroup";
import FormControlLabel from "@mui/material/FormControlLabel";
import Button from "@mui/material/Button";
import Box from "@mui/material/Box";
import Alert from "@mui/material/Alert";

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

export default function PaymentMethod() {
  const [draft, setDraft] = useState<ReserveDraft | null>(null);
  const [slipPreview, setSlipPreview] = useState<string | null>(null);

  useEffect(() => {
    try {
      const raw = sessionStorage.getItem("reserveDraft");
      setDraft(raw ? (JSON.parse(raw) as ReserveDraft) : null);
      const slip = sessionStorage.getItem("reserveSlip");
      setSlipPreview(slip || null);
    } catch {
      setDraft(null);
    }
  }, []);

  const amount = useMemo(() => draft?.totalPrice ?? 0, [draft]);
  const qrSrc = useMemo(() => {
    const n = Math.max(0, Math.round(amount)); // จำนวนเต็มบาท
    return `https://promptpay.io/0640163043/${n}.png`;
  }, [amount]);

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

  if (!draft) {
    return (
      <>
        <Header />
        <Container maxWidth="sm" sx={{ py: { xs: 4, md: 6 } }}>
          <Alert severity="warning">ไม่พบข้อมูลการจอง</Alert>
        </Container>
      </>
    );
  }

  return (
    <>
      <Header />
      <Container maxWidth="sm" sx={{ py: { xs: 4, md: 6 } }}>
        <Paper sx={{ p: { xs: 3, md: 4 }, textAlign: "center" }}>
          <Typography variant="h6" fontWeight={900} gutterBottom>
            ยืนยันการชำระเงิน
          </Typography>
          <Typography sx={{ mb: 1 }}>
            {amount.toLocaleString("th-TH")} บาท
          </Typography>
          <Typography sx={{ color: "text.secondary", mb: 3 }}>
            {draft.title}
            <br /> ระยะเวลา {draft.fromDate} - {draft.toDate}
          </Typography>

          <Typography fontWeight={800} gutterBottom>
            ช่องทางการชำระ:
          </Typography>
          <RadioGroup
            defaultValue="promptpay"
            sx={{ display: "inline-block", mb: 2 }}
          >
            <FormControlLabel
              value="promptpay"
              control={<Radio />}
              label="QR PromptPay"
            />
          </RadioGroup>

          {/* QR PromptPay */}
          <Box sx={{ mb: 2 }}>
            <img
              src={qrSrc}
              alt="QR PromptPay"
              style={{ width: 240, height: 240, objectFit: "contain" }}
            />
          </Box>

          {/* อัปโหลดสลิป */}
          <Box sx={{ display: "grid", gap: 1.5, mb: 2 }}>
            <Button variant="outlined" component="label">
              อัปโหลดสลิปการโอน
              <input
                type="file"
                accept="image/*"
                hidden
                onChange={handleUpload}
              />
            </Button>
            {slipPreview && (
              <Box>
                <Typography
                  variant="body2"
                  sx={{ mb: 0.5, color: "text.secondary" }}
                >
                  ตัวอย่างสลิปที่อัปโหลด
                </Typography>
                <img
                  src={slipPreview}
                  alt="สลิปการโอน"
                  style={{
                    width: "100%",
                    maxHeight: 300,
                    objectFit: "contain",
                    borderRadius: 8,
                  }}
                />
              </Box>
            )}
          </Box>

          <Box sx={{ display: "flex", justifyContent: "center", gap: 1 }}>
            <Button variant="outlined" href={`/checkout/${draft.listingId}`}>
              กลับ
            </Button>
            <Button
              variant="contained"
              onClick={() => {
                // ในระบบจริงควรยิง API ตรวจ/บันทึกการชำระเงินก่อน
                window.location.href = `/reserve/${draft.listingId}/status`;
              }}
            >
              ชำระแล้ว
            </Button>
          </Box>
        </Paper>
      </Container>
    </>
  );
}
