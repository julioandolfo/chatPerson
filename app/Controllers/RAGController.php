<?php
/**
 * Controller RAGController
 * Gerenciamento completo do sistema RAG (Knowledge Base, Feedback Loop, URLs, Memórias)
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\RAGService;
use App\Services\EmbeddingService;
use App\Services\URLScrapingService;
use App\Services\AgentMemoryService;
use App\Jobs\ProcessURLScrapingJob;
use App\Models\AIKnowledgeBase;
use App\Models\AIFeedbackLoop;
use App\Models\AIUrlScraping;
use App\Models\AIAgentMemory;
use App\Models\AIAgent;
use App\Models\Conversation;
use App\Models\Message;

class RAGController
{
    /**
     * Página principal do RAG (Knowledge Base)
     */
    public function knowledgeBase(int $agentId): void
    {
        Permission::abortIfCannot('ai_agents.view');
        
        $agent = AIAgent::find($agentId);
        if (!$agent) {
            Response::redirect('/ai-agents');
            return;
        }
        
        $page = (int)Request::get('page', 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        try {
            $knowledge = AIKnowledgeBase::getByAgent($agentId, $limit);
            $total = AIKnowledgeBase::countByAgent($agentId);
            
            Response::view('rag/knowledge-base', [
                'agent' => $agent,
                'knowledge' => $knowledge,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => ceil($total / $limit)
            ]);
        } catch (\Exception $e) {
            Response::view('rag/knowledge-base', [
                'agent' => $agent,
                'knowledge' => [],
                'total' => 0,
                'page' => 1,
                'limit' => $limit,
                'totalPages' => 0,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Adicionar conhecimento
     */
    public function addKnowledge(int $agentId): void
    {
        Permission::abortIfCannot('ai_agents.edit');
        
        $data = Request::post();
        
        try {
            $knowledgeId = RAGService::addKnowledge(
                $agentId,
                $data['content'] ?? '',
                $data['content_type'] ?? 'manual',
                json_decode($data['metadata'] ?? '{}', true),
                $data['title'] ?? null,
                $data['source_url'] ?? null
            );
            
            Response::json([
                'success' => true,
                'message' => 'Conhecimento adicionado com sucesso!',
                'id' => $knowledgeId
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao adicionar conhecimento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar conhecimentos (busca semântica)
     */
    public function searchKnowledge(int $agentId): void
    {
        Permission::abortIfCannot('ai_agents.view');
        
        $query = Request::get('query', '');
        $limit = (int)Request::get('limit', 5);
        
        if (empty($query)) {
            Response::json(['success' => false, 'message' => 'Query vazia'], 400);
            return;
        }
        
        try {
            $results = RAGService::searchRelevantContext($agentId, $query, $limit);
            
            Response::json([
                'success' => true,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro na busca: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deletar conhecimento
     */
    public function deleteKnowledge(int $agentId, int $knowledgeId): void
    {
        Permission::abortIfCannot('ai_agents.edit');
        
        try {
            $knowledge = AIKnowledgeBase::find($knowledgeId);
            if (!$knowledge || $knowledge['ai_agent_id'] != $agentId) {
                Response::json(['success' => false, 'message' => 'Conhecimento não encontrado'], 404);
                return;
            }
            
            AIKnowledgeBase::delete($knowledgeId);
            
            Response::json([
                'success' => true,
                'message' => 'Conhecimento removido com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao remover conhecimento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Página de Feedback Loop
     */
    public function feedbackLoop(int $agentId): void
    {
        Permission::abortIfCannot('ai_agents.view');
        
        $agent = AIAgent::find($agentId);
        if (!$agent) {
            Response::redirect('/ai-agents');
            return;
        }
        
        $status = Request::get('status', 'pending');
        $page = (int)Request::get('page', 1);
        $limit = 50;
        
        try {
            if ($status === 'pending') {
                $feedbacks = AIFeedbackLoop::getPending($agentId, $limit);
            } else {
                $feedbacks = AIFeedbackLoop::getByAgent($agentId, $limit);
                $feedbacks = array_filter($feedbacks, fn($f) => $f['status'] === $status);
            }
            
            $pendingCount = AIFeedbackLoop::countPending($agentId);
            
            Response::view('rag/feedback-loop', [
                'agent' => $agent,
                'feedbacks' => $feedbacks,
                'status' => $status,
                'pendingCount' => $pendingCount,
                'page' => $page
            ]);
        } catch (\Exception $e) {
            Response::view('rag/feedback-loop', [
                'agent' => $agent,
                'feedbacks' => [],
                'status' => $status,
                'pendingCount' => 0,
                'page' => 1,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Revisar feedback
     */
    public function reviewFeedback(int $agentId, int $feedbackId): void
    {
        Permission::abortIfCannot('ai_agents.edit');
        
        $data = Request::post();
        $userId = \App\Helpers\Auth::user()['id'] ?? 0;
        
        try {
            $feedback = AIFeedbackLoop::find($feedbackId);
            if (!$feedback || $feedback['ai_agent_id'] != $agentId) {
                Response::json(['success' => false, 'message' => 'Feedback não encontrado'], 404);
                return;
            }
            
            $addToKB = isset($data['add_to_kb']) && $data['add_to_kb'] === '1';
            $knowledgeBaseId = null;
            
            // Se deve adicionar à KB, criar conhecimento
            if ($addToKB && !empty($data['correct_answer'])) {
                $knowledgeBaseId = RAGService::addKnowledge(
                    $agentId,
                    $data['correct_answer'],
                    'feedback_review',
                    [
                        'original_question' => $feedback['user_question'],
                        'ai_response' => $feedback['ai_response'],
                        'reviewed_by' => $userId
                    ],
                    'Resposta revisada: ' . substr($feedback['user_question'], 0, 50)
                );
            }
            
            // Marcar como revisado
            AIFeedbackLoop::markAsReviewed(
                $feedbackId,
                $userId,
                $data['correct_answer'] ?? '',
                $addToKB,
                $knowledgeBaseId
            );
            
            Response::json([
                'success' => true,
                'message' => 'Feedback revisado com sucesso!',
                'added_to_kb' => $addToKB
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao revisar feedback: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ignorar feedback
     */
    public function ignoreFeedback(int $agentId, int $feedbackId): void
    {
        Permission::abortIfCannot('ai_agents.edit');
        
        try {
            $feedback = AIFeedbackLoop::find($feedbackId);
            if (!$feedback || $feedback['ai_agent_id'] != $agentId) {
                Response::json(['success' => false, 'message' => 'Feedback não encontrado'], 404);
                return;
            }
            
            AIFeedbackLoop::markAsIgnored($feedbackId);
            
            Response::json([
                'success' => true,
                'message' => 'Feedback ignorado'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao ignorar feedback: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Página de URLs
     */
    public function urls(int $agentId): void
    {
        Permission::abortIfCannot('ai_agents.view');
        
        $agent = AIAgent::find($agentId);
        if (!$agent) {
            Response::redirect('/ai-agents');
            return;
        }
        
        $status = Request::get('status', 'all');
        
        try {
            if ($status === 'all') {
                $urls = AIUrlScraping::getByAgent($agentId, 100);
            } else {
                $urls = AIUrlScraping::getByAgent($agentId, 100);
                $urls = array_filter($urls, fn($u) => $u['status'] === $status);
            }
            
            $pendingCount = AIUrlScraping::countByStatus($agentId, 'pending');
            $processingCount = AIUrlScraping::countByStatus($agentId, 'processing');
            $completedCount = AIUrlScraping::countByStatus($agentId, 'completed');
            $failedCount = AIUrlScraping::countByStatus($agentId, 'failed');
            
            Response::view('rag/urls', [
                'agent' => $agent,
                'urls' => $urls,
                'status' => $status,
                'pendingCount' => $pendingCount,
                'processingCount' => $processingCount,
                'completedCount' => $completedCount,
                'failedCount' => $failedCount
            ]);
        } catch (\Exception $e) {
            Response::view('rag/urls', [
                'agent' => $agent,
                'urls' => [],
                'status' => $status,
                'pendingCount' => 0,
                'processingCount' => 0,
                'completedCount' => 0,
                'failedCount' => 0,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Adicionar URL para scraping
     */
    public function addUrl(int $agentId): void
    {
        Permission::abortIfCannot('ai_agents.edit');
        
        $data = Request::post();
        $url = $data['url'] ?? '';
        $discoverLinks = isset($data['discover_links']) && $data['discover_links'] === '1';
        
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            Response::json(['success' => false, 'message' => 'URL inválida'], 400);
            return;
        }
        
        try {
            // Se deve descobrir links, fazer crawling
            if ($discoverLinks) {
                $options = [
                    'max_depth' => (int)($data['max_depth'] ?? 3),
                    'max_urls' => (int)($data['max_urls'] ?? 500),
                    'allowed_paths' => !empty($data['allowed_paths']) ? explode(',', $data['allowed_paths']) : [],
                    'excluded_paths' => !empty($data['excluded_paths']) ? explode(',', $data['excluded_paths']) : []
                ];
                
                $result = URLScrapingService::discoverAndAddUrls($agentId, $url, $options);
                
                if ($result['success']) {
                    Response::json([
                        'success' => true,
                        'message' => "Crawling concluído! {$result['urls_discovered']} URLs descobertas e adicionadas.",
                        'urls_discovered' => $result['urls_discovered'],
                        'urls' => $result['urls']
                    ]);
                } else {
                    Response::json([
                        'success' => false,
                        'message' => 'Erro no crawling: ' . ($result['error'] ?? 'Erro desconhecido')
                    ], 500);
                }
                return;
            }
            
            // Adicionar URL única
            if (AIUrlScraping::urlExists($agentId, $url)) {
                Response::json(['success' => false, 'message' => 'URL já está na lista'], 400);
                return;
            }
            
            $urlId = AIUrlScraping::create([
                'ai_agent_id' => $agentId,
                'url' => $url,
                'status' => 'pending'
            ]);
            
            Response::json([
                'success' => true,
                'message' => 'URL adicionada com sucesso!',
                'id' => $urlId
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao adicionar URL: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Processar URLs pendentes
     */
    public function processUrls(int $agentId): void
    {
        Permission::abortIfCannot('ai_agents.edit');
        
        $limit = (int)Request::get('limit', 10);
        
        try {
            $stats = ProcessURLScrapingJob::processByAgent($agentId, $limit);
            
            Response::json([
                'success' => true,
                'message' => "Processamento concluído: {$stats['success']} sucesso, {$stats['failed']} falhas",
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao processar URLs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Página de Memórias
     */
    public function memory(int $agentId): void
    {
        Permission::abortIfCannot('ai_agents.view');
        
        $agent = AIAgent::find($agentId);
        if (!$agent) {
            Response::redirect('/ai-agents');
            return;
        }
        
        $conversationId = Request::get('conversation_id');
        $memoryType = Request::get('memory_type');
        
        try {
            if ($conversationId) {
                $memories = AIAgentMemory::getByAgent($agentId, (int)$conversationId, 100);
            } elseif ($memoryType) {
                $memories = AIAgentMemory::getByType($agentId, $memoryType, 100);
            } else {
                $memories = AIAgentMemory::getByAgent($agentId, null, 100);
            }
            
            $totalCount = AIAgentMemory::countByAgent($agentId);
            
            Response::view('rag/memory', [
                'agent' => $agent,
                'memories' => $memories,
                'conversationId' => $conversationId,
                'memoryType' => $memoryType,
                'totalCount' => $totalCount
            ]);
        } catch (\Exception $e) {
            Response::view('rag/memory', [
                'agent' => $agent,
                'memories' => [],
                'conversationId' => null,
                'memoryType' => null,
                'totalCount' => 0,
                'error' => $e->getMessage()
            ]);
        }
    }
}

