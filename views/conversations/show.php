<?php
$layout = 'layouts.metronic.app';
$title = 'Conversa - ' . htmlspecialchars($conversation['contact_name'] ?? '');
$fullWidth = true; // Remover container para layout full width

// Redirecionar para view Chatwoot com ID na URL
\App\Helpers\Response::redirect('/conversations?id=' . ($conversation['id'] ?? ''));
exit;
?>

<?php ob_start(); ?>

<!--begin::Content-->
<div class="h-100 d-flex flex-column">
        <!--begin::Card-->
        <div class="card card-flush h-100">
            <!--begin::Card header-->
            <div class="card-header align-items-center py-5 gap-2 gap-md-5">
                <div class="card-title">
                    <div class="d-flex align-items-center position-relative my-1">
                        <a href="<?= \App\Helpers\Url::to('/conversations') ?>" class="btn btn-icon btn-sm btn-active-light-primary me-2">
                            <i class="ki-duotone ki-arrow-left fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </a>
                        <div class="symbol symbol-45px me-5">
                            <?php if (!empty($conversation['contact_avatar'])): ?>
                                <img src="<?= htmlspecialchars($conversation['contact_avatar']) ?>" alt="<?= htmlspecialchars($conversation['contact_name'] ?? 'Sem nome') ?>" />
                            <?php else: ?>
                                <div class="symbol-label fs-3 fw-semibold text-primary bg-light-primary">
                                    <?= mb_substr(htmlspecialchars($conversation['contact_name'] ?? 'U'), 0, 1) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex flex-column">
                            <a href="#" class="text-gray-800 text-hover-primary fs-4 fw-bold">
                                <?= htmlspecialchars($conversation['contact_name'] ?? 'Sem nome') ?>
                            </a>
                            <span class="text-muted fs-7"><?= htmlspecialchars($conversation['contact_phone'] ?? '') ?></span>
                            <?php if (!empty($conversation['tags']) && is_array($conversation['tags'])): ?>
                                <div class="d-flex flex-wrap gap-1 mt-1">
                                    <?php foreach ($conversation['tags'] as $tag): ?>
                                        <span class="badge" style="background-color: <?= htmlspecialchars($tag['color'] ?? '#009ef7') ?>20; color: <?= htmlspecialchars($tag['color'] ?? '#009ef7') ?>;">
                                            <?= htmlspecialchars($tag['name']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-toolbar flex-row-fluid justify-content-end gap-5">
                    <div class="d-flex align-items-center gap-2">
                        <?php
                        $statusClass = 'secondary';
                        $statusText = 'Desconhecido';
                        switch($conversation['status'] ?? 'unknown') {
                            case 'open':
                                $statusClass = 'success';
                                $statusText = 'Aberta';
                                break;
                            case 'resolved':
                                $statusClass = 'info';
                                $statusText = 'Resolvida';
                                break;
                            case 'closed':
                                $statusClass = 'dark';
                                $statusText = 'Fechada';
                                break;
                        }
                        ?>
                        <span class="badge badge-light-<?= $statusClass ?> fs-7"><?= $statusText ?></span>
                        
                        <?php if ($conversation['status'] === 'open'): ?>
                            <button type="button" class="btn btn-sm btn-light-danger" onclick="closeConversation(<?= $conversation['id'] ?>)">
                                <i class="ki-duotone ki-cross fs-2"></i>
                                Fechar
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-sm btn-light-success" onclick="reopenConversation(<?= $conversation['id'] ?>)">
                                <i class="ki-duotone ki-check fs-2"></i>
                                Reabrir
                            </button>
                        <?php endif; ?>
                        
                        <button type="button" class="btn btn-sm btn-light-info" data-bs-toggle="modal" data-bs-target="#kt_modal_attachments_gallery" onclick="loadAttachmentsGallery(<?= $conversation['id'] ?>)">
                            <i class="ki-duotone ki-picture fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            Anexos
                        </button>
                        <button type="button" class="btn btn-sm btn-light-warning" data-bs-toggle="modal" data-bs-target="#kt_modal_manage_tags" onclick="loadConversationTags(<?= $conversation['id'] ?>)">
                            <i class="ki-duotone ki-bookmark fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Tags
                        </button>
                        <button type="button" class="btn btn-sm btn-light-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_assign_conversation">
                            <i class="ki-duotone ki-user fs-2"></i>
                            Atribuir
                        </button>
                    </div>
                </div>
            </div>
            <!--end::Card header-->
            
            <!--begin::Card body-->
            <div class="card-body pt-0 d-flex flex-column h-100">
                <!--begin::Messages-->
                <div class="messages-container flex-grow-1 overflow-auto p-5" style="max-height: calc(100vh - 300px);">
                    <?php if (empty($messages)): ?>
                        <div class="text-center py-20">
                            <i class="ki-duotone ki-chat-text fs-3x text-gray-400 mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                            <h3 class="text-gray-800 fw-bold mb-2">Nenhuma mensagem ainda</h3>
                            <div class="text-gray-500 fs-6">Comece a conversa enviando uma mensagem.</div>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-5">
                            <?php 
                            $lastDate = '';
                            foreach ($messages as $msg): 
                                $msgDate = date('d/m/Y', strtotime($msg['created_at']));
                                $showDate = $msgDate !== $lastDate;
                                $lastDate = $msgDate;
                            ?>
                                <?php if ($showDate): ?>
                                    <div class="text-center my-5">
                                        <span class="badge badge-light-info fs-7"><?= $msgDate ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-flex align-items-start gap-3 <?= $msg['sender_type'] === 'agent' ? 'flex-row-reverse' : '' ?>">
                                    <div class="symbol symbol-35px">
                                        <?php if (!empty($msg['sender_avatar'])): ?>
                                            <img src="<?= htmlspecialchars($msg['sender_avatar']) ?>" alt="<?= htmlspecialchars($msg['sender_name'] ?? 'Desconhecido') ?>" />
                                        <?php else: ?>
                                            <div class="symbol-label fs-2 fw-semibold text-primary bg-light-primary">
                                                <?= mb_substr(htmlspecialchars($msg['sender_name'] ?? 'U'), 0, 1) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex flex-column <?= $msg['sender_type'] === 'agent' ? 'align-items-end' : 'align-items-start' ?>" style="max-width: 70%;">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="text-gray-800 fw-bold fs-6"><?= htmlspecialchars($msg['sender_name'] ?? 'Desconhecido') ?></span>
                                            <span class="text-muted fs-7"><?= date('H:i', strtotime($msg['created_at'])) ?></span>
                                        </div>
                                        <div class="p-5 rounded <?= $msg['sender_type'] === 'agent' ? 'bg-primary text-white' : 'bg-light-primary' ?>">
                                            <?php if (!empty($msg['content'])): ?>
                                                <div class="fs-6 mb-3"><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
                                            <?php endif; ?>
                                            
                                            <?php
                                            // Exibir anexos se houver
                                            $attachments = [];
                                            if (!empty($msg['attachments'])) {
                                                $attachments = is_string($msg['attachments']) 
                                                    ? json_decode($msg['attachments'], true) ?? [] 
                                                    : $msg['attachments'];
                                            }
                                            
                                            if (!empty($attachments)):
                                            ?>
                                                <div class="d-flex flex-column gap-2 mt-3">
                                                    <?php foreach ($attachments as $attachment): ?>
                                                        <?php if ($attachment['type'] === 'image'): ?>
                                                            <div class="attachment-image">
                                                                <a href="javascript:void(0)" onclick="openImageLightbox('<?= htmlspecialchars($attachment['url']) ?>', '<?= htmlspecialchars(addslashes($attachment['original_name'] ?? 'Imagem')) ?>')" class="d-block">
                                                                    <img src="<?= htmlspecialchars($attachment['url']) ?>" 
                                                                         alt="<?= htmlspecialchars($attachment['original_name'] ?? 'Imagem') ?>" 
                                                                         class="rounded" 
                                                                         style="max-width: 300px; max-height: 300px; cursor: pointer;" />
                                                                </a>
                                                                <div class="text-muted fs-7 mt-1">
                                                                    <?= htmlspecialchars($attachment['original_name'] ?? 'Imagem') ?>
                                                                    (<?= formatFileSize($attachment['size'] ?? 0) ?>)
                                                                </div>
                                                            </div>
                                                        <?php elseif ($attachment['type'] === 'video'): ?>
                                                            <div class="attachment-video">
                                                                <video controls style="max-width: 400px; max-height: 300px;" class="rounded">
                                                                    <source src="<?= htmlspecialchars($attachment['url']) ?>" type="<?= htmlspecialchars($attachment['mime_type'] ?? 'video/mp4') ?>">
                                                                    Seu navegador não suporta o elemento de vídeo.
                                                                </video>
                                                                <div class="text-muted fs-7 mt-1">
                                                                    <?= htmlspecialchars($attachment['original_name'] ?? 'Vídeo') ?>
                                                                    (<?= formatFileSize($attachment['size'] ?? 0) ?>)
                                                                </div>
                                                            </div>
                                                        <?php elseif ($attachment['type'] === 'audio'): ?>
                                                            <div class="attachment-audio">
                                                                <audio controls class="w-100">
                                                                    <source src="<?= htmlspecialchars($attachment['url']) ?>" type="<?= htmlspecialchars($attachment['mime_type'] ?? 'audio/mpeg') ?>">
                                                                    Seu navegador não suporta o elemento de áudio.
                                                                </audio>
                                                                <div class="text-muted fs-7 mt-1">
                                                                    <?= htmlspecialchars($attachment['original_name'] ?? 'Áudio') ?>
                                                                    (<?= formatFileSize($attachment['size'] ?? 0) ?>)
                                                                </div>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="attachment-document d-flex align-items-center gap-3 p-3 bg-light rounded">
                                                                <i class="ki-duotone ki-file fs-2x text-primary">
                                                                    <span class="path1"></span>
                                                                    <span class="path2"></span>
                                                                </i>
                                                                <div class="flex-grow-1">
                                                                    <div class="fw-bold"><?= htmlspecialchars($attachment['original_name'] ?? 'Documento') ?></div>
                                                                    <div class="text-muted fs-7"><?= formatFileSize($attachment['size'] ?? 0) ?></div>
                                                                </div>
                                                                <a href="<?= htmlspecialchars($attachment['url']) ?>" 
                                                                   download="<?= htmlspecialchars($attachment['original_name'] ?? 'arquivo') ?>" 
                                                                   class="btn btn-sm btn-light-primary">
                                                                    <i class="ki-duotone ki-download fs-2">
                                                                        <span class="path1"></span>
                                                                        <span class="path2"></span>
                                                                    </i>
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <!--end::Messages-->
                
                <!--begin::Input-->
                <div class="border-top p-5">
                    <form method="POST" action="<?= \App\Helpers\Url::to('/conversations/' . $conversation['id'] . '/messages') ?>" id="kt_chat_form" enctype="multipart/form-data">
                        <!-- Preview de anexos selecionados -->
                        <div id="kt_attachments_preview" class="mb-3 d-none">
                            <div class="d-flex flex-wrap gap-2" id="kt_attachments_list"></div>
                        </div>
                        
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="document.getElementById('kt_attachment_input').click()" title="Anexar arquivo">
                                <i class="ki-duotone ki-paperclip fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </button>
                            <input type="file" id="kt_attachment_input" name="attachments[]" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv" style="display: none;" onchange="handleAttachments(this.files)" />
                            
                            <div class="position-relative flex-grow-1">
                                <textarea class="form-control form-control-solid" 
                                          name="content" 
                                          id="kt_chat_message"
                                          rows="1" 
                                          placeholder="Digite sua mensagem..." 
                                          style="resize: none; min-height: 50px;"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary" id="kt_chat_send">
                                <i class="ki-duotone ki-send fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </button>
                        </div>
                        <div class="form-text mt-2">
                            Formatos aceitos: Imagens (JPG, PNG, GIF), Vídeos (MP4), Áudios (MP3), Documentos (PDF, DOC, XLS). Máximo: 10MB por arquivo.
                        </div>
                    </form>
                </div>
                <!--end::Input-->
            </div>
            <!--end::Card body-->
        </div>
        <!--end::Card-->
        
        <!--begin::Modal - Gerenciar Tags-->
        <div class="modal fade" id="kt_modal_manage_tags" tabindex="-1" aria-hidden="true">
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
                    <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                        <div id="kt_tags_list" class="d-flex flex-wrap gap-2 mb-5">
                            <!-- Tags serão carregadas aqui -->
                        </div>
                        <div class="separator separator-dashed my-5"></div>
                        <div>
                            <label class="form-label fw-semibold">Adicionar Tag:</label>
                            <select id="kt_add_tag_select" class="form-select form-select-solid">
                                <option value="">Selecione uma tag...</option>
                                <?php if (!empty($allTags)): ?>
                                    <?php foreach ($allTags as $tag): ?>
                                        <option value="<?= $tag['id'] ?>" data-color="<?= htmlspecialchars($tag['color'] ?? '#009ef7') ?>">
                                            <?= htmlspecialchars($tag['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                        <button type="button" class="btn btn-primary" onclick="addTagToConversation()">
                            <i class="ki-duotone ki-plus fs-2"></i>
                            Adicionar Tag
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Modal - Gerenciar Tags-->
        
        <!--begin::Modal - Galeria de Anexos-->
        <div class="modal fade" id="kt_modal_attachments_gallery" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="fw-bold">Galeria de Anexos</h2>
                        <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                            <i class="ki-duotone ki-cross fs-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>
                    <div class="modal-body">
                        <div id="kt_attachments_gallery_content" class="text-center py-10">
                            <span class="spinner-border spinner-border-sm text-primary"></span>
                            <span class="ms-2">Carregando anexos...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Modal - Galeria de Anexos-->
        
        <!--begin::Modal - Lightbox de Imagem-->
        <div class="modal fade" id="kt_modal_image_lightbox" tabindex="-1" aria-hidden="true" style="z-index: 99999 !important;">
            <div class="modal-dialog modal-dialog-centered modal-fullscreen" style="z-index: 100000 !important;">
                <div class="modal-content bg-dark">
                    <div class="modal-header border-0">
                        <h2 class="fw-bold text-white" id="kt_lightbox_image_title">Imagem</h2>
                        <div class="btn btn-icon btn-sm btn-active-icon-danger" data-bs-dismiss="modal">
                            <i class="ki-duotone ki-cross fs-1 text-white">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>
                    <div class="modal-body d-flex align-items-center justify-content-center">
                        <img id="kt_lightbox_image_src" src="" alt="" class="img-fluid" style="max-height: 90vh; max-width: 100%;" />
                    </div>
                </div>
            </div>
        </div>
        <!--end::Modal - Lightbox de Imagem-->
</div>

<!--begin::Modal - Atribuir Conversa-->
<div class="modal fade" id="kt_modal_assign_conversation" tabindex="-1" aria-hidden="true">
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
            <form id="kt_modal_assign_form" class="form">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Agente</label>
                        <select name="agent_id" class="form-select form-select-solid" required>
                            <option value="">Selecione um agente</option>
                            <?php
                            $agents = \App\Models\User::getActiveAgents();
                            foreach ($agents as $agent):
                            ?>
                                <option value="<?= $agent['id'] ?>" <?= ($conversation['agent_id'] ?? null) == $agent['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($agent['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_assign_submit" class="btn btn-primary">
                        <span class="indicator-label">Atribuir</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal - Atribuir Conversa-->

<?php
// Função helper para formatar tamanho de arquivo
function formatFileSize($bytes) {
    if ($bytes === 0) return "0 Bytes";
    $k = 1024;
    $sizes = ["Bytes", "KB", "MB", "GB"];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i) * 100) / 100 . " " . $sizes[$i];
}
?>

<?php 
$content = ob_get_clean(); 
$scripts = '
<script>
// Auto-resize textarea
document.addEventListener("DOMContentLoaded", function() {
    const textarea = document.getElementById("kt_chat_message");
    if (textarea) {
        textarea.addEventListener("input", function() {
            this.style.height = "auto";
            this.style.height = (this.scrollHeight) + "px";
        });
    }
    
    // Scroll to bottom
    const messagesContainer = document.querySelector(".messages-container");
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    // Gerenciar anexos selecionados
    window.selectedAttachments = [];
    
    window.handleAttachments = function(files) {
        const preview = document.getElementById("kt_attachments_preview");
        const list = document.getElementById("kt_attachments_list");
        
        if (!files || files.length === 0) {
            preview.classList.add("d-none");
            window.selectedAttachments = [];
            return;
        }
        
        preview.classList.remove("d-none");
        list.innerHTML = "";
        window.selectedAttachments = Array.from(files);
        
        Array.from(files).forEach((file, index) => {
            const div = document.createElement("div");
            div.className = "d-flex align-items-center gap-2 p-2 bg-light rounded";
            div.style.maxWidth = "300px";
            
            let previewContent = "";
            if (file.type.startsWith("image/")) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    div.querySelector("img").src = e.target.result;
                };
                reader.readAsDataURL(file);
                previewContent = `<img src="" alt="Preview" style="max-width: 50px; max-height: 50px; object-fit: cover;" class="rounded" />`;
            } else {
                previewContent = `<i class="ki-duotone ki-file fs-2x text-primary">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>`;
            }
            
            div.innerHTML = `
                ${previewContent}
                <div class="flex-grow-1">
                    <div class="fw-bold fs-7 text-truncate" style="max-width: 150px;">${file.name}</div>
                    <div class="text-muted fs-8">${formatFileSize(file.size)}</div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-light-danger" onclick="removeAttachment(${index})">
                    <i class="ki-duotone ki-cross fs-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </button>
            `;
            
            list.appendChild(div);
        });
    };
    
    window.removeAttachment = function(index) {
        window.selectedAttachments.splice(index, 1);
        const input = document.getElementById("kt_attachment_input");
        const dt = new DataTransfer();
        window.selectedAttachments.forEach(file => dt.items.add(file));
        input.files = dt.files;
        handleAttachments(input.files);
    };
    
    window.formatFileSize = function(bytes) {
        if (bytes === 0) return "0 Bytes";
        const k = 1024;
        const sizes = ["Bytes", "KB", "MB", "GB"];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + " " + sizes[i];
    };
    
    // Galeria de anexos
    window.loadAttachmentsGallery = function(conversationId) {
        const content = document.getElementById("kt_attachments_gallery_content");
        content.innerHTML = \'<div class="text-center py-10"><span class="spinner-border spinner-border-sm text-primary"></span><span class="ms-2">Carregando anexos...</span></div>\';
        
        fetch(\'' . \App\Helpers\Url::to('/attachments/conversation') . '/\' + conversationId, {
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.attachments && data.attachments.length > 0) {
                let html = \'<div class="row g-4">\';
                
                data.attachments.forEach(attachment => {
                    const date = new Date(attachment.message_created_at).toLocaleString(\'pt-BR\');
                    const senderLabel = attachment.sender_type === \'agent\' ? \'Agente\' : \'Contato\';
                    const safeName = attachment.original_name.replace(/"/g, \'&quot;\').replace(/\'/g, "&#39;");
                    
                    if (attachment.type === 'image') {
                        html += `
                            <div class="col-md-3 col-sm-4 col-6">
                                <div class="card card-flush h-100">
                                    <div class="card-body p-2">
                                        <a href="javascript:void(0)" onclick="openImageLightbox('${attachment.url}', '${safeName}')" class="d-block">
                                            <img src="${attachment.url}" alt="${safeName}" class="w-100 rounded" style="height: 150px; object-fit: cover; cursor: pointer;" />
                                        </a>
                                        <div class="mt-2">
                                            <div class="fw-bold fs-7 text-truncate" title="${safeName}">${attachment.original_name}</div>
                                            <div class="text-muted fs-8">${formatFileSize(attachment.size)}</div>
                                            <div class="text-muted fs-8">${date}</div>
                                            <div class="badge badge-light-${attachment.sender_type === 'agent' ? 'primary' : 'info'} fs-8 mt-1">${senderLabel}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else if (attachment.type === 'video') {
                        html += `
                            <div class="col-md-3 col-sm-4 col-6">
                                <div class="card card-flush h-100">
                                    <div class="card-body p-2">
                                        <video controls class="w-100 rounded" style="height: 150px;">
                                            <source src="${attachment.url}" type="${attachment.mime_type}">
                                        </video>
                                        <div class="mt-2">
                                            <div class="fw-bold fs-7 text-truncate" title="${safeName}">${attachment.original_name}</div>
                                            <div class="text-muted fs-8">${formatFileSize(attachment.size)}</div>
                                            <div class="text-muted fs-8">${date}</div>
                                            <div class="badge badge-light-${attachment.sender_type === 'agent' ? 'primary' : 'info'} fs-8 mt-1">${senderLabel}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else if (attachment.type === 'audio') {
                        html += `
                            <div class="col-md-3 col-sm-4 col-6">
                                <div class="card card-flush h-100">
                                    <div class="card-body p-2">
                                        <audio controls class="w-100">
                                            <source src="${attachment.url}" type="${attachment.mime_type}">
                                        </audio>
                                        <div class="mt-2">
                                            <div class="fw-bold fs-7 text-truncate" title="${safeName}">${attachment.original_name}</div>
                                            <div class="text-muted fs-8">${formatFileSize(attachment.size)}</div>
                                            <div class="text-muted fs-8">${date}</div>
                                            <div class="badge badge-light-${attachment.sender_type === 'agent' ? 'primary' : 'info'} fs-8 mt-1">${senderLabel}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        html += `
                            <div class="col-md-3 col-sm-4 col-6">
                                <div class="card card-flush h-100">
                                    <div class="card-body p-2 d-flex flex-column">
                                        <div class="text-center mb-2">
                                            <i class="ki-duotone ki-file fs-3x text-primary">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold fs-7 text-truncate" title="${safeName}">${attachment.original_name}</div>
                                            <div class="text-muted fs-8">${formatFileSize(attachment.size)}</div>
                                            <div class="text-muted fs-8">${date}</div>
                                            <div class="badge badge-light-${attachment.sender_type === 'agent' ? 'primary' : 'info'} fs-8 mt-1">${senderLabel}</div>
                                        </div>
                                        <a href="${attachment.url}" download="${safeName}" class="btn btn-sm btn-light-primary mt-2">
                                            <i class="ki-duotone ki-download fs-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                });
                
                html += '</div>';
                content.innerHTML = html;
            } else {
                content.innerHTML = '<div class="text-center py-20"><i class="ki-duotone ki-picture fs-3x text-gray-400 mb-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i><h3 class="text-gray-800 fw-bold mb-2">Nenhum anexo encontrado</h3><div class="text-gray-500 fs-6">Esta conversa não possui anexos.</div></div>';
            }
        })
        .catch(error => {
            console.error("Erro ao carregar anexos:", error);
            content.innerHTML = '<div class="alert alert-danger">Erro ao carregar anexos. Tente novamente.</div>';
        });
    };
    
    // Lightbox de imagem
    window.openImageLightbox = function(imageUrl, imageTitle) {
        document.getElementById("kt_lightbox_image_src").src = imageUrl;
        document.getElementById("kt_lightbox_image_title").textContent = imageTitle || "Imagem";
        const modal = new bootstrap.Modal(document.getElementById("kt_modal_image_lightbox"));
        modal.show();
    };
    
    // Form submit com AJAX
    const form = document.getElementById("kt_chat_form");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const content = textarea.value.trim();
            const hasAttachments = window.selectedAttachments.length > 0;
            
            if (!content && !hasAttachments) {
                alert("Digite uma mensagem ou anexe um arquivo");
                return;
            }
            
            const btn = document.getElementById("kt_chat_send");
            const originalHtml = btn.innerHTML;
            
            btn.disabled = true;
            btn.innerHTML = "<span class=\"spinner-border spinner-border-sm\"></span>";
            
            const formData = new FormData();
            if (content) {
                formData.append("content", content);
            }
            
            // Adicionar anexos
            window.selectedAttachments.forEach((file, index) => {
                formData.append("attachments[]", file);
            });
            
            fetch(form.action, {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                
                if (data.success) {
                    textarea.value = "";
                    textarea.style.height = "auto";
                    window.selectedAttachments = [];
                    document.getElementById("kt_attachment_input").value = "";
                    document.getElementById("kt_attachments_preview").classList.add("d-none");
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao enviar mensagem"));
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                alert("Erro ao enviar mensagem");
            });
        });
    }
    
    // Form de atribuição
    const assignForm = document.getElementById("kt_modal_assign_form");
    if (assignForm) {
        assignForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const agentId = this.querySelector("[name=\"agent_id\"]").value;
            if (!agentId) return;
            
            const submitBtn = document.getElementById("kt_modal_assign_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData();
            formData.append("agent_id", agentId);
            
            fetch("' . \App\Helpers\Url::to('/conversations/' . $conversation['id'] . '/assign') . '", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_assign_conversation"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao atribuir conversa"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao atribuir conversa");
            });
        });
    }
});

// Fechar conversa
function closeConversation(id) {
    if (!confirm("Tem certeza que deseja fechar esta conversa?")) return;
    
    const formData = new FormData();
    fetch("' . \App\Helpers\Url::to('/conversations/' . $conversation['id'] . '/close') . '", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert("Erro: " + (data.message || "Erro ao fechar conversa"));
        }
    });
}

// Reabrir conversa
function reopenConversation(id) {
    const formData = new FormData();
    fetch("' . \App\Helpers\Url::to('/conversations/' . $conversation['id'] . '/reopen') . '", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert("Erro: " + (data.message || "Erro ao reabrir conversa"));
        }
    });
}
</script>';
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>
