"use client";

import * as React from "react";
import { useServerInsertedHTML } from "next/navigation";
import { CacheProvider } from "@emotion/react";
import type { EmotionCache } from "@emotion/cache";
import { CssBaseline, ThemeProvider } from "@mui/material";
import theme from "./theme";
import createEmotionCache from "./createEmotionCache";

// map ของ CSS ที่ถูก insert แล้ว
type InsertedMap = Record<string, string | true>;
// โครงขั้นต่ำที่เราต้องใช้จาก serialized style
type Serialized = { name: string };

export default function ThemeRegistry({
  children,
}: {
  children: React.ReactNode;
}) {
  const [cache] = React.useState(() => {
    const c = createEmotionCache();

    // เก็บชื่อ style ที่ insert ในรอบนี้
    const insertedNames: string[] = [];

    // เก็บ type ของ insert เดิมเพื่อเรียกซ้ำ
    const prevInsert = c.insert;
    type InsertArgs = Parameters<typeof c.insert>;

    // โอเวอร์ไรด์ insert เพื่อจดชื่อ style
    c.insert = (...args: InsertArgs) => {
      const [, serialized] = args;
      const name = (serialized as Serialized).name;

      const inserted = c.inserted as InsertedMap;
      if (inserted[name] === undefined) {
        insertedNames.push(name);
      }
      return prevInsert(...args);
    };

    // ผูกที่ cache แบบขยาย type ไม่ใช้ ts-ignore
    (c as EmotionCache & { _insertedNames?: string[] })._insertedNames =
      insertedNames;

    return c;
  });

  useServerInsertedHTML(() => {
    const cacheWithNames = cache as EmotionCache & {
      _insertedNames?: string[];
    };
    const names = cacheWithNames._insertedNames ?? [];
    if (names.length === 0) return null;

    const inserted = cache.inserted as InsertedMap;
    const cssText = names
      .map((name) =>
        typeof inserted[name] === "string" ? (inserted[name] as string) : ""
      )
      .join("");

    // reset per flush
    cacheWithNames._insertedNames = [];

    return (
      <style
        data-emotion={`${cache.key} ${names.join(" ")}`}
        dangerouslySetInnerHTML={{ __html: cssText }}
      />
    );
  });

  return (
    <CacheProvider value={cache}>
      <ThemeProvider theme={theme}>
        <CssBaseline />
        {children}
      </ThemeProvider>
    </CacheProvider>
  );
}
