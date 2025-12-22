# Scripts de Utilidade

## Sincronização de Views para Docker

Se você encontrar erros como "View não encontrada" ao executar a aplicação em um container Docker, use os scripts abaixo para sincronizar os arquivos.

### Windows (PowerShell)

```powershell
.\scripts\sync-views-to-docker.ps1 [nome_do_container]
```

Exemplo:
```powershell
.\scripts\sync-views-to-docker.ps1 chat-app
```

### Linux/Mac (Bash)

```bash
chmod +x scripts/sync-views-to-docker.sh
./scripts/sync-views-to-docker.sh [nome_do_container]
```

Exemplo:
```bash
./scripts/sync-views-to-docker.sh chat-app
```

### Copiar Manualmente

Se preferir copiar manualmente:

```bash
# Linux/Mac
docker cp views/logs/index.php container_name:/var/www/html/views/logs/index.php

# Windows PowerShell
docker cp views\logs\index.php container_name:/var/www/html/views/logs/index.php
```

### Reconstruir Container

Se o problema persistir, você pode precisar reconstruir o container:

```bash
docker-compose build
docker-compose up -d
```

Ou, se estiver usando apenas Docker:

```bash
docker build -t chat-app .
docker run -d -p 80:80 --name chat-app chat-app
```

