"use client";

import Container from "@mui/material/Container";
import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import Header from "@/components/Header";
import Reveal from "@/components/Reveal";
import SearchSection from "@/components/SearchSection";
import CardsGrid from "@/components/CardsGrid";
import Footer from "@/components/Footer";

export default function Page() {
  return (
    <>
      <Header />

      {/* Hero */}
      <Box
        className="grad"
        sx={{
          pt: { xs: 8, md: 12 },
          pb: { xs: 6, md: 10 },
          borderBottom: "1px solid rgba(0,0,0,.06)",
        }}
      >
        <Container maxWidth="lg">
          <Reveal>
            <Stack spacing={2} alignItems="center" textAlign="center">
              <Typography
                variant="h2"
                sx={{
                  fontWeight: 900,
                  lineHeight: 1.1,
                  backgroundImage:
                    "linear-gradient(135deg, var(--brand-1), var(--brand-2))",
                  WebkitBackgroundClip: "text",
                  color: "transparent",
                }}
              >
                เช่าพื้นที่ออนไลน์ แบบง่ายสุด ๆ
              </Typography>
              <Typography variant="h6" color="text.secondary" maxWidth={720}>
                ค้นหา–จอง–ทำสัญญา–ชำระเงิน ในที่เดียว ดีไซน์มินิมอล
                ใช้งานลื่นทุกหน้าจอ
              </Typography>
              <Stack
                direction={{ xs: "column", sm: "row" }}
                spacing={2}
                sx={{ pt: 1 }}
              >
                <Button variant="contained" size="large">
                  เริ่มค้นหาพื้นที่
                </Button>
                <Button variant="outlined" size="large">
                  ปล่อยเช่าพื้นที่ของคุณ
                </Button>
              </Stack>
            </Stack>
          </Reveal>
        </Container>
      </Box>

      {/* Search */}
      <Container maxWidth="lg" sx={{ py: { xs: 4, md: 6 } }}>
        <Reveal>
          <SearchSection />
        </Reveal>
      </Container>

      {/* Recommended */}
      <Container maxWidth="lg" sx={{ pb: { xs: 6, md: 10 } }}>
        <Box mt={4}>
          <Reveal>
            <Typography
              variant="h4"
              align="center"
              sx={{
                fontWeight: 800,
                mb: 3,
                background:
                  "linear-gradient(135deg, var(--brand-1), var(--brand-2))",
                WebkitBackgroundClip: "text",
                color: "transparent",
              }}
            >
              พื้นที่แนะนำ
            </Typography>
          </Reveal>

          <Reveal>
            <CardsGrid />
          </Reveal>
        </Box>
      </Container>

      <Footer />
    </>
  );
}
