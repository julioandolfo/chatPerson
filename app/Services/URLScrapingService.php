<?php
/**
 * Service URLScrapingService
 * Web scraping e processamento de URLs para adicionar à knowledge base
 */

namespace App\Services;

use App\Models\AIUrlScraping;
use App\Models\AIKnowledgeBase;
use App\Services\RAGService;
use App\Services\EmbeddingService;
use App\Helpers\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;

class URLScrapingService
{
    const MAX_DEPTH = 3; // Profundidade máxima de crawling
    const MAX_URLS_PER_DOMAIN = 500; // Limite de URLs por domínio
    const REQUEST_TIMEOUT = 30; // Timeout em segundos
    const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * Processar URL (web scraping e adicionar à KB)
     */
    public static function processUrl(int $scrapingId): bool
    {
        try {
            $scraping = AIUrlScraping::find($scrapingId);
            if (!$scraping) {
                throw new \Exception("URL scraping não encontrado: {$scrapingId}");
            }

            // Marcar como processando
            AIUrlScraping::markAsProcessing($scrapingId);

            // Fazer scraping da URL
            $content = self::scrapeUrl($scraping['url']);
            
            if (empty($content['text'])) {
                throw new \Exception("Nenhum conteúdo encontrado na URL");
            }

            // Dividir em chunks
            $chunks = self::chunkContent($content['text'], $content['title'] ?? null);

            // Gerar embeddings e salvar na KB
            $chunksCreated = 0;
            foreach ($chunks as $index => $chunk) {
                try {
                    RAGService::addKnowledge(
                        $scraping['ai_agent_id'],
                        $chunk['content'],
                        'url',
                        [
                            'source_url' => $scraping['url'],
                            'chunk_index' => $index,
                            'title' => $chunk['title']
                        ],
                        $chunk['title'],
                        $scraping['url']
                    );
                    $chunksCreated++;
                } catch (\Exception $e) {
                    Logger::error("URLScrapingService::processUrl - Erro ao adicionar chunk: " . $e->getMessage());
                }
            }

            // Atualizar scraping
            AIUrlScraping::update($scrapingId, [
                'title' => $content['title'],
                'content' => $content['text'],
                'chunks_created' => $chunksCreated
            ]);

            // Marcar como concluído
            AIUrlScraping::markAsCompleted($scrapingId, $chunksCreated);

            Logger::info("URLScrapingService::processUrl - URL processada: {$scraping['url']}, {$chunksCreated} chunks criados");

            return true;

        } catch (\Exception $e) {
            Logger::error("URLScrapingService::processUrl - Erro: " . $e->getMessage());
            
            if (isset($scrapingId)) {
                AIUrlScraping::markAsFailed($scrapingId, $e->getMessage());
            }
            
            return false;
        }
    }

