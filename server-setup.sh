#!/bin/bash

# Script de Configuração do Servidor PixEat
# IP do Servidor: 188.138.28.110

echo "🚀 Configurando PixEat no servidor 188.138.28.110..."

# Atualizar sistema
echo "📦 Atualizando sistema..."
sudo apt update && sudo apt upgrade -y

# Instalar Docker
echo "🐳 Instalando Docker..."
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER

# Instalar Docker Compose
echo "🔧 Instalando Docker Compose..."
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Instalar Nginx (proxy reverso)
echo "🌐 Instalando Nginx..."
sudo apt install nginx -y

# Criar diretório do projeto
echo "📁 Criando estrutura de diretórios..."
sudo mkdir -p /var/www/pixeat
sudo chown $USER:$USER /var/www/pixeat

# Clonar repositório
echo "📥 Clonando repositório..."
cd /var/www/pixeat
git clone https://github.com/pixeat-site/api.git .

# Configurar ambiente de produção
echo "⚙️ Configurando ambiente..."
cp env.production.template src/.env
echo "📝 IMPORTANTE: Edite o arquivo src/.env com suas configurações:"
echo "   - GEMINI_API_KEY (sua chave do Google Gemini)"
echo "   - DB_PASSWORD (senha forte para o banco)"
echo "   - APP_KEY (será gerado automaticamente)"

# Configurar Nginx
echo "🌐 Configurando Nginx..."
sudo tee /etc/nginx/sites-available/pixeat << EOF
# Configuração principal do site
server {
    listen 80;
    server_name pixeat.site www.pixeat.site;
    root /var/www/pixeat/public;
    index index.html;
    
    # Servir arquivos estáticos
    location / {
        try_files \$uri \$uri/ @api;
    }
    
    # Redirecionar para API quando necessário
    location @api {
        proxy_pass http://localhost:8080;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
    
    # API routes
    location /api/ {
        proxy_pass http://localhost:8080;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}

# Configuração da API
server {
    listen 80;
    server_name api.pixeat.site;
    
    location / {
        proxy_pass http://localhost:8080;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
EOF

# Ativar site
sudo ln -sf /etc/nginx/sites-available/pixeat /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

# Configurar firewall
echo "🔒 Configurando firewall..."
sudo ufw allow 22
sudo ufw allow 80
sudo ufw allow 443
sudo ufw --force enable

echo "✅ Configuração inicial concluída!"
echo "📝 Próximos passos:"
echo "1. Editar /var/www/pixeat/src/.env com suas configurações"
echo "2. Executar: cd /var/www/pixeat && ./deploy.sh"
echo "3. Testar: curl http://188.138.28.110/api/health"
