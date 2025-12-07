# Documentação da API QuePasa WhatsApp

**Versão:** 4.0.0  
**Base URL:** `https://whats.orbichat.com.br`  
**Documentação Swagger:** https://whats.orbichat.com.br/swagger/index.html

---

## Autenticação

Todas as requisições requerem autenticação via header:

```
X-QUEPASA-TOKEN: SEU_TOKEN
```

O token é o token da conexão WhatsApp configurado no sistema.

---

## Endpoint Principal: POST /send

### Descrição
Endpoint para enviar mensagens via WhatsApp. Aceita múltiplos tipos de mensagem:
- Texto simples (campo `text`)
- Arquivos por URL (campo `url`) — o servidor baixa e envia como anexo
- Conteúdo Base64 (campo `content`) — use formato `data:<mime>;base64,<data>`
- Enquetes/Polls (campo `poll`) — envie o JSON da enquete no campo `poll`
- Localização (campo `location`) — envie localização com latitude/longitude no objeto `location`
- Contato (campo `contact`) — envie contato com telefone/nome no objeto `contact`

### URL
```
POST https://whats.orbichat.com.br/send
```

### Headers Obrigatórios
```
X-QUEPASA-TOKEN: SEU_TOKEN
X-QUEPASA-CHATID: JID_DO_CONTATO_OU_GRUPO (ex: "5511999999999@s.whatsapp.net")
X-QUEPASA-TRACKID: algum-id-opcional (para rastrear no seu sistema)
Content-Type: application/json
Accept: application/json
```

**Nota:** Os headers `X-QUEPASA-CHATID` e `X-QUEPASA-TRACKID` são opcionais se você enviar `chatId` e `trackId` no body da requisição.

### Request Body

#### Campos Principais

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|--------------|-----------|
| `chatId` | string | Sim | Identificador do chat (pode ser WID, LID ou número com sufixo `@s.whatsapp.net`) |
| `text` | string | Não* | Texto da mensagem (*obrigatório se não houver `url`, `content`, `poll`, `location` ou `contact`) |
| `url` | string | Não | URL pública para baixar um arquivo |
| `content` | string | Não | Conteúdo embutido em base64 (ex: `data:image/png;base64,...`) |
| `fileName` | string | Não | Nome do arquivo (opcional, usado quando o nome não pode ser inferido) |
| `poll` | object | Não | Objeto JSON com a enquete (question, options, selections) |
| `location` | object | Não | Objeto JSON com dados de localização (latitude, longitude, name, address, url) |
| `contact` | object | Não | Objeto JSON com dados de contato (phone, name, vcard) |

**⚠️ IMPORTANTE:** O campo `text` NÃO pode estar vazio quando enviando mídia via `url`. Se não houver legenda (`caption`), use pelo menos um espaço `" "` ou o nome do arquivo.

#### Objeto `location`

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|--------------|-----------|
| `latitude` | float64 | Sim | Latitude em graus (ex: -23.550520) |
| `longitude` | float64 | Sim | Longitude em graus (ex: -46.633308) |
| `name` | string | Não | Nome/descrição da localização |
| `address` | string | Não | Endereço completo da localização |
| `url` | string | Não | URL com link para o mapa |

#### Objeto `contact`

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|--------------|-----------|
| `phone` | string | Sim | Número de telefone do contato |
| `name` | string | Sim | Nome de exibição do contato |
| `vcard` | string | Não | String vCard completa (gerada automaticamente se não fornecida) |

#### Objeto `poll`

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|--------------|-----------|
| `question` | string | Sim | Pergunta/título da enquete |
| `options` | array[string] | Sim | Array de opções da enquete |
| `selections` | integer | Não | Número máximo de opções que um usuário pode selecionar (padrão: 1) |

### Response

#### Sucesso (200 OK)
```json
{
  "success": true,
  "status": "sended with success",
  "message": {
    "id": "3EB0388F188073F7314725",
    "wid": "553591970289:87@s.whatsapp.net",
    "chatId": "5511999999999@s.whatsapp.net",
    "trackId": "Julio"
  },
  "debug": []
}
```

#### Erro (400 Bad Request)
```json
{
  "success": false,
  "status": "text not found, do not send empty messages",
  "message": {
    "chatId": "string",
    "id": "string",
    "trackId": "string",
    "wid": "string"
  },
  "debug": []
}
```

---

## Exemplos de Uso

### 1. Enviar Texto Simples

```json
{
  "chatId": "5511999999999@s.whatsapp.net",
  "text": "Hello, world!"
}
```

### 2. Enviar Arquivo por URL (Imagem, Vídeo, Áudio, Documento)

```json
{
  "chatId": "5511999999999@s.whatsapp.net",
  "text": "Legenda da imagem (ou espaço se não houver legenda)",
  "url": "https://example.com/path/to/file.jpg"
}
```

**⚠️ IMPORTANTE para Mídia:**
- A URL DEVE ser pública e acessível pelo servidor Quepasa
- A URL DEVE ser absoluta (começar com `https://` ou `http://`)
- O campo `text` NÃO pode estar vazio (use pelo menos `" "` se não houver legenda)
- O servidor Quepasa baixa o arquivo automaticamente e envia como anexo
- O tipo de mídia (imagem, vídeo, áudio, documento) é detectado automaticamente pelo MIME type

### 3. Enviar Conteúdo Base64

```json
{
  "chatId": "5511999999999@s.whatsapp.net",
  "content": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=="
}
```

