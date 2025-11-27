import { NextRequest, NextResponse } from 'next/server';
import { ObjectId } from 'mongodb';
import { connectToDatabase } from '@/lib/db/mongodb';
import { COLLECTIONS, ReservationDocument, CreateReservationDTO } from '@/lib/db/models';
import { withAuth } from '@/lib/auth/middleware';

// GET /api/reservations - Get all reservations (protected)
export const GET = withAuth(async (request: NextRequest, { user }) => {
  try {
    const { db } = await connectToDatabase();

    const reservations = await db
      .collection<ReservationDocument>(COLLECTIONS.RESERVATIONS)
      .find({})
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
    console.error('Get reservations error:', error);
    return NextResponse.json(
      { error: 'Failed to fetch reservations' },
      { status: 500 }
    );
  }
});

// POST /api/reservations - Create new reservation (protected)
export const POST = withAuth(async (request: NextRequest, { user }) => {
  try {
    const body: CreateReservationDTO = await request.json();

    // Validate required fields
    if (!body.listingId || !body.startDate || !body.endDate || body.totalPrice === undefined) {
      return NextResponse.json(
        { error: 'Missing required fields' },
        { status: 400 }
      );
    }

    if (!ObjectId.isValid(body.listingId)) {
      return NextResponse.json(
        { error: 'Invalid listing ID' },
        { status: 400 }
      );
    }

    const { db } = await connectToDatabase();

    // Check if listing exists and is available
    const listing = await db
      .collection(COLLECTIONS.LISTINGS)
      .findOne({ _id: new ObjectId(body.listingId) });

    if (!listing) {
      return NextResponse.json(
        { error: 'Listing not found' },
        { status: 404 }
      );
    }

    if (listing.status !== 'available') {
      return NextResponse.json(
        { error: 'Listing is not available' },
        { status: 400 }
      );
    }

    const newReservation: Omit<ReservationDocument, '_id'> = {
      listingId: new ObjectId(body.listingId),
      userId: new ObjectId(user.userId),
      startDate: new Date(body.startDate),
      endDate: new Date(body.endDate),
      totalPrice: body.totalPrice,
      status: 'pending',
      paymentDetails: body.paymentDetails,
      metadata: body.metadata,
      createdAt: new Date(),
      updatedAt: new Date(),
    };

    const result = await db
      .collection<ReservationDocument>(COLLECTIONS.RESERVATIONS)
      .insertOne(newReservation as ReservationDocument);

    const reservation = await db
      .collection<ReservationDocument>(COLLECTIONS.RESERVATIONS)
      .findOne({ _id: result.insertedId });

    if (!reservation) {
      return NextResponse.json(
        { error: 'Failed to create reservation' },
        { status: 500 }
      );
    }

    return NextResponse.json(
      {
        id: reservation._id!.toString(),
        listingId: reservation.listingId.toString(),
        userId: reservation.userId.toString(),
        startDate: reservation.startDate.toISOString(),
        endDate: reservation.endDate.toISOString(),
        totalPrice: reservation.totalPrice,
        status: reservation.status,
        paymentDetails: reservation.paymentDetails,
        metadata: reservation.metadata,
      },
      { status: 201 }
    );
  } catch (error) {
    console.error('Create reservation error:', error);
    return NextResponse.json(
      { error: 'Failed to create reservation' },
      { status: 500 }
    );
  }
});
