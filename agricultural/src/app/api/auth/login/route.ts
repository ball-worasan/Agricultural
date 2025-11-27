import { NextRequest, NextResponse } from "next/server";
import { connectToDatabase } from "@/lib/db/mongodb";
import { COLLECTIONS, type UserDocument } from "@/lib/db/models";
import { comparePassword } from "@/lib/auth/password";
import { signToken } from "@/lib/auth/jwt";

export async function POST(req: NextRequest) {
  try {
    const body = await req.json();
    const { identifier, username, password } = body;

    // Accept both 'identifier' (from old code) and 'username'
    const usernameOrEmail = identifier || username;

    if (!usernameOrEmail || !password) {
      return NextResponse.json(
        { message: "Username/email and password are required" },
        { status: 400 }
      );
    }

    const { db } = await connectToDatabase();
    const usersCollection = db.collection<UserDocument>(COLLECTIONS.USERS);

    // Find user by username or email
    const user = await usersCollection.findOne({
      $or: [{ username: usernameOrEmail }, { email: usernameOrEmail }],
    });

    if (!user) {
      return NextResponse.json(
        { message: "Invalid credentials" },
        { status: 401 }
      );
    }

    // Verify password
    const isValidPassword = await comparePassword(password, user.password);

    if (!isValidPassword) {
      return NextResponse.json(
        { message: "Invalid credentials" },
        { status: 401 }
      );
    }

    // Generate token
    const token = signToken({
      userId: user._id!.toString(),
      username: user.username,
      email: user.email,
      role: user.role,
    });

    // Return user without password
    const userResponse = {
      id: user._id!.toString(),
      username: user.username,
      email: user.email,
      fullName: user.fullName,
      address: user.address,
      contactPhone: user.contactPhone,
      role: user.role,
    };

    return NextResponse.json({
      message: "Login successful",
      token,
      user: userResponse,
    });
  } catch (error) {
    console.error("Login error:", error);
    return NextResponse.json(
      { message: "Internal server error", error: (error as Error).message },
      { status: 500 }
    );
  }
}
