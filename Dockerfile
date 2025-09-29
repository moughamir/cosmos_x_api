# Use a specific version of PHP
FROM php:8.3-apache AS base

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    zip \
    unzip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install pdo_sqlite

# Enable Apache modules and configure compression
RUN a2enmod deflate headers && \
    echo "<IfModule mod_deflate.c>\n  AddOutputFilterByType DEFLATE application/json application/javascript text/css text/html text/xml\n</IfModule>" > /etc/apache2/conf-available/deflate.conf && \
    a2enconf deflate

# Copy composer files
COPY composer.json composer.lock ./

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Create a separate build stage for composer dependencies
FROM base AS build
RUN --mount=type=cache,target=/root/.composer/cache composer install --no-dev --optimize-autoloader

# Create the final production stage
FROM base AS production
COPY --from=build /var/www/html/vendor /var/www/html/vendor
COPY . .

# Set permissions for the application
RUN chown -R www-data:www-data /var/www/html/config && \
    chmod -R 775 /var/www/html/config

# Expose port
EXPOSE 80