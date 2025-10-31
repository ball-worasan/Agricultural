// src/components/Header.tsx
"use client";

import {
  useCallback,
  useEffect,
  useId,
  useMemo,
  useRef,
  useState,
} from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";

import AppBar from "@mui/material/AppBar";
import Toolbar from "@mui/material/Toolbar";
import Typography from "@mui/material/Typography";
import Button from "@mui/material/Button";
import Box from "@mui/material/Box";
import Menu from "@mui/material/Menu";
import MenuItem from "@mui/material/MenuItem";
import InputAdornment from "@mui/material/InputAdornment";
import TextField from "@mui/material/TextField";
import IconButton from "@mui/material/IconButton";
import Tooltip from "@mui/material/Tooltip";

import AccountCircle from "@mui/icons-material/AccountCircle";
import Business from "@mui/icons-material/Business";
import Search from "@mui/icons-material/Search";
import Close from "@mui/icons-material/Close";

/* =========================
 * Types & Small Utilities
 * =======================*/
type UserLite = { username?: string; email?: string; fullname?: string };

const readCookie = (name: string): string | null => {
  const m = document.cookie.match(
    new RegExp(String.raw`(?:^|;\s*)${name}=([^;]+)`)
  );
  return m ? decodeURIComponent(m[1]) : null;
};

const readUserCookie = (): UserLite | null => {
  try {
    const raw = readCookie("user");
    if (!raw) return null;
    return JSON.parse(raw);
  } catch {
    return null;
  }
};

const parseJwtExp = (token: string): number | null => {
  try {
    const [, payload] = token.split(".");
    const json = JSON.parse(
      atob(payload.replace(/-/g, "+").replace(/_/g, "/"))
    );
    return typeof json?.exp === "number" ? json.exp : null;
  } catch {
    return null;
  }
};

const deleteCookie = (name: string) => {
  document.cookie = `${encodeURIComponent(
    name
  )}=; Path=/; Max-Age=0; SameSite=Lax`;
};

/* ================
 * Header Component
 * ==============*/
