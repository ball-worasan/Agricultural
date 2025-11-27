"use client";

import { memo } from "react";
import Link from "next/link";
import Paper from "@mui/material/Paper";
import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import Chip from "@mui/material/Chip";

import Place from "@mui/icons-material/Place";
import AccessTime from "@mui/icons-material/AccessTime";
import LocalOfferIcon from "@mui/icons-material/LocalOffer";
import ImageIcon from "@mui/icons-material/Image";

import type { Listing } from "@/types";
import { formatPrice, formatDate } from "@/lib/utils/format";

interface ListingCardProps {
  listing: Listing;
}

function ListingCard({ listing }: ListingCardProps) {
  const isReserved = listing.status === "reserved";

  return (
    <Paper
      component={isReserved ? "div" : Link}
      href={isReserved ? undefined : `/listing/${listing.id}`}
      elevation={0}
      sx={{
        p: { xs: 1.25, sm: 1.75, md: 2 },
        border: 1,
        borderColor: "divider",
        borderRadius: 1.5,
        display: "grid",
        gridTemplateColumns: {
          xs: "100px 1fr",
          sm: "140px 1fr 160px",
          md: "170px 1fr 200px",
          lg: "200px 1fr 240px",
        },
        columnGap: { xs: 1.25, sm: 2, md: 2.25 },
        rowGap: 1,
        alignItems: "center",
        textDecoration: "none",
        color: "inherit",
        position: "relative",
        overflow: "hidden",
        cursor: isReserved ? "not-allowed" : "pointer",
        opacity: isReserved ? 0.7 : 1,
        "&:hover": isReserved
          ? {}
          : {
              borderColor: "primary.main",
              boxShadow: "0 2px 8px rgba(0,0,0,0.1)",
            },
      }}
    >
      {/* Status Ribbon */}
      <Box
        sx={{
          position: "absolute",
          top: { xs: 40, sm: 56, md: 64, lg: 72 },
          left: { xs: -16, sm: -20, md: -22, lg: -24 },
          transform: "rotate(-45deg)",
          transformOrigin: "left top",
          bgcolor: isReserved ? "#b42318" : "#1f6f54",
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
          zIndex: 1,
          pointerEvents: "none",
        }}
      >
        {isReserved ? "ติดจอง" : "ว่าง"}
      </Box>

      {/* Image */}
      <Box
        sx={{
          position: "relative",
          width: "100%",
          height: { xs: 80, sm: 96, md: 112, lg: 128 },
          borderRadius: 1,
          overflow: "hidden",
        }}
      >
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
        >
          {listing.image ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={listing.image}
              alt={listing.title}
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
              <ImageIcon fontSize="small" /> รูปภาพ
            </Box>
          )}
        </Box>
      </Box>

      {/* Details */}
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
          {listing.title}
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
              title={`อ.${listing.district} จ.${listing.province}`}
            >
              อ.{listing.district} จ.{listing.province}
            </Typography>
          </Box>
        </Box>

        {listing.tags && listing.tags.length > 0 && (
          <Box sx={{ display: "flex", gap: 0.5, mb: 0.5, flexWrap: "wrap" }}>
            {listing.tags.map((tag) => (
              <Chip
                key={tag}
                label={tag}
                size="small"
                icon={<LocalOfferIcon />}
                sx={{
                  height: 20,
                  fontSize: { xs: 10.5, sm: 11 },
                  "& .MuiChip-icon": { fontSize: 14 },
                }}
              />
            ))}
          </Box>
        )}

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
            ลงประกาศ : {formatDate(listing.postedAt)}
          </Typography>
        </Box>
      </Box>

      {/* Price */}
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
          {formatPrice(listing.price)}/{listing.unit}
        </Typography>
      </Box>
    </Paper>
  );
}

export default memo(ListingCard);
