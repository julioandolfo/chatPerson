# 📦 Instalação do WebSocket

## Requisitos

- PHP 8.1+
- Composer
- Extensão PHP `ext-sockets` habilitada

## Instalação

### 1. Instalar Ratchet via Composer

```bash
composer require cboden/ratchet
```

### 2. Verificar Extensão Sockets

```bash
php -m | grep sockets
```

Se não estiver instalada, instale:

**Windows (XAMPP/Laragon):**
- A extensão geralmente já vem habilitada

**Linux:**
```bash
sudo apt-get install php-sockets
# ou
sudo yum install php-sockets
```

**macOS:**
```bash
brew install php-sockets
```

### 3. Iniciar Servidor WebSocket

```bash
php public/websocket-server.php
```

O servidor será iniciado na porta **8080**.

### 4. Manter Servidor Rodando (Produção)

#### Usando Supervisor (Linux)

Crie arquivo `/etc/supervisor/conf.d/websocket.conf`:

```ini
[program:websocket]
command=php /caminho/para/projeto/public/websocket-server.php
directory=/caminho/para/projeto
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/websocket.log
```

Depois execute:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start websocket
```

#### Usando PM2 (Node.js)

```bash
pm2 start public/websocket-server.php --name websocket --interpreter php
pm2 save
pm2 startup
```

#### Usando systemd (Linux)

Crie arquivo `/etc/systemd/system/websocket.service`:

```ini
[Unit]
Description=WebSocket Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/caminho/para/projeto
ExecStart=/usr/bin/php /caminho/para/projeto/public/websocket-server.php
Restart=always

[Install]
WantedBy=multi-user.target
```

Depois execute:
```bash
sudo systemctl daemon-reload
sudo systemctl enable websocket
sudo systemctl start websocket
```

## Configuração de Proxy Reverso (Nginx)

Adicione ao seu arquivo de configuração do Nginx:

```nginx
location /ws {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 86400;
}
```

## Testando a Conexão

Abra o console do navegador e verifique:

```javascript
console.log(window.wsClient.connected); // deve retornar true
```

## Troubleshooting

### Erro: "Class 'Ratchet\...' not found"
- Execute `composer install` ou `composer require cboden/ratchet`

### Erro: "Address already in use"
- A porta 8080 já está em uso
- Altere a porta em `public/websocket-server.php` e `public/assets/js/websocket-client.js`

### WebSocket não conecta
- Verifique se o servidor está rodando
- Verifique se o firewall permite conexões na porta 8080
- Verifique os logs do servidor WebSocket

### Mensagens não aparecem em tempo real
- Verifique se o cliente está conectado (`wsClient.connected`)
- Verifique se está inscrito na conversa (`wsClient.subscribe()`)
- Verifique o console do navegador para erros

---

**Última atualização**: 2025-01-27

