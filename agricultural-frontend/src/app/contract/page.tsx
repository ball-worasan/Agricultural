"use client";

import { useEffect, useMemo, useState } from "react";
import Header from "@/components/Header";
import Container from "@mui/material/Container";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Box from "@mui/material/Box";
import Grid from "@mui/material/Grid";
import Divider from "@mui/material/Divider";
import MenuItem from "@mui/material/MenuItem";
import Alert from "@mui/material/Alert";

/** ===== helpers: อ่านผู้ใช้จาก cookie (ให้สอดคล้องกับ Header.tsx) ===== */
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

type ReserveDraft = {
  listingId: string;
  title: string;
  locationText: string;
  price: number;
  unit: "วัน" | "เดือน" | "ปี";
  image?: string;
  fromDate?: string; // YYYY-MM-DD
  toDate?: string; // YYYY-MM-DD
  totalDays?: number;
  totalPrice?: number;
};

/** ===== แบบฟอร์มสัญญา (minimal ที่ใช้งานได้จริง) ===== */
type ContractForm = {
  // คู่สัญญา
  lessorName: string;
  lessorTaxId: string;
  lessorAddress: string;
  lessorEmail: string;
  lessorPhone: string;

  lesseeName: string;
  lesseeTaxId: string;
  lesseeAddress: string;
  lesseeEmail: string;
  lesseePhone: string;

  // ทรัพย์/พื้นที่
  placeTitle: string;
  placeLocation: string;
  placeSize: string;

  // เงื่อนไขเช่า
  startDate: string; // YYYY-MM-DD
  endDate: string; // YYYY-MM-DD
  payUnit: "วัน" | "เดือน" | "ปี";
  pricePerUnit: number;
  deposit: number;
  billingCycle: "ต้นงวด" | "ปลายงวด";
  lateFeePerDay: number;
  annualIncreasePercent: number; // ปรับขึ้นรายปี

  // การบำรุงรักษา/ข้อห้ามแบบย่อ (ใส่ข้อความสั้น ๆ)
  maintenanceNote: string;
  prohibitedNote: string;

  // ลายเซ็น/พยาน (เป็น text/อัปโหลดไฟล์ได้ในภายหลัง)
  signerLessor: string;
  signerLessee: string;
  witness1?: string;
  witness2?: string;

  // หมายเหตุท้ายสัญญา
  remark?: string;
};

