<?php
/**
 * Helper de Validação
 */

namespace App\Helpers;

class Validator
{
    /**
     * Validar dados
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $rulesArray = explode('|', $ruleString);
            $value = $data[$field] ?? null;

            foreach ($rulesArray as $rule) {
                $ruleParts = explode(':', $rule);
                $ruleName = $ruleParts[0];
                $ruleValue = $ruleParts[1] ?? null;

                switch ($ruleName) {
                    case 'required':
                        if (empty($value) && $value !== '0') {
                            $errors[$field][] = "O campo {$field} é obrigatório";
                        }
                        break;

                    case 'email':
                        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field][] = "O campo {$field} deve ser um email válido";
                        }
                        break;

                    case 'min':
                        // Verificar se é numérico ou string
                        if (!empty($value)) {
                            if (is_numeric($value)) {
                                // Validação numérica
                                if ((float)$value < (float)$ruleValue) {
                                    $errors[$field][] = "O campo {$field} deve ser no mínimo {$ruleValue}";
                                }
                            } else {
                                // Validação de string
                                if (strlen($value) < (int)$ruleValue) {
                                    $errors[$field][] = "O campo {$field} deve ter no mínimo {$ruleValue} caracteres";
                                }
                            }
                        }
                        break;

                    case 'max':
                        // Verificar se é numérico ou string
                        if (!empty($value)) {
                            if (is_numeric($value)) {
                                // Validação numérica
                                if ((float)$value > (float)$ruleValue) {
                                    $errors[$field][] = "O campo {$field} deve ser no máximo {$ruleValue}";
                                }
                            } else {
                                // Validação de string
                                if (strlen($value) > (int)$ruleValue) {
                                    $errors[$field][] = "O campo {$field} deve ter no máximo {$ruleValue} caracteres";
                                }
                            }
                        }
                        break;

                    case 'numeric':
                        if (!empty($value) && !is_numeric($value)) {
                            $errors[$field][] = "O campo {$field} deve ser numérico";
                        }
                        break;

                    case 'integer':
                        if (!empty($value)) {
                            if (!is_numeric($value) || (int)$value != $value) {
                                $errors[$field][] = "O campo {$field} deve ser um número inteiro";
                            }
                        }
                        break;
                }
            }
        }

        return $errors;
    }

    /**
     * Verificar se validação passou
     */
    public static function passes(array $errors): bool
    {
        return empty($errors);
    }
}

