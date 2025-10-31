// src/components/HomeFilterAndList.tsx
"use client";

import { useMemo, useState } from "react";
import Link from "next/link";

import Container from "@mui/material/Container";
import Paper from "@mui/material/Paper";
import Grid from "@mui/material/Grid";
import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import FormControl from "@mui/material/FormControl";
import Select from "@mui/material/Select";
import MenuItem from "@mui/material/MenuItem";
import InputLabel from "@mui/material/InputLabel";
import Divider from "@mui/material/Divider";

import Image from "@mui/icons-material/Image";
import Place from "@mui/icons-material/Place";
import AccessTime from "@mui/icons-material/AccessTime";

/** ---- mock data ---- */
type Unit = "ปี" | "เดือน" | "วัน";
type Status = "available" | "reserved";

type Listing = {
  id: string;
  title: string;
  province: string;
  district: string;
  postedAt: string; // YYYY-MM-DD
  price: number;
  unit: Unit;
  status: Status;
  image?: string;
};

const LISTINGS: Listing[] = [
  {
    id: "101",
    title: "ปล่อยเช่าที่ดินเปล่า ต.ภูสิงห์ อ.สหัสขันธ์ ติดเขื่อนลำปาว",
    province: "กาฬสินธุ์",
    district: "สหัสขันธ์",
    postedAt: "2025-09-08",
    price: 35000,
    unit: "ปี",
    status: "available",
  },
  {
    id: "102",
    title: "ปล่อยเช่าที่ดินเปล่า ต.ภูสิงห์ อ.สหัสขันธ์",
    province: "กาฬสินธุ์",
    district: "สหัสขันธ์",
    postedAt: "2025-09-08",
    price: 28000,
    unit: "ปี",
    status: "reserved",
  },
  {
    id: "103",
    title: "สำนักงานใจกลางเมือง",
    province: "กรุงเทพมหานคร",
    district: "ปทุมวัน",
    postedAt: "2025-08-30",
    price: 25000,
    unit: "เดือน",
    status: "available",
  },
];

const PROVINCES = [
  "ทั้งหมด",
  "กรุงเทพมหานคร",
  "เชียงใหม่",
  "ภูเก็ต",
  "กาฬสินธุ์",
];
const PRICE_FILTERS = [
  { value: "all", label: "ทั้งหมด" },
  { value: "lt10000", label: "< 10,000" },
  { value: "10to50", label: "10,000 - 50,000" },
  { value: "gt50000", label: "> 50,000" },
] as const;

const SORTS = [
  { value: "newest", label: "ลงประกาศล่าสุด" },
  { value: "priceAsc", label: "ราคาต่ำไปสูง" },
  { value: "priceDesc", label: "ราคาสูงไปต่ำ" },
] as const;

/** ---- utils ---- */
function withinPriceRange(
  value: number,
  f: (typeof PRICE_FILTERS)[number]["value"]
) {
  switch (f) {
    case "lt10000":
      return value < 10000;
    case "10to50":
      return value >= 10000 && value <= 50000;
    case "gt50000":
      return value > 50000;
    default:
      return true;
  }
}

/** ---- ribbon (responsive) ---- */
function StatusRibbon({ status }: { status: Status }) {
  const bg = status === "reserved" ? "#b42318" : "#1f6f54";
  const label = status === "reserved" ? "ติดจอง" : "ว่าง";
  return (
    <Box
      sx={{
        position: "absolute",
        top: { xs: 40, sm: 56, md: 64, lg: 72 },
        left: { xs: -16, sm: -20, md: -22, lg: -24 },
        transform: "rotate(-45deg)",
        transformOrigin: "left top",
        bgcolor: bg,
        color: "#fff",
        width: { xs: 92, sm: 108, md: 120 },
        height: { xs: 22, sm: 24, md: 26 },
        display: "inline-flex",
        alignItems: "center",
        justifyContent: "center",
        fontSize: { xs: 11, sm: 12, md: 13 },
        fontWeight: 800,
        letterSpacing: 0.5,
        boxShadow: "0 2px 6px rgba(0,0,0,.15)",
        userSelect: "none",
        whiteSpace: "nowrap",
        zIndex: 1,
        pointerEvents: "none",
      }}
    >
      {label}
    </Box>
  );
}

