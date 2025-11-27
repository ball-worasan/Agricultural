"use client";

import { memo } from "react";
import Alert from "@mui/material/Alert";
import Button from "@mui/material/Button";
import Box from "@mui/material/Box";

interface AuthGuardProps {
  isLoggedIn: boolean;
  children: React.ReactNode;
  redirectUrl?: string;
}

function AuthGuard({ isLoggedIn, children, redirectUrl = "/auth/login" }: AuthGuardProps) {
  if (!isLoggedIn) {
    return (
      <Box sx={{ display: "flex", flexDirection: "column", gap: 2, alignItems: "center" }}>
        <Alert severity="info" sx={{ width: "100%" }}>
          กรุณาเข้าสู่ระบบเพื่อใช้งานฟีเจอร์นี้
        </Alert>
        <Button variant="contained" href={redirectUrl}>
          เข้าสู่ระบบ
        </Button>
      </Box>
    );
  }

  return <>{children}</>;
}

export default memo(AuthGuard);
