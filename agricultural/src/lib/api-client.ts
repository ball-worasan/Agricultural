import axios, {
  AxiosError,
  AxiosResponse,
  InternalAxiosRequestConfig,
  AxiosInstance,
} from "axios";
import { getCookie, deleteCookie } from "@/lib/utils/cookie";

// Changed to use Next.js API routes instead of external backend
const API_URL = process.env.NEXT_PUBLIC_API_URL || "/api";

// Create a custom axios instance with unwrapped responses
const axiosInstance = axios.create({
  baseURL: API_URL,
  headers: { "Content-Type": "application/json" },
  timeout: 10000,
});

// Request interceptor - add auth token
axiosInstance.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = getCookie("token");
  if (token) {
    config.headers = config.headers || {};
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Response interceptor - handle errors and unwrap data
axiosInstance.interceptors.response.use(
  (response: AxiosResponse) => response.data,
  (error: AxiosError) => {
    // Handle 401 - unauthorized
    if (error.response?.status === 401) {
      deleteCookie("token");
      deleteCookie("user");
      if (typeof window !== "undefined") {
        window.location.href = "/auth/login";
      }
    }

    // Return structured error
    return Promise.reject({
      message:
        (error.response?.data as { message?: string })?.message ||
        error.message,
      status: error.response?.status,
      data: error.response?.data,
    });
  }
);

// Type-safe wrapper that reflects the unwrapped response
export interface UnwrappedAxiosInstance
  extends Omit<AxiosInstance, "get" | "post" | "put" | "patch" | "delete"> {
  get<T = any>(url: string, config?: any): Promise<T>;
  post<T = any>(url: string, data?: any, config?: any): Promise<T>;
  put<T = any>(url: string, data?: any, config?: any): Promise<T>;
  patch<T = any>(url: string, data?: any, config?: any): Promise<T>;
  delete<T = any>(url: string, config?: any): Promise<T>;
}

export const apiClient = axiosInstance as unknown as UnwrappedAxiosInstance;

export default apiClient;
