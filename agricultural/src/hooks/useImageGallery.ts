"use client";

import { useState, useCallback } from "react";

export function useImageGallery(images: string[]) {
  const [currentIdx, setCurrentIdx] = useState(0);
  const [imgLoaded, setImgLoaded] = useState(false);

  const nextImage = useCallback(() => {
    setCurrentIdx((prev) => (prev + 1) % images.length);
    setImgLoaded(false);
  }, [images.length]);

  const prevImage = useCallback(() => {
    setCurrentIdx((prev) => (prev - 1 + images.length) % images.length);
    setImgLoaded(false);
  }, [images.length]);

  const selectImage = useCallback((idx: number) => {
    setCurrentIdx(idx);
    setImgLoaded(false);
  }, []);

  const handleLoad = useCallback(() => setImgLoaded(true), []);

  return {
    currentIdx,
    imgLoaded,
    currentImage: images[currentIdx],
    nextImage,
    prevImage,
    selectImage,
    handleLoad,
  };
}
