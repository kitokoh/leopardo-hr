import { NextResponse } from 'next/server';

const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://leopardo.com';

export async function GET() {
  const robotsTxt = `# Robots.txt for Leopardo
# Allow all bots to crawl the site

User-agent: *
Allow: /
Disallow: /admin
Disallow: /api
Disallow: /auth
Disallow: /dashboard
Disallow: /.env
Disallow: /.git
Disallow: /node_modules
Disallow: /*.json$
Disallow: /*?*sort=
Disallow: /*?*filter=

# Specific rules for Googlebot
User-agent: Googlebot
Allow: /
Crawl-delay: 0

# Specific rules for Bingbot
User-agent: Bingbot
Allow: /
Crawl-delay: 1

# Disallow bad bots
User-agent: MJ12bot
Disallow: /

User-agent: AhrefsBot
Disallow: /

User-agent: SemrushBot
Disallow: /

# Sitemap location
Sitemap: ${siteUrl}/sitemap.xml
Sitemap: ${siteUrl}/api/sitemap

# Cache control
Cache-control: max-age=86400
`;

  return new NextResponse(robotsTxt, {
    headers: {
      'Content-Type': 'text/plain; charset=utf-8',
      'Cache-Control': 'public, s-maxage=3600, stale-while-revalidate=86400',
    },
  });
}
