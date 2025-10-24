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
import Link from "next/link";

export default function Page() {
  return (
    <>
      <Header />

      {/* Hero */}
      <Box
        className="grad"
        sx={{
          pt: "var(--hero-pad-top)",
          pb: "var(--hero-pad-bot)",
          borderBottom: "1px solid rgba(0,0,0,.06)",
        }}
      >
        <Container maxWidth="lg">
          <Reveal>
            <Stack
              spacing={{ xs: 2, md: 3 }}
              alignItems="center"
              textAlign="center"
            >
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
              <Typography
                variant="h6"
                color="text.secondary"
                maxWidth={720}
                sx={{ px: { xs: 2, md: 0 } }}
              >
                ค้นหา–จอง–ทำสัญญา–ชำระเงิน ในที่เดียว ดีไซน์มินิมอล
                ใช้งานลื่นทุกหน้าจอ
              </Typography>
              <Stack
                direction={{ xs: "column", sm: "row" }}
                spacing={2}
                sx={{ pt: 1, width: "100%", justifyContent: "center" }}
              >
                <Button
                  component={Link}
                  href="/reserve/list"
                  variant="contained"
                  size="large"
                  sx={{ minWidth: { xs: "100%", sm: 220 } }}
                >
                  เริ่มค้นหาพื้นที่
                </Button>
                <Button
                  component={Link}
                  href="/account/spaces"
                  variant="outlined"
                  size="large"
                  sx={{ minWidth: { xs: "100%", sm: 220 } }}
                >
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
        <Box mt={{ xs: 2, md: 4 }}>
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
