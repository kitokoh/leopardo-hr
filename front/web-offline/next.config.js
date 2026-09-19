const path = require('path');

/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'export',           // Static export — works fully offline
  trailingSlash: true,
  images: { unoptimized: true },
  env: {
    NEXT_PUBLIC_EDGE_API: process.env.NEXT_PUBLIC_EDGE_API || '',  // #7653 : same-origin via le proxy TLS
  },
  // This sub-project has its own package-lock.json alongside the monorepo
  // root lockfile. Pin the workspace root explicitly so Turbopack does not
  // have to guess (and warn) which lockfile/directory owns the build.
  turbopack: {
    root: path.join(__dirname),
  },
};

module.exports = nextConfig;
