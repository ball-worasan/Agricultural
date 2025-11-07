"use client";

import * as React from "react";
import Slide from "@mui/material/Slide";
import Box from "@mui/material/Box";
import useMediaQuery from "@mui/material/useMediaQuery";
import { useTheme } from "@mui/material/styles";
import useReveal from "@/hooks/useReveal";

type RevealProps = {
  children: React.ReactNode;
  /** หน่วงเวลาแอนิเมชัน (ms) */
  delay?: number;
  /** ทิศทาง Slide */
  direction?: "up" | "down" | "left" | "right";
  /** threshold สำหรับ useReveal */
  threshold?: number;
};

export default function Reveal({
  children,
  delay = 0,
  direction = "up",
  threshold = 0.12,
}: RevealProps) {
  const { ref, show } = useReveal(threshold);
  const theme = useTheme();
  const reduceMotion = useMediaQuery("(prefers-reduced-motion: reduce)");

  // ถ้าผู้ใช้ต้องการลด motion: แสดงผลเลย ไม่ต้อง animate
  if (reduceMotion) {
    return (
      <Box ref={ref} sx={{ willChange: "auto" }}>
        {children}
      </Box>
    );
  }

  return (
    <Box
      ref={ref}
      sx={{
        // ป้องกัน layout shift ตอนรอ intersect
        transform: show ? "none" : "translateY(8px)",
        opacity: show ? 1 : 0,
        transition: `opacity .2s ${delay}ms, transform .2s ${delay}ms`,
        willChange: "transform, opacity",
      }}
    >
      <Slide
        in={show}
        direction={direction}
        timeout={{ enter: 500 + delay }}
        easing={{
          enter: theme.transitions.easing.easeOut,
          exit: theme.transitions.easing.sharp,
        }}
        appear
      >
        <Box>{children}</Box>
      </Slide>
    </Box>
  );
}
