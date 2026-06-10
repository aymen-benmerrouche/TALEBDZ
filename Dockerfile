# ============================================================
# Dockerfile for TalebDZ PHP Application
# Production-ready deployment for Render.com
# ============================================================

# Use official PHP 8.2 with Apache as base image
FROM php:8.2-apache

# Set labels for better container management
LABEL maintainer="TalebDZ Team"
LABEL description="TalebDZ Student Assistant - PHP Application"
LABEL version="1.0"

# Set working directory
WORKDIR /var/www/html

# ============================================================
# Install System Dependencies and PHP Extensions
# ============================================================

# Install system dependencies required for PostgreSQL and other extensions
RUN apt-get update && apt-get install -y \
    # PostgreSQL development libraries (required for pdo_pgsql)
    libpq-dev \
    # Image processing libraries (for potential future use)
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    # Zip utilities
    libzip-dev \
    unzip \
    # Git (useful for debugging and version info)
    git \
    # Cleanup to reduce image size
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ============================================================
# Configure and Install PHP Extensions
# ============================================================

# Install and enable PDO PostgreSQL extension (REQUIRED for Supabase connection)
RUN docker-php-ext-install pdo pdo_pgsql pgsql

# Install other useful PHP extensions
RUN docker-php-ext-install \
    # JSON support (usually enabled by default, but explicit is better)
    # BCMath for precise decimal calculations (useful for pricing)
    bcmath \
    # Opcache for better performance
    opcache

# Configure GD library for image processing (if needed in future)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

# Install Zip extension
RUN docker-php-ext-install zip

# ============================================================
# Configure Apache
# ============================================================

# Enable Apache modules required for the application
# mod_rewrite: URL rewriting for clean URLs
# mod_headers: HTTP header manipulation
# mod_expires: Cache control headers
RUN a2enmod rewrite headers expires

# Copy custom Apache configuration
# This configures the DocumentRoot, security, and proper .htaccess handling
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# ============================================================
# Configure PHP for Production
# ============================================================

# Copy custom PHP configuration or modify existing one
RUN { \
    echo 'display_errors = Off'; \
    echo 'display_startup_errors = Off'; \
    echo 'log_errors = On'; \
    echo 'error_log = /var/log/apache2/php_errors.log'; \
    echo 'max_execution_time = 60'; \
    echo 'max_input_time = 60'; \
    echo 'memory_limit = 256M'; \
    echo 'post_max_size = 20M'; \
    echo 'upload_max_filesize = 20M'; \
    echo 'date.timezone = UTC'; \
    # Opcache configuration for better performance
    echo 'opcache.enable = 1'; \
    echo 'opcache.memory_consumption = 128'; \
    echo 'opcache.interned_strings_buffer = 8'; \
    echo 'opcache.max_accelerated_files = 10000'; \
    echo 'opcache.revalidate_freq = 2'; \
    echo 'opcache.fast_shutdown = 1'; \
} > /usr/local/etc/php/conf.d/production.ini

# ============================================================
# Copy Application Files
# ============================================================

# Copy all application files to the container
# The .dockerignore file will exclude unnecessary files
COPY . /var/www/html/

# ============================================================
# Set Permissions
# ============================================================

# Set proper ownership and permissions
# www-data is the default Apache user in the php:apache image
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Make the photos directory writable (if file uploads are needed)
RUN chmod -R 775 /var/www/html/photos || true

# ============================================================
# Environment Variables
# ============================================================

# Set environment variables with default values
# These can be overridden by Render environment variables
ENV APACHE_DOCUMENT_ROOT=/var/www/html
ENV APACHE_LOG_DIR=/var/log/apache2

# Database connection will be configured via Render environment variables:
# - SUPABASE_URL
# - SUPABASE_ANON_KEY
# - SUPABASE_SERVICE_ROLE_KEY
# - DATABASE_URL
# - SECRET_KEY
# - OPENROUTER_API_KEY

# ============================================================
# Health Check
# ============================================================

# Add a health check to ensure the container is running properly
# Render will use this to determine if the container is healthy
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Install curl for health check
RUN apt-get update && apt-get install -y curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ============================================================
# Expose Port
# ============================================================

# Expose port 80 for HTTP traffic
# Render will automatically map this to the public URL
EXPOSE 80

# ============================================================
# Start Apache
# ============================================================

# Start Apache in foreground mode
# This keeps the container running and allows proper signal handling
CMD ["apache2-foreground"]
