# 🎵 Instalação do FFmpeg para Conversão de Áudio

O FFmpeg é necessário para converter arquivos de áudio WebM para OGG/Opus, que é o formato preferido pelo WhatsApp para mensagens de áudio (PTT).

## 📋 Pré-requisitos

- Acesso ao servidor Docker/VPS
- Permissões de root ou sudo

---

## 🐳 Opção 1: Instalar no Container Docker Existente (Rápido)

Se você já tem um container rodando e não quer reconstruir a imagem:

### Passo 1: Entrar no container

```bash
docker exec -it nome-do-container bash
```

### Passo 2: Instalar FFmpeg

```bash
apt-get update
apt-get install -y ffmpeg
```

### Passo 3: Verificar instalação

```bash
ffmpeg -version
```

Deve mostrar a versão do FFmpeg instalada.

### Passo 4: Sair do container

```bash
exit
```

**⚠️ IMPORTANTE:** Esta instalação será perdida se o container for recriado. Para uma solução permanente, use a Opção 2.

---

## 🏗️ Opção 2: Atualizar Dockerfile (Permanente)

Para que o FFmpeg seja instalado sempre que a imagem for construída:

### Passo 1: Editar Dockerfile

Adicione `ffmpeg` na lista de pacotes a serem instalados:

```dockerfile
# Imagem base com Apache
FROM php:8.2-apache

# Instala dependências de sistema (incluindo FFmpeg)
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    curl \
    ffmpeg \
 && docker-php-ext-install pdo pdo_mysql \
 && a2enmod rewrite
```

### Passo 2: Reconstruir a imagem

```bash
docker build -t sua-imagem:tag .
```

Ou se usar docker-compose:

```bash
docker-compose build
docker-compose up -d
```

---

## 🖥️ Opção 3: Instalar no VPS (Sem Docker)

Se você está usando um VPS diretamente (sem Docker):

### Ubuntu/Debian

```bash
sudo apt-get update
sudo apt-get install -y ffmpeg
```

### CentOS/RHEL

```bash
sudo yum install -y epel-release
sudo yum install -y ffmpeg
```

### Verificar instalação

```bash
ffmpeg -version
```

---

## ✅ Verificação

Após instalar, teste se está funcionando:

### 1. Verificar se FFmpeg está no PATH

```bash
which ffmpeg
# Deve retornar: /usr/bin/ffmpeg (ou caminho similar)
```

### 2. Verificar versão

```bash
ffmpeg -version
# Deve mostrar informações da versão
```

### 3. Testar conversão (opcional)

```bash
# Criar um arquivo de teste WebM (se tiver)
ffmpeg -i arquivo.webm -c:a libopus -b:a 96k -vn arquivo.ogg
```

---

## 🔧 Configuração PHP

Certifique-se de que as funções `shell_exec` e `exec` estão habilitadas no PHP:

### Verificar configuração

```bash
php -i | grep disable_functions
```

Se `shell_exec` ou `exec` estiverem na lista, você precisa removê-las.

### Editar php.ini

```bash
# Encontrar php.ini
php --ini

# Editar php.ini e remover shell_exec e exec de disable_functions
# Ou comentar a linha disable_functions completamente
```

### Reiniciar Apache/PHP-FPM

```bash
# Apache
service apache2 restart
# ou
systemctl restart apache2

# PHP-FPM
service php-fpm restart
# ou
systemctl restart php8.2-fpm
```

---

## 🧪 Teste no Sistema

Após instalar, teste enviando um áudio pelo chat:

1. Grave um áudio no chat web
2. Envie para um contato
3. Verifique os logs em `logs/quepasa.log`:

```bash
tail -f logs/quepasa.log | grep ffmpeg
```

Você deve ver mensagens como:
```
✅ ffmpeg encontrado: /usr/bin/ffmpeg
✅ CONVERSÃO CONCLUÍDA COM SUCESSO!
```

---

## 🐛 Troubleshooting

### Erro: "ffmpeg não encontrado no PATH"

**Solução:**
1. Verifique se FFmpeg está instalado: `which ffmpeg`
2. Se não estiver, instale usando uma das opções acima
3. Se estiver instalado mas não encontrado, adicione ao PATH:

```bash
export PATH=$PATH:/usr/bin:/usr/local/bin
```

### Erro: "shell_exec/exec desabilitadas"

**Solução:**
1. Edite `php.ini`
2. Remova `shell_exec` e `exec` de `disable_functions`
3. Reinicie Apache/PHP-FPM

### Erro: "Permission denied"

**Solução:**
1. Verifique permissões do diretório de anexos:
```bash
chmod -R 775 public/assets/media/attachments
chown -R www-data:www-data public/assets/media/attachments
```

### Conversão falha mas FFmpeg está instalado

**Solução:**
1. Verifique se o arquivo de origem existe e tem permissões de leitura
2. Verifique se o diretório de destino tem permissões de escrita
3. Execute manualmente o comando FFmpeg para ver o erro:

```bash
ffmpeg -y -i arquivo.webm -c:a libopus -b:a 96k -vn arquivo.ogg
```

---

## 📝 Comandos Úteis

### Verificar se FFmpeg está instalado

```bash
docker exec nome-do-container which ffmpeg
```

### Ver versão do FFmpeg

```bash
docker exec nome-do-container ffmpeg -version
```

### Instalar FFmpeg em container existente (sem rebuild)

```bash
docker exec -it nome-do-container bash -c "apt-get update && apt-get install -y ffmpeg"
```

### Criar script de instalação automática

Crie um arquivo `install-ffmpeg.sh`:

```bash
#!/bin/bash
apt-get update
apt-get install -y ffmpeg
ffmpeg -version
```

Execute:
```bash
chmod +x install-ffmpeg.sh
docker cp install-ffmpeg.sh nome-do-container:/tmp/
docker exec nome-do-container /tmp/install-ffmpeg.sh
```

---

## ✅ Checklist de Instalação

- [ ] FFmpeg instalado (`ffmpeg -version` funciona)
- [ ] FFmpeg no PATH (`which ffmpeg` retorna caminho)
- [ ] `shell_exec` e `exec` habilitadas no PHP
- [ ] Permissões corretas nos diretórios de anexos
- [ ] Teste de conversão funcionando
- [ ] Logs mostrando conversão bem-sucedida

---

## 📚 Referências

- [FFmpeg Official Website](https://ffmpeg.org/)
- [FFmpeg Documentation](https://ffmpeg.org/documentation.html)
- [WhatsApp Audio Format](https://developers.facebook.com/docs/whatsapp/cloud-api/reference/media#supported-media-types)

