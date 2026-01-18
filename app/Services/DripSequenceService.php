<?php
/**
 * Service DripSequenceService
 * Lógica de negócio para sequências Drip
 */

namespace App\Services;

use App\Models\DripSequence;
use App\Helpers\Validator;
use App\Helpers\Logger;
use App\Helpers\Database;

class DripSequenceService
{
    /**
     * Criar sequência
     */
    public static function create(array $data): int
    {
        $errors = Validator::validate($data, [
            'name' => 'required|string|max:255',
            'steps' => 'required|array'
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        if (count($data['steps']) < 2) {
            throw new \InvalidArgumentException('É necessário pelo menos 2 etapas');
        }

        // Criar sequência
        $sequenceData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => 'active',
            'total_steps' => count($data['steps']),
            'created_by' => $data['created_by'] ?? null
        ];

        $sequenceId = DripSequence::create($sequenceData);

        // Criar etapas
        foreach ($data['steps'] as $step) {
            $stepData = [
                'sequence_id' => $sequenceId,
                'step_order' => $step['step_order'],
                'name' => $step['name'],
                'message_content' => $step['message_content'],
                'delay_days' => $step['delay_days'] ?? 0,
                'delay_hours' => $step['delay_hours'] ?? 0,
                'trigger_type' => 'time',
                'condition_type' => $step['condition_type'] ?? null,
                'status' => 'active'
            ];

            $sql = "INSERT INTO drip_steps (sequence_id, step_order, name, message_content, delay_days, delay_hours, trigger_type, condition_type, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            Database::execute($sql, [
                $stepData['sequence_id'],
                $stepData['step_order'],
                $stepData['name'],
                $stepData['message_content'],
                $stepData['delay_days'],
                $stepData['delay_hours'],
                $stepData['trigger_type'],
                $stepData['condition_type'],
                $stepData['status']
            ]);
        }

        Logger::info("Sequência Drip criada: ID={$sequenceId}, Etapas=" . count($data['steps']));

        return $sequenceId;
    }

    /**
     * Deletar sequência
     */
    public static function delete(int $sequenceId): bool
    {
        $sequence = DripSequence::find($sequenceId);

        if (!$sequence) {
            throw new \InvalidArgumentException('Sequência não encontrada');
        }

        // Deletar também remove etapas e progresso (CASCADE)
        $result = DripSequence::delete($sequenceId);

        Logger::info("Sequência Drip deletada: ID={$sequenceId}");

        return $result;
    }

    /**
     * Adicionar contatos à sequência
     */
    public static function addContacts(int $sequenceId, array $contactIds): int
    {
        $sequence = DripSequence::find($sequenceId);

        if (!$sequence) {
            throw new \InvalidArgumentException('Sequência não encontrada');
        }

        $added = 0;

        foreach ($contactIds as $contactId) {
            if (DripSequence::addContact($sequenceId, $contactId)) {
                $added++;
            }
        }

        Logger::info("Contatos adicionados à sequência Drip: ID={$sequenceId}, Contatos={$added}");

        return $added;
    }

    /**
     * Processar sequências pendentes (chamado por cron)
     */
    public static function processPending(): array
    {
        $sequences = DripSequence::getActive();
        $processed = 0;
        $errors = 0;

        foreach ($sequences as $sequence) {
            $steps = DripSequence::getSteps($sequence['id']);

            foreach ($steps as $step) {
                try {
                    // Buscar contatos prontos para este passo
                    $contacts = DripSequence::getContactsReadyForNextStep(
                        $sequence['id'],
                        $step['step_order']
                    );

                    foreach ($contacts as $contact) {
                        // Aqui você pode criar uma campanha ou enviar diretamente
                        // Por simplicidade, apenas avançar o contato
                        DripSequence::advanceContact($sequence['id'], $contact['contact_id']);
                        $processed++;

                        // Se for último passo, completar
                        if ($step['step_order'] >= $sequence['total_steps']) {
                            DripSequence::completeForContact($sequence['id'], $contact['contact_id']);
                        }
                    }
                } catch (\Exception $e) {
                    $errors++;
                    Logger::error("Erro ao processar passo drip: " . $e->getMessage());
                }
            }
        }

        return [
            'processed' => $processed,
            'errors' => $errors
        ];
    }
}
