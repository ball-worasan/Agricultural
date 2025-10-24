"use client";

import Box from "@mui/material/Box";
import Container from "@mui/material/Container";
import Typography from "@mui/material/Typography";
import Link from "@mui/material/Link";
import Grid from "@mui/material/Grid";

export default function Footer() {
  return (
    <Box sx={{ mt: 8, bgcolor: "primary.main", color: "primary.contrastText" }}>
      <Container maxWidth="lg" sx={{ py: { xs: 4, md: 6 } }}>
        <Grid container spacing={{ xs: 2, md: 4 }}>
          <Grid size={{ xs: 12, sm: 6, lg: 3 }}>
            <Typography variant="h6" fontWeight={800} gutterBottom>
              🏢 เกี่ยวกับเรา
            </Typography>
            <Typography sx={{ opacity: 0.95, wordBreak: "break-word" }}>
              แพลตฟอร์มเช่าพื้นที่ออนไลน์ที่ใหญ่ที่สุดในประเทศไทย
            </Typography>
            <Typography sx={{ opacity: 0.9 }}>
              เชื่อมต่อผู้ให้เช่าและผู้เช่าอย่างปลอดภัย
            </Typography>
          </Grid>

          <Grid size={{ xs: 12, sm: 6, lg: 3 }}>
            <Typography variant="h6" fontWeight={800} gutterBottom>
              📞 ติดต่อเรา
            </Typography>
            <Link
              href="tel:02-123-4567"
              color="inherit"
              underline="hover"
              sx={{ display: "block" }}
            >
              📱 02-123-4567
            </Link>
            <Link
              href="mailto:info@rentspace.com"
              color="inherit"
              underline="hover"
              sx={{ display: "block", wordBreak: "break-word" }}
            >
              📧 info@rentspace.com
            </Link>
            <Typography>📍 กรุงเทพมหานคร</Typography>
          </Grid>

          <Grid size={{ xs: 12, sm: 6, lg: 3 }}>
            <Typography variant="h6" fontWeight={800} gutterBottom>
              🔗 ลิงก์ด่วน
            </Typography>
            <Link
              href="#"
              color="inherit"
              underline="hover"
              sx={{ display: "block" }}
            >
              ข้อกำหนดการใช้งาน
            </Link>
            <Link
              href="#"
              color="inherit"
              underline="hover"
              sx={{ display: "block" }}
            >
              นโยบายความเป็นส่วนตัว
            </Link>
            <Link
              href="#"
              color="inherit"
              underline="hover"
              sx={{ display: "block" }}
            >
              คำถามที่พบบ่อย
            </Link>
            <Link
              href="#"
              color="inherit"
              underline="hover"
              sx={{ display: "block" }}
            >
              ช่วยเหลือ
            </Link>
          </Grid>

          <Grid size={{ xs: 12, sm: 6, lg: 3 }}>
            <Typography variant="h6" fontWeight={800} gutterBottom>
              📱 ติดตามเรา
            </Typography>
            <Link
              href="#"
              color="inherit"
              underline="hover"
              sx={{ display: "block" }}
            >
              🌐 Facebook
            </Link>
            <Link
              href="#"
              color="inherit"
              underline="hover"
              sx={{ display: "block" }}
            >
              📷 Instagram
            </Link>
            <Link
              href="#"
              color="inherit"
              underline="hover"
              sx={{ display: "block" }}
            >
              🐦 Twitter
            </Link>
            <Link
              href="#"
              color="inherit"
              underline="hover"
              sx={{ display: "block" }}
            >
              📺 YouTube
            </Link>
          </Grid>
        </Grid>
      </Container>

      <Box
        sx={{ textAlign: "center", py: 2, bgcolor: "rgba(0,0,0,.08)", px: 2 }}
      >
        <Typography variant="body2" sx={{ wordBreak: "break-word" }}>
          © 2025 RentSpace - เช่าพื้นที่ออนไลน์. สงวนสิทธิ์ทุกประการ.
        </Typography>
      </Box>
    </Box>
  );
}
