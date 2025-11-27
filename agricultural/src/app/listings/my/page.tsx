"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import Header from "@/components/Header";
import { LISTINGS, Listing } from "@/data/listings";

import Container from "@mui/material/Container";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import Box from "@mui/material/Box";
import Grid from "@mui/material/Grid";
import Divider from "@mui/material/Divider";
import Chip from "@mui/material/Chip";
import Button from "@mui/material/Button";
import Select from "@mui/material/Select";
import MenuItem from "@mui/material/MenuItem";

import PlaceIcon from "@mui/icons-material/Place";
import CalendarMonthIcon from "@mui/icons-material/CalendarMonth";
import ArrowDropDownIcon from "@mui/icons-material/ArrowDropDown";
import ImageIcon from "@mui/icons-material/Image";

// ---- helpers ----
const THB = (n: number) =>
  new Intl.NumberFormat("th-TH", {
    style: "currency",
    currency: "THB",
    maximumFractionDigits: 0,
  }).format(n);

const formatTHDate = (iso: string) => new Date(iso).toLocaleDateString("th-TH");

// ==== Page ====
type SortKey = "posted_desc" | "posted_asc" | "price_desc" | "price_asc";

export default function MyListingsPage() {
  // NOTE: ในงานจริงควร filter ตาม ownerId ของผู้ใช้ที่ login
  const myListings = LISTINGS; // mock: แสดงทั้งหมด

  const [sortBy, setSortBy] = useState<SortKey>("posted_desc");

  const sorted = useMemo(() => {
    const arr = [...myListings];
    switch (sortBy) {
      case "posted_desc":
        return arr.sort(
          (a, b) => +new Date(b.postedAt) - +new Date(a.postedAt)
        );
      case "posted_asc":
        return arr.sort(
          (a, b) => +new Date(a.postedAt) - +new Date(b.postedAt)
        );
      case "price_desc":
        return arr.sort((a, b) => b.price - a.price);
      case "price_asc":
        return arr.sort((a, b) => a.price - b.price);
      default:
        return arr;
    }
  }, [myListings, sortBy]);

  return (
    <>
      <Header />
      <Container maxWidth="lg" sx={{ py: { xs: 3, md: 5 } }}>
        <Grid container spacing={2} alignItems="center" sx={{ mb: 1 }}>
          <Grid size={{ xs: 12, md: 6 }}>
            <Typography variant="h6" fontWeight={900}>
              พื้นที่ให้เช่าของฉัน
            </Typography>
          </Grid>
          <Grid
            size={{ xs: 12, md: 6 }}
            sx={{
              display: "flex",
              justifyContent: { xs: "flex-start", md: "flex-end" },
            }}
          >
            <Box sx={{ display: "flex", alignItems: "center", gap: 1 }}>
              <Typography variant="body2" sx={{ color: "text.secondary" }}>
                เรียงตาม :
              </Typography>
              <Select
                size="small"
                value={sortBy}
                onChange={(e) => setSortBy(e.target.value as SortKey)}
                IconComponent={ArrowDropDownIcon}
                sx={{ minWidth: 180 }}
              >
                <MenuItem value="posted_desc">ลงประกาศล่าสุด</MenuItem>
                <MenuItem value="posted_asc">ลงประกาศเก่าสุด</MenuItem>
                <MenuItem value="price_desc">ราคาสูง → ต่ำ</MenuItem>
                <MenuItem value="price_asc">ราคาต่ำ → สูง</MenuItem>
              </Select>
            </Box>
          </Grid>
        </Grid>

        <Box sx={{ display: "grid", gap: 1.5, mt: 1 }}>
          {sorted.map((item) => (
            <ListingRow key={item.id} item={item} />
          ))}
        </Box>

        <Divider sx={{ my: 3 }} />

        <Box sx={{ display: "grid", placeItems: "center" }}>
          <Button
            component={Link}
            href="/listings/new"
            variant="contained"
            size="large"
            sx={{ minWidth: 260, borderRadius: 2 }}
          >
            เพิ่มพื้นที่ให้เช่า
          </Button>
        </Box>
      </Container>
    </>
  );
}

