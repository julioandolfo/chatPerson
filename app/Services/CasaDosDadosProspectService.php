<?php
/**
 * Prospecção de empresas via API Casa dos Dados (v5)
 * @see https://docs.casadosdados.com.br/
 */

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ExternalDataSource;
use App\Helpers\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class CasaDosDadosProspectService
{
    private const API_BASE = 'https://api.casadosdados.com.br';

    private static ?Client $httpClient = null;

    private static function getClient(): Client
    {
        if (self::$httpClient === null) {
            self::$httpClient = new Client([
                'timeout' => 90,
                'verify' => false,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);
        }
        return self::$httpClient;
    }

    public static function testConnection(string $apiKey): array
    {
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            return ['success' => false, 'message' => 'Informe a chave de API (api-key).'];
        }
        try {
            $res = self::getClient()->get(self::API_BASE . '/v5/saldo', [
                'headers' => ['api-key' => $apiKey],
            ]);
            $data = json_decode($res->getBody()->getContents(), true);
            $total = $data['saldo_total'] ?? null;
            return [
                'success' => true,
                'message' => 'Conectado à Casa dos Dados.' . ($total !== null ? " Saldo total: {$total}." : ''),
                'saldo_total' => $total,
            ];
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';
            Logger::error('CasaDosDados::testConnection ' . $e->getMessage() . ' ' . substr($body, 0, 300));
            return [
                'success' => false,
                'message' => self::humanizeHttpError($e, $body),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Preview: 1 página, limite baixo
     */
    public static function preview(array $searchConfig, int $limit = 5): array
    {
        $apiKey = trim((string)($searchConfig['api_key'] ?? ''));
        if ($apiKey === '') {
            return ['success' => false, 'message' => 'Chave de API obrigatória.'];
        }
        $cfg = $searchConfig;
        $cfg['limite'] = min($limit, (int)($cfg['limite'] ?? 5));
        $cfg['pagina'] = 1;

        $body = self::buildPesquisaBody($cfg);
        if (!self::hasMeaningfulFilters($body)) {
            return ['success' => false, 'message' => 'Defina ao menos: UF, município, CNAE ou busca textual.'];
        }

        try {
            $tipo = in_array($cfg['tipo_resultado'] ?? 'completo', ['simples', 'completo'], true)
                ? $cfg['tipo_resultado'] : 'completo';
            $url = self::API_BASE . '/v5/cnpj/pesquisa?tipo_resultado=' . rawurlencode((string)$tipo);
            $res = self::getClient()->post($url, [
                'headers' => ['api-key' => $apiKey],
                'body' => json_encode($body, JSON_UNESCAPED_UNICODE),
            ]);
            $data = json_decode($res->getBody()->getContents(), true);
            if (!is_array($data)) {
                return ['success' => false, 'message' => 'Resposta inválida da API.'];
            }
            $rows = $data['cnpjs'] ?? [];
            $mapped = [];
            foreach ($rows as $row) {
                $mapped[] = self::mapRowToPreview($row);
            }
            return [
                'success' => true,
                'total' => (int)($data['total'] ?? count($rows)),
                'results' => $mapped,
            ];
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';
            return ['success' => false, 'message' => self::humanizeHttpError($e, $body)];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function sync(int $sourceId, int $contactListId): array
    {
        $startTime = microtime(true);
        $stats = [
            'started_at' => date('Y-m-d H:i:s'),
            'records_fetched' => 0,
            'records_created' => 0,
            'records_updated' => 0,
            'records_skipped' => 0,
            'records_failed' => 0,
        ];

        try {
            $source = ExternalDataSource::find($sourceId);
            if (!$source || ($source['type'] ?? '') !== 'casa_dos_dados') {
                throw new \Exception('Fonte inválida ou tipo não é casa_dos_dados');
            }

            $searchConfig = json_decode($source['search_config'] ?? '{}', true) ?: [];
            $apiKey = trim((string)($searchConfig['api_key'] ?? ''));
            if ($apiKey === '') {
                throw new \Exception('Chave de API não configurada na fonte (search_config.api_key)');
            }

            $forceUpdate = !empty($searchConfig['force_update']);
            $includeNoPhone = false;
            $maxPages = max(1, min(50, (int)($searchConfig['max_pages'] ?? 10)));
            $tipo = in_array($searchConfig['tipo_resultado'] ?? 'completo', ['simples', 'completo'], true)
                ? $searchConfig['tipo_resultado'] : 'completo';

            $allRows = [];
            $pagina = max(1, (int)($searchConfig['pagina'] ?? 1));

            for ($p = 0; $p < $maxPages; $p++) {
                $cfg = $searchConfig;
                $cfg['pagina'] = $pagina + $p;
                $body = self::buildPesquisaBody($cfg);
                if (!self::hasMeaningfulFilters($body)) {
                    throw new \Exception('Configure filtros de pesquisa (UF, município, CNAE ou texto).');
                }

                $url = self::API_BASE . '/v5/cnpj/pesquisa?tipo_resultado=' . rawurlencode((string)$tipo);
                Logger::info('CasaDosDados::sync POST pagina=' . $body['pagina'] . ' limite=' . $body['limite']);

                $res = self::getClient()->post($url, [
                    'headers' => ['api-key' => $apiKey],
                    'body' => json_encode($body, JSON_UNESCAPED_UNICODE),
                ]);
                $data = json_decode($res->getBody()->getContents(), true);
                if (!is_array($data)) {
                    throw new \Exception('Resposta inválida da API Casa dos Dados');
                }

                $batch = $data['cnpjs'] ?? [];
                $totalApi = (int)($data['total'] ?? 0);
                foreach ($batch as $row) {
                    $allRows[] = $row;
                }

                if (count($batch) === 0) {
                    break;
                }
                if (count($allRows) >= $totalApi && $totalApi > 0) {
                    break;
                }
                if (count($batch) < ($body['limite'] ?? 1)) {
                    break;
                }
            }

            $stats['records_fetched'] = count($allRows);

            foreach ($allRows as $row) {
                try {
                    $lead = self::companyRowToLead($row, $includeNoPhone);
                    if ($lead === null) {
                        $stats['records_skipped']++;
                        continue;
                    }
                    $r = self::processLead($lead, $contactListId, $sourceId, $forceUpdate);
                    if ($r['action'] === 'created') {
                        $stats['records_created']++;
                    } elseif ($r['action'] === 'updated') {
                        $stats['records_updated']++;
                    } elseif ($r['action'] === 'skipped') {
                        $stats['records_skipped']++;
                    }
                } catch (\Throwable $e) {
                    Logger::error('CasaDosDados::process row: ' . $e->getMessage());
                    $stats['records_failed']++;
                }
            }

            ExternalDataSource::update($sourceId, [
                'total_synced' => ($source['total_synced'] ?? 0) + $stats['records_created'] + $stats['records_updated'],
            ]);

            $message = "Sincronizado: {$stats['records_created']} novos, {$stats['records_updated']} atualizados, {$stats['records_skipped']} ignorados";
            ExternalDataSource::updateSyncStatus($sourceId, 'success', $message, $stats['records_fetched']);

            $stats['completed_at'] = date('Y-m-d H:i:s');
            $stats['status'] = 'success';
            $stats['execution_time_ms'] = round((microtime(true) - $startTime) * 1000);
            ExternalDataSource::logSync($sourceId, $stats);

            ContactList::update($contactListId, ['last_sync_at' => date('Y-m-d H:i:s')]);
            ContactList::recalculateTotal($contactListId);

            Logger::info('CasaDosDadosProspectService::sync concluído | ' . json_encode($stats, JSON_UNESCAPED_UNICODE));

            return [
                'success' => true,
                'message' => $message,
                'stats' => $stats,
            ];
        } catch (\Throwable $e) {
            Logger::error('CasaDosDadosProspectService::sync ' . $e->getMessage());
            $stats['completed_at'] = date('Y-m-d H:i:s');
            $stats['status'] = 'error';
            $stats['error_message'] = $e->getMessage();
            $stats['execution_time_ms'] = round((microtime(true) - $startTime) * 1000);
            ExternalDataSource::updateSyncStatus($sourceId, 'error', $e->getMessage());
            ExternalDataSource::logSync($sourceId, $stats);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'stats' => $stats,
            ];
        }
    }

    private static function humanizeHttpError(RequestException $e, string $body): string
    {
        $code = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
        $decoded = json_decode($body, true);
        $msg = $decoded['message'] ?? $decoded['error'] ?? $decoded['detail'] ?? '';
        if ($code === 401) {
            return 'Chave de API inválida ou não enviada (401).';
        }
        if ($code === 403) {
            return 'Sem saldo ou sem permissão para este recurso (403). Recarregue créditos no portal Casa dos Dados.';
        }
        return trim($msg !== '' ? $msg : ($e->getMessage() ?: 'Erro HTTP ' . $code));
    }

    private static function hasMeaningfulFilters(array $body): bool
    {
        return !empty($body['cnpj'])
            || !empty($body['uf'])
            || !empty($body['municipio'])
            || !empty($body['codigo_atividade_principal'])
            || !empty($body['codigo_atividade_secundaria'])
            || !empty($body['busca_textual'])
            || !empty($body['ddd'])
            || !empty($body['cep'])
            || !empty($body['bairro'])
            || !empty($body['cnpj_raiz'])
            || !empty($body['telefone']);
    }

    public static function buildPesquisaBody(array $cfg): array
    {
        $body = [];
        $body['limite'] = max(1, min(1000, (int)($cfg['limite'] ?? 100)));
        $body['pagina'] = max(1, (int)($cfg['pagina'] ?? 1));

        $body['situacao_cadastral'] = ['ATIVA'];
        if (!empty($cfg['situacao_cadastral'])) {
            if (is_string($cfg['situacao_cadastral'])) {
                $body['situacao_cadastral'] = array_values(array_filter(array_map(
                    static fn ($s) => strtoupper(trim($s)),
                    explode(',', $cfg['situacao_cadastral'])
                )));
            } elseif (is_array($cfg['situacao_cadastral'])) {
                $body['situacao_cadastral'] = $cfg['situacao_cadastral'];
            }
        }

        if (!empty($cfg['cnpj']) && is_array($cfg['cnpj'])) {
            $body['cnpj'] = $cfg['cnpj'];
        }

        if (!empty($cfg['uf'])) {
            $ufs = is_array($cfg['uf'])
                ? $cfg['uf']
                : preg_split('/[\s,;]+/', strtoupper(trim((string)$cfg['uf'])), -1, PREG_SPLIT_NO_EMPTY);
            $body['uf'] = array_values(array_filter(array_map('strtolower', $ufs)));
        }

        if (!empty($cfg['municipio'])) {
            $mun = is_array($cfg['municipio'])
                ? $cfg['municipio']
                : preg_split('/[,;]+/', (string)$cfg['municipio'], -1, PREG_SPLIT_NO_EMPTY);
            $body['municipio'] = array_values(array_filter(array_map(
                static function ($s) {
                    $s = trim($s);
                    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
                },
                $mun
            )));
        }

        if (!empty($cfg['cnae_principal'])) {
            $raw = is_array($cfg['cnae_principal'])
                ? $cfg['cnae_principal']
                : preg_split('/[\s,;]+/', preg_replace('/\D+/', ' ', (string)$cfg['cnae_principal']), -1, PREG_SPLIT_NO_EMPTY);
            $body['codigo_atividade_principal'] = array_values(array_filter(array_map('trim', $raw)));
        }

        if (!empty($cfg['incluir_cnae_secundaria'])) {
            $body['incluir_atividade_secundaria'] = true;
        }

        if (!empty($cfg['busca_textual']) && is_array($cfg['busca_textual'])) {
            $body['busca_textual'] = $cfg['busca_textual'];
        } elseif (!empty($cfg['busca_texto'])) {
            $textos = array_values(array_filter(array_map('trim', explode(',', (string)$cfg['busca_texto']))));
            if ($textos) {
                $tipo = ($cfg['busca_tipo'] ?? 'radical') === 'exata' ? 'exata' : 'radical';
                $body['busca_textual'] = [[
                    'texto' => $textos,
                    'tipo_busca' => $tipo,
                    'razao_social' => !isset($cfg['busca_razao']) || !empty($cfg['busca_razao']),
                    'nome_fantasia' => !isset($cfg['busca_fantasia']) || !empty($cfg['busca_fantasia']),
                    'nome_socio' => !empty($cfg['busca_socio']),
                ]];
            }
        }

        foreach (['ddd', 'cep', 'bairro'] as $f) {
            if (empty($cfg[$f])) {
                continue;
            }
            $parts = is_array($cfg[$f])
                ? $cfg[$f]
                : preg_split('/[\s,;]+/', trim((string)$cfg[$f]), -1, PREG_SPLIT_NO_EMPTY);
            $body[$f] = array_values(array_filter(array_map('trim', $parts)));
        }

        if (!empty($cfg['data_abertura_ultimos_dias'])) {
            $body['data_abertura'] = ['ultimos_dias' => max(1, (int)$cfg['data_abertura_ultimos_dias'])];
        }

        if (isset($cfg['capital_min']) || isset($cfg['capital_max'])) {
            $cap = [];
            if (isset($cfg['capital_min']) && $cfg['capital_min'] !== '') {
                $cap['minimo'] = (int)$cfg['capital_min'];
            }
            if (isset($cfg['capital_max']) && $cfg['capital_max'] !== '') {
                $cap['maximo'] = (int)$cfg['capital_max'];
            }
            if ($cap !== []) {
                $body['capital_social'] = $cap;
            }
        }

        if (!empty($cfg['matriz_filial']) && in_array($cfg['matriz_filial'], ['MATRIZ', 'FILIAL'], true)) {
            $body['matriz_filial'] = $cfg['matriz_filial'];
        }

        $mais = [];
        if (!empty($cfg['somente_matriz'])) {
            $mais['somente_matriz'] = true;
        }
        if (!empty($cfg['somente_filial'])) {
            $mais['somente_filial'] = true;
        }
        if (!empty($cfg['com_telefone'])) {
            $mais['com_telefone'] = true;
        }
        if (!empty($cfg['com_email'])) {
            $mais['com_email'] = true;
        }
        if (!empty($cfg['somente_celular'])) {
            $mais['somente_celular'] = true;
        }
        if (!empty($cfg['somente_fixo'])) {
            $mais['somente_fixo'] = true;
        }
        if ($mais !== []) {
            $body['mais_filtros'] = $mais;
        }

        return $body;
    }

    private static function mapRowToPreview(array $row): array
    {
        $lead = self::companyRowToLead($row, true);
        return [
            'cnpj' => preg_replace('/\D/', '', (string)($row['cnpj'] ?? '')),
            'name' => $lead['name'] ?? ($row['nome_fantasia'] ?? $row['razao_social'] ?? ''),
            'phone' => $lead['phone'] ?? '',
            'email' => $lead['email'] ?? '',
            'address' => $lead['address'] ?? '',
            'razao_social' => $row['razao_social'] ?? '',
        ];
    }

    /**
     * @return array{name: string, phone: string, email: string, address: string, cnpj: string, raw: array}|null
     */
    private static function companyRowToLead(array $row, bool $includeNoPhone): ?array
    {
        $cnpj = preg_replace('/\D/', '', (string)($row['cnpj'] ?? ''));
        $name = trim((string)($row['nome_fantasia'] ?? ''));
        if ($name === '') {
            $name = trim((string)($row['razao_social'] ?? ''));
        }
        if ($name === '') {
            $name = 'Empresa ' . ($cnpj ?: 's/cnpj');
        }

        $phones = self::extractPhones($row);
        $phone = $phones[0] ?? '';
        $phoneNorm = self::normalizePhone($phone);

        if ($phoneNorm === '' && !$includeNoPhone) {
            return null;
        }

        $email = self::extractEmail($row);
        $address = self::formatEndereco($row['endereco'] ?? []);

        return [
            'name' => $name,
            'phone' => $phoneNorm,
            'email' => $email,
            'address' => $address,
            'cnpj' => $cnpj,
            'razao_social' => trim((string)($row['razao_social'] ?? '')),
            'raw' => $row,
        ];
    }

    private static function extractPhones(array $data): array
    {
        $found = [];
        self::walkForPhones($data, $found);
        return array_values(array_unique(array_filter($found)));
    }

    private static function walkForPhones($node, array &$found): void
    {
        if (!is_array($node)) {
            return;
        }
        foreach ($node as $key => $val) {
            $k = is_string($key) ? strtolower($key) : '';
            if (is_array($val)) {
                if (isset($val['ddd'], $val['numero']) || isset($val['ddd'], $val['telefone'])) {
                    $ddd = preg_replace('/\D/', '', (string)($val['ddd'] ?? ''));
                    $num = preg_replace('/\D/', '', (string)($val['numero'] ?? $val['telefone'] ?? ''));
                    if ($ddd !== '' && $num !== '') {
                        $found[] = $ddd . $num;
                    }
                } else {
                    self::walkForPhones($val, $found);
                }
            } elseif (is_string($val) && $val !== '') {
                if ($k !== '' && (str_contains($k, 'telefone') || str_contains($k, 'fone') || $k === 'celular' || $k === 'whatsapp')) {
                    $digits = preg_replace('/\D/', '', $val);
                    if (strlen($digits) >= 10) {
                        $found[] = $digits;
                    }
                }
            }
        }
    }

    private static function extractEmail(array $data): string
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($data));
        foreach ($it as $val) {
            if (is_string($val) && filter_var($val, FILTER_VALIDATE_EMAIL)) {
                return $val;
            }
        }
        return '';
    }

    private static function formatEndereco($end): string
    {
        if (!is_array($end) || $end === []) {
            return '';
        }
        $parts = array_filter([
            trim((string)($end['tipo_logradouro'] ?? '')),
            trim((string)($end['logradouro'] ?? '')),
            trim((string)($end['numero'] ?? '')),
            trim((string)($end['complemento'] ?? '')),
            trim((string)($end['bairro'] ?? '')),
            trim((string)($end['municipio'] ?? '')),
            trim((string)($end['uf'] ?? '')),
            trim((string)($end['cep'] ?? '')),
        ]);
        return implode(', ', $parts);
    }

    private static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone);
        if ($phone === '') {
            return '';
        }
        if (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }
        if (strlen($phone) === 10 || strlen($phone) === 11) {
            $phone = '55' . $phone;
        }
        return $phone;
    }

    /**
     * @param array $lead from companyRowToLead
     */
    private static function processLead(array $lead, int $contactListId, int $sourceId, bool $forceUpdate): array
    {
        $cnpj = $lead['cnpj'] ?? '';
        $phone = $lead['phone'] ?? '';

        $freshData = [
            'company' => $lead['razao_social'] ?: $lead['name'],
            'address' => $lead['address'],
        ];
        if (!empty($lead['email'])) {
            $freshData['email'] = $lead['email'];
        }

        if ($cnpj !== '') {
            $existing = self::findContactByCnpj($cnpj);
            if ($existing) {
                if ($forceUpdate) {
                    self::mergeCustomAttrs($existing, $lead, $sourceId);
                    Contact::update($existing['id'], $freshData);
                }
                if (!ContactList::hasContact($contactListId, $existing['id'])) {
                    ContactList::addContact($contactListId, $existing['id'], ['source' => 'casa_dos_dados', 'cnpj' => $cnpj]);
                    return ['action' => 'updated', 'contact_id' => $existing['id']];
                }
                if ($forceUpdate) {
                    return ['action' => 'updated', 'contact_id' => $existing['id']];
                }
                return ['action' => 'skipped', 'reason' => 'CNPJ já na lista'];
            }
        }

        if ($phone !== '') {
            $byPhone = Contact::findByPhoneNormalized($phone);
            if ($byPhone) {
                if ($forceUpdate) {
                    self::mergeCustomAttrs($byPhone, $lead, $sourceId);
                    Contact::update($byPhone['id'], $freshData);
                }
                if (!ContactList::hasContact($contactListId, $byPhone['id'])) {
                    ContactList::addContact($contactListId, $byPhone['id'], ['source' => 'casa_dos_dados', 'cnpj' => $cnpj]);
                    return ['action' => 'updated', 'contact_id' => $byPhone['id']];
                }
                if ($forceUpdate) {
                    return ['action' => 'updated', 'contact_id' => $byPhone['id']];
                }
                return ['action' => 'skipped', 'reason' => 'Telefone já existe'];
            }
        }

        if ($phone === '') {
            return ['action' => 'skipped', 'reason' => 'Sem telefone'];
        }

        $city = null;
        $state = null;
        if (!empty($lead['raw']['endereco']) && is_array($lead['raw']['endereco'])) {
            $city = $lead['raw']['endereco']['municipio'] ?? null;
            $state = $lead['raw']['endereco']['uf'] ?? null;
        }

        $contactData = [
            'name' => $lead['name'],
            'phone' => $phone,
            'source' => 'casa_dos_dados',
            'company' => $lead['razao_social'] ?: $lead['name'],
            'address' => $lead['address'],
            'city' => $city,
            'state' => $state,
            'custom_attributes' => json_encode([
                'source_id' => $sourceId,
                'cnpj' => $cnpj,
                'synced_at' => date('Y-m-d H:i:s'),
            ], JSON_UNESCAPED_UNICODE),
        ];
        if (!empty($lead['email'])) {
            $contactData['email'] = $lead['email'];
        }

        $contactId = Contact::create($contactData);
        ContactList::addContact($contactListId, $contactId, ['source' => 'casa_dos_dados', 'cnpj' => $cnpj]);

        return ['action' => 'created', 'contact_id' => $contactId];
    }

    private static function findContactByCnpj(string $cnpj): ?array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        if ($cnpj === '') {
            return null;
        }
        $rows = \App\Helpers\Database::fetchAll(
            "SELECT * FROM contacts WHERE custom_attributes IS NOT NULL AND custom_attributes != '' AND custom_attributes LIKE ? LIMIT 200",
            ['%' . $cnpj . '%']
        );
        foreach ($rows as $r) {
            $attrs = json_decode($r['custom_attributes'] ?? '{}', true);
            if (!is_array($attrs)) {
                continue;
            }
            $stored = preg_replace('/\D/', '', (string)($attrs['cnpj'] ?? ''));
            if ($stored === $cnpj) {
                return $r;
            }
        }
        return null;
    }

    private static function mergeCustomAttrs(array $existing, array $lead, int $sourceId): void
    {
        $attrs = [];
        if (!empty($existing['custom_attributes'])) {
            $attrs = is_string($existing['custom_attributes'])
                ? (json_decode($existing['custom_attributes'], true) ?? [])
                : $existing['custom_attributes'];
        }
        $attrs['source_id'] = $sourceId;
        $attrs['cnpj'] = $lead['cnpj'] ?? ($attrs['cnpj'] ?? '');
        $attrs['synced_at'] = date('Y-m-d H:i:s');
        Contact::update($existing['id'], ['custom_attributes' => json_encode($attrs, JSON_UNESCAPED_UNICODE)]);
    }
}
