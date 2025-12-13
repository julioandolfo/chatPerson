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
     * Buscar ou criar contato
     */
    public static function findOrCreate(array $data): array
    {
        $contact = null;
        
        // Tentar encontrar por telefone primeiro
        if (!empty($data['phone'])) {
            $contact = self::findByPhone($data['phone']);
        }
        
        // Se não encontrou, tentar por email
        if (!$contact && !empty($data['email'])) {
            $contact = self::findByEmail($data['email']);
        }
        
        // Se não encontrou, criar novo
        if (!$contact) {
            $id = self::create($data);
            $contact = self::find($id);
        }
        
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

