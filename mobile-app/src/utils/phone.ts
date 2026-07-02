/** Mantém apenas dígitos. */
export function normalizePhone(phone: string): string {
  return phone.replace(/\D+/g, '');
}

/**
 * Formata número brasileiro para exibição.
 *   5511912345678 → +55 (11) 91234-5678
 *   11912345678   → (11) 91234-5678
 * Números fora do padrão BR são retornados com apenas um "+" quando aplicável.
 */
export function formatPhone(phone: string | null | undefined): string {
  if (!phone) return '';
  const digits = normalizePhone(phone);
  if (digits.length === 0) return phone;

  let country = '';
  let rest = digits;

  if (digits.length > 11 && digits.startsWith('55')) {
    country = '+55 ';
    rest = digits.slice(2);
  }

  if (rest.length === 11) {
    return `${country}(${rest.slice(0, 2)}) ${rest.slice(2, 7)}-${rest.slice(7)}`;
  }
  if (rest.length === 10) {
    return `${country}(${rest.slice(0, 2)}) ${rest.slice(2, 6)}-${rest.slice(6)}`;
  }
  return country ? `${country}${rest}` : phone;
}
