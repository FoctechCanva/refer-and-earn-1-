FROM php:8.2-apache

# Install required extensions
RUN docker-php-ext-install pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Set file permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod 664 users.json error.log \
    && chmod +x entrypoint.sh

# Configure Apache
ENV APACHE_DOCUMENT_ROOT /var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Entrypoint configuration
ENTRYPOINT ["./entrypoint.sh"]