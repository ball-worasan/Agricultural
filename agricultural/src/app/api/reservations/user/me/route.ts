import { NextRequest, NextResponse } from 'next/server';
import { ObjectId } from 'mongodb';
import { connectToDatabase } from '@/lib/db/mongodb';
import { COLLECTIONS, ReservationDocument } from '@/lib/db/models';
import { withAuth } from '@/lib/auth/middleware';

// GET /api/reservations/user/me - Get current user's reservations (protected)
export const GET = withAuth(async (request: NextRequest, { user }) => {
  try {
    const { db } = await connectToDatabase();

    const reservations = await db
      .collection<ReservationDocument>(COLLECTIONS.RESERVATIONS)
      .find({ userId: new ObjectId(user.userId) })
      .sort({ createdAt: -1 })
      .toArray();

    const formattedReservations = reservations.map((reservation) => ({
      id: reservation._id!.toString(),
      listingId: reservation.listingId.toString(),
      userId: reservation.userId.toString(),
      startDate: reservation.startDate.toISOString(),
      endDate: reservation.endDate.toISOString(),
      totalPrice: reservation.totalPrice,
      status: reservation.status,
      paymentDetails: reservation.paymentDetails,
      metadata: reservation.metadata,
    }));

    return NextResponse.json(formattedReservations);
  } catch (error) {
    console.error('Get user reservations error:', error);
    return NextResponse.json(
      { error: 'Failed to fetch user reservations' },
      { status: 500 }
    );
  }
});