    /**
     * Descobrir e adicionar todas as URLs de um site (crawling)
     * 
     * @param int $agentId ID do agente
     * @param string $baseUrl URL base do site
     * @param array $options Opções de crawling
     * @return array Array com URLs descobertas e adicionadas
     */
    public static function discoverAndAddUrls(int $agentId, string $baseUrl, array $options = []): array
    {
        $maxDepth = $options['max_depth'] ?? self::MAX_DEPTH;
        $maxUrls = $options['max_urls'] ?? self::MAX_URLS_PER_DOMAIN;
        $allowedPaths = $options['allowed_paths'] ?? []; // Ex: ['/produto/', '/categoria/']
        $excludedPaths = $options['excluded_paths'] ?? []; // Ex: ['/admin/', '/checkout/']
        
        try {
            $parsedUrl = parse_url($baseUrl);
            $baseDomain = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
            
            $discoveredUrls = [];
            $visitedUrls = [];
            $urlsToVisit = [[$baseUrl, 0]]; // [url, depth]
            
            $client = new Client([
                'timeout' => self::REQUEST_TIMEOUT,
                'verify' => false, // Desabilitar verificação SSL para desenvolvimento
                'headers' => [
                    'User-Agent' => self::USER_AGENT
                ]
            ]);

            Logger::info("URLScrapingService::discoverAndAddUrls - Iniciando crawling de {$baseUrl}");

            while (!empty($urlsToVisit) && count($discoveredUrls) < $maxUrls) {
                list($currentUrl, $depth) = array_shift($urlsToVisit);
                
                // Verificar profundidade
                if ($depth > $maxDepth) {
                    continue;
                }

                // Verificar se já visitamos
                if (isset($visitedUrls[$currentUrl])) {
                    continue;
                }

                // Verificar se URL pertence ao mesmo domínio
                $currentParsed = parse_url($currentUrl);
                if (!isset($currentParsed['host']) || $currentParsed['host'] !== $parsedUrl['host']) {
                    continue;
                }

                // Verificar paths permitidos/excluídos
                $path = $currentParsed['path'] ?? '/';
                if (!empty($allowedPaths)) {
                    $allowed = false;
                    foreach ($allowedPaths as $allowedPath) {
                        if (strpos($path, $allowedPath) !== false) {
                            $allowed = true;
                            break;
                        }
                    }
                    if (!$allowed) {
                        continue;
                    }
                }

                if (!empty($excludedPaths)) {
                    $excluded = false;
                    foreach ($excludedPaths as $excludedPath) {
                        if (strpos($path, $excludedPath) !== false) {
                            $excluded = true;
                            break;
                        }
                    }
                    if ($excluded) {
                        continue;
                    }
                }

                try {
                    // Fazer requisição
                    $response = $client->get($currentUrl);
                    $html = $response->getBody()->getContents();
                    
                    $visitedUrls[$currentUrl] = true;
                    
                    // Extrair links
                    $crawler = new Crawler($html, $currentUrl);
                    $links = $crawler->filter('a[href]')->extract(['href']);
                    
                    // Processar links encontrados
                    foreach ($links as $link) {
                        // Converter link relativo para absoluto
                        $absoluteUrl = self::resolveUrl($currentUrl, $link);
                        
                        if (!$absoluteUrl) {
                            continue;
                        }

                        // Verificar se é do mesmo domínio
                        $linkParsed = parse_url($absoluteUrl);
                        if (!isset($linkParsed['host']) || $linkParsed['host'] !== $parsedUrl['host']) {
                            continue;
                        }

                        // Remover fragmentos e query strings para comparação
                        $cleanUrl = $linkParsed['scheme'] . '://' . $linkParsed['host'] . ($linkParsed['path'] ?? '/');
                        
                        if (!isset($visitedUrls[$cleanUrl]) && !in_array([$cleanUrl, $depth + 1], $urlsToVisit)) {
                            $urlsToVisit[] = [$cleanUrl, $depth + 1];
                        }
                    }

                    // Adicionar URL à lista de descobertas
                    $discoveredUrls[] = $currentUrl;
                    
                    // Adicionar ao banco se não existir
                    if (!AIUrlScraping::urlExists($agentId, $currentUrl)) {
                        AIUrlScraping::create([
                            'ai_agent_id' => $agentId,
                            'url' => $currentUrl,
                            'status' => 'pending',
                            'metadata' => json_encode([
                                'discovered_via' => 'crawling',
                                'base_url' => $baseUrl,
                                'depth' => $depth
                            ], JSON_UNESCAPED_UNICODE)
                        ]);
                    }

                    Logger::info("URLScrapingService::discoverAndAddUrls - URL descoberta: {$currentUrl} (profundidade: {$depth})");

                } catch (RequestException $e) {
                    Logger::warning("URLScrapingService::discoverAndAddUrls - Erro ao acessar {$currentUrl}: " . $e->getMessage());
                    continue;
                } catch (\Exception $e) {
                    Logger::warning("URLScrapingService::discoverAndAddUrls - Erro ao processar {$currentUrl}: " . $e->getMessage());
                    continue;
                }
            }

            Logger::info("URLScrapingService::discoverAndAddUrls - Crawling concluído: " . count($discoveredUrls) . " URLs descobertas");

            return [
                'success' => true,
                'urls_discovered' => count($discoveredUrls),
                'urls' => $discoveredUrls
            ];

        } catch (\Exception $e) {
            Logger::error("URLScrapingService::discoverAndAddUrls - Erro: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'urls_discovered' => 0,
                'urls' => []
            ];
        }
    }

