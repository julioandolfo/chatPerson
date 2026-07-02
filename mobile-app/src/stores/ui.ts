import { create } from 'zustand';

import type { Channel, ConversationFilter, ConversationStatus } from '@/types';

export interface AdvancedFilters {
  status: ConversationStatus | null;
  channel: Channel | null;
  department_id: number | null;
  funnel_id: number | null;
}

export const EMPTY_ADVANCED_FILTERS: AdvancedFilters = {
  status: null,
  channel: null,
  department_id: null,
  funnel_id: null,
};

interface UiState {
  /** Aba da lista de conversas: Minhas / Não atribuídas / Todas. */
  filter: ConversationFilter;
  /** Busca (já com debounce aplicado pela tela). */
  search: string;
  advanced: AdvancedFilters;
  /** Conversa aberta no momento (para subscribe do realtime). */
  activeConversationId: number | null;

  setFilter: (filter: ConversationFilter) => void;
  setSearch: (search: string) => void;
  setAdvancedFilters: (filters: AdvancedFilters) => void;
  clearAdvancedFilters: () => void;
  setActiveConversationId: (id: number | null) => void;
}

export const useUiStore = create<UiState>()((set) => ({
  filter: 'mine',
  search: '',
  advanced: EMPTY_ADVANCED_FILTERS,
  activeConversationId: null,

  setFilter: (filter) => set({ filter }),
  setSearch: (search) => set({ search }),
  setAdvancedFilters: (advanced) => set({ advanced }),
  clearAdvancedFilters: () => set({ advanced: EMPTY_ADVANCED_FILTERS }),
  setActiveConversationId: (activeConversationId) => set({ activeConversationId }),
}));

export function hasActiveAdvancedFilters(advanced: AdvancedFilters): boolean {
  return (
    advanced.status !== null ||
    advanced.channel !== null ||
    advanced.department_id !== null ||
    advanced.funnel_id !== null
  );
}
