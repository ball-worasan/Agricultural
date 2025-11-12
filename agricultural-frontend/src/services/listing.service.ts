import { api } from "./api";

export interface Listing {
  id: string;
  title: string;
  description: string;
  price: number;
  location: string; // e.g. "อ.เมือง จ.เชียงใหม่"
  area: number;
  images: string[];
  isAvailable: boolean;
  owner: string; // user id
  metadata?: Record<string, unknown>;
  createdAt: string;
  updatedAt: string;
}

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

type QueryValue = string | number | boolean | undefined | null;
export type ListingFilters = Record<string, QueryValue>;

export const listingService = {
  async getAll(filters?: ListingFilters): Promise<Listing[]> {
    const params = new URLSearchParams();
    if (filters) {
      Object.entries(filters).forEach(([k, v]) => {
        if (v !== undefined && v !== null) params.set(k, String(v));
      });
    }
    const { data } = await api.get<Listing[]>(`/listings?${params.toString()}`);
    return data;
  },

  async getById(id: string): Promise<Listing> {
    const { data } = await api.get<Listing>(`/listings/${id}`);
    return data;
  },

  async create(payload: CreateListingDTO): Promise<Listing> {
    const { data } = await api.post<Listing>("/listings", payload);
    return data;
  },

  async update(id: string, payload: UpdateListingDTO): Promise<Listing> {
    const { data } = await api.patch<Listing>(`/listings/${id}`, payload);
    return data;
  },

  async delete(id: string): Promise<void> {
    await api.delete<void>(`/listings/${id}`);
  },

  async getMyListings(): Promise<Listing[]> {
    const { data } = await api.get<Listing[]>("/listings/user/me");
    return data;
  },

  // Upload image helpers
  async uploadImage(file: File): Promise<string> {
    const formData = new FormData();
    formData.append("file", file);
    const { data } = await api.post<{ url: string }>("/uploads", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    return data.url;
  },
};
