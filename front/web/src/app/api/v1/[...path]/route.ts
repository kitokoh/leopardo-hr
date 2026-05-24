import { NextRequest } from 'next/server';

const DEFAULT_BACKEND_API_URL = 'https://gestionemployerbackend.onrender.com/api/v1';

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

function proxyHeaders(request: NextRequest): Headers {
  const headers = new Headers(request.headers);

  for (const header of Array.from(headers.keys())) {
    if (HOP_BY_HOP_HEADERS.has(header.toLowerCase())) {
      headers.delete(header);
    }
  }

  headers.set('Accept', headers.get('Accept') || 'application/json');
  return headers;
}

async function proxy(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  const { path } = await context.params;
  const method = request.method.toUpperCase();
  const body = method === 'GET' || method === 'HEAD' ? undefined : await request.arrayBuffer();

  const response = await fetch(toBackendUrl(request, path), {
    method,
    headers: proxyHeaders(request),
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
