import type { MetadataRoute } from 'next';
import { getAllBlogPosts } from '@/lib/mdx';

const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://leopardo.com';

export default function sitemap(): MetadataRoute.Sitemap {
  const today = new Date();

  const staticPages: MetadataRoute.Sitemap = [
    { url: `${siteUrl}/`, lastModified: today, changeFrequency: 'weekly', priority: 1.0 },
    { url: `${siteUrl}/employes`, lastModified: today, changeFrequency: 'weekly', priority: 0.9 },
    { url: `${siteUrl}/documents`, lastModified: today, changeFrequency: 'weekly', priority: 0.9 },
    { url: `${siteUrl}/comptabilite`, lastModified: today, changeFrequency: 'weekly', priority: 0.9 },
    { url: `${siteUrl}/marketing`, lastModified: today, changeFrequency: 'weekly', priority: 0.9 },
    { url: `${siteUrl}/pricing`, lastModified: today, changeFrequency: 'monthly', priority: 0.8 },
    { url: `${siteUrl}/demo`, lastModified: today, changeFrequency: 'monthly', priority: 0.8 },
    { url: `${siteUrl}/about`, lastModified: today, changeFrequency: 'monthly', priority: 0.7 },
    { url: `${siteUrl}/changelog`, lastModified: today, changeFrequency: 'weekly', priority: 0.65 },
    { url: `${siteUrl}/blog`, lastModified: today, changeFrequency: 'weekly', priority: 0.8 },
    { url: `${siteUrl}/privacy`, lastModified: today, changeFrequency: 'yearly', priority: 0.4 },
    { url: `${siteUrl}/terms`, lastModified: today, changeFrequency: 'yearly', priority: 0.4 },
    { url: `${siteUrl}/guides/rh-startup`, lastModified: today, changeFrequency: 'monthly', priority: 0.7 },
    { url: `${siteUrl}/guides/checklist-paie`, lastModified: today, changeFrequency: 'monthly', priority: 0.7 },
    { url: `${siteUrl}/guides/planning-employes`, lastModified: today, changeFrequency: 'monthly', priority: 0.7 },
  ];

  const blogPosts = getAllBlogPosts();
  const blogPages: MetadataRoute.Sitemap = blogPosts.map((post) => ({
    url: `${siteUrl}/blog/${post.slug}`,
    lastModified: post.date ? new Date(post.date) : today,
    changeFrequency: 'monthly' as const,
    priority: 0.6,
  }));

  return [...staticPages, ...blogPages];
}
