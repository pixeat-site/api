# PixEat API 🍽️

API REST para o aplicativo PixEat - Contador de calorias com IA através de análise de imagens.

## 🚀 Funcionalidades

- **Autenticação completa** com Laravel Sanctum
- **Análise de imagens** com Google Gemini AI
- **Gestão de refeições** e histórico
- **Cálculo automático de calorias**
- **Perfis de usuário** personalizáveis
- **Estatísticas** diárias, semanais e mensais

## 🛠️ Tecnologias

- **Laravel 11** - Framework PHP
- **PostgreSQL** - Banco de dados
- **Google Gemini AI** - Análise de imagens
- **Laravel Sanctum** - Autenticação API
- **Docker** - Containerização

## 📋 Pré-requisitos

- Docker e Docker Compose
- PHP 8.2+
- Composer
- Chave da API do Google Gemini

## 🔧 Instalação

### Desenvolvimento Local

1. **Clone o repositório:**
```bash
git clone https://github.com/pixeat-site/api.git
cd api
```

2. **Configure o ambiente:**
```bash
cp src/.env.example src/.env
# Edite o .env com suas configurações
```

3. **Inicie os containers:**
```bash
docker compose up -d
```

4. **Instale as dependências:**
```bash
docker compose exec --user=laradock workspace bash
composer install
php artisan key:generate
php artisan migrate
```

### Produção

1. **Use o script de deploy:**
```bash
chmod +x deploy.sh
./deploy.sh
```

## 🔑 Configuração

### Variáveis de Ambiente Principais

```env
# Banco de Dados
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=pixeat
DB_USERNAME=pixeat_user
DB_PASSWORD=sua_senha_aqui

# Google Gemini AI
GEMINI_API_KEY=sua_chave_gemini_aqui

# Laravel
APP_KEY=base64:sua_chave_laravel_aqui
APP_URL=https://sua-api-pixeat.com
```

## 📚 Endpoints da API

### Autenticação
- `POST /api/v1/auth/register` - Registro de usuário
- `POST /api/v1/auth/login` - Login
- `POST /api/v1/auth/logout` - Logout
- `GET /api/v1/user/profile` - Perfil do usuário

### Análise de IA
- `POST /api/v1/ai/analyze` - Analisar imagem de comida
- `GET /api/v1/ai/test-connection` - Testar conexão com Gemini
- `GET /api/v1/ai/info` - Informações do serviço

### Refeições
- `GET /api/v1/meals` - Listar refeições
- `POST /api/v1/meals` - Criar refeição
- `GET /api/v1/meals/today` - Refeições de hoje
- `PUT /api/v1/meals/{id}` - Atualizar refeição
- `DELETE /api/v1/meals/{id}` - Deletar refeição

### Estatísticas
- `GET /api/v1/stats/daily` - Estatísticas diárias
- `GET /api/v1/stats/weekly` - Estatísticas semanais
- `GET /api/v1/stats/monthly` - Estatísticas mensais

## 🧪 Testando a API

### Health Check
```bash
curl http://localhost:8080/api/health
```

### Análise de Imagem
```bash
curl -X POST http://localhost:8080/api/v1/ai/analyze \
  -H "Authorization: Bearer SEU_TOKEN" \
  -F "image=@caminho/para/imagem.jpg"
```

## 🏗️ Estrutura do Projeto

```
api/
├── src/                    # Código Laravel
│   ├── app/
│   │   ├── Http/Controllers/Api/V1/
│   │   ├── Models/
│   │   └── Services/
│   ├── database/migrations/
│   ├── routes/api.php
│   └── .env
├── docker/                 # Configurações Docker
├── docker-compose.yml      # Desenvolvimento
├── docker-compose.prod.yml # Produção
└── deploy.sh              # Script de deploy
```

## 🔒 Segurança

- Autenticação via Bearer Token (Sanctum)
- Validação de dados em todas as rotas
- Rate limiting implementado
- CORS configurado
- Logs de segurança

## 📊 Monitoramento

- Logs em `src/storage/logs/`
- Health check endpoint: `/api/health`
- Métricas de performance disponíveis

## 🤝 Contribuição

1. Fork o projeto
2. Crie uma branch para sua feature
3. Commit suas mudanças
4. Push para a branch
5. Abra um Pull Request

## 📄 Licença

Este projeto está sob a licença MIT.

## 📞 Suporte

Para suporte, entre em contato através do GitHub Issues.

---

**Desenvolvido com ❤️ para o PixEat**