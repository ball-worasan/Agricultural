"use client";

import { alpha, createTheme } from "@mui/material/styles";

/** 🎨 Brand palette (เดิม) */
const forest = "#1C352D";
const sage = "#A6B28B";
const peach = "#F5C9B0";
const porcelain = "#F9F6F3";

export const glass = (opacity = 0.55) => ({
  backdropFilter: "blur(10px)",
  backgroundColor: alpha("#ffffff", opacity),
  border: `1px solid ${alpha("#000000", 0.06)}`,
  boxShadow: `0 1px 0 ${alpha("#000", 0.04)} inset, 0 12px 28px ${alpha(
    "#000",
    0.06
  )}`,
  "@media (prefers-color-scheme: dark)": {
    backgroundColor: alpha("#0b0d12", Math.max(0.35, opacity - 0.15)),
    border: `1px solid ${alpha("#ffffff", 0.08)}`,
  },
});

const theme = createTheme({
  breakpoints: {
    // ใช้ค่ามาตรฐาน + ย้ำเพื่ออ่านง่าย
    values: { xs: 0, sm: 600, md: 900, lg: 1200, xl: 1536 },
  },
  palette: {
    mode: "light",
    primary: { main: forest, contrastText: porcelain },
    secondary: { main: sage, contrastText: forest },
    background: { default: porcelain, paper: "#ffffff" },
    text: { primary: forest, secondary: "#556963" },
    success: { main: "#3BAA7F" },
    warning: { main: peach },
    info: { main: "#6CA6A3" },
  },
  shape: { borderRadius: 14 },
  typography: {
    fontFamily:
      'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial',
    h1: {
      fontWeight: 900,
      letterSpacing: -0.6,
      fontSize: "clamp(2rem, 2.5vw + 1rem, 3.25rem)",
      lineHeight: 1.12,
    },
    h2: {
      fontWeight: 900,
      letterSpacing: -0.5,
      fontSize: "clamp(1.75rem, 2.1vw + 1rem, 2.75rem)",
      lineHeight: 1.15,
    },
    h3: { fontWeight: 800, fontSize: "clamp(1.35rem, 1.4vw + 1rem, 2rem)" },
    h4: { fontWeight: 800, fontSize: "clamp(1.15rem, 1.1vw + 1rem, 1.6rem)" },
    button: { textTransform: "none", fontWeight: 700 },
  },
  components: {
    MuiContainer: {
      styleOverrides: {
        root: {
          paddingInline: 16,
          maxWidth: "var(--mui-container-max, 1200px)",
        },
      },
    },
    MuiAppBar: {
      styleOverrides: {
        root: {
          borderBottom: "1px solid rgba(0,0,0,.06)",
          backdropFilter: "blur(10px)",
        },
      },
    },
    MuiPaper: {
      defaultProps: { elevation: 0 },
      styleOverrides: { root: { border: "1px solid rgba(0,0,0,.06)" } },
    },
    MuiCard: {
      styleOverrides: {
        root: {
          borderRadius: 18,
          overflow: "hidden",
          border: "1px solid rgba(0,0,0,.06)",
        },
      },
    },
    MuiButton: {
      styleOverrides: {
        root: { borderRadius: 12, paddingInline: 18 },
        containedPrimary: {
          backgroundImage: `linear-gradient(135deg, ${forest}, ${sage})`,
        },
        outlined: {
          borderColor: alpha(forest, 0.25),
          "&:hover": {
            borderColor: alpha(forest, 0.45),
            background: alpha(forest, 0.03),
          },
        },
      },
    },
    MuiChip: {
      styleOverrides: {
        root: { fontWeight: 600 },
        outlined: { borderColor: alpha(forest, 0.2) },
      },
    },
  },
});

export default theme;
