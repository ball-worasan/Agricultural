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
import Stack from "@mui/material/Stack";
import Divider from "@mui/material/Divider";
import Chip from "@mui/material/Chip";
import IconButton from "@mui/material/IconButton";
import Tooltip from "@mui/material/Tooltip";
import ContentCopyIcon from "@mui/icons-material/ContentCopy";
import InfoOutlinedIcon from "@mui/icons-material/InfoOutlined";

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

const PROMPTPAY_ID = "0640163043"; // TODO: ย้ายไป .env ถ้าเป็นโปรดักชัน

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

  const amount = useMemo(
    () => Math.max(100, Math.round(draft?.totalPrice ?? 100)),
    [draft]
  );
  const qrSrc = useMemo(
    () => `https://promptpay.io/${PROMPTPAY_ID}/${amount}.png`,
    [amount]
  );

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

  const copyAmount = async () => {
    try {
      await navigator.clipboard.writeText(String(amount));
    } catch {}
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

  const canSubmit = amount > 0; // กันเผื่อยอด 0 บาท

  return (
    <>
      <Header />
      <Container maxWidth="md" sx={{ py: { xs: 4, md: 6 } }}>
        <Paper
          elevation={1}
          sx={{
            p: { xs: 3, md: 4 },
            borderRadius: 3,
          }}
        >
          {/* หัวข้อ */}
          <Stack
            direction="row"
            alignItems="center"
            justifyContent="space-between"
            sx={{ mb: 2 }}
          >
            <Typography variant="h6" fontWeight={900}>
              ยืนยันการชำระเงิน
            </Typography>
            <Chip
              icon={<InfoOutlinedIcon />}
              label="ชำระผ่าน QR PromptPay"
              color="primary"
              variant="outlined"
              sx={{ fontWeight: 700 }}
            />
          </Stack>

          {/* สรุปรายการ */}
          <Box
            sx={{
              display: "grid",
              gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" },
              gap: 2.5,
              mb: 3,
            }}
          >
            <Paper
              variant="outlined"
              sx={{ p: 2, borderRadius: 2, bgcolor: "background.default" }}
            >
              <Typography fontWeight={800} gutterBottom>
                รายการจอง
              </Typography>
              <Stack spacing={0.5} sx={{ color: "text.secondary" }}>
                <Typography>{draft.title}</Typography>
                <Typography>📍 {draft.locationText}</Typography>
                <Typography>
                  ระยะเวลา {draft.fromDate} - {draft.toDate}
                </Typography>
              </Stack>
            </Paper>

            <Paper
              variant="outlined"
              sx={{ p: 2, borderRadius: 2, bgcolor: "background.default" }}
            >
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
                  {amount.toLocaleString("th-TH")}
                </Typography>
                <Typography variant="h6" sx={{ color: "text.secondary" }}>
                  บาท
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
                ราคา: {draft.price.toLocaleString("th-TH")}/{draft.unit} ×{" "}
                {draft.totalDays} วัน
              </Typography>
            </Paper>
          </Box>

          <Divider sx={{ my: 2.5 }} />

          {/* เลือกช่องทาง (อนาคตจะมีหลายช่องทางได้) */}
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

          {/* QR อยู่กึ่งกลางหน้าจอ */}
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
                bgcolor: "background.default",
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
                  display: "grid",
                  placeItems: "center",
                  bgcolor: "background.paper",
                  borderRadius: 2,
                  overflow: "hidden",
                }}
              >
                <img
                  src={qrSrc}
                  alt="QR PromptPay"
                  style={{
                    width: "100%",
                    height: "100%",
                    objectFit: "contain",
                  }}
                />
              </Box>
              <Typography
                variant="caption"
                sx={{ mt: 1, display: "block", color: "text.secondary" }}
              >
                จำนวนเงินจะถูกฝังใน QR อัตโนมัติ
              </Typography>
            </Paper>
          </Box>

          {/* อัปโหลดสลิป */}
          <Box sx={{ display: "grid", gap: 1.5, my: 3 }}>
            <Typography fontWeight={800}>อัปโหลดสลิปการโอน</Typography>
            <Stack
              direction={{ xs: "column", sm: "row" }}
              spacing={1}
              alignItems="center"
            >
              <Button variant="outlined" component="label">
                เลือกรูปภาพสลิป
                <input
                  type="file"
                  accept="image/*"
                  hidden
                  onChange={handleUpload}
                />
              </Button>
              <Typography variant="body2" sx={{ color: "text.secondary" }}>
                รองรับ JPG/PNG
              </Typography>
            </Stack>

            {slipPreview && (
              <Paper
                variant="outlined"
                sx={{ p: 1.5, borderRadius: 2, bgcolor: "background.default" }}
              >
                <Typography
                  variant="body2"
                  sx={{ mb: 0.5, color: "text.secondary" }}
                >
                  ตัวอย่างสลิปที่อัปโหลด
                </Typography>
                <Box
                  component="img"
                  src={slipPreview}
                  alt="สลิปการโอน"
                  sx={{
                    width: "100%",
                    maxHeight: 360,
                    objectFit: "contain",
                    borderRadius: 1.5,
                  }}
                />
              </Paper>
            )}
          </Box>

          {/* ปุ่ม Action */}
          {!canSubmit && (
            <Alert severity="info" sx={{ mb: 2 }}>
              ยอดชำระเป็น 0 บาท — กรุณาตรวจสอบข้อมูลการจองก่อนดำเนินการ
            </Alert>
          )}

          <Stack direction="row" justifyContent="center" spacing={1}>
            <Button variant="outlined" href={`/checkout/${draft.listingId}`}>
              กลับ
            </Button>
            <Button
              variant="contained"
              disabled={!canSubmit}
              onClick={() => {
                // NOTE: โปรดเพิ่มการเรียก API บันทึก/ตรวจสอบการชำระเงินจริงก่อน redirect
                window.location.href = `/reserve/${draft.listingId}/status`;
              }}
            >
              ชำระแล้ว
            </Button>
          </Stack>
        </Paper>
      </Container>
    </>
  );
}
