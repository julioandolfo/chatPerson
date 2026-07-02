import { useEffect, useState } from 'react';

import { resolveAttachmentUrl } from '@/api/misc';

/**
 * Resolve o path/URL de um anexo para uma URL exibível (assinada quando
 * necessário). Retorna null enquanto resolve ou em caso de falha.
 */
export function useResolvedUrl(pathOrUrl: string | null | undefined): string | null {
  const [url, setUrl] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    setUrl(null);
    if (!pathOrUrl) return undefined;

    resolveAttachmentUrl(pathOrUrl)
      .then((resolved) => {
        if (active) setUrl(resolved);
      })
      .catch(() => {
        if (active) setUrl(null);
      });

    return () => {
      active = false;
    };
  }, [pathOrUrl]);

  return url;
}
