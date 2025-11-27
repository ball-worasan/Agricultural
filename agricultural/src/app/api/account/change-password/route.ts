import { NextRequest, NextResponse } from "next/server";
import { connectToDatabase } from "@/lib/db/mongodb";
import { COLLECTIONS, type UserDocument } from "@/lib/db/models";
import { withAuth, AuthenticatedRequest } from "@/lib/auth/middleware";
import { hashPassword, comparePassword } from "@/lib/auth/password";
import { ObjectId } from "mongodb";

async function changePassword(req: AuthenticatedRequest) {
  try {
    const userId = req.user!.userId;
    const body = await req.json();
    const { password, confirm, oldPassword } = body;

    if (!password || password !== confirm) {
      return NextResponse.json(
        { message: "Passwords do not match" },
        { status: 400 }
      );
    }

    const { db } = await connectToDatabase();
    const usersCollection = db.collection<UserDocument>(COLLECTIONS.USERS);

    const user = await usersCollection.findOne({ _id: new ObjectId(userId) });

    if (!user) {
      return NextResponse.json({ message: "User not found" }, { status: 404 });
    }

    // If oldPassword is provided, verify it
    if (oldPassword) {
      const isValid = await comparePassword(oldPassword, user.password);
      if (!isValid) {
        return NextResponse.json(
          { message: "Current password is incorrect" },
          { status: 401 }
        );
      }
    }

    // Hash new password
    const hashedPassword = await hashPassword(password);

    await usersCollection.updateOne(
      { _id: new ObjectId(userId) },
      {
        $set: {
          password: hashedPassword,
          updatedAt: new Date(),
        },
      }
    );

    return NextResponse.json({
      message: "Password changed successfully",
    });
  } catch (error) {
    console.error("Change password error:", error);
    return NextResponse.json(
      { message: "Internal server error", error: (error as Error).message },
      { status: 500 }
    );
  }
}

export const POST = withAuth(changePassword);
