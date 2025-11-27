import apiClient from "@/lib/api-client";
import type { Reservation, ReservationStatus } from "@/types";

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
  getAll: () => apiClient.get<Reservation[]>("/reservations"),

  getById: (id: string) => apiClient.get<Reservation>(`/reservations/${id}`),

  create: (payload: CreateReservationDTO) =>
    apiClient.post<Reservation>("/reservations", payload),

  update: (id: string, payload: UpdateReservationDTO) =>
    apiClient.patch<Reservation>(`/reservations/${id}`, payload),

  delete: (id: string) => apiClient.delete<void>(`/reservations/${id}`),

  getMyReservations: () =>
    apiClient.get<Reservation[]>("/reservations/user/me"),

  getByListing: (listingId: string) =>
    apiClient.get<Reservation[]>(`/reservations/listing/${listingId}`),

  updateStatus: (id: string, status: ReservationStatus) =>
    apiClient.patch<Reservation>(`/reservations/${id}/status`, { status }),
};
