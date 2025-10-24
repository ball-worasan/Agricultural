"use client";

import AppBar from "@mui/material/AppBar";
import Toolbar from "@mui/material/Toolbar";
import Typography from "@mui/material/Typography";
import Button from "@mui/material/Button";
import Box from "@mui/material/Box";
import Menu from "@mui/material/Menu";
import MenuItem from "@mui/material/MenuItem";
import IconButton from "@mui/material/IconButton";
import AccountCircle from "@mui/icons-material/AccountCircle";
import Business from "@mui/icons-material/Business";
import MenuIcon from "@mui/icons-material/Menu";
import { useState } from "react";
import Link from "next/link";

export default function Header() {
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
  const [navOpen, setNavOpen] = useState<null | HTMLElement>(null);
  const open = Boolean(anchorEl);
  const navMenuOpen = Boolean(navOpen);

  return (
    <AppBar position="sticky" elevation={0}>
      <Toolbar
        sx={{
          maxWidth: 1200,
          mx: "auto",
          width: "100%",
          gap: 1,
          minHeight: { xs: 56, sm: 64 },
          px: { xs: 1, sm: 2 },
        }}
      >
        <Box
          sx={{ display: "flex", alignItems: "center", flex: 1, minWidth: 0 }}
        >
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

        {/* Desktop nav */}
        <Box
          sx={{
            display: { xs: "none", md: "flex" },
            gap: 1.5,
            flexWrap: "wrap",
          }}
        >
          <Button component={Link} href="/" color="inherit">
            สำรวจ
          </Button>
          <Button component={Link} href="/reserve/list" color="inherit">
            ค้นหา
          </Button>
          <Button component={Link} href="/account/spaces" color="inherit">
            ปล่อยเช่า
          </Button>
          <Button component={Link} href="/admin/payments" color="inherit">
            แอดมิน
          </Button>
        </Box>

        {/* Account */}
        <Box sx={{ display: "flex", alignItems: "center", gap: 1 }}>
          <Button
            variant="outlined"
            startIcon={<AccountCircle />}
            onClick={(e) => setAnchorEl(e.currentTarget)}
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
            <Box sx={{ display: { xs: "none", sm: "inline" } }}>บัญชี</Box>
          </Button>
          <Menu
            anchorEl={anchorEl}
            open={open}
            onClose={() => setAnchorEl(null)}
            anchorOrigin={{ vertical: "bottom", horizontal: "right" }}
            transformOrigin={{ vertical: "top", horizontal: "right" }}
            PaperProps={{
              elevation: 8,
              sx: { borderRadius: 2, minWidth: 200 },
            }}
          >
            <MenuItem
              component={Link}
              href="/auth/login"
              onClick={() => setAnchorEl(null)}
            >
              เข้าสู่ระบบ
            </MenuItem>
            <MenuItem
              component={Link}
              href="/auth/register"
              onClick={() => setAnchorEl(null)}
            >
              สมัครสมาชิก
            </MenuItem>
            <MenuItem
              component={Link}
              href="/account/profile"
              onClick={() => setAnchorEl(null)}
            >
              ข้อมูลสมาชิก
            </MenuItem>
          </Menu>

          {/* Mobile nav */}
          <IconButton
            onClick={(e) => setNavOpen(e.currentTarget)}
            sx={{
              display: { xs: "inline-flex", md: "none" },
              border: "1px solid #1C352D",
              borderRadius: 2,
              ml: { xs: 0.5, sm: 1 },
            }}
            aria-label="เมนูนำทาง"
          >
            <MenuIcon sx={{ color: "#A6B28B", fontSize: 28 }} />
          </IconButton>

          <Menu
            anchorEl={navOpen}
            open={navMenuOpen}
            onClose={() => setNavOpen(null)}
            anchorOrigin={{ vertical: "bottom", horizontal: "right" }}
            transformOrigin={{ vertical: "top", horizontal: "right" }}
            PaperProps={{ sx: { minWidth: 220 } }}
          >
            <MenuItem
              component={Link}
              href="/"
              onClick={() => setNavOpen(null)}
            >
              สำรวจ
            </MenuItem>
            <MenuItem
              component={Link}
              href="/reserve/list"
              onClick={() => setNavOpen(null)}
            >
              ค้นหา
            </MenuItem>
            <MenuItem
              component={Link}
              href="/account/spaces"
              onClick={() => setNavOpen(null)}
            >
              ปล่อยเช่า
            </MenuItem>
            <MenuItem
              component={Link}
              href="/admin/payments"
              onClick={() => setNavOpen(null)}
            >
              แอดมิน
            </MenuItem>
          </Menu>
        </Box>
      </Toolbar>
    </AppBar>
  );
}
