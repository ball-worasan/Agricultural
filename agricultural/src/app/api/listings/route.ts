import { NextRequest, NextResponse } from 'next/server';
import { ObjectId } from 'mongodb';
import { connectToDatabase } from '@/lib/db/mongodb';
import { COLLECTIONS, ListingDocument, CreateListingDTO } from '@/lib/db/models';
import { withAuth } from '@/lib/auth/middleware';

// GET /api/listings - Get all listings with optional filters
export async function GET(request: NextRequest) {
  try {
    const { db } = await connectToDatabase();
    const searchParams = request.nextUrl.searchParams;

    // Build filter query
    const filter: any = {};

    // Province filter
    const province = searchParams.get('province');
    if (province) {
      filter.province = province;
    }

    // Price range filter
    const priceMin = searchParams.get('priceMin');
    const priceMax = searchParams.get('priceMax');
    if (priceMin || priceMax) {
      filter.price = {};
      if (priceMin) filter.price.$gte = Number(priceMin);
      if (priceMax) filter.price.$lte = Number(priceMax);
    }

    // Tags filter (support multiple tags)
    const tags = searchParams.get('tags');
    if (tags) {
      const tagArray = tags.split(',').map((t) => t.trim());
      filter.tags = { $in: tagArray };
    }

    // Search filter (title, description, district)
    const search = searchParams.get('search');
    if (search) {
      filter.$or = [
        { title: { $regex: search, $options: 'i' } },
        { description: { $regex: search, $options: 'i' } },
        { district: { $regex: search, $options: 'i' } },
      ];
    }

    // Status filter
    const status = searchParams.get('status');
    if (status === 'available' || status === 'reserved') {
      filter.status = status;
    }

    const listings = await db
      .collection<ListingDocument>(COLLECTIONS.LISTINGS)
      .find(filter)
      .sort({ postedAt: -1 })
      .toArray();

    // Convert to frontend format
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
    console.error('Get listings error:', error);
    return NextResponse.json(
      { error: 'Failed to fetch listings' },
      { status: 500 }
    );
  }
}

// POST /api/listings - Create new listing (protected)
export const POST = withAuth(async (request: NextRequest, { user }) => {
  try {
    const body: CreateListingDTO = await request.json();

    // Validate required fields
    if (!body.title || !body.price || !body.unit || !body.province || !body.district) {
      return NextResponse.json(
        { error: 'Missing required fields' },
        { status: 400 }
      );
    }

    const { db } = await connectToDatabase();

    const newListing: Omit<ListingDocument, '_id'> = {
      title: body.title,
      description: body.description,
      price: body.price,
      unit: body.unit,
      province: body.province,
      district: body.district,
      location: body.location,
      area: body.area,
      image: body.image,
      images: body.images,
      tags: body.tags || [],
      status: 'available',
      userId: new ObjectId(user.userId),
      postedAt: new Date(),
      fromDate: body.fromDate ? new Date(body.fromDate) : undefined,
      toDate: body.toDate ? new Date(body.toDate) : undefined,
      metadata: body.metadata,
      createdAt: new Date(),
      updatedAt: new Date(),
    };

    const result = await db
      .collection<ListingDocument>(COLLECTIONS.LISTINGS)
      .insertOne(newListing as ListingDocument);

    const listing = await db
      .collection<ListingDocument>(COLLECTIONS.LISTINGS)
      .findOne({ _id: result.insertedId });

    if (!listing) {
      return NextResponse.json(
        { error: 'Failed to create listing' },
        { status: 500 }
      );
    }

    return NextResponse.json(
      {
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
      },
      { status: 201 }
    );
  } catch (error) {
    console.error('Create listing error:', error);
    return NextResponse.json(
      { error: 'Failed to create listing' },
      { status: 500 }
    );
  }
});
