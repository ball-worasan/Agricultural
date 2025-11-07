"use client";

import Paper from "@mui/material/Paper";
import Grid from "@mui/material/Grid";
import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import InputAdornment from "@mui/material/InputAdornment";
import IconButton from "@mui/material/IconButton";
import MenuItem from "@mui/material/MenuItem";

import Search from "@mui/icons-material/Search";
import PlaceIcon from "@mui/icons-material/Place";
import CategoryIcon from "@mui/icons-material/Category";
import PaidIcon from "@mui/icons-material/Paid";
import Close from "@mui/icons-material/Close";

import { useRouter } from "next/navigation";
import { useState, useMemo } from "react";

export default function SearchSection() {
  const router = useRouter();
  const [place, setPlace] = useState("");
  const [type, setType] = useState("");
  const [budget, setBudget] = useState("");

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const params = new URLSearchParams();
    if (place.trim()) params.set("place", place.trim());
    if (type) params.set("type", type);
    if (budget) params.set("budget", budget);
    router.push(`/reserve/list?${params.toString()}`);
  };

  const hasAny = useMemo(
    () => !!(place || type || budget),
    [place, type, budget]
  );

  const resetAll = () => {
    setPlace("");
    setType("");
    setBudget("");
    router.push("/reserve/list");
  };

  const fieldSx = {
    "& .MuiOutlinedInput-root": {
      bgcolor: "background.paper",
      borderRadius: 2,
      "& fieldset": { borderColor: "divider" },
      "&:hover fieldset": { borderColor: "text.primary" },
      "&.Mui-focused fieldset": {
        borderColor: "primary.main",
        boxShadow: (t: any) => `0 0 0 3px ${t.palette.action.focus}`,
      },
    },
  } as const;

  return (
    <Paper
      elevation={0}
      sx={{
        p: { xs: 2.5, md: 5 },
        border: 1,
        borderColor: "divider",
        borderRadius: 3,
        bgcolor: "background.paper",
      }}
    >
      <Typography
        variant="h4"
        align="center"
        fontWeight={900}
        sx={{
          mb: { xs: 2, md: 3 },
          letterSpacing: "-0.5px",
          background: "linear-gradient(135deg, var(--brand-1), var(--brand-2))",
          WebkitBackgroundClip: "text",
          color: "transparent",
        }}
      >
        ค้นหาพื้นที่เช่า
      </Typography>

      <Box
        component="form"
        onSubmit={handleSubmit}
        role="search"
        aria-label="ฟอร์มค้นหาพื้นที่เช่า"
      >
        <Grid container spacing={{ xs: 1.5, md: 2 }} alignItems="end">
          {/* สถานที่ */}
          <Grid size={{ xs: 1, md: 6, lg: 5 }}>
            <TextField
              fullWidth
              label="สถานที่"
              placeholder="เช่น กรุงเทพ, เชียงใหม่"
              value={place}
              onChange={(e) => setPlace(e.target.value)}
              sx={fieldSx}
              InputProps={{
                startAdornment: (
                  <InputAdornment position="start">
                    <PlaceIcon />
                  </InputAdornment>
                ),
                endAdornment: place ? (
                  <InputAdornment position="end">
                    <IconButton
                      aria-label="ล้างสถานที่"
                      onClick={() => setPlace("")}
                      edge="end"
                    >
                      <Close />
                    </IconButton>
                  </InputAdornment>
                ) : undefined,
              }}
              inputProps={{ "aria-label": "สถานที่" }}
            />
          </Grid>

          {/* ประเภท — ใช้ TextField select เพื่อรองรับ startAdornment ได้ง่าย */}
          <Grid size={{ xs: 12, md: 6, lg: 2.5 }}>
            <TextField
              fullWidth
              select
              label="ประเภท"
              value={type}
              onChange={(e) => setType(String(e.target.value))}
              sx={fieldSx}
              InputProps={{
                startAdornment: (
                  <InputAdornment position="start" sx={{ pl: 0.5 }}>
                    <CategoryIcon fontSize="small" />
                  </InputAdornment>
                ),
              }}
              SelectProps={{
                displayEmpty: true,
                renderValue: (v) => (v ? (v as string) : "เลือกประเภท"),
              }}
              inputProps={{ "aria-label": "ประเภทพื้นที่" }}
            >
              <MenuItem value="">
                <em>เลือกประเภท</em>
              </MenuItem>
              <MenuItem value="office">สำนักงาน</MenuItem>
              <MenuItem value="retail">ร้านค้า</MenuItem>
              <MenuItem value="warehouse">คลังสินค้า</MenuItem>
              <MenuItem value="event">งานอีเวนต์</MenuItem>
            </TextField>
          </Grid>

          {/* งบประมาณ — ใช้ TextField select + ไอคอน */}
          <Grid size={{ xs: 12, sm: 6, md: 3, lg: 2.5 }}>
            <TextField
              fullWidth
              select
              label="งบประมาณ"
              value={budget}
              onChange={(e) => setBudget(String(e.target.value))}
              sx={fieldSx}
              InputProps={{
                startAdornment: (
                  <InputAdornment position="start" sx={{ pl: 0.5 }}>
                    <PaidIcon fontSize="small" />
                  </InputAdornment>
                ),
              }}
              SelectProps={{
                displayEmpty: true,
                renderValue: (v) => (v ? (v as string) : "เลือกงบประมาณ"),
              }}
              inputProps={{ "aria-label": "ช่วงงบประมาณ" }}
            >
              <MenuItem value="">
                <em>เลือกงบประมาณ</em>
              </MenuItem>
              <MenuItem value="0-10000">0 - 10,000 บาท</MenuItem>
              <MenuItem value="10000-50000">10,000 - 50,000 บาท</MenuItem>
              <MenuItem value="50000+">50,000+ บาท</MenuItem>
            </TextField>
          </Grid>

          {/* ปุ่ม */}
          <Grid size={{ xs: 12, lg: 6 }}>
            <Box sx={{ display: "flex", gap: 1 }}>
              <Button
                type="submit"
                variant="contained"
                size="large"
                startIcon={<Search />}
                sx={{ flex: 1, height: 56 }}
              >
                ค้นหา
              </Button>

              {hasAny && (
                <Button
                  variant="outlined"
                  color="inherit"
                  size="large"
                  onClick={resetAll}
                  startIcon={<Close />}
                  sx={{ height: 56, whiteSpace: "nowrap" }}
                >
                  เคลียร์
                </Button>
              )}
            </Box>
          </Grid>
        </Grid>
      </Box>
    </Paper>
  );
}
