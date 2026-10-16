FROM php:5.6-apache

# Fix Debian Stretch EOL repositories
RUN sed -i 's|deb.debian.org|archive.debian.org|g' /etc/apt/sources.list && \
    sed -i 's|security.debian.org|archive.debian.org|g' /etc/apt/sources.list && \
    sed -i '/stretch-updates/d' /etc/apt/sources.list && \
    echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99no-check-valid

# Install system dependencies
RUN apt-get update && apt-get install -y --allow-unauthenticated \
    libmcrypt-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
# PHP 5.6 GD needs explicit JPEG/FreeType paths for receipt images.
RUN docker-php-ext-configure gd --with-jpeg-dir=/usr --with-freetype-dir=/usr

RUN docker-php-ext-install \
    mcrypt \
    mbstring \
    mysql \
    mysqli \
    pdo \
    pdo_mysql \
    gd \
    curl \
    xml \
    zip \
    intl

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set the document root to /var/www/html/public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Install Composer 1.x
RUN curl -sS https://getcomposer.org/installer | php -- --1 \
    && mv composer.phar /usr/local/bin/composer

# Set working directory
WORKDIR /var/www/html

# Set permissions
RUN mkdir -p storage/app storage/framework/cache storage/framework/sessions \
    storage/framework/testing storage/framework/views storage/logs public/backups \
    && chown -R www-data:www-data storage public/backups \
    && chmod -R 775 storage

COPY deploy/php-local.ini /usr/local/etc/php/conf.d/loan-inputs.ini

EXPOSE 80
