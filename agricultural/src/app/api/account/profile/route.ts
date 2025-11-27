import { NextRequest, NextResponse } from "next/server";
import { connectToDatabase } from "@/lib/db/mongodb";
import { COLLECTIONS, type UserDocument } from "@/lib/db/models";
import { withAuth, AuthenticatedRequest } from "@/lib/auth/middleware";
import { ObjectId } from "mongodb";

async function getProfile(req: AuthenticatedRequest) {
  try {
    const userId = req.user!.userId;

    const { db } = await connectToDatabase();
    const usersCollection = db.collection<UserDocument>(COLLECTIONS.USERS);

    const user = await usersCollection.findOne({ _id: new ObjectId(userId) });

    if (!user) {
      return NextResponse.json({ message: "User not found" }, { status: 404 });
    }

    const userResponse = {
      id: user._id!.toString(),
      username: user.username,
      email: user.email,
      fullName: user.fullName,
      address: user.address,
      contactPhone: user.contactPhone,
      avatarUrl: user.avatarUrl,
      role: user.role,
    };

    return NextResponse.json({ user: userResponse });
  } catch (error) {
    console.error("Get profile error:", error);
    return NextResponse.json(
      { message: "Internal server error", error: (error as Error).message },
      { status: 500 }
    );
  }
}

async function updateProfile(req: AuthenticatedRequest) {
  try {
    const userId = req.user!.userId;
    const body = await req.json();
    const { fullName, address, phone } = body;

    const { db } = await connectToDatabase();
    const usersCollection = db.collection<UserDocument>(COLLECTIONS.USERS);

    const updateData: Partial<UserDocument> = {
      updatedAt: new Date(),
    };

    if (fullName !== undefined) updateData.fullName = fullName;
    if (address !== undefined) updateData.address = address;
    if (phone !== undefined) updateData.contactPhone = phone;

    const result = await usersCollection.findOneAndUpdate(
      { _id: new ObjectId(userId) },
      { $set: updateData },
      { returnDocument: "after" }
    );

    if (!result) {
      return NextResponse.json({ message: "User not found" }, { status: 404 });
    }

    const userResponse = {
      id: result._id!.toString(),
      username: result.username,
      email: result.email,
      fullName: result.fullName,
      address: result.address,
      contactPhone: result.contactPhone,
      avatarUrl: result.avatarUrl,
      role: result.role,
    };

    return NextResponse.json({
      message: "Profile updated successfully",
      user: userResponse,
    });
  } catch (error) {
    console.error("Update profile error:", error);
    return NextResponse.json(
      { message: "Internal server error", error: (error as Error).message },
      { status: 500 }
    );
  }
}

export const GET = withAuth(getProfile);
export const PATCH = withAuth(updateProfile);
