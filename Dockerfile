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
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl zip calendar opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Install dependencies
RUN composer install --optimize-autoloader --no-dev

# Set permissions
RUN chmod -R 755 storage bootstrap/cache

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
php artisan storage:link || true\n\
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