"use client";

import { memo } from "react";
import Button, { ButtonProps } from "@mui/material/Button";
import CircularProgress from "@mui/material/CircularProgress";

interface LoadingButtonProps extends ButtonProps {
  loading?: boolean;
  loadingText?: string;
}

function LoadingButton({
  loading = false,
  loadingText,
  children,
  disabled,
  ...props
}: LoadingButtonProps) {
  return (
    <Button {...props} disabled={disabled || loading}>
      {loading ? (
        <>
          <CircularProgress size={20} sx={{ mr: 1 }} />
          {loadingText || children}
        </>
      ) : (
        children
      )}
    </Button>
  );
}

export default memo(LoadingButton);
