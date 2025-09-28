"use client";

import createCache from "@emotion/cache";

export default function createEmotionCache() {
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
  // เพื่อความเข้ากันได้บางเคส
  // @ts-ignore
  cache.compat = true;
  return cache;
}
