"use client";

import Grid from "@mui/material/Grid";
import Card from "@mui/material/Card";
import CardContent from "@mui/material/CardContent";
import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import Chip from "@mui/material/Chip";
import Stack from "@mui/material/Stack";
import Link from "next/link";

const items = [
  {
    id: "101",
    title: "สำนักงานใจกลางเมือง",
    desc: "พื้นที่สำนักงานสมัยใหม่ ใจกลางกรุงเทพ เดินทางสะดวก มีที่จอดรถ",
    price: "25,000 บาท/เดือน",
    area: "120 ตร.ม.",
    tag: "สำนักงาน",
    emoji: "🏢",
  },
  {
    id: "102",
    title: "พื้นที่จัดงานสวยงาม",
    desc: "พื้นที่กว้างขวาง เหมาะสำหรับจัดงานอีเวนต์ ระบบเสียงแสงครบ",
    price: "8,000 บาท/วัน",
    area: "200 ตร.ม.",
    tag: "อีเวนต์",
    emoji: "🎉",
  },
  {
    id: "103",
    title: "คลังสินค้าทำเลดี",
    desc: "คลังสินค้าขนาดใหญ่ เข้า-ออกสะดวก รปภ. 24 ชั่วโมง",
    price: "35,000 บาท/เดือน",
    area: "500 ตร.ม.",
    tag: "คลังสินค้า",
    emoji: "📦",
  },
  {
    id: "104",
    title: "ร้านค้าหน้าตลาด",
    desc: "ทำเลทอง หน้าตลาดชุมชน ลูกค้าเยอะ เหมาะสำหรับค้าขาย",
    price: "15,000 บาท/เดือน",
    area: "60 ตร.ม.",
    tag: "ร้านค้า",
    emoji: "🛍️",
  },
];

export default function CardsGrid() {
  return (
    <Grid container spacing={{ xs: 2, md: 3 }} sx={{ alignItems: "stretch" }}>
      {items.map((it) => (
        <Grid key={it.id} size={{ xs: 12, sm: 6, md: 4, lg: 3 }}>
          <Card
            className="card-hover"
            component={Link}
            href={`/listing/${it.id}`}
            sx={{
              cursor: "pointer",
              height: "100%",
              display: "flex",
              flexDirection: "column",
              textDecoration: "none",
              color: "inherit",
            }}
          >
            <Box
              sx={{
                height: { xs: 140, sm: 160, md: 180 },
                position: "relative",
                background:
                  "linear-gradient(135deg, var(--brand-1), var(--brand-2))",
              }}
            >
              <Box
                sx={{
                  position: "absolute",
                  inset: 0,
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  fontSize: { xs: 44, md: 60 },
                  opacity: 0.28,
                }}
              >
                {it.emoji}
              </Box>
            </Box>

            <CardContent
              sx={{
                display: "flex",
                flexDirection: "column",
                gap: 1,
                flexGrow: 1,
              }}
            >
              <Stack
                direction="row"
                alignItems="center"
                justifyContent="space-between"
                spacing={1}
              >
                <Typography
                  variant="h6"
                  fontWeight={800}
                  sx={{ pr: 1, lineHeight: 1.25 }}
                >
                  {it.title}
                </Typography>
                <Chip
                  label={it.tag}
                  size="small"
                  color="secondary"
                  variant="outlined"
                />
              </Stack>

              <Typography
                color="text.secondary"
                sx={{
                  lineHeight: 1.7,
                  display: "-webkit-box",
                  WebkitLineClamp: 2,
                  WebkitBoxOrient: "vertical",
                  overflow: "hidden",
                  minHeight: { xs: 44, md: 48 },
                }}
              >
                {it.desc}
              </Typography>

              <Box sx={{ mt: "auto" }}>
                <Stack
                  direction="row"
                  alignItems="center"
                  justifyContent="space-between"
                >
                  <Typography sx={{ color: "primary.main", fontWeight: 900 }}>
                    {it.price}
                  </Typography>
                  <Typography color="text.secondary" fontWeight={600}>
                    📐 {it.area}
                  </Typography>
                </Stack>
              </Box>
            </CardContent>
          </Card>
        </Grid>
      ))}
    </Grid>
  );
}
