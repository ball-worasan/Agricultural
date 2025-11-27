import { NextRequest, NextResponse } from 'next/server';
import { connectToDatabase } from '@/lib/db/mongodb';
import { COLLECTIONS, type UserDocument } from '@/lib/db/models';
import { withAuth, AuthenticatedRequest } from '@/lib/auth/middleware';
import { ObjectId } from 'mongodb';

async function handler(req: AuthenticatedRequest) {
  try {
    const userId = req.user!.userId;

    const { db } = await connectToDatabase();
    const usersCollection = db.collection<UserDocument>(COLLECTIONS.USERS);

    const user = await usersCollection.findOne({ _id: new ObjectId(userId) });

    if (!user) {
      return NextResponse.json({ message: 'User not found' }, { status: 404 });
    }

    // Return user without password
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

    return NextResponse.json(userResponse);
  } catch (error) {
    console.error('Get profile error:', error);
    return NextResponse.json(
      { message: 'Internal server error', error: (error as Error).message },
      { status: 500 }
    );
  }
}

export const GET = withAuth(handler);
