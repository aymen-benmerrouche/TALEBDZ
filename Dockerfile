# ============================================================
# Dockerfile for TalebDZ PHP Application
# Production-ready deployment for Render.com
# ============================================================

FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    curl \
    && docker-php-ext-install pdo pdo_pgsql pgsql bcmath opcache zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

# Configure Apache inline (no external file needed)
RUN echo '<VirtualHost *:80>\n\
    ServerAdmin admin@talebdz.com\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        Options -Indexes +FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
        RewriteEngine On\n\
        RewriteCond %{REQUEST_FILENAME} !-f\n\
        RewriteCond %{REQUEST_FILENAME} !-d\n\
        RewriteRule ^ index.php [L]\n\
    </Directory>\n\
    <Directory /var/www/html/api>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    <Directory /var/www/html/db>\n\
        <FilesMatch "\\.(env|sql|backup)$">\n\
            Require all denied\n\
        </FilesMatch>\n\
    </Directory>\n\
    <IfModule mod_headers.c>\n\
        Header always set X-Content-Type-Options "nosniff"\n\
        Header always set X-Frame-Options "SAMEORIGIN"\n\
        Header always set X-XSS-Protection "1; mode=block"\n\
    </IfModule>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Configure PHP for production
RUN { \
    echo 'display_errors = Off'; \
    echo 'log_errors = On'; \
    echo 'error_log = /var/log/apache2/php_errors.log'; \
    echo 'max_execution_time = 60'; \
    echo 'memory_limit = 256M'; \
    echo 'post_max_size = 20M'; \
    echo 'upload_max_filesize = 20M'; \
    echo 'opcache.enable = 1'; \
    echo 'opcache.memory_consumption = 128'; \
} > /usr/local/etc/php/conf.d/production.ini

# Copy application files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
