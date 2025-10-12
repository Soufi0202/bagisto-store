FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libicu-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libxpm-dev \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions with proper image support
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    --with-xpm \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl zip calendar opcache

RUN echo "memory_limit = 256M" > /usr/local/etc/php/conf.d/custom.ini \
    && echo "max_execution_time = 120" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "max_input_time = 120" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "upload_max_filesize = 50M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "max_file_uploads = 20" >> /usr/local/etc/php/conf.d/custom.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Install dependencies
RUN composer install --optimize-autoloader --no-dev

# Create necessary directories and set proper permissions
RUN mkdir -p storage/app/public \
    && mkdir -p storage/framework/cache/data \
    && mkdir -p storage/framework/sessions \
    && mkdir -p storage/framework/views \
    && mkdir -p storage/logs \
    && mkdir -p bootstrap/cache \
    && mkdir -p public/storage \
    && mkdir -p public/cache/small \
    && mkdir -p public/cache/medium \
    && mkdir -p public/cache/large \
    && mkdir -p public/cache/original \
    && chmod -R 775 storage bootstrap/cache \
    && chmod -R 777 public/cache \
    && chown -R www-data:www-data storage bootstrap/cache public/cache

# Configure trusted proxies - trust all proxies for Railway
# Create entrypoint script
RUN echo '#!/bin/bash\n\
# Run migrations if needed\n\
php artisan migrate --seed --force || true\n\
\n\
# Mark as installed\n\
touch storage/installed\n\
\n\
# Complete installation\n\
php artisan bagisto:install --skip-admin-creation --skip-env-check || true\n\
php artisan db:seed --class=Webkul\\\\User\\\\Database\\\\Seeders\\\\AdminSeeder --force || true\n\
\n\
# Force create storage link\n\
rm -rf public/storage\n\
php artisan storage:link --force\n\
\n\
# Ensure cache directories exist with proper permissions\n\
mkdir -p public/cache/{small,medium,large,original}\n\
chmod -R 777 public/cache\n\
chown -R www-data:www-data public/cache\n\
\n\
# Fix HTTPS URLs in database\n\
php fix-https.php\n\
\n\
# Clear all caches\n\
php artisan cache:clear\n\
php artisan config:clear\n\
php artisan route:clear\n\
php artisan view:clear\n\
php artisan config:cache\n\
php artisan route:cache\n\
php artisan view:cache\n\
\n\
# Start server\n\
php artisan serve --host=0.0.0.0 --port=${PORT:-8000}' > /entrypoint.sh && chmod +x /entrypoint.sh

EXPOSE ${PORT:-8000}

CMD ["/entrypoint.sh"]