import type { Metadata } from "next";
import "./globals.css";
import ThemeRegistry from "./ThemeRegistry";

export const metadata: Metadata = {
  title: "RentSpace - เช่าพื้นที่ออนไลน์",
  description: "แพลตฟอร์มเช่าพื้นที่ออนไลน์ที่ใหญ่ที่สุดในประเทศไทย",
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="th">
      <head>
        <meta name="emotion-insertion-point" content="" />
      </head>
      <body>
        <ThemeRegistry>{children}</ThemeRegistry>
      </body>
    </html>
  );
}
