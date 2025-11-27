// API Error Messages and Status Codes

export const API_ERRORS = {
  // 400 Bad Request
  MISSING_REQUIRED_FIELDS: {
    message: "ข้อมูลที่จำเป็นไม่ครบถ้วน",
    status: 400,
  },
  MISSING_AUTH_FIELDS: {
    message: "ชื่อผู้ใช้งาน, อีเมล, และรหัสผ่านเป็นข้อมูลที่จำเป็น",
    status: 400,
  },
  INVALID_LISTING_ID: {
    message: "รหัสประกาศไม่ถูกต้อง",
    status: 400,
  },
  INVALID_RESERVATION_ID: {
    message: "รหัสการจองไม่ถูกต้อง",
    status: 400,
  },
  INVALID_STATUS_VALUE: {
    message: "ค่าสถานะไม่ถูกต้อง",
    status: 400,
  },
  LISTING_NOT_AVAILABLE: {
    message: "ประกาศนี้ไม่สามารถจองได้",
    status: 400,
  },
  PASSWORDS_NOT_MATCH: {
    message: "รหัสผ่านไม่ตรงกัน",
    status: 400,
  },

  // 401 Unauthorized
  UNAUTHORIZED: {
    message: "ไม่มีสิทธิ์เข้าถึง",
    status: 401,
  },
  INVALID_CREDENTIALS: {
    message: "ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง",
    status: 401,
  },
  INCORRECT_PASSWORD: {
    message: "รหัสผ่านปัจจุบันไม่ถูกต้อง",
    status: 401,
  },
  MISSING_AUTH_HEADER: {
    message: "ไม่พบข้อมูลการยืนยันตัวตน",
    status: 401,
  },
  INVALID_TOKEN: {
    message: "โทเค็นไม่ถูกต้องหรือหมดอายุ",
    status: 401,
  },

  // 403 Forbidden
  FORBIDDEN: {
    message: "คุณไม่มีสิทธิ์ดำเนินการนี้",
    status: 403,
  },
  NOT_OWNER: {
    message: "คุณไม่ใช่เจ้าของรายการนี้",
    status: 403,
  },
  NOT_LISTING_OWNER: {
    message: "คุณไม่ใช่เจ้าของประกาศนี้",
    status: 403,
  },

  // 404 Not Found
  USER_NOT_FOUND: {
    message: "ไม่พบผู้ใช้งาน",
    status: 404,
  },
  LISTING_NOT_FOUND: {
    message: "ไม่พบประกาศ",
    status: 404,
  },
  RESERVATION_NOT_FOUND: {
    message: "ไม่พบการจอง",
    status: 404,
  },

  // 409 Conflict
  USER_EXISTS: {
    message: "ชื่อผู้ใช้งานหรืออีเมลนี้มีอยู่แล้ว",
    status: 409,
  },

  // 500 Internal Server Error
  INTERNAL_SERVER_ERROR: {
    message: "เกิดข้อผิดพลาดภายในเซิร์ฟเวอร์",
    status: 500,
  },
  REGISTER_FAILED: {
    message: "การลงทะเบียนล้มเหลว",
    status: 500,
  },
  LOGIN_FAILED: {
    message: "การเข้าสู่ระบบล้มเหลว",
    status: 500,
  },
  UPDATE_FAILED: {
    message: "การอัพเดทข้อมูลล้มเหลว",
    status: 500,
  },
  CREATE_FAILED: {
    message: "การสร้างข้อมูลล้มเหลว",
    status: 500,
  },
  DELETE_FAILED: {
    message: "การลบข้อมูลล้มเหลว",
    status: 500,
  },
  FETCH_FAILED: {
    message: "การดึงข้อมูลล้มเหลว",
    status: 500,
  },
  UPLOAD_FAILED: {
    message: "การอัพโหลดไฟล์ล้มเหลว",
    status: 500,
  },

  // 503 Service Unavailable
  SERVICE_UNAVAILABLE: {
    message: "บริการไม่พร้อมใช้งานในขณะนี้",
    status: 503,
  },
  DATABASE_ERROR: {
    message: "ไม่สามารถเชื่อมต่อกับฐานข้อมูล",
    status: 503,
  },
} as const;

// Helper function to create error response
export function createErrorResponse(errorKey: keyof typeof API_ERRORS, customMessage?: string) {
  const error = API_ERRORS[errorKey];
  return {
    message: customMessage || error.message,
    status: error.status,
  };
}

// Type for API error keys
export type ApiErrorKey = keyof typeof API_ERRORS;
