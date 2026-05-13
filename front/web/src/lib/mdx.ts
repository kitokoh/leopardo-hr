import fs from 'fs';
import path from 'path';
import matter from 'gray-matter';
import readingTime from 'reading-time';

const BLOG_DIR = path.join(process.cwd(), 'src/content/blog');

export interface BlogFrontmatter {
  title: string;
  description: string;
  date: string;
  author: string;
  category: string;
  keywords: string[];
  image: string;
  tags?: string[];
}

export interface BlogPostMeta {
  slug: string;
  title: string;
  description: string;
  date: string;
  author: string;
  category: string;
  keywords: string[];
  image: string;
  tags: string[];
  readingTime: string;
  readingTimeMinutes: number;
}

export interface BlogPostFull extends BlogPostMeta {
  content: string;
}

export function getAllBlogPosts(): BlogPostMeta[] {
  if (!fs.existsSync(BLOG_DIR)) return [];

  const files = fs.readdirSync(BLOG_DIR).filter((f) => f.endsWith('.md'));

  const posts = files
    .map((filename) => {
      const slug = filename.replace(/\.md$/, '');
      const filePath = path.join(BLOG_DIR, filename);
      const fileContent = fs.readFileSync(filePath, 'utf-8');
      const { data, content } = matter(fileContent);
      const fm = data as BlogFrontmatter;
      const stats = readingTime(content);

      return {
        slug,
        title: fm.title || slug,
        description: fm.description || '',
        date: fm.date || '',
        author: fm.author || 'Leopardo Team',
        category: fm.category || 'General',
        keywords: fm.keywords || [],
        image: fm.image || '/blog/default.png',
        tags: fm.tags || fm.keywords || [],
        readingTime: stats.text,
        readingTimeMinutes: Math.ceil(stats.minutes),
      };
    })
    .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime());

  return posts;
}

export function getBlogPost(slug: string): BlogPostFull | null {
  const filePath = path.join(BLOG_DIR, `${slug}.md`);

  if (!fs.existsSync(filePath)) return null;

  const fileContent = fs.readFileSync(filePath, 'utf-8');
  const { data, content } = matter(fileContent);
  const fm = data as BlogFrontmatter;
  const stats = readingTime(content);

  return {
    slug,
    title: fm.title || slug,
    description: fm.description || '',
    date: fm.date || '',
    author: fm.author || 'Leopardo Team',
    category: fm.category || 'General',
    keywords: fm.keywords || [],
    image: fm.image || '/blog/default.png',
    tags: fm.tags || fm.keywords || [],
    readingTime: stats.text,
    readingTimeMinutes: Math.ceil(stats.minutes),
    content,
  };
}

export function getAllCategories(): string[] {
  const posts = getAllBlogPosts();
  return Array.from(new Set(posts.map((p) => p.category)));
}

export function getAllTags(): string[] {
  const posts = getAllBlogPosts();
  const tags = new Set<string>();
  posts.forEach((p) => p.tags.forEach((t) => tags.add(t)));
  return Array.from(tags);
}

export function getRelatedPosts(slug: string, limit = 3): BlogPostMeta[] {
  const currentPost = getBlogPost(slug);
  if (!currentPost) return [];

  const allPosts = getAllBlogPosts().filter((p) => p.slug !== slug);

  const scored = allPosts.map((post) => {
    let score = 0;
    if (post.category === currentPost.category) score += 3;
    const sharedTags = post.tags.filter((t) => currentPost.tags.includes(t));
    score += sharedTags.length;
    return { ...post, score };
  });

  return scored
    .sort((a, b) => b.score - a.score)
    .slice(0, limit);
}

export function extractHeadings(content: string): { id: string; text: string; level: number }[] {
  const headingRegex = /^(#{2,4})\s+(.+)$/gm;
  const headings: { id: string; text: string; level: number }[] = [];
  let match;

  while ((match = headingRegex.exec(content)) !== null) {
    const level = match[1].length;
    const text = match[2].trim();
    const id = text
      .toLowerCase()
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/\s+/g, '-');
    headings.push({ id, text, level });
  }

  return headings;
}
