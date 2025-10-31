// src/app/page.tsx
"use client";

import Header from "@/components/Header";
import Footer from "@/components/Footer";
import HomeFilterAndList from "@/components/HomeFilterAndList";

export default function Page() {
  return (
    <>
      <Header />
      <HomeFilterAndList />
      <Footer />
    </>
  );
}
