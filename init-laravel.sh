#!/bin/bash

# Script para inicializar projeto Laravel no Docker
echo "🚀 Iniciando setup do Laravel para PixEat API..."

# Verificar se Docker está rodando
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker não está rodando. Por favor, inicie o Docker primeiro."
    exit 1
fi

# Criar pasta src se não existir
if [ ! -d "src" ]; then
    echo "📁 Criando pasta src..."
    mkdir -p src
fi

# Verificar se Laravel já está instalado
if [ ! -f "src/artisan" ]; then
    echo "📦 Instalando Laravel..."
    
    # Instalar Laravel usando Composer via Docker
    docker run --rm \
        -v $(pwd)/src:/app \
        -w /app \
        composer:latest \
        create-project laravel/laravel . --prefer-dist
    
    echo "✅ Laravel instalado com sucesso!"
else
    echo "ℹ️  Laravel já está instalado."
fi

# Copiar arquivo de ambiente
if [ ! -f "src/.env" ]; then
    echo "📝 Copiando arquivo de ambiente..."
    cp env.example src/.env
    echo "✅ Arquivo .env criado!"
else
    echo "ℹ️  Arquivo .env já existe."
fi

# Subir os containers
echo "🐳 Subindo containers Docker..."
docker-compose up -d

# Aguardar containers iniciarem
echo "⏳ Aguardando containers iniciarem..."
sleep 10

# Instalar dependências do Composer
echo "📦 Instalando dependências do Composer..."
docker-compose exec app composer install

# Gerar chave da aplicação
echo "🔑 Gerando chave da aplicação..."
docker-compose exec app php artisan key:generate

# Executar migrations
echo "🗄️  Executando migrations..."
docker-compose exec app php artisan migrate

# Limpar cache
echo "🧹 Limpando cache..."
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear

# Configurar permissões
echo "🔐 Configurando permissões..."
docker-compose exec app chown -R www:www /var/www/html
docker-compose exec app chmod -R 755 /var/www/html/storage
docker-compose exec app chmod -R 755 /var/www/html/bootstrap/cache

echo ""
echo "🎉 Setup concluído com sucesso!"
echo ""
echo "📋 Informações importantes:"
echo "   • API Laravel: http://localhost:8080"
echo "   • pgAdmin: http://localhost:8081"
echo "   • PostgreSQL: localhost:5434"
echo "   • Redis: localhost:6379"
echo ""
echo "🔧 Comandos úteis:"
echo "   • Parar containers: docker-compose down"
echo "   • Ver logs: docker-compose logs -f"
echo "   • Executar comandos Artisan: docker-compose exec app php artisan [comando]"
echo "   • Acessar container: docker-compose exec app bash"
echo ""
echo "📚 Próximos passos:"
echo "   1. Acesse http://localhost:8080 para ver a aplicação"
echo "   2. Configure suas rotas em src/routes/api.php"
echo "   3. Crie seus models e controllers conforme necessário"
echo ""
