<?php
/**
 * Model Contact
 */

namespace App\Models;

class Contact extends Model
{
    protected string $table = 'contacts';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name', 
        'last_name', 
        'email', 
        'phone', 
        'whatsapp_id',
        'identifier', // Para Instagram, Facebook, Telegram, etc (ID único do canal)
        'city',
        'country',
        'bio',
        'company',
        'social_media',
        'avatar', 
        'custom_attributes',
        'last_activity_at',
        'primary_agent_id'
    ];
    protected array $hidden = [];
    protected bool $timestamps = true;
    
    /**
     * Campos do tipo JSON no banco
     */
    protected array $jsonFields = ['social_media', 'custom_attributes'];

    /**
     * Buscar por telefone (busca exata)
     */
    public static function findByPhone(string $phone): ?array
    {
        return self::whereFirst('phone', '=', $phone);
    }

    /**
     * Buscar por telefone normalizado
     * Normaliza o número fornecido e busca no banco
     * Também tenta buscar variações comuns (com/sem sufixos WhatsApp)
     */
    public static function findByPhoneNormalized(string $phone): ?array
    {
        // Normalizar o número fornecido
        $normalized = self::normalizePhoneNumber($phone);
        
        if (empty($normalized)) {
            return null;
        }
        
        // Gerar variantes de número (com e sem 9º dígito de celular)
        $variants = [];

        $variants[] = $normalized; // sempre incluir normalizado

        // Remover código do país para trabalhar com DDD + número
        $national = $normalized;
        if (strpos($normalized, '55') === 0) {
            $national = substr($normalized, 2);
        }

        // Se tem 10 dígitos (possivelmente sem o 9º dígito do celular), gerar com 9
        if (strlen($national) === 10) {
            $with9 = '55' . substr($national, 0, 2) . '9' . substr($national, 2);
            $variants[] = $with9;
        }

        // Se tem 11 dígitos e já tem 9 no início do número, gerar variante sem o 9
        if (strlen($national) === 11 && substr($national, 2, 1) === '9') {
            $without9 = '55' . substr($national, 0, 2) . substr($national, 3);
            $variants[] = $without9;
        }

        // Unificar e remover duplicatas
        $variants = array_values(array_unique($variants));

        // Para cada variante, buscar variações comuns (sufixos WhatsApp e +)
        foreach ($variants as $variant) {
            $candidates = [
                $variant,
                '+' . $variant,
                $variant . '@s.whatsapp.net',
                $variant . '@lid',
                $variant . '@c.us'
            ];

            foreach ($candidates as $candidate) {
                $contact = self::whereFirst('phone', '=', $candidate);
                if ($contact) {
                    return $contact;
                }
            }
        }
        
        // Buscar usando LIKE para números que contenham alguma variante
        foreach ($variants as $variant) {
            $sql = "SELECT * FROM contacts WHERE phone LIKE ? OR phone LIKE ? LIMIT 1";
            $contact = \App\Helpers\Database::fetch($sql, [
                $variant . '%',
                '%' . $variant
            ]);
            
            if ($contact) {
                return $contact;
            }
        }
        
        return null;
    }

    /**
     * Buscar por email ou telefone (com normalização)
     */
    public static function findByEmailOrPhone(?string $email, ?string $phone): ?array
    {
        // Tentar por email primeiro
        if (!empty($email)) {
            $found = self::findByEmail($email);
            if ($found) {
                return $found;
            }
        }

        // Depois tentar por telefone normalizado
        if (!empty($phone)) {
            $found = self::findByPhoneNormalized($phone);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Normalizar número de telefone (mesma lógica do WhatsAppService)
     */
    public static function normalizePhoneNumber(string $phone): string
    {
        if (empty($phone)) {
            return '';
        }
        
        // Remover sufixos comuns do WhatsApp
        $phone = str_replace('@s.whatsapp.net', '', $phone);
        $phone = str_replace('@lid', '', $phone); // Linked ID (contatos não salvos)
        $phone = str_replace('@c.us', '', $phone); // Contato comum
        $phone = str_replace('@g.us', '', $phone); // Grupo
        
        // Remover caracteres especiais comuns
        $phone = str_replace(['+', '-', ' ', '(', ')', '.', '_'], '', $phone);
        
        // Extrair apenas dígitos (pode ter : para separar device ID)
        if (strpos($phone, ':') !== false) {
            $phone = explode(':', $phone)[0];
        }
        
        // Remover + se ainda estiver presente
        $phone = ltrim($phone, '+');
        
        // ✅ NORMALIZAR 9º DÍGITO PARA NÚMEROS BRASILEIROS
        // Formato: 55 (país) + DD (2 dígitos DDD) + 9XXXXXXXX (9 dígitos com 9º adicional)
        if (strlen($phone) == 12 && substr($phone, 0, 2) === '55') {
            // Já tem 12 dígitos (formato correto com 9º dígito)
            // Exemplo: 5535991970289
            return $phone;
        } elseif (strlen($phone) == 13 && substr($phone, 0, 2) === '55') {
            // 13 dígitos? Pode ter 0 extra no DDD antigo, remover
            // Exemplo: 55035991970289 -> 5535991970289
            return '55' . ltrim(substr($phone, 2), '0');
        } elseif (strlen($phone) == 11 && substr($phone, 0, 2) === '55') {
            // 11 dígitos: falta o 9º dígito adicional
            // Exemplo: 553591970289 -> 5535991970289
            $ddd = substr($phone, 2, 2);
            $numero = substr($phone, 4);
            
            // Adicionar 9º dígito se o número começar com 6-9 (celular)
            if (strlen($numero) === 8 && in_array($numero[0], ['6', '7', '8', '9'])) {
                return '55' . $ddd . '9' . $numero;
            }
            
            return $phone; // Número fixo ou já normalizado
        }
        
        return $phone;
    }

    /**
     * Buscar por email
     */
    public static function findByEmail(string $email): ?array
    {
        return self::whereFirst('email', '=', $email);
    }

    /**
     * Buscar por identifier (Instagram, Facebook, Telegram, etc)
     */
    public static function findByIdentifier(string $identifier): ?array
    {
        return self::whereFirst('identifier', '=', $identifier);
    }

    /**
     * Buscar ou criar contato
     */
    public static function findOrCreate(array $data): array
    {
        \App\Helpers\Logger::notificame("[INFO] Contact::findOrCreate - Iniciando busca/criação de contato");
        \App\Helpers\Logger::notificame("[INFO] Contact::findOrCreate - Dados recebidos: " . json_encode([
            'has_identifier' => !empty($data['identifier']),
            'identifier' => $data['identifier'] ?? 'NULL',
            'has_phone' => !empty($data['phone']),
            'has_email' => !empty($data['email']),
            'has_avatar' => !empty($data['avatar']),
            'name' => $data['name'] ?? 'NULL'
        ], JSON_UNESCAPED_UNICODE));
        
        $contact = null;
        
        // Tentar encontrar por identifier primeiro (Instagram, Facebook, etc)
        if (!empty($data['identifier'])) {
            \App\Helpers\Logger::notificame("[INFO] Contact::findOrCreate - Buscando por identifier: {$data['identifier']}");
            $contact = self::findByIdentifier($data['identifier']);
            if ($contact) {
                \App\Helpers\Logger::notificame("[INFO] Contact::findOrCreate - Contato ENCONTRADO por identifier, ID: {$contact['id']}");
            } else {
                \App\Helpers\Logger::notificame("[INFO] Contact::findOrCreate - Nenhum contato encontrado por identifier");
            }
        }
        
        // Tentar encontrar por telefone
        if (!$contact && !empty($data['phone'])) {
            \App\Helpers\Logger::notificame("[INFO] Contact::findOrCreate - Buscando por phone: {$data['phone']}");
            $contact = self::findByPhone($data['phone']);
            if ($contact) {
                \App\Helpers\Logger::notificame("[INFO] Contact::findOrCreate - Contato ENCONTRADO por phone, ID: {$contact['id']}");
            }
        }
        
        // Se não encontrou, tentar por email
        if (!$contact && !empty($data['email'])) {
            \App\Helpers\Logger::notificame("[INFO] Contact::findOrCreate - Buscando por email: {$data['email']}");
            $contact = self::findByEmail($data['email']);
            if ($contact) {
                \App\Helpers\Logger::notificame("[INFO] Contact::findOrCreate - Contato ENCONTRADO por email, ID: {$contact['id']}");
            }
        }
        
        // Se encontrou mas precisa atualizar dados (ex: avatar, identifier)
        if ($contact) {
            $needsUpdate = false;
            $updateData = [];
            
            // Atualizar identifier se não tinha antes
            if (!empty($data['identifier']) && empty($contact['identifier'])) {
                $updateData['identifier'] = $data['identifier'];
                $needsUpdate = true;
                \App\Helpers\Logger::notificame("[INFO] Contact::findOrCreate - Adicionando identifier ao contato existente");
            }
            
            // Atualizar avatar se não tinha antes ou se mudou
            if (!empty($data['avatar']) && ($contact['avatar'] !== $data['avatar'])) {
                $updateData['avatar'] = $data['avatar'];
                $needsUpdate = true;
                \App\Helpers\Logger::notificame("[INFO] Contact::findOrCreate - Atualizando avatar do contato existente");
            }
            
            // Atualizar nome se não tinha antes
            if (!empty($data['name']) && empty($contact['name'])) {
                $updateData['name'] = $data['name'];
                $needsUpdate = true;
                \App\Helpers\Logger::notificame("[INFO] Contact::findOrCreate - Atualizando nome do contato existente");
            }
            
            if ($needsUpdate) {
                \App\Helpers\Logger::notificame("[INFO] Contact::findOrCreate - Atualizando contato ID {$contact['id']} com: " . json_encode($updateData, JSON_UNESCAPED_UNICODE));
                self::update($contact['id'], $updateData);
                $contact = self::find($contact['id']); // Recarregar
                \App\Helpers\Logger::notificame("[INFO] Contact::findOrCreate - Contato atualizado e recarregado");
            }
        }
        
        // Se não encontrou, criar novo
        if (!$contact) {
            \App\Helpers\Logger::notificame("[INFO] Contact::findOrCreate - Nenhum contato encontrado, criando NOVO contato");
            \App\Helpers\Logger::notificame("[INFO] Contact::findOrCreate - Dados para criação: " . json_encode($data, JSON_UNESCAPED_UNICODE));
            
            try {
                $id = self::create($data);
                \App\Helpers\Logger::notificame("[INFO] Contact::findOrCreate - Contato criado com ID: {$id}");
                $contact = self::find($id);
                if ($contact) {
                    \App\Helpers\Logger::notificame("[INFO] Contact::findOrCreate - Contato criado e encontrado com sucesso");
                } else {
                    \App\Helpers\Logger::notificame("[ERROR] Contact::findOrCreate - Contato criado mas não encontrado ao buscar novamente!");
                }
            } catch (\Exception $e) {
                \App\Helpers\Logger::notificame("[ERROR] Contact::findOrCreate - Erro ao criar contato: " . $e->getMessage());
                \App\Helpers\Logger::notificame("[ERROR] Contact::findOrCreate - Trace: " . $e->getTraceAsString());
                throw $e;
            }
        }
        
        \App\Helpers\Logger::notificame("[INFO] Contact::findOrCreate - Retornando contato ID: " . ($contact['id'] ?? 'NULL'));
        return $contact;
    }

    /**
     * Obter atributos customizados
     */
    public static function getCustomAttributes(int $id): array
    {
        $contact = self::find($id);
        if ($contact && !empty($contact['custom_attributes'])) {
            return json_decode($contact['custom_attributes'], true) ?? [];
        }
        return [];
    }

    /**
     * Atualizar atributos customizados
     */
    public static function updateCustomAttributes(int $id, array $attributes): bool
    {
        return self::update($id, [
            'custom_attributes' => json_encode($attributes)
        ]);
    }

    /**
     * Obter redes sociais
     */
    public static function getSocialMedia(int $id): array
    {
        $contact = self::find($id);
        if ($contact && !empty($contact['social_media'])) {
            return json_decode($contact['social_media'], true) ?? [];
        }
        return [];
    }

    /**
     * Atualizar redes sociais
     */
    public static function updateSocialMedia(int $id, array $socialMedia): bool
    {
        return self::update($id, [
            'social_media' => json_encode($socialMedia)
        ]);
    }

    /**
     * Atualizar última atividade
     */
    public static function updateLastActivity(int $id): bool
    {
        return self::update($id, [
            'last_activity_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Obter nome completo (nome + sobrenome)
     */
    public static function getFullName(int $id): string
    {
        $contact = self::find($id);
        if (!$contact) {
            return '';
        }
        
        $name = $contact['name'] ?? '';
        $lastName = $contact['last_name'] ?? '';
        
        return trim($name . ' ' . $lastName);
    }
}

