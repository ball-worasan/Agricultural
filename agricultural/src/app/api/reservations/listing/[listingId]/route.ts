import { NextRequest, NextResponse } from 'next/server';
import { ObjectId } from 'mongodb';
import { connectToDatabase } from '@/lib/db/mongodb';
import { COLLECTIONS, ReservationDocument } from '@/lib/db/models';
import { withAuth } from '@/lib/auth/middleware';

// GET /api/reservations/listing/:listingId - Get reservations for a specific listing (protected)
export const GET = withAuth(async (request: NextRequest, { params, user }) => {
  try {
    const { listingId } = params;

    if (!ObjectId.isValid(listingId)) {
      return NextResponse.json(
        { error: 'Invalid listing ID' },
        { status: 400 }
      );
    }

    const { db } = await connectToDatabase();

    // Check if listing exists and user is the owner
    const listing = await db
      .collection(COLLECTIONS.LISTINGS)
      .findOne({ _id: new ObjectId(listingId) });

    if (!listing) {
      return NextResponse.json(
        { error: 'Listing not found' },
        { status: 404 }
      );
    }

    if (listing.userId.toString() !== user.userId) {
      return NextResponse.json(
        { error: 'Unauthorized - not the listing owner' },
        { status: 403 }
      );
    }

    const reservations = await db
      .collection<ReservationDocument>(COLLECTIONS.RESERVATIONS)
      .find({ listingId: new ObjectId(listingId) })
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
    console.error('Get listing reservations error:', error);
    return NextResponse.json(
      { error: 'Failed to fetch listing reservations' },
      { status: 500 }
    );
  }
});
