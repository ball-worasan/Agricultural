import { NextRequest, NextResponse } from 'next/server';
import { ObjectId } from 'mongodb';
import { connectToDatabase } from '@/lib/db/mongodb';
import { COLLECTIONS, ReservationDocument } from '@/lib/db/models';
import { withAuth } from '@/lib/auth/middleware';

// PATCH /api/reservations/:id/status - Update reservation status (protected, listing owner can also update)
export const PATCH = withAuth(async (request: NextRequest, { params, user }) => {
  try {
    const { id } = params;

    if (!ObjectId.isValid(id)) {
      return NextResponse.json(
        { error: 'Invalid reservation ID' },
        { status: 400 }
      );
    }

    const body = await request.json();
    const { status } = body;

    if (!status || !['pending', 'confirmed', 'cancelled', 'completed'].includes(status)) {
      return NextResponse.json(
        { error: 'Invalid status value' },
        { status: 400 }
      );
    }

    const { db } = await connectToDatabase();

    // Get reservation and listing
    const reservation = await db
      .collection<ReservationDocument>(COLLECTIONS.RESERVATIONS)
      .findOne({ _id: new ObjectId(id) });

    if (!reservation) {
      return NextResponse.json(
        { error: 'Reservation not found' },
        { status: 404 }
      );
    }

    const listing = await db
      .collection(COLLECTIONS.LISTINGS)
      .findOne({ _id: reservation.listingId });

    // Check authorization: reservation owner or listing owner can update status
    const isReservationOwner = reservation.userId.toString() === user.userId;
    const isListingOwner = listing && listing.userId.toString() === user.userId;

    if (!isReservationOwner && !isListingOwner) {
      return NextResponse.json(
        { error: 'Unauthorized' },
        { status: 403 }
      );
    }

    const result = await db
      .collection<ReservationDocument>(COLLECTIONS.RESERVATIONS)
      .findOneAndUpdate(
        { _id: new ObjectId(id) },
        { 
          $set: { 
            status,
            updatedAt: new Date(),
          } 
        },
        { returnDocument: 'after' }
      );

    if (!result) {
      return NextResponse.json(
        { error: 'Failed to update status' },
        { status: 500 }
      );
    }

    // If confirmed, update listing status to reserved
    if (status === 'confirmed' && listing) {
      await db
        .collection(COLLECTIONS.LISTINGS)
        .updateOne(
          { _id: listing._id },
          { $set: { status: 'reserved' } }
        );
    }

    // If cancelled, update listing status back to available
    if (status === 'cancelled' && listing) {
      await db
        .collection(COLLECTIONS.LISTINGS)
        .updateOne(
          { _id: listing._id },
          { $set: { status: 'available' } }
        );
    }

    return NextResponse.json({
      id: result._id!.toString(),
      listingId: result.listingId.toString(),
      userId: result.userId.toString(),
      startDate: result.startDate.toISOString(),
      endDate: result.endDate.toISOString(),
      totalPrice: result.totalPrice,
      status: result.status,
      paymentDetails: result.paymentDetails,
      metadata: result.metadata,
    });
  } catch (error) {
    console.error('Update reservation status error:', error);
    return NextResponse.json(
      { error: 'Failed to update status' },
      { status: 500 }
    );
  }
});
