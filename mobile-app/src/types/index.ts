/**
 * Tipos do contrato da API do chatPerson (Fase 0).
 * Envelope padrão: { success: true, data } | { success: false, error: { message, code } }
 */

// ---------------------------------------------------------------------------
// Envelope
// ---------------------------------------------------------------------------

export interface ApiErrorPayload {
  message: string;
  code?: string;
}

export type ApiEnvelope<T> =
  | { success: true; data: T }
  | { success: false; error: ApiErrorPayload };

export interface Pagination {
  total: number;
  page: number;
  per_page: number;
  total_pages: number;
  has_next: boolean;
}

export interface Paginated<T> {
  items: T[];
  pagination: Pagination;
}

// ---------------------------------------------------------------------------
// Auth
// ---------------------------------------------------------------------------

export interface User {
  id: number;
  name: string;
  email: string;
  avatar?: string | null;
  role?: string | null;
}

export interface LoginData {
  access_token: string;
  refresh_token: string;
  token_type: string;
  expires_in: number;
  user: User;
}

export interface RefreshData {
  access_token: string;
  expires_in: number;
}

export type MeData = User & { permissions: string[] };

// ---------------------------------------------------------------------------
// Conversas
// ---------------------------------------------------------------------------

export type Channel = 'whatsapp' | 'instagram' | 'email' | 'chat';

export type ConversationStatus = 'open' | 'pending' | 'closed';

export type ConversationFilter = 'mine' | 'unassigned' | 'all';

export type SlaState = 'ok' | 'warning' | 'breached' | null;

export interface Tag {
  id: number;
  name: string;
  color: string;
}

export interface ContactSummary {
  id: number;
  name: string;
  avatar?: string | null;
  phone?: string | null;
  email?: string | null;
}

export interface Conversation {
  id: number;
  contact: ContactSummary;
  channel: Channel;
  status: ConversationStatus;
  agent_id: number | null;
  agent_name: string | null;
  department_id: number | null;
  funnel_id: number | null;
  funnel_stage_id: number | null;
  unread_count: number;
  last_message_preview: string | null;
  last_message_at: string | null;
  pinned: boolean;
  priority: string | null;
  tags: Tag[];
  sla_state: SlaState;
  /** Presente no detalhe — conta usada para envio (WhatsApp Cloud etc.). */
  integration_account_id?: number | null;
}

export type ConversationUpdate = Partial<Conversation> & { id: number };

// ---------------------------------------------------------------------------
// Mensagens
// ---------------------------------------------------------------------------

export type SenderType = 'agent' | 'contact' | 'ai_agent' | 'system';

export type MessageStatus = 'pending' | 'sent' | 'delivered' | 'read' | 'error';

export interface Attachment {
  url: string;
  type: string;
  name?: string | null;
  size?: number | null;
}

export interface Message {
  id: number;
  conversation_id: number;
  sender_type: SenderType;
  sender_id: number | null;
  sender_name: string | null;
  content: string | null;
  message_type: string;
  attachments: Attachment[];
  status: MessageStatus;
  created_at: string;
  quoted_message_id?: number | null;
  quoted_text?: string | null;
  quoted_sender_name?: string | null;
  is_note: boolean;
  reactions?: Record<string, number> | null;
  /** Identificador local de mensagens otimistas (não vem da API). */
  local_id?: string;
}

/** Anexo local (selecionado no dispositivo) ainda não enviado. */
export interface LocalAttachment {
  uri: string;
  name: string;
  type: string;
  size?: number;
}

export interface SendMessageInput {
  local_id: string;
  content?: string;
  attachments?: LocalAttachment[];
  quoted_message_id?: number;
  is_note?: boolean;
  /** Apenas para render otimista da citação. */
  quoted_text?: string;
  quoted_sender_name?: string;
}

// ---------------------------------------------------------------------------
// Notas
// ---------------------------------------------------------------------------

export interface Note {
  id: number;
  content: string;
  is_private: boolean;
  author_name?: string | null;
  created_at: string;
}

// ---------------------------------------------------------------------------
// Realtime
// ---------------------------------------------------------------------------

export interface RealtimePollRequest {
  subscribed_conversations: number[];
  last_update_time: number;
  activity_type: 'active' | 'background';
}

export interface MessageStatusUpdate {
  message_id: number;
  status: MessageStatus;
}

export interface RealtimePollResponse {
  timestamp: number;
  new_messages: Message[];
  conversation_updates: ConversationUpdate[];
  new_conversations: Conversation[];
  message_status_updates: MessageStatusUpdate[];
}

// ---------------------------------------------------------------------------
// Notificações
// ---------------------------------------------------------------------------

export interface AppNotification {
  id: number;
  title: string;
  body: string;
  is_read: boolean;
  created_at: string;
  data?: {
    conversation_id?: number;
    [key: string]: unknown;
  } | null;
}

export interface UnreadNotificationsData {
  count: number;
  items: AppNotification[];
}

// ---------------------------------------------------------------------------
// Push / dispositivos
// ---------------------------------------------------------------------------

export interface RegisterDevicePayload {
  token: string;
  platform: 'ios' | 'android';
  device_name: string;
  app_version: string;
}

// ---------------------------------------------------------------------------
// Cloud (WhatsApp oficial)
// ---------------------------------------------------------------------------

export interface CloudWindow {
  is_cloud: boolean;
  within_window: boolean;
  expires_at: string | null;
}

export interface CloudTemplate {
  id?: number;
  name: string;
  language: string;
  category?: string | null;
  /** Corpo do template — placeholders no formato {{1}}, {{2}}... */
  body?: string | null;
}

export interface SendCloudTemplatePayload {
  template_name: string;
  language: string;
  params: string[];
}

// ---------------------------------------------------------------------------
// Auxiliares
// ---------------------------------------------------------------------------

export interface Agent {
  id: number;
  name: string;
  avatar?: string | null;
  email?: string | null;
}

export interface Department {
  id: number;
  name: string;
}

export interface Funnel {
  id: number;
  name: string;
}

export interface FunnelStage {
  id: number;
  name: string;
  color?: string | null;
  position?: number | null;
}

export interface WhatsAppAccount {
  id: number;
  name: string;
  phone?: string | null;
  type?: 'cloud' | 'qr' | string;
}

export interface CheckExistingData {
  exists: boolean;
  conversation_id?: number | null;
}

export interface SignedUrlData {
  url: string;
}
