import fs from 'fs';
import path from 'path';
import matter from 'gray-matter';
import readingTime from 'reading-time';

const CONTENT_DIR = path.join(process.cwd(), 'content', 'blog');

export interface MdxPost {
  slug: string;
  title: string;
  excerpt: string;
  date: string;
  author: { name: string; avatar: string };
  category: string;
  tags: string[];
  image: string;
  readingTime: number;
  content: string;
}

export function getAllPosts(): MdxPost[] {
  if (!fs.existsSync(CONTENT_DIR)) return [];

  const files = fs.readdirSync(CONTENT_DIR).filter(f => f.endsWith('.mdx') || f.endsWith('.md'));

  return files
    .map(file => {
      const raw = fs.readFileSync(path.join(CONTENT_DIR, file), 'utf-8');
      const { data, content } = matter(raw);
      const stats = readingTime(content);

      return {
        slug: data.slug || file.replace(/\.mdx?$/, ''),
        title: data.title || '',
        excerpt: data.excerpt || '',
        date: data.date || '',
        author: data.author || { name: 'Equipe Leopardo', avatar: '' },
        category: data.category || 'General',
        tags: data.tags || [],
        image: data.image || '',
        readingTime: Math.ceil(stats.minutes),
        content,
      };
    })
    .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime());
}

export function getPostBySlug(slug: string): MdxPost | null {
  const posts = getAllPosts();
  return posts.find(p => p.slug === slug) || null;
}

export function getPostsByCategory(category: string): MdxPost[] {
  return getAllPosts().filter(p => p.category === category);
}

export function getAllCategories(): string[] {
  const posts = getAllPosts();
  return Array.from(new Set(posts.map(p => p.category)));
}

export function getAllTags(): string[] {
  const posts = getAllPosts();
  return Array.from(new Set(posts.flatMap(p => p.tags)));
}
