import { NextRequest, NextResponse } from 'next/server';
import { ObjectId } from 'mongodb';
import { connectToDatabase } from '@/lib/db/mongodb';
import { COLLECTIONS, ReservationDocument, UpdateReservationDTO } from '@/lib/db/models';
import { withAuth } from '@/lib/auth/middleware';

// GET /api/reservations/:id - Get reservation by ID (protected)
export const GET = withAuth(async (request: NextRequest, { params, user }) => {
  try {
    const { id } = params;

    if (!ObjectId.isValid(id)) {
      return NextResponse.json(
        { error: 'Invalid reservation ID' },
        { status: 400 }
      );
    }

    const { db } = await connectToDatabase();

    const reservation = await db
      .collection<ReservationDocument>(COLLECTIONS.RESERVATIONS)
      .findOne({ _id: new ObjectId(id) });

    if (!reservation) {
      return NextResponse.json(
        { error: 'Reservation not found' },
        { status: 404 }
      );
    }

    // Check if user is owner or listing owner
    const listing = await db
      .collection(COLLECTIONS.LISTINGS)
      .findOne({ _id: reservation.listingId });

    const isOwner = reservation.userId.toString() === user.userId;
    const isListingOwner = listing && listing.userId.toString() === user.userId;

    if (!isOwner && !isListingOwner) {
      return NextResponse.json(
        { error: 'Unauthorized' },
        { status: 403 }
      );
    }

    return NextResponse.json({
      id: reservation._id!.toString(),
      listingId: reservation.listingId.toString(),
      userId: reservation.userId.toString(),
      startDate: reservation.startDate.toISOString(),
      endDate: reservation.endDate.toISOString(),
      totalPrice: reservation.totalPrice,
      status: reservation.status,
      paymentDetails: reservation.paymentDetails,
      metadata: reservation.metadata,
    });
  } catch (error) {
    console.error('Get reservation error:', error);
    return NextResponse.json(
      { error: 'Failed to fetch reservation' },
      { status: 500 }
    );
  }
});

// PATCH /api/reservations/:id - Update reservation (protected, owner only)
export const PATCH = withAuth(async (request: NextRequest, { params, user }) => {
  try {
    const { id } = params;

    if (!ObjectId.isValid(id)) {
      return NextResponse.json(
        { error: 'Invalid reservation ID' },
        { status: 400 }
      );
    }

    const body: UpdateReservationDTO = await request.json();
    const { db } = await connectToDatabase();

    // Check ownership
    const existingReservation = await db
      .collection<ReservationDocument>(COLLECTIONS.RESERVATIONS)
      .findOne({ _id: new ObjectId(id) });

    if (!existingReservation) {
      return NextResponse.json(
        { error: 'Reservation not found' },
        { status: 404 }
      );
    }

    if (existingReservation.userId.toString() !== user.userId) {
      return NextResponse.json(
        { error: 'Unauthorized - not the owner' },
        { status: 403 }
      );
    }

    // Build update object
    const updateData: any = {
      updatedAt: new Date(),
    };

    if (body.startDate !== undefined) updateData.startDate = new Date(body.startDate);
    if (body.endDate !== undefined) updateData.endDate = new Date(body.endDate);
    if (body.totalPrice !== undefined) updateData.totalPrice = body.totalPrice;
    if (body.status !== undefined) updateData.status = body.status;
    if (body.paymentDetails !== undefined) updateData.paymentDetails = body.paymentDetails;
    if (body.metadata !== undefined) updateData.metadata = body.metadata;

    const result = await db
      .collection<ReservationDocument>(COLLECTIONS.RESERVATIONS)
      .findOneAndUpdate(
        { _id: new ObjectId(id) },
        { $set: updateData },
        { returnDocument: 'after' }
      );

    if (!result) {
      return NextResponse.json(
        { error: 'Failed to update reservation' },
        { status: 500 }
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
    console.error('Update reservation error:', error);
    return NextResponse.json(
      { error: 'Failed to update reservation' },
      { status: 500 }
    );
  }
});

// DELETE /api/reservations/:id - Delete reservation (protected, owner only)
export const DELETE = withAuth(async (request: NextRequest, { params, user }) => {
  try {
    const { id } = params;

    if (!ObjectId.isValid(id)) {
      return NextResponse.json(
        { error: 'Invalid reservation ID' },
        { status: 400 }
      );
    }

    const { db } = await connectToDatabase();

    // Check ownership
    const existingReservation = await db
      .collection<ReservationDocument>(COLLECTIONS.RESERVATIONS)
      .findOne({ _id: new ObjectId(id) });

    if (!existingReservation) {
      return NextResponse.json(
        { error: 'Reservation not found' },
        { status: 404 }
      );
    }

    if (existingReservation.userId.toString() !== user.userId) {
      return NextResponse.json(
        { error: 'Unauthorized - not the owner' },
        { status: 403 }
      );
    }

    await db
      .collection<ReservationDocument>(COLLECTIONS.RESERVATIONS)
      .deleteOne({ _id: new ObjectId(id) });

    return NextResponse.json({ message: 'Reservation deleted successfully' });
  } catch (error) {
    console.error('Delete reservation error:', error);
    return NextResponse.json(
      { error: 'Failed to delete reservation' },
      { status: 500 }
    );
  }
});
