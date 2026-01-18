<?php
/**
 * Service ContactListService
 * Lógica de negócio para listas de contatos
 */

namespace App\Services;

use App\Models\ContactList;
use App\Models\Contact;
use App\Helpers\Validator;
use App\Helpers\Logger;

class ContactListService
{
    /**
     * Criar lista
     */
    public static function create(array $data): int
    {
        $errors = Validator::validate($data, [
            'name' => 'required|string|max:255'
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        $data['is_dynamic'] = $data['is_dynamic'] ?? false;
        $data['total_contacts'] = 0;

        if (isset($data['filter_config']) && is_array($data['filter_config'])) {
            $data['filter_config'] = json_encode($data['filter_config']);
        }

        $listId = ContactList::create($data);
        
        Logger::info("Lista criada: ID={$listId}, Nome={$data['name']}");
        
        return $listId;
    }

    /**
     * Atualizar lista
     */
    public static function update(int $listId, array $data): bool
    {
        $list = ContactList::find($listId);
        if (!$list) {
            throw new \InvalidArgumentException('Lista não encontrada');
        }

        if (isset($data['filter_config']) && is_array($data['filter_config'])) {
            $data['filter_config'] = json_encode($data['filter_config']);
        }

        return ContactList::update($listId, $data);
    }

    /**
     * Adicionar contato à lista
     */
    public static function addContact(int $listId, int $contactId, array $customVariables = [], ?int $userId = null): bool
    {
        $list = ContactList::find($listId);
        if (!$list) {
            throw new \InvalidArgumentException('Lista não encontrada');
        }

        $contact = Contact::find($contactId);
        if (!$contact) {
            throw new \InvalidArgumentException('Contato não encontrado');
        }

        $result = ContactList::addContact($listId, $contactId, $customVariables, $userId);
        
        if ($result) {
            Logger::info("Contato adicionado à lista: ListaID={$listId}, ContatoID={$contactId}");
        }

        return $result;
    }

    /**
     * Adicionar múltiplos contatos
     */
    public static function addContacts(int $listId, array $contactIds, ?int $userId = null): int
    {
        $added = 0;

        foreach ($contactIds as $contactId) {
            try {
                if (self::addContact($listId, $contactId, [], $userId)) {
                    $added++;
                }
            } catch (\Exception $e) {
                Logger::error("Erro ao adicionar contato {$contactId} à lista {$listId}: " . $e->getMessage());
            }
        }

        Logger::info("Contatos adicionados em massa: ListaID={$listId}, Adicionados={$added}");

        return $added;
    }

    /**
     * Remover contato da lista
     */
    public static function removeContact(int $listId, int $contactId): bool
    {
        return ContactList::removeContact($listId, $contactId);
    }

    /**
     * Limpar lista
     */
    public static function clearList(int $listId): bool
    {
        return ContactList::clearList($listId);
    }

    /**
     * Importar de CSV
     * Formato esperado: name, phone, email, empresa, cidade, etc
     */
    public static function importFromCsv(int $listId, string $filePath, array $mapping = []): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException('Arquivo não encontrado');
        }

        $list = ContactList::find($listId);
        if (!$list) {
            throw new \InvalidArgumentException('Lista não encontrada');
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception('Não foi possível abrir o arquivo');
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        // Ler header
        $header = fgetcsv($handle, 1000, ',');
        if (!$header) {
            throw new \Exception('Arquivo CSV vazio ou inválido');
        }

        // Mapear colunas
        $nameCol = array_search('name', $header) ?: array_search('nome', $header);
        $phoneCol = array_search('phone', $header) ?: array_search('telefone', $header);
        $emailCol = array_search('email', $header);

        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            try {
                $name = $row[$nameCol] ?? '';
                $phone = $row[$phoneCol] ?? '';
                $email = $row[$emailCol] ?? null;

                if (empty($name) || empty($phone)) {
                    $skipped++;
                    continue;
                }

                // Buscar ou criar contato
                $contact = Contact::findByEmailOrPhone($email, $phone);
                if (!$contact) {
                    $contactData = [
                        'name' => $name,
                        'phone' => $phone,
                        'email' => $email
                    ];
                    $contactId = Contact::create($contactData);
                } else {
                    $contactId = $contact['id'];
                }

                // Adicionar à lista
                ContactList::addContact($listId, $contactId);
                $imported++;

            } catch (\Exception $e) {
                $errors[] = "Linha " . ($imported + $skipped + 1) . ": " . $e->getMessage();
                $skipped++;
            }
        }

        fclose($handle);

        Logger::info("Import CSV concluído: ListaID={$listId}, Importados={$imported}, Pulados={$skipped}");

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }

    /**
     * Deletar lista
     */
    public static function delete(int $listId): bool
    {
        $list = ContactList::find($listId);
        if (!$list) {
            throw new \InvalidArgumentException('Lista não encontrada');
        }

        // Verificar se está sendo usada em alguma campanha
        $sql = "SELECT COUNT(*) as total FROM campaigns WHERE contact_list_id = ? AND status NOT IN ('completed', 'cancelled')";
        $result = \App\Helpers\Database::fetch($sql, [$listId]);
        
        if (($result['total'] ?? 0) > 0) {
            throw new \Exception('Lista está sendo usada em campanhas ativas. Não é possível deletar.');
        }

        return ContactList::delete($listId);
    }
}
