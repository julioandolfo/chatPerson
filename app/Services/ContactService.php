<?php
/**
 * Service ContactService
 * Lógica de negócio para contatos
 */

namespace App\Services;

use App\Models\Contact;
use App\Helpers\Validator;

class ContactService
{
    /**
     * Criar ou atualizar contato
     */
    public static function createOrUpdate(array $data): array
    {
        // Validar dados
        $errors = Validator::validate($data, [
            'name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'whatsapp_id' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
            'bio' => 'nullable|string',
            'company' => 'nullable|string|max:255',
            'social_media' => 'nullable|array',
            'avatar' => 'nullable|string|max:255'
        ]);

        // Converter erros para array simples
        $flatErrors = [];
        foreach ($errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $flatErrors[] = $error;
            }
        }
        
        if (!empty($flatErrors)) {
            throw new \Exception('Dados inválidos: ' . implode(', ', $flatErrors));
        }

        // Buscar ou criar contato
        $contact = Contact::findOrCreate($data);
        
        // Se foi criado novo contato, executar automações
        if ($contact && isset($contact['id'])) {
            // Verificar se é novo (comparar created_at com agora)
            $isNew = strtotime($contact['created_at']) > (time() - 5); // Criado há menos de 5 segundos
            
            if ($isNew) {
                try {
                    \App\Services\AutomationService::executeForContactCreated($contact['id']);
                } catch (\Exception $e) {
                    error_log("Erro ao executar automações: " . $e->getMessage());
                }
            }
        }
        
