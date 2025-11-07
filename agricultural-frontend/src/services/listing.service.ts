import { api } from "./api";

export interface Listing {
  id: string;
  title: string;
  description: string;
  price: number;
  location: string;
  area: number;
  images: string[];
  isAvailable: boolean;
  owner: string;
  metadata?: Record<string, any>;
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
  metadata?: Record<string, any>;
}

export interface UpdateListingDTO extends Partial<CreateListingDTO> {
  isAvailable?: boolean;
}

export const listingService = {
  async getAll(filters?: Record<string, any>): Promise<Listing[]> {
    const params = new URLSearchParams(filters);
    return api.get(`/listings?${params}`);
  },

  async getById(id: string): Promise<Listing> {
    return api.get(`/listings/${id}`);
  },

  async create(data: CreateListingDTO): Promise<Listing> {
    return api.post("/listings", data);
  },

  async update(id: string, data: UpdateListingDTO): Promise<Listing> {
    return api.patch(`/listings/${id}`, data);
  },

  async delete(id: string): Promise<void> {
    return api.delete(`/listings/${id}`);
  },

  async getMyListings(): Promise<Listing[]> {
    return api.get("/listings/user/me");
  },

  // Upload image helpers
  async uploadImage(file: File): Promise<string> {
    const formData = new FormData();
    formData.append("file", file);
    const response = await api.post("/uploads", formData, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
    });
    return response.data.url;
  },
};
