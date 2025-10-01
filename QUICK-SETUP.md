# 🚀 Setup Rápido - PixEat Produção

## 📋 Checklist de Configuração

### 1. 🌐 DNS (✅ Já configurado)
- `pixeat.site` → `188.138.28.110`
- `www.pixeat.site` → `188.138.28.110`
- `api.pixeat.site` → `188.138.28.110`

### 2. 🖥️ No Servidor (188.138.28.110)

```bash
# Conectar ao servidor
ssh root@188.138.28.110

# Executar setup automático
curl -fsSL https://raw.githubusercontent.com/pixeat-site/api/main/server-setup.sh | bash

# Ir para o diretório
cd /var/www/pixeat

# Editar configurações
nano src/.env
```

### 3. ⚙️ Configurações Obrigatórias no .env

**Substitua estas linhas:**
```env
# Sua chave do Google Gemini
GEMINI_API_KEY=SUA_CHAVE_GEMINI_AQUI

# Senha forte para o banco
DB_PASSWORD=UmaSenhaForte123!@#

# Email (opcional, para recuperação de senha)
MAIL_USERNAME=seu_email@gmail.com
MAIL_PASSWORD=sua_senha_de_app
```

### 4. 🚀 Deploy

```bash
# Executar deploy
./deploy.sh

# Aguardar alguns minutos...
```

### 5. 🧪 Testar

```bash
# Testar API
curl http://pixeat.site/api/health

# Deve retornar:
# {"status":"ok","message":"PixEat API is running",...}
```

### 6. 🔒 SSL (Recomendado)

```bash
# Instalar certificado SSL
sudo certbot --nginx -d pixeat.site -d www.pixeat.site -d api.pixeat.site
```

## 🎯 URLs Finais

- **Site**: https://pixeat.site
- **API**: https://api.pixeat.site
- **Health**: https://pixeat.site/api/health
- **Download APK**: https://pixeat.site/downloads/pixeat-v1.0.0.apk

## 🆘 Problemas Comuns

### API não responde:
```bash
docker ps  # Ver containers
docker compose -f docker-compose.prod.yml logs app  # Ver logs
```

### Erro de permissão:
```bash
sudo chown -R www-data:www-data /var/www/pixeat/src/storage
sudo chmod -R 775 /var/www/pixeat/src/storage
```

### Nginx erro:
```bash
sudo nginx -t  # Testar configuração
sudo systemctl reload nginx  # Recarregar
```

---

**🎉 Pronto! Seu PixEat estará no ar!**
