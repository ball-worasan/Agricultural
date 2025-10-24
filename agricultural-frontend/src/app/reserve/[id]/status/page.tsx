"use client";

import Header from "@/components/Header";
import Container from "@mui/material/Container";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import Grid from "@mui/material/Grid";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Box from "@mui/material/Box";

export default function ReserveStatusCard() {
  return (
    <>
      <Header />
      <Container maxWidth="lg" sx={{ py: { xs: 4, md: 6 } }}>
        <Grid container spacing={3}>
          <Grid size={{ xs: 12, md: 6 }}>
            <Paper sx={{ height: 260, display: "grid", placeItems: "center" }}>
              &lt; รูปภาพพื้นที่ให้เช่า &gt;
            </Paper>
          </Grid>
          <Grid size={{ xs: 12, md: 6 }}>
            <Paper sx={{ p: 3 }}>
              <Typography variant="h6" fontWeight={900} gutterBottom>
                สถานะการจองพื้นที่
              </Typography>
              <Box sx={{ display: "grid", gap: 1.5 }}>
                <TextField label="คุณxxx" disabled />
                <TextField label="วัน/ที่จอง" disabled />
                <Button variant="outlined" component="label">
                  อัปโหลดสลิปยืนยันการจอง
                  <input type="file" hidden />
                </Button>
                <Button variant="contained" href="/contract">
                  OK
                </Button>
              </Box>
            </Paper>
          </Grid>
        </Grid>
      </Container>
    </>
  );
}
