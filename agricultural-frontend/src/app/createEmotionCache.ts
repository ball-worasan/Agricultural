"use client";

import createCache from "@emotion/cache";
import type { EmotionCache } from "@emotion/cache";

export default function createEmotionCache(): EmotionCache {
  let insertionPoint: HTMLElement | undefined;

  if (typeof document !== "undefined") {
    const el = document.querySelector<HTMLMetaElement>(
      'meta[name="emotion-insertion-point"]'
    );
    insertionPoint = el ?? undefined;
  }

  const cache = createCache({
    key: "mui",
    insertionPoint,
  });

  // เปิดโหมด compat โดยไม่ใช้ ts-ignore
  (cache as EmotionCache & { compat?: boolean }).compat = true;

  return cache;
}
