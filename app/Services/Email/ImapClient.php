<?php
/**
 * ImapClient
 * Wrapper fino sobre webklex/php-imap (PHP puro, não exige a extensão imap).
 * Responsável por conectar, testar e buscar emails novos normalizados.
 */

namespace App\Services\Email;

use Webklex\PHPIMAP\ClientManager;
use App\Helpers\Logger;

class ImapClient
{
    /**
     * @param array $cfg ['imap_host','imap_port','imap_encryption','imap_validate_cert','auth_user','auth_pass','imap_folder']
     */
    private static function makeClient(array $cfg)
    {
        $cm = new ClientManager();
        return $cm->make([
            'host'          => $cfg['imap_host'] ?? '',
            'port'          => (int)($cfg['imap_port'] ?? 993),
            'encryption'    => self::encryption($cfg['imap_encryption'] ?? 'ssl'),
            'validate_cert' => (bool)($cfg['imap_validate_cert'] ?? true),
            'username'      => $cfg['auth_user'] ?? '',
            'password'      => $cfg['auth_pass'] ?? '',
            'protocol'      => 'imap',
        ]);
    }

    private static function encryption($enc)
    {
        $enc = strtolower((string)$enc);
        if ($enc === 'ssl') {
            return 'ssl';
        }
        if ($enc === 'tls' || $enc === 'starttls') {
            return 'tls';
        }
        return false; // sem criptografia
    }

    /**
     * Testa a conexão IMAP. Retorna ['success'=>bool,'message'=>string,'count'=>?int]
     */
    public static function testConnection(array $cfg): array
    {
        try {
            $client = self::makeClient($cfg);
            $client->connect();
            $folder = $client->getFolder($cfg['imap_folder'] ?? 'INBOX');
            $count = null;
            try {
                $info = $folder ? $folder->examine() : [];
                $count = $info['exists'] ?? null;
            } catch (\Throwable $e) {
                // examine pode não estar disponível em alguns servidores
            }
            $client->disconnect();
            return ['success' => true, 'message' => 'Conexão IMAP estabelecida com sucesso.', 'count' => $count];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'IMAP: ' . $e->getMessage()];
        }
    }

    /**
     * Busca emails com UID maior que $lastUid na pasta configurada.
     * Não marca como lido (leaveUnread). Retorna lista de emails normalizados.
     */
    public static function fetchNew(array $cfg, int $lastUid, int $lookbackDays = 2, int $limit = 50): array
    {
        $out = [];
        $client = self::makeClient($cfg);
        $client->connect();
        $folder = $client->getFolder($cfg['imap_folder'] ?? 'INBOX');

        $query = $folder->query()->leaveUnread()->setFetchOrder('asc');
        if ($lookbackDays > 0) {
            $since = (new \DateTime())->modify('-' . max(1, $lookbackDays) . ' days');
            $query = $query->since($since);
        }
        $messages = $query->limit($limit)->get();

        foreach ($messages as $message) {
            try {
                $uid = (int) self::attr($message->getUid());
                if ($uid <= $lastUid) {
                    continue;
                }
                $out[] = self::normalize($message, $uid);
            } catch (\Throwable $e) {
                Logger::log('ImapClient::fetchNew normalize: ' . $e->getMessage(), 'email.log');
            }
        }

        $client->disconnect();
        return $out;
    }

    /**
     * Converte um valor (Attribute do webklex, array ou escalar) em string.
     */
    private static function attr($value): string
    {
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        }
        if (is_array($value)) {
            $first = reset($value);
            return is_scalar($first) ? (string)$first : '';
        }
        return is_scalar($value) ? (string)$value : '';
    }

    /**
     * Decodifica "encoded-words" MIME (RFC 2047), ex.: =?iso-8859-1?Q?Or=E7amento?=
     * -> "Orçamento", convertendo para UTF-8. Idempotente para texto já limpo.
     */
    private static function decodeMimeHeader(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strpos($value, '=?') === false) {
            return $value;
        }

        // iconv lida com B/Q e múltiplos encoded-words concatenados
        if (function_exists('iconv_mime_decode')) {
            $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if ($decoded !== false && $decoded !== '') {
                return $decoded;
            }
        }
        if (function_exists('mb_decode_mimeheader')) {
            $decoded = @mb_decode_mimeheader($value);
            if ($decoded !== '') {
                return $decoded;
            }
        }
        return $value;
    }

    /**
     * Normaliza uma mensagem do webklex para o array usado pela ingestão.
     */
    private static function normalize($message, int $uid): array
    {
        $fromArr = $message->getFrom() ?: [];
        $fromObj = is_array($fromArr) ? ($fromArr[0] ?? null) : null;
        $fromEmail = $fromObj->mail ?? '';
        $fromName = self::decodeMimeHeader((string)($fromObj->personal ?? ''));

        $messageId = trim(self::attr($message->getMessageId()), " <>");
        $inReplyTo = trim(self::attr($message->getInReplyTo()), " <>");
        $references = self::attr($message->getReferences());
        $subject = self::decodeMimeHeader(self::attr($message->getSubject()));

        $text = (string) $message->getTextBody();
        $html = (string) $message->getHTMLBody();

        // Se não houver texto puro, derivar texto limpo do HTML (sem CSS/JS)
        if (trim($text) === '' && trim($html) !== '') {
            $text = HtmlToText::convert($html);
        }

        $ts = time();
        try {
            $date = $message->getDate();
            if ($date) {
                $dt = method_exists($date, 'toDate') ? $date->toDate() : $date->first();
                if ($dt instanceof \DateTimeInterface) {
                    $ts = $dt->getTimestamp();
                }
            }
        } catch (\Throwable $e) {
            // mantém time()
        }

        $rawHeaders = '';
        try {
            $header = $message->getHeader();
            $rawHeaders = (string)($header->raw ?? '');
        } catch (\Throwable $e) {
            // opcional
        }

        $attachments = [];
        try {
            foreach ($message->getAttachments() as $att) {
                $content = $att->getContent();
                if ($content === null || $content === '') {
                    continue;
                }
                $attachments[] = [
                    'name'    => $att->getName() ?: ('anexo-' . $uid),
                    'mime'    => $att->getMimeType() ?: 'application/octet-stream',
                    'content' => $content,
                    'size'    => (int)($att->getSize() ?? strlen($content)),
                ];
            }
        } catch (\Throwable $e) {
            // sem anexos
        }

        return [
            'uid'         => $uid,
            'message_id'  => $messageId,
            'in_reply_to' => $inReplyTo,
            'references'  => $references,
            'from_email'  => strtolower(trim((string)$fromEmail)),
            'from_name'   => trim((string)$fromName),
            'subject'     => $subject,
            'text'        => $text,
            'html'        => $html,
            'timestamp'   => $ts,
            'raw_headers' => $rawHeaders,
            'attachments' => $attachments,
        ];
    }
}
