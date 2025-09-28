FROM php:8.2-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite

# Copy application files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html/config && \
    chmod -R 775 /var/www/html/config

# Expose port
EXPOSE 80

