"use client";

import Header from "@/components/Header";
import Container from "@mui/material/Container";
import Grid from "@mui/material/Grid";
import Paper from "@mui/material/Paper";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Typography from "@mui/material/Typography";
import Box from "@mui/material/Box";

export default function RegisterPage() {
  return (
    <>
      <Header />
      <Container maxWidth="lg" sx={{ py: { xs: 4, md: 6 } }}>
        <Grid container spacing={3}>
          <Grid size={{ xs: 12, md: 6 }}>
            <Paper
              sx={{
                height: 520,
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                bgcolor: "rgba(0,0,0,.04)",
              }}
            >
              รูปภาพ
            </Paper>
          </Grid>
          <Grid size={{ xs: 12, md: 6 }}>
            <Paper sx={{ p: { xs: 3, md: 4 } }}>
              <Typography variant="h5" fontWeight={900} gutterBottom>
                สมัครสมาชิก
              </Typography>
              <Box sx={{ display: "grid", gap: 2 }}>
                <TextField label="ชื่อ–นามสกุล" fullWidth />
                <TextField label="ที่อยู่" fullWidth />
                <TextField label="เบอร์โทร" fullWidth />
                <TextField label="ชื่อผู้ใช้งาน" fullWidth />
                <Grid container spacing={2}>
                  <Grid size={{ xs: 12, md: 6 }}>
                    <TextField label="รหัสผ่าน" type="password" fullWidth />
                  </Grid>
                  <Grid size={{ xs: 12, md: 6 }}>
                    <TextField
                      label="ยืนยันรหัสผ่าน"
                      type="password"
                      fullWidth
                    />
                  </Grid>
                </Grid>
                <Button variant="contained" size="large">
                  สมัครสมาชิก
                </Button>
                <Box sx={{ textAlign: "center" }}>
                  มีบัญชีอยู่แล้ว?{" "}
                  <Button href="/auth/login" variant="text">
                    เข้าสู่ระบบ
                  </Button>
                </Box>
              </Box>
            </Paper>
          </Grid>
        </Grid>
      </Container>
    </>
  );
}
