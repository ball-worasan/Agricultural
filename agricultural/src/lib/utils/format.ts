// Utility functions for formatting

export function formatPrice(price: number, locale = "th-TH"): string {
  return price.toLocaleString(locale);
}

export function formatDate(dateStr: string, locale = "th-TH"): string {
  return new Date(dateStr).toLocaleDateString(locale);
}

export function truncateText(text: string, maxLength: number): string {
  if (text.length <= maxLength) return text;
  return text.slice(0, maxLength) + "...";
}
