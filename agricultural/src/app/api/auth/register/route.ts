import { NextRequest, NextResponse } from "next/server";
import { connectToDatabase } from "@/lib/db/mongodb";
import { COLLECTIONS, type UserDocument } from "@/lib/db/models";
import { hashPassword } from "@/lib/auth/password";
import { signToken } from "@/lib/auth/jwt";
import { API_ERRORS } from "@/lib/constants/api-errors";

// ทดสอบสถานะการทำงานของ API
export async function GET() {
  try {
    const { db } = await connectToDatabase();

    // ทดสอบการเชื่อมต่อฐานข้อมูล
    await db.admin().ping();

    return NextResponse.json({
      status: "healthy",
      message: "Register API is running",
      timestamp: new Date().toISOString(),
      database: "connected",
    });
  } catch (error) {
    return NextResponse.json(
      {
        status: "unhealthy",
        message: API_ERRORS.SERVICE_UNAVAILABLE.message,
        error: (error as Error).message,
      },
      { status: API_ERRORS.SERVICE_UNAVAILABLE.status }
    );
  }
}

// ลงทะเบียนผู้ใช้ใหม่
export async function POST(req: NextRequest) {
  try {
    const body = await req.json();
    const {
      username,
      email,
      password,
      firstName,
      lastName,
      address,
      contactPhone,
    } = body;

    // ตรวจสอบความถูกต้องของข้อมูล
    if (!username || !email || !password) {
      return NextResponse.json(
        { message: API_ERRORS.MISSING_AUTH_FIELDS.message },
        { status: API_ERRORS.MISSING_AUTH_FIELDS.status }
      );
    }

    const { db } = await connectToDatabase();
    const usersCollection = db.collection<UserDocument>(COLLECTIONS.USERS);

    // ตรวจสอบว่าผู้ใช้มีอยู่แล้วหรือไม่
    const existingUser = await usersCollection.findOne({
      $or: [{ username }, { email }],
    });

    // หากมีผู้ใช้แล้ว ให้ส่งกลับข้อผิดพลาด
    if (existingUser) {
      return NextResponse.json(
        { message: API_ERRORS.USER_EXISTS.message },
        { status: API_ERRORS.USER_EXISTS.status }
      );
    }

    // แฮชรหัสผ่าน
    const hashedPassword = await hashPassword(password);

    // สร้างผู้ใช้ใหม่
    const newUser: UserDocument = {
      username,
      email,
      password: hashedPassword,
      firstName,
      lastName,
      address,
      contactPhone,
      role: "user",
      createdAt: new Date(),
      updatedAt: new Date(),
    };

    const result = await usersCollection.insertOne(newUser);

    // สร้างโทเค็น
    const token = signToken({
      userId: result.insertedId.toString(),
      username,
      email,
      role: "user",
    });

    // เตรียมข้อมูลผู้ใช้สำหรับการตอบกลับ
    const userResponse = {
      id: result.insertedId.toString(),
      username,
      email,
      firstName: newUser.firstName,
      lastName: newUser.lastName,
      address: newUser.address,
      contactPhone: newUser.contactPhone,
      role: "user",
    };

    return NextResponse.json(
      {
        message: "ลงทะเบียนสำเร็จ",
        token,
        user: userResponse,
      },
      { status: 201 }
    );
  } catch (error) {
    console.error("ข้อผิดพลาดในการลงทะเบียน:", error);
    return NextResponse.json(
      {
        message: API_ERRORS.INTERNAL_SERVER_ERROR.message,
        error: (error as Error).message,
      },
      { status: API_ERRORS.INTERNAL_SERVER_ERROR.status }
    );
  }
}