    /**
     * Fazer scraping de uma URL específica
     */
    private static function scrapeUrl(string $url): array
    {
        $client = new Client([
            'timeout' => self::REQUEST_TIMEOUT,
            'verify' => false,
            'headers' => [
                'User-Agent' => self::USER_AGENT
            ]
        ]);

        try {
            $response = $client->get($url);
            $html = $response->getBody()->getContents();

            $crawler = new Crawler($html, $url);

            // Extrair título
            $title = null;
            try {
                $title = $crawler->filter('title')->first()->text();
            } catch (\Exception $e) {
                try {
                    $title = $crawler->filter('h1')->first()->text();
                } catch (\Exception $e2) {
                    $title = 'Sem título';
                }
            }

            // Remover scripts, styles, etc
            $crawler->filter('script, style, noscript, iframe')->each(function (Crawler $node) {
                $node->getNode(0)->parentNode->removeChild($node->getNode(0));
            });

            // Extrair texto principal
            $text = '';
            
            // Tentar encontrar conteúdo principal (article, main, .content, etc)
            $selectors = ['article', 'main', '.content', '.post-content', '.product-description', '#content'];
            $contentFound = false;
            
            foreach ($selectors as $selector) {
                try {
                    $contentNode = $crawler->filter($selector)->first();
                    if ($contentNode->count() > 0) {
                        $text = $contentNode->text();
                        $contentFound = true;
                        break;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Se não encontrou conteúdo específico, pegar body inteiro
            if (!$contentFound) {
                try {
                    $text = $crawler->filter('body')->text();
                } catch (\Exception $e) {
                    $text = strip_tags($html);
                }
            }

            // Limpar texto
            $text = self::cleanText($text);

            return [
                'title' => trim($title),
                'text' => $text,
                'url' => $url
            ];

        } catch (RequestException $e) {
            throw new \Exception("Erro ao acessar URL: " . $e->getMessage());
        }
    }

    /**
     * Dividir conteúdo em chunks
     */
    public static function chunkContent(string $content, ?string $title = null, int $maxTokens = 1000): array
    {
        // Estimativa: ~4 caracteres por token
        $maxChars = $maxTokens * 4;
        
        $chunks = [];
        $paragraphs = preg_split('/\n\s*\n/', $content);
        
        $currentChunk = '';
        $chunkIndex = 0;

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) {
                continue;
            }

            // Se adicionar este parágrafo ultrapassar o limite, salvar chunk atual
            if (strlen($currentChunk) + strlen($paragraph) > $maxChars && !empty($currentChunk)) {
                $chunks[] = [
                    'title' => $title . (count($chunks) > 0 ? ' - Parte ' . ($chunkIndex + 1) : ''),
                    'content' => trim($currentChunk),
                    'chunk_index' => $chunkIndex
                ];
                $currentChunk = '';
                $chunkIndex++;
            }

            $currentChunk .= $paragraph . "\n\n";
        }

        // Adicionar último chunk se houver conteúdo
        if (!empty(trim($currentChunk))) {
            $chunks[] = [
                'title' => $title . (count($chunks) > 0 ? ' - Parte ' . ($chunkIndex + 1) : ''),
                'content' => trim($currentChunk),
                'chunk_index' => $chunkIndex
            ];
        }

        // Se não dividiu em chunks, criar um único chunk
        if (empty($chunks)) {
            $chunks[] = [
                'title' => $title ?? 'Conteúdo',
                'content' => $content,
                'chunk_index' => 0
            ];
        }

        return $chunks;
    }

    /**
     * Limpar texto extraído
     */
    private static function cleanText(string $text): string
    {
        // Remover espaços múltiplos
        $text = preg_replace('/\s+/', ' ', $text);
        // Remover quebras de linha excessivas
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        // Trim
        $text = trim($text);
        return $text;
    }

    /**
     * Resolver URL relativa para absoluta
     */
    private static function resolveUrl(string $baseUrl, string $relativeUrl): ?string
    {
        // Se já é absoluta, retornar
        if (preg_match('/^https?:\/\//', $relativeUrl)) {
            return $relativeUrl;
        }

        // Remover fragmentos
        $relativeUrl = preg_replace('/#.*$/', '', $relativeUrl);
        
        // Se vazia ou apenas fragmento, retornar null
        if (empty($relativeUrl) || $relativeUrl === '#') {
            return null;
        }

        $baseParsed = parse_url($baseUrl);
        
        // Se começa com /, é relativa ao root
        if (strpos($relativeUrl, '/') === 0) {
            return $baseParsed['scheme'] . '://' . $baseParsed['host'] . $relativeUrl;
        }

        // Caso contrário, relativa ao path atual
        $basePath = $baseParsed['path'] ?? '/';
        $basePath = rtrim($basePath, '/');
        $basePath = dirname($basePath);
        if ($basePath === '.') {
            $basePath = '/';
        }
        
        return $baseParsed['scheme'] . '://' . $baseParsed['host'] . $basePath . '/' . $relativeUrl;
    }
}

