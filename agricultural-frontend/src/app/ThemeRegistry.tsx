"use client";

import * as React from "react";
import { useServerInsertedHTML } from "next/navigation";
import { CacheProvider } from "@emotion/react";
import { CssBaseline, ThemeProvider } from "@mui/material";
import theme from "./theme";
import createEmotionCache from "./createEmotionCache";

// ตาม emotion: inserted map อาจเป็น string | true
type InsertedMap = Record<string, string | true>;

// serialized type แบบหลวม ๆ พอ (เลี่ยง @emotion/serialize)
type Serialized = { name: string };

export default function ThemeRegistry({
  children,
}: {
  children: React.ReactNode;
}) {
  const [cache] = React.useState(() => {
    const c = createEmotionCache();

    const insertedNames: string[] = [];
    const prevInsert = c.insert;

    // ประกาศ signature ให้ตรงกับ Emotion: (selector, serialized, sheet, shouldCache)
    c.insert = (
      selector: string,
      serialized: Serialized,
      sheet: unknown,
      shouldCache: boolean
    ) => {
      // เก็บชื่อ style ใหม่ที่ถูกแทรกในรอบ SSR นี้
      if (c.inserted[serialized.name] === undefined) {
        insertedNames.push(serialized.name);
      }
      // เรียกของเดิม
      return prevInsert(selector, serialized as any, sheet as any, shouldCache);
    };

    // แนบไว้เพื่อดึงใน useServerInsertedHTML
    // @ts-expect-error custom prop
    c._insertedNames = insertedNames;
    return c;
  });

  useServerInsertedHTML(() => {
    // @ts-expect-error custom prop
    const names: string[] = cache._insertedNames ?? [];
    if (names.length === 0) return null;

    // รวมเฉพาะ CSS string จริง ๆ (Emotion บางโหมดจะเก็บเป็น true)
    const inserted = cache.inserted as unknown as InsertedMap;
    const cssText = names
      .map((name: string) => {
        const v = inserted[name];
        return typeof v === "string" ? v : "";
      })
      .join("");

    // เคลียร์ชื่อที่เก็บไว้ เพื่อไม่ให้ส่งซ้ำหลายรอบ
    // @ts-expect-error custom prop
    cache._insertedNames = [];

    return (
      <style
        data-emotion={`${cache.key} ${names.join(" ")}`}
        // eslint-disable-next-line react/no-danger
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
