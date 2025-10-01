#!/bin/bash

# Script para criar estrutura da API PixEat
echo "🚀 Criando estrutura da API PixEat..."

# Verificar se Laravel está instalado
if [ ! -f "src/artisan" ]; then
    echo "❌ Laravel não encontrado. Execute ./init-laravel.sh primeiro."
    exit 1
fi

echo "📦 Instalando Laravel Sanctum..."
docker-compose exec app composer require laravel/sanctum

echo "🗄️  Criando Models e Migrations..."

# Model User (já existe, vamos apenas criar migration para campos extras)
docker-compose exec app php artisan make:migration add_fields_to_users_table --table=users

# Model Meal
docker-compose exec app php artisan make:model Meal -m

# Model Subscription
docker-compose exec app php artisan make:model Subscription -m

# Model UserSettings
docker-compose exec app php artisan make:model UserSettings -m

# Model MealAnalysis (para IA)
docker-compose exec app php artisan make:model MealAnalysis -m

# Model DailyStats
docker-compose exec app php artisan make:model DailyStats -m

echo "🎮 Criando Controllers..."

# Auth Controllers
docker-compose exec app php artisan make:controller Api/V1/Auth/AuthController
docker-compose exec app php artisan make:controller Api/V1/Auth/RegisterController
docker-compose exec app php artisan make:controller Api/V1/Auth/LoginController

# User Controllers
docker-compose exec app php artisan make:controller Api/V1/User/ProfileController
docker-compose exec app php artisan make:controller Api/V1/User/SettingsController

# Meal Controllers
docker-compose exec app php artisan make:controller Api/V1/MealController --api

# AI Controller
docker-compose exec app php artisan make:controller Api/V1/AIController

# Upload Controller
docker-compose exec app php artisan make:controller Api/V1/UploadController

# Stats Controller
docker-compose exec app php artisan make:controller Api/V1/StatsController

# Subscription Controller
docker-compose exec app php artisan make:controller Api/V1/SubscriptionController

echo "📝 Criando Requests para validação..."

# Auth Requests
docker-compose exec app php artisan make:request Auth/LoginRequest
docker-compose exec app php artisan make:request Auth/RegisterRequest

# User Requests
docker-compose exec app php artisan make:request User/UpdateProfileRequest
docker-compose exec app php artisan make:request User/UpdateSettingsRequest

# Meal Requests
docker-compose exec app php artisan make:request Meal/StoreMealRequest
docker-compose exec app php artisan make:request Meal/UpdateMealRequest

# AI Request
docker-compose exec app php artisan make:request AI/AnalyzeRequest

echo "🔧 Criando Resources para API..."

# User Resources
docker-compose exec app php artisan make:resource User/UserResource
docker-compose exec app php artisan make:resource User/UserSettingsResource

# Meal Resources
docker-compose exec app php artisan make:resource Meal/MealResource
docker-compose exec app php artisan make:resource Meal/MealCollection

# Stats Resources
docker-compose exec app php artisan make:resource Stats/DailyStatsResource
docker-compose exec app php artisan make:resource Stats/WeeklyStatsResource
docker-compose exec app php artisan make:resource Stats/MonthlyStatsResource

# Subscription Resource
docker-compose exec app php artisan make:resource Subscription/SubscriptionResource

echo "🛡️  Configurando Sanctum..."
docker-compose exec app php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

echo "🔄 Executando migrations..."
docker-compose exec app php artisan migrate

echo "✅ Estrutura da API criada com sucesso!"
echo ""
echo "📋 Próximos passos:"
echo "   1. Editar as migrations em src/database/migrations/"
echo "   2. Configurar os models em src/app/Models/"
echo "   3. Implementar os controllers em src/app/Http/Controllers/Api/V1/"
echo "   4. Configurar as rotas em src/routes/api.php"
echo "   5. Executar: docker-compose exec app php artisan migrate"
echo ""
