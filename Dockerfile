# Simple Dockerfile for Gemini AI Chatbot
FROM php:8.3-fpm-alpine

WORKDIR /var/www/html

# Install nginx and supervisor
RUN apk add --no-cache nginx supervisor curl

# Create simple nginx config
RUN mkdir -p /etc/nginx/conf.d
COPY <<EOF /etc/nginx/conf.d/default.conf
server {
    listen 8080;
    root /var/www/html;
    index index.php index.html;
    
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
}
EOF

# Create minimal nginx.conf
COPY <<EOF /etc/nginx/nginx.conf
events {
    worker_connections 1024;
}
http {
    include /etc/nginx/mime.types;
    include /etc/nginx/conf.d/*.conf;
}
EOF

# Create supervisor config
COPY <<EOF /etc/supervisor/conf.d/supervisord.conf
[supervisord]
nodaemon=true

[program:php-fpm]
command=php-fpm
autostart=true
autorestart=true

[program:nginx]
command=nginx -g "daemon off;"
autostart=true
autorestart=true
EOF

# Copy app files
COPY . .

# Set permissions
RUN chown -R nginx:nginx /var/www/html

# Expose port
EXPOSE 8080

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"] 