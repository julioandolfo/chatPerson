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
        'last_activity_at'
    ];
    protected array $hidden = [];
    protected bool $timestamps = true;

    /**
     * Buscar por telefone
     */
    public static function findByPhone(string $phone): ?array
    {
        return self::whereFirst('phone', '=', $phone);
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

