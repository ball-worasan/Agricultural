"use client";

import Header from "@/components/Header";
import Container from "@mui/material/Container";
import Paper from "@mui/material/Paper";
import Grid from "@mui/material/Grid";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Typography from "@mui/material/Typography";
import MenuItem from "@mui/material/MenuItem";
import Box from "@mui/material/Box";

export default function ManageSpacePage() {
  return (
    <>
      <Header />
      <Container maxWidth="lg" sx={{ py: { xs: 4, md: 6 } }}>
        <Paper sx={{ p: { xs: 3, md: 4 } }}>
          <Typography variant="h6" fontWeight={900} gutterBottom>
            จัดการข้อมูลพื้นที่ให้เช่า
          </Typography>

          <Grid container spacing={2}>
            <Grid size={{ xs: 12 }}>
              <Paper
                sx={{
                  height: 180,
                  bgcolor: "rgba(0,0,0,.04)",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  mb: 2,
                }}
              >
                รูปภาพ
              </Paper>
            </Grid>

            <Grid size={{ xs: 12, md: 6 }}>
              <TextField label="ชื่อพื้นที่" fullWidth />
            </Grid>
            <Grid size={{ xs: 12, md: 3 }}>
              <TextField select label="จังหวัด" fullWidth>
                <MenuItem value="กทม.">กรุงเทพมหานคร</MenuItem>
              </TextField>
            </Grid>
            <Grid size={{ xs: 12, md: 3 }}>
              <TextField select label="อำเภอ" fullWidth>
                <MenuItem value="ปทุมวัน">ปทุมวัน</MenuItem>
              </TextField>
            </Grid>

            <Grid size={{ xs: 12, md: 6 }}>
              <TextField
                label="ขนาดพื้นที่"
                placeholder="เช่น 200 ตร.ม."
                fullWidth
              />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <TextField label="ราคาเช่า/ปี" placeholder="xx,xxx" fullWidth />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <TextField label="% ค่ามัดจำ" placeholder="เช่น 20" fullWidth />
            </Grid>
            <Grid size={{ xs: 12 }}>
              <Box sx={{ mt: 1 }}>
                <Button variant="contained">แก้ไขข้อมูล/บันทึก</Button>
              </Box>
            </Grid>
          </Grid>
        </Paper>
      </Container>
    </>
  );
}
