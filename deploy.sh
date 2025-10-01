#!/bin/bash

# Script de Deploy para Produção - PixEat API
echo "🚀 Iniciando deploy da PixEat API..."

# Verificar se está no diretório correto
if [ ! -f "docker-compose.prod.yml" ]; then
    echo "❌ Erro: Execute este script no diretório da API"
    exit 1
fi

# Parar containers existentes
echo "⏹️  Parando containers existentes..."
docker compose -f docker-compose.prod.yml down

# Fazer backup do banco (se existir)
echo "💾 Fazendo backup do banco..."
docker compose -f docker-compose.prod.yml exec postgres pg_dump -U pixeat_user pixeat_prod > backup_$(date +%Y%m%d_%H%M%S).sql 2>/dev/null || echo "ℹ️  Nenhum banco para backup"

# Construir imagens
echo "🔨 Construindo imagens..."
docker compose -f docker-compose.prod.yml build --no-cache

# Subir containers
echo "🆙 Subindo containers..."
docker compose -f docker-compose.prod.yml up -d

# Aguardar containers iniciarem
echo "⏳ Aguardando containers iniciarem..."
sleep 30

# Gerar chave da aplicação
echo "🔑 Gerando chave da aplicação..."
docker compose -f docker-compose.prod.yml exec app php artisan key:generate --force

# Executar migrations
echo "🗃️  Executando migrations..."
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force

# Limpar cache
echo "🧹 Limpando cache..."
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan route:cache
docker compose -f docker-compose.prod.yml exec app php artisan view:cache

# Otimizar autoload
echo "⚡ Otimizando autoload..."
docker compose -f docker-compose.prod.yml exec app composer install --optimize-autoloader --no-dev

# Verificar status
echo "✅ Verificando status dos containers..."
docker compose -f docker-compose.prod.yml ps

echo "🎉 Deploy concluído!"
echo "📍 API disponível em: http://localhost"
echo "🔍 Para verificar logs: docker compose -f docker-compose.prod.yml logs -f"