// ==== Row Card ====
function ListingRow({ item }: { item: Listing }) {
  const isReserved = item.status === "reserved";

  return (
    <Paper
      variant="outlined"
      sx={{
        borderRadius: 2,
        overflow: "hidden",
        p: { xs: 1.5, md: 2 },
        position: "relative",
      }}
    >
      {/* reserved ribbon */}
      {isReserved && (
        <Box
          sx={{
            position: "absolute",
            left: { xs: -34, sm: -28 },
            top: 10,
            bgcolor: "grey.700",
            color: "#fff",
            px: 3,
            py: 0.5,
            transform: "rotate(-30deg)",
            fontSize: 12,
            letterSpacing: 0.5,
          }}
        >
          ติดจอง
        </Box>
      )}

      <Grid container spacing={2} alignItems="center">
        {/* image */}
        <Grid size={{ xs: 12, sm: 4, md: 3 }}>
          <Paper
            variant="outlined"
            sx={{
              borderRadius: 1.5,
              overflow: "hidden",
              bgcolor: "rgba(0,0,0,.04)",
              height: 120,
              display: "grid",
              placeItems: "center",
              position: "relative",
            }}
          >
            {item.image ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img
                src={item.image}
                alt={item.title}
                style={{ width: "100%", height: "100%", objectFit: "cover" }}
              />
            ) : (
              <Box
                sx={{
                  display: "flex",
                  alignItems: "center",
                  gap: 1,
                  color: "text.secondary",
                }}
              >
                <ImageIcon />
                รูปภาพพื้นที่ให้เช่า
              </Box>
            )}
          </Paper>
        </Grid>

        {/* text */}
        <Grid size={{ xs: 12, sm: 8, md: 7 }}>
          <Box sx={{ pr: { md: 2 } }}>
            <Typography
              component={Link}
              href={`/listing/${item.id}`}
              sx={{
                fontWeight: 800,
                color: "text.primary",
                textDecoration: "none",
                "&:hover": { textDecoration: "underline" },
              }}
            >
              {item.title}
            </Typography>

            <Box
              sx={{
                display: "flex",
                alignItems: "center",
                gap: 1.25,
                mt: 0.75,
                color: "text.secondary",
                flexWrap: "wrap",
              }}
            >
              <Box
                sx={{ display: "inline-flex", alignItems: "center", gap: 0.5 }}
              >
                <PlaceIcon fontSize="small" />
                <Typography variant="body2">
                  อ.{item.district} จ.{item.province}
                </Typography>
              </Box>
              <Box
                sx={{ display: "inline-flex", alignItems: "center", gap: 0.5 }}
              >
                <CalendarMonthIcon fontSize="small" />
                <Typography variant="body2">
                  ลงประกาศ : {formatTHDate(item.postedAt)}
                </Typography>
              </Box>
            </Box>

            {/* tags (optional) */}
            {item.tags && item.tags.length > 0 && (
              <Box sx={{ mt: 1, display: "flex", gap: 0.5, flexWrap: "wrap" }}>
                {item.tags.map((t) => (
                  <Chip key={t} label={t} size="small" variant="outlined" />
                ))}
              </Box>
            )}
          </Box>
        </Grid>

        {/* price */}
        <Grid size={{ xs: 12, md: 2 }}>
          <Box
            sx={{
              height: "100%",
              display: "flex",
              alignItems: { xs: "flex-start", md: "center" },
              justifyContent: { xs: "flex-end", md: "flex-end" },
            }}
          >
            <Typography
              variant="h6"
              fontWeight={900}
              color="primary"
              sx={{ textAlign: "right" }}
            >
              {THB(item.price).replace("฿", "")}/{item.unit}
            </Typography>
          </Box>
        </Grid>
      </Grid>
    </Paper>
  );
}
