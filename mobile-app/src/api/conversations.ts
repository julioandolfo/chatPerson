import { client } from '@/api/client';
import type {
  CheckExistingData,
  CloudWindow,
  Conversation,
  ConversationFilter,
  Note,
  Paginated,
  SendCloudTemplatePayload,
} from '@/types';

export interface ConversationListParams {
  page?: number;
  per_page?: number;
  status?: string;
  filter?: ConversationFilter;
  search?: string;
  funnel_id?: number;
  department_id?: number;
  channel?: string;
}

export async function listConversations(
  params: ConversationListParams,
): Promise<Paginated<Conversation>> {
  const { data } = await client.get<Paginated<Conversation>>('/conversations', { params });
  return data;
}

export async function getConversation(id: number): Promise<Conversation> {
  const { data } = await client.get<Conversation>(`/conversations/${id}`);
  return data;
}

export async function assignConversation(id: number, agentId: number): Promise<void> {
  await client.post(`/conversations/${id}/assign`, { agent_id: agentId });
}

export async function closeConversation(id: number): Promise<void> {
  await client.post(`/conversations/${id}/close`);
}

export async function reopenConversation(id: number): Promise<void> {
  await client.post(`/conversations/${id}/reopen`);
}

export async function moveConversationStage(id: number, stageId: number): Promise<void> {
  await client.post(`/conversations/${id}/move-stage`, { stage_id: stageId });
}

export async function setConversationDepartment(id: number, departmentId: number): Promise<void> {
  await client.put(`/conversations/${id}/department`, { department_id: departmentId });
}

export async function addConversationTag(id: number, tagId: number): Promise<void> {
  await client.post(`/conversations/${id}/tags`, { tag_id: tagId });
}

export async function removeConversationTag(id: number, tagId: number): Promise<void> {
  await client.delete(`/conversations/${id}/tags/${tagId}`);
}

export async function markConversationRead(id: number): Promise<void> {
  await client.post(`/conversations/${id}/mark-read`);
}

export async function markConversationUnread(id: number): Promise<void> {
  await client.post(`/conversations/${id}/mark-unread`);
}

// ---------------------------------------------------------------------------
// Notas
// ---------------------------------------------------------------------------

export async function listNotes(conversationId: number): Promise<Note[]> {
  const { data } = await client.get<Note[] | Paginated<Note>>(
    `/conversations/${conversationId}/notes`,
  );
  return Array.isArray(data) ? data : data.items;
}

export async function addNote(
  conversationId: number,
  payload: { content: string; is_private: boolean },
): Promise<Note> {
  const { data } = await client.post<Note>(`/conversations/${conversationId}/notes`, payload);
  return data;
}

// ---------------------------------------------------------------------------
// Cloud (WhatsApp oficial)
// ---------------------------------------------------------------------------

export async function getCloudWindow(conversationId: number): Promise<CloudWindow> {
  const { data } = await client.get<CloudWindow>(`/conversations/${conversationId}/cloud-window`);
  return data;
}

export async function sendCloudTemplate(
  conversationId: number,
  payload: SendCloudTemplatePayload,
): Promise<void> {
  await client.post(`/conversations/${conversationId}/send-cloud-template`, payload);
}

// ---------------------------------------------------------------------------
// Nova conversa
// ---------------------------------------------------------------------------

export async function checkExistingConversation(payload: {
  contact_id?: number;
  phone?: string;
}): Promise<CheckExistingData> {
  const { data } = await client.post<CheckExistingData>('/conversations/check-existing', payload);
  return data;
}

export async function createConversation(payload: {
  phone?: string;
  contact_id?: number;
  integration_account_id: number;
  content?: string;
}): Promise<Conversation> {
  const { data } = await client.post<Conversation>('/conversations/new', payload);
  return data;
}
