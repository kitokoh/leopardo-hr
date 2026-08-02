import { NextRequest } from 'next/server';
import { cookies } from 'next/headers';

const DEFAULT_BACKEND_API_URL = 'https://gestionemployerbackend.onrender.com/api/v1';
const SESSION_COOKIE_NAME = 'leopardo_token';

const HOP_BY_HOP_HEADERS = new Set([
  'connection',
  'content-length',
  'host',
  'keep-alive',
  'proxy-authenticate',
  'proxy-authorization',
  'te',
  'trailer',
  'transfer-encoding',
  'upgrade',
]);

function resolveBackendBaseUrl(): string {
  return (
    process.env.API_PROXY_TARGET ||
    process.env.BACKEND_API_URL ||
    process.env.NEXT_PUBLIC_API_URL ||
    DEFAULT_BACKEND_API_URL
  ).replace(/\/$/, '');
}

function toBackendUrl(request: NextRequest, path: string[]): string {
  const url = new URL(request.url);
  const backendUrl = new URL(`${resolveBackendBaseUrl()}/${path.join('/')}`);
  backendUrl.search = url.search;
  return backendUrl.toString();
}

function proxyHeaders(request: NextRequest, sessionToken?: string): Headers {
  const headers = new Headers(request.headers);

  for (const header of Array.from(headers.keys())) {
    if (HOP_BY_HOP_HEADERS.has(header.toLowerCase())) {
      headers.delete(header);
    }
  }

  headers.set('Accept', headers.get('Accept') || 'application/json');

  // Security fix (#1299): inject the httpOnly session cookie as a Bearer
  // Authorization header so the token never flows through client-side JS.
  // The browser cannot read `leopardo_token` (httpOnly), but the Next.js
  // server-side proxy reads it here and adds the Authorization header.
  // If the request already carries an explicit Authorization header
  // (e.g. from mobile or server-side fetch), it is preserved unchanged.
  if (sessionToken && !headers.has('authorization')) {
    headers.set('Authorization', `Bearer ${sessionToken}`);
  }

  return headers;
}

async function proxy(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  const { path } = await context.params;
  const method = request.method.toUpperCase();
  const body = method === 'GET' || method === 'HEAD' ? undefined : await request.arrayBuffer();

  // Read the httpOnly session cookie — only accessible server-side
  const cookieStore = await cookies();
  const sessionToken = cookieStore.get(SESSION_COOKIE_NAME)?.value;

  const response = await fetch(toBackendUrl(request, path), {
    method,
    headers: proxyHeaders(request, sessionToken),
    body,
    redirect: 'manual',
    cache: 'no-store',
  });

  const headers = new Headers(response.headers);
  headers.delete('content-encoding');
  headers.delete('content-length');
  headers.set('Cache-Control', 'no-store');

  return new Response(response.body, {
    status: response.status,
    statusText: response.statusText,
    headers,
  });
}

export async function GET(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  return proxy(request, context);
}

export async function POST(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  return proxy(request, context);
}

export async function PUT(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  return proxy(request, context);
}

export async function PATCH(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  return proxy(request, context);
}

export async function DELETE(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  return proxy(request, context);
}

export async function OPTIONS() {
  return new Response(null, {
    status: 204,
    headers: {
      'Access-Control-Allow-Headers': 'Authorization, Content-Type, Accept, Accept-Language',
      'Access-Control-Allow-Methods': 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
      'Access-Control-Allow-Origin': '*',
      'Cache-Control': 'no-store',
    },
  });
}
