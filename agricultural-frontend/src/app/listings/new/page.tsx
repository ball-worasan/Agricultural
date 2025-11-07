"use client";

import { useEffect, useMemo, useState } from "react";
import Header from "@/components/Header";

import Container from "@mui/material/Container";
import Grid from "@mui/material/Grid"; // MUI Grid v2 (prop size)
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import TextField from "@mui/material/TextField";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Divider from "@mui/material/Divider";
import Snackbar from "@mui/material/Snackbar";
import Alert from "@mui/material/Alert";
import Chip from "@mui/material/Chip";
import MenuItem from "@mui/material/MenuItem";
import Select from "@mui/material/Select";
import InputLabel from "@mui/material/InputLabel";
import FormControl from "@mui/material/FormControl";
import List from "@mui/material/List";
import ListItemButton from "@mui/material/ListItemButton";
import ListItemIcon from "@mui/material/ListItemIcon";
import ListItemText from "@mui/material/ListItemText";
import Avatar from "@mui/material/Avatar";
import IconButton from "@mui/material/IconButton";
import Tooltip from "@mui/material/Tooltip";
import InputAdornment from "@mui/material/InputAdornment";
import Skeleton from "@mui/material/Skeleton";

import MenuIcon from "@mui/icons-material/Menu";
import HomeIcon from "@mui/icons-material/Home";
import HistoryIcon from "@mui/icons-material/History";
import SettingsIcon from "@mui/icons-material/Settings";
import LogoutIcon from "@mui/icons-material/Logout";
import ImageIcon from "@mui/icons-material/Image";
import UploadIcon from "@mui/icons-material/Upload";
import DeleteForeverIcon from "@mui/icons-material/DeleteForever";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import SaveIcon from "@mui/icons-material/Save";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import PlaceIcon from "@mui/icons-material/Place";
import LocalOfferIcon from "@mui/icons-material/LocalOffer";

import { useRouter } from "next/navigation";

