"use client";

import Header from "@/components/Header";
import Container from "@mui/material/Container";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import Radio from "@mui/material/Radio";
import RadioGroup from "@mui/material/RadioGroup";
import FormControlLabel from "@mui/material/FormControlLabel";
import Button from "@mui/material/Button";
import Box from "@mui/material/Box";

export default function PaymentMethod() {
  return (
    <>
      <Header />
      <Container maxWidth="sm" sx={{ py: { xs: 4, md: 6 } }}>
        <Paper sx={{ p: { xs: 3, md: 4 }, textAlign: "center" }}>
          <Typography variant="h6" fontWeight={900} gutterBottom>
            ยืนยันการชำระเงิน
          </Typography>
          <Typography sx={{ mb: 1 }}>xx,xxx บาท</Typography>
          <Typography sx={{ color: "text.secondary", mb: 3 }}>
            ที่ดินเปล่า ต.ภูสิงห์ อ.สหัสขันธ์ ติดเชื่อมลำปาว
            <br /> ระยะเวลาเช่า xx/xx/xxxx - xx/xx/xxxx
          </Typography>

          <Typography fontWeight={800} gutterBottom>
            ช่องทางการชำระ:
          </Typography>
          <RadioGroup
            defaultValue="promptpay"
            sx={{ display: "inline-block", mb: 3 }}
          >
            <FormControlLabel
              value="promptpay"
              control={<Radio />}
              label="QR PromptPay"
            />
          </RadioGroup>

          <Box sx={{ display: "flex", justifyContent: "center", gap: 1 }}>
            <Button variant="outlined" href="/checkout/123">
              กลับ
            </Button>
            <Button variant="contained" href="/admin/payments">
              ชำระแล้ว
            </Button>
          </Box>
        </Paper>
      </Container>
    </>
  );
}
