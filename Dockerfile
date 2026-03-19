FROM php:8.2-apache

# Enable Apache rewrite module
RUN a2enmod rewrite

# Disable conflicting MPM modules and enable prefork only
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true \
    && a2enmod mpm_prefork

# Install PHP extensions
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libssl-dev \
    && docker-php-ext-install pdo pdo_mysql curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy project files
COPY . /var/www/html/

# Set permissions and create logs directory
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/backend/logs \
    && chmod -R 777 /var/www/html/backend/logs

# Allow .htaccess overrides
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/sites-available/000-default.conf

EXPOSE 80