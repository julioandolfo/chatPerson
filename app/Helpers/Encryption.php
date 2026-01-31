<?php
/**
 * Helper Encryption
 * Criptografia simétrica para dados sensíveis que precisam ser recuperados
 */

namespace App\Helpers;

class Encryption
{
    private static string $cipher = 'AES-256-CBC';
    
    /**
     * Obter chave de criptografia
     * Usa APP_KEY do ambiente ou gera uma baseada em configurações do sistema
     */
    private static function getKey(): string
    {
        $key = $_ENV['APP_KEY'] ?? getenv('APP_KEY');
        
        if (empty($key)) {
            // Fallback: usar combinação de configurações do sistema
            $key = md5(__DIR__ . php_uname() . 'api4com_sip_key');
        }
        
        // Garantir que a chave tenha 32 bytes para AES-256
        return hash('sha256', $key, true);
    }
    
    /**
     * Criptografar dados
     * 
     * @param string $data Dados a criptografar
     * @return string Dados criptografados em base64
     */
    public static function encrypt(string $data): string
    {
        $key = self::getKey();
        $ivLength = openssl_cipher_iv_length(self::$cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        $encrypted = openssl_encrypt($data, self::$cipher, $key, OPENSSL_RAW_DATA, $iv);
        
        if ($encrypted === false) {
            throw new \RuntimeException('Falha ao criptografar dados');
        }
        
        // Combinar IV + dados criptografados e codificar em base64
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Descriptografar dados
     * 
     * @param string $encryptedData Dados criptografados em base64
     * @return string|null Dados descriptografados ou null se falhar
     */
    public static function decrypt(string $encryptedData): ?string
    {
        if (empty($encryptedData)) {
            return null;
        }
        
        try {
            // Verificar se parece ser base64 válido (dados criptografados)
            $data = base64_decode($encryptedData, true);
            
            // Se não é base64 válido, provavelmente é texto puro (legado)
            if ($data === false) {
                Logger::api4com("Encryption::decrypt - Dados não são base64, retornando como texto puro (legado)");
                return $encryptedData;
            }
            
            $ivLength = openssl_cipher_iv_length(self::$cipher);
            
            // Se o tamanho não é suficiente para IV + dados, provavelmente é texto puro
            if (strlen($data) <= $ivLength) {
                Logger::api4com("Encryption::decrypt - Dados muito curtos para criptografia, retornando como texto puro (legado)");
                return $encryptedData;
            }
            
            $iv = substr($data, 0, $ivLength);
            $encrypted = substr($data, $ivLength);
            
            $key = self::getKey();
            $decrypted = openssl_decrypt($encrypted, self::$cipher, $key, OPENSSL_RAW_DATA, $iv);
            
            // Se descriptografia falhar, pode ser texto puro que por acaso é base64 válido
            if ($decrypted === false) {
                Logger::api4com("Encryption::decrypt - Falha na descriptografia, retornando como texto puro (legado)");
                return $encryptedData;
            }
            
            return $decrypted;
        } catch (\Exception $e) {
            Logger::error("Encryption::decrypt - Erro: " . $e->getMessage());
            // Em caso de qualquer erro, retornar dados originais (pode ser texto puro)
            return $encryptedData;
        }
    }
    
    /**
     * Verificar se os dados estão criptografados
     * 
     * @param string $data Dados a verificar
     * @return bool True se parecer estar criptografado
     */
    public static function isEncrypted(string $data): bool
    {
        // Verificar se é base64 válido e tem tamanho mínimo (IV + dados)
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return false;
        }
        
        $ivLength = openssl_cipher_iv_length(self::$cipher);
        return strlen($decoded) > $ivLength;
    }
}
