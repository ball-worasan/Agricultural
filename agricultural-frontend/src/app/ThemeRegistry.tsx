"use client";

import * as React from "react";
import { useServerInsertedHTML } from "next/navigation";
import { CacheProvider } from "@emotion/react";
import { CssBaseline, ThemeProvider } from "@mui/material";
import theme from "./theme";
import createEmotionCache from "./createEmotionCache";

// Emotion typesแบบหลวมให้ compile ง่าย
type InsertedMap = Record<string, string | true>;
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

    c.insert = (
      selector: string,
      serialized: Serialized,
      sheet: unknown,
      shouldCache: boolean
    ) => {
      if ((c.inserted as any)[serialized.name] === undefined) {
        insertedNames.push(serialized.name);
      }
      return prevInsert(selector, serialized as any, sheet as any, shouldCache);
    };

    // @ts-expect-error custom
    c._insertedNames = insertedNames;
    return c;
  });

  useServerInsertedHTML(() => {
    // @ts-expect-error custom
    const names: string[] = cache._insertedNames ?? [];
    if (names.length === 0) return null;

    const inserted = cache.inserted as unknown as InsertedMap;
    const cssText = names
      .map((name) =>
        typeof inserted[name] === "string" ? (inserted[name] as string) : ""
      )
      .join("");

    // @ts-expect-error custom
    cache._insertedNames = []; // reset per flush

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
