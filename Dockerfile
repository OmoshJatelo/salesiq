FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

# Install Apache, PHP and required extensions
RUN apt-get update && apt-get install -y \
    apache2 \
    php8.1 \
    php8.1-mysql \
    php8.1-curl \
    php8.1-mbstring \
    libapache2-mod-php8.1 \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite php8.1

# Copy project files
COPY . /var/www/html/

# Remove default Apache page
RUN rm -f /var/www/html/index.html

# Configure Apache
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/sites-available/000-default.conf

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/backend/logs \
    && chmod -R 777 /var/www/html/backend/logs

EXPOSE 80

CMD ["apache2ctl", "-D", "FOREGROUND"]