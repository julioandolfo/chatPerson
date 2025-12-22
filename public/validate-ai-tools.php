<?php
/**
 * Script CLI para validar tools de IA
 * Uso: php public/validate-ai-tools.php [--agent-id=ID] [--tool-id=ID] [--format=json|text]
 */

require_once __DIR__ . '/../config/bootstrap.php';

use App\Services\AIToolValidationService;
use App\Models\AITool;
use App\Models\AIAgent;

// Parse argumentos
$options = getopt('', ['agent-id:', 'tool-id:', 'format:', 'help']);

if (isset($options['help'])) {
    echo "Validação de Tools de IA\n";
    echo "Uso: php public/validate-ai-tools.php [opções]\n\n";
    echo "Opções:\n";
    echo "  --agent-id=ID    Validar tools de um agente específico\n";
    echo "  --tool-id=ID     Validar uma tool específica\n";
    echo "  --format=FORMAT  Formato de saída: json ou text (padrão: text)\n";
    echo "  --help           Mostrar esta ajuda\n";
    exit(0);
}

$format = $options['format'] ?? 'text';
$agentId = isset($options['agent-id']) ? (int)$options['agent-id'] : null;
$toolId = isset($options['tool-id']) ? (int)$options['tool-id'] : null;

try {
    if ($toolId) {
        // Validar tool específica
        $tool = AITool::find($toolId);
        if (!$tool) {
            echo "❌ Tool ID {$toolId} não encontrada\n";
            exit(1);
        }
        
        $validation = AIToolValidationService::validateTool($tool);
        
        if ($format === 'json') {
            echo json_encode([
                'tool_id' => $toolId,
                'tool_name' => $tool['name'],
                'validation' => $validation
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "🔍 Validação da Tool: {$tool['name']} (ID: {$toolId})\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            
            if ($validation['valid']) {
                echo "✅ Tool válida!\n";
            } else {
                echo "❌ Tool inválida!\n";
            }
            
            if (!empty($validation['errors'])) {
                echo "\n❌ Erros:\n";
                foreach ($validation['errors'] as $error) {
                    echo "   • {$error}\n";
                }
            }
            
            if (!empty($validation['warnings'])) {
                echo "\n⚠️  Avisos:\n";
                foreach ($validation['warnings'] as $warning) {
                    echo "   • {$warning}\n";
                }
            }
        }
        
        exit($validation['valid'] ? 0 : 1);
        
    } elseif ($agentId) {
        // Validar integração de um agente
        $agent = AIAgent::find($agentId);
        if (!$agent) {
            echo "❌ Agente ID {$agentId} não encontrado\n";
            exit(1);
        }
        
        $integration = AIToolValidationService::validateOpenAIIntegration($agentId);
        
        if ($format === 'json') {
            echo json_encode([
                'agent_id' => $agentId,
                'agent_name' => $agent['name'],
                'integration' => $integration
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "🔍 Validação de Integração: {$agent['name']} (ID: {$agentId})\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            
            if ($integration['valid']) {
                echo "✅ Integração válida!\n";
            } else {
                echo "❌ Integração inválida!\n";
            }
            
            echo "   Tools: {$integration['functions_count']}\n";
            
            if (!empty($integration['errors'])) {
                echo "\n❌ Erros:\n";
                foreach ($integration['errors'] as $error) {
                    echo "   • {$error}\n";
                }
            }
            
            if (!empty($integration['warnings'])) {
                echo "\n⚠️  Avisos:\n";
                foreach ($integration['warnings'] as $warning) {
                    echo "   • {$warning}\n";
                }
            }
        }
        
        exit($integration['valid'] ? 0 : 1);
        
    } else {
        // Validar todas as tools
        $report = AIToolValidationService::generateReport();
        
        if ($format === 'json') {
            echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "🔍 Relatório de Validação de Tools de IA\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "Data: {$report['timestamp']}\n\n";
            
            $toolsValidation = $report['tools_validation'];
            echo "📊 Estatísticas Gerais:\n";
            echo "   Total de tools: {$toolsValidation['total']}\n";
            echo "   ✅ Válidas: {$toolsValidation['valid']}\n";
            echo "   ❌ Inválidas: {$toolsValidation['invalid']}\n\n";
            
            if (!empty($toolsValidation['errors'])) {
                echo "❌ Erros Encontrados:\n";
                foreach ($toolsValidation['errors'] as $error) {
                    echo "   • {$error}\n";
                }
                echo "\n";
            }
            
            if (!empty($toolsValidation['warnings'])) {
                echo "⚠️  Avisos:\n";
                foreach ($toolsValidation['warnings'] as $warning) {
                    echo "   • {$warning}\n";
                }
                echo "\n";
            }
            
            if (!empty($toolsValidation['tools'])) {
                echo "📋 Detalhes por Tool:\n";
                foreach ($toolsValidation['tools'] as $toolData) {
                    $status = $toolData['validation']['valid'] ? '✅' : '❌';
                    echo "   {$status} {$toolData['name']} (slug: {$toolData['slug']})\n";
                    
                    if (!empty($toolData['validation']['errors'])) {
                        foreach ($toolData['validation']['errors'] as $error) {
                            echo "      • {$error}\n";
                        }
                    }
                }
                echo "\n";
            }
            
            if (!empty($report['agents_with_tools'])) {
                echo "🤖 Agentes com Tools:\n";
                foreach ($report['agents_with_tools'] as $agentData) {
                    echo "   • {$agentData['agent_name']} (ID: {$agentData['agent_id']}) - {$agentData['tools_count']} tools\n";
                    $integration = $agentData['integration'];
                    if (!$integration['valid']) {
                        echo "      ❌ Integração inválida\n";
                        foreach ($integration['errors'] as $error) {
                            echo "         • {$error}\n";
                        }
                    }
                }
            }
        }
        
        $hasErrors = $toolsValidation['invalid'] > 0;
        exit($hasErrors ? 1 : 0);
    }
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    if ($format === 'json') {
        echo json_encode(['error' => $e->getMessage()]) . "\n";
    }
    exit(1);
}

