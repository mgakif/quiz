FROM php:8.4-apache

ARG user=laravel
ARG uid=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        libicu-dev \
        libzip-dev \
        libxml2-dev \
        default-mysql-client \
    && docker-php-ext-install pdo pdo_mysql intl xml zip \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

RUN {
    echo 'upload_max_filesize=100M';
    echo 'post_max_size=100M';
    echo 'memory_limit=256M';
    echo 'max_execution_time=300';
    echo 'max_input_time=300';
    echo 'max_file_uploads=20';
} > /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN useradd -G www-data,root -u ${uid} -d /home/${user} ${user} \
    && mkdir -p /home/${user}/.composer \
    && chown -R ${user}:${user} /home/${user}

WORKDIR /var/www/html
USER ${user}
