FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libxml2-dev \
    oniguruma-dev \
    icu-dev \
    curl-dev \
    linux-headers \
    nodejs \
    npm \
    cronie \
    su-exec

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        zip \
        gd \
        mbstring \
        xml \
        dom \
        exif \
        opcache \
        intl \
        bcmath \
        pcntl \
        posix

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Fix PHP-FPM: /proc/self/fd/2 is not accessible on Docker Desktop (Windows)
RUN for f in /usr/local/etc/php-fpm.conf /usr/local/etc/php-fpm.d/docker.conf; do \
        [ -f "$f" ] && sed -i 's|/proc/self/fd/2|/tmp/php-fpm.log|g' "$f" || true; \
    done

# Create working directory
WORKDIR /var/www/html

# Copy entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
