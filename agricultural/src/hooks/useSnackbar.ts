"use client";

import { useState, useCallback } from "react";

export function useSnackbar() {
  const [snack, setSnack] = useState<{ open: boolean; msg: string }>({
    open: false,
    msg: "",
  });

  const showSnackbar = useCallback((msg: string) => {
    setSnack({ open: true, msg });
  }, []);

  const hideSnackbar = useCallback(() => {
    setSnack((prev) => ({ ...prev, open: false }));
  }, []);

  return { snack, showSnackbar, hideSnackbar };
}
