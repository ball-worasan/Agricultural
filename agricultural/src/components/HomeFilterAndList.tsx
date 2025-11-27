"use client";

import { useMemo, useState, useEffect, useCallback } from "react";
import Container from "@mui/material/Container";
import Box from "@mui/material/Box";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";

import ListingFilters from "./ListingFilters";
import ListingCard from "./ListingCard";

import { LISTINGS } from "@/data/listings";
import {
  isWithinPriceRange,
  type PriceFilterValue,
  type SortValue,
} from "@/lib/constants/filters";

export default function HomeFilterAndList() {
  const [searchTitle, setSearchTitle] = useState("");
  const [province, setProvince] = useState("ทั้งหมด");
  const [priceFilter, setPriceFilter] = useState<PriceFilterValue>("all");
  const [selectedTag, setSelectedTag] = useState("ทั้งหมด");
  const [sortBy, setSortBy] = useState<SortValue>("newest");

  // Listen for search events from Header
  useEffect(() => {
    const handleSearch = (event: Event) => {
      const customEvent = event as CustomEvent<{ keyword: string }>;
      setSearchTitle(customEvent.detail.keyword);
    };

    window.addEventListener("searchListings", handleSearch);
    return () => window.removeEventListener("searchListings", handleSearch);
  }, []);

  // Filter and sort listings
  const filteredListings = useMemo(() => {
    let arr = LISTINGS.filter((listing) => {
      if (
        searchTitle.trim() &&
        !listing.title.toLowerCase().includes(searchTitle.toLowerCase().trim())
      ) {
        return false;
      }
      if (province !== "ทั้งหมด" && listing.province !== province) {
        return false;
      }
      if (!isWithinPriceRange(listing.price, priceFilter)) {
        return false;
      }
      if (selectedTag !== "ทั้งหมด") {
        if (!listing.tags || !listing.tags.includes(selectedTag)) {
          return false;
        }
      }
      return true;
    });

    // Sort
    switch (sortBy) {
      case "priceAsc":
        arr = [...arr].sort((a, b) => a.price - b.price);
        break;
      case "priceDesc":
        arr = [...arr].sort((a, b) => b.price - a.price);
        break;
      default:
        arr = [...arr].sort((a, b) => (a.postedAt < b.postedAt ? 1 : -1));
    }

    return arr;
  }, [searchTitle, province, priceFilter, selectedTag, sortBy]);

  const handleProvinceChange = useCallback(
    (value: string) => setProvince(value),
    []
  );
  const handlePriceChange = useCallback(
    (value: PriceFilterValue) => setPriceFilter(value),
    []
  );
  const handleTagChange = useCallback(
    (value: string) => setSelectedTag(value),
    []
  );
  const handleSortChange = useCallback(
    (value: SortValue) => setSortBy(value),
    []
  );

  return (
    <Container
      maxWidth="lg"
      sx={{ py: { xs: 3, md: 5 } }}
      id="listings-section"
    >
      <ListingFilters
        province={province}
        priceFilter={priceFilter}
        selectedTag={selectedTag}
        sortBy={sortBy}
        onProvinceChange={handleProvinceChange}
        onPriceChange={handlePriceChange}
        onTagChange={handleTagChange}
        onSortChange={handleSortChange}
      />

      <Box sx={{ display: "grid", gap: { xs: 1, sm: 1.25, md: 1.5 } }}>
        {filteredListings.length === 0 ? (
          <Paper
            elevation={0}
            sx={{
              p: { xs: 3, md: 4 },
              textAlign: "center",
              border: 1,
              borderColor: "divider",
              borderRadius: 2,
            }}
          >
            <Typography>ไม่พบรายการที่ตรงเงื่อนไข</Typography>
          </Paper>
        ) : (
          filteredListings.map((listing) => (
            <ListingCard key={listing.id} listing={listing} />
          ))
        )}
      </Box>
    </Container>
  );
}
