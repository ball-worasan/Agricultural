// Validation functions for authentication

export interface ValidationError {
  [key: string]: string;
}

export function validateRegisterForm(data: {
  firstName: string;
  lastName: string;
  email: string;
  address?: string;
  phone: string;
  username: string;
  password: string;
  confirm: string;
}): ValidationError {
  const errors: ValidationError = {};

  // Validate ชื่อ
  if (data.firstName.length < 2) {
    errors.firstName = "กรุณากรอกชื่ออย่างน้อย 2 ตัวอักษร";
  }

  // Validate นามสกุล
  if (data.lastName.length < 2) {
    errors.lastName = "กรุณากรอกนามสกุลอย่างน้อย 2 ตัวอักษร";
  }

  // Validate email
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) {
    errors.email = "รูปแบบอีเมลไม่ถูกต้อง";
  }

  // Validate ที่อยู่ (optional แต่ถ้ากรอกต้องมีอย่างน้อย 10 ตัวอักษร)
  if (data.address && data.address.length < 10) {
    errors.address = "กรุณากรอกที่อยู่อย่างน้อย 10 ตัวอักษร";
  }

  // Validate เบอร์โทร (ต้องเป็นตัวเลข 10 หลักเท่านั้น)
  if (!/^0[0-9]{9}$/.test(data.phone)) {
    errors.phone = "กรุณากรอกเบอร์โทรศัพท์ 10 หลัก (ขึ้นต้นด้วย 0)";
  }

  // Validate username
  if (data.username.length < 3) {
    errors.username = "ชื่อผู้ใช้งานต้องมีอย่างน้อย 3 ตัวอักษร";
  } else if (!/^[a-zA-Z0-9_]+$/.test(data.username)) {
    errors.username = "ชื่อผู้ใช้งานใช้ได้เฉพาะ a-z, 0-9 และ _";
  }

  // Validate password
  if (data.password.length < 6) {
    errors.password = "รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร";
  }

  // Validate confirm password
  if (data.password !== data.confirm) {
    errors.confirm = "รหัสผ่านไม่ตรงกัน";
  }

  return errors;
}

export function validateLoginForm(data: {
  username: string;
  password: string;
}): ValidationError {
  const errors: ValidationError = {};

  if (!data.username || data.username.trim().length === 0) {
    errors.username = "กรุณากรอกชื่อผู้ใช้งาน";
  }

  if (!data.password || data.password.length === 0) {
    errors.password = "กรุณากรอกรหัสผ่าน";
  }

  return errors;
}

export function validateEmail(email: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

export function validatePhone(phone: string): boolean {
  return /^0[0-9]{9}$/.test(phone);
}

export function validateUsername(username: string): boolean {
  return username.length >= 3 && /^[a-zA-Z0-9_]+$/.test(username);
}

export function validatePassword(password: string): boolean {
  return password.length >= 6;
}
