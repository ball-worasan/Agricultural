"use client";

import Header from "@/components/Header";
import Container from "@mui/material/Container";
import Paper from "@mui/material/Paper";
import Grid from "@mui/material/Grid";
import Typography from "@mui/material/Typography";
import Button from "@mui/material/Button";
import Box from "@mui/material/Box";

export default function ReserveEntry() {
  return (
    <>
      <Header />
      <Container maxWidth="lg" sx={{ py: { xs: 4, md: 6 } }}>
        <Paper sx={{ p: { xs: 2, md: 4 } }}>
          <Typography variant="h6" fontWeight={900} gutterBottom>
            ปล่อยเช่าที่ดินเปล่า ต.ภูสิงห์ อ.สหัสขันธ์ ติดเชื่อมลำปาว
          </Typography>

          <Grid container spacing={3}>
            <Grid size={{ xs: 12, md: 6 }}>
              <Paper
                sx={{ height: 260, display: "grid", placeItems: "center" }}
              >
                &lt; รูปภาพพื้นที่ให้เช่า &gt;
              </Paper>
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <Paper sx={{ p: 3 }}>
                <Typography variant="h5" color="primary" fontWeight={900}>
                  xx,xxx/ปี
                </Typography>
                <Box
                  sx={{
                    height: 160,
                    bgcolor: "rgba(0,0,0,.03)",
                    mt: 2,
                    display: "grid",
                    placeItems: "center",
                  }}
                >
                  รายละเอียดประกาศ
                </Box>
                <Box sx={{ mt: 2 }}>
                  <Button variant="contained" href="/reserve/123/inline">
                    จองพื้นที่นี้
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
