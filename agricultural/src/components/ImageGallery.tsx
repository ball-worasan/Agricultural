"use client";

import { memo } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Skeleton from "@mui/material/Skeleton";
import IconButton from "@mui/material/IconButton";
import Tooltip from "@mui/material/Tooltip";
import Typography from "@mui/material/Typography";

import ZoomIn from "@mui/icons-material/ZoomIn";
import Photo from "@mui/icons-material/Photo";

interface ImageGalleryProps {
  images: string[];
  currentIdx: number;
  imgLoaded: boolean;
  title: string;
  onSelectImage: (idx: number) => void;
  onLoad: () => void;
}

function ImageGallery({
  images,
  currentIdx,
  imgLoaded,
  title,
  onSelectImage,
  onLoad,
}: ImageGalleryProps) {
  if (images.length === 0) {
    return (
      <Box
        sx={{
          position: "relative",
          width: "100%",
          pt: "56.25%",
          bgcolor: "rgba(0,0,0,.04)",
          borderRadius: 1.5,
        }}
      >
        <Box
          sx={{
            position: "absolute",
            inset: 0,
            display: "grid",
            placeItems: "center",
            color: "text.secondary",
            border: "1px dashed rgba(0,0,0,.15)",
          }}
        >
          <Stack direction="row" alignItems="center" gap={1}>
            <Photo />
            <Typography>ไม่มีรูปภาพ</Typography>
          </Stack>
        </Box>
      </Box>
    );
  }

  return (
    <>
      <Box
        sx={{
          position: "relative",
          width: "100%",
          pt: "56.25%",
          bgcolor: "rgba(0,0,0,.04)",
          borderRadius: 1.5,
        }}
      >
        {!imgLoaded && (
          <Skeleton
            variant="rectangular"
            sx={{ position: "absolute", inset: 0 }}
          />
        )}
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={images[currentIdx]}
          alt={`${title} - รูปที่ ${currentIdx + 1}`}
          onLoad={onLoad}
          style={{
            position: "absolute",
            inset: 0,
            width: "100%",
            height: "100%",
            objectFit: "cover",
          }}
        />
        <Tooltip title="ขยายดูรูป">
          <IconButton
            aria-label="ขยายดูรูป"
            size="small"
            sx={{
              position: "absolute",
              right: 8,
              bottom: 8,
              bgcolor: "background.paper",
            }}
            onClick={() => window.open(images[currentIdx], "_blank")}
          >
            <ZoomIn />
          </IconButton>
        </Tooltip>
      </Box>

      {images.length > 1 && (
        <Stack direction="row" gap={1} mt={1.5} sx={{ overflowX: "auto", pb: 0.5 }}>
          {images.map((src: string, idx: number) => (
            <Box
              key={src + idx}
              onClick={() => onSelectImage(idx)}
              sx={{
                width: 84,
                height: 56,
                flex: "0 0 auto",
                borderRadius: 1,
                border: 1,
                borderColor: idx === currentIdx ? "primary.main" : "divider",
                overflow: "hidden",
                cursor: "pointer",
              }}
            >
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src={src}
                alt={`thumb-${idx + 1}`}
                style={{
                  width: "100%",
                  height: "100%",
                  objectFit: "cover",
                }}
              />
            </Box>
          ))}
        </Stack>
      )}
    </>
  );
}

export default memo(ImageGallery);
