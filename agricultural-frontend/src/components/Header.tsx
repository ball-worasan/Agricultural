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

export default function Header() {
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
  const [navOpen, setNavOpen] = useState<null | HTMLElement>(null);
  const open = Boolean(anchorEl);
  const navMenuOpen = Boolean(navOpen);

  return (
    <AppBar position="sticky" elevation={0}>
      <Toolbar sx={{ maxWidth: 1200, mx: "auto", width: "100%", gap: 1 }}>
        <Box sx={{ display: "flex", alignItems: "center", flex: 1 }}>
          <Business
            sx={{ mr: 1.2, fontSize: 28, color: "primary.contrastText" }}
          />
          <Typography
            variant="h6"
            component="a"
            href="#"
            sx={{ textDecoration: "none", fontWeight: 900 }}
          >
            RentSpace
          </Typography>
        </Box>

        <Box sx={{ display: { xs: "none", md: "flex" }, gap: 1.5 }}>
          <Button color="inherit">สำรวจ</Button>
          <Button color="inherit">ประเภทพื้นที่</Button>
          <Button color="inherit">ปล่อยเช่า</Button>
          <Button color="inherit">ช่วยเหลือ</Button>
        </Box>

        <Box sx={{ display: "flex", alignItems: "center", gap: 1 }}>
          <Button
            variant="outlined"
            startIcon={<AccountCircle />}
            onClick={(e) => setAnchorEl(e.currentTarget)}
            sx={{
              borderColor: "background.default", // กำหนดสีขอบ
              color: "primary.contrastText", // กำหนดสีตัวอักษร
              "&:hover": {
                borderColor: "#A6B28B", // สีขอบเวลา hover
                color: "#A6B28B", // สีตัวอักษรเวลา hover
                backgroundColor: "rgba(204, 204, 204, 0.1)", // ใส่ background hover ก็ได้
              },
            }}
          >
            บัญชี
          </Button>
          <Menu
            anchorEl={anchorEl}
            open={open}
            onClose={() => setAnchorEl(null)}
            anchorOrigin={{ vertical: "bottom", horizontal: "right" }}
            transformOrigin={{ vertical: "top", horizontal: "right" }}
            PaperProps={{ elevation: 8, sx: { borderRadius: 2 } }}
          >
            <MenuItem onClick={() => setAnchorEl(null)}>เข้าสู่ระบบ</MenuItem>
            <MenuItem onClick={() => setAnchorEl(null)}>สมัครสมาชิก</MenuItem>
          </Menu>

          <IconButton
            onClick={(e) => setNavOpen(e.currentTarget)}
            sx={{
              display: { xs: "inline-flex", md: "none" },
              border: "1px solid #1C352D",
              borderRadius: 2,
            }}
          >
            <MenuIcon sx={{ color: "#A6B28B", fontSize: 28 }} />
          </IconButton>

          <Menu
            anchorEl={navOpen}
            open={navMenuOpen}
            onClose={() => setNavOpen(null)}
            anchorOrigin={{ vertical: "bottom", horizontal: "right" }}
            transformOrigin={{ vertical: "top", horizontal: "right" }}
          >
            <MenuItem onClick={() => setNavOpen(null)}>สำรวจ</MenuItem>
            <MenuItem onClick={() => setNavOpen(null)}>ประเภทพื้นที่</MenuItem>
            <MenuItem onClick={() => setNavOpen(null)}>ปล่อยเช่า</MenuItem>
            <MenuItem onClick={() => setNavOpen(null)}>ช่วยเหลือ</MenuItem>
          </Menu>
        </Box>
      </Toolbar>
    </AppBar>
  );
}
