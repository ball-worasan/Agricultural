"use client";

import Header from "@/components/Header";
import Container from "@mui/material/Container";
import Grid from "@mui/material/Grid";
import Paper from "@mui/material/Paper";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Typography from "@mui/material/Typography";
import Box from "@mui/material/Box";

export default function LoginPage() {
  return (
    <>
      <Header />
      <Container maxWidth="lg" sx={{ py: { xs: 4, md: 6 } }}>
        <Grid container spacing={3}>
          <Grid size={{ xs: 12, md: 6 }}>
            <Paper
              sx={{
                height: 420,
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
                เข้าสู่ระบบ
              </Typography>
              <Box sx={{ display: "grid", gap: 2 }}>
                <TextField label="ชื่อผู้ใช้งาน" fullWidth />
                <TextField label="รหัสผ่าน" type="password" fullWidth />
                <Box sx={{ display: "flex", justifyContent: "flex-end" }}>
                  <Button size="small">ลืมรหัสผ่าน?</Button>
                </Box>
                <Button variant="contained" size="large">
                  เข้าสู่ระบบ
                </Button>
                <Box sx={{ textAlign: "center", mt: 1 }}>
                  ยังไม่มีบัญชี?{" "}
                  <Button href="/auth/register" variant="text">
                    สมัครสมาชิก
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
