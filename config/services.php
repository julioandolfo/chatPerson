<?php
/**
 * Configuração de serviços externos
 * 
 * APIs de terceiros e integrações
 */

return [
    
    /**
     * Google Places API
     * 
     * Para buscar empresas no Google Maps
     * Console: https://console.cloud.google.com/apis/credentials
     * Ativar: Places API, Geocoding API
     * 
     * Custo aproximado:
     * - Nearby Search: $0.032 por requisição
     * - Place Details: $0.017 por requisição
     * - Geocoding: $0.005 por requisição
     */
    'google_places' => [
        'api_key' => $_ENV['GOOGLE_PLACES_API_KEY'] ?? getenv('GOOGLE_PLACES_API_KEY') ?: '',
        'default_language' => 'pt-BR',
        'default_region' => 'br',
    ],
    
    /**
     * Outscraper API
     * 
     * Alternativa ao Google Places para scraping em volume
     * Site: https://outscraper.com
     * 
     * Custo aproximado:
     * - Google Maps Search: $0.002 por resultado
     */
    'outscraper' => [
        'api_key' => $_ENV['OUTSCRAPER_API_KEY'] ?? getenv('OUTSCRAPER_API_KEY') ?: '',
    ],
    
    /**
     * OpenAI API
     * 
     * Para Agentes de IA, embeddings, etc.
     */
    'openai' => [
        'api_key' => $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: '',
        'default_model' => 'gpt-4o-mini',
        'embedding_model' => 'text-embedding-3-small',
    ],
    
    /**
     * Quepasa API
     * 
     * Integração WhatsApp via Quepasa
     */
    'quepasa' => [
        'api_url' => $_ENV['QUEPASA_API_URL'] ?? getenv('QUEPASA_API_URL') ?: '',
    ],
    
    /**
     * Evolution API
     * 
     * Integração WhatsApp via Evolution
     */
    'evolution' => [
        'api_url' => $_ENV['EVOLUTION_API_URL'] ?? getenv('EVOLUTION_API_URL') ?: '',
        'api_key' => $_ENV['EVOLUTION_API_KEY'] ?? getenv('EVOLUTION_API_KEY') ?: '',
    ],
    
];
