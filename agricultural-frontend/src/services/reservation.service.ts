import { api } from "./api";

export enum ReservationStatus {
  PENDING = "pending",
  CONFIRMED = "confirmed",
  CANCELLED = "cancelled",
  COMPLETED = "completed",
}

export interface Reservation {
  id: string;
  listing: string; // listing id
  user: string; // user id
  startDate: string;
  endDate: string;
  totalPrice: number;
  status: ReservationStatus;
  paymentDetails?: Record<string, unknown>;
  metadata?: Record<string, unknown>;
  createdAt: string;
  updatedAt: string;
}

export interface CreateReservationDTO {
  listing: string;
  startDate: string;
  endDate: string;
  totalPrice: number;
  paymentDetails?: Record<string, unknown>;
  metadata?: Record<string, unknown>;
}

export interface UpdateReservationDTO {
  startDate?: string;
  endDate?: string;
  totalPrice?: number;
  status?: ReservationStatus;
  paymentDetails?: Record<string, unknown>;
  metadata?: Record<string, unknown>;
}

export const reservationService = {
  async getAll(): Promise<Reservation[]> {
    const { data } = await api.get<Reservation[]>("/reservations");
    return data;
  },

  async getById(id: string): Promise<Reservation> {
    const { data } = await api.get<Reservation>(`/reservations/${id}`);
    return data;
  },

  async create(payload: CreateReservationDTO): Promise<Reservation> {
    const { data } = await api.post<Reservation>("/reservations", payload);
    return data;
  },

  async update(
    id: string,
    payload: UpdateReservationDTO
  ): Promise<Reservation> {
    const { data } = await api.patch<Reservation>(
      `/reservations/${id}`,
      payload
    );
    return data;
  },

  async delete(id: string): Promise<void> {
    await api.delete<void>(`/reservations/${id}`);
  },

  async getMyReservations(): Promise<Reservation[]> {
    const { data } = await api.get<Reservation[]>("/reservations/user/me");
    return data;
  },

  async getByListing(listingId: string): Promise<Reservation[]> {
    const { data } = await api.get<Reservation[]>(
      `/reservations/listing/${listingId}`
    );
    return data;
  },

  async updateStatus(
    id: string,
    status: ReservationStatus
  ): Promise<Reservation> {
    const { data } = await api.patch<Reservation>(
      `/reservations/${id}/status`,
      { status }
    );
    return data;
  },
};
