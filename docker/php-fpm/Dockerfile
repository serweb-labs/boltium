FROM php:7.4-fpm

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd exif zip intl pdo_mysql \
    && docker-php-ext-enable gd exif zip intl pdo_mysql

# Instalar APCu mediante PECL
RUN pecl install apcu \
    && docker-php-ext-enable apcu

# Instalar OPcache
RUN docker-php-ext-install opcache \
    && docker-php-ext-enable opcache

# Configurar OPcache y APCu
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=1'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'apc.enable_cli=1'; \
    echo 'apc.shm_size=64M'; \
} > /usr/local/etc/php/conf.d/opcache-apcu.ini

# Deshabilitar short_open_tag
RUN { \
    echo 'short_open_tag=Off'; \
} > /usr/local/etc/php/conf.d/short_open_tag.ini

# Limpiar cache de apt
RUN apt-get clean && rm -rf /var/lib/apt/lists/*
