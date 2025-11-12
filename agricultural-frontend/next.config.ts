// next.config.ts
import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  eslint: { ignoreDuringBuilds: !!process.env.NEXT_IGNORE_ESLINT },
  typescript: { ignoreBuildErrors: !!process.env.NEXT_IGNORE_TYPECHECK },
};
export default nextConfig;
