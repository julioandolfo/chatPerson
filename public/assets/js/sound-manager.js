/**
 * Sound Manager - Sistema de Sons de Notificação
 * 
 * Gerencia a reprodução de sons para diferentes eventos do sistema.
 */

(function(window) {
    'use strict';

    const SoundManager = {
        // Configurações carregadas do servidor
        settings: null,
        
        // Elemento de áudio reutilizável
        audioElement: null,
        
        // Cache de áudio pré-carregado
        audioCache: {},
        
        // Flag para indicar se está inicializado
        initialized: false,
        
        // URL base para sons
        soundsBaseUrl: '/assets/sounds/',
        
        // Eventos disponíveis
        events: {
            NEW_CONVERSATION: 'new_conversation',
            NEW_MESSAGE: 'new_message',
            CONVERSATION_ASSIGNED: 'conversation_assigned',
            INVITE_RECEIVED: 'invite_received',
            SLA_WARNING: 'sla_warning',
            SLA_BREACHED: 'sla_breached',
            MENTION_RECEIVED: 'mention_received'
        },

        /**
         * Inicializar o Sound Manager
         */
        init: function() {
            if (this.initialized) {
                console.log('[SoundManager] Já inicializado');
                return;
            }
            
            console.log('[SoundManager] Inicializando...');
            
            // Criar elemento de áudio
            this.audioElement = document.createElement('audio');
            this.audioElement.id = 'global-sound-player';
            this.audioElement.preload = 'auto';
            document.body.appendChild(this.audioElement);
            
            // Carregar configurações
            this.loadSettings();
            
            // Configurar listeners de eventos do sistema
            this.setupEventListeners();
            
            this.initialized = true;
            console.log('[SoundManager] ✅ Inicializado com sucesso');
        },

        /**
         * Carregar configurações do servidor
         */
        loadSettings: function() {
            fetch('/settings/sounds', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.settings) {
                    this.settings = data.settings;
                    console.log('[SoundManager] ✅ Configurações carregadas:', this.settings);
                    
                    // Pré-carregar sons habilitados
                    this.preloadSounds();
                }
            })
            .catch(error => {
                console.error('[SoundManager] ❌ Erro ao carregar configurações:', error);
                // Usar configurações padrão
                this.settings = this.getDefaultSettings();
            });
        },

        /**
         * Recarregar configurações (chamado após salvar)
         */
        reloadSettings: function() {
            console.log('[SoundManager] Recarregando configurações...');
            this.loadSettings();
        },

        /**
         * Configurações padrão
         */
        getDefaultSettings: function() {
            return {
                sounds_enabled: 1,
                volume: 70,
                new_conversation_enabled: 1,
                new_conversation_sound: 'new-conversation.mp3',
                new_message_enabled: 1,
                new_message_sound: 'new-message.mp3',
                conversation_assigned_enabled: 1,
                conversation_assigned_sound: 'assigned.mp3',
                invite_received_enabled: 1,
                invite_received_sound: 'invite.mp3',
                sla_warning_enabled: 1,
                sla_warning_sound: 'sla-warning.mp3',
                sla_breached_enabled: 1,
                sla_breached_sound: 'sla-breached.mp3',
                mention_received_enabled: 1,
                mention_received_sound: 'mention.mp3',
                quiet_hours_enabled: 0,
                quiet_hours_start: '22:00:00',
                quiet_hours_end: '08:00:00'
            };
        },

        /**
         * Pré-carregar sons para reprodução mais rápida
         */
        preloadSounds: function() {
            if (!this.settings || !this.settings.sounds_enabled) return;
            
            const soundFields = [
                'new_conversation_sound',
                'new_message_sound',
                'conversation_assigned_sound',
                'invite_received_sound',
                'sla_warning_sound',
                'sla_breached_sound',
                'mention_received_sound'
            ];
            
            soundFields.forEach(field => {
                const soundFile = this.settings[field];
                if (soundFile && !this.audioCache[soundFile]) {
                    const audio = new Audio(this.soundsBaseUrl + soundFile);
                    audio.preload = 'auto';
                    this.audioCache[soundFile] = audio;
                }
            });
            
            console.log('[SoundManager] Sons pré-carregados:', Object.keys(this.audioCache).length);
        },

        /**
         * Configurar listeners para eventos do sistema
         */
        setupEventListeners: function() {
            // Listener para eventos do WebSocket/Realtime
            document.addEventListener('realtime:new_message', (e) => {
                // Só tocar som se a mensagem for de um contato (incoming)
                if (e.detail && e.detail.direction === 'incoming') {
                    this.play(this.events.NEW_MESSAGE);
                }
            });
            
            document.addEventListener('realtime:new_conversation', (e) => {
                this.play(this.events.NEW_CONVERSATION);
            });
            
            document.addEventListener('realtime:conversation_assigned', (e) => {
                this.play(this.events.CONVERSATION_ASSIGNED);
            });
            
            // Eventos de convite/menção
            document.addEventListener('realtime:new_mention', (e) => {
                this.play(this.events.INVITE_RECEIVED);
            });
            
            document.addEventListener('realtime:mention_received', (e) => {
                this.play(this.events.MENTION_RECEIVED);
            });
            
            // Eventos de SLA
            document.addEventListener('realtime:sla_warning', (e) => {
                this.play(this.events.SLA_WARNING);
            });
            
            document.addEventListener('realtime:sla_breached', (e) => {
                this.play(this.events.SLA_BREACHED);
            });
            
            console.log('[SoundManager] ✅ Event listeners configurados');
        },

        /**
         * Verificar se está em horário silencioso
         */
        isQuietHours: function() {
            if (!this.settings || !this.settings.quiet_hours_enabled) {
                return false;
            }
            
            const now = new Date();
            const currentTime = now.getHours().toString().padStart(2, '0') + ':' + 
                               now.getMinutes().toString().padStart(2, '0') + ':' +
                               now.getSeconds().toString().padStart(2, '0');
            
            const start = this.settings.quiet_hours_start;
            const end = this.settings.quiet_hours_end;
            
            // Horário pode cruzar meia-noite (ex: 22:00 - 08:00)
            if (start > end) {
                return currentTime >= start || currentTime <= end;
            } else {
                return currentTime >= start && currentTime <= end;
            }
        },

        /**
         * Verificar se um evento está habilitado
         */
        isEventEnabled: function(eventName) {
            if (!this.settings) return true; // Padrão: habilitado
            
            // Sons globais desabilitados
            if (!this.settings.sounds_enabled) return false;
            
            // Horário silencioso
            if (this.isQuietHours()) return false;
            
            // Evento específico
            const field = eventName + '_enabled';
            return this.settings[field] !== 0 && this.settings[field] !== '0';
        },

        /**
         * Obter arquivo de som para um evento
         */
        getSoundFile: function(eventName) {
            if (!this.settings) return null;
            
            const field = eventName + '_sound';
            return this.settings[field] || null;
        },

        /**
         * Obter volume configurado (0-1)
         */
        getVolume: function() {
            if (!this.settings) return 0.7;
            return (this.settings.volume || 70) / 100;
        },

        /**
         * Reproduzir som para um evento
         */
        play: function(eventName) {
            console.log('[SoundManager] Tentando tocar som:', eventName);
            
            // Verificar se está habilitado
            if (!this.isEventEnabled(eventName)) {
                console.log('[SoundManager] Som desabilitado para:', eventName);
                return false;
            }
            
            // Obter arquivo de som
            const soundFile = this.getSoundFile(eventName);
            if (!soundFile) {
                console.log('[SoundManager] Arquivo de som não encontrado para:', eventName);
                return false;
            }
            
            // Reproduzir
            return this.playFile(soundFile);
        },

        /**
         * Reproduzir um arquivo de som específico
         */
        playFile: function(filename) {
            try {
                const volume = this.getVolume();
                const url = this.soundsBaseUrl + filename;
                
                // Tentar usar áudio do cache
                let audio = this.audioCache[filename];
                
                if (audio) {
                    audio.volume = volume;
                    audio.currentTime = 0;
                    audio.play().catch(e => console.warn('[SoundManager] Erro ao tocar:', e));
                } else {
                    // Criar novo áudio
                    audio = new Audio(url);
                    audio.volume = volume;
                    audio.play().catch(e => console.warn('[SoundManager] Erro ao tocar:', e));
                    
                    // Adicionar ao cache
                    this.audioCache[filename] = audio;
                }
                
                console.log('[SoundManager] ✅ Tocando:', filename, 'Volume:', volume);
                return true;
            } catch (error) {
                console.error('[SoundManager] ❌ Erro ao reproduzir som:', error);
                return false;
            }
        },

        /**
         * Método público para tocar som de notificação genérico
         * Mantido para compatibilidade
         */
        playNotification: function() {
            this.play(this.events.NEW_MESSAGE);
        },

        /**
         * Parar todos os sons
         */
        stopAll: function() {
            Object.values(this.audioCache).forEach(audio => {
                audio.pause();
                audio.currentTime = 0;
            });
            
            if (this.audioElement) {
                this.audioElement.pause();
                this.audioElement.currentTime = 0;
            }
        },

        /**
         * Testar um som específico
         */
        test: function(soundFile) {
            this.playFile(soundFile);
        }
    };

    // Expor globalmente
    window.SoundManager = SoundManager;

    // Função de compatibilidade para código existente
    window.playNotificationSound = function() {
        SoundManager.play(SoundManager.events.NEW_MESSAGE);
    };

    // Inicializar quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            SoundManager.init();
        });
    } else {
        SoundManager.init();
    }

})(window);

