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
        \App\Helpers\Logger::info("[ContactService::createOrUpdate] === INÍCIO ===");
        \App\Helpers\Logger::info("[ContactService::createOrUpdate] Dados recebidos: " . json_encode($data, JSON_UNESCAPED_UNICODE));
        
        // Normalizar número de telefone ANTES de validar
        if (!empty($data['phone'])) {
            $originalPhone = $data['phone'];
            $data['phone'] = Contact::normalizePhoneNumber($data['phone']);
            \App\Helpers\Logger::info("[ContactService::createOrUpdate] Telefone normalizado: '{$originalPhone}' -> '{$data['phone']}'");
        }
        
        // Validar dados (após normalização)
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
            \App\Helpers\Logger::info("[ContactService::createOrUpdate] ERRO DE VALIDAÇÃO: " . implode(', ', $flatErrors));
            throw new \Exception('Dados inválidos: ' . implode(', ', $flatErrors));
        }
        
        \App\Helpers\Logger::info("[ContactService::createOrUpdate] Validação passou. Phone vazio? " . (empty($data['phone']) ? 'SIM' : 'NÃO'));

        // Buscar ou criar contato (usar busca normalizada para evitar duplicatas)
        if (!empty($data['phone'])) {
            \App\Helpers\Logger::info("[ContactService::createOrUpdate] Buscando contato existente por telefone normalizado: {$data['phone']}");
            $existing = Contact::findByPhoneNormalized($data['phone']);
            if ($existing) {
                \App\Helpers\Logger::info("[ContactService::createOrUpdate] Contato EXISTENTE encontrado! ID: {$existing['id']}");
                // Atualizar contato existente
                Contact::update($existing['id'], $data);
                $contact = Contact::find($existing['id']);
                \App\Helpers\Logger::info("[ContactService::createOrUpdate] Contato atualizado");
            } else {
                \App\Helpers\Logger::info("[ContactService::createOrUpdate] Nenhum contato existente, criando NOVO...");
                // Criar novo contato
                $contactId = Contact::create($data);
                \App\Helpers\Logger::info("[ContactService::createOrUpdate] Contact::create() retornou ID: {$contactId}");
                $contact = Contact::find($contactId);
                if ($contact) {
                    \App\Helpers\Logger::info("[ContactService::createOrUpdate] Contato criado e encontrado com sucesso!");
                } else {
                    \App\Helpers\Logger::info("[ContactService::createOrUpdate] ERRO: Contact::find({$contactId}) retornou NULL!");
                }
            }
        } else {
            // Se não tem telefone, usar método padrão
            \App\Helpers\Logger::info("[ContactService::createOrUpdate] Sem telefone, usando Contact::findOrCreate()...");
            $contact = Contact::findOrCreate($data);
            \App\Helpers\Logger::info("[ContactService::createOrUpdate] findOrCreate retornou: " . json_encode($contact, JSON_UNESCAPED_UNICODE));
        }
        
        // Verificar se o contato foi criado/encontrado
        if (empty($contact)) {
            \App\Helpers\Logger::info("[ContactService::createOrUpdate] ERRO CRÍTICO: \$contact está vazio!");
            throw new \Exception('Erro ao criar/encontrar contato: resultado vazio');
        }
        
        // Se foi criado novo contato, executar automações
        if ($contact && isset($contact['id'])) {
            // Verificar se é novo (comparar created_at com agora)
            $isNew = strtotime($contact['created_at']) > (time() - 5); // Criado há menos de 5 segundos
            \App\Helpers\Logger::info("[ContactService::createOrUpdate] Contato é novo? " . ($isNew ? 'SIM' : 'NÃO') . " (created_at: {$contact['created_at']})");
            
            if ($isNew) {
                try {
                    \App\Helpers\Logger::info("[ContactService::createOrUpdate] Executando automações para contato criado...");
                    \App\Services\AutomationService::executeForContactCreated($contact['id']);
                } catch (\Exception $e) {
                    \App\Helpers\Logger::info("[ContactService::createOrUpdate] Erro ao executar automações: " . $e->getMessage());
                    error_log("Erro ao executar automações: " . $e->getMessage());
                }
            }
        }
        
        \App\Helpers\Logger::info("[ContactService::createOrUpdate] === FIM === Retornando contato ID: " . ($contact['id'] ?? 'NULL'));
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
            $search = trim($filters['search']);
            
            // Verificar se é uma busca por número (contém apenas dígitos e caracteres comuns de telefone)
            $isPhoneSearch = preg_match('/^[\d\s\+\-\(\)\.]+$/', $search);
            
            if ($isPhoneSearch) {
                // Normalizar o número de busca
                $normalizedSearch = Contact::normalizePhoneNumber($search);
                
                if (!empty($normalizedSearch)) {
                    // Gerar variantes do número de busca (com/sem 9º dígito, com/sem código do país)
                    $searchVariants = self::generatePhoneVariants($normalizedSearch);
                    
                    // Adicionar parâmetros para busca de texto PRIMEIRO (na ordem do SQL)
                    $textSearch = "%{$search}%";
                    $params[] = $textSearch; // name
                    $params[] = $textSearch; // last_name
                    $params[] = $textSearch; // email
                    $params[] = $textSearch; // company
                    
                    // Buscar por nome, email, empresa normalmente
                    $sql .= " AND (name LIKE ? OR last_name LIKE ? OR email LIKE ? OR company LIKE ?";
                    
                    // Adicionar busca por telefone com todas as variantes
                    $phoneConditions = [];
                    foreach ($searchVariants as $variant) {
                        $phoneConditions[] = "phone LIKE ?";
                        $params[] = "%{$variant}%";
                    }
                    
                    if (!empty($phoneConditions)) {
                        $sql .= " OR (" . implode(" OR ", $phoneConditions) . ")";
                    }
                    
                    $sql .= ")";
                } else {
                    // Se não conseguiu normalizar, busca simples
                    $sql .= " AND (name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? OR company LIKE ?)";
                    $textSearch = "%{$search}%";
                    $params[] = $textSearch;
                    $params[] = $textSearch;
                    $params[] = $textSearch;
                    $params[] = $textSearch;
                    $params[] = $textSearch;
                }
            } else {
                // Busca de texto normal
                $sql .= " AND (name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? OR company LIKE ?)";
                $textSearch = "%{$search}%";
                $params[] = $textSearch;
                $params[] = $textSearch;
                $params[] = $textSearch;
                $params[] = $textSearch;
                $params[] = $textSearch;
            }
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
     * Listar contatos com busca e retornar total
     */
    public static function listWithTotal(array $filters = []): array
    {
        $whereClause = "WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            
            // Verificar se é uma busca por número (contém apenas dígitos e caracteres comuns de telefone)
            $isPhoneSearch = preg_match('/^[\d\s\+\-\(\)\.]+$/', $search);
            
            if ($isPhoneSearch) {
                // Normalizar o número de busca
                $normalizedSearch = Contact::normalizePhoneNumber($search);
                
                if (!empty($normalizedSearch)) {
                    // Gerar variantes do número de busca (com/sem 9º dígito, com/sem código do país)
                    $searchVariants = self::generatePhoneVariants($normalizedSearch);
                    
                    // Adicionar parâmetros para busca de texto PRIMEIRO (na ordem do SQL)
                    $textSearch = "%{$search}%";
                    $params[] = $textSearch; // name
                    $params[] = $textSearch; // last_name
                    $params[] = $textSearch; // email
                    $params[] = $textSearch; // company
                    
                    // Buscar por nome, email, empresa normalmente
                    $whereClause .= " AND (name LIKE ? OR last_name LIKE ? OR email LIKE ? OR company LIKE ?";
                    
                    // Adicionar busca por telefone com todas as variantes
                    $phoneConditions = [];
                    foreach ($searchVariants as $variant) {
                        $phoneConditions[] = "phone LIKE ?";
                        $params[] = "%{$variant}%";
                    }
                    
                    if (!empty($phoneConditions)) {
                        $whereClause .= " OR (" . implode(" OR ", $phoneConditions) . ")";
                    }
                    
                    $whereClause .= ")";
                } else {
                    // Se não conseguiu normalizar, busca simples
                    $whereClause .= " AND (name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? OR company LIKE ?)";
                    $textSearch = "%{$search}%";
                    $params[] = $textSearch;
                    $params[] = $textSearch;
                    $params[] = $textSearch;
                    $params[] = $textSearch;
                    $params[] = $textSearch;
                }
            } else {
                // Busca de texto normal
                $whereClause .= " AND (name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? OR company LIKE ?)";
                $textSearch = "%{$search}%";
                $params[] = $textSearch;
                $params[] = $textSearch;
                $params[] = $textSearch;
                $params[] = $textSearch;
                $params[] = $textSearch;
            }
        }

        // Contar total
        $countSql = "SELECT COUNT(*) as total FROM contacts {$whereClause}";
        $totalResult = \App\Helpers\Database::fetch($countSql, $params);
        $total = (int)($totalResult['total'] ?? 0);

        // Buscar registros paginados
        $sql = "SELECT * FROM contacts {$whereClause} ORDER BY name ASC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET " . (int)$filters['offset'];
            }
        }

        $contacts = \App\Helpers\Database::fetchAll($sql, $params);

        return [
            'contacts' => $contacts,
            'total' => $total
        ];
    }

    /**
     * Gerar variantes de número de telefone para busca flexível
     * Considera variações com/sem 9º dígito e com/sem código do país
     */
    private static function generatePhoneVariants(string $normalizedPhone): array
    {
        $variants = [];
        
        // Sempre incluir o número normalizado
        $variants[] = $normalizedPhone;
        
        // Se o número está vazio ou muito curto, retornar apenas ele mesmo
        if (strlen($normalizedPhone) < 8) {
            return $variants;
        }
        
        // Remover código do país para trabalhar com DDD + número
        $national = $normalizedPhone;
        $hasCountryCode = false;
        if (strpos($normalizedPhone, '55') === 0 && strlen($normalizedPhone) >= 10) {
            $national = substr($normalizedPhone, 2);
            $hasCountryCode = true;
        }
        
        // Se tem 10 dígitos (possivelmente sem o 9º dígito do celular), gerar variante com 9
        if (strlen($national) === 10 && strlen($national) >= 3) {
            $ddd = substr($national, 0, 2);
            $number = substr($national, 2);
            $with9 = '55' . $ddd . '9' . $number;
            $variants[] = $with9;
            // Também adicionar sem código do país
            $variants[] = $ddd . '9' . $number;
        }
        
        // Se tem 11 dígitos e tem 9 no início do número (após DDD), gerar variante sem o 9
        if (strlen($national) === 11 && strlen($national) >= 4 && substr($national, 2, 1) === '9') {
            $ddd = substr($national, 0, 2);
            $numberWithout9 = substr($national, 3);
            $without9 = '55' . $ddd . $numberWithout9;
            $variants[] = $without9;
            // Também adicionar sem código do país
            $variants[] = $ddd . $numberWithout9;
        }
        
        // Se o número normalizado tem código do país, adicionar variante sem código
        if ($hasCountryCode && strlen($national) >= 8) {
            $variants[] = $national;
        }
        
        // Se o número normalizado não tem código do país e tem pelo menos 10 dígitos, adicionar variante com código
        if (!$hasCountryCode && strlen($normalizedPhone) >= 10) {
            $variants[] = '55' . $normalizedPhone;
        }
        
        // Remover duplicatas e retornar
        return array_values(array_unique($variants));
    }

    /**
     * Atualizar contato
     */
    public static function update(int $id, array $data): array
    {
        // Normalizar número de telefone ANTES de validar
        if (!empty($data['phone'])) {
            $data['phone'] = Contact::normalizePhoneNumber($data['phone']);
        }
        
        // Processar social_media antes da validação
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
        
        // Validar dados (após normalização e processamento)
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
     * Buscar avatar via Quepasa (rota /instances/{instanceId}/contacts/{number}/photo)
     */
    public static function fetchQuepasaAvatar(int $contactId, array $account, ?string $chatId = null, ?string $phone = null): ?string
    {
        try {
            if (($account['provider'] ?? '') !== 'quepasa') {
                \App\Helpers\Logger::quepasa("fetchQuepasaAvatar - provider != quepasa para contato {$contactId}");
                return null;
            }

            $apiUrl = rtrim($account['api_url'] ?? '', '/');
            $token = $account['quepasa_token'] ?? $account['api_key'] ?? null;

            \App\Helpers\Logger::quepasa("fetchQuepasaAvatar - Debug conta ID={$account['id']}: api_url=" . ($apiUrl ?: 'VAZIO') . ", token=" . ($token ? 'PRESENTE' : 'VAZIO'));

            if (empty($apiUrl) || empty($token)) {
                \App\Helpers\Logger::quepasa("fetchQuepasaAvatar - dados insuficientes para contato {$contactId}. Campos da conta: api_url=" . ($apiUrl ?: 'VAZIO') . ", token=" . ($token ? 'PRESENTE' : 'VAZIO'));
                return null;
            }

            // Preparar chatId para o header X-QUEPASA-CHATID
            // Deve estar no formato: 5511999999999@c.us ou similar
            $quepasaChatId = null;
            if ($chatId) {
                // Se chatId já tem @, usar direto
                $quepasaChatId = $chatId;
            } elseif ($phone) {
                // Se só tem phone, adicionar @s.whatsapp.net
                $cleanPhone = preg_replace('/\D+/', '', $phone);
                $quepasaChatId = $cleanPhone . '@s.whatsapp.net';
            }
            
            if (!$quepasaChatId) {
                \App\Helpers\Logger::quepasa("fetchQuepasaAvatar - chatId não fornecido para contato {$contactId}");
                return null;
            }
            
            // Tentar v4 primeiro, depois v3
            $versions = ['v4', 'v3'];
            $avatarUrl = null;
            
            foreach ($versions as $version) {
                $url = "{$apiUrl}/{$version}/bot/{$token}/picinfo";
                \App\Helpers\Logger::quepasa("fetchQuepasaAvatar - Tentando {$version}: {$url} com chatId={$quepasaChatId}");
                
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_HTTPHEADER => [
                        "X-QUEPASA-CHATID: {$quepasaChatId}",
                        "X-QUEPASA-TOKEN: {$token}",
                        'Accept: application/json'
                    ],
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                curl_close($ch);

                \App\Helpers\Logger::quepasa("fetchQuepasaAvatar - {$version} Resposta: httpCode={$httpCode}, contentType={$contentType}");

                if ($httpCode === 200 && !empty($response)) {
                    // Tentar decodificar JSON
                    $json = json_decode($response, true);
                    $photoUrl = null;
                    if ($json && isset($json['url'])) {
                        $photoUrl = $json['url'];
                    } elseif ($json && isset($json['info']['url'])) {
                        // Formato picinfo v3: info.url
                        $photoUrl = $json['info']['url'];
                    }
                    
                    if ($photoUrl) {
                        \App\Helpers\Logger::quepasa("fetchQuepasaAvatar - Avatar encontrado via {$version}: {$photoUrl}");
                        
                        // Baixar e salvar avatar
                        $avatarUrl = self::downloadAvatarFromUrl($contactId, $photoUrl);
                        if ($avatarUrl) {
                            return $avatarUrl;
                        }
                    } elseif ($json) {
                        \App\Helpers\Logger::quepasa("fetchQuepasaAvatar - {$version} JSON sem 'url': " . json_encode($json));
                    }
                }
                
                // Se v4 não funcionar, tentar v3; se já tentou v3, parar
                if ($version === 'v4') {
                    continue;
                } else {
                    break;
                }
            }

            return null;
        } catch (\Exception $e) {
            \App\Helpers\Logger::error("Erro ao buscar avatar via Quepasa: " . $e->getMessage());
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

