import { api } from './api';

export enum ReservationStatus {
  PENDING = 'pending',
  CONFIRMED = 'confirmed',
  CANCELLED = 'cancelled',
  COMPLETED = 'completed',
}

export interface Reservation {
  id: string;
  listing: string;
  user: string;
  startDate: string;
  endDate: string;
  totalPrice: number;
  status: ReservationStatus;
  paymentDetails?: Record<string, any>;
  metadata?: Record<string, any>;
  createdAt: string;
  updatedAt: string;
}

export interface CreateReservationDTO {
  listing: string;
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
  status?: ReservationStatus;
  paymentDetails?: Record<string, any>;
  metadata?: Record<string, any>;
}

export const reservationService = {
  async getAll(): Promise<Reservation[]> {
    return api.get('/reservations');
  },

  async getById(id: string): Promise<Reservation> {
    return api.get(`/reservations/${id}`);
  },

  async create(data: CreateReservationDTO): Promise<Reservation> {
    return api.post('/reservations', data);
  },

  async update(id: string, data: UpdateReservationDTO): Promise<Reservation> {
    return api.patch(`/reservations/${id}`, data);
  },

  async delete(id: string): Promise<void> {
    return api.delete(`/reservations/${id}`);
  },

  async getMyReservations(): Promise<Reservation[]> {
    return api.get('/reservations/user/me');
  },

  async getByListing(listingId: string): Promise<Reservation[]> {
    return api.get(`/reservations/listing/${listingId}`);
  },

  async updateStatus(id: string, status: ReservationStatus): Promise<Reservation> {
    return api.patch(`/reservations/${id}/status`, { status });
  },
};