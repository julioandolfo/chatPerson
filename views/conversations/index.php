<?php
// Log de debug
$logFile = __DIR__ . '/../../storage/logs/conversas_bug.log';
@file_put_contents($logFile, date('Y-m-d H:i:s') . " - Carregando views/conversations/index.php\n", FILE_APPEND);

$layout = 'layouts.metronic.app';
$title = 'Conversas';
$pageTitle = 'Conversas';
$hidePageTitle = true; // Não mostrar título padrão, vamos usar layout customizado
$hideRightSidebar = true; // Esconder sidebar padrão do Metronic (vamos usar nosso próprio)

/**
 * Renderizar anexo
 */
function renderAttachment($attachment) {
    $type = $attachment['type'] ?? 'document';
    
    // Renderizar localização
    if ($type === 'location' && isset($attachment['latitude']) && isset($attachment['longitude'])) {
        $lat = $attachment['latitude'];
        $lng = $attachment['longitude'];
        $name = htmlspecialchars($attachment['name'] ?? 'Localização');
        $address = htmlspecialchars($attachment['address'] ?? '');
        $mapsUrl = "https://www.google.com/maps?q={$lat},{$lng}";
        
        $html = '<div class="attachment-item mb-2">';
        $html .= '<a href="' . $mapsUrl . '" target="_blank" class="d-flex align-items-center gap-2 p-2 border rounded" style="text-decoration: none; color: inherit; background: rgba(255,255,255,0.05);">';
        $html .= '<i class="ki-duotone ki-geolocation fs-2 text-danger">';
        $html .= '<span class="path1"></span>';
        $html .= '<span class="path2"></span>';
        $html .= '</i>';
        $html .= '<div class="flex-grow-1">';
        $html .= '<div class="fw-semibold">' . $name . '</div>';
        if ($address) {
            $html .= '<div class="text-muted fs-7">' . $address . '</div>';
        }
        $html .= '<div class="text-muted fs-7">' . $lat . ', ' . $lng . '</div>';
        $html .= '</div>';
        $html .= '<i class="ki-duotone ki-arrow-top-right fs-4 text-primary">';
        $html .= '<span class="path1"></span>';
        $html .= '<span class="path2"></span>';
        $html .= '</i>';
        $html .= '</a>';
        $html .= '</div>';
        return $html;
    }
    
    // ✅ CORRIGIDO: Usar campo 'url' se disponível, senão construir a partir de 'path'
    $url = !empty($attachment['url']) 
        ? $attachment['url'] 
        : \App\Helpers\Url::to('/' . ltrim($attachment['path'] ?? '', '/'));
    
    $name = htmlspecialchars($attachment['original_name'] ?? 'Anexo');
    
    $html = '<div class="attachment-item mb-2">';
    
    if ($type === 'image') {
        // Placeholder base64 simples (pixel transparente 1x1)
        $placeholder = 'data:image/svg+xml;base64,' . base64_encode('<svg width="300" height="300" xmlns="http://www.w3.org/2000/svg"><rect width="300" height="300" fill="#f0f0f0"/></svg>');
        $html .= '<a href="' . $url . '" target="_blank" class="d-inline-block lazy-image-container" data-src="' . $url . '">';
        $html .= '<img src="' . $placeholder . '" alt="' . $name . '" data-src="' . $url . '" class="lazy-image" style="max-width: 300px; max-height: 300px; border-radius: 8px; cursor: pointer; background: #f0f0f0; min-width: 100px; min-height: 100px;">';
        $html .= '<div class="lazy-loading-spinner" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); display: none;"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></div>';
        $html .= '</a>';
    } elseif ($type === 'video') {
        $html .= '<div class="lazy-video-container" data-src="' . $url . '" data-type="' . ($attachment['mime_type'] ?? $attachment['mimetype'] ?? 'video/mp4') . '">';
        $html .= '<div class="lazy-video-placeholder" style="max-width: 300px; max-height: 200px; border-radius: 8px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; cursor: pointer; min-height: 200px;">';
        $html .= '<i class="ki-duotone ki-play fs-2x text-primary"><span class="path1"></span><span class="path2"></span></i>';
        $html .= '</div>';
        $html .= '<video controls style="max-width: 300px; max-height: 200px; border-radius: 8px; display: none;" preload="none">';
        $html .= '<source src="" type="' . ($attachment['mime_type'] ?? $attachment['mimetype'] ?? 'video/mp4') . '">';
        $html .= 'Seu navegador não suporta vídeo.';
        $html .= '</video>';
        $html .= '</div>';
    } elseif ($type === 'audio') {
        // Player de áudio estilo WhatsApp com largura adequada
        $html .= '<div class="attachment audio-attachment">';
        $html .= '<div class="d-flex align-items-center gap-2">';
        $html .= '<div class="me-1" style="flex-shrink: 0;">';
        $html .= '<i class="ki-duotone ki-music fs-4 text-primary" style="min-width: 20px; font-size: 18px !important;">';
        $html .= '<span class="path1"></span>';
        $html .= '<span class="path2"></span>';
        $html .= '</i>';
        $html .= '</div>';
        $html .= '<div class="flex-grow-1" style="min-width: 300px;">';
        $html .= '<audio controls style="width: 100%; outline: none;">';
        $html .= '<source src="' . $url . '" type="' . ($attachment['mime_type'] ?? $attachment['mimetype'] ?? 'audio/webm') . '">';
        $html .= 'Seu navegador não suporta áudio.';
        $html .= '</audio>';
        
        // ✅ NOVO: Exibir transcrição/texto original se disponível e configurado
        $settings = \App\Services\ConversationSettingsService::getSettings();
        $showTranscription = $settings['audio_transcription']['show_transcription_in_chat'] ?? true;
        
        if ($showTranscription) {
            // Verificar se é áudio TTS (tem texto original) ou áudio transcrito
            $ttsOriginalText = $attachment['tts_original_text'] ?? null;
            $transcription = $attachment['transcription'] ?? null;
            
            if ($ttsOriginalText) {
                // Áudio gerado pela IA - exibir texto original
                $html .= '<div class="audio-transcription mt-2" style="padding: 8px; background: rgba(52, 211, 153, 0.1); border-radius: 6px; border-left: 3px solid #34d399;">';
                $html .= '<div class="d-flex align-items-center gap-1 mb-1">';
                $html .= '<i class="ki-duotone ki-message-text-2 fs-7 text-success"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>';
                $html .= '<span class="text-success fs-8 fw-semibold">Conteúdo do áudio:</span>';
                $html .= '</div>';
                $html .= '<div class="fs-7" style="color: rgba(0,0,0,0.7);">' . nl2br(htmlspecialchars($ttsOriginalText)) . '</div>';
                $html .= '</div>';
            } elseif ($transcription && !empty($transcription['text'])) {
                // Áudio do cliente - exibir transcrição
                $html .= '<div class="audio-transcription mt-2" style="padding: 8px; background: rgba(0,0,0,0.05); border-radius: 6px; border-left: 3px solid #3b82f6;">';
                $html .= '<div class="d-flex align-items-center gap-1 mb-1">';
                $html .= '<i class="ki-duotone ki-text fs-7 text-muted"><span class="path1"></span><span class="path2"></span></i>';
                $html .= '<span class="text-muted fs-8 fw-semibold">Transcrição:</span>';
                $html .= '</div>';
                $html .= '<div class="fs-7" style="color: rgba(0,0,0,0.7);">' . nl2br(htmlspecialchars($transcription['text'])) . '</div>';
                $html .= '</div>';
            }
        }
        
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
    } else {
        // Para documentos, usar URL direta se o arquivo estiver em assets/
        $attachmentPath = $attachment['path'] ?? '';
        if (strpos($attachmentPath, 'assets/') === 0) {
            // Caminho direto para arquivo público
            $downloadUrl = \App\Helpers\Url::to('/' . $attachmentPath);
        } else {
            // Rota de download para arquivos fora de assets/
            $downloadUrl = \App\Helpers\Url::to('/attachments/' . urlencode($attachmentPath) . '/download');
        }
        $html .= '<a href="' . $downloadUrl . '" target="_blank" class="d-flex align-items-center gap-2 p-2 border rounded" style="text-decoration: none; color: inherit; background: rgba(255,255,255,0.05);">';
        $html .= '<i class="ki-duotone ki-file fs-2">';
        $html .= '<span class="path1"></span>';
        $html .= '<span class="path2"></span>';
        $html .= '</i>';
        $html .= '<div class="flex-grow-1">';
        $html .= '<div class="fw-semibold">' . $name . '</div>';
        if (isset($attachment['size'])) {
            $size = $attachment['size'];
            $sizeStr = $size < 1024 ? $size . ' Bytes' : ($size < 1048576 ? round($size / 1024, 2) . ' KB' : round($size / 1048576, 2) . ' MB');
            $html .= '<div class="text-muted fs-7">' . $sizeStr . '</div>';
        }
        $html .= '</div>';
        $html .= '<i class="ki-duotone ki-arrow-down fs-4 text-primary">';
        $html .= '<span class="path1"></span>';
        $html .= '<span class="path2"></span>';
        $html .= '</i>';
        $html .= '</a>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Formatar data para exibição (HOJE, ONTEM, ou data formatada)
 */
function formatDateLabel($dateString) {
    $date = new DateTime($dateString);
    $today = new DateTime();
    $yesterday = new DateTime('yesterday');
    
    $dateOnly = $date->format('Y-m-d');
    $todayOnly = $today->format('Y-m-d');
    $yesterdayOnly = $yesterday->format('Y-m-d');
    
    if ($dateOnly === $todayOnly) {
        return 'HOJE';
    } elseif ($dateOnly === $yesterdayOnly) {
        return 'ONTEM';
    } else {
        // Formato: "DIA X" (ex: "15 de Janeiro de 2025")
        $months = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
        ];
        $day = (int)$date->format('d');
        $month = (int)$date->format('m');
        $year = (int)$date->format('Y');
        
        return $day . ' de ' . $months[$month] . ' de ' . $year;
    }
}

/**
 * Verificar se duas datas são de dias diferentes
 */
function isDifferentDay($date1, $date2) {
    if (empty($date1) || empty($date2)) {
        return false;
    }
    
    $d1 = new DateTime($date1);
    $d2 = new DateTime($date2);
    
    return $d1->format('Y-m-d') !== $d2->format('Y-m-d');
}

/**
 * Renderizar separador de data
 */
function renderDateSeparator($dateString) {
    $label = formatDateLabel($dateString);
    return '<div class="date-separator" data-date="' . htmlspecialchars($dateString) . '">
        <span class="date-separator-line"></span>
        <span class="date-separator-label">' . htmlspecialchars($label) . '</span>
        <span class="date-separator-line"></span>
    </div>';
}

/**
 * Renderizar status de mensagem
 */
function renderMessageStatus($msg) {
    $status = $msg['status'] ?? 'sent';
    $readAt = $msg['read_at'] ?? null;
    $deliveredAt = $msg['delivered_at'] ?? null;
    $errorMessage = $msg['error_message'] ?? null;
    
    // Se houver erro
    if ($status === 'failed' || !empty($errorMessage)) {
        $errorText = htmlspecialchars($errorMessage ?? 'Erro ao enviar');
        return '<span class="message-status message-status-error" title="' . $errorText . '">
            <i class="ki-duotone ki-cross-circle fs-6">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <span class="message-status-label">Erro</span>
        </span>';
    }
    
    // Se foi lida
    if (!empty($readAt)) {
        return '<span class="message-status" title="Lida em ' . date('d/m/Y H:i', strtotime($readAt)) . '">
            <i class="ki-duotone ki-double-check fs-6" style="color: #0088cc;">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <span class="message-status-label">Lida</span>
        </span>';
    }
    
    // Se foi entregue
    if (!empty($deliveredAt)) {
        return '<span class="message-status" title="Entregue em ' . date('d/m/Y H:i', strtotime($deliveredAt)) . '">
            <i class="ki-duotone ki-double-check fs-6 text-white">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <span class="message-status-label">Entregue</span>
        </span>';
    }
    
    // Enviado (padrão)
    return '<span class="message-status" title="Enviado">
        <i class="ki-duotone ki-check fs-6 text-white">
            <span class="path1"></span>
        </i>
        <span class="message-status-label">Enviado</span>
    </span>';
}

// Content
ob_start();
?>

<style>
/* Layout Principal - 3 Colunas */
.conversations-layout {
    display: flex;
    flex-direction: row;
    height: calc(100vh - 110px); /* Header do Metronic */
    overflow: hidden;
    margin: 20px 0 0 0; /* Margem superior apenas */
    padding: 0 20px 0 0; /* Padding à direita para respiro */
    position: relative;
    width: 100%; /* 100% da largura disponível */
    box-sizing: border-box; /* Inclui padding na largura */
}

/* Forçar o container pai a ocupar toda largura - Remove padding do sidebar do Metronic */
.sidebar-enabled .wrapper {
    padding-right: 0 !important;
}

.app-content {
    max-width: 100% !important;
    padding-right: 0 !important;
}

.app-wrapper {
    padding-right: 0 !important;
}

.app-main {
    padding-right: 0 !important;
}

/* Coluna 1: Lista de Conversas */
.conversations-list {
    width: 380px;
    flex-shrink: 0;
    border-right: 1px solid var(--bs-border-color);
    display: flex;
    flex-direction: column;
    background: var(--bs-body-bg);
}

.conversations-list-header {
    padding: 20px;
    border-bottom: 1px solid var(--bs-border-color);
    flex-shrink: 0;
}

.conversations-list-filters {
    padding: 10px 20px;
    border-bottom: 1px solid var(--bs-border-color);
    flex-shrink: 0;
}

.conversations-list-items {
    flex: 1;
    overflow-y: auto;
    overflow-x: visible; /* Permitir que dropdowns não sejam cortados */
}

.conversation-item {
    padding: 15px 20px;
    border-bottom: 1px solid var(--bs-border-color);
    cursor: pointer;
    transition: background 0.15s ease;
    position: relative;
    overflow: visible; /* Permitir que dropdown apareça completamente */
}

.conversation-item:hover {
    background: var(--bs-gray-100);
}

.conversation-item.active {
    background: var(--bs-primary-light);
    border-left: 3px solid var(--bs-primary);
}

.conversation-item.pinned {
    background: rgba(255, 193, 7, 0.05);
    border-left: 3px solid rgba(255, 193, 7, 0.3);
}

.conversation-item.pinned.active {
    background: rgba(255, 193, 7, 0.1);
    border-left: 3px solid rgba(255, 193, 7, 0.5);
}

.hover-bg-light:hover {
    background-color: var(--bs-gray-100) !important;
}

.cursor-pointer {
    cursor: pointer;
}

.conversation-item-header {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    margin-bottom: 6px;
}

.conversation-item-name {
    font-weight: 600;
    font-size: 14px;
    color: var(--bs-text-dark);
    flex: 1;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.conversation-item-time {
    font-size: 12px;
    color: var(--bs-text-muted);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
    white-space: nowrap;
    position: relative;
}

.conversation-item-time .btn,
.conversation-item-time button {
    flex-shrink: 0;
}

/* Dropdown de ações - melhor visibilidade e overflow */
.conversation-item-actions {
    position: relative;
    z-index: 10;
    display: inline-block; /* Garantir que dropdown seja posicionado em relação ao botão */
}

/* Quando dropdown está aberto, aumentar z-index do item da conversa */
.conversation-item:has(.conversation-item-actions .dropdown-menu.show) {
    z-index: 1000 !important;
    position: relative;
}

/* Garantir que o dropdown-menu tenha z-index alto */
.conversation-item-actions .dropdown-menu {
    z-index: 1050 !important;
}

.conversation-item-actions .btn {
    opacity: 0.6;
    transition: opacity 0.2s ease, background-color 0.2s ease;
    padding: 6px 8px !important;
    min-width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.conversation-item-actions .btn i {
    font-size: 1.25rem !important; /* fs-5 equivalente */
}

.conversation-item-actions .btn:hover {
    opacity: 1 !important;
    background-color: var(--bs-gray-200) !important;
}

.conversation-item-actions .btn:hover i {
    color: var(--bs-primary) !important;
}

.conversation-item:hover .conversation-item-actions .btn {
    opacity: 0.8;
}

.conversation-item-actions .dropdown-menu {
    position: absolute !important;
    top: 100% !important;
    right: 0 !important;
    left: auto !important;
    margin-top: 2px !important;
    margin-right: 0 !important;
    min-width: 200px;
    z-index: 1000 !important;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    border: 1px solid var(--bs-border-color);
    max-height: none !important;
    overflow: visible !important;
    transform: none !important;
}

/* Garantir z-index para o item da conversa */
.conversation-item {
    position: relative !important;
}

.conversation-item-actions {
    position: relative !important;
}

/* Estilos para modo dark */
[data-bs-theme="dark"] .conversation-item-actions .dropdown-menu,
.dark-mode .conversation-item-actions .dropdown-menu,
body.dark-mode .conversation-item-actions .dropdown-menu {
    background-color: var(--bs-gray-800) !important;
    border-color: var(--bs-gray-700) !important;
}

[data-bs-theme="dark"] .conversation-item-actions .dropdown-item,
.dark-mode .conversation-item-actions .dropdown-item,
body.dark-mode .conversation-item-actions .dropdown-item {
    color: var(--bs-white) !important;
}

[data-bs-theme="dark"] .conversation-item-actions .dropdown-item:hover,
.dark-mode .conversation-item-actions .dropdown-item:hover,
body.dark-mode .conversation-item-actions .dropdown-item:hover {
    background-color: var(--bs-gray-700) !important;
    color: var(--bs-white) !important;
}

[data-bs-theme="dark"] .conversation-item-actions .dropdown-divider,
.dark-mode .conversation-item-actions .dropdown-divider,
body.dark-mode .conversation-item-actions .dropdown-divider {
    border-color: var(--bs-gray-700) !important;
}

/* Garantir que o dropdown não seja cortado pela altura da linha */
.conversation-item {
    overflow: visible !important;
}

/* Garantir que o dropdown apareça acima de outros elementos */
.conversation-item-actions.show .dropdown-menu {
    display: block !important;
}

/* Garantir que o container da lista não corte o dropdown */
.conversations-list-items {
    overflow-y: auto;
    overflow-x: visible;
    position: relative;
}

/* Garantir que o dropdown apareça acima de outros itens */
.conversation-item-actions {
    position: relative;
    z-index: 10;
}

.conversation-item-actions.show {
    z-index: 1051;
}

.conversation-item-preview {
    font-size: 13px;
    color: var(--bs-text-gray-700);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    margin-bottom: 8px;
    max-width: 100%;
    display: block;
}

.conversation-item-search-match {
    margin-top: 4px;
    margin-bottom: 4px;
}

.conversation-item-search-match .badge {
    font-size: 0.75rem;
    padding: 2px 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
}

.conversation-item-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    position: relative;
    padding-right: 30px; /* Espaço para o badge */
}

.conversation-item-channel {
    font-size: 12px;
    color: var(--bs-text-gray-700);
    display: flex;
    align-items: center;
    gap: 4px;
}

.conversation-item-channel svg {
    flex-shrink: 0;
}

.conversation-item-badge {
    background: #f1416c;
    color: #fff;
    border-radius: 10px;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 600;
    min-width: 20px;
    text-align: center;
    display: inline-block;
    position: absolute;
    right: 15px;
    bottom: 15px;
    z-index: 1;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* Coluna 2: Área de Chat */
.chat-area {
    flex: 1 1 auto;
    min-width: 0; /* Permite que o flex shrink funcione */
    width: 100%;
    max-width: 100%;
    overflow-x: hidden;
    display: flex;
    flex-direction: column;
    background: var(--bs-body-bg);
}

.chat-header {
    padding: 20px 25px;
    border-bottom: 1px solid var(--bs-border-color);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.chat-header-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.chat-header-title {
    font-weight: 600;
    font-size: 16px;
    color: var(--bs-text-dark);
    margin-bottom: 3px;
}

.chat-header-subtitle {
    font-size: 13px;
    color: var(--bs-text-gray-700);
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 20px 25px;
    background: var(--bs-gray-100);
    position: relative;
    width: 100%;
    max-width: 100%;
}

/* Indicador de carregamento de mensagens antigas */
.messages-loading {
    text-align: center;
    padding: 15px;
    color: var(--bs-text-muted);
    font-size: 14px;
}

.messages-loading .spinner-border {
    width: 1.5rem;
    height: 1.5rem;
    margin-right: 10px;
}

.messages-load-more {
    text-align: center;
    padding: 10px;
    color: var(--bs-primary);
    cursor: pointer;
    font-size: 13px;
    transition: opacity 0.2s;
}

.messages-load-more:hover {
    opacity: 0.7;
}

/* Highlight de mensagem encontrada na busca */
.chat-message.message-highlight {
    animation: messageHighlight 0.5s ease-in-out;
}

.chat-message.message-highlight .message-bubble {
    background-color: rgba(255, 193, 7, 0.2) !important;
    border: 2px solid rgba(255, 193, 7, 0.6) !important;
    box-shadow: 0 0 10px rgba(255, 193, 7, 0.4) !important;
}

@keyframes messageHighlight {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.02);
    }
    100% {
        transform: scale(1);
    }
}

/* Estilos para resultados de busca */
.message-search-result {
    transition: background-color 0.2s;
}

.message-search-result:hover {
    background-color: var(--bs-gray-100) !important;
}

.message-search-result.bg-light-primary {
    background-color: rgba(13, 110, 253, 0.1) !important;
}

.message-search-result.hover-bg-light-primary:hover {
    background-color: rgba(13, 110, 253, 0.15) !important;
}

[data-bs-theme="dark"] .message-search-result.hover-bg-light-primary:hover {
    background-color: rgba(13, 110, 253, 0.25) !important;
}

[data-bs-theme="dark"] #messageSearchResults {
    background-color: var(--bs-gray-800) !important;
    border-color: var(--bs-gray-700) !important;
}

[data-bs-theme="dark"] #messageSearchResults .text-gray-800 {
    color: var(--bs-gray-100) !important;
}

[data-bs-theme="dark"] #messageSearchResults .text-gray-600 {
    color: var(--bs-gray-300) !important;
}

[data-bs-theme="dark"] #messageSearchResults .text-gray-500 {
    color: var(--bs-gray-400) !important;
}

[data-bs-theme="dark"] #messageSearchResults .text-gray-700 {
    color: var(--bs-gray-200) !important;
}

/* Remover z-index customizados - deixar Bootstrap gerenciar */

[data-bs-theme="dark"] #messageSearchResults mark {
    background-color: #ffc107;
    color: #000;
}

.template-quick-group-header {
    padding: 8px 12px;
    background-color: var(--bs-gray-100);
    font-weight: 600;
    font-size: 12px;
    color: var(--bs-gray-700);
    border-bottom: 1px solid var(--bs-gray-200);
    margin-top: 8px;
}

.template-quick-group-header:first-child {
    margin-top: 0;
}

[data-bs-theme="dark"] .template-quick-group-header {
    background-color: var(--bs-gray-800);
    color: var(--bs-gray-300);
    border-bottom-color: var(--bs-gray-700);
}

#messageSearchResults mark {
    background-color: #ffc107;
    color: #000;
    padding: 2px 4px;
    border-radius: 3px;
    font-weight: 600;
}

.chat-message {
    display: flex;
    margin-bottom: 20px;
    gap: 10px;
    min-width: 0;
    width: 100%;
    max-width: 100%;
}

.chat-message.incoming {
    justify-content: flex-start;
}

.chat-message.outgoing {
    justify-content: flex-end;
}

.message-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--bs-gray-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: var(--bs-text-muted);
    flex-shrink: 0;
}

.message-content {
    max-width: 60%;
    min-width: 0;
    overflow-wrap: break-word;
    word-wrap: break-word;
    box-sizing: border-box;
}

/* Permitir que áudios sejam mais largos */
.chat-message .message-content:has(.audio-only),
.chat-message .message-content:has(.audio-attachment) {
    max-width: calc(100% - 20px) !important;
    min-width: 0 !important;
    width: 100%;
    box-sizing: border-box;
}

/* Forçar attachment-item a não limitar largura */
.audio-only .attachment-item,
.message-bubble .attachment-item:has(.audio-attachment) {
    max-width: none !important;
    width: 100%;
}

.message-bubble {
    padding: 12px 16px;
    border-radius: 12px;
    font-size: 14px;
    line-height: 1.5;
    word-wrap: break-word;
}

/* Reduzir padding quando contém apenas áudio (manter background da bolha) */
.message-bubble.audio-only {
    padding: 8px !important;
    line-height: 1 !important;
}

.message-bubble.audio-only .audio-attachment {
    margin: 0;
}

/* Player de áudio deve herdar cor de fundo da bolha */
.chat-message.outgoing .audio-attachment > div {
    background: rgba(255, 255, 255, 0.15) !important;
}

.chat-message.incoming .audio-attachment > div {
    background: var(--bs-gray-100) !important;
}

/* Garantir que botões de ação apareçam sobre o player de áudio */
.message-content:has(.audio-attachment) {
    position: relative;
}

.message-content:has(.audio-attachment) .message-actions {
    z-index: 10;
}

/* Banner de IA Ativa */
.ai-active-banner {
    padding: 12px 20px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
    border-bottom: 1px solid rgba(99, 102, 241, 0.2);
    flex-shrink: 0;
    animation: slideDown 0.3s ease-out;
}

.ai-active-banner-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

.ai-active-banner-icon {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    border-radius: 50%;
    color: white;
}

.ai-active-banner-info {
    flex: 1;
    min-width: 0;
}

.ai-active-banner-title {
    display: flex;
    align-items: center;
    font-weight: 600;
    font-size: 14px;
    color: var(--bs-text-dark);
    margin-bottom: 2px;
}

.ai-active-banner-subtitle {
    font-size: 12px;
    color: var(--bs-text-gray-700);
    display: flex;
    align-items: center;
}

.ai-active-banner-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Dark mode para banner de IA */
[data-theme="dark"] .ai-active-banner {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(139, 92, 246, 0.15) 100%);
    border-bottom-color: rgba(99, 102, 241, 0.3);
}

/* Badge de mensagem de IA */
.ai-message-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    margin-bottom: 6px;
    font-size: 11px;
    color: #6366f1;
    font-weight: 500;
}

.ai-message-badge .ai-avatar-mini {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white;
    font-size: 9px;
    font-weight: 600;
    flex-shrink: 0;
}

.ai-message-badge .ai-avatar-mini img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.ai-message-badge i {
    color: #6366f1;
    font-size: 12px;
}

.ai-badge-text {
    font-size: 11px;
    font-weight: 500;
}

/* Avatar do agente de IA nas mensagens */
.chat-message.outgoing .message-avatar.ai-agent-avatar {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 12px;
}

.chat-message.outgoing .message-avatar.ai-agent-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

/* Estilo especial para mensagens de IA */
.message-bubble.ai-message {
    border-left: 3px solid #6366f1;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(139, 92, 246, 0.05) 100%);
}

.chat-message.outgoing .message-bubble.ai-message {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(139, 92, 246, 0.15) 100%);
    border-left-color: #6366f1;
}

/* Dark mode para badge de IA */
[data-theme="dark"] .ai-message-badge {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(139, 92, 246, 0.2) 100%);
    border-color: rgba(99, 102, 241, 0.3);
    color: #818cf8;
}

[data-theme="dark"] .ai-message-badge i {
    color: #818cf8;
}

[data-theme="dark"] .message-bubble.ai-message {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
    border-left-color: #818cf8;
}

[data-theme="dark"] .chat-message.outgoing .message-bubble.ai-message {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(139, 92, 246, 0.2) 100%);
    border-left-color: #818cf8;
}

/* Player de áudio - layout unificado */
.audio-attachment {
    max-width: none !important;
    min-width: 380px !important;
    width: 100%;
    margin: 0;
    line-height: 1;
}

.audio-attachment > div {
    padding: 10px 14px !important;
    line-height: 1.1;
    display: flex !important;
    align-items: center !important;
    background: var(--bs-gray-100) !important;
    border-radius: 18px !important;
    min-width: 380px !important;
}

.audio-attachment audio {
    width: 100% !important;
    min-width: 300px !important;
    height: 34px !important;
    max-height: 34px !important;
    min-height: 34px !important;
    display: block !important;
}

/* Lazy loading styles */
.lazy-image-container {
    position: relative;
    display: inline-block;
}

.lazy-image {
    transition: opacity 0.3s ease-in-out;
}

.lazy-image.loading {
    opacity: 0.5;
}

.lazy-image.loaded {
    opacity: 1;
}

.lazy-loading-spinner {
    pointer-events: none;
}

.lazy-video-container {
    position: relative;
}

.lazy-video-placeholder {
    transition: opacity 0.2s;
}

.lazy-video-placeholder:hover {
    opacity: 0.8;
}

.lazy-video-container video.loaded {
    display: block !important;
}

.lazy-video-container .lazy-video-placeholder.loaded {
    display: none !important;
}

/* Garantir que o ícone não aumente a altura */
.audio-attachment i {
    line-height: 1;
    display: flex;
    align-items: center;
}

.chat-message.incoming .message-bubble {
    background: var(--bs-body-bg);
    color: var(--bs-text-dark);
    border-bottom-left-radius: 4px;
    border: 1px solid var(--bs-border-color);
}

.chat-message.outgoing .message-bubble {
    background: var(--bs-primary);
    color: #fff;
    border-bottom-right-radius: 4px;
}

.message-time {
    font-size: 11px;
    color: var(--bs-text-muted);
    margin-top: 4px;
    padding: 0 4px;
}

/* Separador de data */
.date-separator {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 20px 0;
    gap: 12px;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    flex-shrink: 0;
}

.date-separator-line {
    flex: 1;
    height: 1px;
    background: var(--bs-border-color);
    opacity: 0.5;
}

.date-separator-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--bs-text-muted);
    padding: 4px 12px;
    background: var(--bs-gray-100);
    border-radius: 12px;
    white-space: nowrap;
}

[data-bs-theme="dark"] .date-separator-label {
    background: var(--bs-gray-800);
    color: var(--bs-gray-300);
}

[data-bs-theme="dark"] .date-separator-line {
    background: var(--bs-gray-700);
}

.chat-message.incoming .message-time {
    text-align: left;
}

.chat-message.outgoing .message-time {
    text-align: right;
}

.message-status {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    margin-left: 4px;
    position: relative;
}

.message-status-label {
    font-size: 10px;
    opacity: 0.7;
    margin-left: 2px;
    white-space: nowrap;
}

.message-status-error {
    color: #f1416c !important;
}

.message-status-error .message-status-label {
    color: #f1416c;
    opacity: 1;
}

/* Mensagem Citada/Reply */
.quoted-message {
    border-left: 2px solid rgba(255, 255, 255, 0.2);
    padding: 8px 12px;
    margin-bottom: 8px;
    background: rgba(0, 0, 0, 0.05);
    border-radius: 4px;
    font-size: 12px;
    opacity: 0.85;
    transition: all 0.2s;
    cursor: pointer !important;
}

.quoted-message:hover {
    opacity: 1;
    background: rgba(0, 0, 0, 0.12);
    border-left-color: rgba(255, 255, 255, 0.3);
    transform: translateX(2px);
}

.chat-message.incoming .quoted-message {
    border-left-color: rgba(var(--bs-primary-rgb), 0.4);
    background: rgba(var(--bs-primary-rgb), 0.06);
}

.chat-message.incoming .quoted-message:hover {
    background: rgba(var(--bs-primary-rgb), 0.12);
    border-left-color: rgba(var(--bs-primary-rgb), 0.6);
}

.quoted-message-header {
    font-weight: 600;
    margin-bottom: 4px;
    color: rgba(255, 255, 255, 0.9);
}

.chat-message.incoming .quoted-message-header {
    color: var(--bs-primary);
}

.quoted-message-content {
    color: rgba(255, 255, 255, 0.7);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.chat-message.incoming .quoted-message-content {
    color: var(--bs-text-muted);
}

/* Botão de Reply */
.message-actions {
    position: absolute;
    top: 4px;
    right: 4px;
    opacity: 0;
    transition: opacity 0.2s;
    display: flex;
    gap: 4px;
}

.chat-message:hover .message-actions {
    opacity: 1;
}

.message-actions-btn {
    background: rgba(0, 0, 0, 0.5);
    border: none;
    color: #fff;
    width: 24px;
    height: 24px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 12px;
    transition: background 0.2s;
}

.message-actions-btn:hover {
    background: rgba(0, 0, 0, 0.7);
}

.chat-message.incoming .message-actions-btn {
    background: rgba(255, 255, 255, 0.8);
    color: var(--bs-text-dark);
}

.chat-message.incoming .message-actions-btn:hover {
    background: rgba(255, 255, 255, 1);
}

.message-content {
    position: relative;
}

/* Preview de Reply */
.reply-preview {
    background: rgba(255, 255, 255, 0.05);
    border-left: 3px solid var(--bs-primary);
    padding: 8px 12px;
    margin-bottom: 8px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.reply-preview-content {
    flex-grow: 1;
    min-width: 0;
}

.reply-preview-header {
    font-weight: 600;
    font-size: 12px;
    color: var(--bs-primary);
    margin-bottom: 2px;
}

.reply-preview-text {
    font-size: 12px;
    color: var(--bs-text-muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.reply-preview-close {
    background: transparent;
    border: none;
    color: var(--bs-text-muted);
    cursor: pointer;
    padding: 4px;
    font-size: 16px;
    line-height: 1;
}

.reply-preview-close:hover {
    color: var(--bs-text-dark);
}

/* Mensagem de Sistema/Evento */
.chat-message.system {
    justify-content: center;
    margin: 15px 0;
}

.chat-message.system .message-bubble {
    background: transparent;
    color: var(--bs-text-muted);
    font-size: 12px;
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px dashed var(--bs-border-color);
}

/* Nota Interna - Alinhada à direita como mensagens enviadas */
.chat-message.note {
    justify-content: flex-end;
}

.chat-message.note .message-content {
    max-width: 60%;
}

.chat-message.note .message-bubble {
    background: rgba(255, 193, 7, 0.15); /* Amarelo translúcido */
    border: 1px solid rgba(255, 193, 7, 0.4);
    border-right: 3px solid #ffc107;
    color: var(--bs-text-dark);
    border-bottom-right-radius: 4px;
}

.chat-message.note .message-time {
    text-align: right;
}

.note-header {
    font-weight: 600;
    color: #ff9800;
    margin-bottom: 6px;
    font-size: 11px;
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Campo de Envio */
.chat-input {
    border-top: 1px solid var(--bs-border-color);
    padding: 20px 25px;
    background: var(--bs-body-bg);
    flex-shrink: 0;
}

.chat-input-toolbar {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
}

.chat-input-textarea {
    width: 100%;
    min-height: 60px;
    max-height: 150px;
    border: 1px solid var(--bs-border-color);
    border-radius: 8px;
    padding: 12px 16px;
    font-size: 14px;
    resize: vertical;
    font-family: inherit;
    background: var(--bs-body-bg);
    color: var(--bs-text-dark);
}

.chat-input-textarea:focus {
    outline: none;
    border-color: var(--bs-primary);
}

/* Seletor rápido de templates */
.template-quick-select {
    position: absolute;
    bottom: 100%;
    left: 0;
    right: 0;
    margin-bottom: 8px;
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 1050;
    max-height: 400px;
    display: flex;
    flex-direction: column;
}

.template-quick-select-header {
    padding: 12px;
    border-bottom: 1px solid var(--bs-border-color);
    flex-shrink: 0;
}

.template-quick-select-list {
    flex: 1;
    overflow-y: auto;
    max-height: 320px;
}

.template-quick-item {
    padding: 12px;
    border-bottom: 1px solid var(--bs-border-color);
    cursor: pointer;
    transition: background 0.15s ease;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.template-quick-item:hover,
.template-quick-item.selected {
    background: var(--bs-primary-light);
}

.template-quick-item-name {
    font-weight: 600;
    font-size: 14px;
    color: var(--bs-text-dark);
}

.template-quick-item-preview {
    font-size: 12px;
    color: var(--bs-text-muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.template-quick-item-category {
    display: inline-block;
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 4px;
    background: var(--bs-gray-100);
    color: var(--bs-text-muted);
    margin-top: 4px;
}

.chat-input-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 12px;
}

/* Estado vazio */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--bs-text-muted);
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.3;
    color: var(--bs-text-muted);
}

/* Scrollbar customizado */
.conversations-list-items::-webkit-scrollbar,
.chat-messages::-webkit-scrollbar {
    width: 6px;
}

.conversations-list-items::-webkit-scrollbar-track,
.chat-messages::-webkit-scrollbar-track {
    background: transparent;
}

.conversations-list-items::-webkit-scrollbar-thumb,
.chat-messages::-webkit-scrollbar-thumb {
    background: var(--bs-gray-300);
    border-radius: 3px;
}

.conversations-list-items::-webkit-scrollbar-thumb:hover,
.chat-messages::-webkit-scrollbar-thumb:hover {
    background: var(--bs-gray-400);
}

/* Responsivo */
@media (max-width: 991px) {
    .conversations-layout {
        height: calc(100vh - 70px);
    }
    
    .conversations-list {
        width: 100%;
        max-width: 380px;
    }
}
/* Animações para Assistente IA */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.hover-shadow-lg {
    transition: all 0.3s ease;
}

.hover-shadow-lg:hover {
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15) !important;
    transform: translateY(-2px);
}
/* SweetAlert2 Dark Mode Support */
.swal2-dark {
    background-color: #1e1e2e !important;
    color: #ffffff !important;
}

.swal2-dark .swal2-title {
    color: #ffffff !important;
}

.swal2-dark .swal2-html-container {
    color: #e0e0e0 !important;
}

.swal2-dark .swal2-content {
    color: #e0e0e0 !important;
}

.swal2-dark .swal2-icon {
    border-color: rgba(255, 255, 255, 0.2) !important;
}

.swal2-dark .swal2-icon.swal2-error {
    border-color: #f1416c !important;
}

.swal2-dark .swal2-icon.swal2-warning {
    border-color: #ffc700 !important;
}

.swal2-dark .swal2-icon.swal2-info {
    border-color: #009ef7 !important;
}

.swal2-dark .swal2-icon.swal2-success {
    border-color: #50cd89 !important;
}

.swal2-dark .swal2-icon.swal2-question {
    border-color: #7239ea !important;
}

.swal2-dark .swal2-popup {
    background-color: #1e1e2e !important;
}

.swal2-dark ul {
    color: #e0e0e0 !important;
}

.swal2-dark li {
    color: #e0e0e0 !important;
}

/* Garantir que textos em modo dark sejam claros */
[data-bs-theme="dark"] .swal2-popup,
body.dark-mode .swal2-popup {
    background-color: #1e1e2e !important;
    color: #ffffff !important;
}

[data-bs-theme="dark"] .swal2-title,
body.dark-mode .swal2-title {
    color: #ffffff !important;
}

[data-bs-theme="dark"] .swal2-html-container,
body.dark-mode .swal2-html-container {
    color: #e0e0e0 !important;
}

/* Removido: Borda verde aplicada pelo sistema de indicador SLA ao avatar, não ao card */

[data-bs-theme="dark"] .swal2-content,
body.dark-mode .swal2-content {
    color: #e0e0e0 !important;
}
</style>

<!-- SLA Indicator CSS -->
<link rel="stylesheet" href="<?= \App\Helpers\Url::asset('css/custom/sla-indicator.css') ?>">

<!-- Script inline para definir função ANTES do HTML do botão -->
<script>
// Definir função IMEDIATAMENTE para estar disponível quando o HTML for renderizado
(function() {
    // Mostrar modal de nova conversa
    window.showNewConversationModal = function() {
        const modal = document.getElementById('kt_modal_new_conversation');
        if (!modal) {
            console.error('Modal de nova conversa não encontrado');
            return;
        }
        
        // Limpar formulário
        const form = modal.querySelector('#newConversationForm');
        if (form) form.reset();
        
        // Abrir modal
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        
        // Focar no campo nome
        setTimeout(() => {
            const nameInput = modal.querySelector('#new_contact_name');
            if (nameInput) nameInput.focus();
        }, 300);
    };
    
    console.log('showNewConversationModal definida no escopo global');
})();
</script>

<div class="conversations-layout">
    
    <!-- COLUNA 1: LISTA DE CONVERSAS -->
    <div class="conversations-list">
        
        <!-- Header com busca -->
        <div class="conversations-list-header">
            <div class="d-flex gap-2">
                <div class="position-relative flex-grow-1">
                    <i class="ki-duotone ki-magnifier fs-3 text-gray-500 position-absolute top-50 translate-middle ms-6">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                    <input type="text" id="kt_conversations_search" class="form-control form-control-solid ps-10" placeholder="Buscar conversas e mensagens..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-primary" id="btn_new_conversation" title="Nova conversa" onclick="if(typeof showNewConversationModal === 'function') { showNewConversationModal(); } else { console.error('showNewConversationModal não está definida'); }">
                    <i class="ki-duotone ki-plus fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </button>
            </div>
        </div>
        
        <!-- Barra de Ações em Massa (oculta por padrão) -->
        <div id="bulkActionsBar" class="bulk-actions-bar d-none mb-3 p-3 bg-light-primary rounded">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <span class="fw-semibold text-primary" id="bulkSelectionCount">0 conversas selecionadas</span>
                    <button type="button" class="btn btn-sm btn-light" onclick="selectAllConversations()">
                        <i class="ki-duotone ki-check-square fs-6 me-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Selecionar todas
                    </button>
                    <button type="button" class="btn btn-sm btn-light" onclick="clearBulkSelection()">
                        <i class="ki-duotone ki-cross fs-6 me-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Limpar
                    </button>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="dropdown">
                        <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="ki-duotone ki-profile-user fs-6 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Atribuir
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" style="z-index: 10000;">
                            <?php if (!empty($agents)): ?>
                                <?php foreach ($agents as $agent): ?>
                                    <li>
                                        <a class="dropdown-item text-gray-800" href="#" onclick="event.preventDefault(); bulkAssignAgent(<?= $agent['id'] ?>, '<?= htmlspecialchars($agent['name'], ENT_QUOTES) ?>');">
                                            <?= htmlspecialchars($agent['name']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li><span class="dropdown-item-text text-muted">Nenhum agente disponível</span></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="ki-duotone ki-tag fs-6 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Adicionar Tag
                        </button>
                        <ul class="dropdown-menu">
                            <?php if (!empty($tags)): ?>
                                <?php foreach ($tags as $tag): ?>
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="event.preventDefault(); bulkAddTag(<?= $tag['id'] ?>, '<?= htmlspecialchars($tag['name'], ENT_QUOTES) ?>');">
                                            <span class="badge badge-sm" style="background-color: <?= htmlspecialchars($tag['color'] ?? '#009ef7') ?>20; color: <?= htmlspecialchars($tag['color'] ?? '#009ef7') ?>;">
                                                <?= htmlspecialchars($tag['name']) ?>
                                            </span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <button type="button" class="btn btn-sm btn-warning" onclick="bulkCloseConversations()">
                        <i class="ki-duotone ki-cross-circle fs-6 me-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Fechar
                    </button>
                    <button type="button" class="btn btn-sm btn-info" onclick="bulkReopenConversations()">
                        <i class="ki-duotone ki-arrow-right fs-6 me-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Reabrir
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="conversations-list-filters">
            <div class="d-flex flex-wrap align-items-center gap-2 py-2">
                <select id="filter_status" class="form-select form-select-sm form-select-solid" style="width: auto; min-width: 120px;">
                    <option value="">Todas</option>
                    <option value="open" <?= ($filters['status'] ?? 'open') === 'open' ? 'selected' : '' ?>>Abertas</option>
                    <option value="resolved" <?= ($filters['status'] ?? 'open') === 'resolved' ? 'selected' : '' ?>>Resolvidas</option>
                    <option value="closed" <?= ($filters['status'] ?? 'open') === 'closed' ? 'selected' : '' ?>>Fechadas</option>
                    <option value="spam" <?= !empty($filters['is_spam']) ? 'selected' : '' ?>>🚫 Spam</option>
                    <option value="unanswered" <?= !empty($filters['unanswered']) ? 'selected' : '' ?>>🔴 Não respondidas</option>
                </select>
                
                <select id="filter_channel" class="form-select form-select-sm form-select-solid" style="width: auto; min-width: 120px;">
                    <option value="">Canais</option>
                    <option value="whatsapp" <?= ($filters['channel'] ?? '') === 'whatsapp' ? 'selected' : '' ?>>📱 WhatsApp</option>
                    <option value="email" <?= ($filters['channel'] ?? '') === 'email' ? 'selected' : '' ?>>✉️ Email</option>
                    <option value="chat" <?= ($filters['channel'] ?? '') === 'chat' ? 'selected' : '' ?>>💬 Chat</option>
                </select>
                
                <?php if (!empty($departments)): ?>
                <select id="filter_department" class="form-select form-select-sm form-select-solid" style="width: auto; min-width: 140px;">
                    <option value="">Setores</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= ($filters['department_id'] ?? '') == $dept['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                
                <?php if (!empty($tags)): ?>
                <select id="filter_tag" class="form-select form-select-sm form-select-solid" style="width: auto; min-width: 140px;">
                    <option value="">Tags</option>
                    <?php foreach ($tags as $tag): ?>
                        <option value="<?= $tag['id'] ?>" <?= ($filters['tag_id'] ?? '') == $tag['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tag['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                
                <?php 
                $canViewAllConversations = \App\Helpers\Permission::can('conversations.view.all');
                $currentUserId = \App\Helpers\Auth::id();
                ?>
                <?php if ($canViewAllConversations || !empty($agents)): ?>
                <select id="filter_agent" class="form-select form-select-sm form-select-solid" style="width: auto; min-width: 160px;">
                    <option value="">Agentes</option>
                    <option value="unassigned" <?= ($filters['agent_id'] ?? '') === 'unassigned' ? 'selected' : '' ?>>🔴 Não atribuídas</option>
                    <?php if ($canViewAllConversations && !empty($agents)): ?>
                        <?php foreach ($agents as $agent): ?>
                            <option value="<?= $agent['id'] ?>" <?= ($filters['agent_id'] ?? '') == $agent['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($agent['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php elseif (!$canViewAllConversations && $currentUserId): ?>
                        <?php 
                        $currentUser = \App\Models\User::find($currentUserId);
                        if ($currentUser): ?>
                            <option value="<?= $currentUser['id'] ?>" <?= ($filters['agent_id'] ?? '') == $currentUser['id'] ? 'selected' : '' ?>>
                                Minhas conversas
                            </option>
                        <?php endif; ?>
                    <?php endif; ?>
                </select>
                <?php endif; ?>
                
                <button type="button" class="btn btn-sm btn-light-primary" onclick="openAdvancedFilters()" title="Filtros Avançados">
                    <i class="ki-duotone ki-filter fs-6 me-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Filtros
                </button>
                
                <?php if (!empty($filters['unanswered']) || !empty($filters['answered']) || !empty($filters['date_from']) || !empty($filters['date_to']) || isset($filters['pinned'])): ?>
                <button type="button" class="btn btn-sm btn-light-danger" onclick="clearAllFilters()" title="Limpar Filtros">
                    <i class="ki-duotone ki-cross fs-6">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Lista de conversas -->
        <div class="conversations-list-items">
                <?php if (empty($conversations)): ?>
                <div class="empty-state">
                    <i class="ki-duotone ki-message-text">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <h5>Nenhuma conversa</h5>
                    <p class="text-muted">Aguarde novas mensagens</p>
                    </div>
                <?php else: ?>
                                <?php foreach ($conversations as $conv): ?>
                    <?php
                    $channelIcon = match($conv['channel'] ?? 'chat') {
                        'whatsapp' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="#25D366" style="vertical-align: middle;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>',
                        'email' => '✉️',
                        'chat' => '💬',
                        default => '💬'
                    };
                    
                    $channelName = match($conv['channel'] ?? 'chat') {
                        'whatsapp' => 'WhatsApp',
                        'email' => 'Email',
                        'chat' => 'Chat',
                        default => 'Chat'
                    };
                    
                    $isActive = ($selectedConversationId == $conv['id']);
                    ?>
                    <?php
                    // Usar first_response_at calculado das mensagens se disponível, senão usar o campo da conversa
                    $firstResponseAt = !empty($conv['first_response_at_calc']) ? $conv['first_response_at_calc'] : ($conv['first_response_at'] ?? '');
                    $lastContactAt = $conv['last_contact_message_at'] ?? '';
                    $lastAgentAt = $conv['last_agent_message_at'] ?? '';
                    $lastMessageFromAgent = !empty($lastAgentAt) && (empty($lastContactAt) || strtotime($lastAgentAt) >= strtotime($lastContactAt));
                    ?>
                    <div class="conversation-item <?= $isActive ? 'active' : '' ?> <?= !empty($conv['pinned']) ? 'pinned' : '' ?>" 
                         data-conversation-id="<?= $conv['id'] ?>"
                         data-status="<?= htmlspecialchars($conv['status'] ?? 'open') ?>"
                         data-created-at="<?= htmlspecialchars($conv['created_at'] ?? '') ?>"
                         data-first-response-at="<?= htmlspecialchars($firstResponseAt) ?>"
                         data-last-message-at="<?= htmlspecialchars($conv['last_message_at'] ?? '') ?>"
                         data-last-contact-message-at="<?= htmlspecialchars($lastContactAt) ?>"
                         data-last-agent-message-at="<?= htmlspecialchars($lastAgentAt) ?>"
                         data-agent-id="<?= htmlspecialchars($conv['agent_id'] ?? '') ?>"
                         data-onclick="selectConversation">
                        <div class="d-flex gap-3 w-100">
                            <!-- Checkbox para seleção em massa -->
                            <div class="flex-shrink-0 d-flex align-items-center">
                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input conversation-checkbox" type="checkbox" value="<?= $conv['id'] ?>" 
                                           onclick="event.stopPropagation(); toggleBulkSelection();">
                                </label>
                            </div>
                            <!-- Avatar -->
                            <div class="symbol symbol-45px flex-shrink-0">
                                <?php
                                $name = $conv['contact_name'] ?? 'NN';
                                $parts = explode(' ', $name);
                                $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                                
                                if (!empty($conv['contact_avatar'])):
                                ?>
                                    <div class="symbol-label"><img src="<?= htmlspecialchars($conv['contact_avatar']) ?>" alt="<?= htmlspecialchars($name) ?>" class="h-45px w-45px rounded" style="object-fit: cover;"></div>
                                <?php else: ?>
                                    <div class="symbol-label bg-light-primary text-primary fw-bold"><?= $initials ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Conteúdo -->
                            <div class="flex-grow-1 min-w-0">
                                <div class="conversation-item-header">
                                    <div class="conversation-item-name d-flex align-items-center gap-2">
                                        <?php if (!empty($conv['pinned'])): ?>
                                        <i class="ki-duotone ki-pin fs-7 text-warning" title="Fixada">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <?php endif; ?>
                                                        <?php if (!empty($conv['is_spam'])): ?>
                                                            <span class="badge badge-sm badge-danger" title="Marcada como spam">🚫 SPAM</span>
                                                        <?php endif; ?>
                                                        <?php
                                                        $nameRaw = $conv['contact_name'] ?? 'Sem nome';
                                                        $maxName = 22;
                                                        $displayName = mb_strlen($nameRaw) > $maxName ? mb_substr($nameRaw, 0, $maxName) . '...' : $nameRaw;
                                                        echo htmlspecialchars($displayName);
                                                        ?>
                                    </div>
                            <div class="conversation-item-time d-flex align-items-center gap-2">
                                <?php
                                if (!empty($conv['last_message_at'])) {
                                    $time = strtotime($conv['last_message_at']);
                                    $diff = time() - $time;
                                    if ($diff < 60) {
                                        echo 'Agora';
                                    } elseif ($diff < 3600) {
                                        echo floor($diff / 60) . 'min';
                                    } elseif ($diff < 86400) {
                                        echo date('H:i', $time);
                                    } else {
                                        echo date('d/m', $time);
                                    }
                                } else {
                                    echo '-';
                                }
                                ?>
                                <div class="dropdown conversation-item-actions">
                                    <button type="button" class="btn btn-sm btn-icon btn-light p-0" 
                                            data-bs-toggle="dropdown" 
                                            aria-expanded="false"
                                            onclick="event.stopPropagation();">
                                        <i class="ki-duotone ki-setting-2 text-muted">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                            <span class="path4"></span>
                                            <span class="path5"></span>
                                        </i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end" data-conversation-id="<?= $conv['id'] ?>">
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="event.stopPropagation(); togglePin(<?= $conv['id'] ?>, <?= !empty($conv['pinned']) ? 'true' : 'false' ?>); return false;">
                                                <i class="ki-duotone ki-pin fs-7 me-2 <?= !empty($conv['pinned']) ? 'text-warning' : '' ?>">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                <?= !empty($conv['pinned']) ? 'Desfixar' : 'Fixar' ?>
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="event.stopPropagation(); markConversationAsRead(<?= $conv['id'] ?>); return false;">
                                                <i class="ki-duotone ki-check fs-7 me-2 text-success">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                Marcar como Lido
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="event.stopPropagation(); markConversationAsUnread(<?= $conv['id'] ?>); return false;">
                                                <i class="ki-duotone ki-eye-slash fs-7 me-2 text-danger">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                Marcar como Não Lido
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="event.stopPropagation(); showReminderModal(<?= $conv['id'] ?>); return false;">
                                                <i class="ki-duotone ki-notification-bing fs-7 me-2 text-primary">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                Agendar Lembrete
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="conversation-item-preview">
                            <?= htmlspecialchars(mb_substr($conv['last_message'] ?? 'Sem mensagens', 0, 37)) ?>
                            <?= mb_strlen($conv['last_message'] ?? '') > 37 ? '...' : '' ?>
                        </div>
                        <div class="conversation-item-meta">
                            <span class="conversation-item-channel">
                                <?= $channelIcon ?> <?= $channelName ?>
                            </span>
                            <span class="conversation-item-tags d-flex gap-1 flex-wrap">
                                <?php if (!empty($conv['tags']) && is_array($conv['tags'])): ?>
                                    <?php foreach (array_slice($conv['tags'], 0, 2) as $tag): ?>
                                        <span class="badge badge-sm" style="background-color: <?= htmlspecialchars($tag['color'] ?? '#009ef7') ?>20; color: <?= htmlspecialchars($tag['color'] ?? '#009ef7') ?>;">
                                            <?= htmlspecialchars($tag['name']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </span>
                            <?php if (!empty($conv['unread_count'])): ?>
                                <span class="conversation-item-badge"><?= $conv['unread_count'] ?></span>
                            <?php endif; ?>
                        </div>
                            </div><!-- fim flex-grow -->
                        </div><!-- fim d-flex -->
                    </div>
                <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
    
    <!-- COLUNA 2: ÁREA DE CHAT -->
    <div class="chat-area">
        
        <!-- Header do Chat (sempre presente, mas pode estar oculto) -->
        <div class="chat-header" id="chatHeader" style="<?= empty($selectedConversation) ? 'display: none;' : '' ?>">
            <div class="chat-header-info">
                <div class="symbol symbol-circle symbol-50px">
                    <?php
                    $initials = '';
                    if (!empty($selectedConversation)) {
                        $name = $selectedConversation['contact_name'] ?? 'NN';
                        $parts = explode(' ', $name);
                        $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                    }
                    ?>
                    <div class="symbol-label bg-light-primary text-primary fs-3 fw-bold"><?= $initials ?></div>
                </div>
                <div>
                    <div class="chat-header-title"><?= htmlspecialchars($selectedConversation['contact_name'] ?? 'Sem nome') ?></div>
                    <div class="chat-header-subtitle">
                        <?php
                        $channelIcon = match($selectedConversation['channel'] ?? 'chat') {
                            'whatsapp' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="#25D366" style="vertical-align: middle; margin-right: 4px;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg> WhatsApp',
                            'email' => '✉️ Email',
                            'chat' => '💬 Chat',
                            default => '💬 Chat'
                        };
                        echo $channelIcon;
                        ?>
                        •
                        <?php
                        $statusClass = match($selectedConversation['status'] ?? 'open') {
                            'open' => 'success',
                            'resolved' => 'info',
                            'closed' => 'dark',
                            default => 'secondary'
                        };
                        $statusText = match($selectedConversation['status'] ?? 'open') {
                            'open' => 'Aberta',
                            'resolved' => 'Resolvida',
                            'closed' => 'Fechada',
                            default => 'Desconhecida'
                        };
                        ?>
                        <span class="badge badge-sm badge-light-<?= $statusClass ?>"><?= $statusText ?></span>
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <!-- Busca de mensagens -->
                <div class="position-relative d-flex gap-2" style="width: 250px;">
                    <div class="position-relative flex-grow-1">
                        <input type="text" id="messageSearch" class="form-control form-control-sm form-control-solid ps-10" 
                               placeholder="Buscar mensagens...">
                        <i class="ki-duotone ki-magnifier position-absolute top-50 translate-middle-y start-0 ms-3 fs-6">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div id="messageSearchResults" class="position-absolute top-100 start-0 w-100 bg-body border rounded shadow-lg d-none" style="max-height: 300px; overflow-y: auto; z-index: 1000; margin-top: 5px;">
                            <!-- Resultados da busca serão inseridos aqui -->
                        </div>
                    </div>
                    <button class="btn btn-sm btn-icon btn-light-primary" onclick="showMessageSearchFilters()" title="Filtros avançados" id="messageSearchFiltersBtn">
                        <i class="ki-duotone ki-filter fs-6">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </button>
                </div>
                
                <button class="btn btn-sm btn-icon btn-light-primary" onclick="toggleConversationSidebar()" title="Detalhes da conversa">
                    <i class="ki-duotone ki-burger-menu fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                    </i>
                </button>
            </div>
        </div>
        
        <!-- Banner de IA Ativa (aparece quando IA está ativa) -->
        <div id="aiActiveBanner" class="ai-active-banner d-none" style="display: none !important;">
            <div class="ai-active-banner-content">
                <div class="ai-active-banner-icon">
                    <i class="ki-duotone ki-robot fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                    </i>
                </div>
                <div class="ai-active-banner-info">
                    <div class="ai-active-banner-title">
                        <span class="ai-agent-name">Agente de IA Ativo</span>
                        <span class="badge badge-sm badge-light-success ms-2">Ativo</span>
                    </div>
                    <div class="ai-active-banner-subtitle">
                        <span class="ai-agent-type"></span>
                        <span class="ai-messages-count ms-2"></span>
                    </div>
                </div>
                <div class="ai-active-banner-actions">
                    <button class="btn btn-sm btn-light-primary" id="aiHistoryButton">
                        <i class="ki-duotone ki-eye fs-6">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Ver Histórico
                    </button>
                    <button class="btn btn-sm btn-icon btn-light-danger" id="removeAIButton" title="Remover IA">
                        <i class="ki-duotone ki-cross fs-6">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mensagens (sempre presente) -->
        <div class="chat-messages" id="chatMessages">
            <?php if (!empty($selectedConversation)): ?>
                <?php if (!empty($selectedConversation['messages'])): ?>
                    <?php 
                    $lastDate = null;
                    $isFirstMessage = true;
                    foreach ($selectedConversation['messages'] as $msg): 
                        $msgCreatedAt = $msg['created_at'] ?? date('Y-m-d H:i:s');
                        
                        // Verificar se precisa adicionar separador de data
                        // Sempre adicionar antes da primeira mensagem ou quando o dia muda
                        if ($isFirstMessage || ($lastDate !== null && isDifferentDay($lastDate, $msgCreatedAt))) {
                            echo renderDateSeparator($msgCreatedAt);
                            $lastDate = $msgCreatedAt;
                            $isFirstMessage = false;
                        } elseif ($lastDate === null) {
                            // Garantir que sempre há um separador antes da primeira mensagem
                            echo renderDateSeparator($msgCreatedAt);
                            $lastDate = $msgCreatedAt;
                            $isFirstMessage = false;
                        }
                    ?>
                        <?php
                        // Garantir que as chaves existem para evitar warnings
                        $msgType = $msg['type'] ?? 'message';
                        $msgDirection = $msg['direction'] ?? 'outgoing';
                        $msgContent = $msg['content'] ?? '';
                        $msgSenderName = $msg['sender_name'] ?? 'Sistema';
                        $msgCreatedAt = $msg['created_at'] ?? date('Y-m-d H:i:s');
                        ?>
                        
                        <?php if ($msgType === 'system'): ?>
                            <!-- Evento do sistema -->
                            <div class="chat-message system">
                                <div class="message-content">
                                    <div class="message-bubble">
                                        <i class="ki-duotone ki-information fs-5 me-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <?= htmlspecialchars($msgContent) ?>
                                    </div>
                                </div>
                            </div>
                        
                        <?php elseif ($msgType === 'note'): ?>
                            <!-- Nota interna - Alinhada à direita como mensagens enviadas -->
                            <div class="chat-message note outgoing" data-message-id="<?= $msg['id'] ?? '' ?>" data-timestamp="<?= strtotime($msgCreatedAt) * 1000 ?>">
                                <div class="message-content">
                                    <div class="message-bubble">
                                        <div class="note-header">
                                            <i class="ki-duotone ki-note fs-6">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            Nota Interna • <?= htmlspecialchars($msgSenderName) ?>
                                        </div>
                                        <?= str_replace("\n", "<br>", htmlspecialchars($msgContent)) ?>
                                    </div>
                                    <div class="message-time"><?= date('H:i', strtotime($msgCreatedAt)) ?></div>
                                </div>
                            </div>
                        
                                            <?php else: ?>
                            <!-- Mensagem normal -->
                            <?php
                            // Verificar se é mensagem de IA
                            $isAIMessage = !empty($msg['ai_agent_id']);
                            $aiAgentName = $msg['ai_agent_name'] ?? 'Assistente IA';
                            $aiAgentInitials = '';
                            if ($isAIMessage && $aiAgentName) {
                                $parts = explode(' ', $aiAgentName);
                                $aiAgentInitials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                            }
                            ?>
                            <div class="chat-message <?= $msgDirection === 'incoming' ? 'incoming' : 'outgoing' ?>" data-message-id="<?= $msg['id'] ?? '' ?>" data-timestamp="<?= strtotime($msgCreatedAt) * 1000 ?>">
                                <?php if ($msgDirection === 'incoming'): ?>
                                    <?php if (!empty($selectedConversation['contact_avatar'])): ?>
                                        <img src="<?= htmlspecialchars($selectedConversation['contact_avatar']) ?>" alt="Avatar" class="message-avatar" style="object-fit: cover;">
                                    <?php else: ?>
                                        <div class="message-avatar"><?= $initials ?></div>
                                    <?php endif; ?>
                                <?php elseif ($isAIMessage && $msgDirection === 'outgoing'): ?>
                                    <!-- Avatar do agente de IA -->
                                    <div class="message-avatar ai-agent-avatar" title="<?= htmlspecialchars($aiAgentName) ?>">
                                        <?= $aiAgentInitials ?: '🤖' ?>
                                    </div>
                                <?php endif; ?>
                                <div class="message-content">
                                    <div class="message-actions">
                                        <button class="message-actions-btn" onclick="replyToMessage(<?= $msg['id'] ?? 0 ?>, '<?= htmlspecialchars($msgSenderName, ENT_QUOTES) ?>', '<?= htmlspecialchars(substr($msgContent, 0, 100), ENT_QUOTES) ?>')" title="Responder">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="9 10 4 15 9 20"></polyline>
                                                <path d="M20 4v7a4 4 0 0 1-4 4H4"></path>
                                            </svg>
                                        </button>
                                        <button class="message-actions-btn" onclick="forwardMessage(<?= $msg['id'] ?? 0 ?>)" title="Encaminhar">
                                            <i class="ki-duotone ki-arrow-right fs-6">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </button>
                                    </div>
                                            <?php
                                    // Verificar se é uma mensagem citada/reply
                                    $isQuoted = strpos($msgContent, '↩️') === 0;
                                    
                                    // Verificar se é apenas áudio (sem texto e sem outros anexos)
                                    $hasOnlyAudio = false;
                                    if (!empty($msg['attachments']) && empty($msgContent) && !$isQuoted) {
                                        $attachments = is_string($msg['attachments']) ? json_decode($msg['attachments'], true) : $msg['attachments'];
                                        if (is_array($attachments)) {
                                            $audioCount = 0;
                                            $totalCount = count($attachments);
                                            foreach ($attachments as $att) {
                                                $attType = $att['type'] ?? '';
                                                $attMime = $att['mime_type'] ?? $att['mimetype'] ?? '';
                                                if ($attType === 'audio' || strpos($attMime, 'audio/') === 0) {
                                                    $audioCount++;
                                                }
                                            }
                                            $hasOnlyAudio = ($audioCount === $totalCount && $totalCount > 0);
                                        }
                                    }
                                    $bubbleClass = $hasOnlyAudio ? 'message-bubble audio-only' : 'message-bubble';
                                    if ($isAIMessage) {
                                        $bubbleClass .= ' ai-message';
                                    }
                                    ?>
                                    <?php if ($isAIMessage && $msgDirection === 'outgoing'): ?>
                                        <div class="ai-message-badge" title="Mensagem enviada por <?= htmlspecialchars($aiAgentName) ?>">
                                            <div class="ai-avatar-mini">
                                                <?= $aiAgentInitials ?: '🤖' ?>
                                            </div>
                                            <i class="ki-duotone ki-robot fs-7">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                                <span class="path4"></span>
                                            </i>
                                            <span class="ai-badge-text"><?= htmlspecialchars($aiAgentName) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="<?= $bubbleClass ?>">
                                        <?php 
                                        // Verificar se tem reply através do campo quoted_message_id
                                        $hasQuote = !empty($msg['quoted_message_id']) || $isQuoted;
                                        if ($hasQuote): 
                                            // Priorizar campos separados, senão extrair do content
                                            if (!empty($msg['quoted_message_id'])) {
                                                $quotedMsgId = $msg['quoted_message_id'];
                                                $quotedSenderName = $msg['quoted_sender_name'] ?? 'Remetente';
                                                $quotedText = $msg['quoted_text'] ?? '';
                                                // Limitar texto citado
                                                if (mb_strlen($quotedText) > 100) {
                                                    $quotedText = mb_substr($quotedText, 0, 100) . '...';
                                                }
                                                $actualContent = $msgContent; // Content não foi modificado
                                            } else {
                                                // Mensagem antiga com formato antigo (↩️ no content)
                                                $lines = explode("\n", $msgContent, 2);
                                                $quotedText = substr($lines[0], 2); // Remove "↩️ "
                                                $actualContent = $lines[1] ?? '';
                                                $quotedMsgId = null;
                                                $quotedSenderName = 'Remetente';
                                            }
                                        ?>
                                            <div class="quoted-message" onclick="console.log('Quoted message clicado, ID:', <?= $quotedMsgId ?: 'null' ?>); <?= $quotedMsgId ? "scrollToMessage({$quotedMsgId})" : "console.log('Sem ID para scroll')" ?>" title="<?= $quotedMsgId ? 'Clique para ver a mensagem original' : 'Mensagem original não disponível' ?>" data-quoted-id="<?= $quotedMsgId ?: '' ?>">
                                                <div class="quoted-message-header"><?= htmlspecialchars($quotedSenderName) ?></div>
                                                <div class="quoted-message-content"><?= htmlspecialchars($quotedText) ?></div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($msg['attachments']) && is_array($msg['attachments'])): ?>
                                            <?php foreach ($msg['attachments'] as $attachment): ?>
                                                <?= renderAttachment($attachment) ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <?php if (!empty($msgContent)): ?>
                                            <div class="<?= (!empty($msg['attachments']) || $isQuoted) ? 'mt-2' : '' ?>">
                                                <?= str_replace("\n", "<br>", htmlspecialchars($isQuoted ? $actualContent : $msgContent)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="message-time">
                                        <?= date('H:i', strtotime($msgCreatedAt)) ?>
                                        <?php if ($msgDirection === 'outgoing'): ?>
                                            <?= renderMessageStatus($msg) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                    <?php 
                        // Atualizar última data processada
                        $lastDate = $msgCreatedAt;
                    endforeach; ?>
                                            <?php else: ?>
                    <div class="empty-state">
                        <i class="ki-duotone ki-message-text">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <h5>Nenhuma mensagem</h5>
                        <p class="text-muted">Envie a primeira mensagem</p>
                    </div>
                                            <?php endif; ?>
            <?php else: ?>
                <!-- Estado vazio - nenhuma conversa selecionada -->
                <div class="empty-state">
                    <i class="ki-duotone ki-message-text-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <h3>Selecione uma conversa</h3>
                    <p class="text-muted">Escolha uma conversa da lista para começar</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Campo de Envio (sempre presente, mas pode estar oculto) -->
        <div class="chat-input" id="chatInput" style="<?= empty($selectedConversation) ? 'display: none;' : '' ?>">
                <!-- Preview de Reply -->
                <div id="replyPreview" class="reply-preview d-none">
                    <div class="reply-preview-content">
                        <div class="reply-preview-header" id="replyPreviewHeader"></div>
                        <div class="reply-preview-text" id="replyPreviewText"></div>
                    </div>
                    <button class="reply-preview-close" onclick="cancelReply()" title="Cancelar resposta">
                        <i class="ki-duotone ki-cross fs-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </button>
                </div>
                
                <div class="chat-input-toolbar">
                    <button class="btn btn-sm btn-icon btn-light-primary" title="Anexar arquivo" onclick="attachFile()">
                        <i class="ki-duotone ki-paper-clip fs-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </button>
                    <button type="button" class="btn btn-sm btn-light-secondary d-none" id="cancelRecordingBtn" title="Cancelar gravação" onclick="cancelRecording()">
                        <i class="ki-duotone ki-cross-circle fs-3"><span class="path1"></span><span class="path2"></span></i>
                    </button>
                    <button class="btn btn-sm btn-icon btn-light-primary" id="recordAudioBtn" title="Gravar áudio" onclick="toggleAudioRecording()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                            <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                            <line x1="12" y1="19" x2="12" y2="23"></line>
                            <line x1="8" y1="23" x2="16" y2="23"></line>
                        </svg>
                    </button>
                    <button class="btn btn-sm btn-icon btn-light-primary" title="Agendar mensagem" onclick="showScheduleMessageModal()">
                        <i class="ki-duotone ki-calendar-tick fs-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                        </i>
                    </button>
                    <button class="btn btn-sm btn-icon btn-light-success" title="Ver mensagens agendadas" onclick="showScheduledMessagesModal()">
                        <i class="ki-duotone ki-calendar fs-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </button>
                    <button class="btn btn-sm btn-icon btn-light-primary" title="Emoji" onclick="toggleEmoji()">
                        <i class="ki-duotone ki-emoji-happy fs-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </button>
                    <button class="btn btn-sm btn-icon btn-light-primary" id="aiAssistantBtn" title="Assistente IA" onclick="showAIAssistantModal()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;">
                            <rect x="4" y="8" width="16" height="12" rx="2"></rect>
                            <rect x="9" y="2" width="6" height="6" rx="1"></rect>
                            <circle cx="9" cy="13" r="1.5" fill="currentColor"></circle>
                            <circle cx="15" cy="13" r="1.5" fill="currentColor"></circle>
                            <path d="M9 17h6"></path>
                        </svg>
                    </button>
                    <button class="btn btn-sm btn-icon btn-light-primary" title="Templates" onclick="showTemplatesModal()">
                        <i class="ki-duotone ki-message-text fs-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                    </button>
                    <button class="btn btn-sm btn-icon btn-light-primary" title="Variáveis" onclick="showVariablesModal()">
                        <i class="ki-duotone ki-code fs-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                        </i>
                    </button>
                    <button class="btn btn-sm btn-icon btn-light-primary" id="agentNameToggle" title="Enviar nome do agente" onclick="toggleAgentName()">
                        <i class="ki-duotone ki-user fs-3" id="agentNameToggleIcon">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </button>
                </div>
                
                <div class="position-relative">
                    <textarea id="messageInput" class="chat-input-textarea" placeholder="Digite sua mensagem..." rows="2"></textarea>
                    
                    <!-- Dropdown rápido de templates -->
                    <div id="templateQuickSelect" class="template-quick-select d-none">
                        <div class="template-quick-select-header">
                            <div class="d-flex align-items-center gap-2">
                                <i class="ki-duotone ki-message-text fs-4 text-primary">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <span class="fw-semibold">Templates</span>
                            </div>
                            <input type="text" id="templateQuickSearch" class="form-control form-control-sm mt-2" placeholder="Buscar template...">
                        </div>
                        <div class="template-quick-select-list" id="templateQuickList">
                            <div class="text-center text-muted py-5">
                                <i class="ki-duotone ki-loader fs-3x mb-3">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <div>Carregando templates...</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="chat-input-footer">
                    <div class="form-check form-check-sm form-check-custom">
                        <input class="form-check-input" type="checkbox" id="noteToggle">
                        <label class="form-check-label text-muted fs-7" for="noteToggle">
                            <i class="ki-duotone ki-note fs-6 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Nota Interna
                        </label>
                    </div>
                    
                    <button class="btn btn-sm btn-primary" onclick="sendMessage()">
                        <i class="ki-duotone ki-send fs-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Enviar
                    </button>
                </div>
        </div>
        
    </div>
    
    <?php include __DIR__ . '/sidebar-conversation.php'; ?>
    
</div>

<!-- MODAL: Templates de Mensagens -->
<div class="modal fade" id="kt_modal_templates" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Templates de Mensagens</h2>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-light-warning" onclick="showPersonalTemplatesModal()" title="Gerenciar meus templates">
                        <i class="ki-duotone ki-user fs-5 me-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Meus Templates
                    </button>
                    <button type="button" class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal" aria-label="Fechar">
                        <i class="ki-duotone ki-cross fs-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </button>
                </div>
            </div>
            <div class="modal-body">
                <div class="mb-5">
                    <input type="text" id="templateSearch" class="form-control form-control-solid" placeholder="Buscar template...">
                </div>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-row-dashed align-middle gs-0 gy-4">
                        <thead>
                            <tr class="fw-bold text-muted">
                                <th class="min-w-200px">Nome</th>
                                <th class="min-w-150px">Categoria</th>
                                <th class="text-end min-w-100px">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="templatesList">
                            <tr>
                                <td colspan="3" class="text-center text-muted py-10">
                                    <i class="ki-duotone ki-loader fs-3x mb-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <div>Carregando templates...</div>
                                        </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Assistente IA -->
<div class="modal fade" id="kt_modal_ai_assistant" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-50px me-3">
                        <div class="symbol-label bg-light-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 30px; height: 30px;">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <circle cx="9" cy="9" r="1.5"></circle>
                                <circle cx="15" cy="9" r="1.5"></circle>
                                <path d="M9 15h6"></path>
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-0">Assistente IA</h2>
                        <div class="text-muted fs-7" id="aiAgentInfo">
                            <span class="spinner-border spinner-border-sm text-primary me-2" role="status" style="width: 12px; height: 12px;"></span>
                            Carregando agente...
                        </div>
                    </div>
                </div>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body pt-5">
                <div id="aiAssistantLoading" class="text-center py-15 d-none">
                    <div class="mb-5">
                        <span class="spinner-border spinner-border-lg text-primary mb-3" role="status" style="width: 3rem; height: 3rem;"></span>
                    </div>
                    <div class="fw-semibold fs-5 mb-2">Carregando funcionalidades...</div>
                    <div class="text-muted">Aguarde enquanto preparamos o Assistente IA</div>
                </div>
                
                <div id="aiAssistantContent" class="d-none">
                    <!-- Funcionalidade: Gerar Resposta -->
                    <div class="card card-flush shadow-sm mb-7" id="aiFeatureGenerateResponse">
                        <div class="card-header border-0 pt-5">
                <div class="card-title">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-45px me-3">
                                        <div class="symbol-label bg-light-primary">
                                            <i class="ki-duotone ki-message-text text-primary fs-2x">
                            <span class="path1"></span>
                            <span class="path2"></span>
                                                <span class="path3"></span>
                        </i>
                    </div>
                </div>
                                    <div>
                                        <h3 class="fw-bold mb-1">Gerar Resposta</h3>
                                        <p class="text-muted fs-7 mb-0">Gera sugestões inteligentes baseadas no contexto da conversa</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="row g-4 mb-6">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold mb-2">
                                        <i class="ki-duotone ki-notepad fs-6 text-muted me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                                        Tom da Resposta
                                    </label>
                                    <select id="aiResponseTone" class="form-select form-select-solid">
                                        <option value="professional">💼 Profissional</option>
                                        <option value="friendly">😊 Amigável</option>
                                        <option value="formal">📋 Formal</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold mb-2">
                                        <i class="ki-duotone ki-abstract-26 fs-6 text-muted me-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                            <span class="path4"></span>
                                        </i>
                                        Quantidade de Sugestões
                                    </label>
                                    <select id="aiResponseCount" class="form-select form-select-solid">
                                        <option value="1">1 sugestão</option>
                                        <option value="2">2 sugestões</option>
                                        <option value="3" selected>3 sugestões</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div id="aiResponseResults" class="d-none">
                                <div class="separator separator-dashed my-6"></div>
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="d-flex align-items-center">
                                        <i class="ki-duotone ki-check-circle fs-2x text-success me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <h4 class="fw-bold mb-0">Sugestões Geradas</h4>
                                    </div>
                                    <button class="btn btn-sm btn-light-primary" onclick="loadAIResponseHistory()" title="Ver histórico">
                                        <i class="ki-duotone ki-time fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Histórico
                        </button>
                            </div>
                                <div id="aiResponseSuggestions" class="mb-4"></div>
                            </div>
                            
                            <!-- Histórico de Respostas -->
                            <div id="aiResponseHistory" class="d-none">
                                <div class="separator separator-dashed my-6"></div>
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h4 class="fw-bold mb-0">
                                        <i class="ki-duotone ki-time fs-2x text-primary me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Histórico de Respostas
                                    </h4>
                                    <button class="btn btn-sm btn-light" onclick="hideAIResponseHistory()">
                                        <i class="ki-duotone ki-cross fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Fechar
                                    </button>
                                </div>
                                <div id="aiResponseHistoryContent" class="mb-4">
                                    <div class="text-center py-10">
                                        <span class="spinner-border spinner-border-sm text-primary mb-3" role="status"></span>
                                        <div class="text-muted">Carregando histórico...</div>
                                    </div>
                                </div>
                            </div>
                            
                            <button class="btn btn-primary w-100" onclick="generateAIResponse()" id="aiGenerateBtn">
                                <span class="indicator-label">
                                    <i class="ki-duotone ki-abstract-26 fs-2 me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                    </i>
                                    Gerar Respostas
                                </span>
                                <span class="indicator-progress d-none">
                                    Gerando...
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                </span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Outras Funcionalidades -->
                    <div class="mb-5">
                        <h4 class="fw-bold mb-4">
                            <i class="ki-duotone ki-setting-3 fs-2 text-primary me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            Outras Funcionalidades
                        </h4>
                        <div class="row g-4" id="aiOtherFeatures">
                            <!-- Será preenchido dinamicamente -->
                        </div>
                    </div>
                </div>
                
                <div id="aiAssistantError" class="alert alert-danger d-none">
                    <div class="d-flex align-items-center">
                        <i class="ki-duotone ki-information-5 fs-2x me-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div>
                            <div class="fw-bold">Erro</div>
                            <div id="aiAssistantErrorMessage"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Filtros Avançados de Busca de Mensagens -->
<div class="modal fade" id="kt_modal_message_search_filters" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-600px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Filtros de Busca</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body">
                <form id="messageSearchFiltersForm">
                    <div class="mb-5">
                        <label class="form-label fw-semibold">Tipo de Mensagem:</label>
                        <select id="filterMessageType" class="form-select form-select-solid">
                            <option value="">Todos</option>
                            <option value="text">Texto</option>
                            <option value="image">Imagem</option>
                            <option value="video">Vídeo</option>
                            <option value="audio">Áudio</option>
                            <option value="document">Documento</option>
                            <option value="location">Localização</option>
                            <option value="note">Nota Interna</option>
                        </select>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-semibold">Remetente:</label>
                        <select id="filterSenderType" class="form-select form-select-solid">
                            <option value="">Todos</option>
                            <option value="contact">Contato</option>
                            <option value="agent">Agente</option>
                            <option value="ai">Agente de IA</option>
                        </select>
                    </div>
                    
                    <div class="mb-5" id="filterSenderIdContainer" style="display: none;">
                        <label class="form-label fw-semibold">Agente Específico:</label>
                        <select id="filterSenderId" class="form-select form-select-solid">
                            <option value="">Todos os agentes</option>
                            <?php 
                            $agents = \App\Models\User::getAgents();
                            foreach ($agents as $agent): 
                            ?>
                                <option value="<?= $agent['id'] ?>"><?= htmlspecialchars($agent['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-semibold">Data Inicial:</label>
                        <input type="date" id="filterDateFrom" class="form-control form-control-solid">
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-semibold">Data Final:</label>
                        <input type="date" id="filterDateTo" class="form-control form-control-solid">
                    </div>
                    
                    <div class="mb-5">
                        <div class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" id="filterHasAttachments" value="1">
                            <label class="form-check-label" for="filterHasAttachments">
                                Apenas mensagens com anexos
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light" onclick="clearMessageSearchFilters()">Limpar</button>
                        <button type="button" class="btn btn-primary" onclick="applyMessageSearchFilters()">Aplicar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Variáveis Disponíveis -->
<div class="modal fade" id="kt_modal_variables" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Variáveis Disponíveis</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body">
                <div class="mb-5">
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="ki-duotone ki-information-5 fs-2x me-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div>
                            <div class="fw-bold">Como usar variáveis</div>
                            <div class="fs-7">Clique em uma variável para inseri-la automaticamente no campo de mensagem. As variáveis serão substituídas pelos valores reais quando a mensagem for enviada.</div>
                        </div>
                    </div>
                </div>
                <div class="row g-3" id="variablesList">
                    <div class="col-12 text-center py-10">
                        <span class="spinner-border spinner-border-sm text-primary mb-3" role="status"></span>
                        <div class="text-muted">Carregando variáveis...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Gerenciar Templates Pessoais -->
<div class="modal fade" id="kt_modal_personal_templates" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-800px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">
                    <i class="ki-duotone ki-user fs-2x text-warning me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Meus Templates de Mensagens
                </h2>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-primary" onclick="showCreatePersonalTemplateModal()">
                        <i class="ki-duotone ki-plus fs-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Novo Template
                    </button>
                    <button type="button" class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal" aria-label="Fechar">
                        <i class="ki-duotone ki-cross fs-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </button>
                </div>
            </div>
            <div class="modal-body">
                <div class="mb-5">
                    <input type="text" id="personalTemplateSearch" class="form-control form-control-solid" placeholder="Buscar meus templates...">
                </div>
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-row-dashed align-middle gs-0 gy-4">
                        <thead>
                            <tr class="fw-bold text-muted">
                                <th class="min-w-200px">Nome</th>
                                <th class="min-w-150px">Categoria</th>
                                <th class="min-w-300px">Conteúdo</th>
                                <th class="text-end min-w-150px">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="personalTemplatesList">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-10">
                                    <span class="spinner-border spinner-border-sm text-primary mb-3" role="status"></span>
                                    <div>Carregando templates...</div>
                                        </td>
                                    </tr>
                            </tbody>
                        </table>
                    </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Criar/Editar Template Pessoal -->
<div class="modal fade" id="kt_modal_personal_template_form" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-700px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="personalTemplateFormTitle">Novo Template Pessoal</h2>
                <button type="button" class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal" aria-label="Fechar">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </button>
            </div>
            <div class="modal-body">
                <form id="personalTemplateForm">
                    <input type="hidden" id="personalTemplateId" name="id">
                    <input type="hidden" name="is_personal" value="1">
                    
                    <div class="mb-5">
                        <label class="form-label fw-semibold">Nome do Template <span class="text-danger">*</span></label>
                        <input type="text" id="personalTemplateName" name="name" class="form-control form-control-solid" placeholder="Ex: Saudação Inicial" required>
                        <div class="form-text">Dê um nome descritivo para identificar este template facilmente.</div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-semibold">Categoria</label>
                        <input type="text" id="personalTemplateCategory" name="category" class="form-control form-control-solid" placeholder="Ex: Saudação, Follow-up, Suporte">
                        <div class="form-text">Categoria opcional para organizar seus templates.</div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-semibold">Descrição</label>
                        <textarea id="personalTemplateDescription" name="description" class="form-control form-control-solid" rows="2" placeholder="Descrição opcional do template"></textarea>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-semibold">
                            Conteúdo do Template <span class="text-danger">*</span>
                            <button type="button" class="btn btn-sm btn-light-primary ms-2" onclick="showVariablesModal()" title="Ver variáveis disponíveis">
                                <i class="ki-duotone ki-code fs-6">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                                Variáveis
                            </button>
                        </label>
                        <textarea id="personalTemplateContent" name="content" class="form-control form-control-solid" rows="6" placeholder="Digite o conteúdo do template. Use {{variavel}} para variáveis dinâmicas." required></textarea>
                        <div class="form-text">
                            Use variáveis como <code>{{contact.name}}</code>, <code>{{agent.name}}</code>, <code>{{date}}</code>, etc.
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <div class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" id="personalTemplateActive" name="is_active" value="1" checked>
                            <label class="form-check-label" for="personalTemplateActive">
                                Template ativo
                            </label>
                        </div>
                        <div class="form-text">Templates inativos não aparecerão na lista de seleção.</div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="personalTemplateSubmitBtn">
                            <span class="indicator-label">Salvar Template</span>
                            <span class="indicator-progress d-none">
                                Salvando...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Atribuir Conversa -->
<div class="modal fade" id="kt_modal_assign" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Atribuir Conversa</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body">
                <form id="assignForm">
                    <div class="mb-5">
                                    <label class="form-label fw-semibold">Agente:</label>
                        <select id="assignAgent" class="form-select form-select-solid" required>
                            <option value="">Selecione um agente...</option>
                            <?php if (!empty($agents)): ?>
                                        <?php foreach ($agents as $agent): ?>
                                    <option value="<?= $agent['id'] ?>">
                                                <?= htmlspecialchars($agent['name']) ?>
                                        <?php if (!empty($agent['email'])): ?>
                                            (<?= htmlspecialchars($agent['email']) ?>)
                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                            <?php endif; ?>
                                    </select>
                                </div>
                    <div class="mb-5">
                        <label class="form-label fw-semibold">Setor (opcional):</label>
                        <select id="assignDepartment" class="form-select form-select-solid">
                            <option value="">Manter setor atual</option>
                                <?php if (!empty($departments)): ?>
                                        <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>">
                                                <?= htmlspecialchars($dept['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                            <?php endif; ?>
                                    </select>
                                </div>
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Atribuir</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Escalar de IA para Humano -->
<div class="modal fade" id="kt_modal_escalate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Escalar para Agente Humano</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body">
                <div class="alert alert-info d-flex align-items-center p-5 mb-5">
                    <i class="ki-duotone ki-information fs-2hx text-info me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1">Escalação de IA</h4>
                        <span>Esta conversa será transferida de um agente de IA para um agente humano. Você pode escolher um agente específico ou deixar o sistema atribuir automaticamente.</span>
                    </div>
                </div>
                <form id="escalateForm">
                    <input type="hidden" id="escalateConversationId" value="">
                    <div class="mb-5">
                        <label class="form-label fw-semibold">Agente (opcional):</label>
                        <select id="escalateAgent" class="form-select form-select-solid">
                            <option value="">Atribuir automaticamente</option>
                            <?php if (!empty($agents)): ?>
                                <?php foreach ($agents as $agent): ?>
                                    <option value="<?= $agent['id'] ?>">
                                        <?= htmlspecialchars($agent['name']) ?>
                                        <?php if (!empty($agent['email'])): ?>
                                            (<?= htmlspecialchars($agent['email']) ?>)
                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                            <?php endif; ?>
                                    </select>
                        <div class="form-text">Deixe em branco para atribuição automática baseada em disponibilidade e carga de trabalho.</div>
                                </div>
                                <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="ki-duotone ki-arrow-up fs-5 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Escalar Agora
                        </button>
                                </div>
                </form>
                            </div>
                        </div>
                    </div>
                </div>

<!-- MODAL: Mudar Setor -->
<div class="modal fade" id="kt_modal_change_department" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Mudar Setor</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body">
                <form id="changeDepartmentForm">
                    <input type="hidden" id="changeDepartmentConversationId" value="">
                    <div class="mb-5">
                        <label class="form-label fw-semibold">Setor:</label>
                        <select id="changeDepartmentSelect" class="form-select form-select-solid">
                            <option value="">Sem setor</option>
                            <?php if (!empty($departments)): ?>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>">
                                        <?= htmlspecialchars($dept['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <div class="form-text">Selecione um setor para esta conversa ou deixe "Sem setor" para remover.</div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ki-duotone ki-check fs-5 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Gerenciar Tags -->
<div class="modal fade" id="kt_modal_tags" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Gerenciar Tags</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
            </div>
            </div>
            <div class="modal-body">
                <div class="mb-5">
                    <input type="text" id="tagSearch" class="form-control form-control-solid" placeholder="Buscar tag...">
                </div>
                <div class="d-flex flex-wrap gap-2 mb-5" id="currentTags">
                    <!-- Tags atuais serão inseridas aqui -->
                </div>
                <div class="separator my-5"></div>
                <div class="mb-5">
                    <label class="form-label fw-semibold">Tags Disponíveis:</label>
                    <div class="d-flex flex-wrap gap-2" id="availableTags">
                        <!-- Tags disponíveis serão inseridas aqui -->
                    </div>
                </div>
                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" onclick="saveTags()">Salvar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Gerenciar Agentes do Contato -->
<div class="modal fade" id="kt_modal_contact_agents" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-600px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Gerenciar Agentes do Contato</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body">
                <div class="alert alert-info d-flex align-items-start p-4 mb-5">
                    <i class="ki-duotone ki-information-5 fs-2x me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div>
                        <div class="fw-semibold mb-1">Atribuição Automática</div>
                        <div class="fs-7">Quando uma conversa fechada for reaberta ou o contato chamar novamente após ter conversa fechada, será atribuído automaticamente ao agente principal.</div>
                    </div>
                </div>
                
                <input type="hidden" id="contactAgentsModalContactId" value="">
                
                <div class="mb-5">
                    <label class="form-label fw-semibold mb-3">Agentes Atribuídos:</label>
                    <div id="contactAgentsList" class="border rounded p-3" style="min-height: 100px; max-height: 300px; overflow-y: auto;">
                        <div class="text-muted fs-7 text-center py-3">Carregando...</div>
                    </div>
                </div>
                
                <div class="separator my-5"></div>
                
                <div class="mb-5">
                    <label class="form-label fw-semibold mb-3">Adicionar Novo Agente:</label>
                    <div class="d-flex gap-2">
                        <select id="addContactAgentSelect" class="form-select form-select-solid flex-grow-1">
                            <option value="">Selecione um agente...</option>
                            <?php if (!empty($agents)): ?>
                                <?php foreach ($agents as $agent): ?>
                                    <option value="<?= $agent['id'] ?>">
                                        <?= htmlspecialchars($agent['name']) ?>
                                        <?php if (!empty($agent['email'])): ?>
                                            (<?= htmlspecialchars($agent['email']) ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <button type="button" class="btn btn-primary" onclick="addContactAgentFromModal()">
                            <i class="ki-duotone ki-plus fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            Adicionar
                        </button>
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="addAsPrimaryAgent" value="1">
                        <label class="form-check-label" for="addAsPrimaryAgent">
                            Definir como agente principal
                        </label>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Editar Contato Inline -->
<div class="modal fade" id="kt_modal_edit_contact" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-600px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Editar Contato</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body">
                <form id="editContactForm">
                    <input type="hidden" id="editContactId" value="">
                    
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold required">Nome</label>
                            <input type="text" class="form-control form-control-solid" id="editContactName" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Sobrenome</label>
                            <input type="text" class="form-control form-control-solid" id="editContactLastName" name="last_name">
                        </div>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control form-control-solid" id="editContactEmail" name="email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Telefone</label>
                            <input type="text" class="form-control form-control-solid" id="editContactPhone" name="phone" placeholder="Ex: 5511999999999">
                        </div>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Cidade</label>
                            <input type="text" class="form-control form-control-solid" id="editContactCity" name="city">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">País</label>
                            <input type="text" class="form-control form-control-solid" id="editContactCountry" name="country">
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-semibold">Empresa</label>
                        <input type="text" class="form-control form-control-solid" id="editContactCompany" name="company">
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-semibold">Bio</label>
                        <textarea class="form-control form-control-solid" id="editContactBio" name="bio" rows="3" placeholder="Informações adicionais sobre o contato..."></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ki-duotone ki-check fs-5 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Gerenciar Participantes -->
<div class="modal fade" id="kt_modal_participants" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Gerenciar Participantes</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </div>
                                                </div>
            <div class="modal-body">
                <div class="mb-5" id="currentParticipants">
                    <!-- Participantes atuais serão inseridos aqui -->
                                                        </div>
                <div class="separator my-5"></div>
                <div class="mb-5">
                    <label class="form-label fw-semibold mb-2">Adicionar Participante:</label>
                    <div class="d-flex gap-2">
                        <select id="participantUserSelect" class="form-select form-select-solid flex-grow-1">
                            <option value="">Selecione um usuário...</option>
                        </select>
                        <button type="button" class="btn btn-primary" id="addParticipantBtn" onclick="addParticipant()">Adicionar</button>
                                                </div>
                                            </div>
                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Agendar Mensagem -->
<div class="modal fade" id="kt_modal_schedule_message" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Agendar Mensagem</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body">
                <form id="scheduleMessageForm">
                    <input type="hidden" id="schedule_conversation_id" name="conversation_id">
                    
                    <div class="mb-5">
                        <label class="form-label fw-semibold mb-2">Mensagem:</label>
                        <textarea class="form-control form-control-solid" id="schedule_message_content" name="content" rows="5" placeholder="Digite sua mensagem aqui..."></textarea>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-semibold mb-2">Anexar arquivo (opcional):</label>
                        <input type="file" class="form-control form-control-solid" id="schedule_message_attachment" name="attachment" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.txt">
                        <div class="form-text">Você pode anexar imagens, vídeos, áudios ou documentos</div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-semibold mb-2">Data e Hora:</label>
                        <div class="row">
                            <div class="col-6">
                                <input type="date" class="form-control form-control-solid" id="schedule_message_date" name="date" required>
                            </div>
                            <div class="col-6">
                                <input type="time" class="form-control form-control-solid" id="schedule_message_time" name="time" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-semibold mb-3">Opções:</label>
                        <div class="form-check form-check-custom form-check-solid mb-2">
                            <input class="form-check-input" type="checkbox" id="schedule_cancel_if_resolved" name="cancel_if_resolved">
                            <label class="form-check-label" for="schedule_cancel_if_resolved">
                                Cancelar se conversa foi resolvida
                            </label>
                        </div>
                        <div class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" id="schedule_cancel_if_responded" name="cancel_if_responded">
                            <label class="form-check-label" for="schedule_cancel_if_responded">
                                Cancelar se já foi respondida
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Agendar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Ver Mensagens Agendadas -->
<div class="modal fade" id="kt_modal_scheduled_messages" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-800px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Mensagens Agendadas</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body">
                <!-- Filtros -->
                <div class="mb-5">
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="scheduled_status_filter" id="filter_all" value="" checked>
                        <label class="btn btn-sm btn-outline btn-outline-dashed btn-active-light-primary" for="filter_all">Todas</label>
                        
                        <input type="radio" class="btn-check" name="scheduled_status_filter" id="filter_pending" value="pending">
                        <label class="btn btn-sm btn-outline btn-outline-dashed btn-active-light-warning" for="filter_pending">Pendentes</label>
                        
                        <input type="radio" class="btn-check" name="scheduled_status_filter" id="filter_sent" value="sent">
                        <label class="btn btn-sm btn-outline btn-outline-dashed btn-active-light-success" for="filter_sent">Enviadas</label>
                        
                        <input type="radio" class="btn-check" name="scheduled_status_filter" id="filter_cancelled" value="cancelled">
                        <label class="btn btn-sm btn-outline btn-outline-dashed btn-active-light-danger" for="filter_cancelled">Canceladas</label>
                        
                        <input type="radio" class="btn-check" name="scheduled_status_filter" id="filter_failed" value="failed">
                        <label class="btn btn-sm btn-outline btn-outline-dashed btn-active-light-dark" for="filter_failed">Falhadas</label>
                    </div>
                </div>
                
                <!-- Lista de mensagens -->
                <div id="scheduledMessagesList" style="max-height: 500px; overflow-y: auto;">
                    <div class="text-center py-10">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Agendar Lembrete -->
<div class="modal fade" id="kt_modal_reminder" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Agendar Lembrete</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body">
                <form id="reminderForm">
                    <input type="hidden" id="reminder_conversation_id" name="conversation_id">
                    
                    <div class="mb-5">
                        <label class="form-label fw-semibold mb-2">Data e Hora do Lembrete:</label>
                        <div class="row">
                            <div class="col-6">
                                <input type="date" class="form-control form-control-solid" id="reminder_date" name="date" required>
                            </div>
                            <div class="col-6">
                                <input type="time" class="form-control form-control-solid" id="reminder_time" name="time" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-semibold mb-2">Nota (opcional):</label>
                        <textarea class="form-control form-control-solid" id="reminder_note" name="note" rows="3" placeholder="Ex: Verificar se cliente respondeu"></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Criar Lembrete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Nova Conversa -->
<div class="modal fade" id="kt_modal_new_conversation" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Nova Conversa</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body">
                <form id="newConversationForm">
                    <div class="mb-5">
                        <label class="form-label fw-semibold mb-2">Nome do Contato:</label>
                        <input type="text" class="form-control form-control-solid" id="new_contact_name" name="name" placeholder="Nome completo" required>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-semibold mb-2">Telefone:</label>
                        <div class="input-group">
                            <span class="input-group-text">+55</span>
                            <input type="text" class="form-control form-control-solid" id="new_contact_phone" name="phone" placeholder="DDD + Número (ex: 11987654321)" maxlength="11" required>
                        </div>
                        <div class="form-text">Digite apenas DDD e número (ex: 11987654321)</div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-semibold mb-2">Mensagem:</label>
                        <textarea class="form-control form-control-solid" id="new_conversation_message" name="message" rows="4" placeholder="Digite sua mensagem aqui..." required></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Enviar Mensagem</span>
                            <span class="indicator-progress">Enviando...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Filtros Avançados -->
<div class="modal fade" id="kt_modal_advanced_filters" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-700px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Filtros Avançados</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <form id="advancedFiltersForm">
                    <!-- Canais (Multi-select) -->
                    <div class="mb-5">
                        <label class="form-label fw-semibold mb-2">Canais:</label>
                        <div class="border rounded p-3" style="max-height: 120px; overflow-y: auto; background: var(--bs-gray-100);">
                                            <?php
                            $selectedChannels = is_array($filters['channels'] ?? null) ? $filters['channels'] : [];
                            $legacyChannel = $filters['channel'] ?? '';
                            ?>
                            <label class="form-check form-check-custom form-check-solid mb-2">
                                <input class="form-check-input" type="checkbox" name="channels[]" value="whatsapp" id="filter_channel_whatsapp" <?= (in_array('whatsapp', $selectedChannels)) || $legacyChannel === 'whatsapp' ? 'checked' : '' ?>>
                                <span class="form-check-label">📱 WhatsApp</span>
                            </label>
                            <label class="form-check form-check-custom form-check-solid mb-2">
                                <input class="form-check-input" type="checkbox" name="channels[]" value="email" id="filter_channel_email" <?= (in_array('email', $selectedChannels)) || $legacyChannel === 'email' ? 'checked' : '' ?>>
                                <span class="form-check-label">✉️ Email</span>
                            </label>
                            <label class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" name="channels[]" value="chat" id="filter_channel_chat" <?= (in_array('chat', $selectedChannels)) || $legacyChannel === 'chat' ? 'checked' : '' ?>>
                                <span class="form-check-label">💬 Chat</span>
                            </label>
                        </div>
                        <div class="form-text">Selecione um ou mais canais</div>
                    </div>
                    
                    <!-- Integrações WhatsApp (mostrar apenas se WhatsApp selecionado) -->
                    <div class="mb-5" id="whatsapp_accounts_filter" style="display: none;">
                        <label class="form-label fw-semibold mb-2">Integrações WhatsApp:</label>
                        <div class="border rounded p-3" style="max-height: 150px; overflow-y: auto; background: var(--bs-gray-100);">
                            <?php 
                            $whatsappAccounts = \App\Models\WhatsAppAccount::getActive();
                            $selectedAccounts = [];
                            if (isset($filters['whatsapp_account_ids']) && is_array($filters['whatsapp_account_ids'])) {
                                $selectedAccounts = $filters['whatsapp_account_ids'];
                            } elseif (!empty($filters['whatsapp_account_id'])) {
                                $selectedAccounts = [(int)$filters['whatsapp_account_id']];
                            }
                            if (empty($whatsappAccounts)): ?>
                                <div class="text-muted fs-7">Nenhuma integração WhatsApp cadastrada</div>
                                            <?php else: ?>
                                <?php foreach ($whatsappAccounts as $account): ?>
                                    <label class="form-check form-check-custom form-check-solid mb-2">
                                        <input class="form-check-input" type="checkbox" name="whatsapp_account_ids[]" value="<?= $account['id'] ?>" <?= in_array($account['id'], $selectedAccounts) ? 'checked' : '' ?>>
                                        <span class="form-check-label">
                                            <?= htmlspecialchars($account['name']) ?> 
                                            <span class="text-muted fs-7">(<?= htmlspecialchars($account['phone_number']) ?>)</span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                                            <?php endif; ?>
                        </div>
                        <div class="form-text">Filtrar por número/integração específica do WhatsApp</div>
                    </div>
                    
                    <!-- Tags (Multi-select) -->
                    <?php if (!empty($tags)): ?>
                    <div class="mb-5">
                        <label class="form-label fw-semibold mb-2">Tags:</label>
                        <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto; background: var(--bs-gray-100);">
                            <?php 
                            $selectedTags = [];
                            if (isset($filters['tag_ids']) && is_array($filters['tag_ids'])) {
                                $selectedTags = $filters['tag_ids'];
                            } elseif (!empty($filters['tag_id'])) {
                                $selectedTags = [(int)$filters['tag_id']];
                            }
                            foreach ($tags as $tag): ?>
                                <label class="form-check form-check-custom form-check-solid mb-2">
                                    <input class="form-check-input" type="checkbox" name="tag_ids[]" value="<?= $tag['id'] ?>" <?= in_array($tag['id'], $selectedTags) ? 'checked' : '' ?>>
                                    <span class="form-check-label">
                                        <span class="badge badge-sm" style="background-color: <?= htmlspecialchars($tag['color'] ?? '#009ef7') ?>20; color: <?= htmlspecialchars($tag['color'] ?? '#009ef7') ?>;">
                                            <?= htmlspecialchars($tag['name']) ?>
                                        </span>
                                    </span>
                                </label>
                                <?php endforeach; ?>
                    </div>
                        <div class="form-text">Selecione uma ou mais tags</div>
                    </div>
                <?php endif; ?>
                    
                    <!-- Agentes (Multi-select) -->
                    <?php 
                    $canViewAllConversations = \App\Helpers\Permission::can('conversations.view.all');
                    $currentUserId = \App\Helpers\Auth::id();
                    ?>
                    <?php if ($canViewAllConversations || !empty($agents)): ?>
                    <div class="mb-5">
                        <label class="form-label fw-semibold mb-2">Agentes Atribuídos:</label>
                        <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto; background: var(--bs-gray-100);">
                            <?php 
                            $selectedAgents = [];
                            if (isset($filters['agent_ids']) && is_array($filters['agent_ids'])) {
                                $selectedAgents = $filters['agent_ids'];
                            } elseif (!empty($filters['agent_id']) && $filters['agent_id'] !== 'unassigned') {
                                $selectedAgents = [(int)$filters['agent_id']];
                            }
                            
                            // Opção para não atribuídas
                            ?>
                            <label class="form-check form-check-custom form-check-solid mb-2">
                                <input class="form-check-input" type="checkbox" name="agent_ids[]" value="unassigned" <?= (!empty($filters['agent_id']) && $filters['agent_id'] === 'unassigned') || in_array('unassigned', $selectedAgents) ? 'checked' : '' ?>>
                                <span class="form-check-label">🔴 Não atribuídas</span>
                            </label>
                            
                            <?php if ($canViewAllConversations && !empty($agents)): ?>
                                <?php foreach ($agents as $agent): ?>
                                    <label class="form-check form-check-custom form-check-solid mb-2">
                                        <input class="form-check-input" type="checkbox" name="agent_ids[]" value="<?= $agent['id'] ?>" <?= in_array($agent['id'], $selectedAgents) ? 'checked' : '' ?>>
                                        <span class="form-check-label">
                                            <?= htmlspecialchars($agent['name']) ?>
                                            <?php if (!empty($agent['email'])): ?>
                                                <span class="text-muted fs-7">(<?= htmlspecialchars($agent['email']) ?>)</span>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            <?php elseif (!$canViewAllConversations && $currentUserId): ?>
                                <?php 
                                $currentUser = \App\Models\User::find($currentUserId);
                                if ($currentUser): ?>
                                    <label class="form-check form-check-custom form-check-solid mb-2">
                                        <input class="form-check-input" type="checkbox" name="agent_ids[]" value="<?= $currentUser['id'] ?>" <?= in_array($currentUser['id'], $selectedAgents) ? 'checked' : '' ?>>
                                        <span class="form-check-label">Minhas conversas</span>
                                    </label>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="form-text">Selecione um ou mais agentes</div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Status de Resposta -->
                    <div class="mb-5">
                        <label class="form-label fw-semibold">Status de Resposta:</label>
                        <div class="d-flex gap-3">
                            <label class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" type="radio" name="response_status" value="" <?= empty($filters['unanswered']) && empty($filters['answered']) ? 'checked' : '' ?>>
                                <span class="form-check-label">Todas</span>
                            </label>
                            <label class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" type="radio" name="response_status" value="unanswered" <?= !empty($filters['unanswered']) ? 'checked' : '' ?>>
                                <span class="form-check-label">Sem Resposta</span>
                            </label>
                            <label class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" type="radio" name="response_status" value="answered" <?= !empty($filters['answered']) ? 'checked' : '' ?>>
                                <span class="form-check-label">Respondidas</span>
                            </label>
            </div>
        </div>
                    
                    <!-- Período -->
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Data Inicial:</label>
                            <input type="date" id="filter_date_from" name="date_from" class="form-control form-control-solid" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Data Final:</label>
                            <input type="date" id="filter_date_to" name="date_to" class="form-control form-control-solid" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <!-- Fixadas -->
                    <div class="mb-5">
                        <label class="form-label fw-semibold">Conversas Fixadas:</label>
                        <div class="d-flex gap-3">
                            <label class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" type="radio" name="pinned" value="" <?= !isset($filters['pinned']) ? 'checked' : '' ?>>
                                <span class="form-check-label">Todas</span>
                            </label>
                            <label class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" type="radio" name="pinned" value="1" <?= isset($filters['pinned']) && $filters['pinned'] === true ? 'checked' : '' ?>>
                                <span class="form-check-label">Apenas Fixadas</span>
                            </label>
                            <label class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" type="radio" name="pinned" value="0" <?= isset($filters['pinned']) && $filters['pinned'] === false ? 'checked' : '' ?>>
                                <span class="form-check-label">Não Fixadas</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Ordenação -->
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Ordenar por:</label>
                            <select id="filter_order_by" name="order_by" class="form-select form-select-solid">
                                <option value="">Padrão (Atualização)</option>
                                <option value="last_message" <?= ($filters['order_by'] ?? '') === 'last_message' ? 'selected' : '' ?>>Última Mensagem</option>
                                <option value="created_at" <?= ($filters['order_by'] ?? '') === 'created_at' ? 'selected' : '' ?>>Data de Criação</option>
                                <option value="updated_at" <?= ($filters['order_by'] ?? '') === 'updated_at' ? 'selected' : '' ?>>Última Atualização</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Direção:</label>
                            <select id="filter_order_dir" name="order_dir" class="form-select form-select-solid">
                                <option value="DESC" <?= ($filters['order_dir'] ?? 'DESC') === 'DESC' ? 'selected' : '' ?>>Decrescente</option>
                                <option value="ASC" <?= ($filters['order_dir'] ?? '') === 'ASC' ? 'selected' : '' ?>>Crescente</option>
                            </select>
                        </div>
                    </div>
                </form>
                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-light me-3" onclick="clearAllFilters()">Limpar Tudo</button>
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="applyAdvancedFilters()">Aplicar Filtros</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// FUNÇÕES GLOBAIS - DEFINIR PRIMEIRO
// ============================================

// Mostrar modal de nova conversa (definir IMEDIATAMENTE para estar disponível globalmente)
// Esta função DEVE estar definida antes de qualquer HTML que possa tentar usá-la
function showNewConversationModal() {
    const modal = document.getElementById('kt_modal_new_conversation');
    if (!modal) {
        console.error('Modal de nova conversa não encontrado');
        return;
    }
    
    // Limpar formulário
    const form = modal.querySelector('#newConversationForm');
    if (form) form.reset();
    
    // Abrir modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    // Focar no campo nome
    setTimeout(() => {
        const nameInput = modal.querySelector('#new_contact_name');
        if (nameInput) nameInput.focus();
    }, 300);
}

// Garantir que a função esteja no escopo global IMEDIATAMENTE
window.showNewConversationModal = showNewConversationModal;

// ============================================
// FUNÇÕES DE IA - DEFINIR IMEDIATAMENTE
// ============================================

/**
 * Carregar status da IA na conversa
 */
window.loadAIAgentStatus = function(conversationId) {
    console.log('loadAIAgentStatus chamado com conversationId:', conversationId);
    
    if (!conversationId) {
        console.warn('loadAIAgentStatus: conversationId não fornecido');
        if (typeof window.updateAIAgentSidebar === 'function') {
            window.updateAIAgentSidebar({ has_ai: false });
        }
        return;
    }
    
    // Mostrar estado de carregamento
    const statusDiv = document.getElementById('sidebar-ai-status');
    if (statusDiv) {
        statusDiv.innerHTML = '<div class="text-muted fs-7">Carregando...</div>';
    }
    
    const url = `<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}/ai-status`;
    console.log('Fazendo requisição para:', url);
    
    fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        console.log('Resposta recebida:', response.status, response.statusText);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Dados recebidos:', data);
        if (data.success) {
            if (typeof window.updateAIAgentSidebar === 'function') {
                window.updateAIAgentSidebar(data.data);
            }
            if (typeof window.updateAIActiveBanner === 'function') {
                window.updateAIActiveBanner(data.data, conversationId);
            }
        } else {
            console.error('Erro ao carregar status da IA:', data.message);
            if (typeof window.updateAIAgentSidebar === 'function') {
                window.updateAIAgentSidebar({ has_ai: false });
            }
            if (typeof window.updateAIActiveBanner === 'function') {
                window.updateAIActiveBanner({ has_ai: false }, conversationId);
            }
        }
    })
    .catch(error => {
        console.error('Erro ao carregar status da IA:', error);
        if (typeof window.updateAIAgentSidebar === 'function') {
            window.updateAIAgentSidebar({ has_ai: false });
        }
        if (typeof window.updateAIActiveBanner === 'function') {
            window.updateAIActiveBanner({ has_ai: false }, conversationId);
        }
    });
};

/**
 * Atualizar sidebar com status da IA
 */
window.updateAIAgentSidebar = function(status) {
    console.log('updateAIAgentSidebar chamado com status:', status);
    
    const section = document.getElementById('sidebar-ai-agent-section');
    console.log('Elemento sidebar-ai-agent-section encontrado:', !!section);
    
    if (!section) {
        console.error('Elemento sidebar-ai-agent-section não encontrado!');
        return;
    }
    
    const statusDiv = document.getElementById('sidebar-ai-status');
    const actionsDiv = document.getElementById('sidebar-ai-actions');
    const addSection = document.getElementById('sidebar-ai-add-section');
    
    console.log('Elementos encontrados:', {
        statusDiv: !!statusDiv,
        actionsDiv: !!actionsDiv,
        addSection: !!addSection,
        hasAI: status.has_ai
    });
    
    if (status.has_ai && status.ai_agent) {
        // Tem IA ativa
        const agent = status.ai_agent;
        const stats = status.ai_conversation || {};
        
        if (statusDiv) {
            statusDiv.innerHTML = `
                <div class="d-flex align-items-center mb-2">
                    <div class="symbol symbol-35px symbol-circle me-3">
                        <div class="symbol-label bg-light-primary">
                            <i class="ki-duotone ki-abstract-26 fs-2 text-primary">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <a href="#" class="text-dark fw-bold text-hover-primary fs-6">${agent.name || 'IA'}</a>
                        <span class="text-muted fw-semibold d-block fs-7">${agent.model || 'gpt-4'}</span>
                    </div>
                </div>
                
                ${stats.messages_count ? `
                <div class="mb-2">
                    <span class="text-gray-600 fs-7">Mensagens: </span>
                    <span class="badge badge-light-info fs-7">${stats.messages_count || 0}</span>
                </div>
                ` : ''}
            `;
        }
        
        if (actionsDiv) actionsDiv.style.display = '';
        if (addSection) addSection.style.display = 'none';
        console.log('Sidebar atualizado: IA ativa');
    } else {
        // Sem IA ativa
        if (statusDiv) {
            statusDiv.innerHTML = '<div class="text-muted fs-7">Nenhum agente de IA ativo nesta conversa</div>';
        }
        if (actionsDiv) actionsDiv.style.display = 'none';
        if (addSection) addSection.style.display = 'block';
        console.log('Sidebar atualizado: Sem IA - HTML inserido');
    }
    
    // Atualizar banner de IA ativa (se a função existir)
    if (typeof window.updateAIActiveBanner === 'function') {
        const conversationId = window.currentConversationId || 0;
        window.updateAIActiveBanner(status, conversationId);
    }
};

// Fallback: funções de automação no sidebar (caso não venham de sidebar-conversation.php)
if (typeof window.updateAutomationSidebar !== 'function') {
    window.updateAutomationSidebar = function(data) {
        const statusDiv = document.getElementById('sidebar-automation-status');
        if (!statusDiv) return;
        if (!data || !data.has_automation || !data.automation) {
            statusDiv.innerHTML = '<div class="text-muted fs-7">Nenhuma automação ativa</div>';
            return;
        }
        const automation = data.automation;
        const execStatus = automation.execution_status || 'unknown';
        const autoStatus = automation.automation_status || 'inactive';
        const lastExec = automation.last_execution_at ? formatTime(automation.last_execution_at) : '—';
        statusDiv.innerHTML = `
            <div class="d-flex flex-column gap-1">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge ${autoStatus === 'active' ? 'badge-success' : 'badge-light'}">${autoStatus === 'active' ? 'Ativa' : 'Inativa'}</span>
                    ${automation.trigger_type ? `<span class="badge badge-light">${escapeHtml(automation.trigger_type)}</span>` : ''}
                </div>
                <div class="fw-semibold">${escapeHtml(automation.name || 'Automação')}</div>
                <div class="text-muted fs-8">Execução: ${escapeHtml(execStatus)}</div>
                <div class="text-muted fs-8">Última: ${lastExec}</div>
            </div>
        `;
    };
}

if (typeof window.loadAutomationStatus !== 'function') {
    window.loadAutomationStatus = function(conversationId) {
        if (!conversationId) {
            window.updateAutomationSidebar({ has_automation: false });
            return;
        }
        const statusDiv = document.getElementById('sidebar-automation-status');
        if (statusDiv) {
            statusDiv.innerHTML = '<div class="text-muted fs-7">Carregando...</div>';
        }
        const url = `<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}/automation-status`;
        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.updateAutomationSidebar(data.data);
            } else {
                window.updateAutomationSidebar({ has_automation: false });
            }
        })
        .catch(() => {
            window.updateAutomationSidebar({ has_automation: false });
        });
    };
}

/**
 * Mostrar modal de adicionar agente de IA
 */
window.showAddAIAgentModal = function() {
    console.log('🤖 showAddAIAgentModal chamado');
    console.log('🔍 window.currentConversationId:', window.currentConversationId);
    console.log('🔍 typeof window.currentConversationId:', typeof window.currentConversationId);
    
    const conversationId = window.currentConversationId || 0;
    console.log('🔍 conversationId após || 0:', conversationId);
    
    if (!conversationId) {
        console.warn('⚠️ conversationId vazio ou zero, mostrando alerta');
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Selecione uma conversa primeiro'
        });
        return;
    }
    
    console.log('✅ conversationId válido:', conversationId);
    
    // Carregar agentes disponíveis
    const url = `<?= \App\Helpers\Url::to('/ai-agents/available') ?>`;
    console.log('🔍 Carregando agentes de IA de:', url);
    
    fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        console.log('📥 Resposta recebida:', response.status, response.statusText);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        // Capturar o texto primeiro para ver se é HTML
        return response.text().then(text => {
            console.log('📄 Conteúdo da resposta (primeiros 500 chars):', text.substring(0, 500));
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('❌ Erro ao fazer parse do JSON:', e);
                console.error('📄 Conteúdo completo:', text);
                throw new Error('Resposta não é um JSON válido. Verifique o console para mais detalhes.');
            }
        });
    })
    .then(data => {
        console.log('📊 Dados recebidos:', data);
        
        if (!data.success || !data.data || data.data.length === 0) {
            console.warn('⚠️ Nenhum agente disponível:', data);
            Swal.fire({
                icon: 'warning',
                title: 'Atenção',
                text: data.message || 'Nenhum agente de IA disponível'
            });
            return;
        }
        
        const agents = data.data;
        const agentOptions = agents.map(agent => 
            `<option value="${agent.id}">${agent.name} (${agent.agent_type})</option>`
        ).join('');
        
        Swal.fire({
            title: 'Adicionar Agente de IA',
            html: `
                <div class="text-start">
                    <div class="mb-4">
                        <label class="form-label">Selecione o agente:</label>
                        <select id="swal-ai-agent-select" class="form-select">
                            <option value="">Selecione...</option>
                            ${agentOptions}
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" id="swal-process-immediately" checked>
                            <span class="form-check-label">
                                Processar mensagens imediatamente
                            </span>
                        </label>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" id="swal-assume-conversation">
                            <span class="form-check-label">
                                Assumir conversa (remover agente humano se houver)
                            </span>
                        </label>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" id="swal-only-if-unassigned">
                            <span class="form-check-label">
                                Apenas se não tiver agente atribuído
                            </span>
                        </label>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Adicionar IA',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const agentId = document.getElementById('swal-ai-agent-select').value;
                if (!agentId) {
                    Swal.showValidationMessage('Selecione um agente de IA');
                    return false;
                }
                
                return {
                    ai_agent_id: agentId,
                    process_immediately: document.getElementById('swal-process-immediately').checked,
                    assume_conversation: document.getElementById('swal-assume-conversation').checked,
                    only_if_unassigned: document.getElementById('swal-only-if-unassigned').checked
                };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.addAIAgentToConversation(conversationId, result.value);
            }
        });
    })
    .catch(error => {
        console.error('❌ Erro ao carregar agentes:', error);
        console.error('Detalhes do erro:', {
            message: error.message,
            stack: error.stack
        });
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: `Erro ao carregar agentes de IA: ${error.message}`
        });
    });
};

/**
 * Adicionar agente de IA à conversa
 */
window.addAIAgentToConversation = function(conversationId, data) {
    const btn = Swal.getConfirmButton();
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Adicionando...';
    
    fetch(`<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}/ai-agents`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: result.message || 'Agente de IA adicionado com sucesso',
                timer: 2000,
                showConfirmButton: false
            });
            
            // Recarregar status da IA
            console.log('🔄 Recarregando status da IA após adicionar, conversationId:', conversationId);
            
            // Aguardar um pouco para garantir que o banco foi atualizado
            setTimeout(() => {
                console.log('🔄 Chamando loadAIAgentStatus após timeout');
                window.loadAIAgentStatus(conversationId);
            }, 500);
            
            // Recarregar conversa se necessário
            if (typeof selectConversation === 'function') {
                console.log('🔄 Chamando selectConversation para recarregar');
                selectConversation(conversationId);
            }
        } else {
            throw new Error(result.message || 'Erro ao adicionar agente de IA');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: error.message || 'Erro ao adicionar agente de IA'
        });
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
};

/**
 * Mostrar histórico de mensagens da IA
 */
window.showAIHistory = function() {
    const conversationId = window.currentConversationId || 0;
    if (!conversationId) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Selecione uma conversa primeiro'
        });
        return;
    }
    
    // Carregar mensagens da IA
    fetch(`<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}/ai-messages?limit=50`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Erro ao carregar histórico');
        }
        
        const messages = data.data || [];
        
        if (messages.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Histórico',
                text: 'Nenhuma mensagem da IA encontrada'
            });
            return;
        }
        
        // Carregar nome do agente
        fetch(`<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}/ai-status`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(statusData => {
            const agentName = statusData.success && statusData.data?.ai_agent 
                ? statusData.data.ai_agent.name 
                : 'Agente de IA';
            
            // Formatar mensagens
            const messagesHtml = messages.map(msg => {
                const date = new Date(msg.created_at);
                const formattedDate = date.toLocaleString('pt-BR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                const toolsHtml = msg.tools_used && msg.tools_used.length > 0
                    ? `<div class="mt-2"><small class="text-muted">🔧 Tools: ${msg.tools_used.join(', ')}</small></div>`
                    : '';
                
                const escapeDiv = document.createElement('div');
                escapeDiv.textContent = msg.content;
                const escapedContent = escapeDiv.innerHTML;
                
                return `
                    <div class="border-bottom pb-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <small class="text-muted">${formattedDate}</small>
                        </div>
                        <div class="text-gray-800">${escapedContent}</div>
                        ${toolsHtml}
                    </div>
                `;
            }).join('');
            
            Swal.fire({
                title: `Histórico - ${agentName}`,
                html: `
                    <div style="max-height: 400px; overflow-y: auto; text-align: left;">
                        ${messagesHtml}
                    </div>
                `,
                width: '600px',
                showConfirmButton: true,
                confirmButtonText: 'Fechar'
            });
        });
    })
    .catch(error => {
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: error.message || 'Erro ao carregar histórico da IA'
        });
    });
};

/**
 * Remover agente de IA da conversa
 */
window.removeAIAgent = function() {
    const conversationId = window.currentConversationId || 0;
    if (!conversationId) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Selecione uma conversa primeiro'
        });
        return;
    }
    
    Swal.fire({
        title: 'Remover Agente de IA',
        html: `
            <div class="text-start">
                <p>Deseja realmente remover o agente de IA desta conversa?</p>
                
                <div class="mb-3">
                    <label class="form-check form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" id="swal-assign-to-human" checked>
                        <span class="form-check-label">
                            Atribuir a agente humano após remover
                        </span>
                    </label>
                </div>
                
                <div id="swal-human-agent-select-container" style="display: none;">
                    <label class="form-label">Selecione o agente:</label>
                    <select id="swal-human-agent-select" class="form-select">
                        <option value="">Distribuição automática</option>
                    </select>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Remover',
        cancelButtonText: 'Cancelar',
        didOpen: () => {
            const assignCheckbox = document.getElementById('swal-assign-to-human');
            const selectContainer = document.getElementById('swal-human-agent-select-container');
            
            assignCheckbox.addEventListener('change', function() {
                selectContainer.style.display = this.checked ? 'block' : 'none';
            });
        },
        preConfirm: () => {
            const assignToHuman = document.getElementById('swal-assign-to-human').checked;
            const humanAgentId = document.getElementById('swal-human-agent-select').value;
            
            return {
                assign_to_human: assignToHuman,
                human_agent_id: humanAgentId || null,
                reason: 'Removido manualmente pelo usuário'
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const btn = Swal.getConfirmButton();
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Removendo...';
            
            fetch(`<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}/ai-agents`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(result.value)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: data.message || 'Agente de IA removido com sucesso',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    // Recarregar status da IA
                    window.loadAIAgentStatus(conversationId);
                    
                    // Recarregar conversa se necessário
                    if (typeof selectConversation === 'function') {
                        selectConversation(conversationId);
                    }
                } else {
                    throw new Error(data.message || 'Erro ao remover agente de IA');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: error.message || 'Erro ao remover agente de IA'
                });
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        }
    });
};

console.log('✅ Funções de IA definidas no escopo global:', {
    loadAIAgentStatus: typeof window.loadAIAgentStatus,
    updateAIAgentSidebar: typeof window.updateAIAgentSidebar,
    showAddAIAgentModal: typeof window.showAddAIAgentModal,
    addAIAgentToConversation: typeof window.addAIAgentToConversation,
    showAIHistory: typeof window.showAIHistory,
    removeAIAgent: typeof window.removeAIAgent
});

// ============================================
// VARIÁVEIS E OUTRAS FUNÇÕES
// ============================================

// Log inicial para saber se o script principal carregou
console.log('conversations.js iniciado');

// Capturar erros globais para diagnosticar rapidamente
window.onerror = function(message, source, lineno, colno, error) {
    console.error('Erro global capturado:', { message, source, lineno, colno, error });
    // Mostrar alerta mínimo para o usuário perceber
    try {
        Swal.fire({
            icon: 'error',
            title: 'Erro de script',
            html: `<div style="text-align:left;font-size:12px;">${message}<br><small>${source}:${lineno}:${colno}</small></div>`,
            confirmButtonText: 'OK'
        });
    } catch (e) {
        alert('Erro de script: ' + message + ' (' + source + ':' + lineno + ')');
    }
    return false;
};

window.addEventListener('unhandledrejection', function(event) {
    console.error('Promise rejeitada não tratada:', event.reason);
    try {
        Swal.fire({
            icon: 'error',
            title: 'Erro inesperado',
            html: `<div style="text-align:left;font-size:12px;">${(event.reason && event.reason.message) ? event.reason.message : event.reason}</div>`,
            confirmButtonText: 'OK'
        });
    } catch (e) {
        alert('Erro inesperado: ' + (event.reason && event.reason.message ? event.reason.message : event.reason));
    }
});

// Fallback para avatares faltando (404) -> usa avatar em branco
window.addEventListener('error', function(e) {
    const target = e.target;
    if (target && target.tagName === 'IMG' && target.src && target.src.indexOf('/media/avatars/contacts/') !== -1) {
        target.onerror = null;
        target.src = '<?= \App\Helpers\Url::asset('media/avatars/blank.png') ?>';
    }
}, true);

// Selecionar conversa (carregar via AJAX sem recarregar página)
// Sistema de Polling (fallback quando WebSocket não está disponível)
// Declarar variáveis e funções ANTES de serem usadas
let pollingInterval = null;
let lastMessageId = null;
let currentPollingConversationId = null;

// Sistema de Paginação Infinita
let isLoadingMessages = false;
let hasMoreMessages = true;
let oldestMessageId = null;
let currentConversationId = null;
let currentContactAvatar = null; // Avatar do contato da conversa atual

// Helper para converter valores vindos do PHP em JSON válido
function parsePhpJson(value) {
    try {
        return JSON.parse(value);
    } catch (e) {
        return null;
    }
}

// Se já vier um ID da URL/PHP, setar na inicialização
const initialConversationId = parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
if (initialConversationId) {
    currentConversationId = initialConversationId;
    currentContactAvatar = parsePhpJson('<?= json_encode($selectedConversation['contact_avatar'] ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
}

// Função para adicionar event listener ao botão (funciona mesmo se DOM já estiver carregado)
function attachNewConversationButton() {
    const btnNewConversation = document.getElementById('btn_new_conversation');
    if (btnNewConversation) {
        // Verificar se já tem listener (evitar duplicação)
        if (btnNewConversation.dataset.listenerAttached === 'true') {
            console.log('Listener já está anexado ao botão');
            return;
        }
        
        // Adicionar listener diretamente (sem clonar para preservar onclick)
        btnNewConversation.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Botão de nova conversa clicado (event listener)');
            if (typeof showNewConversationModal === 'function') {
                showNewConversationModal();
            } else {
                console.error('showNewConversationModal não está definida');
            }
        }, true); // Usar capture phase para garantir que execute
        
        // Marcar como anexado
        btnNewConversation.dataset.listenerAttached = 'true';
        
        console.log('Event listener adicionado ao botão de nova conversa');
    } else {
        console.warn('Botão de nova conversa não encontrado');
    }
}

// Tentar adicionar o listener imediatamente (se DOM já estiver pronto)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attachNewConversationButton);
} else {
    // DOM já está pronto, executar imediatamente
    setTimeout(attachNewConversationButton, 100); // Pequeno delay para garantir que tudo está pronto
}

// Garantir inscrição no cliente de tempo real para conversas da lista (necessário no modo polling)
function subscribeVisibleConversations() {
    if (typeof window.wsClient === 'undefined') return;
    const items = document.querySelectorAll('.conversation-item[data-conversation-id]');
    items.forEach(item => {
        const id = parseInt(item.getAttribute('data-conversation-id'));
        if (!isNaN(id)) {
            window.wsClient.subscribe(id);
        }
    });
}

/**
 * Atualizar tempos relativos de todas as conversas na lista
 */
function updateConversationTimes() {
    const conversationItems = document.querySelectorAll('.conversation-item');
    conversationItems.forEach(item => {
        const timeElement = item.querySelector('.conversation-item-time');
        const updatedAt = item.getAttribute('data-updated-at');
        if (timeElement && updatedAt) {
            timeElement.textContent = formatTime(updatedAt);
        }
    });
}

// Remover badge (não lidas) de uma conversa na lista
function removeConversationBadge(conversationId) {
    if (!conversationId) return;
    const conversationItem = document.querySelector(`[data-conversation-id="${conversationId}"]`);
    if (!conversationItem) return;
    const badge = conversationItem.querySelector('.conversation-item-badge');
    if (badge) badge.remove();
}

// Mover conversa para o topo da lista
function moveConversationToTop(conversationId) {
    const list = document.querySelector('.conversations-list-items');
    const conversationItem = document.querySelector(`[data-conversation-id="${conversationId}"]`);
    if (list && conversationItem) {
        list.insertBefore(conversationItem, list.firstChild);
    }
}

// Verificar se há dropdown aberto em uma conversa
function hasOpenDropdown(conversationId) {
    const conversationItem = document.querySelector(`[data-conversation-id="${conversationId}"]`);
    if (!conversationItem) return false;
    const dropdown = conversationItem.querySelector('.conversation-item-actions');
    if (!dropdown) return false;
    // Verificar múltiplas formas de detectar dropdown aberto
    return dropdown.classList.contains('show') || 
           dropdown.querySelector('.dropdown-menu.show') ||
           dropdown.dataset.isOpen === 'true' ||
           dropdown.querySelector('button[aria-expanded="true"]') !== null;
}

// Preservar dropdown aberto durante atualizações
function preserveOpenDropdown(conversationId) {
    const conversationItem = document.querySelector(`[data-conversation-id="${conversationId}"]`);
    if (!conversationItem) return null;
    const dropdown = conversationItem.querySelector('.conversation-item-actions');
    if (!dropdown) return null;
    const isOpen = dropdown.classList.contains('show') || dropdown.querySelector('.dropdown-menu.show');
    return isOpen ? conversationId : null;
}

// Restaurar dropdown aberto após atualização
function restoreOpenDropdown(conversationId) {
    if (!conversationId) return;
    setTimeout(() => {
        const conversationItem = document.querySelector(`[data-conversation-id="${conversationId}"]`);
        if (!conversationItem) return;
        const dropdown = conversationItem.querySelector('.conversation-item-actions');
        if (!dropdown) return;
        const button = dropdown.querySelector('button[data-bs-toggle="dropdown"]');
        if (button) {
            const bsDropdown = bootstrap.Dropdown.getInstance(button) || new bootstrap.Dropdown(button);
            bsDropdown.show();
        }
    }, 50);
}

// Garantir que o dropdown de ações exista e reflita o estado
function ensureActionsDropdown(conversationItem, pinned, conversationId, preserveOpen = false) {
    const timeContainer = conversationItem.querySelector('.conversation-item-time');
    if (!timeContainer) return;

    // Verificar se dropdown está aberto antes de remover
    const wasOpen = preserveOpen && hasOpenDropdown(conversationId);

    // Remover dropdown existente se houver
    const existingDropdown = conversationItem.querySelector('.conversation-item-actions');
    if (existingDropdown) {
        existingDropdown.remove();
    }

    const pinText = pinned ? 'Desfixar' : 'Fixar';
    const pinIconClass = pinned ? 'text-warning' : '';

    const dropdownHtml = `
        <div class="dropdown conversation-item-actions">
            <button type="button" class="btn btn-sm btn-icon btn-light p-0" 
                    data-bs-toggle="dropdown" 
                    aria-expanded="false"
                    onclick="event.stopPropagation();">
                <i class="ki-duotone ki-setting-2 text-muted">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                    <span class="path5"></span>
                </i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" data-conversation-id="${conversationId}">
                <li>
                    <a class="dropdown-item" href="#" onclick="event.stopPropagation(); togglePin(${conversationId}, ${pinned ? 'true' : 'false'}); return false;">
                        <i class="ki-duotone ki-pin fs-7 me-2 ${pinIconClass}">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        ${pinText}
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#" onclick="event.stopPropagation(); markConversationAsRead(${conversationId}); return false;">
                        <i class="ki-duotone ki-check fs-7 me-2 text-success">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Marcar como Lido
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#" onclick="event.stopPropagation(); markConversationAsUnread(${conversationId}); return false;">
                        <i class="ki-duotone ki-eye-slash fs-7 me-2 text-danger">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Marcar como Não Lido
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="#" onclick="event.stopPropagation(); showReminderModal(${conversationId}); return false;">
                        <i class="ki-duotone ki-notification-bing fs-7 me-2 text-primary">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Agendar Lembrete
                    </a>
                </li>
            </ul>
        </div>
    `;

    // Inserir dropdown no timeContainer
    timeContainer.insertAdjacentHTML('beforeend', dropdownHtml);

    // Restaurar dropdown aberto se estava aberto antes
    if (wasOpen) {
        restoreOpenDropdown(conversationId);
    }

    // Atualizar classe do item
    if (pinned) {
        conversationItem.classList.add('pinned');
    } else {
        conversationItem.classList.remove('pinned');
    }
}

// Atualizar atributos de data (updated_at) e resortear a lista
function updateConversationMeta(conversationItem, conv) {
    if (!conversationItem || !conv) return;
    // Manter estabilidade da ordenação: priorizar last_message_at; se não houver, manter valor atual; por último usar updated_at
    const currentUpdated = conversationItem.dataset.updatedAt || null;
    const updatedAt = conv.last_message_at || currentUpdated || conv.updated_at || new Date().toISOString();
    conversationItem.dataset.updatedAt = updatedAt;
}

// Determinar se a última mensagem foi do agente (para mostrar borda verde/SLA ok)
function isLastMessageFromAgent(data) {
    const lastAgent = data.last_agent_message_at || data.lastAgentMessageAt || '';
    const lastContact = data.last_contact_message_at || data.lastContactMessageAt || '';
    if (!lastAgent) return false;
    if (!lastContact) return true;
    const agentTs = Date.parse(lastAgent);
    const contactTs = Date.parse(lastContact);
    if (isNaN(agentTs)) return false;
    if (isNaN(contactTs)) return true;
    return agentTs >= contactTs;
}

function applySlaVisualState(conversationItem, conv) {
    // DESABILITADO: Não aplicar classes SLA ao conversation-item
    // O sistema de SLA (sla-indicator.js) é responsável por aplicar classes apenas ao avatar
    // Remover qualquer classe sla-ok que possa ter sido adicionada
    if (conversationItem) {
        conversationItem.classList.remove('sla-ok');
    }
    return;
}

function sortConversationList() {
    try {
        const list = document.querySelector('.conversations-list-items');
        if (!list) {
            console.log('🔍 sortConversationList: lista não encontrada');
            return;
        }
        
        const items = Array.from(list.children);
        if (items.length === 0) {
            console.log('🔍 sortConversationList: nenhum item para ordenar');
            return;
        }
        
        // Ordenar: pinned primeiro, depois updatedAt desc, depois ID desc (critério de desempate)
        items.sort((a, b) => {
            const pinnedA = a.classList?.contains('pinned') ? 1 : 0;
            const pinnedB = b.classList?.contains('pinned') ? 1 : 0;
            if (pinnedA !== pinnedB) return pinnedB - pinnedA;
            
            const dateA = Date.parse(a.dataset?.updatedAt || '') || 0;
            const dateB = Date.parse(b.dataset?.updatedAt || '') || 0;
            if (dateA !== dateB) return dateB - dateA;
            
            // Critério de desempate: ID da conversa (maior primeiro = mais recente)
            const idA = parseInt(a.dataset?.conversationId) || 0;
            const idB = parseInt(b.dataset?.conversationId) || 0;
            return idB - idA;
        });
        
        items.forEach(item => {
            if (item && list) {
                list.appendChild(item);
            }
        });
        
        console.log('✅ sortConversationList: lista ordenada com sucesso');
    } catch (error) {
        console.error('❌ Erro em sortConversationList:', error);
    }
}

// Atualizar preview/tempo/badge de um item de conversa com dados recebidos
function applyConversationUpdate(conv) {
    const conversationItem = document.querySelector(`[data-conversation-id="${conv.id}"]`);
    if (!conversationItem) return;

    // Preservar dropdown aberto se estiver aberto
    const wasOpen = hasOpenDropdown(conv.id);

            const preview = conversationItem.querySelector('.conversation-item-preview');
            const time = conversationItem.querySelector('.conversation-item-time');
            const badge = conversationItem.querySelector('.conversation-item-badge');
            const avatarContainer = conversationItem.querySelector('.symbol-label');
            const pinned = conv.pinned === 1 || conv.pinned === true;
            const firstResponseAt = conv.first_response_at_calc || conv.first_response_at || '';
            const lastContactAt = conv.last_contact_message_at || '';
            const lastAgentAt = conv.last_agent_message_at || '';

            if (preview && conv.last_message) {
                const maxChars = 37;
                const content = conv.last_message.length > maxChars
                    ? conv.last_message.substring(0, maxChars) + '...'
                    : conv.last_message;
                preview.textContent = content;
            }
    if (time && (conv.last_message_at || conv.updated_at)) {
        // Não atualizar o tempo se dropdown estiver aberto (evita fechar)
        if (!wasOpen) {
            time.textContent = formatTime(conv.last_message_at || conv.updated_at);
        }
    }

    if (avatarContainer) {
        if (conv.contact_avatar) {
            avatarContainer.innerHTML = `<img src="${escapeHtml(conv.contact_avatar)}" alt="${escapeHtml(conv.contact_name || '')}" class="h-45px w-45px rounded" style="object-fit: cover;">`;
        } else {
            avatarContainer.textContent = getInitials(conv.contact_name || 'NN');
        }
    }

    // Garantir botão de fixar e classe pinned (preservar dropdown aberto)
            ensureActionsDropdown(conversationItem, pinned, conv.id, wasOpen);

    // Atualizar data attributes para SLA/tempos
    conversationItem.dataset.status = conv.status || 'open';
    conversationItem.dataset.createdAt = conv.created_at || conversationItem.dataset.createdAt || '';
    conversationItem.dataset.firstResponseAt = firstResponseAt;
    conversationItem.dataset.lastMessageAt = conv.last_message_at || conv.updated_at || conversationItem.dataset.lastMessageAt || '';
    conversationItem.dataset.lastContactMessageAt = lastContactAt;
    conversationItem.dataset.lastAgentMessageAt = lastAgentAt;
    conversationItem.dataset.agentId = conv.agent_id || conversationItem.dataset.agentId || '';

    // Atualizar estado visual de SLA (borda verde quando última msg é do agente)
    applySlaVisualState(conversationItem, conv);

    // ⚠️ IMPORTANTE: Respeitar conversas marcadas manualmente como não lidas
    const isManuallyMarkedAsUnread = window.manuallyMarkedAsUnread && window.manuallyMarkedAsUnread.has(conv.id);
    const hasManualBadge = badge && badge.getAttribute('data-manual-unread') === 'true';
    
    console.log(`applyConversationUpdate: conv=${conv.id}, isManuallyMarked=${isManuallyMarkedAsUnread}, hasManualBadge=${hasManualBadge}, unread_count=${conv.unread_count}`);
    
    // Se foi marcada manualmente como não lida, não remover o badge
    if (isManuallyMarkedAsUnread || hasManualBadge) {
        console.log(`Preservando badge manual para conversa ${conv.id}`);
        // Apenas atualizar o número se aumentou
        if (badge && conv.unread_count > 0) {
            badge.textContent = conv.unread_count;
        }
    } else {
        // Comportamento normal: atualizar badge baseado em unread_count
        const unreadCount = conv.unread_count || 0;
        if (unreadCount > 0) {
            if (badge) {
                badge.textContent = unreadCount;
            } else {
                const badgeHtml = `<span class="conversation-item-badge">${unreadCount}</span>`;
                const meta = conversationItem.querySelector('.conversation-item-meta');
                if (meta) meta.insertAdjacentHTML('beforeend', badgeHtml);
            }
        } else if (badge) {
            badge.remove();
        }
    }
    
    // Atualizar meta e resortear
    updateConversationMeta(conversationItem, conv);
    sortConversationList();
}

/**
 * Iniciar polling (verificação periódica de novas mensagens)
 */
function startPolling(conversationId) {
    // Parar polling anterior se existir
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
    
    currentPollingConversationId = conversationId;
    
    // Se não houver conversa selecionada, não fazer polling
    if (!conversationId) {
        return;
    }
    
    // Verificar novas mensagens a cada 3 segundos
    pollingInterval = setInterval(() => {
        checkForNewMessages(conversationId);
    }, 3000);
    
    console.log('📡 Polling iniciado para conversa ' + conversationId + ' (WebSocket não disponível)');
}

/**
 * Parar polling
 */
function stopPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
        console.log('📡 Polling parado');
    }
    currentPollingConversationId = null;
}

/**
 * Verificar novas mensagens via AJAX
 */
function checkForNewMessages(conversationId) {
    if (!conversationId) return;
    
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) return;
    
    // Buscar apenas mensagens novas
    const conversationIdNum = parseInt(conversationId);
    const lastMessageIdNum = parseInt(lastMessageId) || 0;
    if (isNaN(conversationIdNum)) {
        console.error('ID de conversa inválido:', conversationId);
        return;
    }
    // Usar endpoint de mensagens, não a página da conversa, para evitar 404 e carregar apenas JSON
    const url = `<?= \App\Helpers\Url::to('/conversations') ?>/${conversationIdNum}/messages?last_message_id=${lastMessageIdNum}`;
    fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.messages && data.messages.length > 0) {
            console.group('🔍 checkForNewMessages: Polling antigo recebeu mensagens');
            console.log(`Total de mensagens novas: ${data.messages.length}`);
            data.messages.forEach((msg, index) => {
                console.log(`Mensagem ${index + 1}:`, {
                    id: msg.id,
                    sender_type: msg.sender_type,
                    direction: msg.direction,
                    type: msg.type,
                    content: msg.content?.substring(0, 30)
                });
            });
            console.groupEnd();
            
            // Adicionar apenas mensagens novas
            data.messages.forEach(msg => {
                const existingMsg = chatMessages.querySelector(`[data-message-id="${msg.id}"]`);
                if (!existingMsg) {
                    addMessageToChat(msg);
                    lastMessageId = Math.max(lastMessageId || 0, msg.id || 0);
                    
                    // Disparar evento para atualizar SLA
                    document.dispatchEvent(new CustomEvent('realtime:new_message', { 
                        detail: { 
                            conversation_id: conversationId, 
                            message: msg 
                        } 
                    }));
                }
            });
            
            // Atualizar lista de conversas também
            if (data.messages.length > 0) {
                updateConversationListPreview(conversationId, data.messages[data.messages.length - 1]);
            }
        }
    })
    .catch(error => {
        // Silenciar erros de polling (normal quando não há novas mensagens)
        if (error.message && !error.message.includes('404')) {
            console.error('Erro ao verificar novas mensagens:', error);
        }
    });
}

/**
 * Atualizar preview da conversa na lista
 */
function updateConversationListPreview(conversationId, lastMessage) {
    const conversationItem = document.querySelector(`[data-conversation-id="${conversationId}"]`);
    if (conversationItem && lastMessage) {
        // Preservar dropdown aberto se estiver aberto
        const wasOpen = hasOpenDropdown(conversationId);
        
        const preview = conversationItem.querySelector('.conversation-item-preview');
        const time = conversationItem.querySelector('.conversation-item-time');
        
        if (preview) {
            const content = lastMessage.content || '';
            const maxChars = 37;
            preview.textContent = content.substring(0, maxChars) + (content.length > maxChars ? '...' : '');
        }
        if (time && lastMessage.created_at) {
            // Não atualizar o tempo se dropdown estiver aberto (evita fechar)
            if (!wasOpen) {
                // Usar formatTime com o timestamp real da mensagem
                time.textContent = formatTime(lastMessage.created_at);
            }
            // Atualizar data-updated-at para ordenação correta (sempre)
            conversationItem.setAttribute('data-updated-at', lastMessage.created_at);
        }
        
        // Atualizar datasets de SLA/tempos
        if (lastMessage.sender_type === 'contact') {
            conversationItem.dataset.lastContactMessageAt = lastMessage.created_at;
        } else if (lastMessage.sender_type === 'agent') {
            conversationItem.dataset.lastAgentMessageAt = lastMessage.created_at;
        }
        
        // Atualizar indicador SLA em tempo real
        if (window.SLAIndicator) {
            const convData = window.SLAIndicator.getConversationData(conversationId);
            if (convData) {
                window.SLAIndicator.updateConversation(conversationId, convData);
            }
        }
        
        // Garantir dropdown de ações está atualizado (preservar se estava aberto)
        const pinned = conversationItem.classList.contains('pinned');
        ensureActionsDropdown(conversationItem, pinned, conversationId, wasOpen);
        
        // Resortear lista após atualizar (mas preservar posição se dropdown aberto)
        if (!wasOpen) {
            sortConversationList();
        }
    }
}

function selectConversation(id) {
    // Atualizar conversa selecionada globalmente
    currentConversationId = parseInt(id);
    window.currentConversationId = currentConversationId;

    // Marcar conversa como ativa na lista
    document.querySelectorAll('.conversation-item').forEach(item => {
        item.classList.remove('active');
    });
    const conversationItem = document.querySelector(`[data-conversation-id="${id}"]`);
    if (conversationItem) {
        conversationItem.classList.add('active');
        
        // Remover da lista de marcadas manualmente como não lidas
        if (window.manuallyMarkedAsUnread && window.manuallyMarkedAsUnread.has(id)) {
            window.manuallyMarkedAsUnread.delete(id);
            console.log(`Conversa ${id} removida da lista de marcadas como não lidas`);
        }
        
        // Remover badge de não lidas imediatamente (otimista - antes da resposta do servidor)
        const badge = conversationItem.querySelector('.conversation-item-badge');
        if (badge) {
            badge.remove();
        }
    }
    
    // Mostrar header e input (se estiverem ocultos)
    const chatHeader = document.getElementById('chatHeader');
    const chatInput = document.getElementById('chatInput');
    if (chatHeader) chatHeader.style.display = '';
    if (chatInput) chatInput.style.display = '';
    
    // Mostrar loading
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) {
        console.error('Elemento chatMessages não encontrado');
        return;
    }
    
    // Limpar caixa de mensagem ao trocar de conversa (evita texto pendente em outra conversa)
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.value = '';
        messageInput.style.height = 'auto';
    }
    const noteToggle = document.getElementById('noteToggle');
    if (noteToggle) {
        noteToggle.checked = false;
    }
    
    chatMessages.innerHTML = `
        <div class="d-flex align-items-center justify-content-center" style="height: 100%;">
            <div class="text-center">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
                <div class="text-muted">Carregando conversa...</div>
            </div>
        </div>
    `;
    
    // Fazer requisição AJAX
    fetch(`<?= \App\Helpers\Url::to('/conversations') ?>/${id}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(async response => {
        // Verificar se a resposta é JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Resposta não é JSON:', text.substring(0, 200));
            throw new Error('Resposta do servidor não é JSON. Status: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
    if (data.success && data.conversation) {
            // Atualizar URL sem recarregar
            const newUrl = `<?= \App\Helpers\Url::to('/conversations') ?>?id=${id}`;
            window.history.pushState({ conversationId: id }, '', newUrl);
            
            // Remover badge de não lidas da conversa atual na lista
            if (conversationItem) {
                const badge = conversationItem.querySelector('.conversation-item-badge');
                if (badge) {
                    badge.remove();
                }
            }
            
            // Atualizar header do chat
            updateChatHeader(data.conversation);
            
            // Resetar paginação
            currentConversationId = parseInt(id);
            window.currentConversationId = currentConversationId; // Garantir que window também é atualizado
            console.log('🔄 currentConversationId atualizado para:', currentConversationId);
            currentContactAvatar = data.conversation.contact_avatar || null; // Armazenar avatar do contato
            isLoadingMessages = false;
            hasMoreMessages = true;
            oldestMessageId = null;
            
            // Atualizar mensagens
            const messages = data.messages || [];
    if (messages.length > 0) {
        oldestMessageId = messages[0].id; // Primeira mensagem (mais antiga)
        // Garantir lastMessageId para polling incremental
        const lastMsg = messages[messages.length - 1];
        if (lastMsg && lastMsg.id) {
            lastMessageId = lastMsg.id;
        }
    } else {
        // Sem mensagens, zera controles
        oldestMessageId = null;
        lastMessageId = 0;
    }
    updateChatMessages(messages, true);
            
            // Atualizar sidebar
            updateConversationSidebar(data.conversation, data.tags || []);
            
            // Carregar status da IA e atualizar banner
            console.log('🔍 [selectConversation] Tentando carregar status da IA...', {
                id: id,
                loadAIAgentStatusExists: typeof loadAIAgentStatus
            });
            if (typeof loadAIAgentStatus === 'function') {
                console.log('✅ [selectConversation] Chamando loadAIAgentStatus para id:', id);
                loadAIAgentStatus(id);
            } else {
                console.warn('❌ [selectConversation] loadAIAgentStatus não está disponível');
            }
            // Carregar status da automação
            if (typeof loadAutomationStatus === 'function') {
                loadAutomationStatus(id);
            } else {
                console.warn('❌ [selectConversation] loadAutomationStatus não está disponível');
            }
            
            // Atualizar timeline quando conversa é selecionada
            updateConversationTimeline(data.conversation.id);
            
            // Scroll para última mensagem
            setTimeout(() => {
                const chatMessagesEl = document.getElementById('chatMessages');
                if (chatMessagesEl) {
                    chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
                }
            }, 100);
            
            // Adicionar listener de scroll para paginação infinita
            setupInfiniteScroll();
            
            // Inscrever no WebSocket para esta conversa
            if (typeof window.wsClient !== 'undefined' && window.wsClient.connected && window.wsClient.currentMode === 'websocket') {
                window.wsClient.subscribe(id);
                // Parar polling apenas se o modo for websocket
                stopPolling();
            } else {
                // Se WebSocket não estiver disponível, iniciar polling
                startPolling(id);
            }
            
            // Atualizar último ID de mensagem conhecido
            if (data.messages && data.messages.length > 0) {
                const lastMsg = data.messages[data.messages.length - 1];
                if (lastMsg.id) {
                    lastMessageId = lastMsg.id;
                }
            }

            // Garantir truncamento visual do preview (limitando caracteres)
            const preview = document.querySelector(`[data-conversation-id="${id}"] .conversation-item-preview`);
        if (preview) {
            const text = preview.textContent || '';
            const maxChars = 37;
            if (text.length > maxChars) {
                preview.textContent = text.substring(0, maxChars) + '...';
            }
        }
        } else {
            alert('Erro ao carregar conversa: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro ao carregar conversa:', error);
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.innerHTML = `
                <div class="d-flex align-items-center justify-content-center" style="height: 100%;">
                    <div class="text-center text-danger">
                        <i class="ki-duotone ki-information fs-3x mb-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="fw-semibold">Erro ao carregar conversa</div>
                        <div class="text-muted fs-7">${escapeHtml(error.message || 'Erro desconhecido')}</div>
                        <button class="btn btn-sm btn-primary mt-3" onclick="selectConversation(${id})">Tentar novamente</button>
                    </div>
                </div>
            `;
        } else {
            alert('Erro ao carregar conversa: ' + (error.message || 'Erro desconhecido'));
        }
    });
}

// Atualizar header do chat
function updateChatHeader(conversation) {
    const header = document.getElementById('chatHeader');
    if (!header) return;
    
    const contactName = conversation.contact_name || 'Sem nome';
    const initials = getInitials(contactName);
    const channel = conversation.channel || 'chat';
    const status = conversation.status || 'open';
    
    const channelIcon = {
        'whatsapp': '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="#25D366" style="vertical-align: middle; margin-right: 4px;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg> WhatsApp',
        'email': '✉️ Email',
        'chat': '💬 Chat'
    }[channel] || '💬 Chat';
    
    const statusClass = {
        'open': 'success',
        'resolved': 'info',
        'closed': 'dark'
    }[status] || 'secondary';
    
    const statusText = {
        'open': 'Aberta',
        'resolved': 'Resolvida',
        'closed': 'Fechada'
    }[status] || 'Desconhecida';
    
    // Atualizar avatar
    const avatarLabel = header.querySelector('.symbol-label');
    if (avatarLabel) {
        if (conversation.contact_avatar) {
            avatarLabel.innerHTML = `<img src="${escapeHtml(conversation.contact_avatar)}" alt="${escapeHtml(contactName)}" class="h-45px w-45px rounded" style="object-fit: cover;">`;
        } else {
            avatarLabel.textContent = initials;
        }
    }
    
    // Atualizar nome
    const nameElement = header.querySelector('.chat-header-title');
    if (nameElement) {
        nameElement.textContent = contactName;
    }
    
    // Atualizar canal e status
    const subtitleElement = header.querySelector('.chat-header-subtitle');
    if (subtitleElement) {
        subtitleElement.innerHTML = `${channelIcon} • <span class="badge badge-sm badge-light-${statusClass}">${statusText}</span>`;
    }
}

// Atualizar mensagens do chat
function updateChatMessages(messages, isInitialLoad = false) {
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages || !chatMessages.parentElement) return;
    
    if (!messages || messages.length === 0) {
        if (isInitialLoad) {
            chatMessages.innerHTML = `
                <div class="d-flex align-items-center justify-content-center" style="height: 100%;">
                    <div class="text-center text-muted">
                        <i class="ki-duotone ki-chat fs-3x mb-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="fw-semibold">Nenhuma mensagem</div>
                        <div class="fs-7">Envie a primeira mensagem</div>
                    </div>
                </div>
            `;
        }
        return;
    }
    
    // Se for carregamento inicial, limpar tudo
    if (isInitialLoad) {
        chatMessages.innerHTML = '';
    }
    
    // Adicionar mensagens
    messages.forEach(msg => {
        const messageDiv = addMessageToChat(msg);
    });
    
    // Observar elementos lazy após adicionar todas as mensagens
    if (chatMessages) {
        observeNewLazyElements(chatMessages);
    }
    
    // Scroll para última mensagem apenas no carregamento inicial
    if (isInitialLoad) {
        setTimeout(() => {
            if (chatMessages && chatMessages.scrollHeight !== undefined) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }, 100);
    }
}

// Configurar scroll infinito
function setupInfiniteScroll() {
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) return;
    
    // Remover listener anterior se existir
    chatMessages.removeEventListener('scroll', handleScroll);
    
    // Adicionar novo listener
    chatMessages.addEventListener('scroll', handleScroll);
}

// Handler de scroll para paginação infinita
function handleScroll(event) {
    const chatMessages = event.target;
    
    // Se estiver carregando ou não houver mais mensagens, não fazer nada
    if (isLoadingMessages || !hasMoreMessages || !currentConversationId) {
        return;
    }
    
    // Se scroll estiver próximo do topo (50px), carregar mais mensagens
    if (chatMessages.scrollTop <= 50) {
        loadMoreMessages();
    }
}

// Carregar mais mensagens antigas
async function loadMoreMessages() {
    if (isLoadingMessages || !hasMoreMessages || !currentConversationId || !oldestMessageId) {
        return;
    }
    
    isLoadingMessages = true;
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) {
        isLoadingMessages = false;
        return;
    }
    
    // Salvar posição atual do scroll
    const scrollHeightBefore = chatMessages.scrollHeight;
    
    // Adicionar indicador de carregamento
    const loadingIndicator = document.createElement('div');
    loadingIndicator.className = 'messages-loading';
    loadingIndicator.id = 'messagesLoadingIndicator';
    loadingIndicator.innerHTML = `
        <div class="spinner-border spinner-border-sm text-primary" role="status">
            <span class="visually-hidden">Carregando...</span>
        </div>
        Carregando mensagens antigas...
    `;
    chatMessages.insertBefore(loadingIndicator, chatMessages.firstChild);
    
    try {
        const url = `<?= \App\Helpers\Url::to('/conversations') ?>/${currentConversationId}/messages?limit=50&before_id=${oldestMessageId}`;
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        
        // Garantir que a resposta é JSON (evita erro de parser com HTML/404)
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const text = await response.text();
            console.warn('loadMoreMessages: resposta não-JSON (provável 404 ou erro). Encerrando paginação.', text.substring(0, 200));
            hasMoreMessages = false;
            return;
        }
        
        const data = await response.json();
        
        if (data.success && data.messages && data.messages.length > 0) {
            // Adicionar mensagens no início do chat
            data.messages.forEach(msg => {
                const messageDiv = addMessageToChat(msg);
                chatMessages.insertBefore(messageDiv, loadingIndicator.nextSibling);
            });
            
            // Atualizar oldestMessageId
            oldestMessageId = data.messages[0].id;
            
            // Atualizar flag hasMoreMessages
            hasMoreMessages = data.has_more !== false;
            
            // Restaurar posição do scroll
            const scrollHeightAfter = chatMessages.scrollHeight;
            const scrollDiff = scrollHeightAfter - scrollHeightBefore;
            chatMessages.scrollTop = scrollDiff;
        } else {
            // Não há mais mensagens
            hasMoreMessages = false;
        }
    } catch (error) {
        console.error('Erro ao carregar mensagens antigas:', error);
    } finally {
        // Remover indicador de carregamento
        const indicator = document.getElementById('messagesLoadingIndicator');
        if (indicator) {
            indicator.remove();
        }
        isLoadingMessages = false;
    }
}

// Atualizar sidebar da conversa
function updateConversationSidebar(conversation, tags) {
    // Atualizar informações básicas
    const sidebar = document.getElementById('conversationSidebar');
    if (!sidebar) return;
    
    // Atualizar ID da conversa no sidebar
    sidebar.dataset.conversationId = conversation.id;
    
    // Atualizar avatar ou iniciais do contato
    const initialsEl = sidebar.querySelector('#sidebar-contact-initials');
    if (initialsEl) {
        // Pegar o elemento pai (symbol-label)
        const symbolLabel = initialsEl.closest('.symbol-label') || initialsEl.parentElement;
        
        if (conversation.contact_avatar) {
            // Substituir por imagem do avatar
            symbolLabel.innerHTML = `<img src="${escapeHtml(conversation.contact_avatar)}" alt="Avatar" class="w-100 h-100 rounded" style="object-fit: cover;">`;
        } else if (conversation.contact_name) {
            // Atualizar iniciais
            const name = conversation.contact_name;
            const parts = name.split(' ');
            const initials = (parts[0].charAt(0) + (parts[1] ? parts[1].charAt(0) : '')).toUpperCase();
            
            // Se já é um elemento de iniciais, apenas atualizar texto
            if (initialsEl.id === 'sidebar-contact-initials') {
                initialsEl.textContent = initials;
            } else {
                // Caso contrário, recriar elemento de iniciais
                symbolLabel.innerHTML = `<div id="sidebar-contact-initials" class="symbol-label bg-light-primary text-primary fw-bold">${initials}</div>`;
            }
        }
    }
    
    // Atualizar informações do contato
    const contactNameEl = sidebar.querySelector('[data-field="contact_name"]');
    if (contactNameEl) contactNameEl.textContent = conversation.contact_name || '-';
    
    const contactEmailEl = sidebar.querySelector('[data-field="contact_email"]');
    if (contactEmailEl) contactEmailEl.textContent = conversation.contact_email || '-';
    
    const contactPhoneEls = sidebar.querySelectorAll('[data-field="contact_phone"]');
    contactPhoneEls.forEach(el => {
        el.textContent = conversation.contact_phone || '-';
    });
    
    // Atualizar informações da conversa
    const conversationStatusEl = sidebar.querySelector('[data-field="status"]');
    if (conversationStatusEl) {
        const statusText = {
            'open': 'Aberta',
            'resolved': 'Resolvida',
            'closed': 'Fechada'
        }[conversation.status] || conversation.status;
        const statusClass = {
            'open': 'success',
            'resolved': 'info',
            'closed': 'dark'
        }[conversation.status] || 'secondary';
        conversationStatusEl.innerHTML = `<span class="badge badge-light-${statusClass}">${statusText}</span>`;
    }
    
    // Atualizar badge de SPAM
    const spamBadgeEl = sidebar.querySelector('#sidebar-spam-badge');
    if (spamBadgeEl) {
        if (conversation.is_spam) {
            spamBadgeEl.style.display = '';
        } else {
            spamBadgeEl.style.display = 'none';
        }
    }
    
    // Carregar sentimento
    if (conversation.id) {
        loadConversationSentiment(conversation.id);
    }
    
    const conversationChannelEl = sidebar.querySelector('[data-field="channel"]');
    if (conversationChannelEl) {
        if (conversation.channel === 'whatsapp') {
            // Ícone WhatsApp SVG
            const whatsappIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="#25D366" style="vertical-align: middle; margin-right: 4px;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>`;
            conversationChannelEl.innerHTML = whatsappIcon + ' WhatsApp';
            
            // Mostrar informações do WhatsApp
            const whatsappInfoItem = sidebar.querySelector('#sidebar-whatsapp-info');
            const whatsappPhoneItem = sidebar.querySelector('#sidebar-whatsapp-phone');
            
            if (whatsappInfoItem && conversation.whatsapp_account_name) {
                const accountNameEl = whatsappInfoItem.querySelector('[data-field="whatsapp_account_name"]');
                if (accountNameEl) {
                    accountNameEl.textContent = conversation.whatsapp_account_name;
                }
                whatsappInfoItem.style.display = 'flex';
            }
            
            if (whatsappPhoneItem && conversation.whatsapp_account_phone) {
                const accountPhoneEl = whatsappPhoneItem.querySelector('[data-field="whatsapp_account_phone"]');
                if (accountPhoneEl) {
                    accountPhoneEl.textContent = conversation.whatsapp_account_phone;
                }
                whatsappPhoneItem.style.display = 'flex';
            }
        } else {
            const channelText = {
                'email': '✉️ Email',
                'chat': '💬 Chat'
            }[conversation.channel] || conversation.channel;
            conversationChannelEl.textContent = channelText;
            
            // Ocultar informações WhatsApp se não for WhatsApp
            const whatsappInfoItem = sidebar.querySelector('#sidebar-whatsapp-info');
            const whatsappPhoneItem = sidebar.querySelector('#sidebar-whatsapp-phone');
            if (whatsappInfoItem) whatsappInfoItem.style.display = 'none';
            if (whatsappPhoneItem) whatsappPhoneItem.style.display = 'none';
        }
    }
    
    // Funil/Etapa - Card Destacado
    const funnelSection = sidebar.querySelector('#sidebar-funnel-stage-section');
    const funnelSeparator = sidebar.querySelector('#sidebar-funnel-separator');
    const funnelCard = sidebar.querySelector('#sidebar-funnel-card');
    
    if (funnelSection && funnelCard) {
        if (conversation.funnel_name || conversation.stage_name) {
            // Mostrar seção
            funnelSection.style.display = 'block';
            funnelSeparator.style.display = 'block';
            
            // Atualizar nome do funil
            const funnelNameEl = funnelCard.querySelector('[data-field="funnel_name"]');
            if (funnelNameEl) {
                funnelNameEl.textContent = conversation.funnel_name || '-';
            }
            
            // Atualizar badge da etapa
            const stageBadge = funnelCard.querySelector('#sidebar-stage-badge');
            if (stageBadge) {
                stageBadge.textContent = conversation.stage_name || '-';
                
                // Aplicar cor da etapa (se disponível)
                if (conversation.stage_color) {
                    stageBadge.style.backgroundColor = conversation.stage_color + '20';
                    stageBadge.style.color = conversation.stage_color;
                    stageBadge.style.borderColor = conversation.stage_color;
                    
                    // Aplicar cor no card
                    const cardBody = funnelCard.querySelector('.card-body');
                    if (cardBody) {
                        cardBody.style.borderLeftColor = conversation.stage_color;
                    }
                } else {
                    // Cor padrão
                    stageBadge.className = 'badge badge-primary';
                }
            }
            
            // Atualizar tempo na etapa (futuro - por enquanto placeholder)
            const stageTime = funnelCard.querySelector('#sidebar-stage-time');
            if (stageTime && conversation.updated_at) {
                const now = new Date();
                const updatedAt = new Date(conversation.updated_at);
                const diffMs = now - updatedAt;
                const diffMins = Math.floor(diffMs / 60000);
                const diffHours = Math.floor(diffMins / 60);
                const diffDays = Math.floor(diffHours / 24);
                
                let timeText = '';
                if (diffDays > 0) {
                    timeText = `⏱️ ${diffDays}d nesta etapa`;
                } else if (diffHours > 0) {
                    timeText = `⏱️ ${diffHours}h nesta etapa`;
                } else if (diffMins > 0) {
                    timeText = `⏱️ ${diffMins}min nesta etapa`;
                } else {
                    timeText = `⏱️ Agora mesmo`;
                }
                stageTime.textContent = timeText;
            }
        } else {
            funnelSection.style.display = 'none';
            funnelSeparator.style.display = 'none';
        }
    }
    
    // Atualizar setor
    const departmentEl = sidebar.querySelector('[data-field="department_name"]');
    const departmentItem = sidebar.querySelector('#sidebar-department-item');
    if (departmentEl && departmentItem) {
        if (conversation.department_name) {
            departmentEl.textContent = conversation.department_name;
            departmentItem.style.display = 'flex';
        } else {
            departmentItem.style.display = 'none';
        }
    }
    
    // Atualizar agente
    const agentNameEl = sidebar.querySelector('[data-field="agent_name"]');
    if (agentNameEl) {
        agentNameEl.textContent = conversation.agent_name || 'Não atribuído';
        if (!conversation.agent_name) {
            agentNameEl.classList.add('text-muted');
        } else {
            agentNameEl.classList.remove('text-muted');
        }
    }
    
    // Atualizar data de criação
    const createdAtEl = sidebar.querySelector('[data-field="created_at"]');
    if (createdAtEl && conversation.created_at) {
        const date = new Date(conversation.created_at);
        const formattedDate = date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' +
                              date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        createdAtEl.textContent = formattedDate;
    }
    
    // Atualizar participantes
    const participantsContainer = sidebar.querySelector('#participants-list');
    const addParticipantBtn = sidebar.querySelector('#sidebar-add-participant-btn');
    if (participantsContainer && conversation.id) {
        // Mostrar loading
        participantsContainer.innerHTML = '<div class="text-muted fs-7">Carregando...</div>';
        
        // Buscar participantes via AJAX
        fetch(`<?= \App\Helpers\Url::to('/conversations') ?>/${conversation.id}/participants`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erro ao carregar participantes');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.participants) {
                if (data.participants.length > 0) {
                    participantsContainer.innerHTML = data.participants.map(p => {
                        const initials = (p.user_name || 'U').charAt(0).toUpperCase();
                        return `
                            <div class="d-flex align-items-center gap-2 p-2 border rounded mb-2" style="background: var(--bs-gray-100);">
                                <div class="symbol symbol-30px symbol-circle">
                                    <div class="symbol-label bg-light-primary text-primary fw-bold fs-7">
                                        ${initials}
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold fs-7">${escapeHtml(p.user_name || 'Usuário')}</div>
                                    ${p.user_email ? `<div class="text-muted fs-8">${escapeHtml(p.user_email)}</div>` : ''}
                                </div>
                                <button type="button" class="btn btn-sm btn-icon btn-light-danger p-0" 
                                        onclick="removeParticipant(${conversation.id}, ${p.user_id})" 
                                        title="Remover participante">
                                    <i class="ki-duotone ki-cross fs-7">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </button>
                            </div>
                        `;
                    }).join('');
                } else {
                    participantsContainer.innerHTML = '<div class="text-muted fs-7">Nenhum participante adicional</div>';
                }
                
                if (addParticipantBtn) {
                    addParticipantBtn.style.display = 'block';
                    addParticipantBtn.setAttribute('onclick', `showAddParticipantModal(${conversation.id})`);
                }
            } else {
                participantsContainer.innerHTML = '<div class="text-muted fs-7">Erro ao carregar participantes</div>';
            }
        })
        .catch(error => {
            console.error('Erro ao carregar participantes:', error);
            participantsContainer.innerHTML = '<div class="text-muted fs-7">Erro ao carregar participantes</div>';
        });
    }
    
    // Atualizar tags
    const tagsContainer = sidebar.querySelector('.conversation-tags-list');
    if (tagsContainer) {
        if (tags && tags.length > 0) {
            tagsContainer.innerHTML = tags.map(tag => `
                <span class="badge badge-lg" style="background-color: ${tag.color}20; color: ${tag.color};">
                    ${escapeHtml(tag.name)}
                </span>
            `).join('');
        } else {
            tagsContainer.innerHTML = '<div class="text-muted fs-7">Nenhuma tag</div>';
        }
    }
    
    // Atualizar botões com IDs corretos
    const editContactBtn = sidebar.querySelector('#sidebar-edit-contact-btn');
    if (editContactBtn && conversation.contact_id) {
        editContactBtn.setAttribute('onclick', `editContact(${conversation.contact_id})`);
        editContactBtn.style.display = '';
    }
    
    const manageTagsBtn = sidebar.querySelector('#sidebar-manage-tags-btn');
    if (manageTagsBtn && conversation.id) {
        manageTagsBtn.setAttribute('onclick', `manageTags(${conversation.id})`);
        manageTagsBtn.style.display = '';
    }
    
    // Verificar se conversa está com agente de IA
    const escalateBtn = sidebar.querySelector('#sidebar-escalate-btn');
    if (escalateBtn && conversation.id) {
        // Verificar se conversa está com IA (buscar via API)
        checkIfConversationHasAI(conversation.id).then(hasAI => {
            if (hasAI) {
                escalateBtn.setAttribute('onclick', `escalateFromAI(${conversation.id})`);
                escalateBtn.style.display = '';
            } else {
                escalateBtn.style.display = 'none';
            }
        }).catch(() => {
            escalateBtn.style.display = 'none';
        });
    }
    
    const assignBtn = sidebar.querySelector('#sidebar-assign-btn');
    if (assignBtn && conversation.id) {
        assignBtn.setAttribute('onclick', `assignConversation(${conversation.id})`);
        assignBtn.style.display = '';
    }
    
    const departmentBtn = sidebar.querySelector('#sidebar-department-btn');
    if (departmentBtn && conversation.id) {
        departmentBtn.setAttribute('onclick', `changeDepartment(${conversation.id})`);
        departmentBtn.style.display = '';
    }
    
    const closeBtn = sidebar.querySelector('#sidebar-close-btn');
    const reopenBtn = sidebar.querySelector('#sidebar-reopen-btn');
    if (conversation.id) {
        if (conversation.status === 'open') {
            if (closeBtn) {
                closeBtn.setAttribute('onclick', `closeConversation(${conversation.id})`);
                closeBtn.style.display = '';
            }
            if (reopenBtn) reopenBtn.style.display = 'none';
        } else {
            if (reopenBtn) {
                reopenBtn.setAttribute('onclick', `reopenConversation(${conversation.id})`);
                reopenBtn.style.display = '';
            }
            if (closeBtn) closeBtn.style.display = 'none';
        }
    }
    
    const spamBtn = sidebar.querySelector('#sidebar-spam-btn');
    if (spamBtn && conversation.id) {
        spamBtn.setAttribute('onclick', `markAsSpam(${conversation.id})`);
        spamBtn.style.display = '';
    }
    
    const addNoteBtn = sidebar.querySelector('#sidebar-add-note-btn');
    if (addNoteBtn && conversation.id) {
        addNoteBtn.setAttribute('onclick', `addNote(${conversation.id})`);
        addNoteBtn.style.display = '';
    }
    
    // Carregar status da IA na conversa
    console.log('🔍 Tentando carregar status da IA...', {
        conversationId: conversation.id,
        loadAIAgentStatusExists: typeof loadAIAgentStatus
    });
    if (conversation.id && typeof loadAIAgentStatus === 'function') {
        console.log('✅ Chamando loadAIAgentStatus para conversation.id:', conversation.id);
        loadAIAgentStatus(conversation.id);
    } else {
        console.warn('❌ loadAIAgentStatus não está disponível ou conversation.id está vazio');
    }
    
    // Atualizar timeline
    updateConversationTimeline(conversation.id);

    // Atualizar histórico (aba Histórico)
    loadContactHistory(conversation.contact_id);
    
    // Carregar agentes do contato
    loadContactAgents(conversation.contact_id);
}

// Atualizar timeline da conversa
function updateConversationTimeline(conversationId) {
    if (!conversationId) return;
    
    const timelineContainer = document.querySelector('#kt_tab_timeline .timeline');
    if (!timelineContainer) return;
    
    // Mostrar loading
    timelineContainer.innerHTML = `
        <div class="text-center py-5">
            <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
            <div class="text-muted fs-7 mt-2">Carregando timeline...</div>
        </div>
    `;
    
    // Buscar atividades da conversa, notas e timeline em paralelo
    Promise.all([
        fetch(`<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        }).then(r => r.json()),
        fetch(`<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}/notes`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        }).then(r => r.json()).catch(() => ({ success: true, notes: [] })),
        fetch(`<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}/timeline`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        }).then(r => r.json()).catch(() => ({ success: true, events: [] }))
    ])
        .then(([data, notesData, timelineData]) => {
            if (!data.success || !data.conversation) {
                timelineContainer.innerHTML = '<div class="text-muted fs-7 text-center py-5">Erro ao carregar timeline</div>';
                return;
            }
            
            const conv = data.conversation;
            const messages = data.messages || [];
            const notes = notesData.success ? (notesData.notes || []) : [];
            const timelineEvents = timelineData.success ? (timelineData.events || []) : [];
            
            // Combinar todos os eventos em um array para ordenar por data
            const events = [];
            
            // Evento de criação
            if (conv.created_at) {
                events.push({
                    type: 'created',
                    date: conv.created_at,
                    icon: 'ki-message-text-2',
                    color: 'primary',
                    title: 'Conversa criada',
                    description: null,
                    user_name: null
                });
            }
            
            // Atribuição (verificar se não há evento mais recente de atividades)
            const hasRecentAssignment = timelineEvents.some(e => e.type === 'assigned');
            if (conv.agent_id && conv.agent_name && !hasRecentAssignment) {
                events.push({
                    type: 'assigned',
                    date: conv.updated_at || conv.created_at,
                    icon: 'ki-profile-user',
                    color: 'info',
                    title: `Atribuída a ${escapeHtml(conv.agent_name)}`,
                    description: null,
                    user_name: null
                });
            }
            
            // Adicionar eventos da timeline (atividades)
            timelineEvents.forEach(event => {
                events.push(event);
            });
            
            // Mensagens importantes (primeira e última) - apenas se não houver muitas mensagens
            if (messages.length > 0 && messages.length <= 50) {
                const firstMsg = messages[0];
                const lastMsg = messages[messages.length - 1];
                
                if (firstMsg && firstMsg.id !== lastMsg?.id) {
                    events.push({
                        type: 'first_message',
                        date: firstMsg.created_at,
                        icon: 'ki-message',
                        color: 'success',
                        title: 'Primeira mensagem',
                        description: null,
                        user_name: null
                    });
                }
                
                if (lastMsg) {
                    events.push({
                        type: 'last_message',
                        date: lastMsg.created_at,
                        icon: 'ki-message',
                        color: 'warning',
                        title: 'Última mensagem',
                        description: null,
                        user_name: null
                    });
                }
            }
            
            // Notas internas
            notes.forEach(note => {
                const userInitials = getInitials(note.user_name || 'U');
                events.push({
                    type: 'note',
                    date: note.created_at,
                    icon: 'ki-note-edit',
                    color: 'secondary',
                    title: `Nota por ${escapeHtml(note.user_name || 'Usuário')}`,
                    description: escapeHtml(note.content),
                    userInitials: userInitials,
                    noteId: note.id,
                    userId: note.user_id,
                    isPrivate: note.is_private,
                    user_name: note.user_name
                });
            });
            
            // Status - verificar se não há evento mais recente
            const hasRecentStatusChange = timelineEvents.some(e => e.type === 'closed' || e.type === 'reopened');
            if (conv.status === 'closed' && conv.resolved_at && !hasRecentStatusChange) {
                events.push({
                    type: 'closed',
                    date: conv.resolved_at,
                    icon: 'ki-cross-circle',
                    color: 'dark',
                    title: 'Conversa fechada',
                    description: null,
                    user_name: null
                });
            }
            
            // Adicionar evento de reabertura se status for open mas havia evento de fechamento
            if (conv.status === 'open' && timelineEvents.some(e => e.type === 'closed')) {
                const lastClosed = timelineEvents.filter(e => e.type === 'closed').sort((a, b) => new Date(b.date) - new Date(a.date))[0];
                if (lastClosed) {
                    const reopenedEvent = timelineEvents.find(e => e.type === 'reopened' && new Date(e.date) > new Date(lastClosed.date));
                    if (!reopenedEvent) {
                        events.push({
                            type: 'reopened',
                            date: conv.updated_at || new Date().toISOString(),
                            icon: 'ki-entrance-right',
                            color: 'success',
                            title: 'Conversa reaberta',
                            description: null,
                            user_name: null
                        });
                    }
                }
            }
            
            // Ordenar eventos por data (mais recente primeiro)
            events.sort((a, b) => new Date(b.date) - new Date(a.date));
            
            // Gerar HTML da timeline
            let timelineHtml = '';
            events.forEach((event, index) => {
                const isLast = index === events.length - 1;
                const lineClass = isLast ? '' : 'w-40px';
                
                if (event.type === 'note') {
                    timelineHtml += `
                        <div class="timeline-item">
                            <div class="timeline-line ${lineClass}"></div>
                            <div class="timeline-icon symbol symbol-circle symbol-40px">
                                <div class="symbol-label bg-light-${event.color}">
                                    ${event.userInitials ? `
                                        <div class="symbol-label bg-light-${event.color} text-${event.color} fw-bold fs-7">
                                            ${event.userInitials}
                                        </div>
                                    ` : `
                                        <i class="ki-duotone ${event.icon} fs-2 text-${event.color}">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    `}
                                </div>
                            </div>
                            <div class="timeline-content mb-10">
                                <div class="fw-semibold text-gray-800">${event.title}</div>
                                ${event.description ? `<div class="text-gray-700 fs-7 mt-2 p-3 bg-light rounded">${event.description}</div>` : ''}
                                <div class="text-muted fs-7 mt-1">${formatDateTime(event.date)}</div>
                            </div>
                        </div>
                    `;
                } else {
                    // Determinar se mostra nome do usuário
                    const showUserName = event.user_name && event.type !== 'note';
                    const userInfo = showUserName ? `<div class="text-muted fs-8 mt-1">por ${escapeHtml(event.user_name)}</div>` : '';
                    
                    timelineHtml += `
                        <div class="timeline-item">
                            <div class="timeline-line ${lineClass}"></div>
                            <div class="timeline-icon symbol symbol-circle symbol-40px">
                                <div class="symbol-label bg-light-${event.color}">
                                    <i class="ki-duotone ${event.icon} fs-2 text-${event.color}">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        ${event.icon.includes('message-text') ? '<span class="path3"></span>' : ''}
                                        ${event.icon.includes('arrows-circle') ? '<span class="path3"></span><span class="path4"></span>' : ''}
                                        ${event.icon.includes('entrance-right') ? '<span class="path3"></span>' : ''}
                                    </i>
                                </div>
                            </div>
                            <div class="timeline-content mb-10">
                                <div class="fw-semibold text-gray-800">${event.title}</div>
                                ${event.description ? `<div class="text-muted fs-7 mt-1">${event.description}</div>` : ''}
                                ${userInfo}
                                <div class="text-muted fs-7 mt-1">${formatDateTime(event.date)}</div>
                            </div>
                        </div>
                    `;
                }
            });
            
            if (timelineHtml === '') {
                timelineHtml = '<div class="text-muted fs-7 text-center py-5">Nenhum evento na timeline</div>';
            }
            
            timelineContainer.innerHTML = timelineHtml;
        })
        .catch(error => {
            console.error('Erro ao carregar timeline:', error);
            timelineContainer.innerHTML = '<div class="text-muted fs-7 text-center py-5">Erro ao carregar timeline</div>';
        });
}

// Histórico do contato (aba Histórico)
function loadConversationSentiment(conversationId) {
    if (!conversationId) return;
    
    const sentimentInfo = document.getElementById('sentiment-info');
    const sentimentLabel = document.getElementById('sentiment-label');
    const sentimentProgress = document.getElementById('sentiment-progress');
    const sentimentScore = document.getElementById('sentiment-score');
    
    if (!sentimentInfo) return;
    
    fetch(`<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}/sentiment`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success || !data.sentiment) {
            sentimentInfo.style.display = 'none';
            return;
        }
        
        const sentiment = data.sentiment;
        const score = parseFloat(sentiment.sentiment_score || 0);
        const label = sentiment.sentiment_label || 'neutral';
        
        // Calcular porcentagem para barra de progresso (score de -1 a 1 vira 0 a 100)
        const percentage = ((score + 1) / 2) * 100;
        
        // Determinar cor baseado no sentimento
        let colorClass = 'secondary';
        let labelText = 'Neutro';
        if (label === 'positive') {
            colorClass = 'success';
            labelText = 'Positivo';
        } else if (label === 'negative') {
            colorClass = 'danger';
            labelText = 'Negativo';
        }
        
        if (sentimentLabel) sentimentLabel.innerHTML = `<span class="badge badge-light-${colorClass}">${labelText}</span>`;
        if (sentimentProgress) {
            sentimentProgress.className = `progress-bar bg-${colorClass}`;
            sentimentProgress.style.width = `${percentage}%`;
        }
        if (sentimentScore) sentimentScore.textContent = `Score: ${score.toFixed(2)}`;
        
        sentimentInfo.style.display = 'block';
    })
    .catch(error => {
        console.error('Erro ao carregar sentimento:', error);
        sentimentInfo.style.display = 'none';
    });
}

function loadContactHistory(contactId) {
    if (!contactId) return;
    const countEl = document.getElementById('history-conversations-count');
    const avgEl = document.getElementById('history-avg-time');
    const csatEl = document.getElementById('history-satisfaction');
    const listEl = document.getElementById('history-previous-conversations');

    // Placeholders
    if (countEl) countEl.textContent = '-';
    if (avgEl) avgEl.textContent = '-';
    if (csatEl) csatEl.textContent = '-';
    if (listEl) listEl.innerHTML = `<div class="text-center py-5"><p class="text-muted fs-7">Carregando...</p></div>`;

    fetch(`<?= \App\Helpers\Url::to('/contacts') ?>/${contactId}/history`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(data => {
        console.log('📊 Dados do histórico:', data);
        
        if (!data.success) {
            if (listEl) listEl.innerHTML = `<div class="text-center py-5"><p class="text-muted fs-7">Erro ao carregar histórico</p></div>`;
            if (countEl) countEl.textContent = '-';
            if (avgEl) avgEl.textContent = '-';
            if (csatEl) csatEl.textContent = '--';
            return;
        }
        
        const total = data.total_conversations ?? 0;
        const avgSec = data.avg_duration_seconds;
        const avgMin = data.avg_duration_minutes;
        const avgHours = data.avg_duration_hours;
        const csat = data.csat_score;
        const previous = data.previous_conversations || [];

        // Atualizar contador de conversas
        if (countEl) countEl.textContent = total;
        
        // Atualizar tempo médio
        if (avgEl) {
            if (avgSec !== null && avgSec !== undefined && avgSec > 0) {
                avgEl.textContent = formatDuration(avgSec);
            } else if (total === 0) {
                avgEl.textContent = 'Sem dados';
            } else {
                avgEl.textContent = '-';
            }
        }
        
        // Atualizar CSAT (ainda não implementado)
        if (csatEl) csatEl.textContent = csat !== null && csat !== undefined ? csat : '--';

        if (!previous.length) {
            if (listEl) listEl.innerHTML = `<div class="text-center py-5"><p class="text-muted fs-7">Nenhuma conversa anterior</p></div>`;
            return;
        }

        let html = '';
        previous.forEach(conv => {
            const lastMsg = conv.last_message ? escapeHtml(conv.last_message.substring(0, 60)) + (conv.last_message.length > 60 ? '...' : '') : 'Sem mensagens';
            const updatedAt = conv.updated_at || conv.created_at;
            html += `
                <div class="d-flex flex-column p-3 border rounded mb-2 bg-light-dark">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-semibold">Conversa #${conv.id}</span>
                        <span class="badge badge-light-secondary">${conv.status || ''}</span>
                    </div>
                    <div class="text-muted fs-8">${formatDateTime(updatedAt)}</div>
                    <div class="text-muted fs-8 mt-1">${lastMsg}</div>
                </div>
            `;
        });
        if (listEl) listEl.innerHTML = html;
    })
    .catch(error => {
        console.error('❌ Erro ao carregar histórico:', error);
        if (listEl) listEl.innerHTML = `<div class="text-center py-5"><p class="text-muted fs-7">Erro ao carregar histórico</p></div>`;
        if (countEl) countEl.textContent = '-';
        if (avgEl) avgEl.textContent = '-';
        if (csatEl) csatEl.textContent = '--';
    });
}

function formatDuration(seconds) {
    const sec = Math.max(0, Math.round(seconds));
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;
    if (h > 0) return `${h}h ${m}m`;
    if (m > 0) return `${m}m ${s}s`;
    return `${s}s`;
}

function formatDateTime(dt) {
    if (!dt) return '-';
    const d = new Date(dt);
    if (isNaN(d.getTime())) return dt;
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' }) +
           ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

// Função auxiliar para obter iniciais do nome
function getInitials(name) {
    if (!name) return 'U';
    const parts = name.trim().split(' ');
    if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
}

function formatDateTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', { 
        day: '2-digit', 
        month: '2-digit', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Carregar agentes do contato na sidebar
function loadContactAgents(contactId) {
    if (!contactId) return;
    
    const agentsListEl = document.getElementById('contact-agents-list');
    const manageBtn = document.getElementById('sidebar-manage-contact-agents-btn');
    
    if (!agentsListEl) return;
    
    fetch(`<?= \App\Helpers\Url::to('/contacts') ?>/${contactId}/agents`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success || !data.agents) {
            agentsListEl.innerHTML = '<div class="text-muted fs-7">Nenhum agente atribuído</div>';
            if (manageBtn) manageBtn.style.display = 'none';
            return;
        }
        
        const agents = data.agents || [];
        
        if (agents.length === 0) {
            agentsListEl.innerHTML = '<div class="text-muted fs-7">Nenhum agente atribuído</div>';
            if (manageBtn) manageBtn.style.display = 'none';
            return;
        }
        
        // Renderizar lista de agentes
        let html = '';
        agents.forEach(agent => {
            const isPrimary = agent.is_primary == 1 || agent.is_primary === true;
            const agentName = agent.agent_name || agent.name || 'Agente';
            const agentEmail = agent.agent_email || agent.email || '';
            const initials = getInitials(agentName);
            
            html += `
                <div class="d-flex align-items-center gap-2 p-2 border rounded mb-2" style="background: var(--bs-gray-100);">
                    <div class="symbol symbol-30px symbol-circle">
                        <div class="symbol-label bg-light-primary text-primary fw-bold fs-7">
                            ${initials}
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold fs-7">
                            ${escapeHtml(agentName)}
                            ${isPrimary ? '<span class="badge badge-sm badge-primary ms-1">Principal</span>' : ''}
                        </div>
                        ${agentEmail ? `<div class="text-muted fs-8">${escapeHtml(agentEmail)}</div>` : ''}
                    </div>
                </div>
            `;
        });
        
        agentsListEl.innerHTML = html;
        
        // Mostrar botão de gerenciar se tiver permissão
        if (manageBtn) {
            manageBtn.setAttribute('onclick', `manageContactAgents(${contactId})`);
            manageBtn.style.display = '';
        }
    })
    .catch(error => {
        console.error('Erro ao carregar agentes do contato:', error);
        agentsListEl.innerHTML = '<div class="text-muted fs-7">Erro ao carregar agentes</div>';
        if (manageBtn) manageBtn.style.display = 'none';
    });
}

/**
 * Gerenciar agentes do contato (abrir modal)
 */
function manageContactAgents(contactId) {
    if (!contactId) {
        console.error('ID do contato não informado');
        return;
    }
    
    // Definir ID do contato no modal
    document.getElementById('contactAgentsModalContactId').value = contactId;
    
    // Limpar seleção de agente
    document.getElementById('addContactAgentSelect').value = '';
    document.getElementById('addAsPrimaryAgent').checked = false;
    
    // Restaurar todas as opções do select antes de carregar
    const select = document.getElementById('addContactAgentSelect');
    if (select) {
        Array.from(select.options).forEach(option => {
            option.style.display = '';
            option.disabled = false;
        });
    }
    
    // Carregar agentes do contato
    loadContactAgentsInModal(contactId);
    
    // Abrir modal
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_contact_agents'));
    modal.show();
}

/**
 * Carregar agentes do contato no modal
 */
function loadContactAgentsInModal(contactId) {
    const agentsListEl = document.getElementById('contactAgentsList');
    if (!agentsListEl) return;
    
    agentsListEl.innerHTML = '<div class="text-muted fs-7 text-center py-3">Carregando...</div>';
    
    fetch(`<?= \App\Helpers\Url::to('/contacts') ?>/${contactId}/agents`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success || !data.agents) {
            agentsListEl.innerHTML = '<div class="text-muted fs-7 text-center py-3">Nenhum agente atribuído</div>';
            updateAgentSelect([]);
            return;
        }
        
        const agents = data.agents || [];
        
        if (agents.length === 0) {
            agentsListEl.innerHTML = '<div class="text-muted fs-7 text-center py-3">Nenhum agente atribuído</div>';
            updateAgentSelect([]);
            return;
        }
        
        // Renderizar lista de agentes com ações
        renderContactAgentsList(agents, contactId);
        
        // Atualizar select removendo agentes já adicionados
        const agentIds = agents.map(a => a.agent_id || a.id);
        updateAgentSelect(agentIds);
    })
    .catch(error => {
        console.error('Erro ao carregar agentes do contato:', error);
        agentsListEl.innerHTML = '<div class="text-danger fs-7 text-center py-3">Erro ao carregar agentes</div>';
    });
}

/**
 * Atualizar select de agentes, ocultando os que já estão na lista
 */
function updateAgentSelect(excludedAgentIds) {
    const select = document.getElementById('addContactAgentSelect');
    if (!select) return;
    
    // Percorrer todas as opções e mostrar/ocultar conforme necessário
    Array.from(select.options).forEach(option => {
        if (option.value === '') {
            // Manter opção vazia sempre visível
            return;
        }
        
        const isExcluded = excludedAgentIds.includes(parseInt(option.value));
        
        if (isExcluded) {
            // Ocultar opção (mas não remover, para poder restaurar depois)
            option.style.display = 'none';
            option.disabled = true;
        } else {
            // Mostrar opção
            option.style.display = '';
            option.disabled = false;
        }
    });
    
    // Se a opção selecionada foi ocultada, limpar seleção
    if (select.value && excludedAgentIds.includes(parseInt(select.value))) {
        select.value = '';
    }
}

/**
 * Renderizar lista de agentes no modal
 */
function renderContactAgentsList(agents, contactId) {
    const agentsListEl = document.getElementById('contactAgentsList');
    if (!agentsListEl) return;
    
    let html = '';
    
    agents.forEach(agent => {
        const isPrimary = agent.is_primary == 1 || agent.is_primary === true;
        const agentName = agent.agent_name || agent.name || 'Agente';
        const agentEmail = agent.agent_email || agent.email || '';
        const initials = getInitials(agentName);
        const priority = agent.priority || 0;
        const agentId = agent.agent_id || agent.id;
        
        html += `
            <div class="d-flex align-items-center gap-3 p-3 border rounded mb-2" style="background: var(--bs-gray-50);" data-agent-id="${agentId}">
                <div class="symbol symbol-40px symbol-circle">
                    <div class="symbol-label bg-light-primary text-primary fw-bold">
                        ${initials}
                    </div>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="fw-semibold">${escapeHtml(agentName)}</span>
                        ${isPrimary ? '<span class="badge badge-primary">Principal</span>' : ''}
                    </div>
                    ${agentEmail ? `<div class="text-muted fs-7">${escapeHtml(agentEmail)}</div>` : ''}
                    <div class="mt-2">
                        <label class="form-label fs-7 mb-1">Prioridade:</label>
                        <input type="number" 
                               class="form-control form-control-sm d-inline-block" 
                               style="width: 80px;" 
                               value="${priority}" 
                               min="0" 
                               max="100"
                               onchange="updateContactAgentPriority(${contactId}, ${agentId}, this.value)">
                    </div>
                </div>
                <div class="d-flex flex-column gap-2">
                    ${!isPrimary ? `
                        <button class="btn btn-sm btn-light-primary" 
                                onclick="setContactPrimaryAgent(${contactId}, ${agentId})"
                                title="Definir como principal">
                            <i class="ki-duotone ki-star fs-6">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </button>
                    ` : `
                        <button class="btn btn-sm btn-light-success" disabled title="Agente principal">
                            <i class="ki-duotone ki-check fs-6">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </button>
                    `}
                    <button class="btn btn-sm btn-light-danger" 
                            onclick="removeContactAgent(${contactId}, ${agentId}, '${escapeHtml(agentName)}')"
                            title="Remover agente">
                        <i class="ki-duotone ki-trash fs-6">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                    </button>
                </div>
            </div>
        `;
    });
    
    agentsListEl.innerHTML = html;
}

/**
 * Mostrar toast/notificação de sucesso ou erro
 */
function showToast(type, message) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: type === 'success' ? 'success' : 'error',
            title: type === 'success' ? 'Sucesso!' : 'Erro',
            text: message,
            timer: type === 'success' ? 2000 : 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    } else {
        alert(message);
    }
}

/**
 * Adicionar agente ao contato a partir do modal
 */
function addContactAgentFromModal() {
    const contactId = document.getElementById('contactAgentsModalContactId').value;
    const agentId = document.getElementById('addContactAgentSelect').value;
    const isPrimary = document.getElementById('addAsPrimaryAgent').checked;
    
    if (!contactId) {
        alert('Erro: ID do contato não encontrado');
        return;
    }
    
    if (!agentId) {
        alert('Por favor, selecione um agente');
        return;
    }
    
    // Verificar se agente já está na lista
    const agentsListEl = document.getElementById('contactAgentsList');
    if (agentsListEl) {
        const existingAgents = agentsListEl.querySelectorAll('[data-agent-id]');
        for (let el of existingAgents) {
            if (el.getAttribute('data-agent-id') == agentId) {
                showToast('error', 'Este agente já está na lista');
                return;
            }
        }
    }
    
    // Desabilitar botão durante requisição
    const addBtn = event.target.closest('button');
    const originalText = addBtn.innerHTML;
    addBtn.disabled = true;
    addBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Adicionando...';
    
    const formData = new FormData();
    formData.append('agent_id', agentId);
    formData.append('is_primary', isPrimary ? '1' : '0');
    formData.append('priority', '0');
    
    fetch(`<?= \App\Helpers\Url::to('/contacts') ?>/${contactId}/agents`, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Limpar formulário
            document.getElementById('addContactAgentSelect').value = '';
            document.getElementById('addAsPrimaryAgent').checked = false;
            
            // Recarregar lista (isso também atualizará o select)
            loadContactAgentsInModal(contactId);
            
            // Recarregar na sidebar também
            loadContactAgents(contactId);
            
            // Mostrar mensagem de sucesso
            showToast('success', 'Agente adicionado com sucesso!');
        } else {
            alert('Erro: ' + (data.message || 'Erro ao adicionar agente'));
        }
    })
    .catch(error => {
        console.error('Erro ao adicionar agente:', error);
        alert('Erro ao adicionar agente');
    })
    .finally(() => {
        addBtn.disabled = false;
        addBtn.innerHTML = originalText;
    });
}

/**
 * Remover agente do contato
 */
function removeContactAgent(contactId, agentId, agentName) {
    if (!confirm(`Tem certeza que deseja remover o agente "${agentName}" deste contato?`)) {
        return;
    }
    
    fetch(`<?= \App\Helpers\Url::to('/contacts') ?>/${contactId}/agents/${agentId}`, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Recarregar lista no modal
            loadContactAgentsInModal(contactId);
            
            // Recarregar na sidebar também
            loadContactAgents(contactId);
            
            // Mostrar mensagem de sucesso
            showToast('success', 'Agente removido com sucesso!');
        } else {
            alert('Erro: ' + (data.message || 'Erro ao remover agente'));
        }
    })
    .catch(error => {
        console.error('Erro ao remover agente:', error);
        alert('Erro ao remover agente');
    });
}

/**
 * Definir agente principal
 */
function setContactPrimaryAgent(contactId, agentId) {
    const formData = new FormData();
    formData.append('agent_id', agentId);
    
    fetch(`<?= \App\Helpers\Url::to('/contacts') ?>/${contactId}/agents/set-primary`, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Recarregar lista no modal
            loadContactAgentsInModal(contactId);
            
            // Recarregar na sidebar também
            loadContactAgents(contactId);
            
            // Mostrar mensagem de sucesso
            showToast('success', 'Agente principal definido com sucesso!');
        } else {
            alert('Erro: ' + (data.message || 'Erro ao definir agente principal'));
        }
    })
    .catch(error => {
        console.error('Erro ao definir agente principal:', error);
        alert('Erro ao definir agente principal');
    });
}

/**
 * Atualizar prioridade do agente
 */
function updateContactAgentPriority(contactId, agentId, priority) {
    const priorityValue = parseInt(priority) || 0;
    
    // Validar range
    if (priorityValue < 0 || priorityValue > 100) {
        alert('Prioridade deve estar entre 0 e 100');
        // Recarregar para restaurar valor anterior
        loadContactAgentsInModal(contactId);
        return;
    }
    
    // Atualizar via API (usando método store que atualiza se já existe)
    const formData = new FormData();
    formData.append('agent_id', agentId);
    formData.append('priority', priorityValue);
    
    fetch(`<?= \App\Helpers\Url::to('/contacts') ?>/${contactId}/agents`, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Recarregar lista para atualizar ordem
            loadContactAgentsInModal(contactId);
            
            // Mostrar mensagem de sucesso
            showToast('success', 'Prioridade atualizada!');
        } else {
            alert('Erro: ' + (data.message || 'Erro ao atualizar prioridade'));
            // Recarregar para restaurar valor anterior
            loadContactAgentsInModal(contactId);
        }
    })
    .catch(error => {
        console.error('Erro ao atualizar prioridade:', error);
        alert('Erro ao atualizar prioridade');
        // Recarregar para restaurar valor anterior
        loadContactAgentsInModal(contactId);
    });
}


// Delegação de eventos para conversation-item (resolve problema de função não definida em onclick)
document.addEventListener('click', function(e) {
    const conversationItem = e.target.closest('.conversation-item[data-onclick="selectConversation"]');
    if (conversationItem) {
        const conversationId = parseInt(conversationItem.getAttribute('data-conversation-id'));
        if (!isNaN(conversationId)) {
            selectConversation(conversationId);
        }
    }
});

// Auto-scroll para última mensagem
document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Restaurar estado do sidebar
    const sidebarOpen = localStorage.getItem('conversationSidebarOpen') === 'true';
    if (sidebarOpen) {
        document.getElementById('conversationSidebar')?.classList.add('open');
    }
    
    // Corrigir z-index dos modais e backdrops
    console.log('Configurando correção de z-index para modais');
    fixModalZIndex();
    
    // Observar quando modais são abertos
    document.addEventListener('shown.bs.modal', function(e) {
        console.log('Modal aberto, corrigindo z-index');
        setTimeout(fixModalZIndex, 100);
    });
    
    // Listener para checkboxes de canais no modal de filtros avançados
    const channelCheckboxes = document.querySelectorAll('input[name="channels[]"]');
    channelCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateWhatsAppAccountsFilter);
    });
    
    // Inicializar seletor rápido de templates
    initTemplateQuickSelect();
    
    // Inicializar busca de mensagens
    const messageSearchInput = document.getElementById('messageSearch');
    if (messageSearchInput) {
        messageSearchInput.addEventListener('keyup', function(e) {
            searchMessagesInConversation(e);
        });
    }
    
    // Handler do formulário de escalação
    const escalateForm = document.getElementById('escalateForm');
    if (escalateForm) {
        escalateForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const conversationId = document.getElementById('escalateConversationId').value;
            const agentId = document.getElementById('escalateAgent').value;
            
            if (!conversationId) {
                const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                                   document.body.classList.contains('dark-mode') ||
                                   window.matchMedia('(prefers-color-scheme: dark)').matches;
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'ID da conversa não encontrado',
                    colorScheme: isDarkMode ? 'dark' : 'light'
                });
                return;
            }
            
            const btn = escalateForm.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Escalando...';
            
            try {
                const formData = new FormData();
                if (agentId) {
                    formData.append('agent_id', agentId);
                }
                
                const response = await fetch(`<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}/escalate`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                                   document.body.classList.contains('dark-mode') ||
                                   window.matchMedia('(prefers-color-scheme: dark)').matches;
                
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('kt_modal_escalate')).hide();
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: data.message || 'Conversa escalada com sucesso',
                        colorScheme: isDarkMode ? 'dark' : 'light',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    // Recarregar detalhes da conversa
                    if (currentConversationId) {
                        loadConversationDetails(currentConversationId);
                    }
                    
                    // Recarregar lista de conversas (preservando filtros)
                    const urlParams = new URLSearchParams(window.location.search);
                    refreshConversationList(urlParams);
                } else {
                    throw new Error(data.message || 'Erro ao escalar conversa');
                }
            } catch (error) {
                const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                                   document.body.classList.contains('dark-mode') ||
                                   window.matchMedia('(prefers-color-scheme: dark)').matches;
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: error.message || 'Erro ao escalar conversa',
                    colorScheme: isDarkMode ? 'dark' : 'light'
                });
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });
    }
    
    // Se houver conversa selecionada no carregamento inicial, atualizar sidebar
    const selectedConversationId = parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
    const selectedConversation = parsePhpJson('<?= json_encode($selectedConversation ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
    
    if (selectedConversationId) {
        // IMPORTANTE: Definir currentConversationId para que funcionalidades como Assistente IA funcionem
        currentConversationId = parseInt(selectedConversationId);
        window.currentConversationId = currentConversationId; // Garantir que window também é atualizado
        console.log('🔄 [DOMContentLoaded] currentConversationId definido:', currentConversationId);
        
        // Marcar conversa como ativa na lista
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.classList.remove('active');
        });
        const conversationItem = document.querySelector(`[data-conversation-id="${selectedConversationId}"]`);
        if (conversationItem) {
            conversationItem.classList.add('active');
        }
        
        // Se já temos dados da conversa do PHP, usar diretamente
        if (selectedConversation) {
            // Buscar tags da conversa
            fetch(`<?= \App\Helpers\Url::to('/conversations') ?>/${selectedConversationId}/tags`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                const tags = data.success && data.tags ? data.tags : [];
                // Atualizar sidebar com dados da conversa
                updateConversationSidebar(selectedConversation, tags);
                // Atualizar timeline
                updateConversationTimeline(selectedConversationId);
            })
            .catch(error => {
                console.error('Erro ao carregar tags:', error);
                // Mesmo sem tags, atualizar sidebar
                updateConversationSidebar(selectedConversation, []);
                updateConversationTimeline(selectedConversationId);
            });
        } else {
            // Se não temos dados completos, buscar via AJAX
            fetch(`<?= \App\Helpers\Url::to('/conversations') ?>/${selectedConversationId}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.conversation) {
                    const tags = data.tags || [];
                    // Atualizar sidebar com dados da conversa
                    updateConversationSidebar(data.conversation, tags);
                    // Atualizar timeline
                    updateConversationTimeline(selectedConversationId);
                }
            })
            .catch(error => {
                console.error('Erro ao carregar conversa:', error);
            });
        }
    }
    
    // Suportar navegação pelo histórico do navegador
    window.addEventListener('popstate', function(event) {
        const urlParams = new URLSearchParams(window.location.search);
        const conversationId = urlParams.get('id');
        if (conversationId) {
            selectConversation(parseInt(conversationId));
        } else {
            // Se não tem ID, limpar chat e resetar currentConversationId
            currentConversationId = null;
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.innerHTML = `
                    <div class="d-flex align-items-center justify-content-center" style="height: 100%;">
                        <div class="text-center text-muted">
                            <i class="ki-duotone ki-chat fs-3x mb-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="fw-semibold">Selecione uma conversa</div>
                            <div class="fs-7">Escolha uma conversa da lista para começar</div>
                        </div>
                    </div>
                `;
            }
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
            });
        }
    });
});

// Auto-expand textarea
document.getElementById('messageInput')?.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 150) + 'px';
});

// Enviar mensagem (Enter)
document.getElementById('messageInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

// Busca com debounce para AJAX
let conversationsSearchDebounce = null;

document.getElementById('kt_conversations_search')?.addEventListener('input', function(e) {
    // Limpar debounce anterior
    if (conversationsSearchDebounce) {
        clearTimeout(conversationsSearchDebounce);
    }
    
    // Debounce: aguardar 500ms após parar de digitar
    conversationsSearchDebounce = setTimeout(() => {
        applyFilters();
    }, 500);
});

// Event listeners para filtros dropdown
document.getElementById('filter_status')?.addEventListener('change', applyFilters);
document.getElementById('filter_channel')?.addEventListener('change', applyFilters);
document.getElementById('filter_department')?.addEventListener('change', applyFilters);
document.getElementById('filter_tag')?.addEventListener('change', applyFilters);
document.getElementById('filter_agent')?.addEventListener('change', applyFilters);

document.getElementById('kt_conversations_search')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        if (conversationsSearchDebounce) {
            clearTimeout(conversationsSearchDebounce);
        }
        applyFilters();
    }
});

// Filtros
document.querySelectorAll('#filter_status, #filter_channel, #filter_department, #filter_tag').forEach(select => {
    if (select) {
        select.addEventListener('change', applyFilters);
    }
});

function applyFilters() {
    const search = document.getElementById('kt_conversations_search')?.value || '';
    const status = document.getElementById('filter_status')?.value || '';
    const channel = document.getElementById('filter_channel')?.value || '';
    const department = document.getElementById('filter_department')?.value || '';
    const tag = document.getElementById('filter_tag')?.value || '';
    const agent = document.getElementById('filter_agent')?.value || '';
    
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    
    // Tratar status especial "unanswered" e "spam"
    if (status === 'unanswered') {
        params.append('unanswered', '1');
    } else if (status === 'spam') {
        params.append('status', 'spam');
    } else if (status) {
        params.append('status', status);
    }
    
    if (channel) params.append('channel', channel);
    if (department) params.append('department_id', department);
    if (tag) params.append('tag_id', tag);
    
    // Tratar filtro de agente
    if (agent === 'unassigned') {
        // Para "Não atribuídas", enviar agent_id=0 ou null (será tratado no backend)
        params.append('agent_id', '0');
    } else if (agent) {
        params.append('agent_id', agent);
    }
    
    // Manter filtros avançados da URL (incluindo arrays multi-select)
    const urlParams = new URLSearchParams(window.location.search);
    
    // Preservar filtros multi-select (arrays)
    ['channels[]', 'tag_ids[]', 'whatsapp_account_ids[]'].forEach(key => {
        urlParams.getAll(key).forEach(value => {
            params.append(key, value);
        });
    });
    
    // Preservar filtros avançados simples (não preservar unanswered pois já foi tratado acima)
    ['answered', 'date_from', 'date_to', 'pinned', 'order_by', 'order_dir'].forEach(key => {
        if (urlParams.has(key)) {
            params.append(key, urlParams.get(key));
        }
    });
    
    // Preservar ID da conversa selecionada se houver
    const currentConversationId = urlParams.get('id');
    if (currentConversationId) {
        params.append('id', currentConversationId);
    }
    
    // Atualizar URL sem recarregar página
    const newUrl = '<?= \App\Helpers\Url::to('/conversations') ?>' + (params.toString() ? '?' + params.toString() : '');
    window.history.pushState({ filters: params.toString() }, '', newUrl);
    
    // Buscar conversas via AJAX
    refreshConversationList(params);
}

function refreshConversationList(params = null) {
    console.debug('[TAGS_DEBUG] refreshConversationList start', params instanceof URLSearchParams ? Object.fromEntries(params.entries()) : params);
    const conversationsList = document.querySelector('.conversations-list-items');
    if (!conversationsList) {
        console.error('Elemento .conversations-list-items não encontrado!');
        return;
    }
    
    // Se params não foi fornecido, usar filtros da URL atual preservando TODOS os parâmetros
    if (!params) {
        params = new URLSearchParams(window.location.search);
    }
    
    // Evitar flicker: só mostra spinner no primeiro carregamento OU quando há filtros aplicados
    const isFirstLoad = conversationsList.dataset.loaded !== '1';
    
    // Verificar se há filtros aplicados (não é apenas polling)
    const hasFilters = params && params instanceof URLSearchParams && (
        params.has('search') ||
        params.has('status') ||
        params.has('channel') ||
        params.has('department_id') ||
        params.has('tag_id') ||
        params.has('agent_id') ||
        params.has('unanswered') ||
        params.has('channels[]') ||
        params.has('tag_ids[]') ||
        params.has('whatsapp_account_ids[]') ||
        params.has('answered') ||
        params.has('date_from') ||
        params.has('date_to') ||
        params.has('pinned')
    );
    
    // Mostrar loading apenas no primeiro carregamento OU quando há filtros aplicados (não durante polling)
    if (isFirstLoad || hasFilters) {
        conversationsList.innerHTML = `
            <div class="d-flex align-items-center justify-content-center py-10">
                <div class="text-center">
                    <span class="spinner-border spinner-border-sm text-primary mb-3" role="status"></span>
                    <div class="text-muted fs-7">Carregando conversas...</div>
                </div>
            </div>
        `;
    }
    
    // Construir URL preservando TODOS os filtros
    let url = '<?= \App\Helpers\Url::to('/conversations') ?>';
    
    // Se params é URLSearchParams, converter para string
    if (params instanceof URLSearchParams) {
        const paramsString = params.toString();
        if (paramsString) {
            url += '?' + paramsString;
        }
    } else if (params && typeof params === 'string') {
        url += '?' + params;
    } else if (params) {
        url += '?' + params.toString();
    } else {
        // Usar parâmetros da URL atual
        url += window.location.search;
    }
    
    // Adicionar header para retornar JSON (sem sobrescrever filtros existentes) + cache buster
    url += (url.includes('?') ? '&' : '?') + 'format=json&_ts=' + Date.now();
    
    console.log('Buscando conversas na URL:', url);
    
    fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        // Verificar se a resposta é JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Resposta não é JSON:', text.substring(0, 500));
                console.error('URL:', url);
                console.error('Status:', response.status);
                throw new Error('Resposta não é JSON. Status: ' + response.status);
            });
        }
        return response.json();
    })
    .then(data => {
        if (!data.success || !data.conversations) {
            console.error('Erro na resposta:', data);
            conversationsList.innerHTML = `
                <div class="text-center py-10">
                    <div class="text-muted">Erro ao carregar conversas</div>
                    <div class="text-muted fs-7">${data.message || 'Erro desconhecido'}</div>
                </div>
            `;
            return;
        }
        
        const conversations = data.conversations;
        console.debug('[TAGS_DEBUG] conversas:', conversations.length, 'primeira tags_data:', conversations[0]?.tags_data);
        
        // Calcular assinatura para evitar re-render quando não houver mudanças
        const signature = JSON.stringify(conversations.map(c => [
            c.id,
            c.pinned,
            c.pinned_at,
            c.updated_at,
            c.status,
            c.unread_count,
            c.tags_data ? JSON.stringify(c.tags_data) : null
        ]));
        if (window.lastConversationListSignature === signature) {
            return;
        }
        window.lastConversationListSignature = signature;
        
        // Obter ID da conversa selecionada da URL atual
        const urlParams = new URLSearchParams(window.location.search);
        const selectedConversationId = urlParams.get('id') ? parseInt(urlParams.get('id')) : null;
        
        if (conversations.length === 0) {
            conversationsList.innerHTML = `
                <div class="text-center py-10">
                    <i class="ki-duotone ki-message-text fs-3x text-gray-400 mb-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <h5>Nenhuma conversa encontrada</h5>
                    <p class="text-muted">Tente ajustar os filtros de busca</p>
                </div>
            `;
            return;
        }
        
        // Renderizar conversas
        let html = '';
        conversations.forEach(conv => {
            const channelIcon = conv.channel === 'whatsapp' 
                ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="#25D366" style="vertical-align: middle;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>'
                : conv.channel === 'email' ? '✉️' : '💬';
            
            const channelName = conv.channel === 'whatsapp' ? 'WhatsApp' : (conv.channel === 'email' ? 'Email' : 'Chat');
            
            const isActive = selectedConversationId == conv.id;
            const nameRaw = conv.contact_name || 'NN';
            const maxName = 22;
            const name = nameRaw.length > maxName ? nameRaw.substring(0, maxName) + '...' : nameRaw;
            const parts = nameRaw.split(' ');
            const initials = (parts[0].charAt(0) + (parts[1] ? parts[1].charAt(0) : '')).toUpperCase();
            
            const lastMessage = conv.last_message || '';
            const maxCharsPreview = 37;
            const lastMessagePreview = lastMessage.length > maxCharsPreview ? lastMessage.substring(0, maxCharsPreview) + '...' : lastMessage;
            
            const unreadCount = conv.unread_count || 0;
            const pinned = conv.pinned || 0;
            
            // Tags
            let tagsHtml = '';
            if (conv.tags_data) {
                const tags = conv.tags_data.split('|||');
                tags.slice(0, 2).forEach(tagStr => {
                    const [tagId, tagName, tagColor] = tagStr.split(':');
                    if (tagName) {
                        tagsHtml += `<span class="badge badge-sm" style="background-color: ${tagColor || '#009ef7'}20; color: ${tagColor || '#009ef7'};">${escapeHtml(tagName)}</span>`;
                    }
                });
            }
            
    const avatarHtml = conv.contact_avatar
        ? `<div class="symbol-label"><img src="${escapeHtml(conv.contact_avatar)}" alt="${escapeHtml(name)}" class="h-45px w-45px rounded" style="object-fit: cover;"></div>`
        : `<div class="symbol-label bg-light-primary text-primary fw-bold">${initials}</div>`;

            const firstResponseAt = conv.first_response_at_calc || conv.first_response_at || '';
            const lastContactAt = conv.last_contact_message_at || '';
            const lastAgentAt = conv.last_agent_message_at || '';
            const createdAt = conv.created_at || '';
            const lastMessageAt = conv.last_message_at || conv.updated_at || '';

            html += `
                <div class="conversation-item ${isActive ? 'active' : ''} ${pinned ? 'pinned' : ''}" 
                     data-conversation-id="${conv.id}"
                     data-status="${escapeHtml(conv.status || 'open')}"
                     data-created-at="${escapeHtml(createdAt)}"
                     data-first-response-at="${escapeHtml(firstResponseAt)}"
                     data-last-message-at="${escapeHtml(lastMessageAt)}"
                     data-last-contact-message-at="${escapeHtml(lastContactAt)}"
                     data-last-agent-message-at="${escapeHtml(lastAgentAt)}"
                     data-agent-id="${escapeHtml(conv.agent_id || '')}"
                     data-updated-at="${lastMessageAt || new Date().toISOString()}"
                     data-onclick="selectConversation">
                    <div class="d-flex gap-3 w-100">
                        <!-- Checkbox para seleção em massa -->
                        <div class="flex-shrink-0 d-flex align-items-center">
                            <label class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input conversation-checkbox" type="checkbox" value="${conv.id}" 
                                       onclick="event.stopPropagation(); toggleBulkSelection();">
                            </label>
                        </div>
                        <div class="symbol symbol-45px flex-shrink-0">
                            ${avatarHtml}
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="conversation-item-header">
                                <div class="conversation-item-name d-flex align-items-center gap-2">
                                    ${pinned ? '<i class="ki-duotone ki-pin fs-7 text-warning" title="Fixada"><span class="path1"></span><span class="path2"></span></i>' : ''}
                                    ${conv.is_spam ? '<span class="badge badge-sm badge-danger" title="Marcada como spam">🚫 SPAM</span>' : ''}
                                    ${escapeHtml(name)}
                                </div>
                    <div class="conversation-item-time d-flex align-items-center gap-2">
                        ${formatTime(conv.last_message_at || conv.updated_at)}
                        <div class="dropdown conversation-item-actions">
                            <button type="button" class="btn btn-sm btn-icon btn-light p-0" 
                                    data-bs-toggle="dropdown" 
                                    aria-expanded="false"
                                    onclick="event.stopPropagation();">
                                <i class="ki-duotone ki-setting-2 text-muted">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                    <span class="path5"></span>
                                </i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" data-conversation-id="${conv.id}">
                                <li>
                                    <a class="dropdown-item" href="#" onclick="event.stopPropagation(); togglePin(${conv.id}, ${pinned ? 'true' : 'false'}); return false;">
                                        <i class="ki-duotone ki-pin fs-7 me-2 ${pinned ? 'text-warning' : ''}">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        ${pinned ? 'Desfixar' : 'Fixar'}
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#" onclick="event.stopPropagation(); markConversationAsRead(${conv.id}); return false;">
                                        <i class="ki-duotone ki-check fs-7 me-2 text-success">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Marcar como Lido
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#" onclick="event.stopPropagation(); markConversationAsUnread(${conv.id}); return false;">
                                        <i class="ki-duotone ki-eye-slash fs-7 me-2 text-danger">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Marcar como Não Lido
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="#" onclick="event.stopPropagation(); showReminderModal(${conv.id}); return false;">
                                        <i class="ki-duotone ki-notification-bing fs-7 me-2 text-primary">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Agendar Lembrete
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                            </div>
                            <div class="conversation-item-preview">${escapeHtml(lastMessagePreview || 'Sem mensagens')}</div>
                                        ${conv.search_match_type ? `
                                <div class="conversation-item-search-match mt-1">
                                    <span class="badge badge-sm badge-light-info">
                                        <i class="ki-duotone ki-magnifier fs-7 me-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        ${conv.search_match_type === 'name' ? 'Nome' : 
                                          conv.search_match_type === 'phone' ? 'Telefone' : 
                                          conv.search_match_type === 'email' ? 'Email' : 
                                          conv.search_match_type === 'tag' ? 'Tag' :
                                          conv.search_match_type === 'participant' ? 'Participante' :
                                          'Mensagem'}: 
                                        <span class="fw-semibold">${escapeHtml((conv.search_match_text || '').substring(0, 40))}${(conv.search_match_text || '').length > 40 ? '...' : ''}</span>
                                    </span>
                                </div>
                            ` : ''}
                            <div class="conversation-item-meta">
                                <span class="conversation-item-channel">${channelIcon} ${channelName}</span>
                                <span class="conversation-item-tags d-flex gap-1 flex-wrap">${tagsHtml}</span>
                                ${unreadCount > 0 ? `<span class="conversation-item-badge">${unreadCount}</span>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        conversationsList.innerHTML = html;
        conversationsList.dataset.loaded = '1';
    })
    .catch(error => {
        console.error('Erro ao buscar conversas:', error);
        conversationsList.innerHTML = `
            <div class="text-center py-10">
                <div class="text-danger">Erro ao carregar conversas</div>
                <div class="text-muted fs-7 mt-2">${error.message || 'Erro desconhecido'}</div>
                <button class="btn btn-sm btn-light mt-3" onclick="location.reload()">Recarregar página</button>
            </div>
        `;
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return 'Agora';
    if (diffMins < 60) return `${diffMins}min`;
    if (diffHours < 24) return `${diffHours}h`;
    if (diffDays < 7) return `${diffDays}d`;
    
    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}

function openAdvancedFilters() {
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_advanced_filters'));
    modal.show();
    
    // Verificar se WhatsApp está selecionado e mostrar/ocultar filtro de integrações
    updateWhatsAppAccountsFilter();
}

// Atualizar visibilidade do filtro de integrações WhatsApp
function updateWhatsAppAccountsFilter() {
    const whatsappCheckbox = document.getElementById('filter_channel_whatsapp');
    const whatsappFilter = document.getElementById('whatsapp_accounts_filter');
    
    if (whatsappCheckbox && whatsappFilter) {
        if (whatsappCheckbox.checked) {
            whatsappFilter.style.display = 'block';
        } else {
            whatsappFilter.style.display = 'none';
        }
    }
}

function applyAdvancedFilters() {
    const form = document.getElementById('advancedFiltersForm');
    const formData = new FormData(form);
    
    const params = new URLSearchParams();
    
    // Filtros básicos (manter)
    const search = document.getElementById('kt_conversations_search')?.value || '';
    const status = document.getElementById('filter_status')?.value || '';
    const channel = document.getElementById('filter_channel')?.value || '';
    const department = document.getElementById('filter_department')?.value || '';
    const tag = document.getElementById('filter_tag')?.value || '';
    const agent = document.getElementById('filter_agent')?.value || '';
    
    if (search) params.append('search', search);
    
    // Tratar status especial "unanswered" e "spam"
    if (status === 'unanswered') {
        params.append('unanswered', '1');
    } else if (status === 'spam') {
        params.append('status', 'spam');
    } else if (status) {
        params.append('status', status);
    }
    
    if (channel) params.append('channel', channel);
    if (department) params.append('department_id', department);
    if (tag) params.append('tag_id', tag);
    
    // Canais (multi-select)
    const channels = formData.getAll('channels[]');
    if (channels.length > 0) {
        channels.forEach(ch => params.append('channels[]', ch));
    }
    
    // Tags (multi-select)
    const tagIds = formData.getAll('tag_ids[]');
    if (tagIds.length > 0) {
        tagIds.forEach(tagId => params.append('tag_ids[]', tagId));
    }
    
    // Agentes (multi-select) - substituir filtro simples se houver seleção múltipla
    const agentIds = formData.getAll('agent_ids[]');
    if (agentIds.length > 0) {
        // Usar multiselect de agentes (substitui filtro simples)
        agentIds.forEach(agentId => params.append('agent_ids[]', agentId));
    } else {
        // Se não houver multiselect, usar filtro simples
        if (agent === 'unassigned') {
            params.append('agent_id', '0');
        } else if (agent) {
            params.append('agent_id', agent);
        }
    }
    
    // Integrações WhatsApp (multi-select)
    const whatsappAccountIds = formData.getAll('whatsapp_account_ids[]');
    if (whatsappAccountIds.length > 0) {
        whatsappAccountIds.forEach(accId => params.append('whatsapp_account_ids[]', accId));
    }
    
    // Filtros avançados
    const responseStatus = formData.get('response_status');
    if (responseStatus === 'unanswered') {
        params.append('unanswered', '1');
    } else if (responseStatus === 'answered') {
        params.append('answered', '1');
    }
    
    const dateFrom = formData.get('date_from');
    if (dateFrom) params.append('date_from', dateFrom);
    
    const dateTo = formData.get('date_to');
    if (dateTo) params.append('date_to', dateTo);
    
    const pinned = formData.get('pinned');
    if (pinned !== null && pinned !== '') {
        params.append('pinned', pinned);
    }
    
    const orderBy = formData.get('order_by');
    if (orderBy) params.append('order_by', orderBy);
    
    const orderDir = formData.get('order_dir');
    if (orderDir) params.append('order_dir', orderDir);
    
    // Fechar modal e aplicar filtros
    bootstrap.Modal.getInstance(document.getElementById('kt_modal_advanced_filters')).hide();
    window.location.href = '<?= \App\Helpers\Url::to('/conversations') ?>' + (params.toString() ? '?' + params.toString() : '');
}

function clearAllFilters() {
    // Redirecionar para a página com status=open como padrão
    window.location.href = '<?= \App\Helpers\Url::to('/conversations') ?>?status=open';
}

function togglePin(conversationId, isPinned) {
    // Fechar o dropdown imediatamente
    closeAllDropdowns();
    
    const url = isPinned 
        ? `<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}/unpin`
        : `<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}/pin`;
    
    fetch(url, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Atualizar UI sem recarregar página
            const conversationItem = document.querySelector(`[data-conversation-id="${conversationId}"]`);
            if (conversationItem) {
                const newPinnedState = !isPinned;
                ensureActionsDropdown(conversationItem, newPinnedState, conversationId);
                sortConversationList();
            }
            
            // Toast de sucesso
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: data.message || (isPinned ? 'Conversa desfixada' : 'Conversa fixada'),
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
                });
            }
        } else {
            alert('Erro ao ' + (isPinned ? 'desfixar' : 'fixar') + ' conversa: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao ' + (isPinned ? 'desfixar' : 'fixar') + ' conversa');
    });
}

// Função auxiliar para fechar todos os dropdowns
function closeAllDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown.show');
    dropdowns.forEach(dropdown => {
        const toggleBtn = dropdown.querySelector('[data-bs-toggle="dropdown"]');
        if (toggleBtn) {
            const bsDropdown = bootstrap.Dropdown.getInstance(toggleBtn);
            if (bsDropdown) {
                bsDropdown.hide();
            }
        }
    });
}

// Função para corrigir z-index de modais e backdrops
function fixModalZIndex() {
    // Remover backdrops duplicados
    const backdrops = document.querySelectorAll('.modal-backdrop');
    if (backdrops.length > 1) {
        console.warn('Múltiplos backdrops encontrados, removendo duplicados:', backdrops.length);
        // Manter apenas o último
        for (let i = 0; i < backdrops.length - 1; i++) {
            backdrops[i].remove();
        }
    }
    
    // Remover z-index inline para deixar Bootstrap gerenciar
    backdrops.forEach(backdrop => {
        backdrop.style.removeProperty('z-index');
    });
    
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.style.removeProperty('z-index');
        const dialog = modal.querySelector('.modal-dialog');
        if (dialog) dialog.style.removeProperty('z-index');
        const content = modal.querySelector('.modal-content');
        if (content) content.style.removeProperty('z-index');
    });
}

// Marcar conversa como lida
function markConversationAsRead(conversationId) {
    // Fechar o dropdown imediatamente
    closeAllDropdowns();
    
    fetch(`<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}/mark-read`, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remover badge de não lido
            const conversationItem = document.querySelector(`[data-conversation-id="${conversationId}"]`);
            if (conversationItem) {
                const badge = conversationItem.querySelector('.conversation-item-badge');
                if (badge) badge.remove();
            }
            
            // Atualizar contador global
            if (typeof updateUnreadCount === 'function') {
                updateUnreadCount();
            }
            
            // Toast de sucesso
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Conversa marcada como lida',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
                });
            }
        } else {
            alert('Erro ao marcar como lida: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao marcar conversa como lida');
    });
}

// Armazenar conversas marcadas como não lidas manualmente
window.manuallyMarkedAsUnread = window.manuallyMarkedAsUnread || new Set();

// Marcar conversa como não lida
function markConversationAsUnread(conversationId) {
    // Fechar o dropdown imediatamente
    closeAllDropdowns();
    
    console.log("Marcando conversa como não lida:", conversationId);
    
    fetch(`<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}/mark-unread`, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        console.log("Resposta mark-unread:", data);
        
        if (data.success) {
            // Adicionar à lista de conversas marcadas manualmente
            window.manuallyMarkedAsUnread.add(conversationId);
            console.log("Conversas marcadas como não lidas:", Array.from(window.manuallyMarkedAsUnread));
            
            // Forçar atualização do badge
            const conversationItem = document.querySelector(`[data-conversation-id="${conversationId}"]`);
            if (conversationItem) {
                const meta = conversationItem.querySelector('.conversation-item-meta');
                if (meta) {
                    // Remover badge existente
                    const existingBadge = meta.querySelector('.conversation-item-badge');
                    if (existingBadge) {
                        existingBadge.remove();
                    }
                    
                    // Adicionar novo badge
                    const badge = document.createElement('span');
                    badge.className = 'conversation-item-badge';
                    badge.textContent = data.unread_count || '1';
                    badge.setAttribute('data-manual-unread', 'true');
                    meta.appendChild(badge);
                    
                    console.log("Badge adicionado para conversa:", conversationId, "unread_count:", data.unread_count);
                }
            }
            
            // Atualizar contador global
            if (typeof updateUnreadCount === 'function') {
                updateUnreadCount();
            }
            
            // Toast de sucesso
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Conversa marcada como não lida',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
                });
            }
        } else {
            alert('Erro ao marcar como não lida: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao marcar conversa como não lida');
    });
}

// Mostrar modal de agendar mensagem
function showScheduleMessageModal() {
    // Usar variável JavaScript global que é atualizada dinamicamente
    const conversationId = currentConversationId || parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
    if (!conversationId) {
        alert('Selecione uma conversa primeiro');
        return;
    }
    
    const modal = document.getElementById('kt_modal_schedule_message');
    if (!modal) {
        console.error('Modal de agendar mensagem não encontrado');
        return;
    }
    
    // Limpar formulário
    const form = modal.querySelector('#scheduleMessageForm');
    if (form) form.reset();
    
    // Definir conversation_id
    const conversationIdInput = modal.querySelector('#schedule_conversation_id');
    if (conversationIdInput) {
        conversationIdInput.value = conversationId;
    }
    
    // Definir data/hora mínima (hoje, agora)
    const dateInput = modal.querySelector('#schedule_message_date');
    const timeInput = modal.querySelector('#schedule_message_time');
    if (dateInput) {
        const now = new Date();
        const dateStr = now.toISOString().split('T')[0];
        dateInput.value = dateStr;
        dateInput.min = dateStr;
    }
    if (timeInput) {
        const now = new Date();
        const timeStr = now.toTimeString().slice(0, 5);
        timeInput.value = timeStr;
    }
    
    // Abrir modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

// Mostrar modal de agendar lembrete
function showReminderModal(conversationId) {
    const modal = document.getElementById('kt_modal_reminder');
    if (!modal) {
        console.error('Modal de lembrete não encontrado');
        return;
    }
    
    // Limpar formulário
    const form = modal.querySelector('#reminderForm');
    if (form) form.reset();
    
    // Definir conversation_id
    const conversationIdInput = modal.querySelector('#reminder_conversation_id');
    if (conversationIdInput) {
        conversationIdInput.value = conversationId;
    }
    
    // Definir data/hora mínima (hoje, agora)
    const dateInput = modal.querySelector('#reminder_date');
    const timeInput = modal.querySelector('#reminder_time');
    if (dateInput) {
        const now = new Date();
        const dateStr = now.toISOString().split('T')[0];
        dateInput.value = dateStr;
        dateInput.min = dateStr;
    }
    if (timeInput) {
        const now = new Date();
        const timeStr = now.toTimeString().slice(0, 5);
        timeInput.value = timeStr;
    }
    
    // Abrir modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

// Buscar mensagens dentro da conversa
let messageSearchTimeout = null;
// Variáveis globais para navegação de busca
let messageSearchResults = [];
let currentSearchIndex = -1;
let currentSearchTerm = '';
let messageSearchFilters = {
    message_type: null,
    sender_type: null,
    sender_id: null,
    ai_agent_id: null,
    date_from: null,
    date_to: null,
    has_attachments: null
};

// Função para destacar texto encontrado
function highlightSearchTerm(text, searchTerm) {
    if (!searchTerm) return escapeHtml(text);
    
    const regex = new RegExp(`(${escapeRegex(searchTerm)})`, 'gi');
    return escapeHtml(text).replace(regex, '<mark class="bg-warning text-dark" style="padding: 2px 4px; border-radius: 3px;">$1</mark>');
}

// Função para escapar caracteres especiais em regex
function escapeRegex(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function searchMessagesInConversation(event) {
    const searchInput = event.target;
    const searchTerm = searchInput.value.trim();
    const resultsDiv = document.getElementById('messageSearchResults');
    
    // Obter ID da conversa da URL ou variável global
    let conversationId = currentConversationId;
    if (!conversationId) {
        const urlParams = new URLSearchParams(window.location.search);
        conversationId = urlParams.get('id') ? parseInt(urlParams.get('id')) : null;
    }
    
    // Limpar timeout anterior
    if (messageSearchTimeout) {
        clearTimeout(messageSearchTimeout);
    }
    
    // Verificar se há filtros ativos
    const hasActiveFilters = Object.values(messageSearchFilters).some(v => v !== null && v !== '');
    
    // Se campo vazio e não há filtros, esconder resultados
    if (!searchTerm && !hasActiveFilters) {
        resultsDiv.classList.add('d-none');
        messageSearchResults = [];
        currentSearchIndex = -1;
        currentSearchTerm = '';
        return;
    }
    
    // Aguardar 300ms antes de buscar (debounce)
    messageSearchTimeout = setTimeout(() => {
        if (!conversationId) {
            resultsDiv.innerHTML = '<div class="p-3 text-center text-gray-500"><i class="ki-duotone ki-information-5 fs-2x mb-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i><br>Nenhuma conversa selecionada</div>';
            resultsDiv.classList.remove('d-none');
            return;
        }
        
        // Construir URL com filtros
        const params = new URLSearchParams();
        if (searchTerm) {
            params.append('q', searchTerm);
        }
        
        // Adicionar filtros ativos
        Object.keys(messageSearchFilters).forEach(key => {
            const value = messageSearchFilters[key];
            if (value !== null && value !== '' && value !== false) {
                // Para booleanos, enviar apenas se true
                if (typeof value === 'boolean' && value === true) {
                    params.append(key, '1');
                } else {
                    params.append(key, value);
                }
            }
        });
        
        const url = `<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}/search-messages?${params.toString()}`;
        
        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.messages && data.messages.length > 0) {
                // Salvar resultados para navegação
                messageSearchResults = data.messages;
                currentSearchTerm = searchTerm;
                currentSearchIndex = -1;
                
                const total = data.messages.length;
                let html = `
                    <div class="p-2 border-bottom bg-light-primary d-flex justify-content-between align-items-center">
                        <small class="text-gray-700 fw-semibold">${total} mensagem(ns) encontrada(s)</small>
                        <div class="d-flex gap-2 align-items-center">
                            <button class="btn btn-sm btn-icon btn-light-primary" onclick="navigateSearchResults(-1)" title="Anterior (↑)" id="searchPrevBtn" style="padding: 2px 6px;">
                                <i class="ki-duotone ki-up fs-6">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </button>
                            <small class="text-gray-700 fw-semibold" id="searchCounter">-</small>
                            <button class="btn btn-sm btn-icon btn-light-primary" onclick="navigateSearchResults(1)" title="Próximo (↓)" id="searchNextBtn" style="padding: 2px 6px;">
                                <i class="ki-duotone ki-down fs-6">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </button>
                        </div>
                    </div>
                `;
                
                data.messages.forEach((msg, index) => {
                    const content = msg.content || '';
                    // Encontrar posição do termo no conteúdo para mostrar contexto relevante
                    const searchLower = searchTerm.toLowerCase();
                    const contentLower = content.toLowerCase();
                    const termIndex = contentLower.indexOf(searchLower);
                    
                    let preview = '';
                    if (termIndex >= 0) {
                        // Mostrar contexto ao redor do termo encontrado
                        const start = Math.max(0, termIndex - 30);
                        const end = Math.min(content.length, termIndex + searchTerm.length + 30);
                        preview = content.substring(start, end);
                        if (start > 0) preview = '...' + preview;
                        if (end < content.length) preview = preview + '...';
                    } else {
                        preview = content.length > 80 ? content.substring(0, 80) + '...' : content;
                    }
                    
                    const time = msg.created_at ? new Date(msg.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
                    const senderType = msg.sender_type || 'contact';
                    let senderBadge = '';
                    if (msg.ai_agent_id) {
                        senderBadge = '<span class="badge badge-light-warning badge-sm ms-2"><i class="ki-duotone ki-robot fs-7 me-1"><span class="path1"></span><span class="path2"></span></i>IA</span>';
                    } else if (senderType === 'agent') {
                        senderBadge = '<span class="badge badge-light-primary badge-sm ms-2">Agente</span>';
                    } else {
                        senderBadge = '<span class="badge badge-light-info badge-sm ms-2">Contato</span>';
                    }
                    html += `
                        <div class="message-search-result p-3 border-bottom cursor-pointer hover-bg-light-primary" 
                             data-message-id="${msg.id}" 
                             data-index="${index}"
                             onclick="selectSearchResult(${index}, true)">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <div class="d-flex align-items-center">
                                    <span class="fw-semibold text-gray-800">${escapeHtml(msg.sender_name || 'Remetente')}</span>
                                    ${senderBadge}
                                </div>
                                <span class="text-gray-500 small">${time}</span>
                            </div>
                            <div class="text-gray-600 small mt-1">${highlightSearchTerm(preview, searchTerm)}</div>
                        </div>
                    `;
                });
                resultsDiv.innerHTML = html;
                updateSearchCounter();
            } else {
                resultsDiv.innerHTML = '<div class="p-3 text-center text-gray-500"><i class="ki-duotone ki-magnifier fs-2x mb-2"><span class="path1"></span><span class="path2"></span></i><br>Nenhuma mensagem encontrada</div>';
                messageSearchResults = [];
                currentSearchIndex = -1;
            }
            resultsDiv.classList.remove('d-none');
        })
        .catch(error => {
            console.error('Erro ao buscar mensagens:', error);
            resultsDiv.innerHTML = '<div class="p-3 text-center text-danger"><i class="ki-duotone ki-information-5 fs-2x mb-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i><br>Erro ao buscar mensagens</div>';
            resultsDiv.classList.remove('d-none');
            messageSearchResults = [];
            currentSearchIndex = -1;
        });
    }, 300);
}

// Selecionar resultado da busca
function selectSearchResult(index, closeDropdown = false) {
    if (!messageSearchResults || index < 0 || index >= messageSearchResults.length) return;
    
    const msg = messageSearchResults[index];
    currentSearchIndex = index;
    
    // Scroll até a mensagem
    scrollToMessage(msg.id);
    
    // Atualizar contador e destacar item
    updateSearchCounter();
    highlightSearchResultItem(index);
    
    // Fechar dropdown apenas se solicitado (ex: clique direto)
    if (closeDropdown) {
        document.getElementById('messageSearchResults').classList.add('d-none');
        document.getElementById('messageSearch').blur();
    }
}

// Navegar entre resultados (próximo/anterior)
function navigateSearchResults(direction) {
    if (!messageSearchResults || messageSearchResults.length === 0) return;
    
    // Atualizar índice
    currentSearchIndex += direction;
    
    // Limites
    if (currentSearchIndex < 0) {
        currentSearchIndex = messageSearchResults.length - 1;
    } else if (currentSearchIndex >= messageSearchResults.length) {
        currentSearchIndex = 0;
    }
    
    // Selecionar resultado
    selectSearchResult(currentSearchIndex);
    
    // Atualizar contador
    updateSearchCounter();
    
    // Destacar item no dropdown
    highlightSearchResultItem(currentSearchIndex);
}

// Atualizar contador de busca
function updateSearchCounter() {
    const counter = document.getElementById('searchCounter');
    if (counter && messageSearchResults.length > 0) {
        const current = currentSearchIndex >= 0 ? currentSearchIndex + 1 : '-';
        counter.textContent = `${current} / ${messageSearchResults.length}`;
    }
}

// Destacar item selecionado no dropdown
function highlightSearchResultItem(index) {
    const resultsDiv = document.getElementById('messageSearchResults');
    if (!resultsDiv) return;
    
    // Remover destaque anterior
    resultsDiv.querySelectorAll('.message-search-result').forEach(item => {
        item.classList.remove('bg-light-primary');
    });
    
    // Destacar item atual
    const currentItem = resultsDiv.querySelector(`[data-index="${index}"]`);
    if (currentItem) {
        currentItem.classList.add('bg-light-primary');
        // Scroll do item para dentro da view
        currentItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

// Adicionar navegação por teclado
document.addEventListener('DOMContentLoaded', function() {
    const messageSearchInput = document.getElementById('messageSearch');
    if (messageSearchInput) {
        messageSearchInput.addEventListener('keydown', function(e) {
            const resultsDiv = document.getElementById('messageSearchResults');
            if (!resultsDiv || resultsDiv.classList.contains('d-none')) return;
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                navigateSearchResults(1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                navigateSearchResults(-1);
            } else if (e.key === 'Enter' && currentSearchIndex >= 0) {
                e.preventDefault();
                selectSearchResult(currentSearchIndex, true);
            } else if (e.key === 'Escape') {
                resultsDiv.classList.add('d-none');
                messageSearchInput.blur();
            }
        });
    }
    
    // Inicializar lazy loading
    initLazyLoading();
});

// Inicializar lazy loading de imagens e vídeos
function initLazyLoading() {
    // Verificar se Intersection Observer está disponível
    if (!('IntersectionObserver' in window)) {
        // Fallback: carregar todas as imagens/vídeos imediatamente
        document.querySelectorAll('.lazy-image[data-src]').forEach(img => {
            img.src = img.dataset.src;
            img.classList.add('loaded');
        });
        document.querySelectorAll('.lazy-video-container').forEach(container => {
            loadVideo(container);
        });
        return;
    }
    
    // Configurar Intersection Observer para imagens
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                const container = img.closest('.lazy-image-container');
                const spinner = container ? container.querySelector('.lazy-loading-spinner') : null;
                
                if (spinner) spinner.style.display = 'block';
                img.classList.add('loading');
                
                const imageUrl = img.dataset.src;
                const tempImg = new Image();
                
                tempImg.onload = function() {
                    img.src = imageUrl;
                    img.classList.remove('loading');
                    img.classList.add('loaded');
                    if (spinner) spinner.style.display = 'none';
                    observer.unobserve(img);
                };
                
                tempImg.onerror = function() {
                    img.classList.remove('loading');
                    if (spinner) spinner.style.display = 'none';
                    img.style.opacity = '0.5';
                    observer.unobserve(img);
                };
                
                tempImg.src = imageUrl;
            }
        });
    }, {
        rootMargin: '50px' // Começar a carregar 50px antes de ficar visível
    });
    
    // Observar todas as imagens lazy
    document.querySelectorAll('.lazy-image[data-src]').forEach(img => {
        imageObserver.observe(img);
    });
    
    // Configurar Intersection Observer para vídeos
    const videoObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const container = entry.target;
                loadVideo(container);
                observer.unobserve(container);
            }
        });
    }, {
        rootMargin: '100px' // Vídeos começam a carregar mais cedo
    });
    
    // Observar todos os containers de vídeo lazy
    document.querySelectorAll('.lazy-video-container').forEach(container => {
        videoObserver.observe(container);
        
        // Adicionar clique no placeholder para carregar imediatamente
        const placeholder = container.querySelector('.lazy-video-placeholder');
        if (placeholder) {
            placeholder.addEventListener('click', function() {
                loadVideo(container);
            });
        }
    });
}

// Carregar vídeo quando ficar visível ou ao clicar
function loadVideo(container) {
    const video = container.querySelector('video');
    const placeholder = container.querySelector('.lazy-video-placeholder');
    const src = container.dataset.src;
    const type = container.dataset.type;
    
    if (!video || !src) return;
    
    // Se já foi carregado, não fazer nada
    if (video.classList.contains('loaded')) return;
    
    // Carregar vídeo
    const source = video.querySelector('source');
    if (source) {
        source.src = src;
        if (type) source.type = type;
    }
    
    video.load();
    video.classList.add('loaded');
    
    if (placeholder) {
        placeholder.classList.add('loaded');
    }
    
    // Quando vídeo estiver pronto, mostrar
    video.addEventListener('loadeddata', function() {
        video.style.display = 'block';
        if (placeholder) {
            placeholder.style.display = 'none';
        }
    }, { once: true });
}

// Função para observar novos elementos adicionados dinamicamente
function observeNewLazyElements(container) {
    if (!container) return;
    
    // Observar novas imagens
    const newImages = container.querySelectorAll('.lazy-image[data-src]:not(.lazy-observed)');
    if (newImages.length > 0 && 'IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    const container = img.closest('.lazy-image-container');
                    const spinner = container ? container.querySelector('.lazy-loading-spinner') : null;
                    
                    if (spinner) spinner.style.display = 'block';
                    img.classList.add('loading');
                    
                    const imageUrl = img.dataset.src;
                    const tempImg = new Image();
                    
                    tempImg.onload = function() {
                        img.src = imageUrl;
                        img.classList.remove('loading');
                        img.classList.add('loaded');
                        if (spinner) spinner.style.display = 'none';
                        observer.unobserve(img);
                    };
                    
                    tempImg.onerror = function() {
                        img.classList.remove('loading');
                        if (spinner) spinner.style.display = 'none';
                        img.style.opacity = '0.5';
                        observer.unobserve(img);
                    };
                    
                    tempImg.src = imageUrl;
                }
            });
        }, { rootMargin: '50px' });
        
        newImages.forEach(img => {
            img.classList.add('lazy-observed');
            imageObserver.observe(img);
        });
    }
    
    // Observar novos vídeos
    const newVideos = container.querySelectorAll('.lazy-video-container:not(.lazy-observed)');
    if (newVideos.length > 0 && 'IntersectionObserver' in window) {
        const videoObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const container = entry.target;
                    loadVideo(container);
                    observer.unobserve(container);
                }
            });
        }, { rootMargin: '100px' });
        
        newVideos.forEach(container => {
            container.classList.add('lazy-observed');
            videoObserver.observe(container);
            
            const placeholder = container.querySelector('.lazy-video-placeholder');
            if (placeholder) {
                placeholder.addEventListener('click', function() {
                    loadVideo(container);
                });
            }
        });
    }
}

// Fechar resultados ao clicar fora
document.addEventListener('click', function(event) {
    const searchInput = document.getElementById('messageSearch');
    const resultsDiv = document.getElementById('messageSearchResults');
    if (searchInput && resultsDiv && !searchInput.contains(event.target) && !resultsDiv.contains(event.target)) {
        resultsDiv.classList.add('d-none');
    }
});

// Mostrar modal de filtros de busca
function showMessageSearchFilters() {
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_message_search_filters'));
    modal.show();
    
    // Preencher filtros atuais
    document.getElementById('filterMessageType').value = messageSearchFilters.message_type || '';
    document.getElementById('filterSenderType').value = messageSearchFilters.sender_type || '';
    document.getElementById('filterDateFrom').value = messageSearchFilters.date_from || '';
    document.getElementById('filterDateTo').value = messageSearchFilters.date_to || '';
    document.getElementById('filterHasAttachments').checked = messageSearchFilters.has_attachments === true;
    document.getElementById('filterSenderId').value = messageSearchFilters.sender_id || '';
    
    // Mostrar/ocultar campo de agente específico baseado no tipo de remetente
    updateSenderIdFilterVisibility();
    
    // Adicionar listener para atualizar visibilidade quando mudar tipo de remetente
    const senderTypeSelect = document.getElementById('filterSenderType');
    if (senderTypeSelect) {
        senderTypeSelect.removeEventListener('change', updateSenderIdFilterVisibility);
        senderTypeSelect.addEventListener('change', updateSenderIdFilterVisibility);
    }
}

// Atualizar visibilidade do filtro de agente específico
function updateSenderIdFilterVisibility() {
    const senderType = document.getElementById('filterSenderType')?.value;
    const senderIdContainer = document.getElementById('filterSenderIdContainer');
    
    if (senderType === 'agent' && senderIdContainer) {
        senderIdContainer.style.display = 'block';
    } else {
        if (senderIdContainer) {
            senderIdContainer.style.display = 'none';
            document.getElementById('filterSenderId').value = '';
        }
    }
}

// Aplicar filtros de busca
function applyMessageSearchFilters() {
    // Obter valores dos filtros
    const senderType = document.getElementById('filterSenderType').value || null;
    const senderId = document.getElementById('filterSenderId').value || null;
    
    messageSearchFilters = {
        message_type: document.getElementById('filterMessageType').value || null,
        sender_type: senderType,
        sender_id: (senderType === 'agent' && senderId) ? parseInt(senderId) : null,
        date_from: document.getElementById('filterDateFrom').value || null,
        date_to: document.getElementById('filterDateTo').value || null,
        has_attachments: document.getElementById('filterHasAttachments').checked || null
    };
    
    // Se filtro de IA, ajustar sender_type
    if (senderType === 'ai') {
        messageSearchFilters.sender_type = 'agent';
        messageSearchFilters.ai_agent_id = true; // Flag especial para filtrar por IA
    }
    
    // Fechar modal
    bootstrap.Modal.getInstance(document.getElementById('kt_modal_message_search_filters')).hide();
    
    // Atualizar indicador visual de filtros ativos
    updateFiltersIndicator();
    
    // Se houver termo de busca, refazer busca com filtros
    const searchInput = document.getElementById('messageSearch');
    if (searchInput && searchInput.value.trim()) {
        searchMessagesInConversation({ target: searchInput });
    } else {
        // Se não houver termo, mostrar que filtros estão ativos
        const hasActiveFilters = Object.values(messageSearchFilters).some(v => v !== null && v !== '');
        if (hasActiveFilters) {
            // Mostrar mensagem informando que filtros estão ativos
            const resultsDiv = document.getElementById('messageSearchResults');
            if (resultsDiv) {
                resultsDiv.innerHTML = '<div class="p-3 text-center text-gray-500"><i class="ki-duotone ki-filter fs-2x mb-2"><span class="path1"></span><span class="path2"></span></i><br>Digite um termo de busca para aplicar os filtros</div>';
                resultsDiv.classList.remove('d-none');
            }
        }
    }
}

// Limpar filtros de busca
function clearMessageSearchFilters() {
    messageSearchFilters = {
        message_type: null,
        sender_type: null,
        sender_id: null,
        ai_agent_id: null,
        date_from: null,
        date_to: null,
        has_attachments: null
    };
    
    // Limpar formulário
    document.getElementById('filterMessageType').value = '';
    document.getElementById('filterSenderType').value = '';
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value = '';
    document.getElementById('filterHasAttachments').checked = false;
    document.getElementById('filterSenderId').value = '';
    
    // Ocultar campo de agente específico
    const senderIdContainer = document.getElementById('filterSenderIdContainer');
    if (senderIdContainer) {
        senderIdContainer.style.display = 'none';
    }
    
    // Atualizar indicador
    updateFiltersIndicator();
    
    // Refazer busca se houver termo
    const searchInput = document.getElementById('messageSearch');
    if (searchInput && searchInput.value.trim()) {
        searchMessagesInConversation({ target: searchInput });
    }
}

// Atualizar indicador visual de filtros ativos
function updateFiltersIndicator() {
    const filtersBtn = document.getElementById('messageSearchFiltersBtn');
    if (!filtersBtn) return;
    
    const hasActiveFilters = Object.values(messageSearchFilters).some(v => v !== null && v !== '');
    
    if (hasActiveFilters) {
        filtersBtn.classList.add('btn-primary');
        filtersBtn.classList.remove('btn-light-primary');
        filtersBtn.setAttribute('title', 'Filtros ativos - Clique para editar');
    } else {
        filtersBtn.classList.remove('btn-primary');
        filtersBtn.classList.add('btn-light-primary');
        filtersBtn.setAttribute('title', 'Filtros avançados');
    }
}

/**
 * Formatar data para exibição (HOJE, ONTEM, ou data formatada) - JavaScript
 */
function formatDateLabel(dateString) {
    if (!dateString) return '';
    
    const date = new Date(dateString);
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    
    const dateOnly = date.toISOString().split('T')[0];
    const todayOnly = today.toISOString().split('T')[0];
    const yesterdayOnly = yesterday.toISOString().split('T')[0];
    
    if (dateOnly === todayOnly) {
        return 'HOJE';
    } else if (dateOnly === yesterdayOnly) {
        return 'ONTEM';
    } else {
        // Formato: "DIA X" (ex: "15 de Janeiro de 2025")
        const months = [
            'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
            'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
        ];
        const day = date.getDate();
        const month = months[date.getMonth()];
        const year = date.getFullYear();
        
        return `${day} de ${month} de ${year}`;
    }
}

/**
 * Verificar se duas datas são de dias diferentes - JavaScript
 */
function isDifferentDay(date1, date2) {
    if (!date1 || !date2) return false;
    
    const d1 = new Date(date1);
    const d2 = new Date(date2);
    
    return d1.toISOString().split('T')[0] !== d2.toISOString().split('T')[0];
}

/**
 * Renderizar separador de data - JavaScript
 */
function renderDateSeparator(dateString) {
    const label = formatDateLabel(dateString);
    const separator = document.createElement('div');
    separator.className = 'date-separator';
    separator.setAttribute('data-date', dateString);
    separator.innerHTML = `
        <span class="date-separator-line"></span>
        <span class="date-separator-label">${escapeHtml(label)}</span>
        <span class="date-separator-line"></span>
    `;
    return separator;
}

// Adicionar mensagem ao chat dinamicamente
function addMessageToChat(message) {
    console.group('📨 addMessageToChat');
    console.log('Mensagem recebida:', message);
    console.table({
        'ID': message.id,
        'sender_type': message.sender_type,
        'direction': message.direction,
        'type': message.type,
        'message_type': message.message_type
    });
    
    const isIncoming = message.direction === 'incoming';
    console.log(`Será renderizada como: ${isIncoming ? '⬅️ INCOMING (esquerda)' : '➡️ OUTGOING (direita)'}`);
    console.groupEnd();
    
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) return null;

    // Evitar duplicação: se já existe mensagem com o mesmo ID, verificar se precisa reposicionar
    if (message.id) {
        const existing = chatMessages.querySelector(`[data-message-id="${message.id}"]`);
        if (existing) {
            // Verificar se precisa reposicionar baseado no timestamp
            const messageTimestamp = message.created_at ? new Date(message.created_at).getTime() : Date.now();
            const existingTimestamp = existing.getAttribute('data-timestamp');
            
            if (existingTimestamp) {
                const existingTime = parseInt(existingTimestamp);
                if (!isNaN(existingTime) && Math.abs(messageTimestamp - existingTime) > 1000) {
                    // Timestamp diferente, atualizar e reposicionar
                    existing.setAttribute('data-timestamp', messageTimestamp);
                    existing.remove(); // Remover do DOM para reposicionar
                } else {
                    // Mesma mensagem, não precisa reposicionar
                    return existing;
                }
            } else {
                // Mensagem existe mas sem timestamp, apenas retornar
                return existing;
            }
        }
    }
    
    const messageDiv = document.createElement('div');
    
    if (message.type === 'system') {
        messageDiv.className = 'chat-message system';
        messageDiv.innerHTML = `
            <div class="message-content">
                <div class="message-bubble">
                    <i class="ki-duotone ki-information fs-5 me-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    ${escapeHtml(message.content)}
                </div>
            </div>
        `;
    } else if (message.type === 'note') {
        // Notas internas ficam alinhadas à direita como mensagens enviadas
        messageDiv.className = 'chat-message note outgoing';
        const senderName = message.sender_name || 'Sistema';
        messageDiv.innerHTML = `
            <div class="message-content">
                <div class="message-bubble">
                    <div class="note-header">
                        <i class="ki-duotone ki-note fs-6">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Nota Interna • ${escapeHtml(senderName)}
                    </div>
                    ${nl2br(escapeHtml(message.content))}
                </div>
                <div class="message-time">${formatTime(message.created_at)}</div>
            </div>
        `;
    } else {
        const isIncoming = message.direction === 'incoming';
        messageDiv.className = `chat-message ${isIncoming ? 'incoming' : 'outgoing'}`;
        
        // Adicionar ID da mensagem como atributo
        if (message.id) {
            messageDiv.setAttribute('data-message-id', message.id);
            lastMessageId = Math.max(lastMessageId || 0, message.id);
        }
        
        // Verificar se é mensagem de IA
        const isAIMessage = message.ai_agent_id !== null && message.ai_agent_id !== undefined;
        const aiAgentName = message.ai_agent_name || 'Assistente IA';
        
        // Obter iniciais do agente de IA
        let aiAgentInitials = '';
        if (isAIMessage && aiAgentName) {
            const parts = aiAgentName.split(' ');
            aiAgentInitials = (parts[0].charAt(0) + (parts[1] ? parts[1].charAt(0) : '')).toUpperCase();
        }
        
        let avatarHtml = '';
        if (isIncoming) {
            if (currentContactAvatar) {
                avatarHtml = `<img src="${escapeHtml(currentContactAvatar)}" alt="Avatar" class="message-avatar" style="object-fit: cover;">`;
            } else {
                const initials = getInitials(message.sender_name || 'NN');
                avatarHtml = `<div class="message-avatar">${initials}</div>`;
            }
        } else if (isAIMessage) {
            // Avatar do agente de IA para mensagens de IA
            avatarHtml = `<div class="message-avatar ai-agent-avatar" title="${escapeHtml(aiAgentName)}">${aiAgentInitials || '🤖'}</div>`;
        }
        
        // Badge de IA se for mensagem de agente de IA
        let aiBadgeHtml = '';
        if (isAIMessage && !isIncoming) {
            aiBadgeHtml = `
                <div class="ai-message-badge" title="Mensagem enviada por ${escapeHtml(aiAgentName)}">
                    <div class="ai-avatar-mini">${aiAgentInitials || '🤖'}</div>
                    <i class="ki-duotone ki-robot fs-7">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                    </i>
                    <span class="ai-badge-text">${escapeHtml(aiAgentName)}</span>
                </div>
            `;
        }
        
        // Função helper para renderizar status
        function renderMessageStatusHtml(message) {
            if (!message || message.direction === 'incoming') {
                return '';
            }
            
            const status = message.status || 'sent';
            const readAt = message.read_at || null;
            const deliveredAt = message.delivered_at || null;
            const errorMessage = message.error_message || null;
            
            // Se houver erro
            if (status === 'failed' || errorMessage) {
                const errorText = escapeHtml(errorMessage || 'Erro ao enviar');
                return `<span class="message-status message-status-error" title="${errorText}">
                    <i class="ki-duotone ki-cross-circle fs-6">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <span class="message-status-label">Erro</span>
                </span>`;
            }
            
            // Se foi lida
            if (readAt) {
                const readDate = new Date(readAt).toLocaleString('pt-BR');
                return `<span class="message-status" title="Lida em ${readDate}">
                    <i class="ki-duotone ki-double-check fs-6" style="color: #0088cc;">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <span class="message-status-label">Lida</span>
                </span>`;
            }
            
            // Se foi entregue
            if (deliveredAt) {
                const deliveredDate = new Date(deliveredAt).toLocaleString('pt-BR');
                return `<span class="message-status" title="Entregue em ${deliveredDate}">
                    <i class="ki-duotone ki-double-check fs-6 text-white">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <span class="message-status-label">Entregue</span>
                </span>`;
            }
            
            // Enviado (padrão)
            return `<span class="message-status" title="Enviado">
                <i class="ki-duotone ki-check fs-6 text-white">
                    <span class="path1"></span>
                </i>
                <span class="message-status-label">Enviado</span>
            </span>`;
        }
        
        let statusHtml = renderMessageStatusHtml(message);
        
        // Renderizar anexos se houver
        let attachmentsHtml = '';
        if (message.attachments && Array.isArray(message.attachments) && message.attachments.length > 0) {
            message.attachments.forEach(att => {
                attachmentsHtml += renderAttachmentHtml(att);
            });
        }
        
        // Verificar se é mensagem citada/reply
        const hasQuote = message.quoted_message_id || (message.content && message.content.startsWith('↩️'));
        let quotedHtml = '';
        let actualContent = message.content || '';
        
        if (hasQuote) {
            let quotedText = '';
            let quotedSender = 'Remetente';
            let quotedMessageId = null;
            
            // Priorizar campos separados (novo formato)
            if (message.quoted_message_id) {
                quotedMessageId = message.quoted_message_id;
                quotedSender = message.quoted_sender_name || 'Remetente';
                quotedText = message.quoted_text || '';
                // Limitar texto citado
                if (quotedText.length > 100) {
                    quotedText = quotedText.substring(0, 100) + '...';
                }
                // Content não foi modificado no novo formato
                actualContent = message.content || '';
            } else {
                // Formato antigo (↩️ no content)
                const lines = actualContent.split('\n', 2);
                quotedText = lines[0].substring(2); // Remove "↩️ "
                quotedSender = message.quoted_sender_name || 'Remetente';
                actualContent = lines[1] || '';
                quotedMessageId = null;
            }
            
            // Criar onclick handler para quoted message
            const quotedOnclick = quotedMessageId 
                ? "scrollToMessage(" + quotedMessageId + ")" 
                : "console.log('Sem ID para scroll')";
            const quotedTitle = quotedMessageId ? 'Clique para ver a mensagem original' : 'Mensagem original não disponível';
            const quotedDataId = quotedMessageId || '';
            
            quotedHtml = `
                <div class="quoted-message" onclick="${quotedOnclick}" title="${quotedTitle}" data-quoted-id="${quotedDataId}">
                    <div class="quoted-message-header">${escapeHtml(quotedSender)}</div>
                    <div class="quoted-message-content">${escapeHtml(quotedText.length > 60 ? quotedText.substring(0, 60) + '...' : quotedText)}</div>
                </div>
            `;
        }
        
        // Adicionar botões de ação
        const msgId = message.id || 0;
        const senderName = (message.sender_name || 'Remetente').replace(/'/g, "\\'");
        const msgContent = ((message.content || '').substring(0, 100)).replace(/'/g, "\\'");
        
        const replyBtn = `
            <div class="message-actions">
                <button class="message-actions-btn" onclick="replyToMessage(${msgId}, '${senderName}', '${msgContent}')" title="Responder">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 10 4 15 9 20"></polyline>
                        <path d="M20 4v7a4 4 0 0 1-4 4H4"></path>
                    </svg>
                </button>
                <button class="message-actions-btn" onclick="forwardMessage(${msgId})" title="Encaminhar">
                    <i class="ki-duotone ki-arrow-right fs-6">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </button>
            </div>
        `;
        
        // Verificar se é apenas áudio (sem texto e sem outros anexos)
        const isAudioOnly = attachmentsHtml && attachmentsHtml.includes('audio-attachment') && !actualContent && !quotedHtml;
        const bubbleClass = isAudioOnly ? 'message-bubble audio-only' : 'message-bubble';
        
        messageDiv.innerHTML = `
            ${avatarHtml}
            <div class="message-content">
                ${replyBtn}
                ${aiBadgeHtml}
                <div class="${bubbleClass} ${isAIMessage ? 'ai-message' : ''}">
                    ${quotedHtml}
                    ${attachmentsHtml}
                    ${actualContent ? '<div class="' + ((attachmentsHtml || quotedHtml) ? 'mt-2' : '') + '">' + nl2br(escapeHtml(actualContent)) + '</div>' : ''}
                </div>
                <div class="message-time">
                    ${formatTime(message.created_at)}${statusHtml}
                </div>
            </div>
        `;
    }
    
    // Armazenar timestamp no elemento para ordenação
    const messageTimestamp = message.created_at ? new Date(message.created_at).getTime() : Date.now();
    messageDiv.setAttribute('data-timestamp', messageTimestamp);
    
    // Verificar se precisa adicionar separador de data antes da mensagem
    const allMessages = Array.from(chatMessages.children);
    let needsDateSeparator = false;
    let dateSeparatorPosition = null;
    
    // Encontrar posição correta para inserir (ordem crescente por timestamp)
    let insertPosition = null;
    let previousMessageDate = null;
    
    for (let i = 0; i < allMessages.length; i++) {
        const existingMsg = allMessages[i];
        
        // Verificar se é separador de data
        if (existingMsg.classList.contains('date-separator')) {
            const separatorDate = existingMsg.getAttribute('data-date');
            if (separatorDate && message.created_at) {
                // Verificar se a mensagem pertence a um dia diferente do separador
                if (isDifferentDay(separatorDate, message.created_at)) {
                    const separatorTime = new Date(separatorDate).getTime();
                    const messageTime = new Date(message.created_at).getTime();
                    if (messageTime < separatorTime) {
                        // Mensagem é anterior ao separador, inserir antes dele
                        insertPosition = existingMsg;
                        dateSeparatorPosition = existingMsg;
                        break;
                    }
                }
            }
            continue;
        }
        
        const existingTimestamp = existingMsg.getAttribute('data-timestamp');
        
        if (existingTimestamp) {
            const existingTime = parseInt(existingTimestamp);
            if (!isNaN(existingTime)) {
                // Comparar timestamps: se nova mensagem é mais antiga ou igual, inserir antes
                if (messageTimestamp <= existingTime) {
                    insertPosition = existingMsg;
                    
                    // Verificar se precisa de separador de data
                    const existingDate = existingMsg.getAttribute('data-date') || 
                                       (existingMsg.querySelector('.message-time') ? 
                                        new Date(existingTime).toISOString() : null);
                    
                    if (existingDate && message.created_at && isDifferentDay(existingDate, message.created_at)) {
                        needsDateSeparator = true;
                        dateSeparatorPosition = existingMsg;
                    }
                    break;
                } else {
                    // Guardar data da mensagem anterior para verificar separador
                    const existingDate = existingMsg.getAttribute('data-date') || 
                                       (existingMsg.querySelector('.message-time') ? 
                                        new Date(existingTime).toISOString() : null);
                    if (existingDate) {
                        previousMessageDate = existingDate;
                    }
                }
            }
        } else {
            // Se mensagem existente não tem timestamp, tentar pelo ID (fallback)
            const existingId = existingMsg.getAttribute('data-message-id');
            const newId = message.id;
            if (existingId && newId && !existingId.startsWith('temp_') && !newId.toString().startsWith('temp_')) {
                const existingIdNum = parseInt(existingId);
                const newIdNum = parseInt(newId);
                if (!isNaN(existingIdNum) && !isNaN(newIdNum) && newIdNum <= existingIdNum) {
                    insertPosition = existingMsg;
                    break;
                }
            }
        }
    }
    
    // Verificar se precisa de separador antes da primeira mensagem ou entre mensagens
    if (!needsDateSeparator && message.created_at) {
        if (insertPosition) {
            // Verificar se a mensagem anterior é de um dia diferente
            const prevElement = insertPosition.previousElementSibling;
            if (prevElement && !prevElement.classList.contains('date-separator')) {
                const prevDate = prevElement.getAttribute('data-date') || 
                               (prevElement.querySelector('.message-time') ? 
                                new Date(parseInt(prevElement.getAttribute('data-timestamp') || 0)).toISOString() : null);
                if (prevDate && isDifferentDay(prevDate, message.created_at)) {
                    needsDateSeparator = true;
                    dateSeparatorPosition = insertPosition;
                }
            } else if (!prevElement) {
                // Não há elemento anterior, primeira mensagem
                needsDateSeparator = true;
                dateSeparatorPosition = insertPosition;
            }
        } else if (allMessages.length === 0) {
            // Primeira mensagem - sempre adicionar separador
            needsDateSeparator = true;
            dateSeparatorPosition = null;
        } else {
            // Última mensagem - verificar se é de dia diferente da anterior
            const lastMsg = allMessages[allMessages.length - 1];
            if (lastMsg && !lastMsg.classList.contains('date-separator')) {
                const lastDate = lastMsg.getAttribute('data-date') || 
                               (lastMsg.querySelector('.message-time') ? 
                                new Date(parseInt(lastMsg.getAttribute('data-timestamp') || 0)).toISOString() : null);
                if (lastDate && isDifferentDay(lastDate, message.created_at)) {
                    needsDateSeparator = true;
                    dateSeparatorPosition = null; // Inserir antes da mensagem
                }
            }
        }
    }
    
    // Adicionar separador de data se necessário
    if (needsDateSeparator && message.created_at) {
        const dateSeparator = renderDateSeparator(message.created_at);
        if (dateSeparatorPosition) {
            chatMessages.insertBefore(dateSeparator, dateSeparatorPosition);
        } else if (insertPosition) {
            chatMessages.insertBefore(dateSeparator, insertPosition);
        } else {
            chatMessages.appendChild(dateSeparator);
        }
    }
    
    // Inserir mensagem na posição correta ou no final
    if (insertPosition) {
        chatMessages.insertBefore(messageDiv, insertPosition);
    } else {
        chatMessages.appendChild(messageDiv);
    }
    
    // Adicionar atributo data-date para facilitar comparações futuras
    if (message.created_at) {
        messageDiv.setAttribute('data-date', message.created_at);
    }
    
    // Atualizar último ID de mensagem conhecido
    if (message.id) {
        lastMessageId = Math.max(lastMessageId || 0, message.id);
    }
    
    // Scroll para última mensagem apenas se estiver no final do chat
    const isAtBottom = chatMessages.scrollHeight - chatMessages.scrollTop <= chatMessages.clientHeight + 100;
    if (isAtBottom && chatMessages && chatMessages.scrollHeight !== undefined) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Observar novos elementos lazy (imagens e vídeos) na mensagem recém-adicionada
    observeNewLazyElements(messageDiv);
    
    return messageDiv;
}

function formatTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) {
        return 'Agora';
    } else if (diff < 3600000) {
        return Math.floor(diff / 60000) + 'min';
    } else {
        return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }
}

function getInitials(name) {
    const parts = name.split(' ');
    return parts.length > 1 
        ? (parts[0][0] + parts[1][0]).toUpperCase()
        : name.substring(0, 2).toUpperCase();
}

function nl2br(text) {
    return text.replace(/\n/g, '<br>');
}

// Variável global para armazenar mensagem sendo respondida
let replyingToMessage = null;

// Função para responder uma mensagem
function replyToMessage(messageId, senderName, messageText) {
    replyingToMessage = {
        id: messageId,
        sender_name: senderName,
        text: messageText
    };
    
    // Mostrar preview
    const preview = document.getElementById('replyPreview');
    const header = document.getElementById('replyPreviewHeader');
    const text = document.getElementById('replyPreviewText');
    
    if (preview && header && text) {
        header.textContent = senderName;
        text.textContent = messageText.length > 60 ? messageText.substring(0, 60) + '...' : messageText;
        preview.classList.remove('d-none');
    }
    
    // Focar no input
    const input = document.getElementById('messageInput');
    if (input) {
        input.focus();
    }
}

// Cancelar reply
function cancelReply() {
    replyingToMessage = null;
    const preview = document.getElementById('replyPreview');
    if (preview) {
        preview.classList.add('d-none');
    }
}

// Encaminhar mensagem
async function forwardMessage(messageId) {
    if (!messageId) return;
    
    // Usar variável JavaScript global que é atualizada dinamicamente
    const conversationId = currentConversationId || parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
    if (!conversationId) {
        alert('Selecione uma conversa primeiro');
        return;
    }
    
    // Buscar lista de conversas para encaminhamento
    try {
        const response = await fetch(`<?= \App\Helpers\Url::to("/conversations/for-forwarding") ?>?exclude=${conversationId}`);
        
        // Verificar se a resposta é JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Resposta não é JSON:', text.substring(0, 200));
            throw new Error('Resposta inválida do servidor. Verifique o console para mais detalhes.');
        }
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Erro ao carregar conversas');
        }
        
        if (!data.conversations) {
            data.conversations = [];
        }
        
        // Criar HTML do modal
        let conversationsHtml = '';
        if (data.conversations.length === 0) {
            conversationsHtml = '<div class="text-center text-muted p-4">Nenhuma conversa disponível para encaminhamento</div>';
        } else {
            conversationsHtml = '<div class="forward-conversations-list" style="max-height: 400px; overflow-y: auto;">';
            data.conversations.forEach(conv => {
                const channelIcon = conv.channel === 'whatsapp' 
                    ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="#25D366" style="vertical-align: middle;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>' 
                    : conv.channel === 'email' ? '✉️' : '💬';
                const lastMsg = conv.last_message ? (conv.last_message.length > 50 ? conv.last_message.substring(0, 50) + '...' : conv.last_message) : 'Sem mensagens';
                conversationsHtml += `
                    <div class="forward-conversation-item" onclick="selectForwardConversation(${conv.id}, ${messageId})" style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.1); cursor: pointer; transition: background 0.2s;">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <div class="fw-bold">${escapeHtml(conv.contact_name)}</div>
                                <div class="text-muted small" style="display: flex; align-items: center; gap: 4px;">${channelIcon} <span>${escapeHtml(lastMsg)}</span></div>
                            </div>
                            <i class="ki-duotone ki-arrow-right fs-4 text-muted">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>
                `;
            });
            conversationsHtml += '</div>';
        }
        
        // Mostrar modal
        Swal.fire({
            title: 'Encaminhar Mensagem',
            html: `
                <div class="text-start">
                    <p class="mb-3">Selecione a conversa para onde deseja encaminhar:</p>
                    ${conversationsHtml}
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Cancelar',
            cancelButtonText: 'Fechar',
            confirmButtonColor: '#6c757d',
            width: '500px',
            customClass: {
                popup: 'forward-modal'
            },
            didOpen: () => {
                // Adicionar hover effect
                document.querySelectorAll('.forward-conversation-item').forEach(item => {
                    item.addEventListener('mouseenter', function() {
                        this.style.background = 'rgba(255,255,255,0.05)';
                    });
                    item.addEventListener('mouseleave', function() {
                        this.style.background = '';
                    });
                });
            }
        });
        
    } catch (error) {
        console.error('Erro ao carregar conversas:', error);
        alert('Erro ao carregar lista de conversas: ' + error.message);
    }
}

// Selecionar conversa para encaminhamento
async function selectForwardConversation(targetConversationId, messageId) {
const conversationId = parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
    
    if (!conversationId || !targetConversationId || !messageId) {
        alert('Dados inválidos');
        return;
    }
    
    // Fechar modal
    Swal.close();
    
    // Detectar tema dark
    const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                      document.body.classList.contains('dark-mode') ||
                      window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    // Mostrar loading
    Swal.fire({
        title: 'Encaminhando...',
        text: 'Aguarde enquanto encaminhamos a mensagem',
        allowOutsideClick: false,
        colorScheme: isDarkMode ? 'dark' : 'light',
        customClass: {
            popup: isDarkMode ? 'swal2-dark' : '',
            title: isDarkMode ? 'text-white' : '',
            htmlContainer: isDarkMode ? 'text-white' : ''
        },
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        const response = await fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${conversationId}/forward`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                message_id: messageId,
                target_conversation_id: targetConversationId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Mensagem Encaminhada',
                text: 'A mensagem foi encaminhada com sucesso!',
                timer: 2000,
                showConfirmButton: false,
                colorScheme: isDarkMode ? 'dark' : 'light',
                customClass: {
                    popup: isDarkMode ? 'swal2-dark' : '',
                    title: isDarkMode ? 'text-white' : '',
                    htmlContainer: isDarkMode ? 'text-white' : ''
                }
            });
        } else {
            throw new Error(data.message || 'Erro ao encaminhar mensagem');
        }
    } catch (error) {
        console.error('Erro ao encaminhar:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao encaminhar mensagem: ' + error.message,
            colorScheme: isDarkMode ? 'dark' : 'light',
            customClass: {
                popup: isDarkMode ? 'swal2-dark' : '',
                title: isDarkMode ? 'text-white' : '',
                htmlContainer: isDarkMode ? 'text-white' : ''
            }
        });
    }
}

// Scroll até mensagem específica
function scrollToMessage(messageId) {
    console.log('🔍 scrollToMessage chamado com messageId:', messageId, 'tipo:', typeof messageId);
    
    if (!messageId || messageId === 'null' || messageId === null || messageId === '') {
        console.warn('⚠️ scrollToMessage: ID de mensagem inválido:', messageId);
        return;
    }
    
    // Converter para número se for string
    const numericId = parseInt(messageId);
    if (isNaN(numericId)) {
        console.error('❌ scrollToMessage: ID não é um número válido:', messageId);
        return;
    }
    
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) {
        console.error('❌ scrollToMessage: Container de mensagens não encontrado');
        return;
    }
    
    console.log('🔍 scrollToMessage: Procurando mensagem com ID:', numericId);
    
    // Tentar encontrar a mensagem
    const messageElement = chatMessages.querySelector(`[data-message-id="${numericId}"]`);
    
    console.log('🔍 scrollToMessage: Elemento encontrado:', messageElement);
    
    if (messageElement) {
        console.log('✅ scrollToMessage: Mensagem encontrada, fazendo scroll...');
        
        // Remover highlight anterior se houver
        chatMessages.querySelectorAll('.message-highlight').forEach(el => {
            el.classList.remove('message-highlight');
        });
        
        // Adicionar classe de highlight
        messageElement.classList.add('message-highlight');
        
        // Calcular posição relativa ao container do chat
        const elementTop = messageElement.offsetTop;
        const elementHeight = messageElement.offsetHeight;
        const containerHeight = chatMessages.clientHeight;
        
        // Scroll suave até a mensagem (centralizada no container)
        const targetScroll = elementTop - (containerHeight / 2) + (elementHeight / 2);
        
        chatMessages.scrollTo({
            top: Math.max(0, targetScroll),
            behavior: 'smooth'
        });
        
        // Remover highlight após 3 segundos
            setTimeout(() => {
            messageElement.classList.remove('message-highlight');
            }, 3000);
        
        console.log('✅ scrollToMessage: Scroll executado com sucesso');
        
        // Remover destaque após 3 segundos
        setTimeout(() => {
            messageElement.style.backgroundColor = '';
            messageElement.style.border = '';
            messageElement.style.borderRadius = '';
        }, 3000);
    } else {
        // Mensagem não encontrada - pode estar em outra página ou não carregada
        console.warn('⚠️ scrollToMessage: Mensagem não encontrada com ID:', numericId);
        const allMessages = chatMessages.querySelectorAll('[data-message-id]');
        console.log('📊 scrollToMessage: Total de mensagens no DOM:', allMessages.length);
        console.log('📊 scrollToMessage: IDs disponíveis:', Array.from(allMessages).map(el => el.getAttribute('data-message-id')));
        
        // Tentar carregar mais mensagens ou mostrar aviso
        if (typeof Swal !== 'undefined' && Swal.fire) {
            Swal.fire({
                icon: 'info',
                title: 'Mensagem não encontrada',
                text: 'A mensagem pode estar em outra página do histórico. Tente rolar para cima para encontrá-la.',
                timer: 3000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        } else {
            // Fallback se SweetAlert não estiver disponível
            alert('Mensagem não encontrada. A mensagem pode estar em outra página do histórico.');
        }
    }
}

// Variáveis para gravação de áudio
let mediaRecorder = null;
let audioChunks = [];
let isRecording = false;
let recordingCanceled = false;
let currentStream = null;

// Gravar áudio
async function toggleAudioRecording() {
    const btn = document.getElementById('recordAudioBtn');
    // Usar variável JavaScript global que é atualizada dinamicamente
    const conversationId = currentConversationId || parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
    
    if (!conversationId) {
        alert('Selecione uma conversa primeiro');
        return;
    }
    
    if (!isRecording) {
        // Iniciar gravação
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            currentStream = stream;

            // Tentar preferir OGG/Opus; se não suportar, cair para WebM/Opus
            let mimeType = '';
            const preferred = 'audio/ogg;codecs=opus';
            const fallback = 'audio/webm;codecs=opus';
            if (MediaRecorder.isTypeSupported(preferred)) {
                mimeType = preferred;
            } else if (MediaRecorder.isTypeSupported(fallback)) {
                mimeType = fallback;
            }

            mediaRecorder = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream);
            audioChunks = [];
            
            mediaRecorder.ondataavailable = (event) => {
                audioChunks.push(event.data);
            };
            
            mediaRecorder.onstop = async () => {
                // Se cancelado, não enviar
                if (recordingCanceled) {
                    recordingCanceled = false;
                    audioChunks = [];
                } else {
                    const recordedType = mediaRecorder.mimeType || 'audio/webm';
                    const audioBlob = new Blob(audioChunks, { type: recordedType });
                    await sendAudioMessage(audioBlob, conversationId);
                }
                
                // Parar stream
                if (currentStream) {
                    currentStream.getTracks().forEach(track => track.stop());
                    currentStream = null;
                }
            };
            
            mediaRecorder.start();
            isRecording = true;
            
            // Atualizar botão
            btn.classList.remove('btn-light-primary', 'btn-danger');
            btn.classList.add('btn-success');
            btn.title = 'Parar e enviar áudio';
            btn.innerHTML = `
                <i class="ki-duotone ki-send fs-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
            `;
            
            // Mostrar indicador de gravação
            showRecordingIndicator();
            showCancelRecordingButton();
            
        } catch (error) {
            console.error('Erro ao acessar microfone:', error);
            alert('Erro ao acessar o microfone. Verifique as permissões.');
        }
    } else {
        stopRecordingAndSend();
    }
}

function stopRecordingAndSend() {
    const btn = document.getElementById('recordAudioBtn');
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    }
    isRecording = false;
    btn.classList.remove('btn-success', 'btn-danger');
    btn.classList.add('btn-light-primary');
    btn.title = 'Gravar áudio';
    btn.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
            <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
            <line x1="12" y1="19" x2="12" y2="23"></line>
            <line x1="8" y1="23" x2="16" y2="23"></line>
        </svg>
    `;
    hideRecordingIndicator();
    hideCancelRecordingButton();
}

function cancelRecording() {
    if (!isRecording) return;
    recordingCanceled = true;
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    }
    isRecording = false;
    const btn = document.getElementById('recordAudioBtn');
    btn.classList.remove('btn-success', 'btn-danger');
    btn.classList.add('btn-light-primary');
    btn.title = 'Gravar áudio';
    btn.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
            <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
            <line x1="12" y1="19" x2="12" y2="23"></line>
            <line x1="8" y1="23" x2="16" y2="23"></line>
        </svg>
    `;
    if (currentStream) {
        currentStream.getTracks().forEach(track => track.stop());
        currentStream = null;
    }
    hideRecordingIndicator();
    hideCancelRecordingButton();
}

function showCancelRecordingButton() {
    const cancelBtn = document.getElementById('cancelRecordingBtn');
    if (cancelBtn) {
        cancelBtn.classList.remove('d-none');
    }
}

function hideCancelRecordingButton() {
    const cancelBtn = document.getElementById('cancelRecordingBtn');
    if (cancelBtn) {
        cancelBtn.classList.add('d-none');
    }
}

// Mostrar indicador de gravação
function showRecordingIndicator() {
    const input = document.getElementById('messageInput');
    if (input) {
        input.placeholder = '🎤 Gravando... Clique no microfone para parar';
        input.disabled = true;
    }
}

// Esconder indicador de gravação
function hideRecordingIndicator() {
    const input = document.getElementById('messageInput');
    if (input) {
        input.placeholder = 'Digite sua mensagem...';
        input.disabled = false;
    }
}

// Enviar mensagem de áudio
async function sendAudioMessage(audioBlob, conversationId) {
    try {
        // Converter para formato compatível (webm para ogg ou mp3 seria ideal, mas vamos usar webm)
        const formData = new FormData();
        formData.append('attachments[]', audioBlob, 'audio-' + Date.now() + '.webm');
        formData.append('content', '');
        
        const response = await fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${conversationId}/messages`, {
            method: 'POST',
            body: formData
        });
        
        // Garantir que temos JSON; se não, capturar texto para debug
        // IMPORTANTE: Ler o texto primeiro e depois tentar fazer parse JSON para evitar "body stream already read"
        const responseText = await response.text();
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (jsonErr) {
            throw new Error(`Resposta não é JSON. HTTP ${response.status}. Corpo: ${responseText.substring(0, 500)}`);
        }
        
        if (data.success && data.message) {
            addMessageToChat(data.message);
        } else {
            throw new Error(data.message || 'Erro ao enviar áudio');
        }
    } catch (error) {
        console.error('Erro ao enviar áudio:', error);
        alert('Erro ao enviar áudio: ' + error.message);
    }
}

// Enviar mensagem
function sendMessage() {
    const input = document.getElementById('messageInput');
    const isNote = document.getElementById('noteToggle').checked;
    let message = input.value.trim();
    
    // Não permitir enviar mensagem vazia (mesmo com reply deve ter algum texto)
    if (!message) {
        return;
    }
    
    // Obter conversationId da variável global atualizada ou do PHP
    let conversationId = window.currentConversationId || parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
    // Fallback: tentar pegar da conversa ativa no DOM
    if (!conversationId) {
        const activeItem = document.querySelector('.conversation-item.active');
        if (activeItem) {
            conversationId = parseInt(activeItem.getAttribute('data-conversation-id'));
            window.currentConversationId = conversationId;
        }
    }
    if (!conversationId) {
        console.error('Nenhuma conversa selecionada');
        alert('Por favor, selecione uma conversa primeiro');
        return;
    }

    // Capturar contexto de reply antes de limpar estado
    const replyContext = replyingToMessage ? {
        id: replyingToMessage.id,
        sender: replyingToMessage.sender,
        text: replyingToMessage.text
    } : null;
    
    // Preparar mensagem com reply se houver
    // IMPORTANTE: Enviar apenas o texto digitado pelo usuário
    // O backend processa o quoted_message_id separadamente
    let finalMessage = message; // Texto que será enviado ao backend (apenas o digitado)
    
    // Adicionar nome do agente em negrito se toggle estiver ativo
    const agentNameToggle = document.getElementById('agentNameToggle');
    if (agentNameToggle && agentNameToggle.classList.contains('active')) {
        const agentName = '<?= htmlspecialchars(\App\Helpers\Auth::user()["name"] ?? "Agente", ENT_QUOTES) ?>';
        finalMessage = `*${agentName}*\n\n${message}`;
    }
    
    // Para preview otimista, formatar com reply se houver
    let previewMessage = message;
    if (replyContext) {
        previewMessage = `↩️ ${replyContext.text}\n\n${message}`;
    }
    
    // Mostrar loading
    const btn = event.target.closest('button') || document.querySelector('button[onclick="sendMessage()"]');
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
    
    // Adicionar mensagem otimisticamente (antes da resposta do servidor)
    const tempId = 'temp_' + Date.now();
    const tempMessage = {
        id: tempId,
        content: previewMessage, // Usar preview formatado para exibição
        direction: 'outgoing',
        type: isNote ? 'note' : 'message',
        created_at: new Date().toISOString(),
        sender_name: 'Você',
        quoted_message_id: replyContext ? replyContext.id : null,
        quoted_sender_name: replyContext ? replyContext.sender : null,
        quoted_text: replyContext ? replyContext.text : null
    };
    const messageDiv = addMessageToChat(tempMessage);
    if (messageDiv) {
        messageDiv.setAttribute('data-temp-id', tempId);
    }
    
    // Limpar input e reply
    input.value = '';
    input.style.height = 'auto';
    document.getElementById('noteToggle').checked = false;
    cancelReply();
    
    fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${conversationId}/messages`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            message: finalMessage, // Enviar apenas o texto digitado (sem formatação de reply)
            is_note: isNote,
            quoted_message_id: replyContext ? replyContext.id : null
        })
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                try {
                    const json = JSON.parse(text);
                    throw new Error(json.message || 'Erro ao enviar mensagem');
                } catch (e) {
                    if (e instanceof Error && e.message !== 'Erro ao enviar mensagem') {
                        throw e;
                    }
                    throw new Error(`Erro ${response.status}: ${text.substring(0, 100)}`);
                }
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Remover mensagem temporária e adicionar a real
            const tempMsg = document.querySelector(`[data-temp-id="${tempMessage.id}"]`);
            if (tempMsg) tempMsg.remove();
            
            if (data.message) {
                addMessageToChat(data.message);
            }
            
            // Atualizar lista de conversas
            updateConversationInList(conversationId, message);
        } else {
            // Remover mensagem temporária em caso de erro
            const tempMsg = document.querySelector(`[data-temp-id="${tempMessage.id}"]`);
            if (tempMsg) tempMsg.remove();
            
            alert('Erro ao enviar mensagem: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        // Remover mensagem temporária em caso de erro
        const tempMsg = document.querySelector(`[data-temp-id="${tempMessage.id}"]`);
        if (tempMsg) tempMsg.remove();
        
        alert('Erro ao enviar mensagem');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}

function updateConversationInList(conversationId, lastMessage) {
    const conversationItem = document.querySelector(`[data-conversation-id="${conversationId}"]`);
    if (conversationItem) {
        const preview = conversationItem.querySelector('.conversation-item-preview');
        const time = conversationItem.querySelector('.conversation-item-time');
        if (preview) {
            const maxChars = 37;
            preview.textContent = lastMessage.substring(0, maxChars) + (lastMessage.length > maxChars ? '...' : '');
        }
        if (time) {
            // Usar formatTime para tempo relativo (Agora, 1min, etc)
            const timestamp = new Date().toISOString();
            time.textContent = formatTime(timestamp);
            // Atualizar data-updated-at
            conversationItem.setAttribute('data-updated-at', timestamp);
            // Atualizar dataset para SLA (mensagem do agente)
            conversationItem.dataset.lastAgentMessageAt = timestamp;
        }
        
        // Atualizar indicador SLA em tempo real
        if (window.SLAIndicator) {
            const convData = window.SLAIndicator.getConversationData(conversationId);
            if (convData) {
                window.SLAIndicator.updateConversation(conversationId, convData);
            }
        }
        
        // Garantir dropdown de ações
        ensureActionsDropdown(conversationItem, conversationItem.classList.contains('pinned'), conversationId);
        
        // Resortear lista após atualizar
        sortConversationList();
    }
}

// Modal de Templates
function showTemplatesModal() {
    const modalElement = document.getElementById('kt_modal_templates');
    if (!modalElement) {
        console.error('Modal de templates não encontrado');
        return;
    }
    
    // Verificar se já existe uma instância do modal
    let modal = bootstrap.Modal.getInstance(modalElement);
    
    // Se não existe, criar nova instância
    if (!modal) {
        modal = new bootstrap.Modal(modalElement, {
            backdrop: true,
            keyboard: true,
            focus: true
        });
    }
    
    // Limpar qualquer listener anterior e adicionar novo
    const loadTemplatesHandler = function() {
        loadTemplates();
        modalElement.removeEventListener('shown.bs.modal', loadTemplatesHandler);
    };
    
    modalElement.addEventListener('shown.bs.modal', loadTemplatesHandler, { once: true });
    
    // Mostrar modal
    modal.show();
}

// Modal de Templates Pessoais
function showPersonalTemplatesModal() {
    const modalElement = document.getElementById('kt_modal_personal_templates');
    if (!modalElement) {
        console.error('Modal de templates pessoais não encontrado');
        return;
    }
    
    // Verificar se já existe uma instância do modal
    let modal = bootstrap.Modal.getInstance(modalElement);
    
    // Se não existe, criar nova instância
    if (!modal) {
        modal = new bootstrap.Modal(modalElement, {
            backdrop: true,
            keyboard: true,
            focus: true
        });
    }
    
    // Fechar modal de templates se estiver aberto
    const templatesModal = bootstrap.Modal.getInstance(document.getElementById('kt_modal_templates'));
    if (templatesModal) {
        templatesModal.hide();
    }
    
    // Limpar qualquer listener anterior e adicionar novo
    const loadPersonalTemplatesHandler = function() {
        loadPersonalTemplates();
        modalElement.removeEventListener('shown.bs.modal', loadPersonalTemplatesHandler);
    };
    
    modalElement.addEventListener('shown.bs.modal', loadPersonalTemplatesHandler, { once: true });
    
    // Mostrar modal
    modal.show();
}

// Carregar templates pessoais
function loadPersonalTemplates() {
    const templatesList = document.getElementById('personalTemplatesList');
    if (!templatesList) return;
    
    templatesList.innerHTML = `
        <tr>
            <td colspan="4" class="text-center text-muted py-10">
                <span class="spinner-border spinner-border-sm text-primary mb-3" role="status"></span>
                <div>Carregando templates...</div>
            </td>
        </tr>
    `;
    
    fetch('<?= \App\Helpers\Url::to("/message-templates/personal") ?>', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.templates) {
            renderPersonalTemplates(data.templates);
        } else {
            templatesList.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center text-muted py-10">
                        <i class="ki-duotone ki-information-5 fs-3x mb-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div>Você ainda não tem templates pessoais.</div>
                        <button class="btn btn-sm btn-primary mt-3" onclick="showCreatePersonalTemplateModal()">
                            <i class="ki-duotone ki-plus fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Criar Primeiro Template
                        </button>
                    </td>
                </tr>
            `;
        }
    })
    .catch(error => {
        console.error('Erro ao carregar templates pessoais:', error);
        templatesList.innerHTML = `
            <tr>
                <td colspan="4" class="text-center text-danger py-10">
                    <div>Erro ao carregar templates pessoais</div>
                </td>
            </tr>
        `;
    });
}

// Renderizar templates pessoais
function renderPersonalTemplates(templates) {
    const templatesList = document.getElementById('personalTemplatesList');
    if (!templatesList) return;
    
    if (templates.length === 0) {
        templatesList.innerHTML = `
            <tr>
                <td colspan="4" class="text-center text-muted py-10">
                    <div>Você ainda não tem templates pessoais.</div>
                    <button class="btn btn-sm btn-primary mt-3" onclick="showCreatePersonalTemplateModal()">
                        <i class="ki-duotone ki-plus fs-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Criar Primeiro Template
                    </button>
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    templates.forEach(template => {
        const category = template.category || 'Geral';
        const contentPreview = (template.content || '').substring(0, 100);
        html += `
            <tr data-template-id="${template.id}">
                <td>
                    <div class="fw-semibold text-gray-800">${escapeHtml(template.name)}</div>
                    ${template.description ? `<div class="text-muted fs-7">${escapeHtml(template.description)}</div>` : ''}
                </td>
                <td>
                    <span class="badge badge-light-primary">${escapeHtml(category)}</span>
                </td>
                <td>
                    <div class="text-gray-600 fs-7" style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(template.content || '')}">
                        ${escapeHtml(contentPreview)}${template.content && template.content.length > 100 ? '...' : ''}
                    </div>
                </td>
                <td class="text-end">
                    <div class="d-flex gap-2 justify-content-end">
                        <button class="btn btn-sm btn-light-primary" onclick="editPersonalTemplate(${template.id})" title="Editar">
                            <i class="ki-duotone ki-notepad-edit fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </button>
                        <button class="btn btn-sm btn-light-danger" onclick="deletePersonalTemplate(${template.id})" title="Excluir">
                            <i class="ki-duotone ki-trash fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    templatesList.innerHTML = html;
    
    // Adicionar busca em tempo real - remover listener anterior se existir e adicionar novo
    const searchInput = document.getElementById('personalTemplateSearch');
    if (searchInput) {
        // Clonar elemento para remover todos os listeners
        const newInput = searchInput.cloneNode(true);
        searchInput.parentNode.replaceChild(newInput, searchInput);
        
        // Adicionar novo listener
        newInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = templatesList.querySelectorAll('tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }
}

// Mostrar modal de criar template pessoal
function showCreatePersonalTemplateModal() {
    const modalElement = document.getElementById('kt_modal_personal_template_form');
    if (!modalElement) {
        console.error('Modal de formulário de template pessoal não encontrado');
        return;
    }
    
    // Resetar formulário
    const formTitle = document.getElementById('personalTemplateFormTitle');
    const form = document.getElementById('personalTemplateForm');
    const templateId = document.getElementById('personalTemplateId');
    const templateActive = document.getElementById('personalTemplateActive');
    
    if (formTitle) formTitle.textContent = 'Novo Template Pessoal';
    if (form) form.reset();
    if (templateId) templateId.value = '';
    if (templateActive) templateActive.checked = true;
    
    // Verificar se já existe uma instância do modal
    let modal = bootstrap.Modal.getInstance(modalElement);
    
    // Se não existe, criar nova instância
    if (!modal) {
        modal = new bootstrap.Modal(modalElement, {
            backdrop: true,
            keyboard: true,
            focus: true
        });
    }
    
    // Fechar modal de templates pessoais se estiver aberto
    const personalTemplatesModal = bootstrap.Modal.getInstance(document.getElementById('kt_modal_personal_templates'));
    if (personalTemplatesModal) {
        personalTemplatesModal.hide();
    }
    
    // Mostrar modal
    modal.show();
}

// Editar template pessoal
function editPersonalTemplate(templateId) {
    fetch(`<?= \App\Helpers\Url::to("/message-templates") ?>/${templateId}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.template) {
            const template = data.template;
            document.getElementById('personalTemplateFormTitle').textContent = 'Editar Template Pessoal';
            document.getElementById('personalTemplateId').value = template.id;
            document.getElementById('personalTemplateName').value = template.name || '';
            document.getElementById('personalTemplateCategory').value = template.category || '';
            document.getElementById('personalTemplateDescription').value = template.description || '';
            document.getElementById('personalTemplateContent').value = template.content || '';
            document.getElementById('personalTemplateActive').checked = template.is_active !== false;
            
            const modal = new bootstrap.Modal(document.getElementById('kt_modal_personal_template_form'));
            modal.show();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Template não encontrado',
                colorScheme: isDarkMode ? 'dark' : 'light',
                customClass: {
                    popup: isDarkMode ? 'swal2-dark' : '',
                    title: isDarkMode ? 'text-white' : '',
                    htmlContainer: isDarkMode ? 'text-white' : ''
                }
            });
        }
    })
    .catch(error => {
        console.error('Erro ao carregar template:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao carregar template',
            colorScheme: isDarkMode ? 'dark' : 'light',
            customClass: {
                popup: isDarkMode ? 'swal2-dark' : '',
                title: isDarkMode ? 'text-white' : '',
                htmlContainer: isDarkMode ? 'text-white' : ''
            }
        });
    });
}

// Deletar template pessoal
function deletePersonalTemplate(templateId) {
    Swal.fire({
        icon: 'warning',
        title: 'Confirmar Exclusão',
        text: 'Tem certeza que deseja excluir este template pessoal? Esta ação não pode ser desfeita.',
        showCancelButton: true,
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar',
        colorScheme: isDarkMode ? 'dark' : 'light',
        customClass: {
            popup: isDarkMode ? 'swal2-dark' : '',
            title: isDarkMode ? 'text-white' : '',
            htmlContainer: isDarkMode ? 'text-white' : '',
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-light'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`<?= \App\Helpers\Url::to("/message-templates") ?>/${templateId}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: 'Template excluído com sucesso',
                        timer: 2000,
                        showConfirmButton: false,
                        colorScheme: isDarkMode ? 'dark' : 'light',
                        customClass: {
                            popup: isDarkMode ? 'swal2-dark' : '',
                            title: isDarkMode ? 'text-white' : '',
                            htmlContainer: isDarkMode ? 'text-white' : ''
                        }
                    });
                    loadPersonalTemplates();
                    loadTemplates(); // Recarregar templates no modal principal também
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: data.message || 'Erro ao excluir template',
                        colorScheme: isDarkMode ? 'dark' : 'light',
                        customClass: {
                            popup: isDarkMode ? 'swal2-dark' : '',
                            title: isDarkMode ? 'text-white' : '',
                            htmlContainer: isDarkMode ? 'text-white' : ''
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Erro ao excluir template:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Erro ao excluir template',
                    colorScheme: isDarkMode ? 'dark' : 'light',
                    customClass: {
                        popup: isDarkMode ? 'swal2-dark' : '',
                        title: isDarkMode ? 'text-white' : '',
                        htmlContainer: isDarkMode ? 'text-white' : ''
                    }
                });
            });
        }
    });
}

// Submeter formulário de template pessoal
document.addEventListener('DOMContentLoaded', function() {
    const personalTemplateForm = document.getElementById('personalTemplateForm');
    if (personalTemplateForm) {
        personalTemplateForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('personalTemplateSubmitBtn');
            const indicator = submitBtn.querySelector('.indicator-label');
            const progress = submitBtn.querySelector('.indicator-progress');
            
            submitBtn.disabled = true;
            indicator.classList.add('d-none');
            progress.classList.remove('d-none');
            
            const formData = new FormData(this);
            const data = {
                name: formData.get('name'),
                category: formData.get('category') || null,
                description: formData.get('description') || null,
                content: formData.get('content'),
                is_active: formData.get('is_active') === '1',
                is_personal: true
            };
            
            const templateId = formData.get('id');
            const url = templateId 
                ? `<?= \App\Helpers\Url::to("/message-templates") ?>/${templateId}`
                : '<?= \App\Helpers\Url::to("/message-templates") ?>';
            const method = templateId ? 'POST' : 'POST';
            
            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: templateId ? 'Template atualizado com sucesso!' : 'Template criado com sucesso!',
                        timer: 2000,
                        showConfirmButton: false,
                        colorScheme: isDarkMode ? 'dark' : 'light',
                        customClass: {
                            popup: isDarkMode ? 'swal2-dark' : '',
                            title: isDarkMode ? 'text-white' : '',
                            htmlContainer: isDarkMode ? 'text-white' : ''
                        }
                    });
                    
                    bootstrap.Modal.getInstance(document.getElementById('kt_modal_personal_template_form')).hide();
                    loadPersonalTemplates();
                    loadTemplates(); // Recarregar templates no modal principal também
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: result.message || 'Erro ao salvar template',
                        colorScheme: isDarkMode ? 'dark' : 'light',
                        customClass: {
                            popup: isDarkMode ? 'swal2-dark' : '',
                            title: isDarkMode ? 'text-white' : '',
                            htmlContainer: isDarkMode ? 'text-white' : ''
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Erro ao salvar template:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Erro ao salvar template',
                    colorScheme: isDarkMode ? 'dark' : 'light',
                    customClass: {
                        popup: isDarkMode ? 'swal2-dark' : '',
                        title: isDarkMode ? 'text-white' : '',
                        htmlContainer: isDarkMode ? 'text-white' : ''
                    }
                });
            })
            .finally(() => {
                submitBtn.disabled = false;
                indicator.classList.remove('d-none');
                progress.classList.add('d-none');
            });
        });
    }
});

function loadTemplates() {
    const templatesList = document.getElementById('templatesList');
    if (!templatesList) return;
    
    // Mostrar loading
    templatesList.innerHTML = `
        <tr>
            <td colspan="4" class="text-center text-muted py-10">
                <span class="spinner-border spinner-border-sm text-primary mb-3" role="status"></span>
                <div>Carregando templates...</div>
            </td>
        </tr>
    `;
    
    // Buscar templates disponíveis para a conversa atual (inclui pessoais + globais)
    const conversationId = currentConversationId;
    const url = '<?= \App\Helpers\Url::to('/message-templates/available') ?>' + 
                (conversationId ? `?conversation_id=${conversationId}` : '');
    
    fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.templates) {
            renderTemplates(data.templates);
        } else {
            templatesList.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center text-muted py-10">
                        <i class="ki-duotone ki-information-5 fs-3x mb-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div>Nenhum template disponível</div>
                    </td>
                </tr>
            `;
        }
    })
    .catch(error => {
        console.error('Erro ao carregar templates:', error);
        templatesList.innerHTML = `
            <tr>
                <td colspan="4" class="text-center text-danger py-10">
                    <div>Erro ao carregar templates</div>
                </td>
            </tr>
        `;
    });
}

function renderTemplates(templates) {
    const templatesList = document.getElementById('templatesList');
    if (!templatesList) return;
    
    if (templates.length === 0) {
        templatesList.innerHTML = `
            <tr>
                <td colspan="4" class="text-center text-muted py-10">
                    <div>Nenhum template disponível</div>
                </td>
            </tr>
        `;
        return;
    }
    
    // Separar templates pessoais e globais
    const personalTemplates = templates.filter(t => t.user_id !== null && t.user_id !== undefined);
    const globalTemplates = templates.filter(t => t.user_id === null || t.user_id === undefined);
    
    let html = '';
    
    // Templates pessoais primeiro
    if (personalTemplates.length > 0) {
        html += `
            <tr class="table-group">
                <td colspan="4" class="bg-light-primary fw-bold py-2">
                    <i class="ki-duotone ki-user fs-6 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Meus Templates (${personalTemplates.length})
                </td>
            </tr>
        `;
        personalTemplates.forEach(template => {
            const category = template.category || 'Geral';
            html += `
                <tr data-template-id="${template.id}" data-type="personal">
                    <td>
                        <div class="fw-semibold text-gray-800">${escapeHtml(template.name)}</div>
                        ${template.description ? `<div class="text-muted fs-7">${escapeHtml(template.description)}</div>` : ''}
                    </td>
                    <td>
                        <span class="badge badge-light-primary">${escapeHtml(category)}</span>
                    </td>
                    <td>
                        <span class="badge badge-light-warning badge-sm">
                            <i class="ki-duotone ki-user fs-7 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Pessoal
                        </span>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-light-primary me-2" onclick="previewTemplate(${template.id})" title="Preview">
                            <i class="ki-duotone ki-eye fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </button>
                        <button class="btn btn-sm btn-primary" onclick="useTemplate(${template.id})" title="Usar template">
                            <i class="ki-duotone ki-check fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Usar
                        </button>
                    </td>
                </tr>
            `;
        });
    }
    
    // Templates globais depois
    if (globalTemplates.length > 0) {
        html += `
            <tr class="table-group">
                <td colspan="4" class="bg-light-info fw-bold py-2">
                    <i class="ki-duotone ki-global fs-6 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Templates Globais (${globalTemplates.length})
                </td>
            </tr>
        `;
        globalTemplates.forEach(template => {
            const category = template.category || 'Geral';
            html += `
                <tr data-template-id="${template.id}" data-type="global">
                    <td>
                        <div class="fw-semibold text-gray-800">${escapeHtml(template.name)}</div>
                        ${template.description ? `<div class="text-muted fs-7">${escapeHtml(template.description)}</div>` : ''}
                    </td>
                    <td>
                        <span class="badge badge-light-info">${escapeHtml(category)}</span>
                    </td>
                    <td>
                        <span class="badge badge-light-secondary badge-sm">
                            <i class="ki-duotone ki-global fs-7 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Global
                        </span>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-light-primary me-2" onclick="previewTemplate(${template.id})" title="Preview">
                            <i class="ki-duotone ki-eye fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </button>
                        <button class="btn btn-sm btn-primary" onclick="useTemplate(${template.id})" title="Usar template">
                            <i class="ki-duotone ki-check fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Usar
                        </button>
                    </td>
                </tr>
            `;
        });
    }
    
    templatesList.innerHTML = html;
    
    // Adicionar busca em tempo real
    const searchInput = document.getElementById('templateSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = templatesList.querySelectorAll('tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }
}

function previewTemplate(templateId) {
    if (!currentConversationId) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Selecione uma conversa primeiro para visualizar o preview com variáveis',
            confirmButtonText: 'OK'
        });
        return;
    }
    
    fetch(`<?= \App\Helpers\Url::to('/message-templates') ?>/${templateId}/preview`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            conversation_id: currentConversationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                               document.body.classList.contains('dark-mode') ||
                               window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            Swal.fire({
                title: 'Preview do Template',
                html: `
                    <div class="text-start">
                        <div class="alert alert-info mb-4" style="white-space: pre-wrap; text-align: left;">${escapeHtml(data.processed_content || data.content)}</div>
                        ${data.variables_used ? `
                            <div class="text-muted fs-7">
                                <strong>Variáveis utilizadas:</strong> ${data.variables_used.join(', ')}
                            </div>
                        ` : ''}
                    </div>
                `,
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Usar este Template',
                cancelButtonText: 'Fechar',
                buttonsStyling: false,
                colorScheme: isDarkMode ? 'dark' : 'light',
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-light',
                    popup: isDarkMode ? 'swal2-dark' : '',
                    title: isDarkMode ? 'text-white' : '',
                    htmlContainer: isDarkMode ? 'text-white' : ''
                },
                width: '700px'
            }).then((result) => {
                if (result.isConfirmed) {
                    useTemplate(templateId);
                }
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: data.message || 'Erro ao processar template'
            });
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao carregar preview do template'
        });
    });
}

function useTemplate(templateId) {
    if (!currentConversationId) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Selecione uma conversa primeiro',
            confirmButtonText: 'OK'
        });
        return;
    }
    
    fetch(`<?= \App\Helpers\Url::to('/message-templates') ?>/${templateId}/process`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            conversation_id: currentConversationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.value = data.processed_content || data.content;
                messageInput.focus();
                
                // Fechar modal de templates
                const modal = bootstrap.Modal.getInstance(document.getElementById('kt_modal_templates'));
                if (modal) {
                    modal.hide();
                }
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: data.message || 'Erro ao processar template'
            });
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao processar template'
        });
    });
}

function loadTemplates() {
    const tbody = document.getElementById('templatesList');
    if (!tbody) {
        console.error('Elemento templatesList não encontrado');
        return;
    }
    
    // Mostrar loading
    tbody.innerHTML = `
        <tr>
            <td colspan="3" class="text-center text-muted py-10">
                <span class="spinner-border spinner-border-sm text-primary mb-3" role="status"></span>
                <div>Carregando templates...</div>
            </td>
        </tr>
    `;
    
    fetch('<?= \App\Helpers\Url::to("/message-templates/available") ?>')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (!data.success || !data.templates || data.templates.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="3" class="text-center text-muted py-10">
                            Nenhum template disponível
                        </td>
                    </tr>
                `;
                return;
            }
            
            let html = '';
            data.templates.forEach(template => {
                html += `
                    <tr data-template-id="${template.id}">
                        <td>
                            <div class="fw-semibold text-gray-800">${escapeHtml(template.name)}</div>
                            <div class="text-muted fs-7">${escapeHtml(template.content.substring(0, 80))}${template.content.length > 80 ? '...' : ''}</div>
                            <div class="template-preview mt-2 p-3 bg-light rounded d-none" id="preview-${template.id}" style="white-space: pre-wrap; font-size: 13px; border-left: 3px solid var(--bs-primary);">
                                <div class="fw-semibold mb-2 text-primary">Preview:</div>
                                <div class="preview-content text-gray-700"></div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-light-primary">${escapeHtml(template.category || 'Geral')}</span>
                        </td>
                        <td class="text-end">
                            <div class="d-flex gap-2 justify-content-end">
                                <button class="btn btn-sm btn-light-info" onclick="previewTemplate(${template.id})" title="Ver preview">
                                    <i class="ki-duotone ki-eye fs-5">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                </button>
                                <button class="btn btn-sm btn-light-primary" onclick="useTemplate(${template.id})">
                                    <i class="ki-duotone ki-check fs-5">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Usar
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
            
            // Busca de templates - remover listener anterior se existir e adicionar novo
            const templateSearchInput = document.getElementById('templateSearch');
            if (templateSearchInput) {
                // Clonar elemento para remover todos os listeners
                const newInput = templateSearchInput.cloneNode(true);
                templateSearchInput.parentNode.replaceChild(newInput, templateSearchInput);
                
                // Adicionar novo listener
                newInput.addEventListener('input', function(e) {
                    const search = e.target.value.toLowerCase();
                    const rows = tbody.querySelectorAll('tr');
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(search) ? '' : 'none';
                    });
                });
            }
        })
        .catch(error => {
            console.error('Erro ao carregar templates:', error);
            const tbody = document.getElementById('templatesList');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="3" class="text-center text-danger py-10">
                            Erro ao carregar templates: ${error.message || 'Erro desconhecido'}
                        </td>
                    </tr>
                `;
            }
        });
}

// Preview de template com variáveis preenchidas
function previewTemplate(templateId) {
const conversationId = parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
    const previewDiv = document.getElementById(`preview-${templateId}`);
    const previewContent = previewDiv ? previewDiv.querySelector('.preview-content') : null;
    
    if (!previewDiv || !previewContent) return;
    
    // Alternar visibilidade do preview
    const isVisible = !previewDiv.classList.contains('d-none');
    
    if (isVisible) {
        // Esconder preview
        previewDiv.classList.add('d-none');
        return;
    }
    
    // Mostrar loading
    previewContent.innerHTML = '<div class="text-muted"><i class="ki-duotone ki-loader fs-6"><span class="path1"></span><span class="path2"></span></i> Carregando preview...</div>';
    previewDiv.classList.remove('d-none');
    
    // Buscar preview do template
    fetch(`<?= \App\Helpers\Url::to("/message-templates") ?>/${templateId}/preview`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            conversation_id: conversationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.preview) {
            // Mostrar preview formatado
            previewContent.innerHTML = escapeHtml(data.preview).replace(/\n/g, '<br>');
        } else {
            previewContent.innerHTML = '<div class="text-danger">Erro ao gerar preview</div>';
        }
    })
    .catch(error => {
        console.error('Erro ao gerar preview:', error);
        previewContent.innerHTML = '<div class="text-danger">Erro ao carregar preview</div>';
    });
}

function useTemplate(templateId) {
    const conversationId = currentConversationId || parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
    
    if (!conversationId) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Selecione uma conversa primeiro',
            confirmButtonText: 'OK'
        });
        return;
    }
    
    fetch(`<?= \App\Helpers\Url::to("/message-templates") ?>/${templateId}/process`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            conversation_id: conversationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.processed_content) {
            const input = document.getElementById('messageInput');
            input.value = data.processed_content;
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 150) + 'px';
            input.focus();
            
            bootstrap.Modal.getInstance(document.getElementById('kt_modal_templates')).hide();
        } else {
            alert('Erro ao processar template: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao processar template');
    });
}

// Seletor rápido de templates
let templateQuickSelectData = [];
let templateQuickSelectIndex = -1;
let templateQuickSelectDebounce = null;

function initTemplateQuickSelect() {
    const messageInput = document.getElementById('messageInput');
    const templateQuickSelect = document.getElementById('templateQuickSelect');
    const templateQuickSearch = document.getElementById('templateQuickSearch');
    const templateQuickList = document.getElementById('templateQuickList');
    
    if (!messageInput || !templateQuickSelect || !templateQuickSearch || !templateQuickList) return;
    
    // Detectar digitação de {{ no campo de mensagem
    messageInput.addEventListener('input', function(e) {
        const value = e.target.value;
        const cursorPos = e.target.selectionStart;
        const textBeforeCursor = value.substring(0, cursorPos);
        
        // Verificar se digitou {{ antes do cursor
        if (textBeforeCursor.endsWith('{{')) {
            showTemplateQuickSelect();
        } else if (textBeforeCursor.includes('{{') && !textBeforeCursor.includes('}}')) {
            // Se já tem {{ mas não fechou, manter aberto
            const lastOpen = textBeforeCursor.lastIndexOf('{{');
            const textAfterOpen = textBeforeCursor.substring(lastOpen + 2);
            if (!textAfterOpen.includes('}}')) {
                filterTemplateQuickSelect(textAfterOpen);
            } else {
                hideTemplateQuickSelect();
            }
        } else {
            hideTemplateQuickSelect();
        }
    });
    
    // Fechar ao perder foco (com delay para permitir cliques)
    messageInput.addEventListener('blur', function() {
        setTimeout(() => {
            if (!templateQuickSelect.matches(':hover') && !templateQuickSearch.matches(':focus')) {
                hideTemplateQuickSelect();
            }
        }, 200);
    });
    
    // Busca no dropdown
    templateQuickSearch.addEventListener('input', function(e) {
        filterTemplateQuickSelect(e.target.value);
    });
    
    // Navegação com teclado
    messageInput.addEventListener('keydown', function(e) {
        if (!templateQuickSelect.classList.contains('d-none')) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                navigateTemplateQuickSelect(1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                navigateTemplateQuickSelect(-1);
            } else if (e.key === 'Enter' && templateQuickSelectIndex >= 0) {
                e.preventDefault();
                selectTemplateQuickItem(templateQuickSelectIndex);
            } else if (e.key === 'Escape') {
                e.preventDefault();
                hideTemplateQuickSelect();
            }
        }
    });
    
    // Clique no botão de templates também abre o quick select
    const templateBtn = document.querySelector('button[onclick="showTemplatesModal()"]');
    if (templateBtn) {
        templateBtn.addEventListener('click', function(e) {
            // Se segurar Shift, abre quick select ao invés do modal
            if (e.shiftKey) {
                e.preventDefault();
                e.stopPropagation();
                showTemplateQuickSelect();
                return false;
            }
        });
    }
}

function showTemplateQuickSelect() {
    const templateQuickSelect = document.getElementById('templateQuickSelect');
    const templateQuickList = document.getElementById('templateQuickList');
    
    if (!templateQuickSelect || !templateQuickList) return;
    
    templateQuickSelect.classList.remove('d-none');
    templateQuickSelectIndex = -1;
    
    // Carregar templates se ainda não carregou
    if (templateQuickSelectData.length === 0) {
        loadTemplateQuickSelect();
    } else {
        renderTemplateQuickSelect(templateQuickSelectData);
    }
}

function hideTemplateQuickSelect() {
    const templateQuickSelect = document.getElementById('templateQuickSelect');
    if (templateQuickSelect) {
        templateQuickSelect.classList.add('d-none');
        templateQuickSelectIndex = -1;
    }
}

function loadTemplateQuickSelect() {
    const templateQuickList = document.getElementById('templateQuickList');
    if (!templateQuickList) return;
    
    templateQuickList.innerHTML = `
        <div class="text-center text-muted py-5">
            <i class="ki-duotone ki-loader fs-3x mb-3">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <div>Carregando templates...</div>
        </div>
    `;
    
    fetch('<?= \App\Helpers\Url::to("/message-templates/available") ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.templates) {
                // Separar e ordenar: pessoais primeiro, depois globais
                const personalTemplates = data.templates.filter(t => t.user_id !== null && t.user_id !== undefined);
                const globalTemplates = data.templates.filter(t => t.user_id === null || t.user_id === undefined);
                templateQuickSelectData = [...personalTemplates, ...globalTemplates];
                renderTemplateQuickSelect(templateQuickSelectData);
            } else {
                templateQuickList.innerHTML = `
                    <div class="text-center text-muted py-5">
                        Nenhum template disponível
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Erro ao carregar templates:', error);
            templateQuickList.innerHTML = `
                <div class="text-center text-danger py-5">
                    Erro ao carregar templates
                </div>
            `;
        });
}

function renderTemplateQuickSelect(templates) {
    const templateQuickList = document.getElementById('templateQuickList');
    const templateQuickSearch = document.getElementById('templateQuickSearch');
    if (!templateQuickList) return;
    
    const searchTerm = templateQuickSearch ? templateQuickSearch.value.toLowerCase() : '';
    
    // Separar templates pessoais e globais
    const personalTemplates = templates.filter(t => t.user_id !== null && t.user_id !== undefined);
    const globalTemplates = templates.filter(t => t.user_id === null || t.user_id === undefined);
    
    // Filtrar ambos os grupos
    const filteredPersonal = personalTemplates.filter(t => {
        if (!searchTerm) return true;
        const name = (t.name || '').toLowerCase();
        const content = (t.content || '').toLowerCase();
        const category = (t.category || '').toLowerCase();
        return name.includes(searchTerm) || content.includes(searchTerm) || category.includes(searchTerm);
    });
    
    const filteredGlobal = globalTemplates.filter(t => {
        if (!searchTerm) return true;
        const name = (t.name || '').toLowerCase();
        const content = (t.content || '').toLowerCase();
        const category = (t.category || '').toLowerCase();
        return name.includes(searchTerm) || content.includes(searchTerm) || category.includes(searchTerm);
    });
    
    const filteredTemplates = [...filteredPersonal, ...filteredGlobal];
    
    if (filteredTemplates.length === 0) {
        templateQuickList.innerHTML = `
            <div class="text-center text-muted py-5">
                Nenhum template encontrado
            </div>
        `;
        return;
    }
    
    let html = '';
    let currentIndex = 0;
    
    // Templates pessoais primeiro
    if (filteredPersonal.length > 0) {
        html += `
            <div class="template-quick-group-header">
                <i class="ki-duotone ki-user fs-7 me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Meus Templates (${filteredPersonal.length})
            </div>
        `;
        filteredPersonal.forEach((template) => {
            const preview = (template.content || '').substring(0, 60);
            html += `
                <div class="template-quick-item ${currentIndex === templateQuickSelectIndex ? 'selected' : ''}" 
                     data-template-id="${template.id}" 
                     data-index="${currentIndex}"
                     onclick="selectTemplateQuickItem(${currentIndex})"
                     onmouseenter="highlightTemplateQuickItem(${currentIndex})">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="template-quick-item-name">${escapeHtml(template.name || 'Sem nome')}</div>
                        <span class="badge badge-light-warning badge-sm ms-2">
                            <i class="ki-duotone ki-user fs-7">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                    </div>
                    <div class="template-quick-item-preview">${escapeHtml(preview)}${template.content && template.content.length > 60 ? '...' : ''}</div>
                    ${template.category ? `<span class="template-quick-item-category">${escapeHtml(template.category)}</span>` : ''}
                </div>
            `;
            currentIndex++;
        });
    }
    
    // Templates globais depois
    if (filteredGlobal.length > 0) {
        html += `
            <div class="template-quick-group-header">
                <i class="ki-duotone ki-global fs-7 me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Templates Globais (${filteredGlobal.length})
            </div>
        `;
        filteredGlobal.forEach((template) => {
            const preview = (template.content || '').substring(0, 60);
            html += `
                <div class="template-quick-item ${currentIndex === templateQuickSelectIndex ? 'selected' : ''}" 
                     data-template-id="${template.id}" 
                     data-index="${currentIndex}"
                     onclick="selectTemplateQuickItem(${currentIndex})"
                     onmouseenter="highlightTemplateQuickItem(${currentIndex})">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="template-quick-item-name">${escapeHtml(template.name || 'Sem nome')}</div>
                        <span class="badge badge-light-secondary badge-sm ms-2">
                            <i class="ki-duotone ki-global fs-7">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                    </div>
                    <div class="template-quick-item-preview">${escapeHtml(preview)}${template.content && template.content.length > 60 ? '...' : ''}</div>
                    ${template.category ? `<span class="template-quick-item-category">${escapeHtml(template.category)}</span>` : ''}
                </div>
            `;
            currentIndex++;
        });
    }
    
    templateQuickList.innerHTML = html;
    
    // Scroll para item selecionado
    if (templateQuickSelectIndex >= 0) {
        const selectedItem = templateQuickList.querySelector(`[data-index="${templateQuickSelectIndex}"]`);
        if (selectedItem) {
            selectedItem.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }
}

function filterTemplateQuickSelect(searchTerm) {
    if (templateQuickSelectDebounce) {
        clearTimeout(templateQuickSelectDebounce);
    }
    
    templateQuickSelectDebounce = setTimeout(() => {
        templateQuickSelectIndex = -1;
        renderTemplateQuickSelect(templateQuickSelectData);
    }, 150);
}

function navigateTemplateQuickSelect(direction) {
    const templateQuickList = document.getElementById('templateQuickList');
    if (!templateQuickList) return;
    
    const items = templateQuickList.querySelectorAll('.template-quick-item');
    if (items.length === 0) return;
    
    templateQuickSelectIndex += direction;
    
    if (templateQuickSelectIndex < 0) {
        templateQuickSelectIndex = items.length - 1;
    } else if (templateQuickSelectIndex >= items.length) {
        templateQuickSelectIndex = 0;
    }
    
    // Atualizar visual
    items.forEach((item, index) => {
        if (index === templateQuickSelectIndex) {
            item.classList.add('selected');
            item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        } else {
            item.classList.remove('selected');
        }
    });
}

function highlightTemplateQuickItem(index) {
    templateQuickSelectIndex = index;
    const templateQuickList = document.getElementById('templateQuickList');
    if (!templateQuickList) return;
    
    const items = templateQuickList.querySelectorAll('.template-quick-item');
    items.forEach((item, i) => {
        if (i === index) {
            item.classList.add('selected');
        } else {
            item.classList.remove('selected');
        }
    });
}

function selectTemplateQuickItem(index) {
    const templateQuickList = document.getElementById('templateQuickList');
    if (!templateQuickList) return;
    
    const items = templateQuickList.querySelectorAll('.template-quick-item');
    if (index < 0 || index >= items.length) return;
    
    const item = items[index];
    const templateId = item.getAttribute('data-template-id');
    
    if (templateId) {
        useTemplateQuick(templateId);
    }
}

function useTemplateQuick(templateId) {
    const messageInput = document.getElementById('messageInput');
    if (!messageInput) return;
    
    if (!currentConversationId) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Selecione uma conversa primeiro para usar templates',
            confirmButtonText: 'OK'
        });
        hideTemplateQuickSelect();
        return;
    }
    
    // Buscar e processar template
    fetch(`<?= \App\Helpers\Url::to("/message-templates") ?>/${templateId}/process`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            conversation_id: currentConversationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.processed_content) {
            // Substituir {{ pelo conteúdo do template
            const currentValue = messageInput.value;
            const cursorPos = messageInput.selectionStart;
            const textBeforeCursor = currentValue.substring(0, cursorPos);
            const textAfterCursor = currentValue.substring(cursorPos);
            
            // Encontrar último {{ antes do cursor
            const lastOpen = textBeforeCursor.lastIndexOf('{{');
            if (lastOpen >= 0) {
                const newValue = currentValue.substring(0, lastOpen) + data.processed_content + textAfterCursor;
                messageInput.value = newValue;
                messageInput.style.height = 'auto';
                messageInput.style.height = Math.min(messageInput.scrollHeight, 150) + 'px';
                
                // Posicionar cursor após o conteúdo inserido
                const newCursorPos = lastOpen + data.processed_content.length;
                messageInput.setSelectionRange(newCursorPos, newCursorPos);
            } else {
                // Se não encontrou {{, apenas inserir o conteúdo
                messageInput.value = data.processed_content;
                messageInput.style.height = 'auto';
                messageInput.style.height = Math.min(messageInput.scrollHeight, 150) + 'px';
            }
            
            messageInput.focus();
            hideTemplateQuickSelect();
        } else {
            const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                               document.body.classList.contains('dark-mode') ||
                               window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: data.message || 'Erro ao processar template',
                colorScheme: isDarkMode ? 'dark' : 'light',
                customClass: {
                    popup: isDarkMode ? 'swal2-dark' : '',
                    title: isDarkMode ? 'text-white' : '',
                    htmlContainer: isDarkMode ? 'text-white' : ''
                }
            });
            hideTemplateQuickSelect();
        }
    })
    .catch(error => {
        console.error('Erro ao processar template:', error);
        const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                           document.body.classList.contains('dark-mode') ||
                           window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao processar template',
            colorScheme: isDarkMode ? 'dark' : 'light',
            customClass: {
                popup: isDarkMode ? 'swal2-dark' : '',
                title: isDarkMode ? 'text-white' : '',
                htmlContainer: isDarkMode ? 'text-white' : ''
            }
        });
        hideTemplateQuickSelect();
    });
}

// Toggle nome do agente
function toggleAgentName() {
    const toggle = document.getElementById('agentNameToggle');
    const icon = document.getElementById('agentNameToggleIcon');
    
    if (toggle.classList.contains('active')) {
        toggle.classList.remove('active');
        toggle.classList.remove('btn-primary');
        toggle.classList.add('btn-light-primary');
        icon.classList.remove('text-primary');
        localStorage.setItem('agentNameEnabled', 'false');
    } else {
        toggle.classList.add('active');
        toggle.classList.remove('btn-light-primary');
        toggle.classList.add('btn-primary');
        icon.classList.add('text-primary');
        localStorage.setItem('agentNameEnabled', 'true');
    }
}

// Carregar estado inicial do toggle do nome do agente
document.addEventListener('DOMContentLoaded', function() {
    const defaultEnabled = <?= json_encode(\App\Services\SettingService::get('chat_agent_name_enabled', false)) ?>;
    const savedState = localStorage.getItem('agentNameEnabled');
    const isEnabled = savedState !== null ? savedState === 'true' : defaultEnabled;
    
    const toggle = document.getElementById('agentNameToggle');
    const icon = document.getElementById('agentNameToggleIcon');
    
    if (isEnabled && toggle) {
        toggle.classList.add('active');
        toggle.classList.remove('btn-light-primary');
        toggle.classList.add('btn-primary');
        if (icon) icon.classList.add('text-primary');
    }
});

// Modal de Variáveis
function showVariablesModal() {
    const modalElement = document.getElementById('kt_modal_variables');
    if (!modalElement) {
        console.error('Modal de variáveis não encontrado');
        return;
    }
    
    const modal = new bootstrap.Modal(modalElement);
    modal.show();
    
    // Carregar variáveis disponíveis
    loadVariables();
}

function loadVariables() {
    const variablesList = document.getElementById('variablesList');
    if (!variablesList) return;
    
    // Mostrar loading
    variablesList.innerHTML = `
        <div class="col-12 text-center py-10">
            <span class="spinner-border spinner-border-sm text-primary mb-3" role="status"></span>
            <div class="text-muted">Carregando variáveis...</div>
        </div>
    `;
    
    fetch('<?= \App\Helpers\Url::to('/message-templates/variables') ?>', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(async response => {
        // Verificar se a resposta é JSON antes de fazer parse
        const contentType = response.headers.get('content-type') || '';
        const text = await response.text();
        
        if (!contentType.includes('application/json')) {
            console.error('Resposta não é JSON. Content-Type:', contentType);
            console.error('Resposta completa:', text.substring(0, 500));
            throw new Error('Resposta não é JSON. Verifique o console para mais detalhes.');
        }
        
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Erro ao fazer parse do JSON:', e);
            console.error('Texto recebido:', text.substring(0, 500));
            throw new Error('Resposta não é JSON válido. Verifique o console para mais detalhes.');
        }
    })
    .then(data => {
        if (data.success && data.variables) {
            renderVariables(data.variables);
        } else {
            variablesList.innerHTML = `
                <div class="col-12 text-center text-muted py-10">
                    <div>Nenhuma variável disponível</div>
                    ${data.message ? `<div class="fs-7 mt-2">${escapeHtml(data.message)}</div>` : ''}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Erro ao carregar variáveis:', error);
        variablesList.innerHTML = `
            <div class="col-12 text-center text-danger py-10">
                <div>Erro ao carregar variáveis</div>
                <div class="fs-7 mt-2">${escapeHtml(error.message || 'Erro desconhecido')}</div>
                <div class="fs-8 mt-3 text-muted">Verifique o console para mais detalhes</div>
            </div>
        `;
    });
}

function renderVariables(variables) {
    const variablesList = document.getElementById('variablesList');
    if (!variablesList) return;
    
    let html = '';
    
    // Variáveis de contato
    if (variables.contact) {
        html += ''
            + '<div class="col-12">'
            + '  <h5 class="fw-bold mb-3">'
            + '    <i class="ki-duotone ki-user fs-4 text-primary me-2">'
            + '      <span class="path1"></span>'
            + '      <span class="path2"></span>'
            + '    </i>'
            + '    Contato'
            + '  </h5>'
            + '</div>';

        Object.keys(variables.contact).forEach(key => {
            const varName = `{{contact.${key}}}`;
            const label = escapeHtml(varName);
            const desc = escapeHtml(variables.contact[key]);
            html += ''
                + '<div class="col-md-6">'
                + '  <div class="card card-flush shadow-sm hover-shadow-lg cursor-pointer" onclick="insertVariable(\''
                + varName.replace(/'/g, "\\'")
                + '\')">'
                + '    <div class="card-body p-4">'
                + '      <div class="d-flex justify-content-between align-items-center">'
                + '        <div>'
                + '          <div class="fw-semibold text-gray-800">' + label + '</div>'
                + '          <div class="text-muted fs-7">' + desc + '</div>'
                + '        </div>'
                + '        <i class="ki-duotone ki-copy fs-4 text-primary">'
                + '          <span class="path1"></span>'
                + '          <span class="path2"></span>'
                + '        </i>'
                + '      </div>'
                + '    </div>'
                + '  </div>'
                + '</div>';
        });
    }
    
    // Variáveis de agente
    if (variables.agent) {
        html += ''
            + '<div class="col-12 mt-5">'
            + '  <h5 class="fw-bold mb-3">'
            + '    <i class="ki-duotone ki-profile-user fs-4 text-success me-2">'
            + '      <span class="path1"></span>'
            + '      <span class="path2"></span>'
            + '    </i>'
            + '    Agente'
            + '  </h5>'
            + '</div>';
        
        Object.keys(variables.agent).forEach(key => {
            const varName = `{{agent.${key}}}`;
            const label = escapeHtml(varName);
            const desc = escapeHtml(variables.agent[key]);
            html += ''
                + '<div class="col-md-6">'
                + '  <div class="card card-flush shadow-sm hover-shadow-lg cursor-pointer" onclick="insertVariable(\''
                + varName.replace(/'/g, "\\'")
                + '\')">'
                + '    <div class="card-body p-4">'
                + '      <div class="d-flex justify-content-between align-items-center">'
                + '        <div>'
                + '          <div class="fw-semibold text-gray-800">' + label + '</div>'
                + '          <div class="text-muted fs-7">' + desc + '</div>'
                + '        </div>'
                + '        <i class="ki-duotone ki-copy fs-4 text-success">'
                + '          <span class="path1"></span>'
                + '          <span class="path2"></span>'
                + '        </i>'
                + '      </div>'
                + '    </div>'
                + '  </div>'
                + '</div>';
        });
    }
    
    // Variáveis de conversa
    if (variables.conversation) {
        html += ''
            + '<div class="col-12 mt-5">'
            + '  <h5 class="fw-bold mb-3">'
            + '    <i class="ki-duotone ki-message-text fs-4 text-info me-2">'
            + '      <span class="path1"></span>'
            + '      <span class="path2"></span>'
            + '      <span class="path3"></span>'
            + '    </i>'
            + '    Conversa'
            + '  </h5>'
            + '</div>';
        
        Object.keys(variables.conversation).forEach(key => {
            const varName = `{{conversation.${key}}}`;
            const label = escapeHtml(varName);
            const desc = escapeHtml(variables.conversation[key]);
            html += ''
                + '<div class="col-md-6">'
                + '  <div class="card card-flush shadow-sm hover-shadow-lg cursor-pointer" onclick="insertVariable(\''
                + varName.replace(/'/g, "\\'")
                + '\')">'
                + '    <div class="card-body p-4">'
                + '      <div class="d-flex justify-content-between align-items-center">'
                + '        <div>'
                + '          <div class="fw-semibold text-gray-800">' + label + '</div>'
                + '          <div class="text-muted fs-7">' + desc + '</div>'
                + '        </div>'
                + '        <i class="ki-duotone ki-copy fs-4 text-info">'
                + '          <span class="path1"></span>'
                + '          <span class="path2"></span>'
                + '        </i>'
                + '      </div>'
                + '    </div>'
                + '  </div>'
                + '</div>';
        });
    }
    
    // Variáveis de data/hora
    html += ''
        + '<div class="col-12 mt-5">'
        + '  <h5 class="fw-bold mb-3">'
        + '    <i class="ki-duotone ki-time fs-4 text-warning me-2">'
        + '      <span class="path1"></span>'
        + '      <span class="path2"></span>'
        + '    </i>'
        + '    Data e Hora'
        + '  </h5>'
        + '</div>';
    
    const dateVariables = {
        'date': 'Data atual (dd/mm/yyyy)',
        'time': 'Hora atual (HH:mm)',
        'datetime': 'Data e hora atuais'
    };
    
    Object.keys(dateVariables).forEach(key => {
        const varName = `{{${key}}}`;
        const label = escapeHtml(varName);
        const desc = escapeHtml(dateVariables[key]);
        html += ''
            + '<div class="col-md-4">'
            + '  <div class="card card-flush shadow-sm hover-shadow-lg cursor-pointer" onclick="insertVariable(\''
            + varName.replace(/'/g, "\\'")
            + '\')">'
            + '    <div class="card-body p-4">'
            + '      <div class="d-flex justify-content-between align-items-center">'
            + '        <div>'
            + '          <div class="fw-semibold text-gray-800">' + label + '</div>'
            + '          <div class="text-muted fs-7">' + desc + '</div>'
            + '        </div>'
            + '        <i class="ki-duotone ki-copy fs-4 text-warning">'
            + '          <span class="path1"></span>'
            + '          <span class="path2"></span>'
            + '        </i>'
            + '      </div>'
            + '    </div>'
            + '  </div>'
            + '</div>';
    });
    
    variablesList.innerHTML = html;
}

function insertVariable(variable) {
    const messageInput = document.getElementById('messageInput');
    if (!messageInput) return;
    
    const startPos = messageInput.selectionStart;
    const endPos = messageInput.selectionEnd;
    const text = messageInput.value;
    
    // Inserir variável na posição do cursor
    const newText = text.substring(0, startPos) + variable + text.substring(endPos);
    messageInput.value = newText;
    
    // Reposicionar cursor após a variável inserida
    const newPos = startPos + variable.length;
    messageInput.setSelectionRange(newPos, newPos);
    messageInput.focus();
    
    // Mostrar feedback visual
    const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                       document.body.classList.contains('dark-mode') ||
                       window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    Swal.fire({
        icon: 'success',
        title: 'Variável inserida!',
        text: `Variável ${variable} inserida no campo de mensagem`,
        timer: 1500,
        showConfirmButton: false,
        colorScheme: isDarkMode ? 'dark' : 'light',
        customClass: {
            popup: isDarkMode ? 'swal2-dark' : '',
            title: isDarkMode ? 'text-white' : '',
            htmlContainer: isDarkMode ? 'text-white' : ''
        }
    });
    
    // Fechar modal após inserir
    const modal = bootstrap.Modal.getInstance(document.getElementById('kt_modal_variables'));
    if (modal) {
        setTimeout(() => modal.hide(), 500);
    }
}

function renderVariables(variables) {
    const variablesList = document.getElementById('variablesList');
    if (!variablesList) return;
    
    let html = '';
    
    // Variáveis de contato
    if (variables.contact) {
        html += `
            <div class="col-12">
                <h5 class="fw-bold mb-3">
                    <i class="ki-duotone ki-user fs-4 text-primary me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Contato
                </h5>
            </div>
        `;
        Object.keys(variables.contact).forEach(key => {
            const varName = `{{contact.${key}}}`;
            html += `
                <div class="col-md-6">
                    <div class="card card-flush shadow-sm hover-shadow-lg cursor-pointer transition-all" onclick="insertVariable('${varName}')" style="cursor: pointer;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <code class="text-primary fw-bold fs-6 d-block mb-1">${escapeHtml(varName)}</code>
                                    <div class="text-muted fs-7">${escapeHtml(variables.contact[key])}</div>
                                </div>
                                <i class="ki-duotone ki-copy fs-4 text-primary">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    // Variáveis de agente
    if (variables.agent) {
        html += `
            <div class="col-12 mt-5">
                <h5 class="fw-bold mb-3">
                    <i class="ki-duotone ki-profile-user fs-4 text-success me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Agente
                </h5>
            </div>
        `;
        Object.keys(variables.agent).forEach(key => {
            const varName = `{{agent.${key}}}`;
            html += `
                <div class="col-md-6">
                    <div class="card card-flush shadow-sm hover-shadow-lg cursor-pointer transition-all" onclick="insertVariable('${varName}')" style="cursor: pointer;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <code class="text-success fw-bold fs-6 d-block mb-1">${escapeHtml(varName)}</code>
                                    <div class="text-muted fs-7">${escapeHtml(variables.agent[key])}</div>
                                </div>
                                <i class="ki-duotone ki-copy fs-4 text-success">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    // Variáveis de conversa
    if (variables.conversation) {
        html += `
            <div class="col-12 mt-5">
                <h5 class="fw-bold mb-3">
                    <i class="ki-duotone ki-message-text fs-4 text-info me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Conversa
                </h5>
            </div>
        `;
        Object.keys(variables.conversation).forEach(key => {
            const varName = `{{conversation.${key}}}`;
            html += `
                <div class="col-md-6">
                    <div class="card card-flush shadow-sm hover-shadow-lg cursor-pointer transition-all" onclick="insertVariable('${varName}')" style="cursor: pointer;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <code class="text-info fw-bold fs-6 d-block mb-1">${escapeHtml(varName)}</code>
                                    <div class="text-muted fs-7">${escapeHtml(variables.conversation[key])}</div>
                                </div>
                                <i class="ki-duotone ki-copy fs-4 text-info">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    // Variáveis de data/hora
    html += `
        <div class="col-12 mt-5">
            <h5 class="fw-bold mb-3">
                <i class="ki-duotone ki-time fs-4 text-warning me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Data e Hora
            </h5>
        </div>
    `;
    
    const dateVariables = {
        'date': 'Data atual (dd/mm/yyyy)',
        'time': 'Hora atual (HH:mm)',
        'datetime': 'Data e hora atuais'
    };
    
    Object.keys(dateVariables).forEach(key => {
        const varName = `{{${key}}}`;
        html += `
            <div class="col-md-4">
                <div class="card card-flush shadow-sm hover-shadow-lg cursor-pointer transition-all" onclick="insertVariable('${varName}')" style="cursor: pointer;">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <code class="text-warning fw-bold fs-6 d-block mb-1">${escapeHtml(varName)}</code>
                                <div class="text-muted fs-7">${escapeHtml(dateVariables[key])}</div>
                            </div>
                            <i class="ki-duotone ki-copy fs-4 text-warning">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    variablesList.innerHTML = html;
}

function insertVariable(variable) {
    const messageInput = document.getElementById('messageInput');
    if (!messageInput) return;
    
    const startPos = messageInput.selectionStart;
    const endPos = messageInput.selectionEnd;
    const text = messageInput.value;
    
    // Inserir variável na posição do cursor
    const newText = text.substring(0, startPos) + variable + text.substring(endPos);
    messageInput.value = newText;
    
    // Reposicionar cursor após a variável inserida
    const newPos = startPos + variable.length;
    messageInput.setSelectionRange(newPos, newPos);
    messageInput.focus();
    
    // Ajustar altura do textarea
    messageInput.style.height = 'auto';
    messageInput.style.height = Math.min(messageInput.scrollHeight, 150) + 'px';
    
    // Mostrar feedback visual
    const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                       document.body.classList.contains('dark-mode') ||
                       window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    Swal.fire({
        icon: 'success',
        title: 'Variável inserida!',
        text: `Variável ${variable} inserida no campo de mensagem`,
        timer: 1500,
        showConfirmButton: false,
        colorScheme: isDarkMode ? 'dark' : 'light',
        customClass: {
            popup: isDarkMode ? 'swal2-dark' : '',
            title: isDarkMode ? 'text-white' : '',
            htmlContainer: isDarkMode ? 'text-white' : ''
        }
    });
    
    // Fechar modal após inserir
    const modal = bootstrap.Modal.getInstance(document.getElementById('kt_modal_variables'));
    if (modal) {
        setTimeout(() => modal.hide(), 500);
    }
}

// Função copyVariable mantida para compatibilidade, mas insertVariable é preferida
function copyVariable(variable) {
    insertVariable(variable);
}

// Verificar se conversa está com agente de IA
async function checkIfConversationHasAI(conversationId) {
    try {
        const response = await fetch(`<?= \App\Helpers\Url::to('/conversations') ?>/${conversationId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        const data = await response.json();
        if (data.success && data.conversation) {
            // Verificar se existe conversa de IA ativa
            return data.conversation.has_ai_agent === true || data.conversation.ai_agent_id !== null;
        }
        return false;
    } catch (error) {
        console.error('Erro ao verificar agente de IA:', error);
        return false;
    }
}

// Escalar conversa de IA para humano
function escalateFromAI(conversationId) {
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_escalate'));
    document.getElementById('escalateConversationId').value = conversationId;
    document.getElementById('escalateAgent').value = '';
    modal.show();
}

// Modal de Atribuição
function assignConversation(conversationId) {
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_assign'));
    modal.show();
    
    // Resetar formulário
    document.getElementById('assignForm').reset();
    
    // Salvar ID da conversa no formulário
    document.getElementById('assignForm').dataset.conversationId = conversationId;
}

// Submeter atribuição
document.getElementById('assignForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const conversationId = this.dataset.conversationId || parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
    const agentId = document.getElementById('assignAgent').value;
    const departmentId = document.getElementById('assignDepartment').value;
    
    if (!agentId) {
        alert('Selecione um agente');
        return;
    }
    
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Atribuindo...';
    
    fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${conversationId}/assign`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            agent_id: parseInt(agentId),
            department_id: departmentId ? parseInt(departmentId) : null
        })
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                console.error('Erro na resposta:', text);
                throw new Error(`HTTP ${response.status}: ${text.substring(0, 100)}`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('kt_modal_assign')).hide();
            
            // Recarregar conversa para atualizar dados
            if (currentConversationId == conversationId) {
                // Recarregar conversa completa (inclui agentes do contato)
                selectConversation(conversationId);
            } else {
            window.location.reload();
            }
        } else {
            alert('Erro ao atribuir conversa: ' + (data.message || 'Erro desconhecido'));
            btn.disabled = false;
            btn.innerHTML = 'Atribuir';
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao atribuir conversa');
        btn.disabled = false;
        btn.innerHTML = 'Atribuir';
    });
});

// Submeter mudança de setor
document.getElementById('changeDepartmentForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const conversationId = document.getElementById('changeDepartmentConversationId').value || 
                          window.currentConversationId || 
                          parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
    const departmentId = document.getElementById('changeDepartmentSelect').value || null;
    
    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
    
    const formData = new FormData();
    if (departmentId) {
        formData.append('department_id', departmentId);
    } else {
        formData.append('department_id', '');
    }
    
    fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${conversationId}/update-department`, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('kt_modal_change_department')).hide();
            
            const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                               document.body.classList.contains('dark-mode') ||
                               window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: data.message || 'Setor atualizado com sucesso',
                colorScheme: isDarkMode ? 'dark' : 'light',
                timer: 2000,
                showConfirmButton: false
            });
            
            // Recarregar detalhes da conversa
            if (window.currentConversationId) {
                if (typeof loadConversationDetails === 'function') {
                    loadConversationDetails(window.currentConversationId);
                }
            }
            
            // Recarregar lista de conversas (preservando filtros)
            const urlParams = new URLSearchParams(window.location.search);
            if (typeof refreshConversationList === 'function') {
                refreshConversationList(urlParams);
            } else {
                window.location.reload();
            }
        } else {
            throw new Error(data.message || 'Erro ao atualizar setor');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                           document.body.classList.contains('dark-mode') ||
                           window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: error.message || 'Erro ao atualizar setor',
            colorScheme: isDarkMode ? 'dark' : 'light'
        });
        
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});

// Modal de Tags
function manageTags(conversationId) {
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_tags'));
    modal.show();
    
    // Salvar ID da conversa
    document.getElementById('kt_modal_tags').dataset.conversationId = conversationId || parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
    
    // Carregar tags
    loadTagsForConversation();
}

function loadTagsForConversation() {
    const conversationId = document.getElementById('kt_modal_tags').dataset.conversationId;
    
    // Carregar tags da conversa
    fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${conversationId}/tags`)
        .then(response => response.json())
        .then(data => {
            // Iniciar seleção com as tags atuais
            selectedTags = data.success && data.tags ? [...data.tags] : [];
            updateSelectedTagsDisplay();
        })
        .catch(error => {
            console.error('Erro ao carregar tags da conversa:', error);
            selectedTags = [];
            updateSelectedTagsDisplay();
        });
    
    // Carregar todas as tags disponíveis
    fetch('<?= \App\Helpers\Url::to("/tags/all") ?>')
        .then(response => response.json())
        .then(data => {
            const availableTagsDiv = document.getElementById('availableTags');
            
            if (!data.success || !data.tags || data.tags.length === 0) {
                availableTagsDiv.innerHTML = '<div class="text-muted">Nenhuma tag disponível</div>';
                return;
            }
            
            let html = '';
            data.tags.forEach(tag => {
                html += `
                    <span class="badge badge-lg" style="background-color: ${tag.color}20; color: ${tag.color}; cursor: pointer;" 
                          onclick="addTagToList(${tag.id}, '${escapeHtml(tag.name)}', '${tag.color}')" title="Clique para adicionar">
                        ${escapeHtml(tag.name)}
                        <i class="ki-duotone ki-plus fs-7 ms-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                    </span>
                `;
            });
            
            availableTagsDiv.innerHTML = html;
            
            // Busca de tags
            document.getElementById('tagSearch').addEventListener('input', function(e) {
                const search = e.target.value.toLowerCase();
                const tags = availableTagsDiv.querySelectorAll('span');
                tags.forEach(tag => {
                    const text = tag.textContent.toLowerCase();
                    tag.style.display = text.includes(search) ? '' : 'none';
                });
            });
        })
        .catch(error => {
            console.error('Erro ao carregar tags:', error);
            document.getElementById('availableTags').innerHTML = '<div class="text-danger">Erro ao carregar tags</div>';
        });
}

let selectedTags = [];

function addTagToList(tagId, tagName, tagColor) {
    if (selectedTags.find(t => t.id === tagId)) {
        return; // Já está selecionada
    }
    
    selectedTags.push({ id: tagId, name: tagName, color: tagColor });
    updateSelectedTagsDisplay();
}

function removeTagFromList(tagId, tagName) {
    selectedTags = selectedTags.filter(t => t.id !== tagId);
    updateSelectedTagsDisplay();
}

function updateSelectedTagsDisplay() {
    const currentTagsDiv = document.getElementById('currentTags');
    const uniqueTags = selectedTags.filter((tag, index, self) =>
        index === self.findIndex(t => t.id === tag.id)
    );
    
    if (uniqueTags.length > 0) {
        let html = '<div class="w-100 mb-2"><strong>Tags Selecionadas:</strong></div>';
        uniqueTags.forEach(tag => {
            html += `
                <span class="badge badge-lg" 
                      style="background-color: ${tag.color}20; color: ${tag.color}; cursor: pointer;" 
                      onclick="removeTagFromList(${tag.id}, '${escapeHtml(tag.name)}')" title="Clique para remover">
                    ${escapeHtml(tag.name)}
                    <i class="ki-duotone ki-cross fs-7 ms-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </span>
            `;
        });
        currentTagsDiv.innerHTML = html;
    } else {
        currentTagsDiv.innerHTML = '<div class="w-100 text-muted">Nenhuma tag selecionada</div>';
    }
}

function saveTags() {
    const conversationId = document.getElementById('kt_modal_tags').dataset.conversationId;
    
    // Carregar tags atuais para comparar
    fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${conversationId}/tags`)
        .then(response => response.json())
        .then(data => {
            const currentTagIds = data.success && data.tags ? data.tags.map(t => t.id) : [];
            const selectedTagIds = selectedTags.map(t => t.id);
            
            // Tags para adicionar (estão em selectedTags mas não nas atuais)
            const toAdd = selectedTagIds.filter(id => !currentTagIds.includes(id));
            
            // Tags para remover (estão nas atuais mas não em selectedTags)
            const toRemove = currentTagIds.filter(id => !selectedTagIds.includes(id));
            
            // Executar operações
            const promises = [
                ...toAdd.map(tagId => 
                    fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${conversationId}/tags`, {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ tag_id: tagId })
                    })
                ),
                ...toRemove.map(tagId => 
                    fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${conversationId}/tags/remove`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ tag_id: tagId })
                    })
                )
            ];
            
            Promise.all(promises)
                .then(() => {
                    // Buscar tags atualizadas para refletir no sidebar
                    return fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${conversationId}/tags`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    }).then(r => r.json());
                })
                .then(updated => {
                    const tags = updated.success && updated.tags ? updated.tags : [];
                    
                    // Fechar modal
                    bootstrap.Modal.getInstance(document.getElementById('kt_modal_tags')).hide();
                    selectedTags = [];
                    
                    // Atualizar sidebar (tags) sem refresh
                    if (typeof updateConversationSidebar === 'function') {
                        updateConversationSidebar({ id: conversationId }, tags);
                    }
                    
                    // Atualizar modelos locais em memória (selected/current conversation)
                    if (window.selectedConversation && window.selectedConversation.id == conversationId) {
                        window.selectedConversation.tags = tags;
                    }
                    if (window.currentConversation && window.currentConversation.id == conversationId) {
                        window.currentConversation.tags = tags;
                    }
                    
                    // Atualizar lista de conversas (tags nos itens) sem refresh
                    if (typeof refreshConversationList === 'function') {
                        const urlParams = new URLSearchParams(window.location.search);
                        // Cache buster para evitar lista cacheada
                        urlParams.set('_ts', Date.now().toString());
                        console.debug('[TAGS_DEBUG] saveTags -> refreshConversationList', Object.fromEntries(urlParams.entries()));
                        refreshConversationList(urlParams);
                    } else {
                        // Fallback: atualizar item atual se existir
                        const item = document.querySelector(`[data-conversation-id="${conversationId}"]`);
                        if (item) {
                            const tagsContainer = item.querySelector('.conversation-item-tags');
                            if (tagsContainer) {
                                const tagsHtml = tags.slice(0, 2).map(t => 
                                    `<span class="badge badge-sm" style="background-color: ${t.color || '#009ef7'}20; color: ${t.color || '#009ef7'};">${escapeHtml(t.name)}</span>`
                                ).join('');
                                tagsContainer.innerHTML = tagsHtml || '';
                                console.debug('[TAGS_DEBUG] saveTags fallback updated item', { conversationId, tagsCount: tags.length });
                            }
                        }
                    }
                    
                    // Atualizar listagem na UI (opcional): recarregar participantes/modal
                    loadTagsForConversation();
                    
                    // Sucesso visual
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Tags atualizadas',
                            timer: 1400,
                            showConfirmButton: false
                        });
                    } else {
                        alert('Tags atualizadas com sucesso');
                    }
                })
                .catch(error => {
                    console.error('Erro ao salvar tags:', error);
                    alert('Erro ao salvar tags');
                });
        });
}

// Função helper para escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Modal de Participantes
function showAddParticipantModal(conversationId) {
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_participants'));
    modal.show();
    
    // Salvar ID da conversa
    document.getElementById('kt_modal_participants').dataset.conversationId = conversationId || window.currentConversationId || parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
    window.currentConversationId = document.getElementById('kt_modal_participants').dataset.conversationId || window.currentConversationId;
    
    // Carregar participantes e usuários disponíveis
    loadParticipantsForConversation(window.currentConversationId);
}

function loadParticipantsForConversation(conversationIdParam = null) {
    const modalElement = document.getElementById('kt_modal_participants');
    const conversationId = conversationIdParam || (modalElement ? modalElement.dataset.conversationId : null) || window.currentConversationId;
    
    if (!conversationId || conversationId === 'undefined' || conversationId === 'null') {
        console.error('loadParticipantsForConversation: conversationId inválido:', conversationId);
        return;
    }
    
    // Garantir que o dataset fique sincronizado para as próximas chamadas
    if (modalElement) {
        modalElement.dataset.conversationId = conversationId;
    }

    console.log('loadParticipantsForConversation: conversationId =', conversationId);
    
    // Carregar participantes atuais
    fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${conversationId}/participants`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
        .then(response => {
            // Primeiro, pegar o texto da resposta
            return response.text().then(text => {
                console.log('=== RESPOSTA BRUTA (getParticipants) ===');
                console.log('Status:', response.status);
                console.log('Primeiros 300 chars:', text.substring(0, 300));
                
                if (!response.ok) {
                    console.error('❌ Resposta com erro:', text);
                    throw new Error(`HTTP ${response.status}`);
                }
                
                // Tentar fazer parse do JSON
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('❌ Erro ao fazer parse do JSON:', e);
                    console.error('Texto completo:', text);
                    throw new Error('Resposta não é JSON válido');
                }
            });
        })
        .then(data => {
            const currentParticipantsDiv = document.getElementById('currentParticipants');
            
            if (data.success && data.participants && data.participants.length > 0) {
                let html = '<div class="w-100 mb-3"><strong>Participantes Atuais:</strong></div>';
                data.participants.forEach(p => {
                    const initials = (p.user_name || 'U').charAt(0).toUpperCase();
                    html += `
                        <div class="d-flex align-items-center gap-2 p-2 border rounded mb-2" style="background: var(--bs-gray-100);">
                            <div class="symbol symbol-30px symbol-circle">
                                <div class="symbol-label bg-light-primary text-primary fw-bold fs-7">
                                    ${initials}
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold fs-7">${escapeHtml(p.user_name || 'Usuário')}</div>
                                ${p.user_email ? `<div class="text-muted fs-8">${escapeHtml(p.user_email)}</div>` : ''}
                            </div>
                            <button type="button" class="btn btn-sm btn-icon btn-light-danger p-0" 
                                    onclick="removeParticipant(${conversationId}, ${p.user_id})" 
                                    title="Remover participante">
                                <i class="ki-duotone ki-cross fs-7">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </button>
                        </div>
                    `;
                });
                currentParticipantsDiv.innerHTML = html;
            } else {
                currentParticipantsDiv.innerHTML = '<div class="w-100 text-muted">Nenhum participante adicional</div>';
            }
        })
        .catch(error => {
            console.error('Erro ao carregar participantes:', error);
            const currentParticipantsDiv = document.getElementById('currentParticipants');
            if (currentParticipantsDiv) {
                currentParticipantsDiv.innerHTML = '<div class="text-danger">Erro ao carregar participantes</div>';
            }
        });
    
    // Carregar usuários disponíveis (agentes)
    fetch('<?= \App\Helpers\Url::to("/agents") ?>?format=json', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('participantUserSelect');
            
            if (!data.success || !data.agents || data.agents.length === 0) {
                if (select) {
                    select.innerHTML = '<option value="">Nenhum usuário disponível</option>';
                }
                return;
            }
            
            if (select) {
                select.innerHTML = '<option value="">Selecione um usuário...</option>';
                data.agents.forEach(user => {
                    const emailLabel = user.email ? ' (' + escapeHtml(user.email) + ')' : '';
                    select.innerHTML += `<option value="${user.id}">${escapeHtml(user.name || user.email || 'Usuário')}${emailLabel}</option>`;
                });
            }
        })
        .catch(error => {
            console.error('Erro ao carregar usuários:', error);
            const select = document.getElementById('participantUserSelect');
            if (select) {
                select.innerHTML = '<option value="">Erro ao carregar usuários</option>';
            }
        });
}

function removeParticipant(conversationId, userId) {
    if (!confirm('Tem certeza que deseja remover este participante?')) {
        return;
    }
    
    fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${conversationId}/participants/${userId}`, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        // Primeiro, pegar o texto da resposta
        return response.text().then(text => {
            console.log('=== RESPOSTA BRUTA (removeParticipant) ===');
            console.log('Status:', response.status);
            console.log('Content-Type:', response.headers.get('Content-Type'));
            console.log('Primeiros 500 chars:', text.substring(0, 500));
            
            if (!response.ok) {
                console.error('❌ Resposta com erro:', text);
                throw new Error(`HTTP ${response.status}: ${text.substring(0, 100)}`);
            }
            
            // Tentar fazer parse do JSON
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('❌ Erro ao fazer parse do JSON:', e);
                console.error('Texto completo da resposta:', text);
                throw new Error('Resposta não é JSON válido: ' + text.substring(0, 200));
            }
        });
    })
    .then(data => {
        if (data.success) {
            // Recarregar participantes
            loadParticipantsForConversation(conversationId);
            // Recarregar a conversa inteira para atualizar sidebar
            if (window.currentConversationId == conversationId) {
                if (typeof loadConversationDetails === 'function') {
                    loadConversationDetails(conversationId);
                } else if (typeof loadConversation === 'function') {
                    loadConversation(conversationId);
                }
            }
            // Atualizar apenas a seção de participantes do sidebar, se existir (sempre tenta)
            if (typeof updateConversationSidebar === 'function') {
                updateConversationSidebar({ id: conversationId }, []);
            }
            // Sucesso visual
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Participante removido',
                    timer: 1400,
                    showConfirmButton: false
                });
            } else {
                alert('Participante removido com sucesso');
            }
        } else {
            alert('Erro ao remover participante: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro ao remover participante:', error);
        alert('Erro ao remover participante: ' + error.message);
    });
}

function addParticipant() {
    const modalElement = document.getElementById('kt_modal_participants');
    const conversationId = modalElement.dataset.conversationId || window.currentConversationId;
    const userId = document.getElementById('participantUserSelect').value;
    
    if (!conversationId || conversationId === 'undefined' || conversationId === 'null') {
        alert('ID da conversa não encontrado');
        return;
    }
    
    if (!userId) {
        alert('Por favor, selecione um usuário');
        return;
    }
    
    const btn = document.getElementById('addParticipantBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Adicionando...';
    
    fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${conversationId}/participants`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ user_id: userId })
    })
    .then(response => {
        // Primeiro, pegar o texto da resposta
        return response.text().then(text => {
            console.log('=== RESPOSTA BRUTA (addParticipant) ===');
            console.log('Status:', response.status);
            console.log('Content-Type:', response.headers.get('Content-Type'));
            console.log('Primeiros 500 chars:', text.substring(0, 500));
            
            if (!response.ok) {
                console.error('❌ Resposta com erro:', text);
                throw new Error(`HTTP ${response.status}: ${text.substring(0, 100)}`);
            }
            
            // Tentar fazer parse do JSON
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('❌ Erro ao fazer parse do JSON:', e);
                console.error('Texto completo da resposta:', text);
                throw new Error('Resposta não é JSON válido: ' + text.substring(0, 200));
            }
        });
    })
    .then(data => {
        if (data.success) {
            // Limpar select
            document.getElementById('participantUserSelect').value = '';
            // Recarregar participantes
            loadParticipantsForConversation(conversationId);
            // Recarregar a conversa inteira para atualizar sidebar
            if (window.currentConversationId == conversationId) {
                if (typeof loadConversationDetails === 'function') {
                    loadConversationDetails(conversationId);
                } else if (typeof loadConversation === 'function') {
                    loadConversation(conversationId);
                }
            }
            // Atualizar apenas a seção de participantes do sidebar, se existir (sempre tenta)
            if (typeof updateConversationSidebar === 'function') {
                updateConversationSidebar({ id: conversationId }, []);
            }
            // Sucesso visual
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Participante adicionado',
                    timer: 1400,
                    showConfirmButton: false
                });
            } else {
                alert('Participante adicionado com sucesso');
            }
            
            // Toast de sucesso
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Participante adicionado!',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
                });
            }
        } else {
            alert('Erro ao adicionar participante: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro ao adicionar participante:', error);
        alert('Erro ao adicionar participante: ' + error.message);
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = 'Adicionar';
    });
}

// Upload de arquivo
function attachFile() {
    const input = document.createElement('input');
    input.type = 'file';
    input.multiple = true;
    input.accept = 'image/*,video/*,audio/*,.pdf,.doc,.docx,.txt';
    
    input.onchange = function(e) {
        const files = Array.from(e.target.files);
        files.forEach(file => {
            uploadFile(file);
        });
    };
    
    input.click();
}

function uploadFile(file) {
    // Usar variável JavaScript global que é atualizada dinamicamente
    const conversationId = currentConversationId || parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
    
    if (!conversationId) {
        alert('Selecione uma conversa primeiro');
        return;
    }

    const uploadId = 'upload_' + Date.now();
    const chatMessages = document.getElementById('chatMessages');
    const uploadDiv = document.createElement('div');
    uploadDiv.id = uploadId;
    uploadDiv.className = 'chat-message outgoing';
    
    // Construir HTML inicial
    let initialHtml = '<div class="message-content">';
    initialHtml += '<div class="message-bubble">';
    initialHtml += '<div class="d-flex align-items-center gap-2">';
    initialHtml += '<i class="ki-duotone ki-file-up fs-3">';
    initialHtml += '<span class="path1"></span><span class="path2"></span><span class="path3"></span>';
    initialHtml += '</i>';
    initialHtml += '<div class="flex-grow-1">';
    initialHtml += '<div class="fw-semibold">' + escapeHtml(file.name) + '</div>';
    initialHtml += '<div class="progress progress-sm mt-2">';
    initialHtml += '<div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>';
    initialHtml += '</div>'; // progress
    initialHtml += '</div>'; // flex-grow-1
    initialHtml += '</div>'; // d-flex
    initialHtml += '</div>'; // message-bubble
    initialHtml += '</div>'; // message-content
    
    uploadDiv.innerHTML = initialHtml;
    chatMessages.appendChild(uploadDiv);

    if (chatMessages && chatMessages.scrollHeight !== undefined) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    const maxSize = 10 * 1024 * 1024;
    if (file.size > maxSize) {
        uploadDiv.innerHTML = '<div class="message-content"><div class="message-bubble text-danger">Arquivo muito grande. Tamanho máximo: 10MB</div></div>';
        return;
    }

    const allowedTypes = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
        'video/mp4', 'video/webm', 'video/ogg',
        'audio/mp3', 'audio/wav', 'audio/ogg',
        'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain', 'text/csv'
    ];

    const isAllowed = allowedTypes.includes(file.type) ||
        /\.(jpg|jpeg|png|gif|webp|mp4|webm|ogg|mp3|wav|pdf|doc|docx|xls|xlsx|txt|csv)$/i.test(file.name);

    if (!isAllowed) {
        uploadDiv.innerHTML = '<div class="message-content"><div class="message-bubble text-danger">Tipo de arquivo não permitido</div></div>';
        return;
    }

    const formData = new FormData();
    formData.append('attachments[]', file);
    formData.append('content', '');

    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = uploadDiv.querySelector('.attachment-preview img');
            if (img) img.src = e.target.result;
        };
        reader.readAsDataURL(file);
        
        // Atualizar HTML para imagem
        let imgHtml = '<div class="attachment-preview mb-2">';
        imgHtml += '<img src="" alt="' + escapeHtml(file.name) + '" style="max-width: 200px; max-height: 200px; border-radius: 8px; display: block;">';
        imgHtml += '</div>';
        imgHtml += '<div class="fw-semibold fs-7">' + escapeHtml(file.name) + '</div>';
        imgHtml += '<div class="progress progress-sm mt-2" style="height: 4px;">';
        imgHtml += '<div class="progress-bar progress-bar-striped progress-bar-animated" role="width: 0%"></div>';
        imgHtml += '</div>';
        
        const bubble = uploadDiv.querySelector('.message-bubble');
        if(bubble) bubble.innerHTML = imgHtml;
        
    } else {
        // Atualizar HTML para arquivo
        let fileHtml = '<div class="d-flex align-items-center gap-2">';
        fileHtml += '<i class="ki-duotone ki-file-up fs-3">';
        fileHtml += '<span class="path1"></span><span class="path2"></span><span class="path3"></span>';
        fileHtml += '</i>';
        fileHtml += '<div class="flex-grow-1">';
        fileHtml += '<div class="fw-semibold">' + escapeHtml(file.name) + '</div>';
        fileHtml += '<div class="text-muted fs-7">' + formatFileSize(file.size) + '</div>';
        fileHtml += '</div>';
        fileHtml += '</div>';
        fileHtml += '<div class="progress progress-sm mt-2" style="height: 4px;">';
        fileHtml += '<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>';
        fileHtml += '</div>';
        
        const bubble = uploadDiv.querySelector('.message-bubble');
        if(bubble) bubble.innerHTML = fileHtml;
    }

    const xhr = new XMLHttpRequest();

    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            const percentComplete = (e.loaded / e.total) * 100;
            const progressBar = uploadDiv.querySelector('.progress-bar');
            if (progressBar) {
                progressBar.style.width = percentComplete + '%';
            }
        }
    });

    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const data = JSON.parse(xhr.responseText);
                if (data.success && data.message) {
                    uploadDiv.remove();
                    addMessageToChat(data.message);
                    if (data.message.id) {
                        lastMessageId = Math.max(lastMessageId || 0, data.message.id);
                    }
                } else {
                    uploadDiv.innerHTML = '<div class="message-content"><div class="message-bubble text-danger">Erro: ' + escapeHtml(data.message || 'Erro desconhecido') + '</div></div>';
                }
            } catch (e) {
                console.error('Erro ao processar resposta:', e);
                uploadDiv.innerHTML = '<div class="message-content"><div class="message-bubble text-danger">Erro ao processar resposta do servidor</div></div>';
            }
        } else {
            uploadDiv.innerHTML = '<div class="message-content"><div class="message-bubble text-danger">Erro ao enviar arquivo (' + xhr.status + ')</div></div>';
        }
    };

    xhr.onerror = function() {
        uploadDiv.innerHTML = '<div class="message-content"><div class="message-bubble text-danger">Erro de conexão. Tente novamente.</div></div>';
    };

    xhr.open('POST', `<?= \App\Helpers\Url::to("/conversations") ?>/${conversationId}/messages`);
    xhr.send(formData);
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function renderAttachmentHtml(attachment) {
    if (!attachment) return '';
    
    const type = attachment.type || 'document';
    const mimeType = attachment.mime_type || attachment.mimetype || '';
    
    // Renderizar localização
    if (type === 'location' && attachment.latitude && attachment.longitude) {
        const lat = attachment.latitude;
        const lng = attachment.longitude;
        const name = escapeHtml(attachment.name || 'Localização');
        const address = escapeHtml(attachment.address || '');
        const mapsUrl = `https://www.google.com/maps?q=${lat},${lng}`;
        
        return `<div class="attachment-item mb-2">
            <a href="${mapsUrl}" target="_blank" class="d-flex align-items-center gap-2 p-2 border rounded" style="text-decoration: none; color: inherit; background: rgba(255,255,255,0.05);" onclick="event.stopPropagation();">
                <i class="ki-duotone ki-geolocation fs-2 text-danger">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="flex-grow-1">
                    <div class="fw-semibold">${name}</div>
                    ${address ? `<div class="text-muted fs-7">${address}</div>` : ''}
                    <div class="text-muted fs-7">${lat}, ${lng}</div>
                </div>
                <i class="ki-duotone ki-arrow-top-right fs-4 text-primary">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
            </a>
        </div>`;
    }
    
    // Construir URL correta do anexo
    let url = attachment.url || '';
    if (!url && attachment.path) {
        // Se não tem URL mas tem path, construir URL
        if (attachment.path.startsWith('http')) {
            url = attachment.path;
        } else {
            url = `<?= \App\Helpers\Url::to('/attachments') ?>/${encodeURIComponent(attachment.path)}`;
        }
    }
    const name = escapeHtml(attachment.original_name || attachment.name || attachment.filename || 'Anexo');
    const size = attachment.size ? formatFileSize(attachment.size) : '';
    
    let html = '<div class="attachment-item mb-2">';
    
    if (type === 'image') {
        // Placeholder base64 simples
        const placeholder = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgZmlsbD0iI2YwZjBmMCIvPjwvc3ZnPg==';
        html += `<a href="${url}" target="_blank" class="d-inline-block lazy-image-container" data-src="${url}" onclick="event.stopPropagation();" style="position: relative;">
            <img src="${placeholder}" alt="${name}" data-src="${url}" class="lazy-image" style="max-width: 300px; max-height: 300px; border-radius: 8px; cursor: pointer; background: #f0f0f0; min-width: 100px; min-height: 100px;">
            <div class="lazy-loading-spinner" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); display: none;"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></div>
        </a>`;
    } else if (type === 'video') {
        html += `<div class="lazy-video-container" data-src="${url}" data-type="${mimeType || 'video/mp4'}" onclick="event.stopPropagation();">
            <div class="lazy-video-placeholder" style="max-width: 300px; max-height: 200px; border-radius: 8px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; cursor: pointer; min-height: 200px;">
                <i class="ki-duotone ki-play fs-2x text-primary"><span class="path1"></span><span class="path2"></span></i>
            </div>
            <video controls style="max-width: 300px; max-height: 200px; border-radius: 8px; display: none;" preload="none">
                <source src="" type="${mimeType || 'video/mp4'}">
                Seu navegador não suporta vídeo.
            </video>
        </div>`;
    } else if (type === 'audio' || (mimeType && mimeType.startsWith('audio/'))) {
        // Renderização de áudio estilo WhatsApp com largura adequada
        // ✅ CORRIGIDO: Usar URL direta se começar com / (áudios TTS), senão usar rota de attachments
        let audioUrl = url;
        if (!audioUrl && attachment.path) {
            if (attachment.path.startsWith('assets/') || attachment.path.startsWith('/assets/')) {
                // Arquivo público (TTS, etc) - acesso direto
                audioUrl = '<?= \App\Helpers\Url::to('/') ?>' + attachment.path.replace(/^\//, '');
            } else {
                // Arquivo de upload - usar rota de attachments
                audioUrl = `<?= \App\Helpers\Url::to('/attachments') ?>/${encodeURIComponent(attachment.path)}`;
            }
        }
        
        // ✅ NOVO: Verificar se há transcrição ou texto original (TTS)
        let transcriptionHtml = '';
        const showTranscription = <?= json_encode(\App\Services\ConversationSettingsService::getSettings()['audio_transcription']['show_transcription_in_chat'] ?? true) ?>;
        
        if (showTranscription) {
            // Verificar se é áudio TTS (tem texto original) ou áudio transcrito
            const ttsOriginalText = attachment.tts_original_text;
            const transcription = attachment.transcription;
            
            if (ttsOriginalText) {
                // Áudio gerado pela IA - exibir texto original
                const textContent = escapeHtml(ttsOriginalText);
                transcriptionHtml = `
                    <div class="audio-transcription mt-2" style="padding: 8px; background: rgba(52, 211, 153, 0.1); border-radius: 6px; border-left: 3px solid #34d399;">
                        <div class="d-flex align-items-center gap-1 mb-1">
                            <i class="ki-duotone ki-message-text-2 fs-7 text-success"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            <span class="text-success fs-8 fw-semibold">Conteúdo do áudio:</span>
                        </div>
                        <div class="fs-7" style="color: rgba(0,0,0,0.7);">${nl2br(textContent)}</div>
                    </div>
                `;
            } else if (transcription && transcription.text) {
                // Áudio do cliente - exibir transcrição
                const transcriptionText = escapeHtml(transcription.text);
                transcriptionHtml = `
                    <div class="audio-transcription mt-2" style="padding: 8px; background: rgba(0,0,0,0.05); border-radius: 6px; border-left: 3px solid #3b82f6;">
                        <div class="d-flex align-items-center gap-1 mb-1">
                            <i class="ki-duotone ki-text fs-7 text-muted"><span class="path1"></span><span class="path2"></span></i>
                            <span class="text-muted fs-8 fw-semibold">Transcrição:</span>
                        </div>
                        <div class="fs-7" style="color: rgba(0,0,0,0.7);">${nl2br(transcriptionText)}</div>
                    </div>
                `;
            }
        }
        
        html += `<div class="attachment audio-attachment">
            <div class="d-flex align-items-center gap-2">
                <div class="me-1" style="flex-shrink: 0;">
                    <i class="ki-duotone ki-music fs-4 text-primary" style="min-width: 20px; font-size: 18px !important;">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
                <div class="flex-grow-1" style="min-width: 300px;">
                    <audio controls style="width: 100%; outline: none;" preload="metadata" onclick="event.stopPropagation();">
                        <source src="${audioUrl}" type="${mimeType || 'audio/ogg; codecs=opus'}">
                        Seu navegador não suporta o elemento de áudio.
                    </audio>
                    ${transcriptionHtml}
                </div>
            </div>
        </div>`;
    } else {
        // Para documentos, usar URL direta se o arquivo estiver em assets/
        const attachmentPath = attachment.path || '';
        let downloadUrl;
        if (attachmentPath.startsWith('assets/')) {
            // Caminho direto para arquivo público
            downloadUrl = `<?= \App\Helpers\Url::to('/') ?>${attachmentPath}`;
        } else {
            // Rota de download para arquivos fora de assets/
            downloadUrl = `<?= \App\Helpers\Url::to('/attachments') ?>/${encodeURIComponent(attachmentPath)}/download`;
        }
        html += `<a href="${downloadUrl}" target="_blank" class="d-flex align-items-center gap-2 p-2 border rounded" style="text-decoration: none; color: inherit; background: rgba(255,255,255,0.05);" onclick="event.stopPropagation();">
            <i class="ki-duotone ki-file fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <div class="flex-grow-1">
                <div class="fw-semibold">${name}</div>
                ${size ? `<div class="text-muted fs-7">${size}</div>` : ''}
            </div>
            <i class="ki-duotone ki-arrow-down fs-4 text-primary">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
        </a>`;
    }
    
    html += '</div>';
    return html;
}

// Emoji picker (placeholder melhorado)
function toggleEmoji() {
    // TODO: Implementar emoji picker completo
    const emojis = ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '😉', '😊', '😇', '🥰', '😍', '🤩', '😘', '😗', '😚', '😙', '😋', '😛', '😜', '🤪', '😝', '🤑', '🤗', '🤭', '🤫', '🤔', '🤐', '🤨', '😐', '😑', '😶', '😏', '😒', '🙄', '😬', '🤥', '😌', '😔', '😪', '🤤', '😴', '😷', '🤒', '🤕', '🤢', '🤮', '🤧', '🥵', '🥶', '😶‍🌫️', '😵', '😵‍💫', '🤯', '🤠', '🥳', '🥸', '😎', '🤓', '🧐', '😕', '😟', '🙁', '☹️', '😮', '😯', '😲', '😳', '🥺', '😦', '😧', '😨', '😰', '😥', '😢', '😭', '😱', '😖', '😣', '😞', '😓', '😩', '😫', '🥱', '😤', '😡', '😠', '🤬', '😈', '👿', '💀', '☠️', '💩', '🤡', '👹', '👺', '👻', '👽', '👾', '🤖', '😺', '😸', '😹', '😻', '😼', '😽', '🙀', '😿', '😾'];
    
    // Criar modal simples de emoji
    const emojiHtml = emojis.map(emoji => `<span class="btn btn-sm btn-icon btn-light m-1" onclick="insertEmoji('${emoji}')" style="font-size: 24px; cursor: pointer;">${emoji}</span>`).join('');
    
    Swal.fire({
        title: 'Selecione um emoji',
        html: `<div style="max-height: 400px; overflow-y: auto; text-align: center; padding: 20px;">${emojiHtml}</div>`,
        width: '600px',
        showConfirmButton: false,
        showCloseButton: true
    });
}

function insertEmoji(emoji) {
    const input = document.getElementById('messageInput');
    const cursorPos = input.selectionStart;
    const textBefore = input.value.substring(0, cursorPos);
    const textAfter = input.value.substring(cursorPos);
    
    input.value = textBefore + emoji + textAfter;
    input.focus();
    input.setSelectionRange(cursorPos + emoji.length, cursorPos + emoji.length);
    
    // Ajustar altura
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 150) + 'px';
    
    Swal.close();
}

// Toggle sidebar de detalhes
function toggleConversationSidebar() {
    const sidebar = document.getElementById('conversationSidebar');
    if (sidebar) {
        sidebar.classList.toggle('open');
        localStorage.setItem('conversationSidebarOpen', sidebar.classList.contains('open'));
    }
}

// Sistema de Polling já declarado acima (antes de selectConversation)

// WebSocket - Atualizar em tempo real
if (typeof window.wsClient !== 'undefined') {
    window.wsClient.on('new_message', (data) => {
        console.log('Handler new_message acionado:', data);
        const currentConversationId = window.currentConversationId ?? parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
        
        // Disparar evento personalizado para o sistema de SLA
        console.log('[WebSocket/Poll] Disparando evento realtime:new_message para SLA');
        document.dispatchEvent(new CustomEvent('realtime:new_message', { detail: data }));
        
        // Atualizar lista de conversas
        const conversationItem = document.querySelector(`[data-conversation-id="${data.conversation_id}"]`);
        if (conversationItem) {
            const preview = conversationItem.querySelector('.conversation-item-preview');
            const time = conversationItem.querySelector('.conversation-item-time');
            const badge = conversationItem.querySelector('.conversation-item-badge');
            const pinned = conversationItem.classList.contains('pinned');
            
            if (preview) {
                const content = data.message.content || '';
                const maxChars = 37;
                preview.textContent = content.substring(0, maxChars) + (content.length > maxChars ? '...' : '');
            }
            if (time) {
                const ts = data.message.created_at || new Date().toISOString();
                time.textContent = formatTime(ts);
                conversationItem.setAttribute('data-updated-at', ts);
            }
            
            // Atualizar badge de não lidas (se não for a conversa atual)
            if (currentConversationId != data.conversation_id) {
                const currentCount = badge ? parseInt(badge.textContent) || 0 : 0;
                if (badge) {
                    badge.textContent = currentCount + 1;
                } else {
                    const badgeHtml = `<span class="conversation-item-badge">1</span>`;
                    const meta = conversationItem.querySelector('.conversation-item-meta');
                    if (meta) meta.insertAdjacentHTML('beforeend', badgeHtml);
                }
            }
            
            // Garantir dropdown de ações
            ensureActionsDropdown(conversationItem, pinned, data.conversation_id);
            
            // Atualizar indicador SLA em tempo real
            if (window.SLAIndicator && data.message) {
                // Atualizar dados relevantes para SLA
                if (data.message.sender_type === 'contact') {
                    conversationItem.dataset.lastContactMessageAt = data.message.created_at;
                } else if (data.message.sender_type === 'agent') {
                    conversationItem.dataset.lastAgentMessageAt = data.message.created_at;
                }
                // Forçar atualização do indicador
                const convData = window.SLAIndicator.getConversationData(data.conversation_id);
                if (convData) {
                    window.SLAIndicator.updateConversation(data.conversation_id, convData);
                }
            }

            // Reordenar lista após atualização (respeita timestamp e pinned)
            sortConversationList();
        } else {
            // Se não existe na lista, fazer refresh para forçar render (preservando filtros)
            console.log('new_message: conversa não encontrada na lista, atualizando lista');
            const urlParams = new URLSearchParams(window.location.search);
            refreshConversationList(urlParams);
        }
        
        // Se é a conversa atual, adicionar mensagem dinamicamente
        if (currentConversationId == data.conversation_id && data.message) {
            console.group('🔍 DEBUG: Nova mensagem via WebSocket/Polling');
            console.log('Dados completos:', data);
            console.table({
                'ID': data.message.id,
                'Conteúdo': data.message.content?.substring(0, 50),
                'sender_type': data.message.sender_type,
                'direction': data.message.direction,
                'message_type': data.message.message_type,
                'type': data.message.type
            });
            
            // Validação de campos críticos
            if (!data.message.direction) {
                console.error('❌ ERRO: Campo "direction" está AUSENTE!');
            } else if (data.message.direction === 'outgoing' && data.message.sender_type === 'contact') {
                console.error('❌ ERRO: Mensagem do contato (sender_type=contact) mas direction=outgoing (deveria ser incoming)');
            } else if (data.message.direction === 'incoming' && data.message.sender_type === 'contact') {
                console.log('✅ CORRETO: Mensagem do contato com direction=incoming');
            }
            
            console.groupEnd();
            addMessageToChat(data.message);
            
            // Remover badge se existir (mensagem já foi marcada como lida no backend)
            if (badge) badge.remove();
        } else {
            // Se não é a conversa atual, atualizar lista completa após um delay para garantir sincronização
            setTimeout(() => {
                refreshConversationBadges();
            }, 1000);
        }
    });
    
    // Atualizar status de mensagem via WebSocket
    window.wsClient.on('message_status_updated', (data) => {
        const messageElement = document.querySelector(`[data-message-id="${data.message_id}"]`);
        if (messageElement) {
            const statusElement = messageElement.querySelector('.message-status');
            if (statusElement) {
                // Buscar mensagem atualizada do servidor
                const convId = parseInt(data.conversation_id);
                const msgId = parseInt(data.message_id) || 0;
                if (!isNaN(convId)) {
                    fetch(`<?= \App\Helpers\Url::to('/conversations') ?>/${convId}?last_message_id=${msgId}`)
                        .then(res => res.json())
                        .then(result => {
                            if (result.success && result.conversation && result.conversation.messages) {
                                const updatedMessage = result.conversation.messages.find(m => m.id == data.message_id);
                                if (updatedMessage) {
                                    // Atualizar status
                                    const newStatusHtml = renderMessageStatusHtml(updatedMessage);
                                    if (newStatusHtml) {
                                        statusElement.outerHTML = newStatusHtml;
                                    }
                                }
                            }
                        })
                        .catch(err => console.error('Erro ao atualizar status:', err));
                }
            }
        }
    });
    
    // Handler para conversa atualizada (marca como lida, etc)
    window.wsClient.on('conversation_updated', (data) => {
        if (data && data.conversation_id) {
            // Disparar evento personalizado para o sistema de SLA
            document.dispatchEvent(new CustomEvent('realtime:conversation_updated', { detail: data }));
            
            // Atualiza badge/preview/tempo
            applyConversationUpdate(data.conversation || { id: data.conversation_id, unread_count: data.unread_count });
            // Move para topo se não for a conversa atual (feito no handler abaixo)
        }
    });

    // Handler para novas conversas criadas
    window.wsClient.on('new_conversation', (data) => {
        console.log('Nova conversa recebida (WS/Poll):', data);
        
        // Disparar evento personalizado para o sistema de SLA
        document.dispatchEvent(new CustomEvent('realtime:new_conversation', { detail: data }));
        
        try {
            // Adicionar nova conversa à lista sem recarregar a página
            if (data.conversation) {
                addConversationToList(data.conversation);
            } else {
                console.warn('new_conversation sem campo conversation', data);
            }
        } catch (err) {
            console.error('Erro ao adicionar nova conversa na lista:', err);
            // Fallback: recarregar lista por AJAX (preservando filtros)
            const urlParams = new URLSearchParams(window.location.search);
            refreshConversationList(urlParams);
        }
    });
    
    window.wsClient.on('conversation_updated', (data) => {
        // Usar variável global para refletir a conversa selecionada após navegação AJAX
        const currentConversationId = window.currentConversationId ?? parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
        
        // Se é a conversa atual, não atualizar badge (já foi removido ao selecionar)
        if (currentConversationId == data.conversation_id) {
            // Recarregar apenas se necessário (mudanças de status, atribuição)
            if (data.changes && (data.changes.status || data.changes.agent_id || data.changes.department_id)) {
                window.location.reload();
            }
            return; // Não atualizar badge se for a conversa atual
        }
        
        // Atualizar item e mover para topo (lista refletindo última atividade)
        // Se a conversa ainda não existe na lista (ex.: criada agora), criar e adicionar
        const existingItem = document.querySelector(`[data-conversation-id="${data.conversation_id}"]`);
        if (!existingItem) {
            if (data.conversation) {
                addConversationToList(data.conversation);
            } else {
                // Dados mínimos para criar
                addConversationToList({
                    id: data.conversation_id,
                    last_message: data.last_message || '',
                    last_message_at: data.updated_at || new Date().toISOString(),
                    updated_at: data.updated_at || new Date().toISOString(),
                    contact_name: data.contact_name || 'Contato',
                    channel: data.channel || 'whatsapp',
                    unread_count: data.unread_count || 0,
                    tags_data: null,
                    pinned: 0
                });
            }
        } else {
            applyConversationUpdate(data.conversation || { id: data.conversation_id, unread_count: data.unread_count });
            // moveConversationToTop removido - applyConversationUpdate já ordena via sortConversationList()
        }
    });
    
    // Inscrever na conversa atual
const currentConversationId = parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
    if (currentConversationId) {
        // Se a conversa foi aberta diretamente por URL, remover badge na lista
        removeConversationBadge(currentConversationId);

        if (window.wsClient.connected && window.wsClient.currentMode === 'websocket') {
            window.wsClient.subscribe(currentConversationId);
            stopPolling(); // Parar polling apenas se WebSocket estiver conectado
        } else {
            // Se WebSocket não estiver conectado, usar polling
            startPolling(currentConversationId);
        }
    }
    // Inscrever todas as conversas visíveis (modo polling)
    subscribeVisibleConversations();
    
    // Sistema de atualização periódica da lista de conversas (para badges de não lidas)
    // Atualizar a cada 10 segundos para verificar novas mensagens em todas as conversas
    let conversationListUpdateInterval = setInterval(() => {
        refreshConversationBadges();
    }, 10000);
    
    // Atualizar tempos relativos a cada 30 segundos
    let timeUpdateInterval = setInterval(() => {
        updateConversationTimes();
    }, 30000); // 30 segundos
    
    // Carregar funcionalidades do Assistente IA quando modal for aberto
    const aiAssistantModal = document.getElementById('kt_modal_ai_assistant');
    if (aiAssistantModal) {
        aiAssistantModal.addEventListener('show.bs.modal', function() {
            loadAIAssistantFeatures();
        });
    }
} else {
    // Se WebSocket não estiver disponível, usar polling
const currentConversationId = parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
    if (currentConversationId) {
        startPolling(currentConversationId);
    }
    
    // Sistema de atualização periódica da lista de conversas (para badges de não lidas)
    let conversationListUpdateInterval = setInterval(() => {
        refreshConversationBadges();
    }, 10000); // 10 segundos

    // Atualizar tempos relativos a cada 30 segundos (modo polling)
    let timeUpdateInterval = setInterval(() => {
        updateConversationTimes();
    }, 30000); // 30 segundos
}

// Fallback global (sempre ativo): ouvir evento disparado pelo RealtimeClient (polling)
if (!window.__realtimeGlobalNewConvListener) {
    window.__realtimeGlobalNewConvListener = true;
    window.addEventListener('realtime:new_conversation', (e) => {
        console.log('Nova conversa recebida (evento global):', e.detail);
        
        // Verificar se a conversa passa pelos filtros ativos antes de adicionar
        const urlParams = new URLSearchParams(window.location.search);
        
        // Se há filtros ativos, recarregar lista preservando filtros para incluir a nova conversa
        if (urlParams.toString().length > 0) {
            console.log('Filtros ativos detectados. Recarregando lista filtrada para incluir nova conversa.');
            refreshConversationList(urlParams);
            return;
        }
        
        try {
            addConversationToList(e.detail);
        } catch (err) {
            console.error('Erro ao adicionar nova conversa (evento global):', err);
            // Preservar filtros ao recarregar
            refreshConversationList(urlParams);
        }
    });
}

/**
 * Adicionar nova conversa à lista dinamicamente (sem recarregar tudo)
 */
function addConversationToList(conv) {
    const conversationsList = document.querySelector('.conversations-list-items');
    if (!conversationsList) {
        console.error('Elemento .conversations-list-items não encontrado!');
        return;
    }

    // Verificar se a conversa já existe na lista
    const existingItem = document.querySelector(`[data-conversation-id="${conv.id}"]`);
    if (existingItem) {
        // Se já existe, apenas atualizar (applyConversationUpdate já ordena via sortConversationList)
        applyConversationUpdate(conv);
        return;
    }

    // Verificar se há mensagem vazia ou estado de "sem conversas"
    const emptyState = conversationsList.querySelector('.text-center');
    if (emptyState) {
        conversationsList.innerHTML = '';
    }

    // Preparar dados da conversa
    const channelIcon = conv.channel === 'whatsapp' 
        ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="#25D366" style="vertical-align: middle;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>'
        : conv.channel === 'email' ? '✉️' : '💬';
    
    const channelName = conv.channel === 'whatsapp' ? 'WhatsApp' : (conv.channel === 'email' ? 'Email' : 'Chat');
    
    const urlParams = new URLSearchParams(window.location.search);
    const selectedConversationId = urlParams.get('id') ? parseInt(urlParams.get('id')) : null;
    const isActive = selectedConversationId == conv.id;
    
    const nameRaw = conv.contact_name || 'NN';
    const maxName = 22;
    const name = nameRaw.length > maxName ? nameRaw.substring(0, maxName) + '...' : nameRaw;
    const parts = nameRaw.split(' ');
    const initials = (parts[0].charAt(0) + (parts[1] ? parts[1].charAt(0) : '')).toUpperCase();
    
    const lastMessage = conv.last_message || '';
    const maxCharsPreview = 37;
    const lastMessagePreview = lastMessage.length > maxCharsPreview ? lastMessage.substring(0, maxCharsPreview) + '...' : lastMessage;
    
    const unreadCount = conv.unread_count || 0;
    const pinned = conv.pinned || 0;
    
    // Tags
    let tagsHtml = '';
    if (conv.tags_data) {
        const tags = conv.tags_data.split('|||');
        tags.slice(0, 2).forEach(tagStr => {
            const [tagId, tagName, tagColor] = tagStr.split(':');
            if (tagName) {
                tagsHtml += `<span class="badge badge-sm" style="background-color: ${tagColor || '#009ef7'}20; color: ${tagColor || '#009ef7'};">${escapeHtml(tagName)}</span>`;
            }
        });
    }
    
    // Calcular campos de SLA / datas
    const firstResponseAt = conv.first_response_at_calc || conv.first_response_at || '';
    const lastContactAt = conv.last_contact_message_at || '';
    const lastAgentAt = conv.last_agent_message_at || '';
    const createdAt = conv.created_at || '';
    const lastMessageAt = conv.last_message_at || conv.updated_at || '';
    const lastMessageFromAgent = lastAgentAt && (!lastContactAt || new Date(lastAgentAt) >= new Date(lastContactAt));
    
    // Criar HTML do item
    const avatarHtml = conv.contact_avatar
        ? `<div class="symbol-label"><img src="${escapeHtml(conv.contact_avatar)}" alt="${escapeHtml(name)}" class="h-45px w-45px rounded" style="object-fit: cover;"></div>`
        : `<div class="symbol-label bg-light-primary text-primary fw-bold">${initials}</div>`;

    const conversationHtml = `
        <div class="conversation-item ${isActive ? 'active' : ''} ${pinned ? 'pinned' : ''}" 
             data-conversation-id="${conv.id}"
             data-status="${escapeHtml(conv.status || 'open')}"
             data-created-at="${escapeHtml(createdAt)}"
             data-first-response-at="${escapeHtml(firstResponseAt)}"
             data-last-message-at="${escapeHtml(lastMessageAt)}"
             data-last-contact-message-at="${escapeHtml(lastContactAt)}"
             data-last-agent-message-at="${escapeHtml(lastAgentAt)}"
             data-agent-id="${escapeHtml(conv.agent_id || '')}"
             data-updated-at="${lastMessageAt || new Date().toISOString()}"
             data-onclick="selectConversation">
            <div class="d-flex gap-3 w-100">
                <!-- Checkbox para seleção em massa -->
                <div class="flex-shrink-0 d-flex align-items-center">
                    <label class="form-check form-check-custom form-check-solid">
                        <input class="form-check-input conversation-checkbox" type="checkbox" value="${conv.id}" 
                               onclick="event.stopPropagation(); toggleBulkSelection();">
                    </label>
                </div>
                <div class="symbol symbol-45px flex-shrink-0">
                    ${avatarHtml}
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="conversation-item-header">
                        <div class="conversation-item-name d-flex align-items-center gap-2">
                            ${pinned ? '<i class="ki-duotone ki-pin fs-7 text-warning" title="Fixada"><span class="path1"></span><span class="path2"></span></i>' : ''}
                            ${escapeHtml(name)}
                        </div>
                        <div class="conversation-item-time d-flex align-items-center gap-2">
                            ${formatTime(conv.last_message_at || conv.updated_at)}
                            <div class="dropdown conversation-item-actions">
                                <button type="button" class="btn btn-sm btn-icon btn-light p-0" 
                                        data-bs-toggle="dropdown" 
                                        aria-expanded="false"
                                        onclick="event.stopPropagation();">
                                    <i class="ki-duotone ki-setting-2 text-muted">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                        <span class="path5"></span>
                                    </i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" data-conversation-id="${conv.id}">
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="event.stopPropagation(); togglePin(${conv.id}, ${pinned ? 'true' : 'false'}); return false;">
                                            <i class="ki-duotone ki-pin fs-7 me-2 ${pinned ? 'text-warning' : ''}">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            ${pinned ? 'Desfixar' : 'Fixar'}
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="event.stopPropagation(); markConversationAsRead(${conv.id}); return false;">
                                            <i class="ki-duotone ki-check fs-7 me-2 text-success">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            Marcar como Lido
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="event.stopPropagation(); markConversationAsUnread(${conv.id}); return false;">
                                            <i class="ki-duotone ki-eye-slash fs-7 me-2 text-danger">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            Marcar como Não Lido
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="event.stopPropagation(); showReminderModal(${conv.id}); return false;">
                                            <i class="ki-duotone ki-notification-bing fs-7 me-2 text-primary">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            Agendar Lembrete
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="conversation-item-preview">${escapeHtml(lastMessagePreview || 'Sem mensagens')}</div>
                        <div class="conversation-item-meta">
                            <span class="conversation-item-channel">${channelIcon} ${channelName}</span>
                            <span class="conversation-item-tags d-flex gap-1 flex-wrap">${tagsHtml}</span>
                            ${unreadCount > 0 ? `<span class="conversation-item-badge">${unreadCount}</span>` : ''}
                        </div>
                </div>
            </div>
        </div>
    `;
    
    // Adicionar ao topo da lista
    conversationsList.insertAdjacentHTML('afterbegin', conversationHtml);
    console.log('Conversa adicionada ao topo:', conv.id);
    
    // Inscrever na nova conversa para receber atualizações
    if (typeof window.wsClient !== 'undefined') {
        if (window.wsClient.connected && window.wsClient.currentMode === 'websocket') {
            window.wsClient.subscribe(conv.id);
        }
    }
    
    // Resortear lista (respeitando pinned e updated_at)
    sortConversationList();
    
    console.log('Nova conversa adicionada à lista:', conv.id);
}

/**
 * Atualizar badges de não lidas nas conversas da lista (sem recarregar toda a lista)
 */
function refreshConversationBadges() {
    // Buscar lista atualizada de conversas - PRESERVAR TODOS OS FILTROS DA URL
    const urlParams = new URLSearchParams(window.location.search);
    
    // Criar params preservando TODOS os filtros da URL atual
    const params = new URLSearchParams();
    
    // Preservar todos os parâmetros da URL atual (incluindo arrays)
    urlParams.forEach((value, key) => {
        // Para arrays (channels[], tag_ids[], whatsapp_account_ids[]), adicionar cada valor
        if (key.endsWith('[]')) {
            // Se já existe, adicionar mais um valor
            params.append(key, value);
        } else {
            // Parâmetros simples
            params.set(key, value);
        }
    });
    
    // Garantir que filtros básicos também sejam preservados se não estiverem na URL
    const filters = {
        status: urlParams.get('status') || '',
        channel: urlParams.get('channel') || '',
        search: urlParams.get('search') || '',
        department_id: urlParams.get('department_id') || '',
        tag_id: urlParams.get('tag_id') || '',
        agent_id: urlParams.get('agent_id') || '',
        unanswered: urlParams.get('unanswered') || '',
        answered: urlParams.get('answered') || '',
        date_from: urlParams.get('date_from') || '',
        date_to: urlParams.get('date_to') || '',
        pinned: urlParams.get('pinned') || '',
        order_by: urlParams.get('order_by') || '',
        order_dir: urlParams.get('order_dir') || ''
    };
    
    // Adicionar filtros básicos apenas se não estiverem já nos params
    Object.keys(filters).forEach(key => {
        if (filters[key] && !params.has(key)) {
            params.append(key, filters[key]);
        }
    });
    
    // Adicionar format=json aos params
    params.append('format', 'json');
    
    fetch(`<?= \App\Helpers\Url::to('/conversations') ?>?${params.toString()}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            throw new Error('Resposta não é JSON (refreshConversationBadges)');
        }
        return response.json();
    })
    .then(data => {
        if (data.success && data.conversations) {
            // Obter IDs das conversas que devem estar na lista (segundo o filtro atual)
            const validConversationIds = new Set(data.conversations.map(c => c.id));
            
            // Remover conversas que NÃO passam pelo filtro atual
            const allConversationItems = document.querySelectorAll('.conversation-item[data-conversation-id]');
            allConversationItems.forEach(item => {
                const conversationId = parseInt(item.getAttribute('data-conversation-id'));
                const currentConversationId = window.currentConversationId ?? parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
                
                // Não remover a conversa atual
                if (conversationId === currentConversationId) {
                    return;
                }
                
                // Se a conversa não está na lista válida, removê-la
                if (!validConversationIds.has(conversationId)) {
                    item.remove();
                }
            });
            
            // Atualizar badges de não lidas em cada conversa da lista
            data.conversations.forEach(conv => {
                const conversationItem = document.querySelector(`[data-conversation-id="${conv.id}"]`);
                // Se a conversa passou pelo filtro, mas ainda não está renderizada, adicionar agora
                if (!conversationItem) {
                    addConversationToList(conv);
                    return;
                }
                if (conversationItem) {
                    const badge = conversationItem.querySelector('.conversation-item-badge');
                    const unreadCount = conv.unread_count || 0;
                    const currentConversationId = window.currentConversationId ?? parsePhpJson('<?= json_encode($selectedConversationId ?? null, JSON_HEX_APOS | JSON_HEX_QUOT) ?>');
                    
                    // Não atualizar badge se for a conversa atual (já está sendo gerenciada separadamente)
                    if (currentConversationId == conv.id) {
                        return;
                    }
                    
                    if (unreadCount > 0) {
                        if (badge) {
                            badge.textContent = unreadCount;
                        } else {
                            // Criar badge se não existir
                            const badgeHtml = `<span class="conversation-item-badge">${unreadCount}</span>`;
                            const meta = conversationItem.querySelector('.conversation-item-meta');
                            if (meta) {
                                meta.insertAdjacentHTML('beforeend', badgeHtml);
                            }
                        }
                        // A ordenação correta será feita por sortConversationList() abaixo
                    } else {
                        // Remover badge se não houver mensagens não lidas
                        if (badge) {
                            badge.remove();
                        }
                    }
                    
                    // Atualizar preview e tempo se necessário
                    if (conv.last_message) {
                        const preview = conversationItem.querySelector('.conversation-item-preview');
                        if (preview) {
                            const maxChars = 37;
                            const content = conv.last_message.length > maxChars
                                ? conv.last_message.substring(0, maxChars) + '...'
                                : conv.last_message;
                            preview.textContent = content;
                        }
                    }
                    
                    // Atualizar datasets de SLA e datas
                    if (conv.last_contact_message_at) {
                        conversationItem.dataset.lastContactMessageAt = conv.last_contact_message_at;
                    }
                    if (conv.last_agent_message_at) {
                        conversationItem.dataset.lastAgentMessageAt = conv.last_agent_message_at;
                    }
                    // Reaplicar estado visual de SLA (borda verde quando última msg é do agente)
                    applySlaVisualState(conversationItem, conv);
                    
                    // Atualizar meta e resortear
                    updateConversationMeta(conversationItem, conv);
                    // Garantir dropdown de ações após updates
                    ensureActionsDropdown(conversationItem, conv.pinned === 1 || conv.pinned === true, conv.id);
                    sortConversationList();
                }
            });

            // Atualizar todos os indicadores SLA após atualizar a lista
            if (window.SLAIndicator) {
                console.log('[refreshConversationBadges] Atualizando todos os indicadores SLA');
                window.SLAIndicator.updateAllIndicators();
            }

            // Reinscrever conversas visíveis para receber eventos de polling/new_message
            subscribeVisibleConversations();
        }
    })
    .catch(error => {
        // Silenciar erros de atualização (não crítico)
        console.debug('Erro ao atualizar lista de conversas:', error);
    });
}

/**
 * Assistente IA - Funções
 */

let aiAssistantFeatures = [];
let currentAIAgent = null;

function showAIAssistantModal() {
    // Verificar disponibilidade antes de abrir o modal
    checkAIAssistantAvailability().then(availability => {
        if (!availability.available) {
            // Mostrar erros de forma amigável
            const issues = availability.issues || [];
            
            if (issues.length > 0) {
                const mainIssue = issues[0];
                let message = `<strong>${escapeHtml(mainIssue.title)}</strong><br>${escapeHtml(mainIssue.message)}`;
                
                if (issues.length > 1) {
                    message += '<br><br><strong>Outros problemas encontrados:</strong><ul class="text-start mt-2">';
                    issues.slice(1).forEach(issue => {
                        message += `<li>${escapeHtml(issue.title)}: ${escapeHtml(issue.message)}</li>`;
                    });
                    message += '</ul>';
                }
                
                // Detectar tema dark
                const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                                  document.body.classList.contains('dark-mode') ||
                                  window.matchMedia('(prefers-color-scheme: dark)').matches;
                
                if (mainIssue.action_url) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Assistente IA não disponível',
                        html: message,
                        confirmButtonText: mainIssue.action === 'configure_api_key' ? 'Ir para Configurações' : 'OK',
                        showCancelButton: true,
                        cancelButtonText: 'Cancelar',
                        buttonsStyling: false,
                        colorScheme: isDarkMode ? 'dark' : 'light',
                        customClass: {
                            confirmButton: 'btn btn-primary',
                            cancelButton: 'btn btn-light',
                            popup: isDarkMode ? 'swal2-dark' : '',
                            title: isDarkMode ? 'text-white' : '',
                            htmlContainer: isDarkMode ? 'text-white' : ''
                        }
                    }).then((result) => {
                        if (result.isConfirmed && mainIssue.action_url) {
                            window.location.href = mainIssue.action_url;
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Assistente IA não disponível',
                        html: message,
                        confirmButtonText: 'OK',
                        buttonsStyling: false,
                        colorScheme: isDarkMode ? 'dark' : 'light',
                        customClass: {
                            confirmButton: 'btn btn-primary',
                            popup: isDarkMode ? 'swal2-dark' : '',
                            title: isDarkMode ? 'text-white' : '',
                            htmlContainer: isDarkMode ? 'text-white' : ''
                        }
                    });
                }
                return;
            }
        }
        
        // Se há warnings, mostrar mas continuar
        if (availability.warnings && availability.warnings.length > 0) {
            const warning = availability.warnings[0];
            const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                              document.body.classList.contains('dark-mode') ||
                              window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            Swal.fire({
                icon: 'warning',
                title: warning.title,
                text: warning.message,
                confirmButtonText: 'Continuar',
                showCancelButton: true,
                cancelButtonText: 'Cancelar',
                buttonsStyling: false,
                colorScheme: isDarkMode ? 'dark' : 'light',
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-light',
                    popup: isDarkMode ? 'swal2-dark' : '',
                    title: isDarkMode ? 'text-white' : '',
                    htmlContainer: isDarkMode ? 'text-white' : ''
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    openAIAssistantModal();
                }
            });
            return;
        }
        
        // Tudo OK, abrir modal
        openAIAssistantModal();
    }).catch(error => {
        console.error('Erro ao verificar disponibilidade:', error);
        const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                          document.body.classList.contains('dark-mode') ||
                          window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Não foi possível verificar a disponibilidade do Assistente IA. Tente novamente.',
            confirmButtonText: 'OK',
            buttonsStyling: false,
            colorScheme: isDarkMode ? 'dark' : 'light',
            customClass: {
                confirmButton: 'btn btn-primary',
                popup: isDarkMode ? 'swal2-dark' : '',
                title: isDarkMode ? 'text-white' : '',
                htmlContainer: isDarkMode ? 'text-white' : ''
            }
        });
    });
}

function openAIAssistantModal() {
    if (!currentConversationId) {
        const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                          document.body.classList.contains('dark-mode') ||
                          window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        Swal.fire({
            icon: 'info',
            title: 'Selecione uma conversa',
            text: 'Por favor, selecione uma conversa antes de usar o Assistente IA',
            confirmButtonText: 'OK',
            buttonsStyling: false,
            colorScheme: isDarkMode ? 'dark' : 'light',
            customClass: {
                confirmButton: 'btn btn-primary',
                popup: isDarkMode ? 'swal2-dark' : '',
                title: isDarkMode ? 'text-white' : '',
                htmlContainer: isDarkMode ? 'text-white' : ''
            }
        });
        return;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_ai_assistant'));
    modal.show();
    
    // Resetar estado
    const resultsDiv = document.getElementById('aiResponseResults');
    if (resultsDiv) {
        resultsDiv.classList.add('d-none');
    }
    
    // Carregar funcionalidades disponíveis
    loadAIAssistantFeatures();
}

function checkAIAssistantAvailability() {
    const featureKey = 'generate_response'; // Funcionalidade padrão
    const url = `<?= \App\Helpers\Url::to('/ai-assistant/check-availability') ?>?conversation_id=${currentConversationId || ''}&feature_key=${featureKey}`;
    
    return fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success && !data.available) {
            // Retornar dados mesmo se não disponível para mostrar erros
            return data;
        }
        return data;
    });
}

function loadAIAssistantFeatures() {
    const loading = document.getElementById('aiAssistantLoading');
    const content = document.getElementById('aiAssistantContent');
    const error = document.getElementById('aiAssistantError');
    
    loading.classList.remove('d-none');
    content.classList.add('d-none');
    error.classList.add('d-none');
    
    fetch('<?= \App\Helpers\Url::to('/ai-assistant/features') ?>', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        loading.classList.add('d-none');
        
        if (data.success && data.features) {
            aiAssistantFeatures = data.features;
            renderAIFeatures(data.features);
            content.classList.remove('d-none');
            
            // Obter agente selecionado para contexto atual
            loadSelectedAgent();
        } else {
            showAIError(data.message || 'Erro ao carregar funcionalidades');
        }
    })
    .catch(error => {
        loading.classList.add('d-none');
        showAIError('Erro ao carregar funcionalidades: ' + error.message);
    });
}

function renderAIFeatures(features) {
    const otherFeaturesContainer = document.getElementById('aiOtherFeatures');
    if (!otherFeaturesContainer) return;
    
    otherFeaturesContainer.innerHTML = '';
    
    // Mapear ícones para nomes mais amigáveis
    const iconMap = {
        'ki-file-down': '📄',
        'ki-tag': '🏷️',
        'ki-heart': '❤️',
        'ki-translate': '🌐',
        'ki-pencil': '✏️',
        'ki-arrow-right': '➡️',
        'ki-information': 'ℹ️'
    };
    
    features.forEach(feature => {
        // Pular "Gerar Resposta" pois já tem card dedicado
        if (feature.feature_key === 'generate_response') {
            return;
        }
        
        const icon = feature.icon || 'ki-abstract-26';
        const emoji = iconMap[icon] || '🤖';
        const cardHtml = `
            <div class="col-md-6 col-lg-4">
                <div class="card card-flush h-100 shadow-sm hover-shadow-lg transition-all">
                    <div class="card-body d-flex flex-column p-6">
                        <div class="mb-4">
                            <div class="symbol symbol-50px mb-3">
                                <div class="symbol-label bg-light-primary">
                                    <span class="fs-2x">${emoji}</span>
                                </div>
                            </div>
                            <h5 class="fw-bold mb-2">${escapeHtml(feature.name)}</h5>
                            <p class="text-muted fs-7 mb-0">${escapeHtml(feature.description || '')}</p>
                        </div>
                        <button class="btn btn-sm btn-primary w-100 mt-auto" onclick="executeAIFeature('${feature.feature_key}')">
                            <i class="ki-duotone ki-play fs-5 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Executar
                        </button>
                    </div>
                </div>
            </div>
        `;
        otherFeaturesContainer.insertAdjacentHTML('beforeend', cardHtml);
    });
}

function loadSelectedAgent() {
    if (!currentConversationId) return;
    
    const agentInfo = document.getElementById('aiAgentInfo');
    if (agentInfo) {
        agentInfo.innerHTML = '<span class="spinner-border spinner-border-sm text-primary me-2" role="status" style="width: 12px; height: 12px;"></span>Carregando agente...';
    }
    
    fetch(`<?= \App\Helpers\Url::to('/ai-assistant/selected-agent') ?>?conversation_id=${currentConversationId}&feature_key=generate_response`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.agent) {
            currentAIAgent = data.agent;
            if (agentInfo) {
                agentInfo.innerHTML = `
                    <i class="ki-duotone ki-abstract-26 fs-6 text-primary me-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                    </i>
                    <span class="fw-semibold">${escapeHtml(data.agent.name)}</span>
                    ${data.agent.model ? `<span class="text-muted ms-2">(${escapeHtml(data.agent.model)})</span>` : ''}
                `;
            }
        } else {
            if (agentInfo) {
                agentInfo.innerHTML = '<span class="text-muted">Agente padrão</span>';
            }
        }
    })
    .catch(error => {
        console.error('Erro ao carregar agente:', error);
        if (agentInfo) {
            agentInfo.innerHTML = '<span class="text-muted">Agente padrão</span>';
        }
    });
}

function generateAIResponse() {
    if (!currentConversationId) {
        alert('Selecione uma conversa primeiro');
        return;
    }
    
    const tone = document.getElementById('aiResponseTone')?.value || 'professional';
    const count = parseInt(document.getElementById('aiResponseCount')?.value || '3');
    const resultsDiv = document.getElementById('aiResponseResults');
    const suggestionsDiv = document.getElementById('aiResponseSuggestions');
    const generateBtn = document.getElementById('aiGenerateBtn');
    
    // Desabilitar botão e mostrar loading
    if (generateBtn) {
        generateBtn.disabled = true;
        generateBtn.querySelector('.indicator-label').classList.add('d-none');
        generateBtn.querySelector('.indicator-progress').classList.remove('d-none');
    }
    
    // Mostrar loading nas sugestões
    suggestionsDiv.innerHTML = `
        <div class="text-center py-10">
            <div class="mb-4">
                <span class="spinner-border spinner-border-lg text-primary mb-3" role="status" style="width: 3rem; height: 3rem;"></span>
            </div>
            <div class="fw-semibold fs-5 mb-2">Gerando respostas inteligentes...</div>
            <div class="text-muted">Analisando o contexto da conversa</div>
        </div>
    `;
    resultsDiv.classList.remove('d-none');
    
    // Scroll para resultados
    resultsDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    
    fetch('<?= \App\Helpers\Url::to('/ai-assistant/generate-response') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            conversation_id: currentConversationId,
            count: count,
            tone: tone
        })
    })
    .then(response => response.json())
    .then(data => {
        // Reabilitar botão
        if (generateBtn) {
            generateBtn.disabled = false;
            generateBtn.querySelector('.indicator-label').classList.remove('d-none');
            generateBtn.querySelector('.indicator-progress').classList.add('d-none');
        }
        
        if (data.success && data.responses) {
            let html = '';
            data.responses.forEach((response, index) => {
                const toneEmoji = {
                    'professional': '💼',
                    'friendly': '😊',
                    'formal': '📋'
                }[tone] || '💬';
                
                html += `
                    <div class="card card-flush shadow-sm mb-4 hover-shadow-lg transition-all" style="animation: fadeIn 0.3s ease-in ${index * 0.1}s both;">
                        <div class="card-body p-6">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="d-flex align-items-center">
                                    <span class="badge badge-light-primary badge-lg me-2">${toneEmoji} Sugestão ${index + 1}</span>
                                    ${response.tokens_used ? `<span class="badge badge-light-info badge-sm">${response.tokens_used.toLocaleString('pt-BR')} tokens</span>` : ''}
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-icon btn-light-primary" onclick="useAIResponse(${index})" title="Usar esta resposta">
                                        <i class="ki-duotone ki-check fs-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                    <button class="btn btn-sm btn-icon btn-light" onclick="copyToClipboard('${escapeHtml(response.text).replace(/'/g, "\\'")}')" title="Copiar resposta">
                                        <i class="ki-duotone ki-copy fs-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-4" style="white-space: pre-wrap; line-height: 1.7;">${escapeHtml(response.text)}</div>
                            <div class="d-flex align-items-center text-muted fs-7">
                                <i class="ki-duotone ki-abstract-26 fs-6 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                                <span class="fw-semibold me-2">${escapeHtml(response.agent_name || 'Assistente IA')}</span>
                                ${response.cost ? `<span class="text-muted">• R$ ${response.cost.toFixed(4).replace('.', ',')}</span>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            suggestionsDiv.innerHTML = html;
            
            // Armazenar respostas para uso posterior
            window.aiGeneratedResponses = data.responses;
            
            // Scroll suave para primeira sugestão
            setTimeout(() => {
                const firstCard = suggestionsDiv.querySelector('.card');
                if (firstCard) {
                    firstCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }, 100);
        } else {
            suggestionsDiv.innerHTML = `
                <div class="alert alert-danger d-flex align-items-center">
                    <i class="ki-duotone ki-information-5 fs-2x me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div>
                        <div class="fw-bold">Erro ao gerar respostas</div>
                        <div class="fs-7">${escapeHtml(data.message || 'Ocorreu um erro inesperado')}</div>
                    </div>
                </div>
            `;
        }
    })
    .catch(error => {
        // Reabilitar botão
        if (generateBtn) {
            generateBtn.disabled = false;
            generateBtn.querySelector('.indicator-label').classList.remove('d-none');
            generateBtn.querySelector('.indicator-progress').classList.add('d-none');
        }
        
        suggestionsDiv.innerHTML = `
            <div class="alert alert-danger d-flex align-items-center">
                <i class="ki-duotone ki-information-5 fs-2x me-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <div>
                    <div class="fw-bold">Erro ao gerar respostas</div>
                    <div class="fs-7">${escapeHtml(error.message || 'Erro de conexão')}</div>
                </div>
            </div>
        `;
    });
}

function useAIResponse(index, responseId = null) {
    if (!window.aiGeneratedResponses || !window.aiGeneratedResponses[index]) {
        return;
    }
    
    const response = window.aiGeneratedResponses[index];
    
    // Mostrar preview antes de usar
    const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                       document.body.classList.contains('dark-mode') ||
                       window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    // Construir HTML de forma segura
    const agentName = escapeHtml(response.agent_name || 'Assistente IA');
    const responseText = escapeHtml(response.text || '');
    const tokensText = response.tokens_used ? ' • ' + response.tokens_used.toLocaleString('pt-BR') + ' tokens' : '';
    const costText = response.cost ? ' • R$ ' + response.cost.toFixed(4).replace('.', ',') : '';
    
    const htmlContent = '<div class="text-start">' +
        '<div class="alert alert-info mb-4" style="white-space: pre-wrap; text-align: left;">' + responseText + '</div>' +
        '<div class="text-muted fs-7">' +
        '<i class="ki-duotone ki-abstract-26 fs-6 me-1">' +
        '<span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>' +
        '</i>' +
        agentName + tokensText + costText +
        '</div>' +
        '</div>';
    
    Swal.fire({
        title: 'Preview da Resposta',
        html: htmlContent,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Usar esta Resposta',
        cancelButtonText: 'Cancelar',
        buttonsStyling: false,
        colorScheme: isDarkMode ? 'dark' : 'light',
        customClass: {
            confirmButton: 'btn btn-primary',
            cancelButton: 'btn btn-light',
            popup: isDarkMode ? 'swal2-dark' : '',
            title: isDarkMode ? 'text-white' : '',
            htmlContainer: isDarkMode ? 'text-white' : ''
        },
        width: '600px'
    }).then((result) => {
        if (result.isConfirmed) {
            const messageInput = document.getElementById('messageInput');
            
            if (messageInput) {
                messageInput.value = response.text;
                messageInput.focus();
                
                // Marcar como usada se tiver ID
                if (responseId) {
                    fetch('<?= \App\Helpers\Url::to('/ai-assistant/mark-as-used') ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            response_id: responseId
                        })
                    }).catch(err => console.error('Erro ao marcar resposta como usada:', err));
                }
                
                // Fechar modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('kt_modal_ai_assistant'));
                if (modal) {
                    modal.hide();
                }
            }
        }
    });
}

function loadAIResponseHistory() {
    if (!currentConversationId) {
        return;
    }
    
    const historyDiv = document.getElementById('aiResponseHistory');
    const historyContent = document.getElementById('aiResponseHistoryContent');
    const resultsDiv = document.getElementById('aiResponseResults');
    
    if (!historyDiv || !historyContent) return;
    
    // Mostrar seção de histórico e esconder resultados atuais
    historyDiv.classList.remove('d-none');
    if (resultsDiv) {
        resultsDiv.classList.add('d-none');
    }
    
    // Scroll para histórico
    historyDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    
    fetch(`<?= \App\Helpers\Url::to('/ai-assistant/response-history') ?>?conversation_id=${currentConversationId}&limit=20`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.history) {
            const history = data.history;
            
            if (history.length === 0) {
                historyContent.innerHTML = `
                    <div class="text-center py-10">
                        <i class="ki-duotone ki-time fs-3x text-muted mb-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="text-muted">Nenhuma resposta no histórico ainda</div>
                    </div>
                `;
                return;
            }
            
            let html = '';
            history.forEach(item => {
                const date = new Date(item.created_at);
                const formattedDate = date.toLocaleString('pt-BR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                const toneEmoji = {
                    'professional': '💼',
                    'friendly': '😊',
                    'formal': '📋'
                }[item.tone] || '💬';
                
                html += `
                    <div class="card card-flush shadow-sm mb-4">
                        <div class="card-body p-6">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="d-flex align-items-center">
                                    <span class="badge badge-light-primary badge-sm me-2">${toneEmoji} ${escapeHtml(item.tone || 'N/A')}</span>
                                    ${item.is_favorite ? '<span class="badge badge-light-warning badge-sm me-2">⭐ Favorita</span>' : ''}
                                    ${item.used_at ? '<span class="badge badge-light-success badge-sm">✓ Usada</span>' : ''}
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-icon btn-light-${item.is_favorite ? 'warning' : 'gray'}" 
                                            onclick="toggleFavoriteResponse(${item.id}, this)" 
                                            title="${item.is_favorite ? 'Remover dos favoritos' : 'Adicionar aos favoritos'}">
                                        <i class="ki-duotone ki-star fs-5 ${item.is_favorite ? 'text-warning' : ''}">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                    <button class="btn btn-sm btn-icon btn-light-primary" 
                                            onclick="useHistoryResponse(${item.id}, '${escapeHtml(item.response_text).replace(/'/g, "\\'")}')" 
                                            title="Usar esta resposta">
                                        <i class="ki-duotone ki-check fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3" style="white-space: pre-wrap; line-height: 1.7;">${escapeHtml(item.response_text)}</div>
                            <div class="d-flex justify-content-between align-items-center text-muted fs-7">
                                <div>
                                    <i class="ki-duotone ki-time fs-6 me-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    ${formattedDate}
                                </div>
                                <div>
                                    ${escapeHtml(item.agent_name || 'Assistente IA')}
                                    ${item.tokens_used ? ` • ${item.tokens_used.toLocaleString('pt-BR')} tokens` : ''}
                                    ${item.cost ? ` • R$ ${parseFloat(item.cost).toFixed(4).replace('.', ',')}` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            historyContent.innerHTML = html;
        } else {
            historyContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="ki-duotone ki-information-5 fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Erro ao carregar histórico
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Erro ao carregar histórico:', error);
        historyContent.innerHTML = `
            <div class="alert alert-danger">
                <i class="ki-duotone ki-information-5 fs-2 me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                Erro ao carregar histórico
            </div>
        `;
    });
}

function hideAIResponseHistory() {
    const historyDiv = document.getElementById('aiResponseHistory');
    const resultsDiv = document.getElementById('aiResponseResults');
    
    if (historyDiv) {
        historyDiv.classList.add('d-none');
    }
    if (resultsDiv) {
        resultsDiv.classList.remove('d-none');
    }
}

function toggleFavoriteResponse(responseId, buttonElement) {
    fetch('<?= \App\Helpers\Url::to('/ai-assistant/toggle-favorite') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            response_id: responseId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Recarregar histórico
            loadAIResponseHistory();
        } else {
            alert('Erro ao atualizar favorito: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao atualizar favorito');
    });
}

function useHistoryResponse(responseId, responseText) {
    const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                       document.body.classList.contains('dark-mode') ||
                       window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    Swal.fire({
        title: 'Preview da Resposta',
        html: `
            <div class="text-start">
                <div class="alert alert-info mb-4" style="white-space: pre-wrap; text-align: left;">${escapeHtml(responseText)}</div>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Usar esta Resposta',
        cancelButtonText: 'Cancelar',
        buttonsStyling: false,
        colorScheme: isDarkMode ? 'dark' : 'light',
        customClass: {
            confirmButton: 'btn btn-primary',
            cancelButton: 'btn btn-light',
            popup: isDarkMode ? 'swal2-dark' : '',
            title: isDarkMode ? 'text-white' : '',
            htmlContainer: isDarkMode ? 'text-white' : ''
        },
        width: '600px'
    }).then((result) => {
        if (result.isConfirmed) {
            const messageInput = document.getElementById('messageInput');
            
            if (messageInput) {
                messageInput.value = responseText;
                messageInput.focus();
                
                // Marcar como usada
                fetch('<?= \App\Helpers\Url::to('/ai-assistant/mark-as-used') ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        response_id: responseId
                    })
                }).catch(err => console.error('Erro ao marcar resposta como usada:', err));
                
                // Fechar modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('kt_modal_ai_assistant'));
                if (modal) {
                    modal.hide();
                }
            }
        }
    });
}

function executeAIFeature(featureKey) {
    if (!currentConversationId) {
        alert('Selecione uma conversa primeiro');
        return;
    }
    
    const feature = aiAssistantFeatures.find(f => f.feature_key === featureKey);
    if (!feature) {
        alert('Funcionalidade não encontrada');
        return;
    }
    
    // Mostrar loading
    const loadingHtml = `
        <div class="text-center py-10">
            <span class="spinner-border spinner-border-lg text-primary mb-3" role="status"></span>
            <div class="text-muted">Processando...</div>
        </div>
    `;
    
    // Criar modal temporário para mostrar resultado
    const resultModal = document.createElement('div');
    resultModal.className = 'modal fade';
    resultModal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered mw-700px">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="fw-bold">${escapeHtml(feature.name)}</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                        <i class="ki-duotone ki-cross fs-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </div>
                </div>
                <div class="modal-body">
                    ${loadingHtml}
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(resultModal);
    const bsModal = new bootstrap.Modal(resultModal);
    bsModal.show();
    
    fetch('<?= \App\Helpers\Url::to('/ai-assistant/execute-feature') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            conversation_id: currentConversationId,
            feature_key: featureKey
        })
    })
    .then(response => response.json())
    .then(data => {
        const modalBody = resultModal.querySelector('.modal-body');
        if (data.success && data.result) {
            // Renderizar resultado baseado no tipo de funcionalidade
            modalBody.innerHTML = renderAIResult(featureKey, data.result, data, currentConversationId);
        } else {
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    <i class="ki-duotone ki-information-5 fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    ${escapeHtml(data.message || 'Erro ao executar funcionalidade')}
                </div>
            `;
        }
    })
    .catch(error => {
        const modalBody = resultModal.querySelector('.modal-body');
        modalBody.innerHTML = `
            <div class="alert alert-danger">
                <i class="ki-duotone ki-information-5 fs-2 me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                Erro: ${escapeHtml(error.message)}
            </div>
        `;
    });
    
    // Remover modal quando fechar
    resultModal.addEventListener('hidden.bs.modal', function() {
        resultModal.remove();
    });
}

function showAIError(message) {
    const error = document.getElementById('aiAssistantError');
    const errorMessage = document.getElementById('aiAssistantErrorMessage');
    
    if (error && errorMessage) {
        errorMessage.textContent = message;
        error.classList.remove('d-none');
    }
}

/**
 * Renderizar resultado do Assistente IA com formatação especial e ações rápidas
 */
function renderAIResult(featureKey, result, data, conversationId) {
    const agentInfo = `
        <div class="mt-3 d-flex align-items-center justify-content-between">
            <div class="text-muted fs-7">
                <i class="ki-duotone ki-abstract-26 fs-6">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                ${escapeHtml(data.agent_used?.name || 'Assistente IA')}
                ${data.tokens_used ? `<span class="ms-2">• ${data.tokens_used} tokens</span>` : ''}
                ${data.cost ? `<span class="ms-2">• R$ ${data.cost.toFixed(4)}</span>` : ''}
            </div>
        </div>
    `;
    
    switch (featureKey) {
        case 'suggest_tags':
            // Extrair tags do resultado (pode ser lista separada por vírgula)
            const tagsText = result.trim();
            const suggestedTags = tagsText.split(/[,;]/).map(t => t.trim()).filter(t => t);
            
            return `
                <div class="card">
                    <div class="card-body">
                        <h5 class="fw-bold mb-4">Tags Sugeridas</h5>
                        <div class="mb-4">
                            ${suggestedTags.map(tag => `
                                <span class="badge badge-lg me-2 mb-2" style="background-color: #009ef720; color: #009ef7;">
                                    ${escapeHtml(tag)}
                                </span>
                            `).join('')}
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-primary" onclick="applySuggestedTags(${conversationId}, ${JSON.stringify(suggestedTags).replace(/"/g, '&quot;')})">
                                <i class="ki-duotone ki-check fs-5 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Aplicar Tags
                            </button>
                            <button class="btn btn-sm btn-light" onclick="copyToClipboard('${escapeHtml(tagsText).replace(/'/g, "\\'")}')">
                                <i class="ki-duotone ki-copy fs-5 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Copiar
                            </button>
                        </div>
                        ${agentInfo}
                    </div>
                </div>
            `;
            
        case 'translate':
            return `
                <div class="card">
                    <div class="card-body">
                        <h5 class="fw-bold mb-4">Tradução</h5>
                        <div class="alert alert-light-primary d-flex align-items-start p-4 mb-4">
                            <i class="ki-duotone ki-translate fs-2x text-primary me-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="flex-grow-1" style="white-space: pre-wrap;">${escapeHtml(result)}</div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-primary" onclick="useTranslatedText('${escapeHtml(result).replace(/'/g, "\\'")}')">
                                <i class="ki-duotone ki-check fs-5 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Usar Tradução
                            </button>
                            <button class="btn btn-sm btn-light" onclick="copyToClipboard('${escapeHtml(result).replace(/'/g, "\\'")}')">
                                <i class="ki-duotone ki-copy fs-5 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Copiar
                            </button>
                        </div>
                        ${agentInfo}
                    </div>
                </div>
            `;
            
        case 'improve_grammar':
            return `
                <div class="card">
                    <div class="card-body">
                        <h5 class="fw-bold mb-4">Texto Melhorado</h5>
                        <div class="alert alert-light-success d-flex align-items-start p-4 mb-4">
                            <i class="ki-duotone ki-pencil fs-2x text-success me-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="flex-grow-1" style="white-space: pre-wrap;">${escapeHtml(result)}</div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-primary" onclick="useImprovedText('${escapeHtml(result).replace(/'/g, "\\'")}')">
                                <i class="ki-duotone ki-check fs-5 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Usar Texto Melhorado
                            </button>
                            <button class="btn btn-sm btn-light" onclick="copyToClipboard('${escapeHtml(result).replace(/'/g, "\\'")}')">
                                <i class="ki-duotone ki-copy fs-5 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Copiar
                            </button>
                        </div>
                        ${agentInfo}
                    </div>
                </div>
            `;
            
        case 'analyze_sentiment':
            // Detectar sentimento no resultado
            const sentimentLower = result.toLowerCase();
            let sentimentBadge = 'secondary';
            let sentimentIcon = 'ki-information';
            if (sentimentLower.includes('positivo') || sentimentLower.includes('positive')) {
                sentimentBadge = 'success';
                sentimentIcon = 'ki-like';
            } else if (sentimentLower.includes('negativo') || sentimentLower.includes('negative')) {
                sentimentBadge = 'danger';
                sentimentIcon = 'ki-dislike';
            } else if (sentimentLower.includes('neutro') || sentimentLower.includes('neutral')) {
                sentimentBadge = 'warning';
                sentimentIcon = 'ki-information';
            }
            
            return `
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-4">
                            <span class="badge badge-${sentimentBadge} badge-lg me-3">
                                <i class="ki-duotone ${sentimentIcon} fs-6 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Análise de Sentimento
                            </span>
                        </div>
                        <div style="white-space: pre-wrap; line-height: 1.8;">${escapeHtml(result)}</div>
                        ${agentInfo}
                    </div>
                </div>
            `;
            
        case 'summarize':
            return `
                <div class="card">
                    <div class="card-body">
                        <h5 class="fw-bold mb-4">
                            <i class="ki-duotone ki-file-down fs-2 text-primary me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Resumo da Conversa
                        </h5>
                        <div class="alert alert-light-primary p-4 mb-4" style="white-space: pre-wrap; line-height: 1.8;">${escapeHtml(result)}</div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-light" onclick="copyToClipboard('${escapeHtml(result).replace(/'/g, "\\'")}')">
                                <i class="ki-duotone ki-copy fs-5 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Copiar Resumo
                            </button>
                        </div>
                        ${agentInfo}
                    </div>
                </div>
            `;
            
        case 'extract_info':
            return `
                <div class="card">
                    <div class="card-body">
                        <h5 class="fw-bold mb-4">
                            <i class="ki-duotone ki-information fs-2 text-primary me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Informações Extraídas
                        </h5>
                        <div style="white-space: pre-wrap; line-height: 1.8;">${escapeHtml(result)}</div>
                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-sm btn-light" onclick="copyToClipboard('${escapeHtml(result).replace(/'/g, "\\'")}')">
                                <i class="ki-duotone ki-copy fs-5 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Copiar
                            </button>
                        </div>
                        ${agentInfo}
                    </div>
                </div>
            `;
            
        case 'suggest_next_steps':
            return `
                <div class="card">
                    <div class="card-body">
                        <h5 class="fw-bold mb-4">
                            <i class="ki-duotone ki-arrow-right fs-2 text-primary me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Próximos Passos Sugeridos
                        </h5>
                        <div style="white-space: pre-wrap; line-height: 1.8;">${escapeHtml(result)}</div>
                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-sm btn-light" onclick="copyToClipboard('${escapeHtml(result).replace(/'/g, "\\'")}')">
                                <i class="ki-duotone ki-copy fs-5 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Copiar
                            </button>
                        </div>
                        ${agentInfo}
                    </div>
                </div>
            `;
            
        default:
            return `
                <div class="card">
                    <div class="card-body">
                        <div style="white-space: pre-wrap;">${escapeHtml(result)}</div>
                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-sm btn-light" onclick="copyToClipboard('${escapeHtml(result).replace(/'/g, "\\'")}')">
                                <i class="ki-duotone ki-copy fs-5 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Copiar
                            </button>
                        </div>
                        ${agentInfo}
                    </div>
                </div>
            `;
    }
}

/**
 * Aplicar tags sugeridas na conversa
 */
async function applySuggestedTags(conversationId, tags) {
    if (!conversationId || !tags || tags.length === 0) {
        alert('Nenhuma tag para aplicar');
        return;
    }
    
    // Buscar tags existentes por nome
    try {
        const tagsResponse = await fetch('<?= \App\Helpers\Url::to("/tags/all") ?>');
        const tagsData = await tagsResponse.json();
        
        if (!tagsData.success || !tagsData.tags) {
            throw new Error('Erro ao carregar tags');
        }
        
        const allTags = tagsData.tags;
        const tagsToAdd = [];
        
        // Encontrar IDs das tags sugeridas
        tags.forEach(suggestedTag => {
            const tag = allTags.find(t => t.name.toLowerCase() === suggestedTag.toLowerCase());
            if (tag) {
                tagsToAdd.push(tag.id);
            }
        });
        
        if (tagsToAdd.length === 0) {
            alert('Nenhuma das tags sugeridas foi encontrada no sistema');
            return;
        }
        
        // Adicionar tags à conversa
        const promises = tagsToAdd.map(tagId => 
            fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${conversationId}/tags`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ tag_id: tagId })
            })
        );
        
        await Promise.all(promises);
        
        // Fechar modal e recarregar página para mostrar tags aplicadas
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) bsModal.hide();
        });
        
        // Mostrar mensagem de sucesso
        const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                          document.body.classList.contains('dark-mode') ||
                          window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        Swal.fire({
            icon: 'success',
            title: 'Tags Aplicadas',
            text: `${tagsToAdd.length} tag(s) aplicada(s) com sucesso!`,
            timer: 2000,
            showConfirmButton: false,
            colorScheme: isDarkMode ? 'dark' : 'light',
            customClass: {
                popup: isDarkMode ? 'swal2-dark' : '',
                title: isDarkMode ? 'text-white' : '',
                htmlContainer: isDarkMode ? 'text-white' : ''
            }
        });
        
        // Recarregar página após um breve delay
        setTimeout(() => {
            window.location.reload();
        }, 500);
        
    } catch (error) {
        console.error('Erro ao aplicar tags:', error);
        alert('Erro ao aplicar tags: ' + error.message);
    }
}

/**
 * Usar texto traduzido na mensagem
 */
function useTranslatedText(text) {
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.value = text;
        messageInput.focus();
        
        // Fechar modal
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) bsModal.hide();
        });
    }
}

/**
 * Usar texto melhorado na mensagem
 */
function useImprovedText(text) {
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.value = text;
        messageInput.focus();
        
        // Fechar modal
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) bsModal.hide();
        });
    }
}

/**
 * Copiar texto para área de transferência
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                          document.body.classList.contains('dark-mode') ||
                          window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        Swal.fire({
            icon: 'success',
            title: 'Copiado!',
            text: 'Texto copiado para área de transferência',
            timer: 1500,
            showConfirmButton: false,
            colorScheme: isDarkMode ? 'dark' : 'light',
            customClass: {
                popup: isDarkMode ? 'swal2-dark' : '',
                title: isDarkMode ? 'text-white' : '',
                htmlContainer: isDarkMode ? 'text-white' : ''
            }
        });
    }).catch(err => {
        console.error('Erro ao copiar:', err);
        alert('Erro ao copiar texto');
    });
}

// Carregar funcionalidades quando modal for aberto
document.addEventListener('DOMContentLoaded', function() {
    const aiAssistantModal = document.getElementById('kt_modal_ai_assistant');
    if (aiAssistantModal) {
        aiAssistantModal.addEventListener('show.bs.modal', function() {
            loadAIAssistantFeatures();
        });
    }
    
    // Preservar dropdowns abertos durante atualizações
    // Usar delegação de eventos para dropdowns dinâmicos
    document.addEventListener('show.bs.dropdown', function(e) {
        const dropdown = e.target.closest('.conversation-item-actions');
        if (dropdown) {
            const conversationId = dropdown.querySelector('[data-conversation-id]')?.getAttribute('data-conversation-id');
            if (conversationId) {
                // Marcar que este dropdown está aberto
                dropdown.dataset.isOpen = 'true';
            }
        }
    });
    
    document.addEventListener('hide.bs.dropdown', function(e) {
        const dropdown = e.target.closest('.conversation-item-actions');
        if (dropdown) {
            dropdown.dataset.isOpen = 'false';
        }
    });
    
    // Inicializar seletor rápido de templates
    initTemplateQuickSelect();
    
    // Formulário de nova conversa
    const newConversationForm = document.getElementById('newConversationForm');
    if (newConversationForm) {
        console.log('✅ Anexando listener ao formulário de nova conversa');
        newConversationForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            console.log('📝 Formulário de nova conversa submetido');
            
            const name = document.getElementById('new_contact_name').value.trim();
            const phone = document.getElementById('new_contact_phone').value.trim();
            const message = document.getElementById('new_conversation_message').value.trim();
            
            console.log('📋 Dados do formulário:', { name, phone, message });
            
            if (!name || !phone || !message) {
                alert('Preencha todos os campos');
                return;
            }
            
            // Validar telefone (deve ter pelo menos 10 dígitos - DDD + número)
            if (phone.length < 10 || phone.length > 11) {
                alert('Telefone inválido. Digite DDD + número (ex: 11987654321)');
                return;
            }
            
            // Formatar telefone completo (+55 + DDD + número)
            const fullPhone = '55' + phone;
            console.log('📞 Telefone formatado:', fullPhone);
            
            const submitBtn = newConversationForm.querySelector('button[type="submit"]');
            const indicator = submitBtn.querySelector('.indicator-label');
            const progress = submitBtn.querySelector('.indicator-progress');
            
            // Mostrar loading
            submitBtn.setAttribute('data-kt-indicator', 'on');
            indicator.style.display = 'none';
            progress.style.display = 'inline-block';
            submitBtn.disabled = true;
            
            console.log('🚀 Enviando requisição para criar nova conversa...');
            
            try {
                const response = await fetch('<?= \App\Helpers\Url::to("/conversations/new") ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        name: name,
                        phone: fullPhone,
                        message: message
                    })
                });
                
                console.log('📡 Resposta HTTP:', response.status, response.statusText);
                
                const responseText = await response.text();
                console.log('📄 Resposta texto (primeiros 500 chars):', responseText.substring(0, 500));
                
                let data;
                try {
                    data = JSON.parse(responseText);
                    console.log('✅ Resposta JSON parseada:', data);
                } catch (jsonErr) {
                    console.error('❌ Erro ao fazer parse do JSON:', jsonErr);
                    throw new Error(`Resposta não é JSON. HTTP ${response.status}. Corpo: ${responseText.substring(0, 500)}`);
                }
                
                if (data.success) {
                    console.log('✅ Conversa criada com sucesso!', data);
                    
                    const modal = bootstrap.Modal.getInstance(document.getElementById('kt_modal_new_conversation'));
                    if (modal) modal.hide();
                    
                    // Limpar formulário
                    newConversationForm.reset();
                    
                    // Redirecionar para a nova conversa
                    if (data.conversation_id) {
                        console.log('🔄 Redirecionando para conversa:', data.conversation_id);
                        window.location.href = '<?= \App\Helpers\Url::to("/conversations") ?>?id=' + data.conversation_id;
                    } else {
                        console.log('🔄 Recarregando lista de conversas...');
                        // Recarregar lista de conversas
                        refreshConversationList();
                        
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Conversa criada!',
                                text: 'Mensagem enviada com sucesso',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 3000
                            });
                        }
                    }
                } else {
                    console.error('Erro na resposta:', data);
                    // Mostrar aviso sobre agente atribuído
                    if (data.existing_agent) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Conversa já atribuída',
                                html: `<p>${data.message}</p>`,
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#009ef7'
                            });
                        } else {
                            alert(data.message);
                        }
                    } else {
                        alert('Erro ao criar conversa: ' + (data.message || 'Erro desconhecido'));
                    }
                }
            } catch (error) {
                console.error('Erro capturado:', error);
                alert('Erro ao criar conversa: ' + error.message);
            } finally {
                console.log('Finalizando requisição, escondendo loading...');
                // Esconder loading
                submitBtn.removeAttribute('data-kt-indicator');
                indicator.style.display = 'inline-block';
                progress.style.display = 'none';
                submitBtn.disabled = false;
            }
        });
    } else {
        console.warn('Formulário newConversationForm não encontrado');
    }
    
    // Formulário de agendar mensagem
    const scheduleMessageForm = document.getElementById('scheduleMessageForm');
    if (scheduleMessageForm) {
        scheduleMessageForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const conversationId = document.getElementById('schedule_conversation_id').value;
            const content = document.getElementById('schedule_message_content').value;
            const date = document.getElementById('schedule_message_date').value;
            const time = document.getElementById('schedule_message_time').value;
            const cancelIfResolved = document.getElementById('schedule_cancel_if_resolved').checked;
            const cancelIfResponded = document.getElementById('schedule_cancel_if_responded').checked;
            
            if (!content.trim() && !document.getElementById('schedule_message_attachment').files.length) {
                alert('Digite uma mensagem ou anexe um arquivo');
                return;
            }
            
            if (!date || !time) {
                alert('Selecione data e hora');
                return;
            }
            
            const scheduledAt = `${date} ${time}:00`;
            const scheduledDate = new Date(scheduledAt);
            const now = new Date();
            
            if (scheduledDate <= now) {
                alert('Data/hora deve ser no futuro');
                return;
            }
            
            const formData = new FormData();
            formData.append('content', content);
            formData.append('scheduled_at', scheduledAt);
            formData.append('cancel_if_resolved', cancelIfResolved ? '1' : '0');
            formData.append('cancel_if_responded', cancelIfResponded ? '1' : '0');
            
            // Adicionar anexo se houver
            const attachment = document.getElementById('schedule_message_attachment').files[0];
            if (attachment) {
                formData.append('attachment', attachment);
            }
            
            try {
                const response = await fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${conversationId}/schedule-message`, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const responseText = await response.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (jsonErr) {
                    throw new Error(`Resposta não é JSON. HTTP ${response.status}. Corpo: ${responseText.substring(0, 500)}`);
                }
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('kt_modal_schedule_message'));
                    if (modal) modal.hide();
                    
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Mensagem agendada!',
                            text: `Mensagem será enviada em ${new Date(scheduledAt).toLocaleString('pt-BR')}`,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    }
                } else {
                    alert('Erro ao agendar mensagem: ' + (data.message || 'Erro desconhecido'));
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao agendar mensagem: ' + error.message);
            }
        });
    }
    
    // Mostrar modal de mensagens agendadas
    window.showScheduledMessagesModal = function() {
        const conversationId = window.currentConversationId;
        if (!conversationId) {
            alert('Nenhuma conversa selecionada');
            return;
        }
        
        const modal = new bootstrap.Modal(document.getElementById('kt_modal_scheduled_messages'));
        modal.show();
        
        // Carregar mensagens
        loadScheduledMessages();
        
        // Configurar filtros
        const filterInputs = document.querySelectorAll('input[name="scheduled_status_filter"]');
        filterInputs.forEach(input => {
            input.addEventListener('change', function() {
                loadScheduledMessages(this.value);
            });
        });
    };
    
    // Carregar mensagens agendadas
    async function loadScheduledMessages(status = '') {
        const conversationId = window.currentConversationId;
        if (!conversationId) return;
        
        const listContainer = document.getElementById('scheduledMessagesList');
        listContainer.innerHTML = '<div class="text-center py-10"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div></div>';
        
        try {
            const url = `<?= \App\Helpers\Url::to("/conversations") ?>/${conversationId}/scheduled-messages${status ? '?status=' + status : ''}`;
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                const messages = data.messages || [];
                
                if (messages.length === 0) {
                    listContainer.innerHTML = `
                        <div class="text-center py-10">
                            <i class="ki-duotone ki-calendar fs-3x text-muted mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <h5 class="text-muted">Nenhuma mensagem agendada</h5>
                        </div>
                    `;
                    return;
                }
                
                let html = '';
                messages.forEach(msg => {
                    const scheduledDate = new Date(msg.scheduled_at);
                    const formattedDate = scheduledDate.toLocaleString('pt-BR', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    let statusBadge = '';
                    let statusClass = '';
                    let actionButtons = '';
                    
                    switch(msg.status) {
                        case 'pending':
                            statusBadge = '<span class="badge badge-light-warning">Pendente</span>';
                            statusClass = 'border-warning';
                            actionButtons = `<button class="btn btn-sm btn-light-danger" onclick="cancelScheduledMessage(${msg.id})">Cancelar</button>`;
                            break;
                        case 'sent':
                            statusBadge = '<span class="badge badge-light-success">Enviada</span>';
                            statusClass = 'border-success';
                            break;
                        case 'cancelled':
                            statusBadge = '<span class="badge badge-light-danger">Cancelada</span>';
                            statusClass = 'border-danger';
                            break;
                        case 'failed':
                            statusBadge = '<span class="badge badge-light-dark">Falhou</span>';
                            statusClass = 'border-dark';
                            break;
                    }
                    
                    const content = msg.content || '';
                    const truncatedContent = content.length > 100 ? content.substring(0, 100) + '...' : content;
                    
                    html += `
                        <div class="card mb-3 ${statusClass}" style="border-left: 4px solid;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <span class="fw-bold text-gray-800 fs-6">
                                            <i class="ki-duotone ki-calendar fs-5 me-1">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            ${formattedDate}
                                        </span>
                                    </div>
                                    <div>
                                        ${statusBadge}
                                    </div>
                                </div>
                                <div class="text-gray-700 mb-3">${escapeHtml(truncatedContent)}</div>
                                ${msg.error_message ? `<div class="alert alert-danger mb-3 py-2">${escapeHtml(msg.error_message)}</div>` : ''}
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        ${msg.cancel_if_resolved ? '<i class="ki-duotone ki-check-circle fs-6 text-info me-1"><span class="path1"></span><span class="path2"></span></i>Cancelar se resolvida ' : ''}
                                        ${msg.cancel_if_responded ? '<i class="ki-duotone ki-message-text fs-6 text-info me-1"><span class="path1"></span><span class="path2"></span></i>Cancelar se respondida' : ''}
                                    </small>
                                    <div>
                                        ${actionButtons}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                listContainer.innerHTML = html;
            } else {
                listContainer.innerHTML = `
                    <div class="alert alert-danger">
                        ${data.message || 'Erro ao carregar mensagens'}
                    </div>
                `;
            }
        } catch (error) {
            console.error('Erro:', error);
            listContainer.innerHTML = `
                <div class="alert alert-danger">
                    Erro ao carregar mensagens: ${error.message}
                </div>
            `;
        }
    }
    
    // Cancelar mensagem agendada
    window.cancelScheduledMessage = async function(messageId) {
        const conversationId = window.currentConversationId;
        if (!conversationId) return;
        
        const result = await Swal.fire({
            title: 'Confirmar cancelamento',
            text: 'Deseja cancelar esta mensagem agendada?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, cancelar',
            cancelButtonText: 'Não',
            customClass: {
                confirmButton: 'btn btn-danger',
                cancelButton: 'btn btn-light'
            }
        });
        
        if (!result.isConfirmed) return;
        
        try {
            const response = await fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${conversationId}/scheduled-messages/${messageId}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Sucesso!',
                    text: 'Mensagem cancelada com sucesso',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
                
                // Recarregar lista
                const activeFilter = document.querySelector('input[name="scheduled_status_filter"]:checked');
                loadScheduledMessages(activeFilter ? activeFilter.value : '');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: data.message || 'Erro ao cancelar mensagem'
                });
            }
        } catch (error) {
            console.error('Erro:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Erro ao cancelar mensagem: ' + error.message
            });
        }
    };
    
    // Função auxiliar para escapar HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Máscara de telefone brasileiro (DDD + número)
    const phoneInput = document.getElementById('new_contact_phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, ''); // Remove tudo que não é dígito
            if (value.length > 11) value = value.substring(0, 11); // Limita a 11 dígitos
            e.target.value = value;
        });
    }
    
    // Formulário de criar lembrete
    const reminderForm = document.getElementById('reminderForm');
    if (reminderForm) {
        reminderForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const conversationId = document.getElementById('reminder_conversation_id').value;
            const date = document.getElementById('reminder_date').value;
            const time = document.getElementById('reminder_time').value;
            const note = document.getElementById('reminder_note').value;
            
            if (!date || !time) {
                alert('Selecione data e hora');
                return;
            }
            
            const reminderAt = `${date} ${time}:00`;
            const reminderDate = new Date(reminderAt);
            const now = new Date();
            
            if (reminderDate <= now) {
                alert('Data/hora deve ser no futuro');
                return;
            }
            
            try {
                const response = await fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${conversationId}/reminders`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        reminder_at: reminderAt,
                        note: note || null
                    })
                });
                
                const responseText = await response.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (jsonErr) {
                    throw new Error(`Resposta não é JSON. HTTP ${response.status}. Corpo: ${responseText.substring(0, 500)}`);
                }
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('kt_modal_reminder'));
                    if (modal) modal.hide();
                    
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Lembrete criado!',
                            text: `Lembrete será exibido em ${new Date(reminderAt).toLocaleString('pt-BR')}`,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    }
                } else {
                    alert('Erro ao criar lembrete: ' + (data.message || 'Erro desconhecido'));
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao criar lembrete: ' + error.message);
            }
        });
    }
    
    // Inicializar seletor rápido de variáveis (ao digitar {{)
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.addEventListener('input', function(e) {
            const value = e.target.value;
            const cursorPos = e.target.selectionStart;
            const textBeforeCursor = value.substring(0, cursorPos);
            
            // Se digitar {{, mostrar seletor rápido de templates ou variáveis
            if (textBeforeCursor.endsWith('{{')) {
                // Mostrar seletor rápido de templates (pode ser expandido para variáveis também)
                showTemplateQuickSelect();
            }
        });
    }
});

// ============================================
// AÇÕES EM MASSA
// ============================================

// Obter conversas selecionadas
function getSelectedConversations() {
    const checkboxes = document.querySelectorAll('.conversation-checkbox:checked');
    return Array.from(checkboxes).map(cb => parseInt(cb.value));
}

// Atualizar contador e barra de ações
function toggleBulkSelection() {
    const selected = getSelectedConversations();
    const bulkBar = document.getElementById('bulkActionsBar');
    const countEl = document.getElementById('bulkSelectionCount');
    
    if (bulkBar && countEl) {
        if (selected.length > 0) {
            bulkBar.classList.remove('d-none');
            countEl.textContent = `${selected.length} conversa${selected.length > 1 ? 's' : ''} selecionada${selected.length > 1 ? 's' : ''}`;
        } else {
            bulkBar.classList.add('d-none');
        }
    }
}

// Selecionar todas as conversas
function selectAllConversations() {
    document.querySelectorAll('.conversation-checkbox').forEach(cb => {
        cb.checked = true;
    });
    toggleBulkSelection();
}

// Limpar seleção
function clearBulkSelection() {
    document.querySelectorAll('.conversation-checkbox').forEach(cb => {
        cb.checked = false;
    });
    toggleBulkSelection();
}

// Atribuir conversas a agente
async function bulkAssignAgent(agentId, agentName) {
    const selected = getSelectedConversations();
    if (selected.length === 0) {
        alert('Selecione pelo menos uma conversa');
        return;
    }
    
    const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                       document.body.classList.contains('dark-mode') ||
                       window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    if (!confirm(`Atribuir ${selected.length} conversa(s) para ${agentName}?`)) {
        return;
    }
    
    try {
        const promises = selected.map(id => 
            fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${id}/assign`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ agent_id: agentId })
            }).then(r => r.json())
        );
        
        const results = await Promise.all(promises);
        const success = results.filter(r => r.success).length;
        const failed = results.filter(r => !r.success).length;
        
        Swal.fire({
            icon: success > 0 ? 'success' : 'error',
            title: success > 0 ? 'Sucesso!' : 'Erro',
            text: `${success} conversa(s) atribuída(s) com sucesso${failed > 0 ? `. ${failed} falharam.` : '.'}`,
            colorScheme: isDarkMode ? 'dark' : 'light'
        });
        
        clearBulkSelection();
        if (typeof refreshConversationList === 'function') {
            const urlParams = new URLSearchParams(window.location.search);
            refreshConversationList(urlParams);
        }
    } catch (error) {
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao atribuir conversas',
            colorScheme: isDarkMode ? 'dark' : 'light'
        });
    }
}

// Adicionar tag em massa
async function bulkAddTag(tagId, tagName) {
    const selected = getSelectedConversations();
    if (selected.length === 0) {
        alert('Selecione pelo menos uma conversa');
        return;
    }
    
    const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                       document.body.classList.contains('dark-mode') ||
                       window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    try {
        const promises = selected.map(id => 
            fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${id}/tags`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ tag_id: tagId })
            }).then(r => r.json())
        );
        
        const results = await Promise.all(promises);
        const success = results.filter(r => r.success).length;
        const failed = results.filter(r => !r.success).length;
        
        Swal.fire({
            icon: success > 0 ? 'success' : 'error',
            title: success > 0 ? 'Sucesso!' : 'Erro',
            text: `Tag "${tagName}" adicionada em ${success} conversa(s)${failed > 0 ? `. ${failed} falharam.` : '.'}`,
            colorScheme: isDarkMode ? 'dark' : 'light'
        });
        
        clearBulkSelection();
        if (typeof refreshConversationList === 'function') {
            const urlParams = new URLSearchParams(window.location.search);
            refreshConversationList(urlParams);
        }
    } catch (error) {
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao adicionar tag',
            colorScheme: isDarkMode ? 'dark' : 'light'
        });
    }
}

// Fechar conversas em massa
async function bulkCloseConversations() {
    const selected = getSelectedConversations();
    if (selected.length === 0) {
        alert('Selecione pelo menos uma conversa');
        return;
    }
    
    const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                       document.body.classList.contains('dark-mode') ||
                       window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    if (!confirm(`Fechar ${selected.length} conversa(s)?`)) {
        return;
    }
    
    try {
        const promises = selected.map(id => 
            fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${id}/close`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(r => r.json())
        );
        
        const results = await Promise.all(promises);
        const success = results.filter(r => r.success).length;
        const failed = results.filter(r => !r.success).length;
        
        Swal.fire({
            icon: success > 0 ? 'success' : 'error',
            title: success > 0 ? 'Sucesso!' : 'Erro',
            text: `${success} conversa(s) fechada(s)${failed > 0 ? `. ${failed} falharam.` : '.'}`,
            colorScheme: isDarkMode ? 'dark' : 'light'
        });
        
        clearBulkSelection();
        if (typeof refreshConversationList === 'function') {
            const urlParams = new URLSearchParams(window.location.search);
            refreshConversationList(urlParams);
        }
    } catch (error) {
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao fechar conversas',
            colorScheme: isDarkMode ? 'dark' : 'light'
        });
    }
}

// Reabrir conversas em massa
async function bulkReopenConversations() {
    const selected = getSelectedConversations();
    if (selected.length === 0) {
        alert('Selecione pelo menos uma conversa');
        return;
    }
    
    const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                       document.body.classList.contains('dark-mode') ||
                       window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    if (!confirm(`Reabrir ${selected.length} conversa(s)?`)) {
        return;
    }
    
    try {
        const promises = selected.map(id => 
            fetch(`<?= \App\Helpers\Url::to("/conversations") ?>/${id}/reopen`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(r => r.json())
        );
        
        const results = await Promise.all(promises);
        const success = results.filter(r => r.success).length;
        const failed = results.filter(r => !r.success).length;
        
        Swal.fire({
            icon: success > 0 ? 'success' : 'error',
            title: success > 0 ? 'Sucesso!' : 'Erro',
            text: `${success} conversa(s) reaberta(s)${failed > 0 ? `. ${failed} falharam.` : '.'}`,
            colorScheme: isDarkMode ? 'dark' : 'light'
        });
        
        clearBulkSelection();
        if (typeof refreshConversationList === 'function') {
            const urlParams = new URLSearchParams(window.location.search);
            refreshConversationList(urlParams);
        }
    } catch (error) {
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao reabrir conversas',
            colorScheme: isDarkMode ? 'dark' : 'light'
        });
    }
}

// Formulário de edição de contato
const editContactForm = document.getElementById('editContactForm');
if (editContactForm) {
    editContactForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const contactId = document.getElementById('editContactId').value;
        if (!contactId) {
            return;
        }
        
        const formData = new FormData(this);
        
        // Mostrar loading
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
        
        fetch(`<?= \App\Helpers\Url::to('/contacts') ?>/${contactId}`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Fechar modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('kt_modal_edit_contact'));
                if (modal) {
                    modal.hide();
                }
                
                // Atualizar sidebar
                if (window.currentConversation && window.currentConversation.contact_id == contactId) {
                    // Recarregar dados do contato
                    fetch(`<?= \App\Helpers\Url::to('/contacts') ?>/${contactId}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    })
                    .then(r => r.json())
                    .then(result => {
                        if (result.success && result.contact) {
                            // Atualizar campos no sidebar
                            const sidebar = document.getElementById('kt_conversation_sidebar');
                            if (sidebar) {
                                const nameEl = sidebar.querySelector('[data-field="contact_name"]');
                                const emailEl = sidebar.querySelector('[data-field="contact_email"]');
                                const phoneEl = sidebar.querySelector('[data-field="contact_phone"]');
                                
                                if (nameEl) {
                                    nameEl.textContent = result.contact.name || '-';
                                }
                                if (emailEl) {
                                    emailEl.textContent = result.contact.email || '-';
                                }
                                if (phoneEl) {
                                    phoneEl.textContent = result.contact.phone || '-';
                                }
                                
                                // Atualizar iniciais
                                const initialsEl = sidebar.querySelector('#sidebar-contact-initials');
                                if (initialsEl && result.contact.name) {
                                    const initials = result.contact.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                                    initialsEl.textContent = initials || 'NN';
                                }
                            }
                        }
                    });
                }
                
                const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                                   document.body.classList.contains('dark-mode') ||
                                   window.matchMedia('(prefers-color-scheme: dark)').matches;
                
                Swal.fire({
                    icon: 'success',
                    title: 'Sucesso!',
                    text: data.message || 'Contato atualizado com sucesso',
                    colorScheme: isDarkMode ? 'dark' : 'light',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                throw new Error(data.message || 'Erro ao atualizar contato');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark' || 
                               document.body.classList.contains('dark-mode') ||
                               window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: error.message || 'Erro ao atualizar contato',
                colorScheme: isDarkMode ? 'dark' : 'light'
            });
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });
}

/**
 * Atualizar banner de IA ativa no topo do chat
 */
function updateAIActiveBanner(status, conversationId) {
    const banner = document.getElementById('aiActiveBanner');
    if (!banner) return;
    
    if (status.has_ai && status.ai_agent) {
        const aiAgent = status.ai_agent;
        const aiConv = status.ai_conversation;
        
        // Obter iniciais do agente
        const parts = aiAgent.name.split(' ');
        const initials = (parts[0].charAt(0) + (parts[1] ? parts[1].charAt(0) : '')).toUpperCase();
        
        // Atualizar conteúdo do banner
        const nameEl = banner.querySelector('.ai-agent-name');
        const typeEl = banner.querySelector('.ai-agent-type');
        const countEl = banner.querySelector('.ai-messages-count');
        
        if (nameEl) nameEl.textContent = aiAgent.name;
        if (typeEl) typeEl.textContent = aiAgent.type || 'GENERAL';
        if (countEl) {
            const count = status.messages_count || 0;
            countEl.textContent = `${count} ${count === 1 ? 'mensagem' : 'mensagens'}`;
        }
        
        // Atualizar onclick dos botões
        const historyBtn = document.getElementById('aiHistoryButton');
        const removeBtn = document.getElementById('removeAIButton');
        
        if (historyBtn) {
            historyBtn.onclick = function() {
                if(typeof showAIHistory === 'function') {
                    showAIHistory();
                } else {
                    console.error('showAIHistory não está disponível');
                }
            };
        }
        if (removeBtn) {
            removeBtn.onclick = function() {
                if(typeof removeAIAgent === 'function') {
                    removeAIAgent();
                } else {
                    console.error('removeAIAgent não está disponível');
                }
            };
        }
        
        // Mostrar banner
        banner.classList.remove('d-none');
        banner.style.display = '';
    } else {
        // Ocultar banner
        banner.classList.add('d-none');
        banner.style.display = 'none';
    }
}
</script>

<!-- SLA Indicator JavaScript -->
<script src="<?= \App\Helpers\Url::asset('js/custom/sla-indicator.js') ?>"></script>

<?php $content = ob_get_clean(); ?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>

