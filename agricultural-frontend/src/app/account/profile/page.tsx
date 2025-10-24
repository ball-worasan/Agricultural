"use client";

import Header from "@/components/Header";
import Container from "@mui/material/Container";
import Grid from "@mui/material/Grid";
import Paper from "@mui/material/Paper";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Typography from "@mui/material/Typography";
import Avatar from "@mui/material/Avatar";
import Box from "@mui/material/Box";

export default function ProfilePage() {
  return (
    <>
      <Header />
      <Container maxWidth="lg" sx={{ py: { xs: 4, md: 6 } }}>
        <Paper sx={{ p: { xs: 3, md: 4 }, mb: 4 }}>
          <Typography variant="h6" fontWeight={900} gutterBottom>
            จัดการข้อมูลส่วนตัว
          </Typography>
          <Grid container spacing={3} alignItems="center">
            <Grid size={{ xs: 12, md: 3 }}>
              <Avatar sx={{ width: 120, height: 120, bgcolor: "primary.main" }}>
                รูปภาพ
              </Avatar>
            </Grid>
            <Grid size={{ xs: 12, md: 9 }}>
              <Grid container spacing={2}>
                <Grid size={{ xs: 12, md: 6 }}>
                  <TextField label="ชื่อ–นามสกุล" fullWidth />
                </Grid>
                <Grid size={{ xs: 12, md: 6 }}>
                  <TextField label="ชื่อผู้ใช้" fullWidth />
                </Grid>
                <Grid size={{ xs: 12, md: 6 }}>
                  <TextField label="ที่อยู่" fullWidth />
                </Grid>
                <Grid size={{ xs: 12, md: 6 }}>
                  <TextField label="เบอร์โทร" fullWidth />
                </Grid>
              </Grid>
              <Box sx={{ mt: 2 }}>
                <Button variant="contained">แก้ไขข้อมูล/บันทึก</Button>
              </Box>
            </Grid>
          </Grid>
        </Paper>

        <Paper sx={{ p: { xs: 3, md: 4 } }}>
          <Typography variant="h6" fontWeight={900} gutterBottom>
            เปลี่ยนรหัสผ่าน
          </Typography>
          <Grid container spacing={2}>
            <Grid size={{ xs: 12, md: 4 }}>
              <TextField label="รหัสผ่านใหม่" type="password" fullWidth />
            </Grid>
            <Grid size={{ xs: 12, md: 4 }}>
              <TextField label="ยืนยันรหัสผ่าน" type="password" fullWidth />
            </Grid>
            <Grid size={{ xs: 12, md: 4 }}>
              <Button
                fullWidth
                size="large"
                variant="contained"
                sx={{ height: 56 }}
              >
                เปลี่ยนรหัสผ่าน
              </Button>
            </Grid>
          </Grid>
        </Paper>
      </Container>
    </>
  );
}
