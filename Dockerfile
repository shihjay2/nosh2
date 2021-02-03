FROM php:7.4.14-fpm-alpine

LABEL Maintainer Michael Shihjay Chen <shihjay2@gmail.com>

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN apk add --update --no-cache \
    bash \
    supervisor \
    nano \
    mariadb-client \
    postgresql-client && \
    chmod +x /usr/local/bin/install-php-extensions && sync && \
    install-php-extensions \
    opcache \
    mysqli \
    pgsql \
    pdo_mysql \
    pdo_pgsql \
    sockets \
    intl \
    gd \
    zip \
    bz2 \
    pcntl \
    soap \
    imap \
    exif \
    gmp \
    bcmath \
    imagick \
    @composer

WORKDIR "/var/www/nosh"

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
