import { NextRequest, NextResponse } from 'next/server';
import { ObjectId } from 'mongodb';
import { connectToDatabase } from '@/lib/db/mongodb';
import { COLLECTIONS, ListingDocument, UpdateListingDTO } from '@/lib/db/models';
import { withAuth } from '@/lib/auth/middleware';

// GET /api/listings/:id - Get listing by ID
export async function GET(
  request: NextRequest,
  { params }: { params: { id: string } }
) {
  try {
    const { id } = params;

    if (!ObjectId.isValid(id)) {
      return NextResponse.json(
        { error: 'Invalid listing ID' },
        { status: 400 }
      );
    }

    const { db } = await connectToDatabase();

    const listing = await db
      .collection<ListingDocument>(COLLECTIONS.LISTINGS)
      .findOne({ _id: new ObjectId(id) });

    if (!listing) {
      return NextResponse.json(
        { error: 'Listing not found' },
        { status: 404 }
      );
    }

    return NextResponse.json({
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
    });
  } catch (error) {
    console.error('Get listing error:', error);
    return NextResponse.json(
      { error: 'Failed to fetch listing' },
      { status: 500 }
    );
  }
}

// PATCH /api/listings/:id - Update listing (protected, owner only)
export const PATCH = withAuth(async (request: NextRequest, { params, user }) => {
  try {
    const { id } = params;

    if (!ObjectId.isValid(id)) {
      return NextResponse.json(
        { error: 'Invalid listing ID' },
        { status: 400 }
      );
    }

    const body: UpdateListingDTO = await request.json();
    const { db } = await connectToDatabase();

    // Check ownership
    const existingListing = await db
      .collection<ListingDocument>(COLLECTIONS.LISTINGS)
      .findOne({ _id: new ObjectId(id) });

    if (!existingListing) {
      return NextResponse.json(
        { error: 'Listing not found' },
        { status: 404 }
      );
    }

    if (existingListing.userId.toString() !== user.userId) {
      return NextResponse.json(
        { error: 'Unauthorized - not the owner' },
        { status: 403 }
      );
    }

    // Build update object
    const updateData: any = {
      updatedAt: new Date(),
    };

    if (body.title !== undefined) updateData.title = body.title;
    if (body.description !== undefined) updateData.description = body.description;
    if (body.price !== undefined) updateData.price = body.price;
    if (body.unit !== undefined) updateData.unit = body.unit;
    if (body.province !== undefined) updateData.province = body.province;
    if (body.district !== undefined) updateData.district = body.district;
    if (body.location !== undefined) updateData.location = body.location;
    if (body.area !== undefined) updateData.area = body.area;
    if (body.image !== undefined) updateData.image = body.image;
    if (body.images !== undefined) updateData.images = body.images;
    if (body.tags !== undefined) updateData.tags = body.tags;
    if (body.status !== undefined) updateData.status = body.status;
    if (body.fromDate !== undefined) updateData.fromDate = new Date(body.fromDate);
    if (body.toDate !== undefined) updateData.toDate = new Date(body.toDate);
    if (body.metadata !== undefined) updateData.metadata = body.metadata;

    const result = await db
      .collection<ListingDocument>(COLLECTIONS.LISTINGS)
      .findOneAndUpdate(
        { _id: new ObjectId(id) },
        { $set: updateData },
        { returnDocument: 'after' }
      );

    if (!result) {
      return NextResponse.json(
        { error: 'Failed to update listing' },
        { status: 500 }
      );
    }

    return NextResponse.json({
      id: result._id!.toString(),
      title: result.title,
      description: result.description,
      price: result.price,
      unit: result.unit,
      province: result.province,
      district: result.district,
      location: result.location,
      area: result.area,
      image: result.image,
      images: result.images,
      tags: result.tags,
      status: result.status,
      postedAt: result.postedAt.toISOString(),
      fromDate: result.fromDate?.toISOString(),
      toDate: result.toDate?.toISOString(),
      metadata: result.metadata,
    });
  } catch (error) {
    console.error('Update listing error:', error);
    return NextResponse.json(
      { error: 'Failed to update listing' },
      { status: 500 }
    );
  }
});

// DELETE /api/listings/:id - Delete listing (protected, owner only)
export const DELETE = withAuth(async (request: NextRequest, { params, user }) => {
  try {
    const { id } = params;

    if (!ObjectId.isValid(id)) {
      return NextResponse.json(
        { error: 'Invalid listing ID' },
        { status: 400 }
      );
    }

    const { db } = await connectToDatabase();

    // Check ownership
    const existingListing = await db
      .collection<ListingDocument>(COLLECTIONS.LISTINGS)
      .findOne({ _id: new ObjectId(id) });

    if (!existingListing) {
      return NextResponse.json(
        { error: 'Listing not found' },
        { status: 404 }
      );
    }

    if (existingListing.userId.toString() !== user.userId) {
      return NextResponse.json(
        { error: 'Unauthorized - not the owner' },
        { status: 403 }
      );
    }

    await db
      .collection<ListingDocument>(COLLECTIONS.LISTINGS)
      .deleteOne({ _id: new ObjectId(id) });

    return NextResponse.json({ message: 'Listing deleted successfully' });
  } catch (error) {
    console.error('Delete listing error:', error);
    return NextResponse.json(
      { error: 'Failed to delete listing' },
      { status: 500 }
    );
  }
});
