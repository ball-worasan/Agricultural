"use client";

import Container from "@mui/material/Container";
import Grid from "@mui/material/Grid";
import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import Paper from "@mui/material/Paper";
import Button from "@mui/material/Button";
import Chip from "@mui/material/Chip";
import Header from "@/components/Header";

export default function ListingDetail() {
  return (
    <>
      <Header />
      <Container maxWidth="lg" sx={{ py: { xs: 4, md: 6 } }}>
        <Paper sx={{ p: { xs: 2, md: 4 } }}>
          <Typography variant="h5" fontWeight={900} gutterBottom>
            ปล่อยเช่าที่ดินเปล่า ต.ภูสิงห์ อ.สหัสขันธ์ ติดเชื่อมลำปาว
          </Typography>

          <Box sx={{ display: "flex", gap: 2, color: "text.secondary", mb: 2 }}>
            <span>📍 อ.สหัสขันธ์ จ.กาฬสินธุ์</span>
            <span>•</span>
            <span>ลงประกาศ : 08/09/2568</span>
          </Box>

          <Grid container spacing={3}>
            <Grid size={{ xs: 12, md: 7 }}>
              <Paper
                sx={{
                  height: 380,
                  bgcolor: "rgba(0,0,0,.04)",
                  border: "1px solid rgba(0,0,0,.06)",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  fontSize: 22,
                  color: "text.secondary",
                }}
              >
                &lt; รูปภาพพื้นที่ให้เช่า &gt;
              </Paper>
            </Grid>
            <Grid size={{ xs: 12, md: 5 }}>
              <Paper sx={{ p: 3, height: "100%" }}>
                <Box
                  sx={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    mb: 2,
                  }}
                >
                  <Typography variant="h5" fontWeight={900} color="primary">
                    xx,xxx/ปี
                  </Typography>
                  <Chip label="ว่าง" color="success" variant="outlined" />
                </Box>

                <Typography variant="h6" fontWeight={800} gutterBottom>
                  รายละเอียดประกาศ
                </Typography>
                <Typography color="text.secondary" sx={{ lineHeight: 1.8 }}>
                  พื้นที่ติดชุมชน เข้าออกสะดวก เหมาะสำหรับทำการเกษตร/โกดัง
                  มีทางน้ำใกล้เคียง ระบบไฟเข้าถึง
                </Typography>

                <Box sx={{ mt: 3, display: "flex", gap: 1.5 }}>
                  <Button variant="contained" size="large" href="/reserve/123">
                    จองพื้นที่นี้
                  </Button>
                  <Button variant="outlined" size="large">
                    แชร์ประกาศ
                  </Button>
                </Box>
              </Paper>
            </Grid>
          </Grid>
        </Paper>
      </Container>
    </>
  );
}
