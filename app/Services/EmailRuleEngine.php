<?php
/**
 * EmailRuleEngine
 * Avalia as regras de validação contra um email normalizado e decide se entra
 * como conversa. Comparações de texto são case-insensitive e ignoram acentos.
 */

namespace App\Services;

class EmailRuleEngine
{
    /**
     * @param array  $rules            Regras (EmailRule::getForAccount), já ordenadas
     * @param array  $email            Email normalizado (ImapClient::normalize)
     * @param string $unmatchedAction  'ignore' | 'ingest' (ação quando nenhuma regra casa)
     * @return array ['ingest'=>bool,'matched_rule'=>?array,'actions'=>array,'reason'=>string]
     */
    public static function evaluate(array $rules, array $email, string $unmatchedAction = 'ignore'): array
    {
        foreach ($rules as $rule) {
            if (self::matches($rule, $email)) {
                $actions = is_array($rule['actions'] ?? null) ? $rule['actions'] : [];
                $ingest = array_key_exists('ingest', $actions) ? (bool)$actions['ingest'] : true;
                return [
                    'ingest'       => $ingest,
                    'matched_rule' => $rule,
                    'actions'      => $actions,
                    'reason'       => 'regra:' . ($rule['name'] ?? ($rule['id'] ?? '?')),
                ];
            }
        }

        return [
            'ingest'       => ($unmatchedAction === 'ingest'),
            'matched_rule' => null,
            'actions'      => [],
            'reason'       => 'sem_regra:' . $unmatchedAction,
        ];
    }

    private static function matches(array $rule, array $email): bool
    {
        $conditions = is_array($rule['conditions'] ?? null) ? $rule['conditions'] : [];
        if (empty($conditions)) {
            return true; // regra sem condições casa sempre
        }

        $matchType = strtolower((string)($rule['match_type'] ?? 'any'));
        $results = [];
        foreach ($conditions as $cond) {
            if (is_array($cond)) {
                $results[] = self::evalCondition($cond, $email);
            }
        }

        if ($matchType === 'all') {
            return !empty($results) && !in_array(false, $results, true);
        }
        return in_array(true, $results, true);
    }

    private static function evalCondition(array $cond, array $email): bool
    {
        $field = strtolower((string)($cond['field'] ?? 'subject_or_body'));
        $op = strtolower((string)($cond['op'] ?? 'contains'));
        $value = (string)($cond['value'] ?? '');

        $subject = (string)($email['subject'] ?? '');
        $body = (string)($email['text'] ?? '');
        if ($body === '' && !empty($email['html'])) {
            $body = \App\Services\Email\HtmlToText::convert((string)$email['html']);
        }
        $from = (string)($email['from_email'] ?? '');

        switch ($field) {
            case 'subject':
                $haystack = $subject;
                break;
            case 'body':
                $haystack = $body;
                break;
            case 'subject_or_body':
                $haystack = $subject . "\n" . $body;
                break;
            case 'from':
                $haystack = $from;
                break;
            case 'from_domain':
                $haystack = self::domain($from);
                break;
            case 'to':
                $haystack = (string)($email['to'] ?? '');
                break;
            case 'has_attachment':
                return (!empty($email['attachments'])) === self::truthy($value);
            default:
                $haystack = $subject . "\n" . $body;
        }

        return self::applyOp($op, $haystack, $value);
    }

    private static function applyOp(string $op, string $haystack, string $needle): bool
    {
        $h = self::norm($haystack);
        $n = self::norm($needle);

        switch ($op) {
            case 'contains':
                return $n !== '' && strpos($h, $n) !== false;
            case 'not_contains':
                return $n === '' || strpos($h, $n) === false;
            case 'equals':
                return $h === $n;
            case 'starts_with':
                return $n !== '' && strpos($h, $n) === 0;
            case 'ends_with':
                return $n !== '' && substr($h, -strlen($n)) === $n;
            case 'regex':
                $re = $needle;
                if (@preg_match($re, '') === false) {
                    $re = '/' . str_replace('/', '\/', $needle) . '/i';
                }
                return (bool) @preg_match($re, $haystack);
            case 'in_list':
                $list = array_map([self::class, 'norm'], array_map('trim', explode(',', $needle)));
                return in_array($h, $list, true);
            default:
                return false;
        }
    }

    /**
     * Normaliza: minúsculas + remove acentos comuns do português.
     */
    private static function norm(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $map = [
            'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
            'é'=>'e','ê'=>'e','è'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c','ñ'=>'n',
        ];
        return strtr($s, $map);
    }

    private static function domain(string $email): string
    {
        $at = strrpos($email, '@');
        return $at === false ? '' : substr($email, $at + 1);
    }

    private static function truthy(string $v): bool
    {
        $v = strtolower(trim($v));
        if ($v === '') {
            return true;
        }
        return in_array($v, ['1', 'true', 'yes', 'sim', 'y', 's'], true);
    }
}