### 4. Enviar Enquete/Poll

```json
{
  "chatId": "5511999999999@s.whatsapp.net",
  "poll": {
    "question": "Which languages do you know?",
    "options": ["JavaScript", "Python", "Go", "Java", "C#", "Ruby"],
    "selections": 3
  }
}
```

### 5. Enviar Localização

```json
{
  "chatId": "5511999999999@s.whatsapp.net",
  "location": {
    "latitude": -23.550520,
    "longitude": -46.633308,
    "name": "Avenida Paulista, São Paulo",
    "address": "Avenida Paulista, 1000, São Paulo, SP"
  }
}
```

### 6. Enviar Contato

```json
{
  "chatId": "5511999999999@s.whatsapp.net",
  "contact": {
    "phone": "5511999999999",
    "name": "John Doe"
  }
}
```

---

## Endpoint Alternativo: POST /senddocument

### Descrição
Endpoint para enviar documentos via WhatsApp, forçando o tipo de documento independentemente do MIME type do arquivo. Útil para enviar imagens, arquivos de áudio ou outro conteúdo como documentos.

Aceita os mesmos parâmetros que `/send`, mas sempre trata anexos como documentos.

### URL
```
POST https://whats.orbichat.com.br/senddocument
```

### Exemplo

```json
{
  "chatId": "5511999999999@s.whatsapp.net",
  "url": "https://example.com/document.pdf",
  "text": "Please check this document"
}
```

---

## Outros Endpoints Úteis

### GET /receive
Recupera mensagens pendentes do WhatsApp.

**Query Parameters:**
- `timestamp` (string, opcional): Filtro de timestamp para mensagens
- `exceptions` (string, opcional): Filtrar por status de exceções ('true' para mensagens com erros, 'false' para sem erros)

### GET /download
Baixa arquivos de mídia (imagens, vídeos, documentos) de mensagens do WhatsApp.

**Query Parameters:**
- `messageid` (string): ID da mensagem
- `cache` (string, opcional): Usar conteúdo em cache

### POST /read
Marca uma ou mais mensagens como lidas por ID.

**Request Body:**
```json
["id1", "id2"]
```
ou
```json
[{"id": "id1"}, {"id": "id2"}]
```

### POST /chat/markread
Marca um chat como lido (remove badge de não lido).

**Request Body:**
```json
{
  "chatid": "5511999999999@s.whatsapp.net"
}
```

---

## Problemas Comuns e Soluções

### Erro: "text not found, do not send empty messages"

**Causa:** O campo `text` está vazio ou ausente quando enviando mídia.

**Solução:** Sempre inclua o campo `text` com pelo menos um espaço `" "` ou uma legenda quando enviando mídia via `url`:

```json
{
  "chatId": "5511999999999@s.whatsapp.net",
  "text": " ",  // ou "Legenda da imagem"
  "url": "https://example.com/image.jpg"
}
```

### Mídia não chega no WhatsApp

**Possíveis causas:**
1. URL não é pública ou não acessível pelo servidor Quepasa
2. URL é relativa em vez de absoluta
3. Certificado SSL inválido ou não confiável
4. Servidor Quepasa não consegue baixar o arquivo (timeout, bloqueio de firewall, etc.)

**Solução:**
1. Verifique se a URL é acessível publicamente (teste com `curl` do servidor Quepasa)
2. Use sempre URLs absolutas (`https://...` ou `http://...`)
3. Verifique os logs do Quepasa para erros de download
4. Teste o download direto da URL a partir do servidor onde o Quepasa está rodando

### Exemplo de Teste de Acessibilidade

No servidor onde roda o Quepasa, execute:

```bash
curl -I https://seu-dominio.com/assets/media/attachments/78/arquivo.png
```

Se retornar `HTTP/2 200`, a URL é acessível. Se retornar erro (404, 403, timeout, etc.), o problema está na acessibilidade da URL.

---

## Notas Importantes

1. **URLs Absolutas:** Sempre use URLs absolutas para mídia. URLs relativas (`/assets/...`) não funcionam.

2. **Campo `text` Obrigatório:** Quando enviando mídia via `url`, o campo `text` não pode estar vazio. Use pelo menos um espaço `" "` se não houver legenda.

3. **Download Automático:** O servidor Quepasa baixa automaticamente o arquivo da URL fornecida. Certifique-se de que:
   - A URL é pública
   - O arquivo existe e está acessível
   - O servidor Quepasa tem acesso à internet para baixar o arquivo

4. **Tipos de Mídia:** O tipo de mídia (imagem, vídeo, áudio, documento) é detectado automaticamente pelo MIME type do arquivo baixado.

5. **Tamanho de Arquivo:** Verifique os limites de tamanho de arquivo do WhatsApp:
   - Imagens: até 16 MB
   - Vídeos: até 64 MB
   - Áudios: até 16 MB
   - Documentos: até 100 MB

6. **Headers Opcionais:** Os headers `X-QUEPASA-CHATID` e `X-QUEPASA-TRACKID` podem ser omitidos se você enviar `chatId` e `trackId` no body da requisição.

---

## Referências

- **Repositório GitHub:** https://github.com/nocodeleaks/quepasa
- **Documentação Swagger:** https://whats.orbichat.com.br/swagger/index.html
- **Licença:** GNU Affero General Public License v3.0

---

## Última Atualização

Documentação baseada na API QuePasa versão 4.0.0, consultada em 2025-12-07.

