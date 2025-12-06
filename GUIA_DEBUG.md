# Guia de Debug - Página em Branco

## 🔍 Arquivos de Debug Criados

### 1. `public/debug-login.php`
**Teste completo do login passo a passo**
- Acesse: `http://localhost/chat/public/debug-login.php`
- Mostra cada etapa do processo
- Identifica onde está falhando

### 2. `public/test-view-direct.php`
**Teste direto da view de login**
- Acesse: `http://localhost/chat/public/test-view-direct.php`
- Carrega a view diretamente sem passar pelo Router
- Verifica se a view funciona isoladamente

### 3. `public/test-full-flow.php`
**Teste do fluxo completo**
- Acesse: `http://localhost/chat/public/test-full-flow.php`
- Simula todo o fluxo do Router
- Mostra output gerado

### 4. `public/check-output.php`
**Verifica problemas de output buffering**
- Acesse: `http://localhost/chat/public/check-output.php`
- Verifica se há problemas com buffers
- Testa Response::view diretamente

## 🐛 Como Debugar

### Passo 1: Verificar Logs
Os logs do PHP/Apache mostrarão mensagens de debug. Verifique:
- Logs do Apache (geralmente em `C:\laragon\bin\apache\logs\error.log`)
- Ou ative logs no PHP

### Passo 2: Executar Testes
Execute os arquivos de debug na ordem:
1. `debug-login.php` - Ver o que está acontecendo
2. `test-view-direct.php` - Ver se a view funciona
3. `test-full-flow.php` - Ver o fluxo completo

### Passo 3: Verificar Output
Se a página está em branco, pode ser:
- ✅ View não está gerando output
- ✅ Router está fazendo redirect silencioso
- ✅ Há um erro que está sendo suprimido
- ✅ Output buffer está bloqueando

## 🔧 Possíveis Problemas e Soluções

### Problema 1: View não gera output
**Sintoma**: `test-view-direct.php` mostra que a view existe mas não gera output

**Solução**: Verificar se há algum `exit` ou `die` antes do output, ou se há erro de sintaxe PHP

### Problema 2: Router não encontra rota
**Sintoma**: `debug-login.php` mostra que o URI processado não corresponde às rotas

**Solução**: Verificar o processamento do URI no Router

### Problema 3: Controller não executa
**Sintoma**: Router encontra rota mas controller não executa

**Solução**: Verificar se o controller existe e se o método existe

### Problema 4: Response::view falha silenciosamente
**Sintoma**: Controller executa mas Response::view não mostra nada

**Solução**: Verificar se há output buffer ativo ou headers já enviados

## 📝 Checklist de Debug

- [ ] Executar `debug-login.php` e verificar cada passo
- [ ] Executar `test-view-direct.php` para ver se view funciona
- [ ] Verificar logs do Apache/PHP
- [ ] Verificar se há erros no console do navegador (F12)
- [ ] Verificar se há redirects (Network tab no DevTools)
- [ ] Verificar output buffer (ob_get_level())

## 🎯 Próximos Passos

Após executar os testes, informe:
1. O que cada teste mostrou
2. Se algum erro apareceu nos logs
3. Se a view funciona isoladamente
4. Qual passo falhou no debug-login.php

Com essas informações, conseguiremos identificar e corrigir o problema exato!

