import { ObjectId } from "mongodb";

// User Model
export interface UserDocument {
  _id?: ObjectId;
  username: string;
  email: string;
  password: string; // hashed
  firstName?: string;
  lastName?: string;
  address?: string;
  contactPhone?: string;
  avatarUrl?: string;
  role?: "user" | "admin";
  createdAt: Date;
  updatedAt: Date;
}

// Listing Model
export interface ListingDocument {
  _id?: ObjectId;
  title: string;
  description?: string;
  price: number;
  unit: "วัน" | "เดือน" | "ปี";
  province: string;
  district: string;
  location?: string;
  area?: number;
  image?: string;
  images?: string[];
  tags?: string[];
  status: "available" | "reserved";
  userId: ObjectId; // owner
  postedAt: Date;
  fromDate?: Date;
  toDate?: Date;
  metadata?: Record<string, any>;
  createdAt: Date;
  updatedAt: Date;
}

// Reservation Model
export interface ReservationDocument {
  _id?: ObjectId;
  listingId: ObjectId;
  userId: ObjectId; // renter
  startDate: Date;
  endDate: Date;
  totalPrice: number;
  status: "pending" | "confirmed" | "cancelled" | "completed";
  paymentDetails?: Record<string, any>;
  metadata?: Record<string, any>;
  createdAt: Date;
  updatedAt: Date;
}

// Collection names
export const COLLECTIONS = {
  USERS: "users",
  LISTINGS: "listings",
  RESERVATIONS: "reservations",
} as const;

// DTOs for API
export interface CreateListingDTO {
  title: string;
  description?: string;
  price: number;
  unit: "วัน" | "เดือน" | "ปี";
  province: string;
  district: string;
  location?: string;
  area?: number;
  image?: string;
  images?: string[];
  tags?: string[];
  fromDate?: string;
  toDate?: string;
  metadata?: Record<string, any>;
}

export interface UpdateListingDTO {
  title?: string;
  description?: string;
  price?: number;
  unit?: "วัน" | "เดือน" | "ปี";
  province?: string;
  district?: string;
  location?: string;
  area?: number;
  image?: string;
  images?: string[];
  tags?: string[];
  status?: "available" | "reserved";
  fromDate?: string;
  toDate?: string;
  metadata?: Record<string, any>;
}

export interface CreateReservationDTO {
  listingId: string;
  startDate: string;
  endDate: string;
  totalPrice: number;
  paymentDetails?: Record<string, any>;
  metadata?: Record<string, any>;
}

export interface UpdateReservationDTO {
  startDate?: string;
  endDate?: string;
  totalPrice?: number;
  status?: "pending" | "confirmed" | "cancelled" | "completed";
  paymentDetails?: Record<string, any>;
  metadata?: Record<string, any>;
}
