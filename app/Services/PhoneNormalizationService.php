<?php
/**
 * Service PhoneNormalizationService
 * Normalização de números de telefone para busca no WooCommerce
 */

namespace App\Services;

class PhoneNormalizationService
{
    /**
     * Normalizar telefone para busca no WooCommerce
     * Gera todas as variações possíveis para matching
     */
    public static function generateVariations(string $phone): array
    {
        $variations = [];
        
        // 1. Remover caracteres especiais
        $clean = preg_replace('/[^0-9]/', '', $phone);
        
        // 2. Remover sufixos WhatsApp
        $clean = str_replace(['@s.whatsapp.net', '@lid', '@c.us', '@g.us'], '', $clean);
        
        // 3. Gerar variações com código do país
        if (strpos($clean, '55') === 0) {
            // Já tem código do país
            $national = substr($clean, 2);
            $variations[] = $clean; // Com código
            $variations[] = $national; // Sem código
            
            // Com +55
            $variations[] = '+55' . $national;
            $variations[] = '+55' . substr($clean, 2);
        } else {
            // Sem código do país - adicionar
            $variations[] = '55' . $clean;
            $variations[] = '+55' . $clean;
            $variations[] = $clean; // Original
        }
        
        // 4. Gerar variações com/sem 9º dígito (Brasil)
        $newVariations = [];
        foreach ($variations as $var) {
            $newVariations[] = $var;
            
            // Se tem 13 dígitos (55 + DDD + 9 + número), gerar sem 9º
            if (strlen($var) == 13 && substr($var, 4, 1) == '9') {
                $without9 = substr($var, 0, 4) . substr($var, 5);
                $newVariations[] = $without9;
            }
            
            // Se tem 12 dígitos (55 + DDD + número sem 9), gerar com 9º
            if (strlen($var) == 12 && strlen($var) >= 4) {
                $with9 = substr($var, 0, 4) . '9' . substr($var, 4);
                $newVariations[] = $with9;
            }
            
            // Variações sem código do país
            if (strlen($var) >= 12 && strpos($var, '55') === 0) {
                $national = substr($var, 2);
                $newVariations[] = $national;
                
                // Com/sem 9º dígito na versão nacional
                if (strlen($national) == 11 && substr($national, 2, 1) == '9') {
                    $nationalWithout9 = substr($national, 0, 2) . substr($national, 3);
                    $newVariations[] = $nationalWithout9;
                } elseif (strlen($national) == 10) {
                    $nationalWith9 = substr($national, 0, 2) . '9' . substr($national, 2);
                    $newVariations[] = $nationalWith9;
                }
            }
        }
        
        // 5. Remover duplicatas e retornar
        return array_values(array_unique($newVariations));
    }
    
    /**
     * Normalizar telefone do WooCommerce para comparação
     */
    public static function normalizeWooCommercePhone(string $wcPhone): string
    {
        // Remover caracteres especiais
        $clean = preg_replace('/[^0-9]/', '', $wcPhone);
        
        // Se não tem código do país, adicionar
        if (strlen($clean) >= 10 && strlen($clean) <= 11 && strpos($clean, '55') !== 0) {
            $clean = '55' . $clean;
        }
        
        return $clean;
    }
    
    /**
     * Comparar dois números de telefone (considerando variações)
     */
    public static function comparePhones(string $phone1, string $phone2): bool
    {
        $variations1 = self::generateVariations($phone1);
        $variations2 = self::generateVariations($phone2);
        
        // Normalizar todas as variações
        $normalized1 = array_map([self::class, 'normalizeWooCommercePhone'], $variations1);
        $normalized2 = array_map([self::class, 'normalizeWooCommercePhone'], $variations2);
        
        // Verificar se há interseção
        return !empty(array_intersect($normalized1, $normalized2));
    }
}

