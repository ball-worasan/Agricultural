import type { Metadata } from "next";
import "./globals.css";
import ThemeRegistry from "./ThemeRegistry";
import { AuthProvider } from "../contexts/AuthContext";

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
        <meta name="viewport" content="width=device-width, initial-scale=1" />
      </head>
      <body>
        <ThemeRegistry>
          <AuthProvider>{children}</AuthProvider>
        </ThemeRegistry>
      </body>
    </html>
  );
}
