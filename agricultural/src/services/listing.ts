import apiClient from "@/lib/api-client";
import type { Listing } from "@/types";

export interface CreateListingDTO {
  title: string;
  description: string;
  price: number;
  location: string;
  area: number;
  images?: string[];
  metadata?: Record<string, unknown>;
}

export interface UpdateListingDTO extends Partial<CreateListingDTO> {
  isAvailable?: boolean;
}

export interface ListingFilters {
  province?: string;
  priceMin?: number;
  priceMax?: number;
  tags?: string;
  search?: string;
}

export const listingService = {
  getAll: (filters?: ListingFilters) => {
    const params = new URLSearchParams();
    if (filters) {
      Object.entries(filters).forEach(([k, v]) => {
        if (v !== undefined && v !== null) params.set(k, String(v));
      });
    }
    return apiClient.get<Listing[]>(`/listings?${params.toString()}`);
  },

  getById: (id: string) => apiClient.get<Listing>(`/listings/${id}`),

  create: (payload: CreateListingDTO) =>
    apiClient.post<Listing>("/listings", payload),

  update: (id: string, payload: UpdateListingDTO) =>
    apiClient.patch<Listing>(`/listings/${id}`, payload),

  delete: (id: string) => apiClient.delete<void>(`/listings/${id}`),

  getMyListings: () => apiClient.get<Listing[]>("/listings/user/me"),

  uploadImage: (file: File) => {
    const formData = new FormData();
    formData.append("file", file);
    return apiClient.post<{ url: string }>("/uploads", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });
  },
};
