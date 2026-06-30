/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'export',           // Static export — works fully offline
  trailingSlash: true,
  images: { unoptimized: true },
  env: {
    NEXT_PUBLIC_EDGE_API: process.env.NEXT_PUBLIC_EDGE_API || 'http://leopardo.local:7878',
  },
};

module.exports = nextConfig;
