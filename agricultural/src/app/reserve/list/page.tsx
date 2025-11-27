"use client";

import Header from "@/components/Header";
import Container from "@mui/material/Container";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import TextField from "@mui/material/TextField";
import Table from "@mui/material/Table";
import TableHead from "@mui/material/TableHead";
import TableRow from "@mui/material/TableRow";
import TableCell from "@mui/material/TableCell";
import TableBody from "@mui/material/TableBody";
import Button from "@mui/material/Button";
import Box from "@mui/material/Box";

export default function ReserveList() {
  const rows = [
    {
      name: "แขกเช่าพื้นที่1",
      phone: "xxx-xxxxxxx",
      place: "ชื่อพื้นที่",
      date: "xx/xx/xxxx",
      deposit: "รอการชำระเงิน",
      status: "อยู่ระหว่างจอง",
    },
    {
      name: "คุณxxx",
      phone: "xxx-xxxxxxx",
      place: "ชื่อพื้นที่",
      date: "xx/xx/xxxx",
      deposit: "มัดจำสำเร็จ",
      status: "จองสำเร็จ",
    },
  ];

  return (
    <>
      <Header />
      <Container maxWidth="lg" sx={{ py: { xs: 4, md: 6 } }}>
        <Paper sx={{ p: { xs: 2, md: 4 } }}>
          <Typography variant="h6" fontWeight={900} gutterBottom>
            รายการจองพื้นที่
          </Typography>
          <Box sx={{ mb: 2 }}>
            <TextField label="กรองตามสถานะ" placeholder="ทั้งหมด" />
          </Box>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>ชื่อผู้เช่า</TableCell>
                <TableCell>เบอร์โทร</TableCell>
                <TableCell>ชื่อพื้นที่</TableCell>
                <TableCell>วัน/ที่จอง</TableCell>
                <TableCell>อัตราค่ามัดจำ</TableCell>
                <TableCell>สถานะ</TableCell>
                <TableCell>การดำเนินการ</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {rows.map((r, i) => (
                <TableRow key={i}>
                  <TableCell>{r.name}</TableCell>
                  <TableCell>{r.phone}</TableCell>
                  <TableCell>{r.place}</TableCell>
                  <TableCell>{r.date}</TableCell>
                  <TableCell>{r.deposit}</TableCell>
                  <TableCell>{r.status}</TableCell>
                  <TableCell>
                    <Button size="small" href="/reserve/123/status">
                      ดู
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </Paper>
      </Container>
    </>
  );
}
