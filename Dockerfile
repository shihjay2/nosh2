FROM php:7.4.13-fpm-alpine

LABEL Maintainer Michael Shihjay Chen <shihjay2@gmail.com>

RUN rm -f /etc/apk/repositories &&\
    echo "https://dl-cdn.alpinelinux.org/alpine/edge/main" >> /etc/apk/repositories &&\
    echo "https://dl-cdn.alpinelinux.org/alpine/edge/community" >> /etc/apk/repositories &&\
    echo "https://dl-cdn.alpinelinux.org/alpine/edge/testing" >> /etc/apk/repositories &&\
    apk add --no-cache --virtual .build-deps \
    git \
    zlib-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libxml2-dev \
    php7-dev \
    autoconf \
    gcc \
    g++ \
    make \
    pcre-dev \
    bzip2-dev &&\
    apk add --update --no-cache \
    jpegoptim \
    pngquant \
    optipng \
    supervisor \
    nano \
    icu-dev \
    mariadb-client \
    imagemagick-dev \
    libssh2-dev \
    libzip-dev \
    imap-dev \
    libtool \
    freetype-dev &&\
    docker-php-ext-configure \
    opcache --enable-opcache &&\
    docker-php-ext-configure gd --with-freetype --with-jpeg &&\
    PHP_OPENSSL=yes docker-php-ext-configure imap --with-imap --with-imap-ssl &&\
    docker-php-ext-install \
    opcache \
    mysqli \
    pdo \
    pdo_mysql \
    sockets \
    intl \
    gd \
    xml \
    zip \
    bz2 \
    pcntl \
    soap \
    imap \
    exif \
    json \
    bcmath &&\
    pecl install imagick &&\
    docker-php-ext-enable imagick &&\
    mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" &&\
    apk del .build-deps

WORKDIR "/var/www/nosh"

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="./vendor/bin:$PATH"

# Copy existing application directory permissions
COPY --chown=www-data:www-data . /var/www/nosh
RUN mkdir /var/www/nosh/vendor &&\
    chmod 777 /var/www/nosh/storage &&\
    chmod 777 /var/www/nosh/public &&\
    chmod 777 /var/www/nosh/vendor &&\
    mkdir /noshdocuments &&\
    chown -R www-data:www-data /noshdocuments &&\
    chmod -R 755 /noshdocuments

USER www-data

# Install all PHP dependencies
RUN composer install --no-interaction

USER root

COPY docker-entrypoint.sh /usr/local/bin/
RUN ["chmod", "+x", "/usr/local/bin/docker-entrypoint.sh"]

COPY supervisord.conf /etc/supervisord.conf
COPY schedule.sh /usr/local/bin/schedule.sh
RUN ["chmod", "+x", "/usr/local/bin/schedule.sh"]

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

EXPOSE 9000
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisord.conf"]