/** ---- แถวรายการ (ปรับขนาดกริด + ฟอนต์) ---- */
function ListingRow({ it }: { it: Listing }) {
  return (
    <Paper
      component={Link}
      href={`/listing/${it.id}`}
      elevation={0}
      sx={{
        p: { xs: 1.25, sm: 1.75, md: 2 },
        border: 1,
        borderColor: "divider",
        borderRadius: 1.5,
        display: "grid",
        gridTemplateColumns: {
          xs: "100px 1fr", // mobile
          sm: "140px 1fr 160px", // tablet
          md: "170px 1fr 200px", // small desktop
          lg: "200px 1fr 240px", // large desktop
        },
        columnGap: { xs: 1.25, sm: 2, md: 2.25 },
        rowGap: 1,
        alignItems: "center",
        textDecoration: "none",
        color: "inherit",
        position: "relative",
        "&:hover": {
          borderColor: "primary.main",
          boxShadow: "0 6px 18px rgba(0,0,0,.06)",
        },
        overflow: "hidden",
      }}
    >
      {/* ซ้าย: รูป + ริบบอน */}
      <Box
        sx={{
          position: "relative",
          width: "100%",
          height: { xs: 80, sm: 96, md: 112, lg: 128 },
          borderRadius: 1,
          overflow: "hidden",
        }}
      >
        <StatusRibbon status={it.status} />
        <Box
          sx={{
            position: "absolute",
            inset: 0,
            bgcolor: "rgba(0,0,0,.06)",
            border: "1px solid rgba(0,0,0,.06)",
            display: "grid",
            placeItems: "center",
            color: "text.secondary",
          }}
          aria-label="รูปภาพพื้นที่ให้เช่า"
        >
          {it.image ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={it.image}
              alt={it.title}
              style={{ width: "100%", height: "100%", objectFit: "cover" }}
            />
          ) : (
            <Box
              sx={{
                display: "flex",
                alignItems: "center",
                gap: 1,
                fontSize: 12,
              }}
            >
              <Image fontSize="small" /> รูปภาพพื้นที่ให้เช่า
            </Box>
          )}
        </Box>
      </Box>

      {/* กลาง: รายละเอียด */}
      <Box
        sx={{
          minWidth: 0,
          display: "flex",
          flexDirection: "column",
          alignSelf: "stretch",
        }}
      >
        <Typography
          sx={{
            fontWeight: 900,
            fontSize: { xs: 15, sm: 16.5, md: 18, lg: 19 },
            lineHeight: 1.25,
            mb: 0.5,
            display: "-webkit-box",
            WebkitLineClamp: { xs: 2, md: 1 },
            WebkitBoxOrient: "vertical",
            overflow: "hidden",
          }}
        >
          {it.title}
        </Typography>

        <Box
          sx={{
            display: "flex",
            alignItems: "center",
            gap: 1,
            color: "text.secondary",
            mb: 0.5,
            flexWrap: "wrap",
            fontSize: { xs: 12.5, sm: 13, md: 13.5 },
          }}
        >
          <Box
            sx={{
              display: "flex",
              alignItems: "center",
              gap: 0.5,
              minWidth: 0,
            }}
          >
            <Place sx={{ fontSize: { xs: 16, sm: 18 } }} />
            <Typography
              component="span"
              sx={{
                overflow: "hidden",
                textOverflow: "ellipsis",
                whiteSpace: "nowrap",
                fontSize: "inherit",
              }}
            >
              อ.{it.district} จ.{it.province}
            </Typography>
          </Box>
        </Box>

        {/* ลงประกาศ (ดันชิดล่างคอลัมน์) */}
        <Box
          sx={{
            display: "flex",
            alignItems: "center",
            gap: 0.5,
            color: "text.secondary",
            mt: "auto",
            fontSize: { xs: 12, sm: 12.5, md: 13 },
          }}
        >
          <AccessTime sx={{ fontSize: { xs: 16, sm: 18 } }} />
          <Typography component="span" sx={{ fontSize: "inherit" }}>
            ลงประกาศ : {new Date(it.postedAt).toLocaleDateString("th-TH")}
          </Typography>
        </Box>
      </Box>

      {/* ขวา: ราคา */}
      <Box
        sx={{
          justifySelf: { xs: "start", sm: "end" },
          alignSelf: { xs: "end", sm: "center" },
          mt: { xs: 1, sm: 0 },
          minWidth: { sm: 140, md: 180, lg: 220 },
        }}
      >
        <Typography
          sx={{
            fontWeight: 900,
            color: "primary.main",
            textAlign: { xs: "left", sm: "right" },
            fontSize: { xs: 16, sm: 18, md: 20, lg: 22 },
            lineHeight: 1.2,
          }}
        >
          {it.price.toLocaleString("th-TH")}/{it.unit}
        </Typography>
      </Box>
    </Paper>
  );
}

