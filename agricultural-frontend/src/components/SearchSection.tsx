"use client";

import Paper from "@mui/material/Paper";
import Grid from "@mui/material/Grid";
import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import TextField from "@mui/material/TextField";
import FormControl from "@mui/material/FormControl";
import Select from "@mui/material/Select";
import MenuItem from "@mui/material/MenuItem";
import InputLabel from "@mui/material/InputLabel";
import Button from "@mui/material/Button";
import { useRouter } from "next/navigation";
import { useState } from "react";

export default function SearchSection() {
  const router = useRouter();
  const [place, setPlace] = useState("");
  const [type, setType] = useState("");
  const [budget, setBudget] = useState("");

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const params = new URLSearchParams();
    if (place) params.set("place", place);
    if (type) params.set("type", type);
    if (budget) params.set("budget", budget);
    router.push(`/reserve/list?${params.toString()}`);
  };

  const fieldSx = {
    "& .MuiOutlinedInput-root": {
      "& fieldset": { borderColor: "black" },
      "&:hover fieldset": { borderColor: "black" },
      "&.Mui-focused fieldset": { borderColor: "black" },
      backgroundColor: "#ffffff",
      color: "green",
    },
    "& .MuiInputBase-input": { color: "green" },
    "& .MuiInputLabel-root": { color: "green" },
    "& .MuiInputLabel-root.Mui-focused": { color: "green" },
  };

  return (
    <Paper
      sx={{
        p: { xs: 2.5, md: 5 },
        backgroundColor: "#ffffff",
        border: "1px solid #1C352D",
      }}
    >
      <Typography
        variant="h4"
        align="center"
        fontWeight={900}
        sx={{
          mb: { xs: 2, md: 3 },
          letterSpacing: "-0.5px",
          color: "green",
          px: { xs: 1, md: 0 },
        }}
      >
        ค้นหาพื้นที่เช่า
      </Typography>

      <Box component="form" onSubmit={handleSubmit}>
        <Grid container spacing={{ xs: 1.5, md: 2 }} alignItems="end">
          <Grid size={{ xs: 12, md: 6, lg: 4 }}>
            <TextField
              fullWidth
              label="📍 สถานที่"
              placeholder="เช่น กรุงเทพ, เชียงใหม่"
              sx={fieldSx}
              value={place}
              onChange={(e) => setPlace(e.target.value)}
            />
          </Grid>

          <Grid size={{ xs: 12, sm: 6, md: 3 }}>
            <FormControl fullWidth sx={fieldSx}>
              <InputLabel id="type-label">🏢 ประเภท</InputLabel>
              <Select
                labelId="type-label"
                value={type}
                onChange={(e) => setType(String(e.target.value))}
              >
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
              <Select
                labelId="budget-label"
                value={budget}
                onChange={(e) => setBudget(String(e.target.value))}
              >
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
                width: { xs: "100%", md: "auto" },
                minWidth: { xs: "100%", md: 160 },
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
