#!/bin/bash

# Script para configurar SSL/HTTPS - PixEat
echo "🔒 Configurando SSL para PixEat..."

# Instalar Certbot
echo "📦 Instalando Certbot..."
sudo apt update
sudo apt install certbot python3-certbot-nginx -y

# Parar containers temporariamente para liberar porta 80
echo "⏸️  Parando containers temporariamente..."
docker compose -f docker-compose.prod.yml down

# Configurar Nginx do sistema para proxy
echo "🌐 Configurando Nginx do sistema..."
sudo tee /etc/nginx/sites-available/pixeat-ssl << 'EOF'
server {
    listen 80;
    server_name pixeat.site www.pixeat.site;
    
    # Redirecionar HTTP para HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name pixeat.site www.pixeat.site;
    
    # Certificados SSL (serão criados pelo Certbot)
    ssl_certificate /etc/letsencrypt/live/pixeat.site/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/pixeat.site/privkey.pem;
    
    # Configurações SSL
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    
    # Proxy para container
    location / {
        proxy_pass http://localhost:8081;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
EOF

# Ativar site
sudo ln -sf /etc/nginx/sites-available/pixeat-ssl /etc/nginx/sites-enabled/
sudo nginx -t

# Obter certificados SSL
echo "🔐 Obtendo certificados SSL..."
sudo certbot --nginx -d pixeat.site -d www.pixeat.site --non-interactive --agree-tos --email admin@pixeat.site

# Subir containers novamente
echo "🚀 Subindo containers..."
docker compose -f docker-compose.prod.yml up -d

# Recarregar Nginx
sudo systemctl reload nginx

# Configurar renovação automática
echo "🔄 Configurando renovação automática..."
(crontab -l 2>/dev/null; echo "0 12 * * * /usr/bin/certbot renew --quiet") | crontab -

echo "✅ SSL configurado com sucesso!"
echo "🌐 Acesse: https://pixeat.site"
echo "🔍 Teste: curl -I https://pixeat.site"