export default function Header() {
  const router = useRouter();

  // ---- Auth state ----
  const [user, setUser] = useState<UserLite | null>(null);
  const [tokenValid, setTokenValid] = useState(false);
  const isLoggedIn = useMemo(
    () => Boolean(user) && tokenValid,
    [user, tokenValid]
  );

  // ---- Menus ----
  const [accountEl, setAccountEl] = useState<null | HTMLElement>(null);
  const accountOpen = Boolean(accountEl);
  const accountMenuId = useId();

  // ---- Search ----
  const [keyword, setKeyword] = useState("");
  const searchInputRef = useRef<HTMLInputElement | null>(null);

  /* =========================
   * Effects: sync auth by cookie
   * =======================*/
  useEffect(() => {
    const syncAuth = () => {
      const token = readCookie("token");
      const u = readUserCookie();

      let valid = false;
      if (token) {
        const exp = parseJwtExp(token);
        const now = Math.floor(Date.now() / 1000);
        valid = !exp || exp > now;
        if (!valid) {
          deleteCookie("token");
          deleteCookie("user");
        }
      }
      setTokenValid(valid);
      setUser(valid ? u : null);
    };

    // initial
    syncAuth();

    // refresh on focus / visibility
    const onFocus = () => syncAuth();
    const onVisible = () =>
      document.visibilityState === "visible" && syncAuth();

    window.addEventListener("focus", onFocus);
    document.addEventListener("visibilitychange", onVisible);

    return () => {
      window.removeEventListener("focus", onFocus);
      document.removeEventListener("visibilitychange", onVisible);
    };
  }, []);

  /* =========================
   * Handlers (useCallback)
   * =======================*/
  const handleLogout = useCallback(() => {
    deleteCookie("token");
    deleteCookie("user");
    window.location.href = "/";
  }, []);

  const handleOpenAccount = useCallback((e: React.MouseEvent<HTMLElement>) => {
    setAccountEl(e.currentTarget);
  }, []);
  const handleCloseAccount = useCallback(() => setAccountEl(null), []);

  const onSearchSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      const params = new URLSearchParams();
      if (keyword.trim()) params.set("q", keyword.trim());
      router.push(`/reserve/list?${params.toString()}`);
    },
    [keyword, router]
  );

  const clearKeyword = useCallback(() => {
    setKeyword("");
    searchInputRef.current?.focus();
  }, []);

  // Keyboard shortcut: press "/" to focus search (like GitHub)
  useEffect(() => {
    const onKey = (ev: KeyboardEvent) => {
      if (
        ev.key === "/" &&
        !ev.metaKey &&
        !ev.ctrlKey &&
        !ev.altKey &&
        (ev.target as HTMLElement)?.tagName !== "INPUT" &&
        (ev.target as HTMLElement)?.tagName !== "TEXTAREA" &&
        !(ev.target as HTMLElement)?.isContentEditable
      ) {
        ev.preventDefault();
        searchInputRef.current?.focus();
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  /* =========================
   * Menu items (arrays only)
   * =======================*/
  const accountMenuItems = useMemo(() => {
    if (!isLoggedIn) {
      return [
        <MenuItem
          key="login"
          component={Link}
          href="/auth/login"
          onClick={handleCloseAccount}
        >
          เข้าสู่ระบบ
        </MenuItem>,
        <MenuItem
          key="register"
          component={Link}
          href="/auth/register"
          onClick={handleCloseAccount}
        >
          สมัครสมาชิก
        </MenuItem>,
      ];
    }
    return [
      <MenuItem
        key="my-reserve"
        component={Link}
        href="/reserve/list"
        onClick={handleCloseAccount}
      >
        รายการเช่าพื้นที่
      </MenuItem>,
      <MenuItem
        key="profile"
        component={Link}
        href="/account/profile"
        onClick={handleCloseAccount}
      >
        ข้อมูลผู้ใช้
      </MenuItem>,
      <MenuItem
        key="logout"
        onClick={() => {
          handleCloseAccount();
          handleLogout();
        }}
        sx={{ color: "error.main", fontWeight: 700 }}
      >
        ออกจากระบบ
      </MenuItem>,
    ];
  }, [handleCloseAccount, handleLogout, isLoggedIn]);

  /* ============
   * Render
   * ===========*/
  return (
    <AppBar position="sticky" elevation={0}>
      <Toolbar
        sx={{
          maxWidth: 1400,
          mx: "auto",
          width: "100%",
          gap: 1.5,
          minHeight: { xs: 68, sm: 76 },
          px: { xs: 1, sm: 2 },
        }}
      >
        {/* Left: Logo */}
        <Box sx={{ display: "flex", alignItems: "center", minWidth: 0 }}>
          <Business
            sx={{ mr: 1.2, fontSize: 28, color: "primary.contrastText" }}
          />
          <Typography
            variant="h6"
            component={Link}
            href="/"
            sx={{
              textDecoration: "none",
              fontWeight: 900,
              color: "inherit",
              whiteSpace: "nowrap",
              overflow: "hidden",
              textOverflow: "ellipsis",
            }}
          >
            RentSpace
          </Typography>
        </Box>

        {/* Center: Search */}
        <Box
          component="form"
          onSubmit={onSearchSubmit}
          sx={{
            flex: 1,
            mx: { xs: 1, md: 2 },
            display: "flex",
            justifyContent: "center", // จัดกลางในแนวนอน
            minWidth: 0,
          }}
          role="search"
          aria-label="ค้นหาพื้นที่เช่า"
        >
          <Box
            sx={{
              width: "100%",
              maxWidth: { xs: 420, sm: 560, md: 720 },
            }}
          >
            <TextField
              inputRef={searchInputRef}
              fullWidth
              size="medium"
              placeholder="ค้นหาพื้นที่เช่า…"
              value={keyword}
              onChange={(e) => setKeyword(e.target.value)}
              InputProps={{
                startAdornment: (
                  <InputAdornment position="start">
                    <Search />
                  </InputAdornment>
                ),
                endAdornment: keyword ? (
                  <InputAdornment position="end">
                    <Tooltip title="ล้างคำค้นหา">
                      <IconButton
                        aria-label="ล้างคำค้นหา"
                        edge="end"
                        onClick={clearKeyword}
                      >
                        <Close />
                      </IconButton>
                    </Tooltip>
                  </InputAdornment>
                ) : undefined,
              }}
              sx={{
                "& .MuiOutlinedInput-root": {
                  height: 40,
                  borderRadius: 9999,
                  backgroundColor: "transparent",
                  "& fieldset": { borderColor: "rgba(255,255,255,0.6)" },
                  "&:hover fieldset": { borderColor: "rgba(255,255,255,0.85)" },
                  "&.Mui-focused fieldset": {
                    borderColor: "#A6B28B",
                    boxShadow: "none",
                  },
                },
                "& .MuiInputBase-input": { color: "primary.contrastText" },
                "& .MuiInputAdornment-root": { color: "primary.contrastText" },
              }}
              aria-label="ช่องค้นหาพื้นที่เช่า"
            />
          </Box>
        </Box>

        {/* Right: Account */}
        <Box sx={{ display: "flex", alignItems: "center" }}>
          <Button
            variant="outlined"
            startIcon={<AccountCircle />}
            onClick={handleOpenAccount}
            aria-haspopup="menu"
            aria-controls={accountMenuId}
            aria-expanded={accountOpen ? "true" : undefined}
            sx={{
              borderColor: "background.default",
              color: "primary.contrastText",
              "&:hover": {
                borderColor: "#A6B28B",
                color: "#A6B28B",
                backgroundColor: "rgba(204, 204, 204, 0.1)",
              },
              minWidth: { xs: 44, sm: 120 },
              px: { xs: 1, sm: 2 },
            }}
          >
            <Box sx={{ display: { xs: "none", sm: "inline" } }}>
              {isLoggedIn ? user?.username ?? "บัญชี" : "บัญชี"}
            </Box>
          </Button>

          <Menu
            id={accountMenuId}
            anchorEl={accountEl}
            open={accountOpen}
            onClose={handleCloseAccount}
            anchorOrigin={{ vertical: "bottom", horizontal: "right" }}
            transformOrigin={{ vertical: "top", horizontal: "right" }}
            PaperProps={{
              elevation: 8,
              sx: { borderRadius: 2, minWidth: 220 },
            }}
            disableScrollLock
            keepMounted
          >
            {accountMenuItems}
          </Menu>
        </Box>
      </Toolbar>
    </AppBar>
  );
}
