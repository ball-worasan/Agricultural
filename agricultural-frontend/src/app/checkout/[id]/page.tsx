"use client";

import Header from "@/components/Header";
import Container from "@mui/material/Container";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import Grid from "@mui/material/Grid";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";

export default function CheckoutSummary() {
  return (
    <>
      <Header />
      <Container maxWidth="md" sx={{ py: { xs: 4, md: 6 } }}>
        <Paper sx={{ p: { xs: 3, md: 4 } }}>
          <Typography variant="h6" fontWeight={900} gutterBottom>
            ชำระ/ข้อมูลพื้นที่ให้เช่า
          </Typography>
          <Grid container spacing={2}>
            <Grid size={{ xs: 12, md: 6 }}>
              <TextField label="ชื่อพื้นที่" value="ชื่อพื้นที่" fullWidth />
            </Grid>
            <Grid size={{ xs: 12, md: 3 }}>
              <TextField label="จังหวัด" value="จังหวัด" fullWidth />
            </Grid>
            <Grid size={{ xs: 12, md: 3 }}>
              <TextField label="อำเภอ" value="อำเภอ" fullWidth />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <TextField label="ขนาดพื้นที่" value="xx ตร.ม." fullWidth />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <TextField label="ราคาเช่า/ปี" value="xx,xxx" fullWidth />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <TextField label="ค่าธรรมเนียม %" value="x,xxx" fullWidth />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <TextField label="รวมเงินที่ต้องชำระ" value="xx,xxx" fullWidth />
            </Grid>
            <Grid size={{ xs: 12 }}>
              <Button
                variant="contained"
                href="/checkout/123/method"
                size="large"
              >
                ยืนยันการชำระ
              </Button>
            </Grid>
          </Grid>
        </Paper>
      </Container>
    </>
  );
}
