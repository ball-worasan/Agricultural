"use client";

import Paper from "@mui/material/Paper";
import Grid from "@mui/material/Grid"; // ✅ ใช้ Grid v6 (มี prop size)
import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import TextField from "@mui/material/TextField";
import FormControl from "@mui/material/FormControl";
import Select from "@mui/material/Select";
import MenuItem from "@mui/material/MenuItem";
import InputLabel from "@mui/material/InputLabel";
import Button from "@mui/material/Button";

export default function SearchSection() {
  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    alert("กำลังค้นหาพื้นที่เช่าที่ตรงกับความต้องการของคุณ...");
  };

  const fieldSx = {
    "& .MuiOutlinedInput-root": {
      "& fieldset": { borderColor: "black" }, // กรอบดำ
      "&:hover fieldset": { borderColor: "black" },
      "&.Mui-focused fieldset": { borderColor: "black" },
      backgroundColor: "#ffffff", // input ขาว
      color: "green", // ข้อความเขียว
    },
    "& .MuiInputBase-input": {
      color: "green",
    },
    "& .MuiInputLabel-root": { color: "green" },
    "& .MuiInputLabel-root.Mui-focused": { color: "green" },
  };

  return (
    <Paper
      sx={{
        p: { xs: 3, md: 5 },
        backgroundColor: "#ffffff", // พื้นหลังบล็อกขาว
        border: "1px solid #1C352D",
      }}
    >
      <Typography
        variant="h4"
        align="center"
        fontWeight={900}
        sx={{
          mb: 3,
          letterSpacing: "-0.5px",
          color: "green", // หัวข้อเขียว
        }}
      >
        ค้นหาพื้นที่เช่า
      </Typography>

      <Box component="form" onSubmit={handleSubmit}>
        <Grid container spacing={2} alignItems="end">
          <Grid size={{ xs: 12, md: 6, lg: 4 }}>
            <TextField
              fullWidth
              label="📍 สถานที่"
              placeholder="เช่น กรุงเทพ, เชียงใหม่"
              sx={fieldSx}
            />
          </Grid>

          <Grid size={{ xs: 12, sm: 6, md: 3 }}>
            <FormControl fullWidth sx={fieldSx}>
              <InputLabel id="type-label">🏢 ประเภท</InputLabel>
              <Select labelId="type-label" defaultValue="">
                <MenuItem value="">เลือกประเภท</MenuItem>
                <MenuItem value="office">สำนักงาน</MenuItem>
                <MenuItem value="retail">ร้านค้า</MenuItem>
                <MenuItem value="warehouse">คลังสินค้า</MenuItem>
                <MenuItem value="event">งานอีเวนต์</MenuItem>
              </Select>
            </FormControl>
          </Grid>

          <Grid size={{ xs: 12, sm: 6, md: 3 }}>
            <FormControl fullWidth sx={fieldSx}>
              <InputLabel id="budget-label">💰 งบประมาณ</InputLabel>
              <Select labelId="budget-label" defaultValue="">
                <MenuItem value="">เลือกงบประมาณ</MenuItem>
                <MenuItem value="0-10000">0 - 10,000 บาท</MenuItem>
                <MenuItem value="10000-50000">10,000 - 50,000 บาท</MenuItem>
                <MenuItem value="50000+">50,000+ บาท</MenuItem>
              </Select>
            </FormControl>
          </Grid>

          <Grid size={{ xs: 12, md: 2 }}>
            <Button
              type="submit"
              variant="contained"
              size="large"
              sx={{
                minWidth: 160,
                height: 56,
                backgroundColor: "green",
                color: "white",
                "&:hover": { backgroundColor: "darkgreen" },
              }}
            >
              🔍 ค้นหา
            </Button>
          </Grid>
        </Grid>
      </Box>
    </Paper>
  );
}