/* ===== จังหวัดและอำเภอทั้งหมด 77 จังหวัด ===== */
const PROVINCES: Record<string, string[]> = {
  กรุงเทพมหานคร: [
    "พระนคร",
    "ดุสิต",
    "หนองจอก",
    "บางรัก",
    "บางเขน",
    "บางกะปิ",
    "ปทุมวัน",
    "ป้อมปราบศัตรูพ่าย",
    "พระโขนง",
    "มีนบุรี",
    "ลาดกระบัง",
    "ยานนาวา",
    "สัมพันธวงศ์",
    "พญาไท",
    "ธนบุรี",
    "บางกอกใหญ่",
    "ห้วยขวาง",
    "คลองสาน",
    "ตลิ่งชัน",
    "บางกอกน้อย",
    "บางขุนเทียน",
    "ภาษีเจริญ",
    "หนองแขม",
    "ราษฎร์บูรณะ",
    "บางพลัด",
    "ดินแดง",
    "บึงกุ่ม",
    "สาทร",
    "บางซื่อ",
    "จตุจักร",
    "บางคอแหลม",
    "ประเวศ",
    "คลองเตย",
    "สวนหลวง",
    "จอมทอง",
    "ดอนเมือง",
    "ราชเทวี",
    "ลาดพร้าว",
    "วัฒนา",
    "บางแค",
    "หลักสี่",
    "สายไหม",
    "คันนายาว",
    "สะพานสูง",
    "วังทองหลาง",
    "คลองสามวา",
    "บางนา",
    "ทวีวัฒนา",
    "ทุ่งครุ",
    "บางบอน",
  ],
  กระบี่: [
    "เมืองกระบี่",
    "เขาพนม",
    "เกาะลันตา",
    "คลองท่อม",
    "อ่าวลึก",
    "ปลายพระยา",
    "ลำทับ",
    "เหนือคลอง",
  ],
  กาญจนบุรี: [
    "เมืองกาญจนบุรี",
    "ไทรโยค",
    "บ่อพลอย",
    "ศรีสวัสดิ์",
    "ท่ามะกา",
    "ท่าม่วง",
    "ทองผาภูมิ",
    "สังขละบุรี",
    "พนมทวน",
    "เลาขวัญ",
    "ด่านมะขามเตี้ย",
    "หนองปรือ",
    "ห้วยกระเจา",
  ],
  กาฬสินธุ์: [
    "เมืองกาฬสินธุ์",
    "นามน",
    "กมลาไสย",
    "ร่องคำ",
    "กุฉินารายณ์",
    "เขาวง",
    "ยางตลาด",
    "ห้วยเม็ก",
    "สหัสขันธ์",
    "คำม่วง",
    "ท่าคันโท",
    "หนองกุงศรี",
    "สมเด็จ",
    "ห้วยผึ้ง",
    "สามชัย",
    "นาคู",
    "ดอนจาน",
    "ฆ้องชัย",
  ],
  กำแพงเพชร: [
    "เมืองกำแพงเพชร",
    "ไทรงาม",
    "คลองลาน",
    "ขาณุวรลักษบุรี",
    "คลองขลุง",
    "พรานกระต่าย",
    "ลานกระบือ",
    "ทรายทองวัฒนา",
    "ปางศิลาทอง",
    "บึงสามัคคี",
    "โกสัมพีนคร",
  ],
  ขอนแก่น: [
    "เมืองขอนแก่น",
    "บ้านฝาง",
    "พระยืน",
    "หนองเรือ",
    "ชุมแพ",
    "สีชมพู",
    "น้ำพอง",
    "อุบลรัตน์",
    "กระนวน",
    "บ้านไผ่",
    "เปือยน้อย",
    "พล",
    "แวงใหญ่",
    "แวงน้อย",
    "หนองสองห้อง",
    "ภูเวียง",
    "มัญจาคีรี",
    "ชนบท",
    "เขาสวนกวาง",
    "ภูผาม่าน",
    "ซำสูง",
    "โคกโพธิ์ไชย",
    "หนองนาคำ",
    "บ้านแฮด",
    "โนนศิลา",
    "เวียงเก่า",
  ],
  จันทบุรี: [
    "เมืองจันทบุรี",
    "ขลุง",
    "ท่าใหม่",
    "โป่งน้ำร้อน",
    "มะขาม",
    "แหลมสิงห์",
    "สอยดาว",
    "แก่งหางแมว",
    "นายายอาม",
    "เขาคิชฌกูฏ",
  ],
  ฉะเชิงเทรา: [
    "เมืองฉะเชิงเทรา",
    "บางคล้า",
    "บางน้ำเปรี้ยว",
    "บางปะกง",
    "บ้านโพธิ์",
    "พนมสารคาม",
    "ราชสาส์น",
    "สนามชัยเขต",
    "แปลงยาว",
    "ท่าตะเกียบ",
    "คลองเขื่อน",
  ],
  ชลบุรี: [
    "เมืองชลบุรี",
    "บ้านบึง",
    "หนองใหญ่",
    "บางละมุง",
    "พานทอง",
    "พนัสนิคม",
    "ศรีราชา",
    "เกาะสีชัง",
    "สัตหีบ",
    "บ่อทอง",
    "เกาะจันทร์",
    "หนองใหญ่",
    "โป่งน้ำร้อน",
  ],
  ชัยนาท: [
    "เมืองชัยนาท",
    "มโนรมย์",
    "วัดสิงห์",
    "สรรพยา",
    "สรรคบุรี",
    "หันคา",
    "หนองมะโมง",
    "เนินขาม",
  ],
  ชัยภูมิ: [
    "เมืองชัยภูมิ",
    "บ้านเขว้า",
    "คอนสวรรค์",
    "เกษตรสมบูรณ์",
    "แก้งคร้อ",
    "หนองบัวแดง",
    "จัตุรัส",
    "บำเหน็จณรงค์",
    "หนองบัวระเหว",
    "เทพสถิต",
    "ภูเขียว",
    "บ้านแท่น",
    "แก้งคร้อ",
    "คอนสาร",
    "ซับใหญ่",
    "เนินสง่า",
  ],
  ชุมพร: [
    "เมืองชุมพร",
    "ท่าแซะ",
    "ปะทิว",
    "หลังสวน",
    "ละแม",
    "พะโต๊ะ",
    "สวี",
    "ทุ่งตะโก",
  ],
  เชียงราย: [
    "เมืองเชียงราย",
    "เวียงชัย",
    "เชียงของ",
    "เทิง",
    "พาน",
    "ป่าแดด",
    "แม่จัน",
    "เชียงแสน",
    "แม่สาย",
    "แม่สรวย",
    "เวียงป่าเป้า",
    "พญาเม็งราย",
    "เวียงแก่น",
    "ขุนตาล",
    "แม่ฟ้าหลวง",
    "แม่ลาว",
    "เวียงเชียงรุ้ง",
    "ดอยหลวง",
  ],
  เชียงใหม่: [
    "เมืองเชียงใหม่",
    "จอมทอง",
    "แม่แจ่ม",
    "เชียงดาว",
    "ดอยสะเก็ด",
    "แม่ริม",
    "สะเมิง",
    "ฝาง",
    "แม่อาย",
    "พร้าว",
    "สันป่าตอง",
    "สันกำแพง",
    "สันทราย",
    "หางดง",
    "ฮอด",
    "ดอยเต่า",
    "อมก๋อย",
    "สารภี",
    "เวียงแหง",
    "ไชยปราการ",
    "แม่วาง",
    "แม่ออน",
    "ดอยหล่อ",
    "กัลยาณิวัฒนา",
    "แม่แตง",
  ],
  ตรัง: [
    "เมืองตรัง",
    "กันตัง",
    "ย่านตาขาว",
    "ปะเหลียน",
    "สิเกา",
    "ห้วยยอด",
    "วังวิเศษ",
    "นาโยง",
    "รัษฎา",
    "หาดสำราญ",
  ],
  ตราด: [
    "เมืองตราด",
    "คลองใหญ่",
    "เขาสมิง",
    "บ่อไร่",
    "แหลมงอบ",
    "เกาะกูด",
    "เกาะช้าง",
  ],
  ตาก: [
    "เมืองตาก",
    "บ้านตาก",
    "สามเงา",
    "แม่ระมาด",
    "ท่าสองยาง",
    "แม่สอด",
    "พบพระ",
    "อุ้มผาง",
    "วังเจ้า",
  ],
  นครนายก: ["เมืองนครนายก", "ปากพลี", "บ้านนา", "องครักษ์"],
  นครปฐม: [
    "เมืองนครปฐม",
    "กำแพงแสน",
    "นครชัยศรี",
    "ดอนตูม",
    "บางเลน",
    "สามพราน",
    "พุทธมณฑล",
  ],
  นครพนม: [
    "เมืองนครพนม",
    "ปลาปาก",
    "ท่าอุเทน",
    "บ้านแพง",
    "ธาตุพนม",
    "เรณูนคร",
    "นาแก",
    "ศรีสงคราม",
    "นาหว้า",
    "โพนสวรรค์",
    "นาทม",
    "วังยาง",
  ],
  นครราชสีมา: [
    "เมืองนครราชสีมา",
    "ครบุรี",
    "เสิงสาง",
    "คง",
    "บ้านเหลื่อม",
    "จักราช",
    "โชคชัย",
    "ด่านขุนทด",
    "โนนไทย",
    "โนนสูง",
    "ขามสะแกแสง",
    "บัวใหญ่",
    "ประทาย",
    "ปักธงชัย",
    "พิมาย",
    "ห้วยแถลง",
    "ชุมพวง",
    "สูงเนิน",
    "ขามทะเลสอ",
    "สีคิ้ว",
    "ปากช่อง",
    "หนองบุญมาก",
    "แก้งสนามนาง",
    "โนนแดง",
    "วังน้ำเขียว",
    "เทพารักษ์",
    "เมืองยาง",
    "พระทองคำ",
    "ลำทะเมนชัย",
    "บัวลาย",
    "สีดา",
    "เฉลิมพระเกียรติ",
  ],
  นครศรีธรรมราช: [
    "เมืองนครศรีธรรมราช",
    "พรหมคีรี",
    "ลานสกา",
    "ฉวาง",
    "พิปูน",
    "เชียรใหญ่",
    "ชะอวด",
    "ท่าศาลา",
    "ทุ่งสง",
    "นาบอน",
    "ทุ่งใหญ่",
    "ปากพนัง",
    "ร่อนพิบูลย์",
    "สิชล",
    "ขนอม",
    "หัวไทร",
    "บางขัน",
    "ถ้ำพรรณรา",
    "จุฬาภรณ์",
    "พระพรหม",
    "นบพิตำ",
    "ช้างกลาง",
    "เฉลิมพระเกียรติ",
  ],
  นครสวรรค์: [
    "เมืองนครสวรรค์",
    "โกรกพระ",
    "ชุมแสง",
    "หนองบัว",
    "บรรพตพิสัย",
    "เก้าเลี้ยว",
    "ตาคลี",
    "ท่าตะโก",
    "ไพศาลี",
    "พยุหะคีรี",
    "ลาดยาว",
    "ตากฟ้า",
    "แม่วงก์",
    "แม่เปิน",
    "ชุมตาบง",
  ],
  นนทบุรี: [
    "เมืองนนทบุรี",
    "บางกรวย",
    "บางใหญ่",
    "บางบุวทอง",
    "ไทรน้อย",
    "ปากเกร็ด",
  ],
  นราธิวาส: [
    "เมืองนราธิวาส",
    "ตากใบ",
    "บาเจาะ",
    "ยี่งอ",
    "ระแงะ",
    "รือเสาะ",
    "ศรีสาคร",
    "แว้ง",
    "สุคิริน",
    "สุไหงโก-ลก",
    "สุไหงปาดี",
    "จะแนะ",
    "เจาะไอร้อง",
  ],
  น่าน: [
    "เมืองน่าน",
    "แม่จริม",
    "บ้านหลวง",
    "นาน้อย",
    "ปัว",
    "ทุ่งช้าง",
    "เวียงสา",
    "ท่าวังผา",
    "เฉลิมพระเกียรติ",
    "นาหมื่น",
    "สันติสุข",
    "บ่อเกลือ",
    "สองแคว",
    "ภูเพียง",
    "เชียงกลาง",
  ],
  บึงกาฬ: [
    "เมืองบึงกาฬ",
    "พรเจริญ",
    "โซ่พิสัย",
    "เซกา",
    "ปากคาด",
    "บุ่งคล้า",
    "ศรีวิไล",
    "บึงโขงหลง",
  ],
  บุรีรัมย์: [
    "เมืองบุรีรัมย์",
    "คูเมือง",
    "กระสัง",
    "นางรอง",
    "หนองกี่",
    "ละหานทราย",
    "ประโคนชัย",
    "บ้านกรวด",
    "พุทไธสง",
    "ลำปลายมาศ",
    "สตึก",
    "ปะคำ",
    "นาโพธิ์",
    "หนองหงส์",
    "พลับพลาชัย",
    "ห้วยราช",
    "โนนสุวรรณ",
    "ชำนิ",
    "บ้านใหม่ไชยพจน์",
    "โนนดินแดง",
    "บ้านด่าน",
    "แคนดง",
    "เฉลิมพระเกียรติ",
  ],
  ปทุมธานี: [
    "เมืองปทุมธานี",
    "คลองหลวง",
    "ธัญบุรี",
    "หนองเสือ",
    "ลาดหลุมแก้ว",
    "ลำลูกกา",
    "สามโคก",
  ],
  ประจวบคีรีขันธ์: [
    "เมืองประจวบคีรีขันธ์",
    "กุยบุรี",
    "ทับสะแก",
    "บางสะพาน",
    "บางสะพานน้อย",
    "ปราณบุรี",
    "หัวหิน",
    "สามร้อยยอด",
  ],
  ปราจีนบุรี: [
    "เมืองปราจีนบุรี",
    "กบินทร์บุรี",
    "นาดี",
    "บ้านสร้าง",
    "ประจันตคาม",
    "ศรีมโหสถ",
    "ศรีมหาโพธิ",
    "กบินทร์บุรี",
  ],
  ปัตตานี: [
    "เมืองปัตตานี",
    "โคกโพธิ์",
    "หนองจิก",
    "ปะนาเระ",
    "มายอ",
    "ทุ่งยางแดง",
    "สายบุรี",
    "ไม้แก่น",
    "ยะหริ่ง",
    "ยะรัง",
    "กะพ้อ",
    "แม่ลาน",
  ],
  พระนครศรีอยุธยา: [
    "เมืองพระนครศรีอยุธยา",
    "ท่าเรือ",
    "นครหลวง",
    "บางไทร",
    "บางบาล",
    "บางปะอิน",
    "บางปะหัน",
    "ผักไห่",
    "ภาชี",
    "ลาดบัวหลวง",
    "วังน้อย",
    "เสนา",
    "บางซ้าย",
    "อุทัย",
    "มหาราช",
    "บ้านแพรก",
  ],
  พะเยา: [
    "เมืองพะเยา",
    "จุน",
    "เชียงคำ",
    "เชียงม่วน",
    "ดอกคำใต้",
    "ปง",
    "แม่ใจ",
    "ภูซาง",
    "ภูกามยาว",
  ],
  พังงา: [
    "เมืองพังงา",
    "เกาะยาว",
    "กะปง",
    "ตะกั่วทุ่ง",
    "ตะกั่วป่า",
    "คุระบุรี",
    "ทับปุด",
    "ท้ายเหมือง",
  ],
  พัทลุง: [
    "เมืองพัทลุง",
    "กงหรา",
    "เขาชัยสน",
    "ตะโหมด",
    "ควนขนุน",
    "ปากพะยูน",
    "ศรีบรรพต",
    "ป่าบอน",
    "บางแก้ว",
    "ป่าพะยอม",
    "ศรีนครินทร์",
  ],
  พิจิตร: [
    "เมืองพิจิตร",
    "วังทรายพูน",
    "โพธิ์ประทับช้าง",
    "ตะพานหิน",
    "บางมูลนาก",
    "โพทะเล",
    "สามง่าม",
    "ทับคล้อ",
    "สากเหล็ก",
    "บึงนาราง",
    "ดงเจริญ",
    "วชิรบารมี",
  ],
  พิษณุโลก: [
    "เมืองพิษณุโลก",
    "นครไทย",
    "ชาติตระการ",
    "บางระกำ",
    "บางกระทุ่ม",
    "พรหมพิราม",
    "วัดโบสถ์",
    "วังทอง",
    "เนินมะปราง",
  ],
  เพชรบุรี: [
    "เมืองเพชรบุรี",
    "เขาย้อย",
    "หนองหญ้าปล้อง",
    "ชะอำ",
    "ท่ายาง",
    "บ้านลาด",
    "บ้านแหลม",
    "แก่งกระจาน",
  ],
  เพชรบูรณ์: [
    "เมืองเพชรบูรณ์",
    "ชนแดน",
    "วิเชียรบุรี",
    "ศรีเทพ",
    "หนองไผ่",
    "บึงสามพัน",
    "น้ำหนาว",
    "วังโป่ง",
    "เขาค้อ",
    "หล่มสัก",
    "หล่มเก่า",
  ],
  แพร่: [
    "เมืองแพร่",
    "ร้องกวาง",
    "ลอง",
    "สูงเม่น",
    "เด่นชัย",
    "สอง",
    "วังชิ้น",
    "หนองม่วงไข่",
  ],
  ภูเก็ต: ["เมืองภูเก็ต", "กะทู้", "ถลาง"],
  มหาสารคาม: [
    "เมืองมหาสารคาม",
    "กันทรวิชัย",
    "เชียงยืน",
    "บรบือ",
    "นาเชือก",
    "พยัคฆภูมิพิสัย",
    "วาปีปทุม",
    "นาดูน",
    "ยางสีสุราช",
    "กุดรัง",
    "ชื่นชม",
    "นาดูน",
    "โกสุมพิสัย",
  ],
  มุกดาหาร: [
    "เมืองมุกดาหาร",
    "นิคมคำสร้อย",
    "ดอนตาล",
    "ดงหลวง",
    "คำชะอี",
    "หว้านใหญ่",
    "หนองสูง",
  ],
  แม่ฮ่องสอน: [
    "เมืองแม่ฮ่องสอน",
    "ขุนยวม",
    "ปาย",
    "แม่สะเรียง",
    "แม่ลาน้อย",
    "สบเมย",
    "ปางมะผ้า",
  ],
  ยโสธร: [
    "เมืองยโสธร",
    "ทรายมูล",
    "กุดชุม",
    "คำเขื่อนแก้ว",
    "ป่าติ้ว",
    "มหาชนะชัย",
    "ค้อวัง",
    "เลิงนกทา",
    "ไทยเจริญ",
  ],
  ยะลา: [
    "เมืองยะลา",
    "เบตง",
    "บันนังสตา",
    "ธารโต",
    "ยะหา",
    "รามัน",
    "กาบัง",
    "กรงปินัง",
  ],
  ร้อยเอ็ด: [
    "เมืองร้อยเอ็ด",
    "เกษตรวิสัย",
    "ปทุมรัตต์",
    "จตุรพักตรพิมาน",
    "ธวัชบุรี",
    "พนมไพร",
    "โพนทอง",
    "โพธิ์ชัย",
    "หนองพอก",
    "เสลภูมิ",
    "สุวรรณภูมิ",
    "เมืองสรวง",
    "โพนทราย",
    "อาจสามารถ",
    "เมยวดี",
    "ศรีสมเด็จ",
    "จังหาร",
    "เชียงขวัญ",
    "หนองฮี",
    "ทุ่งเขาหลวง",
  ],
  ระนอง: ["เมืองระนอง", "ละอุ่น", "กะเปอร์", "กระบุรี", "สุขสำราญ"],
  ระยอง: [
    "เมืองระยอง",
    "บ้านฉาง",
    "แกลง",
    "วังจันทร์",
    "บ้านค่าย",
    "ปลวกแดง",
    "เขาชะเมา",
    "นิคมพัฒนา",
  ],
  ราชบุรี: [
    "เมืองราชบุรี",
    "จอมบึง",
    "สวนผึ้ง",
    "ดำเนินสะดวก",
    "บ้านโป่ง",
    "บางแพ",
    "โพธาราม",
    "ปากท่อ",
    "วัดเพลง",
    "บ้านคา",
  ],
  ลพบุรี: [
    "เมืองลพบุรี",
    "พัฒนานิคม",
    "โคกสำโรง",
    "ชัยบาดาล",
    "ท่าวุ้ง",
    "บ้านหมี่",
    "ท่าหลวง",
    "สระโบสถ์",
    "โคกเจริญ",
    "ลำสนธิ",
    "หนองม่วง",
  ],
  ลำปาง: [
    "เมืองลำปาง",
    "แม่เมาะ",
    "เกาะคา",
    "เสริมงาม",
    "งาว",
    "แจ้ห่ม",
    "วังเหนือ",
    "เถิน",
    "แม่พริก",
    "แม่ทะ",
    "สบปราบ",
    "ห้างฉัตร",
    "เมืองปาน",
  ],
  ลำพูน: [
    "เมืองลำพูน",
    "แม่ทา",
    "บ้านโฮ่ง",
    "ลี้",
    "ทุ่งหัวช้าง",
    "ป่าซาง",
    "บ้านธิ",
    "เวียงหนองล่อง",
  ],
  เลย: [
    "เมืองเลย",
    "นาด้วง",
    "เชียงคาน",
    "ปากชม",
    "ด่านซ้าย",
    "นาแห้ว",
    "ภูเรือ",
    "ท่าลี่",
    "วังสะพุง",
    "ภูกระดึง",
    "ภูหลวง",
    "ผาขาว",
    "เอราวัณ",
    "หนองหิน",
  ],
  ศรีสะเกษ: [
    "เมืองศรีสะเกษ",
    "ยางชุมน้อย",
    "กันทรารมย์",
    "กันทรลักษ์",
    "ขุขันธ์",
    "ไพรบึง",
    "ปรางค์กู่",
    "ขุนหาญ",
    "ราษีไศล",
    "อุทุมพรพิสัย",
    "บึงบูรพ์",
    "ห้วยทับทัน",
    "โนนคูณ",
    "ศรีรัตนะ",
    "น้ำเกลี้ยง",
    "วังหิน",
    "ภูสิงห์",
    "เมืองจันทร์",
    "เบญจลักษ์",
    "พยุห์",
    "โพธิ์ศรีสุวรรณ",
    "ศิลาลาด",
  ],
  สกลนคร: [
    "เมืองสกลนคร",
    "กุสุมาลย์",
    "กุดบาก",
    "พรรณานิคม",
    "พังโคน",
    "วาริชภูมิ",
    "นิคมน้ำอูน",
    "วานรนิวาส",
    "คำตากล้า",
    "บ้านม่วง",
    "อากาศอำนวย",
    "สว่างแดนดิน",
    "ส่องดาว",
    "เต่างอย",
    "โคกศรีสุพรรณ",
    "เจริญศิลป์",
    "โพนนาแก้ว",
    "ภูพาน",
  ],
  สงขลา: [
    "เมืองสงขลา",
    "สทิงพระ",
    "จะนะ",
    "นาทวี",
    "เทพา",
    "สะบ้าย้อย",
    "ระโนด",
    "กระแสสินธุ์",
    "รัตภูมิ",
    "สะเดา",
    "หาดใหญ่",
    "นาหม่อม",
    "ควนเนียง",
    "บางกล่ำ",
    "สิงหนคร",
    "เขาชัยสน",
  ],
  สตูล: [
    "เมืองสตูล",
    "ควนโดน",
    "ควนกาหลง",
    "ท่าแพ",
    "ละงู",
    "ทุ่งหว้า",
    "มะนัง",
  ],
  สมุทรปราการ: [
    "เมืองสมุทรปราการ",
    "บางบ่อ",
    "บางพลี",
    "พระประแดง",
    "พระสมุทรเจดีย์",
    "บางเสาธง",
  ],
  สมุทรสงคราม: ["เมืองสมุทรสงคราม", "บางคนที", "อัมพวา"],
  สมุทรสาคร: ["เมืองสมุทรสาคร", "กระทุ่มแบน", "บ้านแพ้ว"],
  สระแก้ว: [
    "เมืองสระแก้ว",
    "คลองหาด",
    "ตาพระยา",
    "วังน้ำเย็น",
    "วัฒนานคร",
    "อรัญประเทศ",
    "เขาฉกรรจ์",
    "โคกสูง",
    "วังสมบูรณ์",
  ],
  สระบุรี: [
    "เมืองสระบุรี",
    "แก่งคอย",
    "หนองแค",
    "วิหารแดง",
    "หนองแซง",
    "บ้านหมอ",
    "ดอนพุด",
    "หนองโดน",
    "พระพุทธบาท",
    "เสาไห้",
    "มวกเหล็ก",
    "วังม่วง",
    "เฉลิมพระเกียรติ",
  ],
  สิงห์บุรี: [
    "เมืองสิงห์บุรี",
    "บางระจัน",
    "ค่ายบางระจัน",
    "พรหมบุรี",
    "ท่าช้าง",
    "อินทร์บุรี",
  ],
  สุโขทัย: [
    "เมืองสุโขทัย",
    "บ้านด่านลานหอย",
    "คีรีมาศ",
    "กงไกรลาศ",
    "ศรีสัชนาลัย",
    "ศรีสำโรง",
    "สวรรคโลก",
    "ศรีนคร",
    "ทุ่งเสลี่ยม",
  ],
  สุพรรณบุรี: [
    "เมืองสุพรรณบุรี",
    "เดิมบางนางบวช",
    "ด่านช้าง",
    "บางปลาม้า",
    "ศรีประจันต์",
    "ดอนเจดีย์",
    "สองพี่น้อง",
    "สามชุก",
    "อู่ทอง",
    "หนองหญ้าไซ",
  ],
  สุราษฎร์ธานี: [
    "เมืองสุราษฎร์ธานี",
    "กาญจนดิษฐ์",
    "ดอนสัก",
    "เกาะสมุย",
    "เกาะพะงัน",
    "ไชยา",
    "ท่าชนะ",
    "คีรีรัฐนิคม",
    "บ้านตาขุน",
    "พนม",
    "ท่าฉาง",
    "บ้านนาสาร",
    "บ้านนาเดิม",
    "เคียนซา",
    "เวียงสระ",
    "พระแสง",
    "พุนพิน",
    "ชัยบุรี",
    "วิภาวดี",
  ],
  สุรินทร์: [
    "เมืองสุรินทร์",
    "ชุมพลบุรี",
    "ท่าตูม",
    "จอมพระ",
    "ปราสาท",
    "กาบเชิง",
    "รัตนบุรี",
    "สนม",
    "ศีขรภูมิ",
    "สังขะ",
    "ลำดวน",
    "สำโรงทาบ",
    "บัวเชด",
    "พนมดงรัก",
    "ศรีณรงค์",
    "เขวาสินรินทร์",
    "โนนนารายณ์",
  ],
  หนองคาย: [
    "เมืองหนองคาย",
    "ท่าบ่อ",
    "โพนพิสัย",
    "ศรีเชียงใหม่",
    "สังคม",
    "สระใคร",
    "เฝ้าไร่",
    "รัตนวาปี",
    "โพธิ์ตาก",
  ],
  หนองบัวลำภู: [
    "เมืองหนองบัวลำภู",
    "นากลาง",
    "โนนสัง",
    "ศรีบุญเรือง",
    "สุวรรณคูหา",
    "นาวัง",
  ],
  อ่างทอง: [
    "เมืองอ่างทอง",
    "ไชโย",
    "ป่าโมก",
    "โพธิ์ทอง",
    "แสวงหา",
    "วิเศษชัยชาญ",
    "สามโก้",
  ],
  อำนาจเจริญ: [
    "เมืองอำนาจเจริญ",
    "ชานุมาน",
    "ปทุมราชวงศา",
    "พนา",
    "เสนางคนิคม",
    "หัวตะพาน",
    "ลืออำนาจ",
  ],
  อุดรธานี: [
    "เมืองอุดรธานี",
    "กุดจับ",
    "หนองวัวซอ",
    "กุมภวาปี",
    "โนนสะอาด",
    "หนองหาน",
    "ทุ่งฝน",
    "ไชยวาน",
    "ศรีธาตุ",
    "วังสามหมอ",
    "บ้านดุง",
    "สร้างคอม",
    "หนองแสง",
    "นายูง",
    "พิบูลย์รักษ์",
    "กู่แก้ว",
    "ประจักษ์ศิลปาคม",
    "เพ็ญ",
    "สามสูง",
    "บ้านผือ",
  ],
  อุตรดิตถ์: [
    "เมืองอุตรดิตถ์",
    "ตรอน",
    "ท่าปลา",
    "น้ำปาด",
    "ฟากท่า",
    "บ้านโคก",
    "พิชัย",
    "ลับแล",
    "ทองแสนขัน",
  ],
  อุทัยธานี: [
    "เมืองอุทัยธานี",
    "ทัพทัน",
    "สว่างอารมณ์",
    "หนองฉาง",
    "หนองขาหย่าง",
    "บ้านไร่",
    "ลานสัก",
    "ห้วยคต",
  ],
  อุบลราชธานี: [
    "เมืองอุบลราชธานี",
    "ศรีเมืองใหม่",
    "โขงเจียม",
    "เขื่องใน",
    "เขมราฐ",
    "เดชอุดม",
    "นาจะหลวย",
    "น้ำยืน",
    "บุณฑริก",
    "ตระการพืชผล",
    "กุดข้าวปุ้น",
    "ม่วงสามสิบ",
    "วารินชำราบ",
    "พิบูลมังสาหาร",
    "ตาลสุม",
    "โพธิ์ไทร",
    "สำโรง",
    "ดอนมดแดง",
    "สิรินธร",
    "ทุ่งศรีอุดม",
    "นาเยีย",
    "นาตาล",
    "เหล่าเสือโก้ก",
    "สว่างวีระวงศ์",
    "น้ำขุ่น",
  ],
};

