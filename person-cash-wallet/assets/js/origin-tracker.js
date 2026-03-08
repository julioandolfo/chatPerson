/**
 * Growly Digital - Origin Tracker
 * 
 * Captura e persiste dados de origem dos visitantes:
 * - UTM parameters
 * - Click IDs (gclid, fbclid, etc)
 * - Referrer
 * - Device info
 * 
 * @package PersonCashWallet
 * @since 1.4.0
 */
(function($) {
    'use strict';

    var PCWOriginTracker = {
        
        // Configurações
        config: {
            cookiePrefix: 'pcw_',
            firstTouchCookie: 'pcw_first_touch',
            lastTouchCookie: 'pcw_last_touch',
            sessionCookie: 'pcw_origin_session',
            cookieDays: 90
        },

        /**
         * Inicializar tracker
         */
        init: function() {
            // Usar configurações do PHP se disponíveis
            if (typeof pcwOrigin !== 'undefined') {
                this.config.cookieDays = pcwOrigin.cookieDays || 90;
            }

            // Capturar origem
            this.captureOrigin();

            // Enviar dados adicionais do cliente via AJAX
            this.sendClientData();

            // Adicionar listeners
            this.setupEventListeners();
        },

        /**
         * Capturar dados de origem
         */
        captureOrigin: function() {
            var originData = this.collectOriginData();

            // Verificar se tem dados significativos
            var hasOrigin = originData.utm_source || 
                           originData.click_id || 
                           originData.referrer_domain ||
                           originData.referral_code;

            // First touch - salvar apenas se não existir
            if (!this.getCookie(this.config.firstTouchCookie) && hasOrigin) {
                this.saveFirstTouch(originData);
            }

            // Last touch - atualizar sempre que tiver origem significativa
            if (hasOrigin) {
                this.saveLastTouch(originData);
            }
        },

        /**
         * Coletar dados de origem
         */
        collectOriginData: function() {
            var urlParams = this.getUrlParams();
            var referrer = this.getReferrer();

            var data = {
                timestamp: new Date().toISOString(),
                page_url: window.location.href,
                page_path: window.location.pathname,
                referrer: referrer.url,
                referrer_domain: referrer.domain,
                
                // UTM params
                utm_source: urlParams.utm_source || '',
                utm_medium: urlParams.utm_medium || '',
                utm_campaign: urlParams.utm_campaign || '',
                utm_term: urlParams.utm_term || '',
                utm_content: urlParams.utm_content || '',
                utm_id: urlParams.utm_id || '',

                // Click IDs
                click_id: '',
                click_id_type: '',

                // Referral
                referral_code: urlParams.ref || '',

                // Email tracking
                email_tracking: urlParams.pcw_track || '',
                automation_id: urlParams.pcw_auto || 0,
                campaign_id: urlParams.pcw_camp || 0,

                // Device
                device_type: this.getDeviceType(),
                screen_width: window.screen.width,
                screen_height: window.screen.height,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                language: navigator.language || navigator.userLanguage
            };

            // Detectar click IDs
            var clickIds = ['gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'twclid', 'ttclid', 'li_fat_id', 'mc_cid', '_kx'];
            for (var i = 0; i < clickIds.length; i++) {
                var clickId = clickIds[i];
                if (urlParams[clickId]) {
                    data.click_id = urlParams[clickId];
                    data.click_id_type = clickId;
                    break;
                }
            }

            // Inferir source/medium se não definidos
            if (!data.utm_source) {
                var inferred = this.inferSourceMedium(data);
                data.utm_source = inferred.source;
                data.utm_medium = inferred.medium;
            }

            // Determinar canal
            data.channel = this.determineChannel(data);

            return data;
        },

        /**
         * Obter parâmetros da URL
         */
        getUrlParams: function() {
            var params = {};
            var search = window.location.search.substring(1);
            
            if (!search) {
                return params;
            }

            var pairs = search.split('&');
            for (var i = 0; i < pairs.length; i++) {
                var pair = pairs[i].split('=');
                var key = decodeURIComponent(pair[0]);
                var value = pair[1] ? decodeURIComponent(pair[1].replace(/\+/g, ' ')) : '';
                params[key] = value;
            }

            return params;
        },

        /**
         * Obter referrer
         */
        getReferrer: function() {
            var referrer = document.referrer;
            var result = { url: '', domain: '' };

            if (!referrer) {
                return result;
            }

            // Ignorar referrer do próprio site
            try {
                var refUrl = new URL(referrer);
                var currentHost = window.location.hostname;

                if (refUrl.hostname === currentHost) {
                    return result;
                }

                result.url = referrer;
                result.domain = refUrl.hostname.replace(/^www\./, '');
            } catch (e) {
                // URL inválida
            }

            return result;
        },

        /**
         * Inferir source/medium
         */
        inferSourceMedium: function(data) {
            var source = '';
            var medium = '';

            // Click ID
            if (data.click_id_type) {
                var platformMap = {
                    'gclid': ['google', 'cpc'],
                    'gbraid': ['google', 'cpc'],
                    'wbraid': ['google', 'cpc'],
                    'fbclid': ['facebook', 'cpc'],
                    'msclkid': ['bing', 'cpc'],
                    'twclid': ['twitter', 'cpc'],
                    'ttclid': ['tiktok', 'cpc'],
                    'li_fat_id': ['linkedin', 'cpc'],
                    'mc_cid': ['mailchimp', 'email'],
                    '_kx': ['klaviyo', 'email']
                };

                if (platformMap[data.click_id_type]) {
                    return {
                        source: platformMap[data.click_id_type][0],
                        medium: platformMap[data.click_id_type][1]
                    };
                }
            }

            // Email tracking
            if (data.email_tracking || data.automation_id || data.campaign_id) {
                return { source: 'pcw_email', medium: 'email' };
            }

            // Referral code
            if (data.referral_code) {
                return { source: 'referral', medium: 'referral' };
            }

            // Inferir do referrer
            if (data.referrer_domain) {
                var domain = data.referrer_domain.toLowerCase();

                // Redes sociais
                var socialDomains = {
                    'facebook.com': 'facebook',
                    'fb.com': 'facebook',
                    'm.facebook.com': 'facebook',
                    'l.facebook.com': 'facebook',
                    'instagram.com': 'instagram',
                    'twitter.com': 'twitter',
                    't.co': 'twitter',
                    'linkedin.com': 'linkedin',
                    'youtube.com': 'youtube',
                    'youtu.be': 'youtube',
                    'pinterest.com': 'pinterest',
                    'tiktok.com': 'tiktok',
                    'reddit.com': 'reddit'
                };

                for (var socialDomain in socialDomains) {
                    if (domain.indexOf(socialDomain) !== -1) {
                        return { source: socialDomains[socialDomain], medium: 'social' };
                    }
                }

                // Buscadores
                var searchDomains = {
                    'google.': 'google',
                    'bing.com': 'bing',
                    'yahoo.': 'yahoo',
                    'duckduckgo.com': 'duckduckgo'
                };

                for (var searchDomain in searchDomains) {
                    if (domain.indexOf(searchDomain) !== -1) {
                        return { source: searchDomains[searchDomain], medium: 'organic' };
                    }
                }

                // Outro site
                return { source: data.referrer_domain, medium: 'referral' };
            }

            // Acesso direto
            return { source: '(direct)', medium: '(none)' };
        },

        /**
         * Determinar canal
         */
        determineChannel: function(data) {
            var source = (data.utm_source || '').toLowerCase();
            var medium = (data.utm_medium || '').toLowerCase();

            if (source === '(direct)') return 'direct';
            if (['cpc', 'ppc', 'paidsearch'].indexOf(medium) !== -1) return 'paid_search';
            if (medium === 'email') return 'email';
            if (medium === 'social' || ['facebook', 'instagram', 'twitter', 'linkedin', 'youtube', 'tiktok'].indexOf(source) !== -1) {
                return medium === 'cpc' ? 'paid_social' : 'organic_social';
            }
            if (medium === 'referral' || data.referral_code) return 'referral';
            if (medium === 'organic' || ['google', 'bing', 'yahoo', 'duckduckgo'].indexOf(source) !== -1) return 'organic_search';
            if (['display', 'cpm', 'banner'].indexOf(medium) !== -1) return 'display';

            return 'other';
        },

        /**
         * Obter tipo de dispositivo
         */
        getDeviceType: function() {
            var ua = navigator.userAgent.toLowerCase();

            if (/mobile|android|iphone|ipod|blackberry|opera mini|windows phone/i.test(ua)) {
                return 'mobile';
            }
            if (/tablet|ipad|playbook|silk/i.test(ua)) {
                return 'tablet';
            }
            return 'desktop';
        },

        /**
         * Salvar first touch
         */
        saveFirstTouch: function(data) {
            var cookieData = this.prepareCookieData(data);
            this.setCookie(this.config.firstTouchCookie, cookieData, this.config.cookieDays);
        },

        /**
         * Salvar last touch
         */
        saveLastTouch: function(data) {
            var cookieData = this.prepareCookieData(data);
            this.setCookie(this.config.lastTouchCookie, cookieData, 30); // 30 dias para last touch
        },

        /**
         * Preparar dados para cookie
         */
        prepareCookieData: function(data) {
            return {
                ts: data.timestamp,
                src: data.utm_source || '',
                med: data.utm_medium || '',
                cmp: data.utm_campaign || '',
                trm: data.utm_term || '',
                cnt: data.utm_content || '',
                cid: data.click_id || '',
                cit: data.click_id_type || '',
                ref: data.referrer_domain || '',
                rfc: data.referral_code || '',
                lp: data.page_path || '',
                ch: data.channel || '',
                aid: data.automation_id || 0,
                cpid: data.campaign_id || 0
            };
        },

        /**
         * Enviar dados do cliente via AJAX
         */
        sendClientData: function() {
            if (typeof pcwOrigin === 'undefined') {
                return;
            }

            var clientData = {
                action: 'pcw_track_origin',
                nonce: pcwOrigin.nonce,
                screen_width: window.screen.width,
                screen_height: window.screen.height,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                language: navigator.language || navigator.userLanguage
            };

            $.ajax({
                url: pcwOrigin.ajaxUrl,
                type: 'POST',
                data: clientData,
                timeout: 5000
            });
        },

        /**
         * Configurar event listeners
         */
        setupEventListeners: function() {
            var self = this;

            // Capturar origem quando usuário entra no checkout
            $(document).on('updated_checkout', function() {
                // Atualizar last touch se houver novos parâmetros
                var urlParams = self.getUrlParams();
                if (urlParams.utm_source || urlParams.gclid || urlParams.fbclid) {
                    self.captureOrigin();
                }
            });

            // Adicionar dados de origem ao formulário de checkout
            $('form.checkout').on('checkout_place_order', function() {
                var firstTouch = self.getCookie(self.config.firstTouchCookie);
                var lastTouch = self.getCookie(self.config.lastTouchCookie);

                if (firstTouch) {
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'pcw_first_touch',
                        value: JSON.stringify(firstTouch)
                    }).appendTo('form.checkout');
                }

                if (lastTouch) {
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'pcw_last_touch',
                        value: JSON.stringify(lastTouch)
                    }).appendTo('form.checkout');
                }
            });
        },

        /**
         * Set cookie
         */
        setCookie: function(name, value, days) {
            var expires = '';
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = '; expires=' + date.toUTCString();
            }

            var cookieValue = btoa(JSON.stringify(value));
            document.cookie = name + '=' + cookieValue + expires + '; path=/; SameSite=Lax';
        },

        /**
         * Get cookie
         */
        getCookie: function(name) {
            var nameEQ = name + '=';
            var ca = document.cookie.split(';');

            for (var i = 0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) === ' ') {
                    c = c.substring(1, c.length);
                }
                if (c.indexOf(nameEQ) === 0) {
                    try {
                        var value = c.substring(nameEQ.length, c.length);
                        return JSON.parse(atob(value));
                    } catch (e) {
                        return null;
                    }
                }
            }
            return null;
        },

        /**
         * Obter first touch data
         */
        getFirstTouch: function() {
            return this.getCookie(this.config.firstTouchCookie);
        },

        /**
         * Obter last touch data
         */
        getLastTouch: function() {
            return this.getCookie(this.config.lastTouchCookie);
        },

        /**
         * Obter dados formatados para exibição
         */
        getAttributionSummary: function() {
            var firstTouch = this.getFirstTouch();
            var lastTouch = this.getLastTouch();

            return {
                firstTouch: firstTouch ? {
                    source: firstTouch.src,
                    medium: firstTouch.med,
                    campaign: firstTouch.cmp,
                    channel: firstTouch.ch,
                    referrer: firstTouch.ref,
                    referralCode: firstTouch.rfc,
                    timestamp: firstTouch.ts
                } : null,
                lastTouch: lastTouch ? {
                    source: lastTouch.src,
                    medium: lastTouch.med,
                    campaign: lastTouch.cmp,
                    channel: lastTouch.ch,
                    referrer: lastTouch.ref,
                    referralCode: lastTouch.rfc,
                    timestamp: lastTouch.ts
                } : null
            };
        }
    };

    // Inicializar quando DOM estiver pronto
    $(document).ready(function() {
        PCWOriginTracker.init();
    });

    // Expor globalmente para debug
    window.PCWOriginTracker = PCWOriginTracker;

})(jQuery);