        return $contact;
    }

    /**
     * Listar contatos com busca
     */
    public static function list(array $filters = []): array
    {
        $sql = "SELECT * FROM contacts WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? OR company LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= " ORDER BY name ASC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET " . (int)$filters['offset'];
            }
        }

        return \App\Helpers\Database::fetchAll($sql, $params);
    }

    /**
     * Atualizar contato
     */
    public static function update(int $id, array $data): array
    {
        // Validar dados
        $errors = Validator::validate($data, [
            'name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'whatsapp_id' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
            'bio' => 'nullable|string',
            'company' => 'nullable|string|max:255',
            'social_media' => 'nullable|array',
            'avatar' => 'nullable|string|max:255'
        ]);
        
        // Processar social_media se for array ou string JSON
        if (isset($data['social_media'])) {
            if (is_array($data['social_media'])) {
                $data['social_media'] = json_encode($data['social_media']);
            } elseif (is_string($data['social_media'])) {
                // Se já é string JSON, manter como está
                // Se for string vazia, converter para array vazio
                if (empty($data['social_media'])) {
                    $data['social_media'] = json_encode([]);
                }
            }
        } else {
            $data['social_media'] = json_encode([]);
        }

        // Converter erros para array simples
        $flatErrors = [];
        foreach ($errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $flatErrors[] = $error;
            }
        }
        
        if (!empty($flatErrors)) {
            throw new \Exception('Dados inválidos: ' . implode(', ', $flatErrors));
        }

        // Obter contato antes da atualização para detectar mudanças
        $oldContact = Contact::find($id);
        
        Contact::update($id, $data);
        
        // Detectar mudanças
        $changes = [];
        if ($oldContact) {
            foreach ($data as $key => $value) {
                if (isset($oldContact[$key]) && $oldContact[$key] != $value) {
                    $changes[$key] = $value;
                }
            }
        }
        
        $contact = Contact::find($id);
        
        // Executar automações para contato atualizado
        if (!empty($changes)) {
            try {
                \App\Services\AutomationService::executeForContactUpdated($id, $changes);
            } catch (\Exception $e) {
                error_log("Erro ao executar automações: " . $e->getMessage());
            }
        }
        
        return $contact;
    }

    /**
     * Upload de avatar
     */
    public static function uploadAvatar(int $contactId, array $file): string
    {
        // Validar arquivo
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception('Erro ao fazer upload do arquivo');
        }

        // Validar tipo de arquivo
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            throw new \Exception('Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WEBP');
        }

        // Validar tamanho (2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            throw new \Exception('Arquivo muito grande. Tamanho máximo: 2MB');
        }

        // Criar diretório se não existir
        $uploadDir = __DIR__ . '/../../public/assets/media/avatars/contacts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Gerar nome único
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'contact_' . $contactId . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        // Mover arquivo
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new \Exception('Erro ao salvar arquivo');
        }

        // Remover avatar antigo se existir
        $contact = Contact::find($contactId);
        if ($contact && !empty($contact['avatar'])) {
            $oldPath = __DIR__ . '/../../public' . str_replace(\App\Helpers\Url::basePath(), '', $contact['avatar']);
            if (file_exists($oldPath) && strpos($oldPath, 'contacts/') !== false) {
                @unlink($oldPath);
            }
        }

        // Retornar URL relativa
        $avatarUrl = \App\Helpers\Url::asset('media/avatars/contacts/' . $filename);
        
        // Atualizar contato
        Contact::update($contactId, ['avatar' => $avatarUrl]);

        return $avatarUrl;
    }

    /**
     * Buscar avatar do WhatsApp via Quepasa
     */
    public static function fetchWhatsAppAvatar(int $contactId, int $whatsappAccountId): ?string
    {
        try {
            $contact = Contact::find($contactId);
            if (!$contact || empty($contact['phone']) && empty($contact['whatsapp_id'])) {
                return null;
            }

            $phone = $contact['whatsapp_id'] ?? $contact['phone'];
            $phone = str_replace('@s.whatsapp.net', '', $phone);
            $phone = preg_replace('/[^0-9]/', '', $phone); // Remover caracteres não numéricos

            if (empty($phone)) {
                return null;
            }

            $account = \App\Models\WhatsAppAccount::find($whatsappAccountId);
            if (!$account || $account['provider'] !== 'quepasa' || empty($account['quepasa_token'])) {
                return null;
            }

            // Tentar buscar avatar via Quepasa API
            // Nota: A API do Quepasa pode não ter endpoint direto para avatar
            // Vamos tentar alguns endpoints comuns
            $apiUrl = rtrim($account['api_url'], '/');
            $endpoints = [
                "/profile-picture/{$phone}",
                "/contact/{$phone}/picture",
                "/avatar/{$phone}",
                "/picture/{$phone}"
            ];

            foreach ($endpoints as $endpoint) {
                $url = $apiUrl . $endpoint;
                $headers = [
                    'Accept: image/*',
                    'X-QUEPASA-TOKEN: ' . $account['quepasa_token'],
                    'X-QUEPASA-TRACKID: ' . ($account['quepasa_trackid'] ?? $account['name'])
                ];

                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200 && !empty($response)) {
                    // Salvar avatar
                    $uploadDir = __DIR__ . '/../../public/assets/media/avatars/contacts/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $filename = 'whatsapp_' . $contactId . '_' . time() . '.jpg';
                    $filepath = $uploadDir . $filename;

                    if (file_put_contents($filepath, $response)) {
                        $avatarUrl = \App\Helpers\Url::asset('media/avatars/contacts/' . $filename);
                        Contact::update($contactId, ['avatar' => $avatarUrl]);
                        return $avatarUrl;
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            \App\Helpers\Logger::error("Erro ao buscar avatar do WhatsApp: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Buscar avatar do WhatsApp via Quepasa usando chat.id
     */
    public static function fetchWhatsAppAvatarByChatId(int $contactId, int $whatsappAccountId, string $chatId): ?string
    {
        try {
            $account = \App\Models\WhatsAppAccount::find($whatsappAccountId);
            if (!$account || $account['provider'] !== 'quepasa' || empty($account['quepasa_token'])) {
                return null;
            }

            // Tentar buscar avatar usando chat.id diretamente
            $apiUrl = rtrim($account['api_url'], '/');
            $endpoints = [
                "/profile-picture/{$chatId}",
                "/contact/{$chatId}/picture",
                "/avatar/{$chatId}",
                "/picture/{$chatId}"
            ];

            foreach ($endpoints as $endpoint) {
                $url = $apiUrl . $endpoint;
                $headers = [
                    'Accept: image/*',
                    'X-QUEPASA-TOKEN: ' . $account['quepasa_token'],
                    'X-QUEPASA-TRACKID: ' . ($account['quepasa_trackid'] ?? $account['name'])
                ];

                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200 && !empty($response)) {
                    // Salvar avatar
                    $uploadDir = __DIR__ . '/../../public/assets/media/avatars/contacts/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $filename = 'whatsapp_' . $contactId . '_' . time() . '.jpg';
                    $filepath = $uploadDir . $filename;

                    if (file_put_contents($filepath, $response)) {
                        $avatarUrl = \App\Helpers\Url::asset('media/avatars/contacts/' . $filename);
                        Contact::update($contactId, ['avatar' => $avatarUrl]);
                        return $avatarUrl;
                    }
                }
            }

            // Se não conseguir com chat.id, tentar método padrão
            return self::fetchWhatsAppAvatar($contactId, $whatsappAccountId);
        } catch (\Exception $e) {
            \App\Helpers\Logger::error("Erro ao buscar avatar do WhatsApp por chat.id: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Baixar avatar de uma URL e salvar
     */
    public static function downloadAvatarFromUrl(int $contactId, string $avatarUrl): ?string
    {
        try {
            if (empty($avatarUrl)) {
                return null;
            }

            $ch = curl_init($avatarUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            if ($httpCode === 200 && !empty($response)) {
                // Determinar extensão baseado no content-type ou URL
                $extension = 'jpg';
                if (strpos($contentType, 'png') !== false) {
                    $extension = 'png';
                } elseif (strpos($contentType, 'gif') !== false) {
                    $extension = 'gif';
                } elseif (strpos($contentType, 'webp') !== false) {
                    $extension = 'webp';
                } else {
                    // Tentar extrair da URL
                    $pathInfo = pathinfo(parse_url($avatarUrl, PHP_URL_PATH));
                    if (!empty($pathInfo['extension'])) {
                        $extension = $pathInfo['extension'];
                    }
                }

                // Salvar avatar
                $uploadDir = __DIR__ . '/../../public/assets/media/avatars/contacts/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $filename = 'whatsapp_' . $contactId . '_' . time() . '.' . $extension;
                $filepath = $uploadDir . $filename;

                if (file_put_contents($filepath, $response)) {
                    $avatarUrl = \App\Helpers\Url::asset('media/avatars/contacts/' . $filename);
                    Contact::update($contactId, ['avatar' => $avatarUrl]);
                    return $avatarUrl;
                }
            }

            return null;
        } catch (\Exception $e) {
            \App\Helpers\Logger::error("Erro ao baixar avatar de URL: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Salvar avatar a partir de base64
     */
    public static function saveAvatarFromBase64(int $contactId, string $base64Data, string $mimeType = 'image/jpeg'): ?string
    {
        try {
            if (empty($base64Data)) {
                return null;
            }

            // Remover prefixo data:image/xxx;base64, se presente
            if (strpos($base64Data, 'base64,') !== false) {
                $base64Data = explode('base64,', $base64Data)[1];
            }

            // Decodificar base64
            $imageData = base64_decode($base64Data, true);
            if ($imageData === false) {
                \App\Helpers\Logger::error("Erro ao decodificar base64 do avatar para contato {$contactId}");
                return null;
            }

            // Determinar extensão baseado no mime type
            $extension = 'jpg';
            if (strpos($mimeType, 'png') !== false) {
                $extension = 'png';
            } elseif (strpos($mimeType, 'gif') !== false) {
                $extension = 'gif';
            } elseif (strpos($mimeType, 'webp') !== false) {
                $extension = 'webp';
            }

            // Criar diretório se não existir
            $uploadDir = __DIR__ . '/../../public/assets/media/avatars/contacts/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Remover avatar antigo se existir
            $contact = Contact::find($contactId);
            if ($contact && !empty($contact['avatar'])) {
                $oldPath = __DIR__ . '/../../public' . str_replace(\App\Helpers\Url::basePath(), '', $contact['avatar']);
                if (file_exists($oldPath) && strpos($oldPath, 'contacts/') !== false) {
                    @unlink($oldPath);
                }
            }

            // Salvar arquivo
            $filename = 'whatsapp_' . $contactId . '_' . time() . '.' . $extension;
            $filepath = $uploadDir . $filename;

            if (file_put_contents($filepath, $imageData)) {
                $avatarUrl = \App\Helpers\Url::asset('media/avatars/contacts/' . $filename);
                Contact::update($contactId, ['avatar' => $avatarUrl]);
                \App\Helpers\Logger::quepasa("Avatar salvo a partir de base64 para contato {$contactId}: {$avatarUrl}");
                return $avatarUrl;
            }

            return null;
        } catch (\Exception $e) {
            \App\Helpers\Logger::error("Erro ao salvar avatar base64: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Deletar contato
     * @param bool $force Se true, deleta mesmo com conversas (apenas para admin global)
     */
    public static function delete(int $id, bool $force = false): bool
    {
        // Verificar se contato tem conversas
        $conversations = \App\Models\Conversation::where('contact_id', '=', $id);
        if (!empty($conversations) && !$force) {
            throw new \Exception('Não é possível deletar contato com conversas associadas');
        }
        
        // Se force=true, deletar conversas primeiro
        if ($force && !empty($conversations)) {
            foreach ($conversations as $conv) {
                // Deletar mensagens
                \App\Helpers\Database::query("DELETE FROM messages WHERE conversation_id = ?", [$conv['id']]);
                // Deletar tags
                \App\Helpers\Database::query("DELETE FROM conversation_tags WHERE conversation_id = ?", [$conv['id']]);
                // Deletar conversa
                \App\Models\Conversation::delete($conv['id']);
            }
        }

        return Contact::delete($id);
    }
}

