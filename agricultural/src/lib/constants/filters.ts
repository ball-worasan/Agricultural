// Filter options for listings

export const PRICE_FILTERS = [
  { value: "all", label: "ทั้งหมด" },
  { value: "lt10000", label: "< 10,000" },
  { value: "10to50", label: "10,000 - 50,000" },
  { value: "gt50000", label: "> 50,000" },
] as const;

export const SORT_OPTIONS = [
  { value: "newest", label: "ลงประกาศล่าสุด" },
  { value: "priceAsc", label: "ราคาต่ำไปสูง" },
  { value: "priceDesc", label: "ราคาสูงไปต่ำ" },
] as const;

export const LISTING_TAGS = ["ทำนา", "ทำไร่", "ทำสวน", "ฟาร์ม"] as const;

export type PriceFilterValue = (typeof PRICE_FILTERS)[number]["value"];
export type SortValue = (typeof SORT_OPTIONS)[number]["value"];
export type ListingTag = (typeof LISTING_TAGS)[number];

export function isWithinPriceRange(
  price: number,
  filter: PriceFilterValue
): boolean {
  switch (filter) {
    case "lt10000":
      return price < 10000;
    case "10to50":
      return price >= 10000 && price <= 50000;
    case "gt50000":
      return price > 50000;
    default:
      return true;
  }
}