const AVAILABLE_TAGS = ["ทำนา", "ทำไร่", "ทำสวน", "ฟาร์ม"] as const;

type FormState = {
  title: string;
  province: string;
  district: string;
  areaSize: string; // ขนาดพื้นที่ (เช่น "10 ไร่" / "800 ตร.วา")
  pricePerYear: string; // บาท/ปี
  depositPercent: string; // %
  description: string; // รายละเอียดประกาศ
  tags: string[]; // tags ที่เลือก
  images: string[]; // array ของ base64 images
};

const initialState: FormState = {
  title: "",
  province: "",
  district: "",
  areaSize: "",
  pricePerYear: "",
  depositPercent: "10",
  description: "",
  tags: [],
  images: [],
};

const STORAGE_KEY = "newListingDraft"; // เก็บ draft ชั่วคราว

export default function NewListingPage() {
  const router = useRouter();

  const [form, setForm] = useState<FormState>(initialState);
  const [saving, setSaving] = useState(false);
  const [snack, setSnack] = useState<{
    open: boolean;
    msg: string;
    ok?: boolean;
  }>({
    open: false,
    msg: "",
    ok: false,
  });
  const districts = useMemo(
    () => (form.province ? PROVINCES[form.province] ?? [] : []),
    [form.province]
  );

  // โหลด draft
  useEffect(() => {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY);
      if (raw) setForm({ ...initialState, ...JSON.parse(raw) });
    } catch {}
  }, []);

  // บันทึก draft อัตโนมัติ
  useEffect(() => {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(form));
  }, [form]);

  const handleUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (!files || files.length === 0) return;

    // จำกัดไม่เกิน 10 รูป
    if (form.images.length + files.length > 10) {
      setSnack({
        open: true,
        msg: "สามารถอัปโหลดได้สูงสุด 10 รูป",
        ok: false,
      });
      return;
    }

    Array.from(files).forEach((file) => {
      if (!/^image\/(png|jpe?g)$/i.test(file.type)) {
        setSnack({ open: true, msg: "รองรับเฉพาะ JPG/PNG", ok: false });
        return;
      }
      if (file.size > 5 * 1024 * 1024) {
        setSnack({ open: true, msg: "ไฟล์ใหญ่เกิน 5MB", ok: false });
        return;
      }
      const reader = new FileReader();
      reader.onload = () =>
        setForm((f) => ({
          ...f,
          images: [...f.images, String(reader.result)],
        }));
      reader.readAsDataURL(file);
    });
  };

  const removeImage = (index: number) => {
    setForm((f) => ({
      ...f,
      images: f.images.filter((_, i) => i !== index),
    }));
  };

  const toggleTag = (tag: string) => {
    setForm((f) => ({
      ...f,
      tags: f.tags.includes(tag)
        ? f.tags.filter((t) => t !== tag)
        : [...f.tags, tag],
    }));
  };

  const requiredOk =
    form.title.trim() &&
    form.province &&
    form.district &&
    form.pricePerYear.trim();

  const handleSave = async () => {
    if (!requiredOk) {
      setSnack({
        open: true,
        msg: "กรอกข้อมูลที่จำเป็นให้ครบก่อนบันทึก",
        ok: false,
      });
      return;
    }
    setSaving(true);
    // mock: เก็บไว้ที่ localStorage “my_owned_listings”
    const payload = {
      id: `m-${Date.now()}`,
      title: form.title.trim(),
      province: form.province,
      district: form.district,
      postedAt: new Date().toISOString().slice(0, 10),
      price: Number(form.pricePerYear.replace(/[^\d]/g, "")) || 0,
      unit: "ปี" as const,
      status: "available" as const,
      description:
        form.description.trim() ||
        `${form.areaSize ? `ขนาดพื้นที่: ${form.areaSize}. ` : ""}มัดจำ ${
          form.depositPercent || "10"
        }%`,
      image: form.images[0] || undefined, // รูปแรกเป็นรูปหลัก
      images: form.images, // เก็บรูปทั้งหมด
      tags: form.tags, // เก็บ tags ที่เลือก
    };

    try {
      const raw = localStorage.getItem("my_owned_listings");
      const arr = raw ? JSON.parse(raw) : [];
      arr.unshift(payload);
      localStorage.setItem("my_owned_listings", JSON.stringify(arr));
      setSnack({ open: true, msg: "บันทึกสำเร็จ", ok: true });
      sessionStorage.removeItem(STORAGE_KEY);
      // ส่งไปหน้ารายการของฉัน
      setTimeout(() => router.push("/my/listings"), 700);
    } catch {
      setSnack({ open: true, msg: "บันทึกไม่สำเร็จ", ok: false });
    } finally {
      setSaving(false);
    }
  };

  return (
    <>
      <Header />

      <Container maxWidth="lg" sx={{ py: { xs: 3, md: 5 } }}>
        <Grid container spacing={3}>
          <Grid size={{ xs: 12 }}>
            <Typography variant="h6" fontWeight={900} sx={{ mb: 2 }}>
              จัดการข้อมูลพื้นที่ให้เช่า
            </Typography>

            {/* อัปโหลดรูป */}
            <Paper
              variant="outlined"
              sx={{ p: 2, borderRadius: 2, display: "grid", gap: 2, mb: 2 }}
            >
              <Typography fontWeight={800}>
                รูปภาพ ({form.images.length}/10)
              </Typography>

              {/* Gallery แสดงรูปที่อัปโหลด */}
              {form.images.length > 0 && (
                <Box
                  sx={{
                    display: "grid",
                    gridTemplateColumns: {
                      xs: "repeat(2, 1fr)",
                      sm: "repeat(3, 1fr)",
                      md: "repeat(4, 1fr)",
                    },
                    gap: 1.5,
                  }}
                >
                  {form.images.map((img, idx) => (
                    <Paper
                      key={idx}
                      variant="outlined"
                      sx={{
                        position: "relative",
                        aspectRatio: "1/1",
                        borderRadius: 2,
                        overflow: "hidden",
                        bgcolor: "rgba(0,0,0,.04)",
                      }}
                    >
                      {/* eslint-disable-next-line @next/next/no-img-element */}
                      <img
                        src={img}
                        alt={`รูปที่ ${idx + 1}`}
                        style={{
                          width: "100%",
                          height: "100%",
                          objectFit: "cover",
                        }}
                      />
                      {/* ปุ่มลบ */}
                      <Tooltip title="ลบรูปนี้">
                        <IconButton
                          size="small"
                          onClick={() => removeImage(idx)}
                          sx={{
                            position: "absolute",
                            top: 4,
                            right: 4,
                            bgcolor: "rgba(0,0,0,.6)",
                            color: "#fff",
                            "&:hover": { bgcolor: "error.main" },
                          }}
                        >
                          <DeleteForeverIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                      {/* badge รูปแรก */}
                      {idx === 0 && (
                        <Chip
                          label="รูปหลัก"
                          size="small"
                          color="primary"
                          sx={{
                            position: "absolute",
                            bottom: 4,
                            left: 4,
                            fontWeight: 700,
                            fontSize: 10,
                          }}
                        />
                      )}
                    </Paper>
                  ))}
                </Box>
              )}

              <Box sx={{ display: "flex", gap: 1, flexWrap: "wrap" }}>
                <Button
                  variant="outlined"
                  startIcon={<UploadIcon />}
                  component="label"
                  disabled={form.images.length >= 10}
                >
                  เลือกรูป JPG/PNG (สูงสุด 10 รูป)
                  <input
                    type="file"
                    accept="image/png,image/jpeg"
                    multiple
                    hidden
                    onChange={handleUpload}
                  />
                </Button>
                {form.images.length > 0 && (
                  <Button
                    color="error"
                    variant="text"
                    startIcon={<DeleteForeverIcon />}
                    onClick={() => setForm((f) => ({ ...f, images: [] }))}
                  >
                    ลบรูปทั้งหมด
                  </Button>
                )}
              </Box>
            </Paper>

            {/* ฟอร์มหลัก */}
            <Paper variant="outlined" sx={{ p: 2, borderRadius: 2 }}>
              <Grid container spacing={2}>
                <Grid size={{ xs: 12 }}>
                  <TextField
                    label="ชื่อพื้นที่"
                    fullWidth
                    value={form.title}
                    onChange={(e) =>
                      setForm((f) => ({ ...f, title: e.target.value }))
                    }
                  />
                </Grid>

                <Grid size={{ xs: 12 }}>
                  <TextField
                    label="รายละเอียดประกาศ"
                    fullWidth
                    multiline
                    rows={4}
                    placeholder="อธิบายรายละเอียดพื้นที่ เช่น ลักษณะที่ตั้ง สิ่งอำนวยความสะดวก เงื่อนไขการเช่า..."
                    value={form.description}
                    onChange={(e) =>
                      setForm((f) => ({ ...f, description: e.target.value }))
                    }
                  />
                </Grid>

                <Grid size={{ xs: 12 }}>
                  <Box sx={{ display: "grid", gap: 1 }}>
                    <Typography fontWeight={800} sx={{ fontSize: 14 }}>
                      ประเภทพื้นที่ (เลือกได้หลายแท็ก)
                    </Typography>
                    <Box sx={{ display: "flex", gap: 1, flexWrap: "wrap" }}>
                      {AVAILABLE_TAGS.map((tag) => (
                        <Chip
                          key={tag}
                          label={tag}
                          icon={<LocalOfferIcon />}
                          onClick={() => toggleTag(tag)}
                          color={
                            form.tags.includes(tag) ? "primary" : "default"
                          }
                          variant={
                            form.tags.includes(tag) ? "filled" : "outlined"
                          }
                          sx={{
                            fontWeight: form.tags.includes(tag) ? 700 : 400,
                            cursor: "pointer",
                          }}
                        />
                      ))}
                    </Box>
                  </Box>
                </Grid>

                <Grid size={{ xs: 12, sm: 6 }}>
                  <FormControl fullWidth>
                    <InputLabel>จังหวัด</InputLabel>
                    <Select
                      label="จังหวัด"
                      value={form.province}
                      onChange={(e) =>
                        setForm((f) => ({
                          ...f,
                          province: String(e.target.value),
                          district: "", // reset อำเภอเมื่อเปลี่ยนจังหวัด
                        }))
                      }
                    >
                      {Object.keys(PROVINCES).map((p) => (
                        <MenuItem key={p} value={p}>
                          {p}
                        </MenuItem>
                      ))}
                    </Select>
                  </FormControl>
                </Grid>

                <Grid size={{ xs: 12, sm: 6 }}>
                  <FormControl fullWidth disabled={!form.province}>
                    <InputLabel>อำเภอ</InputLabel>
                    <Select
                      label="อำเภอ"
                      value={form.district}
                      onChange={(e) =>
                        setForm((f) => ({
                          ...f,
                          district: String(e.target.value),
                        }))
                      }
                    >
                      {districts.map((d) => (
                        <MenuItem key={d} value={d}>
                          {d}
                        </MenuItem>
                      ))}
                    </Select>
                  </FormControl>
                </Grid>

                <Grid size={{ xs: 12, sm: 6 }}>
                  <TextField
                    label="ขนาดพื้นที่ (เช่น 10 ไร่ / 5 งาน)"
                    fullWidth
                    value={form.areaSize}
                    onChange={(e) =>
                      setForm((f) => ({ ...f, areaSize: e.target.value }))
                    }
                  />
                </Grid>

                <Grid size={{ xs: 12, sm: 6 }}>
                  <TextField
                    label="ราคาเช่า/ปี"
                    fullWidth
                    type="number"
                    inputProps={{ min: 0, step: 1000 }}
                    value={form.pricePerYear}
                    onChange={(e) =>
                      setForm((f) => ({ ...f, pricePerYear: e.target.value }))
                    }
                    InputProps={{
                      startAdornment: (
                        <InputAdornment position="start">฿</InputAdornment>
                      ),
                      endAdornment: (
                        <InputAdornment position="end">/ปี</InputAdornment>
                      ),
                    }}
                  />
                </Grid>

                <Grid size={{ xs: 12, sm: 6 }}>
                  <TextField
                    label="% ค่ามัดจำ"
                    fullWidth
                    type="number"
                    inputProps={{ min: 0, max: 100 }}
                    value={form.depositPercent}
                    onChange={(e) =>
                      setForm((f) => ({ ...f, depositPercent: e.target.value }))
                    }
                    InputProps={{
                      endAdornment: (
                        <InputAdornment position="end">%</InputAdornment>
                      ),
                    }}
                  />
                </Grid>

                <Grid size={{ xs: 12 }}>
                  <Divider sx={{ my: 1 }} />
                  <Box
                    sx={{
                      display: "flex",
                      gap: 1,
                      justifyContent: "flex-start",
                    }}
                  >
                    <Button
                      variant="outlined"
                      startIcon={<ArrowBackIcon />}
                      onClick={() => history.back()}
                    >
                      ย้อนกลับ
                    </Button>
                    <Button
                      variant="contained"
                      startIcon={<SaveIcon />}
                      disabled={!requiredOk || saving}
                      onClick={handleSave}
                    >
                      แก้ไขข้อมูล/บันทึก
                    </Button>
                  </Box>
                </Grid>
              </Grid>
            </Paper>
          </Grid>
        </Grid>
      </Container>

      <Snackbar
        open={snack.open}
        autoHideDuration={2200}
        onClose={() => setSnack((s) => ({ ...s, open: false }))}
        message={snack.msg}
        anchorOrigin={{ vertical: "bottom", horizontal: "center" }}
        ContentProps={{
          sx: snack.ok ? { bgcolor: "success.main" } : undefined,
        }}
      />
    </>
  );
}
