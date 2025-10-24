"use client";

import Header from "@/components/Header";
import Container from "@mui/material/Container";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Box from "@mui/material/Box";

export default function ContractPage() {
  return (
    <>
      <Header />
      <Container maxWidth="md" sx={{ py: { xs: 4, md: 6 } }}>
        <Paper sx={{ p: { xs: 3, md: 4 } }}>
          <Typography variant="h6" fontWeight={900} gutterBottom>
            บันทึกข้อมูลการทำสัญญา
          </Typography>

          <Box
            sx={{
              bgcolor: "rgba(0,0,0,.03)",
              p: 2,
              height: 220,
              mb: 2,
              display: "grid",
              placeItems: "center",
            }}
          >
            กรอกข้อมูลสัญญา
          </Box>

          <Button variant="outlined" component="label" sx={{ mb: 2 }}>
            ไฟล์เอกสารสัญญา
            <input type="file" hidden />
          </Button>

          <Box sx={{ display: "flex", gap: 1 }}>
            <Button variant="contained" href="/checkout/123">
              บันทึก
            </Button>
            <Button variant="outlined">ยกเลิก</Button>
          </Box>
        </Paper>
      </Container>
    </>
  );
}
