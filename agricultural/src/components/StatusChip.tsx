"use client";

import { memo } from "react";
import Chip from "@mui/material/Chip";
import CheckCircle from "@mui/icons-material/CheckCircle";
import HourglassBottom from "@mui/icons-material/HourglassBottom";

interface StatusChipProps {
  status: "available" | "reserved";
  size?: "small" | "medium";
}

function StatusChip({ status, size = "small" }: StatusChipProps) {
  const config =
    status === "available"
      ? {
          label: "ว่าง",
          color: "success" as const,
          icon: <CheckCircle fontSize="small" />,
        }
      : {
          label: "ติดจอง",
          color: "warning" as const,
          icon: <HourglassBottom fontSize="small" />,
        };

  return <Chip size={size} color={config.color} icon={config.icon} label={config.label} />;
}

export default memo(StatusChip);
