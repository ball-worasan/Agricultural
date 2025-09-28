"use client";

import Slide from "@mui/material/Slide";
import Box from "@mui/material/Box";
import useReveal from "@/hooks/useReveal";

export default function Reveal({ children }: { children: React.ReactNode }) {
  const { ref, show } = useReveal(0.12);
  return (
    <Box ref={ref}>
      <Slide in={show} direction="up" timeout={500}>
        <Box>{children}</Box>
      </Slide>
    </Box>
  );
}
