"use client";

import Box from "@mui/material/Box";
import Container from "@mui/material/Container";
import Typography from "@mui/material/Typography";
import Link from "@mui/material/Link";
import Grid from "@mui/material/Grid";
import Divider from "@mui/material/Divider";

export default function Footer() {
  return (
    <Box
      component="footer"
      sx={{
        mt: 8,
        color: "primary.contrastText",
        backgroundImage: (theme) =>
          `linear-gradient(135deg, ${theme.palette.primary.main}, ${theme.palette.secondary.main})`,
      }}
    >
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
              sx={{
                display: "block",
                opacity: 0.95,
                "&:hover": { opacity: 1 },
              }}
            >
              📱 02-123-4567
            </Link>
            <Link
              href="mailto:info@rentspace.com"
              color="inherit"
              underline="hover"
              sx={{
                display: "block",
                wordBreak: "break-word",
                opacity: 0.95,
                "&:hover": { opacity: 1 },
              }}
            >
              📧 info@rentspace.com
            </Link>
            <Typography sx={{ opacity: 0.95 }}>📍 กรุงเทพมหานคร</Typography>
          </Grid>

          <Grid size={{ xs: 12, sm: 6, lg: 3 }}>
            <Typography variant="h6" fontWeight={800} gutterBottom>
              🔗 ลิงก์ด่วน
            </Typography>
            {[
              ["#", "ข้อกำหนดการใช้งาน"],
              ["#", "นโยบายความเป็นส่วนตัว"],
              ["#", "คำถามที่พบบ่อย"],
              ["#", "ช่วยเหลือ"],
            ].map(([href, label]) => (
              <Link
                key={label}
                href={href}
                color="inherit"
                underline="hover"
                sx={{
                  display: "block",
                  opacity: 0.95,
                  "&:hover": { opacity: 1, textDecorationThickness: "2px" },
                }}
              >
                {label}
              </Link>
            ))}
          </Grid>

          <Grid size={{ xs: 12, sm: 6, lg: 3 }}>
            <Typography variant="h6" fontWeight={800} gutterBottom>
              📱 ติดตามเรา
            </Typography>
            {["Facebook", "Instagram", "Twitter", "YouTube"].map((label) => (
              <Link
                key={label}
                href="#"
                color="inherit"
                underline="hover"
                sx={{
                  display: "block",
                  opacity: 0.95,
                  "&:hover": { opacity: 1, textDecorationThickness: "2px" },
                }}
              >
                {label}
              </Link>
            ))}
          </Grid>
        </Grid>
      </Container>

      <Divider sx={{ opacity: 0.12 }} />
      <Box
        sx={{
          textAlign: "center",
          py: 2.2,
          px: 2,
          bgcolor: "rgba(0,0,0,.12)",
        }}
      >
        <Typography
          variant="body2"
          sx={{ wordBreak: "break-word", opacity: 0.95 }}
        >
          © 2025 RentSpace - เช่าพื้นที่ออนไลน์. สงวนสิทธิ์ทุกประการ.
        </Typography>
      </Box>
    </Box>
  );
}
