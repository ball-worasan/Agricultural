// Centralized type definitions

export type Unit = "ปี" | "เดือน" | "วัน";
export type Status = "available" | "reserved";

export interface Listing {
  id: string;
  title: string;
  province: string;
  district: string;
  postedAt: string;
  price: number;
  unit: Unit;
  status: Status;
  image?: string;
  tags?: string[];
  description?: string;
  fromDate?: string;
  toDate?: string;
}

export interface User {
  id?: string;
  username?: string;
  email?: string;
  fullName?: string;
  avatarUrl?: string;
  roles?: string[];
}

export interface AuthResponse {
  token: string;
  user: User;
}

export interface LoginDTO {
  username: string;
  password: string;
}

export interface RegisterDTO {
  username: string;
  email: string;
  password: string;
  fullName?: string;
  address?: string;
  contactPhone?: string;
}

export enum ReservationStatus {
  PENDING = "pending",
  CONFIRMED = "confirmed",
  CANCELLED = "cancelled",
  COMPLETED = "completed",
}

export interface Reservation {
  id: string;
  listing: string;
  user: string;
  startDate: string;
  endDate: string;
  totalPrice: number;
  status: ReservationStatus;
  paymentDetails?: Record<string, unknown>;
  metadata?: Record<string, unknown>;
  createdAt: string;
  updatedAt: string;
}
