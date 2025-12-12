<?php
/**
 * Controller LogController
 * Visualização de logs do sistema via web
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Permission;
use App\Helpers\Logger;

class LogController
{
    /**
     * Listar arquivos de log disponíveis
     */
    public function index(): void
    {
        Permission::abortIfCannot('admin.logs');
        
        $logDirs = [
            'logs' => __DIR__ . '/../../logs',
            'storage/logs' => __DIR__ . '/../../storage/logs'
        ];
        
        $logFiles = [];
        
        foreach ($logDirs as $dirName => $dirPath) {
            if (is_dir($dirPath)) {
                $files = scandir($dirPath);
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..' && is_file($dirPath . '/' . $file)) {
                        $filePath = $dirPath . '/' . $file;
                        $logFiles[] = [
                            'name' => $file,
                            'path' => $dirName . '/' . $file,
                            'size' => filesize($filePath),
                            'modified' => filemtime($filePath),
                            'dir' => $dirName
                        ];
                    }
                }
            }
        }
        
        // Ordenar por data de modificação (mais recentes primeiro)
        usort($logFiles, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
        
        Response::view('logs/index', [
            'logFiles' => $logFiles
        ]);
    }
    
    /**
     * Visualizar conteúdo de um arquivo de log
     */
    public function view(): void
    {
        Permission::abortIfCannot('admin.logs');
        
        $file = $_GET['file'] ?? '';
        $lines = (int)($_GET['lines'] ?? 500); // Padrão: últimas 500 linhas
        $search = $_GET['search'] ?? '';
        
        if (empty($file)) {
            Response::json(['error' => 'Arquivo não especificado'], 400);
            return;
        }
        
        // Validar caminho (prevenir directory traversal)
        $file = basename($file);
        $allowedDirs = ['logs', 'storage/logs'];
        $dir = $_GET['dir'] ?? 'logs';
        
        if (!in_array($dir, $allowedDirs)) {
            Response::json(['error' => 'Diretório inválido'], 400);
            return;
        }
        
        $filePath = __DIR__ . '/../../' . $dir . '/' . $file;
        
        // Verificar se arquivo existe e está dentro do diretório permitido
        $realPath = realpath($filePath);
        $basePath = realpath(__DIR__ . '/../../');
        
        if (!$realPath || strpos($realPath, $basePath) !== 0) {
            Response::json(['error' => 'Arquivo não encontrado ou acesso negado'], 404);
            return;
        }
        
        if (!is_file($realPath)) {
            Response::json(['error' => 'Arquivo não encontrado'], 404);
            return;
        }
        
        // Ler arquivo
        $content = file_get_contents($realPath);
        $allLines = explode("\n", $content);
        
        // Aplicar filtro de busca se fornecido
        if (!empty($search)) {
            $allLines = array_filter($allLines, function($line) use ($search) {
                return stripos($line, $search) !== false;
            });
            $allLines = array_values($allLines);
        }
        
        // Pegar últimas N linhas
        $totalLines = count($allLines);
        $startLine = max(0, $totalLines - $lines);
        $logLines = array_slice($allLines, $startLine);
        
        // Formatar linhas com numeração
        $formattedLines = [];
        foreach ($logLines as $index => $line) {
            $lineNumber = $startLine + $index + 1;
            $formattedLines[] = [
                'number' => $lineNumber,
                'content' => htmlspecialchars($line, ENT_QUOTES, 'UTF-8')
            ];
        }
        
        Response::json([
            'file' => $file,
            'dir' => $dir,
            'totalLines' => $totalLines,
            'showingLines' => count($formattedLines),
            'lines' => $formattedLines,
            'fileSize' => filesize($realPath),
            'lastModified' => filemtime($realPath)
        ]);
    }
    
    /**
     * Download de arquivo de log
     */
    public function download(): void
    {
        Permission::abortIfCannot('admin.logs');
        
        $file = $_GET['file'] ?? '';
        $dir = $_GET['dir'] ?? 'logs';
        
        if (empty($file)) {
            Response::json(['error' => 'Arquivo não especificado'], 400);
            return;
        }
        
        // Validar caminho
        $file = basename($file);
        $allowedDirs = ['logs', 'storage/logs'];
        
        if (!in_array($dir, $allowedDirs)) {
            Response::json(['error' => 'Diretório inválido'], 400);
            return;
        }
        
        $filePath = __DIR__ . '/../../' . $dir . '/' . $file;
        $realPath = realpath($filePath);
        $basePath = realpath(__DIR__ . '/../../');
        
        if (!$realPath || strpos($realPath, $basePath) !== 0 || !is_file($realPath)) {
            Response::json(['error' => 'Arquivo não encontrado'], 404);
            return;
        }
        
        // Enviar arquivo
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($realPath));
        readfile($realPath);
        exit;
    }
    
    /**
     * Limpar arquivo de log
     */
    public function clear(): void
    {
        Permission::abortIfCannot('admin.logs');
        
        $file = $_POST['file'] ?? '';
        $dir = $_POST['dir'] ?? 'logs';
        
        if (empty($file)) {
            Response::json(['error' => 'Arquivo não especificado'], 400);
            return;
        }
        
        // Validar caminho
        $file = basename($file);
        $allowedDirs = ['logs', 'storage/logs'];
        
        if (!in_array($dir, $allowedDirs)) {
            Response::json(['error' => 'Diretório inválido'], 400);
            return;
        }
        
        $filePath = __DIR__ . '/../../' . $dir . '/' . $file;
        $realPath = realpath($filePath);
        $basePath = realpath(__DIR__ . '/../../');
        
        if (!$realPath || strpos($realPath, $basePath) !== 0 || !is_file($realPath)) {
            Response::json(['error' => 'Arquivo não encontrado'], 404);
            return;
        }
        
        // Limpar arquivo (escrever string vazia)
        file_put_contents($realPath, '');
        
        Response::json(['success' => true, 'message' => 'Log limpo com sucesso']);
    }
}

