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
import { usePathname } from "next/navigation";

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
import Divider from "@mui/material/Divider";
import ListItemIcon from "@mui/material/ListItemIcon";
import Container from "@mui/material/Container";
import useScrollTrigger from "@mui/material/useScrollTrigger";
import Paper from "@mui/material/Paper";

import AccountCircle from "@mui/icons-material/AccountCircle";
import Business from "@mui/icons-material/Business";
import Search from "@mui/icons-material/Search";
import Close from "@mui/icons-material/Close";
import AddBusiness from "@mui/icons-material/AddBusiness";
import ReceiptLong from "@mui/icons-material/ReceiptLong";
import PersonOutline from "@mui/icons-material/PersonOutline";
import ExitToApp from "@mui/icons-material/ExitToApp";
import HomeWork from "@mui/icons-material/HomeWork";

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

/* ================ */
export default function Header() {
  const pathname = usePathname();
  const isHomePage = pathname === "/";

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

  // ---- Elevation on scroll ----
  const elevate = useScrollTrigger({ disableHysteresis: true, threshold: 2 });

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

    syncAuth();

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
      if (!keyword.trim()) return;

      // Scroll to listings section on homepage
      const listingsSection = document.getElementById("listings-section");
      if (listingsSection) {
        listingsSection.scrollIntoView({ behavior: "smooth", block: "start" });
      }

      // Trigger custom event for filtering
      const event = new CustomEvent("searchListings", {
        detail: { keyword: keyword.trim() },
      });
      window.dispatchEvent(event);
    },
    [keyword]
  );

  const clearKeyword = useCallback(() => {
    setKeyword("");
    searchInputRef.current?.focus();
  }, []);

  // Keyboard shortcut: press "/" to focus search
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
   * Menu items
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
        href="/"
        onClick={handleCloseAccount}
      >
        <ListItemIcon>
          <HomeWork fontSize="small" />
        </ListItemIcon>
        รายการพื้นที่เช่า
      </MenuItem>,

      <MenuItem
        key="host-spaces"
        component={Link}
        href="/my/listings"
        onClick={handleCloseAccount}
      >
        <ListItemIcon>
          <AddBusiness fontSize="small" />
        </ListItemIcon>
        พื้นที่ปล่อยเช่า
      </MenuItem>,

      <MenuItem
        key="rent-history"
        component={Link}
        href="/account/rentals"
        onClick={handleCloseAccount}
      >
        <ListItemIcon>
          <ReceiptLong fontSize="small" />
        </ListItemIcon>
        ประวัติการเช่าพื้นที่
      </MenuItem>,

      <Divider key="div-1" sx={{ my: 0.5 }} />,

      <MenuItem
        key="profile"
        component={Link}
        href="/account/profile"
        onClick={handleCloseAccount}
      >
        <ListItemIcon>
          <PersonOutline fontSize="small" />
        </ListItemIcon>
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
        <ListItemIcon sx={{ color: "error.main" }}>
          <ExitToApp fontSize="small" />
        </ListItemIcon>
        ออกจากระบบ
      </MenuItem>,
    ];
  }, [handleCloseAccount, handleLogout, isLoggedIn]);

  /* ============ Render ===========*/
  return (
    <AppBar
      position="sticky"
      elevation={elevate ? 6 : 0}
      sx={{
        backgroundImage: (theme) =>
          `linear-gradient(135deg, ${theme.palette.primary.main}, ${theme.palette.secondary.main})`,
        borderBottom: "1px solid rgba(0,0,0,.06)",
      }}
    >
      <Container maxWidth="lg" disableGutters>
        <Toolbar
          sx={{
            mx: "auto",
            width: "100%",
            gap: 1.5,
            minHeight: { xs: 68, sm: 76 },
            px: { xs: 1, sm: 2 },
          }}
        >
          {/* Left: Logo */}
          <Box sx={{ display: "flex", alignItems: "center", minWidth: 0 }}>
            <Paper
              elevation={0}
              sx={{
                mr: 1.2,
                width: 36,
                height: 36,
                borderRadius: "50%",
                display: "grid",
                placeItems: "center",
                bgcolor: "rgba(255,255,255,.16)",
                border: "1px solid rgba(255,255,255,.24)",
              }}
            >
              <Business sx={{ fontSize: 20, color: "primary.contrastText" }} />
            </Paper>
            <Typography
              variant="h6"
              component={Link}
              href="/"
              sx={{
                textDecoration: "none",
                fontWeight: 900,
                color: "primary.contrastText",
                whiteSpace: "nowrap",
                overflow: "hidden",
                textOverflow: "ellipsis",
                letterSpacing: 0.2,
              }}
            >
              RentSpace
            </Typography>
          </Box>

          {/* Center: Search - Only on homepage */}
          {isHomePage && (
            <Box
              component="form"
              onSubmit={onSearchSubmit}
              sx={{
                flex: 1,
                mx: { xs: 1, md: 2 },
                display: "flex",
                justifyContent: "center",
                minWidth: 0,
              }}
              role="search"
              aria-label="ค้นหาพื้นที่เช่า"
            >
              <Box
                sx={{ width: "100%", maxWidth: { xs: 420, sm: 560, md: 720 } }}
              >
                <TextField
                  inputRef={searchInputRef}
                  fullWidth
                  size="medium"
                  placeholder="ค้นหาชื่อประกาศ…"
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
                      height: 42,
                      borderRadius: 9999,
                      bgcolor: "rgba(255,255,255,.08)",
                      "& fieldset": { borderColor: "rgba(255,255,255,0.36)" },
                      "&:hover fieldset": {
                        borderColor: "rgba(255,255,255,0.6)",
                      },
                      "&.Mui-focused fieldset": {
                        borderColor: "#A6B28B",
                        boxShadow: "none",
                      },
                    },
                    "& .MuiInputBase-input": { color: "primary.contrastText" },
                    "& .MuiInputAdornment-root": {
                      color: "primary.contrastText",
                    },
                  }}
                  aria-label="ช่องค้นหาชื่อประกาศ"
                />
              </Box>
            </Box>
          )}

          {/* Spacer when search is not shown */}
          {!isHomePage && <Box sx={{ flex: 1 }} />}

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
                borderColor: "rgba(255,255,255,.36)",
                color: "primary.contrastText",
                "&:hover": {
                  borderColor: "#E8FFD7",
                  color: "#E8FFD7",
                  backgroundColor: "rgba(255, 255, 255, 0.08)",
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
                sx: { borderRadius: 2, minWidth: 240, py: 0.5 },
              }}
              disableScrollLock
              keepMounted
            >
              {accountMenuItems}
            </Menu>
          </Box>
        </Toolbar>
      </Container>
    </AppBar>
  );
}
