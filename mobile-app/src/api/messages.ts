import { client } from '@/api/client';
import type { Message, SendMessageInput } from '@/types';

export interface MessagesQuery {
  limit?: number;
  before_id?: number;
  after_id?: number;
}

export async function listMessages(
  conversationId: number,
  query: MessagesQuery = {},
): Promise<{ items: Message[] }> {
  const { data } = await client.get<{ items: Message[] }>(
    `/conversations/${conversationId}/messages`,
    { params: query },
  );
  return data;
}

/**
 * Envia mensagem via multipart/form-data (suporta anexos do dispositivo).
 */
export async function sendMessage(
  conversationId: number,
  input: SendMessageInput,
): Promise<Message> {
  const form = new FormData();
  if (input.content && input.content.trim().length > 0) {
    form.append('content', input.content.trim());
  }
  if (input.quoted_message_id != null) {
    form.append('quoted_message_id', String(input.quoted_message_id));
  }
  if (input.is_note) {
    form.append('is_note', '1');
  }
  for (const attachment of input.attachments ?? []) {
    // No React Native, arquivos são anexados como { uri, name, type }.
    form.append('attachments[]', {
      uri: attachment.uri,
      name: attachment.name,
      type: attachment.type,
    } as unknown as Blob);
  }

  const { data } = await client.post<Message>(
    `/conversations/${conversationId}/messages`,
    form,
    { headers: { 'Content-Type': 'multipart/form-data' } },
  );
  return data;
}
