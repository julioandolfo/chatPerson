<?php
/**
 * SmtpMailer
 * Envio de email via SMTP (PHPMailer) para respostas do atendente.
 */

namespace App\Services\Email;

use PHPMailer\PHPMailer\PHPMailer;

class SmtpMailer
{
    private static function configure(PHPMailer $mail, array $cfg): void
    {
        $mail->isSMTP();
        $mail->Host = $cfg['smtp_host'] ?? ($cfg['imap_host'] ?? '');
        $mail->Port = (int)($cfg['smtp_port'] ?? 587);
        $mail->SMTPAuth = true;
        $mail->Username = $cfg['auth_user'] ?? '';
        $mail->Password = $cfg['auth_pass'] ?? '';
        $mail->CharSet = 'UTF-8';

        $enc = strtolower((string)($cfg['smtp_encryption'] ?? 'tls'));
        if ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'tls' || $enc === 'starttls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }
    }

    /**
     * Envia um email.
     * @param array $msg ['to','subject','body','html','in_reply_to','references','attachments'=>[['abs_path'|'content','name','mime']]]
     * @return array ['success'=>bool,'external_id'=>?string,'error'=>?string]
     */
    public static function send(array $cfg, array $msg): array
    {
        $mail = new PHPMailer(true);
        try {
            self::configure($mail, $cfg);
            $mail->Timeout = 25;

            $fromAddr = $cfg['from_address'] ?? ($cfg['auth_user'] ?? '');
            $fromName = $cfg['from_name'] ?? '';
            if ($fromAddr !== '') {
                $mail->setFrom($fromAddr, $fromName);
            }

            $mail->addAddress($msg['to']);
            if (!empty($msg['reply_to'])) {
                $mail->addReplyTo($msg['reply_to']);
            }

            $mail->Subject = (string)($msg['subject'] ?? '');

            if (!empty($msg['html'])) {
                $mail->isHTML(true);
                $mail->Body = $msg['html'];
                $mail->AltBody = !empty($msg['body']) ? $msg['body'] : strip_tags($msg['html']);
            } else {
                $mail->isHTML(false);
                $mail->Body = (string)($msg['body'] ?? '');
            }

            if (!empty($msg['in_reply_to'])) {
                $mail->addCustomHeader('In-Reply-To', '<' . trim($msg['in_reply_to'], '<>') . '>');
            }
            if (!empty($msg['references'])) {
                $mail->addCustomHeader('References', $msg['references']);
            }

            foreach (($msg['attachments'] ?? []) as $att) {
                if (!empty($att['abs_path']) && is_file($att['abs_path'])) {
                    $mail->addAttachment($att['abs_path'], $att['name'] ?? '');
                } elseif (!empty($att['content'])) {
                    $mail->addStringAttachment(
                        $att['content'],
                        $att['name'] ?? 'anexo',
                        PHPMailer::ENCODING_BASE64,
                        $att['mime'] ?? 'application/octet-stream'
                    );
                }
            }

            $mail->send();
            $extId = trim((string)$mail->getLastMessageID(), '<>');
            return ['success' => true, 'external_id' => $extId !== '' ? $extId : null];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $mail->ErrorInfo ?: $e->getMessage()];
        }
    }

    /**
     * Testa a conexão/autenticação SMTP. Retorna ['success'=>bool,'message'=>string]
     */
    public static function testConnection(array $cfg): array
    {
        $mail = new PHPMailer(true);
        try {
            self::configure($mail, $cfg);
            $mail->Timeout = 15;
            $ok = $mail->smtpConnect();
            $mail->smtpClose();
            return ['success' => (bool)$ok, 'message' => $ok ? 'Conexão SMTP estabelecida com sucesso.' : 'Falha ao conectar no SMTP.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'SMTP: ' . ($mail->ErrorInfo ?: $e->getMessage())];
        }
    }
}
