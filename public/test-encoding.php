<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Teste Encoding</title>
</head>
<body>
    <h1>Teste de Encoding UTF-8</h1>
    
    <script>
// Teste 1: String com acentos direto no script
const test1 = "você está testando";
console.log("Test 1:", test1);

// Teste 2: Via JSON encode
const test2 = <?= json_encode("você está testando", JSON_UNESCAPED_UNICODE) ?>;
console.log("Test 2:", test2);

// Teste 3: Via JSON encode com flags completas
const test3 = <?= json_encode("você está testando", JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;
console.log("Test 3:", test3);

// Teste 4: Objeto complexo
const test4 = <?= json_encode([
    'mensagem' => "Vou transferir você para um especialista",
    'texto' => "Mensagem com: aspas \"duplas\" e 'simples'",
    'campo' => 'text'
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;
console.log("Test 4:", test4);

console.log("✅ Todos os testes passaram!");
    </script>
</body>
</html>

