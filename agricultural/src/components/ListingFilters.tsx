"use client";

import { memo } from "react";
import Paper from "@mui/material/Paper";
import Grid from "@mui/material/Grid";
import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import FormControl from "@mui/material/FormControl";
import Select, { type SelectChangeEvent } from "@mui/material/Select";
import MenuItem from "@mui/material/MenuItem";
import InputLabel from "@mui/material/InputLabel";
import Divider from "@mui/material/Divider";

import { PROVINCES } from "@/lib/constants/locations";
import {
  PRICE_FILTERS,
  SORT_OPTIONS,
  LISTING_TAGS,
  type PriceFilterValue,
  type SortValue,
} from "@/lib/constants/filters";

interface ListingFiltersProps {
  province: string;
  priceFilter: PriceFilterValue;
  selectedTag: string;
  sortBy: SortValue;
  onProvinceChange: (value: string) => void;
  onPriceChange: (value: PriceFilterValue) => void;
  onTagChange: (value: string) => void;
  onSortChange: (value: SortValue) => void;
}

function ListingFilters({
  province,
  priceFilter,
  selectedTag,
  sortBy,
  onProvinceChange,
  onPriceChange,
  onTagChange,
  onSortChange,
}: ListingFiltersProps) {
  return (
    <Paper
      elevation={0}
      sx={{
        p: { xs: 1.25, sm: 1.75, md: 2 },
        border: 1,
        borderColor: "divider",
        borderRadius: 2,
        mb: { xs: 2, md: 3 },
      }}
    >
      <Grid
        container
        spacing={1.5}
        alignItems="center"
        sx={{ columnGap: { md: 2 } }}
      >
        <Grid
          size={{ xs: 12, md: "auto" }}
          sx={{ display: "flex", gap: 1, flexWrap: "wrap", flexShrink: 0 }}
        >
          <FormControl
            size="small"
            sx={{ minWidth: 160, flex: { xs: 1, sm: 0 } }}
          >
            <InputLabel id="province-label">จังหวัด</InputLabel>
            <Select
              labelId="province-label"
              label="จังหวัด"
              value={province}
              onChange={(e: SelectChangeEvent) =>
                onProvinceChange(e.target.value)
              }
              MenuProps={{ disableScrollLock: true }}
            >
              {PROVINCES.map((p) => (
                <MenuItem key={p} value={p}>
                  {p}
                </MenuItem>
              ))}
            </Select>
          </FormControl>

          <FormControl
            size="small"
            sx={{ minWidth: 140, flex: { xs: 1, sm: 0 } }}
          >
            <InputLabel id="price-label">ราคา</InputLabel>
            <Select
              labelId="price-label"
              label="ราคา"
              value={priceFilter}
              onChange={(e: SelectChangeEvent) =>
                onPriceChange(e.target.value as PriceFilterValue)
              }
              MenuProps={{ disableScrollLock: true }}
            >
              {PRICE_FILTERS.map((p) => (
                <MenuItem key={p.value} value={p.value}>
                  {p.label}
                </MenuItem>
              ))}
            </Select>
          </FormControl>

          <FormControl
            size="small"
            sx={{ minWidth: 140, flex: { xs: 1, sm: 0 } }}
          >
            <InputLabel id="tag-label">ประเภท</InputLabel>
            <Select
              labelId="tag-label"
              label="ประเภท"
              value={selectedTag}
              onChange={(e: SelectChangeEvent) => onTagChange(e.target.value)}
              MenuProps={{ disableScrollLock: true }}
            >
              <MenuItem value="ทั้งหมด">ทั้งหมด</MenuItem>
              {LISTING_TAGS.map((tag) => (
                <MenuItem key={tag} value={tag}>
                  {tag}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
        </Grid>

        <Grid size={{ xs: 12 }} sx={{ display: { xs: "block", md: "none" } }}>
          <Divider />
        </Grid>

        <Grid size={{ xs: 12, md: "auto" }} sx={{ ml: { md: "auto" } }}>
          <Box
            sx={{
              display: "flex",
              alignItems: "center",
              gap: 1,
              flexWrap: "wrap",
              justifyContent: { xs: "flex-start", md: "flex-end" },
            }}
          >
            <Typography
              variant="body2"
              sx={{ color: "text.secondary", fontSize: { xs: 12.5, sm: 13 } }}
            >
              เรียงตาม :
            </Typography>
            <FormControl
              size="small"
              sx={{ minWidth: 180, flex: { xs: 1, sm: 0 } }}
            >
              <InputLabel id="sort-label">ลงประกาศล่าสุด</InputLabel>
              <Select
                labelId="sort-label"
                label="ลงประกาศล่าสุด"
                value={sortBy}
                onChange={(e: SelectChangeEvent) =>
                  onSortChange(e.target.value as SortValue)
                }
                MenuProps={{ disableScrollLock: true }}
              >
                {SORT_OPTIONS.map((s) => (
                  <MenuItem key={s.value} value={s.value}>
                    {s.label}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          </Box>
        </Grid>
      </Grid>
    </Paper>
  );
}

export default memo(ListingFilters);
