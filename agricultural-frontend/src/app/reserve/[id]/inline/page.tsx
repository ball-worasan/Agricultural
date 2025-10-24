"use client";

import Header from "@/components/Header";
import Container from "@mui/material/Container";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import Grid from "@mui/material/Grid";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Box from "@mui/material/Box";

export default function ReserveInline() {
  return (
    <>
      <Header />
      <Container maxWidth="lg" sx={{ py: { xs: 4, md: 6 } }}>
        <Paper sx={{ p: { xs: 2, md: 4 } }}>
          <Typography variant="h6" fontWeight={900} gutterBottom>
            จองพื้นที่เช่า
          </Typography>

          <Grid container spacing={3}>
            <Grid size={{ xs: 12, md: 7 }}>
              <Paper
                sx={{ height: 260, display: "grid", placeItems: "center" }}
              >
                &lt; รูปภาพพื้นที่ให้เช่า &gt;
              </Paper>
            </Grid>
            <Grid size={{ xs: 12, md: 5 }}>
              <Paper sx={{ p: 3 }}>
                <Typography fontWeight={800} gutterBottom>
                  จองพื้นที่เช่า
                </Typography>
                <Box sx={{ display: "grid", gap: 2 }}>
                  <TextField label="คุณxxx (ผู้จอง)" disabled fullWidth />
                  <TextField
                    label="เลือกวันที่"
                    type="date"
                    InputLabelProps={{ shrink: true }}
                  />
                  <Button variant="outlined" component="label">
                    อัปโหลดสลิป
                    <input type="file" hidden />
                  </Button>
                  <TextField label="หมายเหตุ" multiline minRows={3} />
                  <Box
                    sx={{ display: "flex", gap: 1, justifyContent: "flex-end" }}
                  >
                    <Button href="/reserve/123/status">ยืนยัน</Button>
                    <Button variant="outlined">ยกเลิก</Button>
                  </Box>
                </Box>
              </Paper>
            </Grid>
          </Grid>
        </Paper>
      </Container>
    </>
  );
}
