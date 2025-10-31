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
import InputAdornment from "@mui/material/InputAdornment";
import IconButton from "@mui/material/IconButton";
import Search from "@mui/icons-material/Search";
import PlaceIcon from "@mui/icons-material/Place";
import CategoryIcon from "@mui/icons-material/Category";
import PaidIcon from "@mui/icons-material/Paid";
import Close from "@mui/icons-material/Close";
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
      bgcolor: "background.paper",
      borderRadius: 2, // 16px
      "& fieldset": { borderColor: "divider" },
      "&:hover fieldset": { borderColor: "text.primary" },
      "&.Mui-focused fieldset": {
        borderColor: "primary.main",
        boxShadow: (t: any) => `0 0 0 3px ${t.palette.action.focus}`,
      },
    },
  };

  const hasAny = !!(place || type || budget);

  const resetAll = () => {
    setPlace("");
    setType("");
    setBudget("");
    router.push("/reserve/list");
  };

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

      <Box component="form" onSubmit={handleSubmit}>
        <Grid container spacing={{ xs: 1.5, md: 2 }} alignItems="end">
          <Grid size={{ xs: 12, md: 6, lg: 5 }}>
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
            />
          </Grid>

          <Grid size={{ xs: 12, sm: 6, md: 3, lg: 2.5 }}>
            <FormControl fullWidth sx={fieldSx}>
              <InputLabel id="type-label">ประเภท</InputLabel>
              <Select
                labelId="type-label"
                label="ประเภท"
                value={type}
                onChange={(e) => setType(String(e.target.value))}
                startAdornment={
                  <InputAdornment position="start" sx={{ pl: 1 }}>
                    <CategoryIcon fontSize="small" />
                  </InputAdornment>
                }
              >
                <MenuItem value="">เลือกประเภท</MenuItem>
                <MenuItem value="office">สำนักงาน</MenuItem>
                <MenuItem value="retail">ร้านค้า</MenuItem>
                <MenuItem value="warehouse">คลังสินค้า</MenuItem>
                <MenuItem value="event">งานอีเวนต์</MenuItem>
              </Select>
            </FormControl>
          </Grid>

          <Grid size={{ xs: 12, sm: 6, md: 3, lg: 2.5 }}>
            <FormControl fullWidth sx={fieldSx}>
              <InputLabel id="budget-label">งบประมาณ</InputLabel>
              <Select
                labelId="budget-label"
                label="งบประมาณ"
                value={budget}
                onChange={(e) => setBudget(String(e.target.value))}
                startAdornment={
                  <InputAdornment position="start" sx={{ pl: 1 }}>
                    <PaidIcon fontSize="small" />
                  </InputAdornment>
                }
              >
                <MenuItem value="">เลือกงบประมาณ</MenuItem>
                <MenuItem value="0-10000">0 - 10,000 บาท</MenuItem>
                <MenuItem value="10000-50000">10,000 - 50,000 บาท</MenuItem>
                <MenuItem value="50000+">50,000+ บาท</MenuItem>
              </Select>
            </FormControl>
          </Grid>

          <Grid size={{ xs: 12, md: 12, lg: 2 }}>
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