export default function ContractPage() {
  const [draft, setDraft] = useState<ReserveDraft | null>(null);
  const [user, setUser] = useState<UserLite | null>(null);

  // โหลดจาก sessionStorage
  useEffect(() => {
    try {
      const raw = sessionStorage.getItem("reserveDraft");
      setDraft(raw ? (JSON.parse(raw) as ReserveDraft) : null);
    } catch {
      setDraft(null);
    }
    setUser(readUserCookie());
  }, []);

  // สร้างค่าเริ่มต้นแบบฉลาด
  const initial: ContractForm = useMemo(
    () => ({
      lessorName: "",
      lessorTaxId: "",
      lessorAddress: "",
      lessorEmail: "",
      lessorPhone: "",
      lesseeName: user?.fullname || user?.username || user?.email || "", // auto-fill ผู้เช่า
      lesseeTaxId: "",
      lesseeAddress: "",
      lesseeEmail: user?.email || "",
      lesseePhone: "",

      placeTitle: draft?.title || "",
      placeLocation: draft?.locationText || "",
      placeSize: "",

      startDate: draft?.fromDate || "",
      endDate: draft?.toDate || "",
      payUnit: draft?.unit || "เดือน",
      pricePerUnit: draft?.price ?? 0,
      deposit: Math.round((draft?.totalPrice || 0) * 0.2), // สมมติ มัดจำ 20% ของยอดรวม
      billingCycle: "ต้นงวด",
      lateFeePerDay: 200, // สมมติ
      annualIncreasePercent: 3, // สมมติ

      maintenanceNote:
        "ผู้เช่ารับผิดชอบความสะอาดภายในพื้นที่; โครงสร้างหลักผู้ให้เช่าดูแล",
      prohibitedNote:
        "ห้ามดัดแปลงโครงสร้าง ถอด/เพิ่มผนัง, ห้ามเก็บวัตถุอันตราย/ไวไฟ",

      signerLessor: "",
      signerLessee: user?.fullname || user?.username || "",
      witness1: "",
      witness2: "",
      remark: "",
    }),
    [draft, user]
  );

  const [form, setForm] = useState<ContractForm>(initial);
  useEffect(() => {
    setForm(initial);
  }, [initial]);

  const set = <K extends keyof ContractForm>(key: K, value: ContractForm[K]) =>
    setForm((prev) => ({ ...prev, [key]: value }));

  const totalText = useMemo(() => {
    if (!draft) return "-";
    const price = (draft.totalPrice ?? 0).toLocaleString("th-TH");
    return `${price} บาท (${draft.totalDays ?? 0} วัน)`;
  }, [draft]);

  const isBasicValid =
    form.lessorName &&
    form.lesseeName &&
    form.placeTitle &&
    form.placeLocation &&
    form.startDate &&
    form.endDate &&
    form.pricePerUnit > 0;

  const handleSave = () => {
    // เก็บเป็น contractDraft เพื่อไปหน้าพรีวิว/สร้าง PDF ต่อ
    sessionStorage.setItem("contractDraft", JSON.stringify({ form, draft }));
  };

  return (
    <>
      <Header />
      <Container maxWidth="lg" sx={{ py: { xs: 4, md: 6 } }}>
        {!draft ? (
          <Alert severity="warning">
            ไม่พบข้อมูลการจอง — โปรดกลับไปเลือกช่วงวันที่ก่อน
          </Alert>
        ) : (
          <Grid container spacing={3}>
            {/* ซ้าย: แบบฟอร์ม */}
            <Grid size={{ xs: 12, md: 7 }}>
              <Paper sx={{ p: { xs: 3, md: 4 }, display: "grid", gap: 2.25 }}>
                <Typography variant="h6" fontWeight={900}>
                  บันทึกข้อมูลการทำสัญญา
                </Typography>

                {/* คู่สัญญา */}
                <Typography fontWeight={800}>1) ข้อมูลคู่สัญญา</Typography>
                <Grid container spacing={1.5}>
                  <Grid size={{ xs: 12 }}>
                    <Typography
                      variant="body2"
                      sx={{ color: "text.secondary" }}
                    >
                      ผู้ให้เช่า
                    </Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <TextField
                      label="ชื่อผู้ให้เช่า/นิติบุคคล"
                      value={form.lessorName}
                      onChange={(e) => set("lessorName", e.target.value)}
                      fullWidth
                    />
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <TextField
                      label="เลขผู้เสียภาษี/เลขบัตร"
                      value={form.lessorTaxId}
                      onChange={(e) => set("lessorTaxId", e.target.value)}
                      fullWidth
                    />
                  </Grid>
                  <Grid size={{ xs: 12 }}>
                    <TextField
                      label="ที่อยู่"
                      value={form.lessorAddress}
                      onChange={(e) => set("lessorAddress", e.target.value)}
                      fullWidth
                    />
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <TextField
                      label="อีเมล"
                      value={form.lessorEmail}
                      onChange={(e) => set("lessorEmail", e.target.value)}
                      fullWidth
                    />
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <TextField
                      label="โทรศัพท์"
                      value={form.lessorPhone}
                      onChange={(e) => set("lessorPhone", e.target.value)}
                      fullWidth
                    />
                  </Grid>

                  <Grid size={{ xs: 12 }} sx={{ mt: 1 }}>
                    <Typography
                      variant="body2"
                      sx={{ color: "text.secondary" }}
                    >
                      ผู้เช่า
                    </Typography>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <TextField
                      label="ชื่อผู้เช่า/นิติบุคคล"
                      value={form.lesseeName}
                      onChange={(e) => set("lesseeName", e.target.value)}
                      fullWidth
                    />
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <TextField
                      label="เลขผู้เสียภาษี/เลขบัตร"
                      value={form.lesseeTaxId}
                      onChange={(e) => set("lesseeTaxId", e.target.value)}
                      fullWidth
                    />
                  </Grid>
                  <Grid size={{ xs: 12 }}>
                    <TextField
                      label="ที่อยู่"
                      value={form.lesseeAddress}
                      onChange={(e) => set("lesseeAddress", e.target.value)}
                      fullWidth
                    />
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <TextField
                      label="อีเมล"
                      value={form.lesseeEmail}
                      onChange={(e) => set("lesseeEmail", e.target.value)}
                      fullWidth
                    />
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <TextField
                      label="โทรศัพท์"
                      value={form.lesseePhone}
                      onChange={(e) => set("lesseePhone", e.target.value)}
                      fullWidth
                    />
                  </Grid>
                </Grid>

                <Divider />

                {/* พื้นที่ */}
                <Typography fontWeight={800}>
                  2) ข้อมูลทรัพย์/พื้นที่
                </Typography>
                <Grid container spacing={1.5}>
                  <Grid size={{ xs: 12 }}>
                    <TextField
                      label="ชื่อพื้นที่"
                      value={form.placeTitle}
                      onChange={(e) => set("placeTitle", e.target.value)}
                      fullWidth
                    />
                  </Grid>
                  <Grid size={{ xs: 12 }}>
                    <TextField
                      label="ที่ตั้ง"
                      value={form.placeLocation}
                      onChange={(e) => set("placeLocation", e.target.value)}
                      fullWidth
                    />
                  </Grid>
                  <Grid size={{ xs: 12 }}>
                    <TextField
                      label="ขนาดพื้นที่ (เช่น 120 ตร.ม.)"
                      value={form.placeSize}
                      onChange={(e) => set("placeSize", e.target.value)}
                      fullWidth
                    />
                  </Grid>
                </Grid>

                <Divider />

                {/* ระยะเวลา/ราคา */}
                <Typography fontWeight={800}>
                  3) ระยะเวลาเช่า & ค่าตอบแทน
                </Typography>
                <Grid container spacing={1.5}>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <TextField
                      type="date"
                      label="วันเริ่มสัญญา"
                      InputLabelProps={{ shrink: true }}
                      value={form.startDate}
                      onChange={(e) => set("startDate", e.target.value)}
                      fullWidth
                    />
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <TextField
                      type="date"
                      label="วันสิ้นสุดสัญญา"
                      InputLabelProps={{ shrink: true }}
                      value={form.endDate}
                      onChange={(e) => set("endDate", e.target.value)}
                      fullWidth
                    />
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <TextField
                      select
                      label="หน่วยการคิดราคา"
                      value={form.payUnit}
                      onChange={(e) =>
                        set(
                          "payUnit",
                          e.target.value as ContractForm["payUnit"]
                        )
                      }
                      fullWidth
                    >
                      <MenuItem value="วัน">วัน</MenuItem>
                      <MenuItem value="เดือน">เดือน</MenuItem>
                      <MenuItem value="ปี">ปี</MenuItem>
                    </TextField>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <TextField
                      type="number"
                      label={`ราคา/ ${form.payUnit}`}
                      value={form.pricePerUnit}
                      onChange={(e) =>
                        set("pricePerUnit", Number(e.target.value || 0))
                      }
                      fullWidth
                    />
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <TextField
                      type="number"
                      label="มัดจำ (บาท)"
                      value={form.deposit}
                      onChange={(e) =>
                        set("deposit", Number(e.target.value || 0))
                      }
                      fullWidth
                    />
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <TextField
                      select
                      label="รอบบิล"
                      value={form.billingCycle}
                      onChange={(e) =>
                        set(
                          "billingCycle",
                          e.target.value as ContractForm["billingCycle"]
                        )
                      }
                      fullWidth
                    >
                      <MenuItem value="ต้นงวด">ต้นงวด</MenuItem>
                      <MenuItem value="ปลายงวด">ปลายงวด</MenuItem>
                    </TextField>
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <TextField
                      type="number"
                      label="ค่าปรับล่าช้า (บาท/วัน)"
                      value={form.lateFeePerDay}
                      onChange={(e) =>
                        set("lateFeePerDay", Number(e.target.value || 0))
                      }
                      fullWidth
                    />
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <TextField
                      type="number"
                      label="ปรับขึ้นรายปี (%)"
                      value={form.annualIncreasePercent}
                      onChange={(e) =>
                        set(
                          "annualIncreasePercent",
                          Number(e.target.value || 0)
                        )
                      }
                      fullWidth
                    />
                  </Grid>
                </Grid>

                <Divider />

                {/* หน้าที่/ข้อห้าม */}
                <Typography fontWeight={800}>
                  4) หน้าที่–ข้อห้าม (ย่อ)
                </Typography>
                <TextField
                  label="การซ่อมบำรุง (ย่อ)"
                  value={form.maintenanceNote}
                  onChange={(e) => set("maintenanceNote", e.target.value)}
                  fullWidth
                  multiline
                  minRows={2}
                />
                <TextField
                  label="การใช้งานต้องห้าม (ย่อ)"
                  value={form.prohibitedNote}
                  onChange={(e) => set("prohibitedNote", e.target.value)}
                  fullWidth
                  multiline
                  minRows={2}
                />

                <Divider />

                {/* ลายเซ็น */}
                <Typography fontWeight={800}>5) ผู้ลงนาม</Typography>
                <Grid container spacing={1.5}>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <TextField
                      label="ผู้ลงนามฝั่งผู้ให้เช่า"
                      value={form.signerLessor}
                      onChange={(e) => set("signerLessor", e.target.value)}
                      fullWidth
                    />
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <TextField
                      label="ผู้ลงนามฝั่งผู้เช่า"
                      value={form.signerLessee}
                      onChange={(e) => set("signerLessee", e.target.value)}
                      fullWidth
                    />
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <TextField
                      label="พยาน 1 (ถ้ามี)"
                      value={form.witness1}
                      onChange={(e) => set("witness1", e.target.value)}
                      fullWidth
                    />
                  </Grid>
                  <Grid size={{ xs: 12, sm: 6 }}>
                    <TextField
                      label="พยาน 2 (ถ้ามี)"
                      value={form.witness2}
                      onChange={(e) => set("witness2", e.target.value)}
                      fullWidth
                    />
                  </Grid>
                </Grid>

                <TextField
                  label="หมายเหตุท้ายสัญญา"
                  value={form.remark}
                  onChange={(e) => set("remark", e.target.value)}
                  fullWidth
                  multiline
                  minRows={2}
                />

                {/* ปุ่ม */}
                <Box
                  sx={{
                    display: "flex",
                    gap: 1,
                    justifyContent: "flex-end",
                    mt: 1,
                  }}
                >
                  <Button
                    variant="outlined"
                    href={`/checkout/${draft.listingId}`}
                  >
                    ย้อนกลับ
                  </Button>
                  <Button
                    variant="contained"
                    disabled={!isBasicValid}
                    onClick={() => {
                      handleSave();
                      alert("บันทึกสัญญาชั่วคราวแล้ว (contractDraft)");
                    }}
                  >
                    บันทึก
                  </Button>
                </Box>
              </Paper>
            </Grid>

            {/* ขวา: สรุปสาระสำคัญ (เหมือนใบปะหน้า) */}
            <Grid size={{ xs: 12, md: 5 }}>
              <Paper sx={{ p: { xs: 3, md: 4 }, position: "sticky", top: 24 }}>
                <Typography variant="h6" fontWeight={900} gutterBottom>
                  สรุปสัญญา
                </Typography>
                <Box sx={{ display: "grid", gap: 1.25, fontSize: 14 }}>
                  <Box>
                    <Typography fontWeight={800}>คู่สัญญา</Typography>
                    <Typography sx={{ color: "text.secondary" }}>
                      ผู้ให้เช่า: {form.lessorName || "-"}
                      <br />
                      ผู้เช่า: {form.lesseeName || "-"}
                    </Typography>
                  </Box>
                  <Divider />
                  <Box>
                    <Typography fontWeight={800}>ทรัพย์/พื้นที่</Typography>
                    <Typography sx={{ color: "text.secondary" }}>
                      {form.placeTitle || "-"}
                      <br />
                      {form.placeLocation || "-"}
                      <br />
                      {form.placeSize ? `ขนาด: ${form.placeSize}` : ""}
                    </Typography>
                  </Box>
                  <Divider />
                  <Box>
                    <Typography fontWeight={800}>ระยะเวลา & เงิน</Typography>
                    <Typography sx={{ color: "text.secondary" }}>
                      {form.startDate || "-"} ถึง {form.endDate || "-"}
                      <br />
                      ราคา/หน่วย: {form.pricePerUnit?.toLocaleString(
                        "th-TH"
                      )} / {form.payUnit}
                      <br />
                      มัดจำ: {form.deposit?.toLocaleString("th-TH")} บาท
                      <br />
                      ยอดรวมจากการจอง: {totalText}
                    </Typography>
                  </Box>
                  <Divider />
                  <Box>
                    <Typography fontWeight={800}>รอบบิล/ปรับราคา</Typography>
                    <Typography sx={{ color: "text.secondary" }}>
                      รอบบิล: {form.billingCycle}
                      <br />
                      ล่าช้า: {form.lateFeePerDay} บ./วัน
                      <br />
                      ปรับขึ้นรายปี: {form.annualIncreasePercent}%
                    </Typography>
                  </Box>
                  <Divider />
                  <Box>
                    <Typography fontWeight={800}>ข้อกำหนดย่อ</Typography>
                    <Typography sx={{ color: "text.secondary" }}>
                      {form.maintenanceNote}
                      <br />
                      {form.prohibitedNote}
                    </Typography>
                  </Box>
                </Box>

                <Box
                  sx={{
                    display: "flex",
                    gap: 1,
                    justifyContent: "flex-end",
                    mt: 2,
                  }}
                >
                  <Button
                    variant="outlined"
                    onClick={() => {
                      const raw = sessionStorage.getItem("contractDraft");
                      if (!raw) return alert("ยังไม่ได้บันทึกสัญญา");
                      // ต่อยอด: พาไปหน้าพรีวิว/สร้าง PDF
                      alert("พร้อมสำหรับสร้าง PDF/ส่งอีเมล (จำลอง)");
                    }}
                  >
                    พรีวิว PDF
                  </Button>
                  <Button
                    variant="contained"
                    onClick={() => {
                      const raw = sessionStorage.getItem("contractDraft");
                      if (!raw) return alert("ยังไม่ได้บันทึกสัญญา");
                      // ต่อยอดจริง: call API สร้าง PDF + ส่งอีเมลให้คู่สัญญาเซ็น
                      alert("สร้าง PDF และส่งอีเมล (จำลอง)");
                    }}
                  >
                    สร้าง PDF & ส่งอีเมล
                  </Button>
                </Box>
              </Paper>
            </Grid>
          </Grid>
        )}
      </Container>
    </>
  );
}
