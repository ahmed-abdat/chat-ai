# Simple Dockerfile for Gemini AI Chatbot
FROM php:8.3-fpm-alpine

WORKDIR /var/www/html

# Install required packages and PHP extensions
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    && docker-php-ext-install curl \
    && adduser -D -s /bin/sh www-data

# Create directories
RUN mkdir -p /var/log/nginx /var/log/supervisor /run/nginx

# Create nginx config
COPY <<EOF /etc/nginx/http.d/default.conf
server {
    listen 8080;
    server_name _;
    root /var/www/html;
    index index.php index.html;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    
    # Handle PHP files
    location ~ \.php$ {
        try_files \$uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Handle all other requests
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
}
EOF

# Create main nginx.conf
COPY <<EOF /etc/nginx/nginx.conf
user www-data;
worker_processes auto;
pid /run/nginx.pid;

events {
    worker_connections 1024;
    use epoll;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    
    # Logging
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;
    
    # Performance
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    
    # Include server configs
    include /etc/nginx/http.d/*.conf;
}
EOF

# Create supervisor config
COPY <<EOF /etc/supervisor/conf.d/supervisord.conf
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

[program:php-fpm]
command=php-fpm --nodaemonize
autostart=true
autorestart=true
stdout_logfile=/var/log/supervisor/php-fpm.log
stderr_logfile=/var/log/supervisor/php-fpm.log

[program:nginx]
command=nginx -g "daemon off;"
autostart=true
autorestart=true
stdout_logfile=/var/log/supervisor/nginx.log
stderr_logfile=/var/log/supervisor/nginx.log
EOF

# Copy application files
COPY . .

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port for Railway
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD curl -f http://localhost:8080/ || exit 1

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"] 