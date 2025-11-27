import { NextRequest, NextResponse } from 'next/server';
import { ObjectId } from 'mongodb';
import { connectToDatabase } from '@/lib/db/mongodb';
import { COLLECTIONS, ListingDocument } from '@/lib/db/models';
import { withAuth } from '@/lib/auth/middleware';

// GET /api/listings/user/me - Get current user's listings (protected)
export const GET = withAuth(async (request: NextRequest, { user }) => {
  try {
    const { db } = await connectToDatabase();

    const listings = await db
      .collection<ListingDocument>(COLLECTIONS.LISTINGS)
      .find({ userId: new ObjectId(user.userId) })
      .sort({ postedAt: -1 })
      .toArray();

    const formattedListings = listings.map((listing) => ({
      id: listing._id!.toString(),
      title: listing.title,
      description: listing.description,
      price: listing.price,
      unit: listing.unit,
      province: listing.province,
      district: listing.district,
      location: listing.location,
      area: listing.area,
      image: listing.image,
      images: listing.images,
      tags: listing.tags,
      status: listing.status,
      postedAt: listing.postedAt.toISOString(),
      fromDate: listing.fromDate?.toISOString(),
      toDate: listing.toDate?.toISOString(),
      metadata: listing.metadata,
    }));

    return NextResponse.json(formattedListings);
  } catch (error) {
    console.error('Get user listings error:', error);
    return NextResponse.json(
      { error: 'Failed to fetch user listings' },
      { status: 500 }
    );
  }
});
