# Production-Ready Dockerfile for Gemini AI Chatbot
# Optimized for free hosting platforms (Railway, Render, Fly.io) - 2025

# Use official PHP 8.3 FPM Alpine image for minimal size and security
FROM php:8.3-fpm-alpine

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and PHP extensions needed for production
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    && docker-php-ext-install \
    opcache \
    && rm -rf /var/cache/apk/*

# Copy nginx configuration
COPY <<EOF /etc/nginx/nginx.conf
user nginx;
worker_processes auto;
pid /run/nginx.pid;

events {
    worker_connections 1024;
    use epoll;
    multi_accept on;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    
    # Logging
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log warn;
    
    # Basic optimizations
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    client_max_body_size 1M;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;
    
    server {
        listen 8080;
        server_name _;
        root /var/www/html;
        index index.php index.html;
        
        # Security: disable server tokens
        server_tokens off;
        
        # Handle PHP files
        location ~ \.php$ {
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_read_timeout 30;
        }
        
        # Serve static files
        location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
            expires 1y;
            add_header Cache-Control "public, immutable";
        }
        
        # Security: deny access to sensitive files
        location ~ /\.(env|git) {
            deny all;
            return 404;
        }
        
        # Handle all other requests
        location / {
            try_files \$uri \$uri/ /index.php?\$query_string;
        }
    }
}
EOF

# Copy supervisor configuration
COPY <<EOF /etc/supervisor/conf.d/supervisord.conf
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

[program:php-fpm]
command=php-fpm
autostart=true
autorestart=true
stderr_logfile=/var/log/supervisor/php-fpm.err.log
stdout_logfile=/var/log/supervisor/php-fpm.out.log

[program:nginx]
command=nginx -g "daemon off;"
autostart=true
autorestart=true
stderr_logfile=/var/log/supervisor/nginx.err.log
stdout_logfile=/var/log/supervisor/nginx.out.log
EOF

# Configure PHP for production
COPY <<EOF /usr/local/etc/php/php.ini
; Production PHP configuration
; Optimized for free hosting platforms

; Error handling
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

; Security
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off

; Performance
memory_limit = 128M
max_execution_time = 30
max_input_time = 30
post_max_size = 1M
upload_max_filesize = 1M

; OPcache settings for performance
opcache.enable = 1
opcache.memory_consumption = 64
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 60
opcache.save_comments = 1
opcache.enable_cli = 0

; Session security
session.cookie_httponly = 1
session.use_strict_mode = 1
session.cookie_samesite = "Strict"
EOF

# Copy application files
COPY . .

# Set proper permissions
RUN chown -R nginx:nginx /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/log/supervisor /var/log/nginx /var/run \
    && touch /var/log/php_errors.log \
    && chown nginx:nginx /var/log/php_errors.log

# Expose port (Railway uses 8080, Render auto-detects)
EXPOSE 8080

# Health check for container orchestration
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8080/ || exit 1

# Start supervisor to manage nginx and php-fpm
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"] 