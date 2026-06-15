<?php
/**
 * HtmlToText
 * Converte corpo HTML de email em texto legível, removendo blocos não-visíveis
 * (style/script/head) — que o strip_tags() simples deixaria vazar como "lixo".
 */

namespace App\Services\Email;

class HtmlToText
{
    public static function convert(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        // Remove blocos cujo conteúdo NÃO é texto visível (CSS, JS, metadados)
        $html = preg_replace('#<(style|script|head|title)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;

        // Remove comentários HTML
        $html = preg_replace('#<!--.*?-->#s', ' ', $html) ?? $html;

        // Converte quebras de bloco em novas linhas para preservar a estrutura
        $html = preg_replace('#<\s*br\s*/?>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</\s*(p|div|tr|li|h[1-6]|table|ul|ol)\s*>#i', "\n", $html) ?? $html;

        // Remove as tags restantes e decodifica entidades (&nbsp;, &amp;, etc.)
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normaliza espaços e linhas em branco excessivas
        $text = str_replace("\xc2\xa0", ' ', $text);          // nbsp -> espaço
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/[ \t]*\n[ \t]*/', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
