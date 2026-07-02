import { client } from '@/api/client';
import type {
  Agent,
  CloudTemplate,
  ContactSummary,
  Department,
  Funnel,
  FunnelStage,
  Paginated,
  SignedUrlData,
  Tag,
  WhatsAppAccount,
} from '@/types';

function asArray<T>(data: T[] | Paginated<T>): T[] {
  return Array.isArray(data) ? data : data.items;
}

export async function getAgents(): Promise<Agent[]> {
  const { data } = await client.get<Agent[] | Paginated<Agent>>('/agents');
  return asArray(data);
}

export async function getDepartments(): Promise<Department[]> {
  const { data } = await client.get<Department[] | Paginated<Department>>('/departments');
  return asArray(data);
}

export async function getFunnels(): Promise<Funnel[]> {
  const { data } = await client.get<Funnel[] | Paginated<Funnel>>('/funnels');
  return asArray(data);
}

export async function getFunnelStages(funnelId: number): Promise<FunnelStage[]> {
  const { data } = await client.get<FunnelStage[] | Paginated<FunnelStage>>(
    `/funnels/${funnelId}/stages`,
  );
  return asArray(data);
}

export async function getTags(): Promise<Tag[]> {
  const { data } = await client.get<Tag[] | Paginated<Tag>>('/tags');
  return asArray(data);
}

export async function getWhatsAppAccounts(): Promise<WhatsAppAccount[]> {
  const { data } = await client.get<WhatsAppAccount[] | Paginated<WhatsAppAccount>>(
    '/whatsapp-accounts',
  );
  return asArray(data);
}

export async function getContacts(search: string, page = 1): Promise<Paginated<ContactSummary>> {
  const { data } = await client.get<Paginated<ContactSummary>>('/contacts', {
    params: { search: search || undefined, page },
  });
  return data;
}

export async function getTemplates(from?: number | string): Promise<CloudTemplate[]> {
  const { data } = await client.get<CloudTemplate[] | Paginated<CloudTemplate>>('/templates', {
    params: { from },
  });
  return asArray(data);
}

// ---------------------------------------------------------------------------
// Mídia assinada
// ---------------------------------------------------------------------------

interface SignedCacheEntry {
  url: string;
  expiresAt: number;
}

const SIGNED_URL_TTL_MS = 10 * 60 * 1000;
const signedCache = new Map<string, SignedCacheEntry>();
const inflight = new Map<string, Promise<string>>();

/**
 * Resolve a URL de um anexo. URLs absolutas e arquivos locais são retornados
 * como estão; paths relativos são trocados por URLs assinadas via
 * GET /attachments/sign (com cache de 10 minutos e dedupe de requests).
 */
export async function resolveAttachmentUrl(pathOrUrl: string): Promise<string> {
  if (/^(https?|file|content|data):/i.test(pathOrUrl)) {
    return pathOrUrl;
  }

  const cached = signedCache.get(pathOrUrl);
  if (cached && cached.expiresAt > Date.now()) {
    return cached.url;
  }

  const pending = inflight.get(pathOrUrl);
  if (pending) return pending;

  const promise = (async () => {
    try {
      const { data } = await client.get<SignedUrlData>('/attachments/sign', {
        params: { path: pathOrUrl },
      });
      signedCache.set(pathOrUrl, { url: data.url, expiresAt: Date.now() + SIGNED_URL_TTL_MS });
      return data.url;
    } finally {
      inflight.delete(pathOrUrl);
    }
  })();

  inflight.set(pathOrUrl, promise);
  return promise;
}