/** ---- กล่องกรอง + กล่องรายการ ---- */
export default function HomeFilterAndList() {
  const [province, setProvince] = useState<string>("ทั้งหมด");
  const [price, setPrice] =
    useState<(typeof PRICE_FILTERS)[number]["value"]>("all");
  const [sortBy, setSortBy] =
    useState<(typeof SORTS)[number]["value"]>("newest");

  const data = useMemo(() => {
    let arr = LISTINGS.filter((x) =>
      province === "ทั้งหมด" ? true : x.province === province
    ).filter((x) => withinPriceRange(x.price, price));
    switch (sortBy) {
      case "priceAsc":
        arr = [...arr].sort((a, b) => a.price - b.price);
        break;
      case "priceDesc":
        arr = [...arr].sort((a, b) => b.price - a.price);
        break;
      default:
        arr = [...arr].sort((a, b) => (a.postedAt < b.postedAt ? 1 : -1));
    }
    return arr;
  }, [province, price, sortBy]);

  return (
    <Container maxWidth="lg" sx={{ py: { xs: 3, md: 5 } }}>
      {/* Box 1: ฟิลเตอร์ */}
      <Paper
        elevation={0}
        sx={{
          p: { xs: 1.25, sm: 1.75, md: 2 },
          border: 1,
          borderColor: "divider",
          borderRadius: 2,
          mb: { xs: 2, md: 3 },
        }}
      >
        <Grid
          container
          spacing={1.5}
          alignItems="center"
          sx={{
            "& > .MuiGrid-item": { minWidth: 0 },
            columnGap: { md: 2 }, // เว้นช่องไฟแนวนอนเล็กน้อยบนเดสก์ท็อป
          }}
        >
          {/* ซ้าย: จังหวัด / ราคา */}
          <Grid
            size={{ xs: 12, md: "auto" }}
            sx={{
              display: "flex",
              gap: 1,
              flexWrap: "wrap",
              flexShrink: 0, // อย่าให้ยืด/หดจนดันขวา
            }}
          >
            <FormControl
              size="small"
              sx={{ minWidth: 160, flex: { xs: 1, sm: 0 } }}
            >
              <InputLabel id="province">จังหวัด</InputLabel>
              <Select
                labelId="province"
                label="จังหวัด"
                value={province}
                onChange={(e) => setProvince(String(e.target.value))}
                MenuProps={{ disableScrollLock: true }}
              >
                {PROVINCES.map((p) => (
                  <MenuItem key={p} value={p}>
                    {p}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>

            <FormControl
              size="small"
              sx={{ minWidth: 140, flex: { xs: 1, sm: 0 } }}
            >
              <InputLabel id="price">ราคา</InputLabel>
              <Select
                labelId="price"
                label="ราคา"
                value={price}
                onChange={(e) => setPrice(e.target.value as any)}
                MenuProps={{ disableScrollLock: true }}
              >
                {PRICE_FILTERS.map((p) => (
                  <MenuItem key={p.value} value={p.value}>
                    {p.label}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          </Grid>

          {/* เส้นแบ่ง: แสดงเฉพาะบนจอเล็ก เพื่อคั่นซ้าย-ขวาเมื่อเรียงเป็นแนวตั้ง */}
          <Grid size={{ xs: 12 }} sx={{ display: { xs: "block", md: "none" } }}>
            <Divider />
          </Grid>

          {/* ขวา: เรียงตาม (ดันไปชิดขวาบน md+) */}
          <Grid
            size={{ xs: 12, md: "auto" }}
            sx={{
              ml: { md: "auto" }, // ดันไปขวา
            }}
          >
            <Box
              sx={{
                display: "flex",
                alignItems: "center",
                gap: 1,
                flexWrap: "wrap",
                justifyContent: { xs: "flex-start", md: "flex-end" },
              }}
            >
              <Typography
                variant="body2"
                sx={{ color: "text.secondary", fontSize: { xs: 12.5, sm: 13 } }}
              >
                เรียงตาม :
              </Typography>
              <FormControl
                size="small"
                sx={{ minWidth: 180, flex: { xs: 1, sm: 0 } }}
              >
                <InputLabel id="sort">ลงประกาศล่าสุด</InputLabel>
                <Select
                  labelId="sort"
                  label="ลงประกาศล่าสุด"
                  value={sortBy}
                  onChange={(e) => setSortBy(e.target.value as any)}
                  MenuProps={{ disableScrollLock: true }}
                >
                  {SORTS.map((s) => (
                    <MenuItem key={s.value} value={s.value}>
                      {s.label}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>
            </Box>
          </Grid>
        </Grid>
      </Paper>

      {/* Box 2: รายการ */}
      <Box sx={{ display: "grid", gap: { xs: 1, sm: 1.25, md: 1.5 } }}>
        {data.length === 0 ? (
          <Paper
            elevation={0}
            sx={{
              p: { xs: 3, md: 4 },
              textAlign: "center",
              border: 1,
              borderColor: "divider",
              borderRadius: 2,
            }}
          >
            <Typography>ไม่พบรายการที่ตรงเงื่อนไข</Typography>
          </Paper>
        ) : (
          data.map((it) => <ListingRow key={it.id} it={it} />)
        )}
      </Box>
    </Container>
  );
}
