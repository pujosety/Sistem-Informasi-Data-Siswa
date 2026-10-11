export class ApiError extends Error {
  constructor(public status: number, message: string) { super(message); }
}
export const config = JSON.parse(document.getElementById('workspace-design-config')!.textContent!) as {
  project: string; endpoint: string; assets: string; index: string;
};
export async function api<T>(method: string, path: string, body?: unknown): Promise<T> {
  if (!path.startsWith('/workspace/design/') || path.startsWith('//')) throw new Error('Invalid API path');
  const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')!.content;
  const r = await fetch(path, {
    method, credentials: 'same-origin', redirect: 'error',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
    ...(body === undefined ? {} : { body: JSON.stringify(body) }),
  });
  const data = await r.json().catch(() => { throw new ApiError(r.status, `Server returned ${r.status} without JSON. Check your session or retry later.`); });
  if (!r.ok) throw new ApiError(r.status, data.message || 'Request failed');
  return data as T;
}
export function privateSource(src: string): string {
  const url = new URL(src, location.origin);
  if (url.origin !== location.origin || url.search || url.hash ||
      !url.pathname.startsWith(config.assets + '/') ||
      !/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(url.pathname.slice(config.assets.length + 1))) throw new Error('Only authorized private Drive images are supported');
  return url.pathname;
}
export function normalizeCanvas(value: unknown): unknown {
  if (Array.isArray(value)) return value.map(normalizeCanvas);
  if (value && typeof value === 'object') {
    return Object.fromEntries(Object.entries(value).map(([k,v]) => [k, k === 'src' && typeof v === 'string' ? privateSource(v) : normalizeCanvas(v)]));
  }
  return value;
}
