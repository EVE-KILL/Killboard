FROM bitnami/minideb:latest

LABEL org.opencontainers.image.source="https://github.com/EVE-KILL/Killboard"

# Run as root
USER root

# Install PHP
ARG PHP_VERSION="8.3"
RUN \
    apt update && \
    apt upgrade -y && \
    install_packages \
        ca-certificates \
        lsb-release \
        gettext-base \
        patch \
        wget \
        curl \
        procps \
        unzip \
        bzip2 \
    && \
    curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg && \
    sh -c 'echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list' && \
    apt update && \
    apt install -y \
        php${PHP_VERSION} \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-readline \
        php${PHP_VERSION}-common \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-igbinary \
        php${PHP_VERSION}-apcu \
        php${PHP_VERSION}-imagick \
        php${PHP_VERSION}-yaml \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-mysqlnd \
        php${PHP_VERSION}-mysqli \
        php${PHP_VERSION}-zip \
        php${PHP_VERSION}-bz2 \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-msgpack \
        php${PHP_VERSION}-intl \
        php${PHP_VERSION}-zstd \
        php${PHP_VERSION}-redis \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-opcache \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-soap \
        php${PHP_VERSION}-exif \
        php${PHP_VERSION}-xsl \
        php${PHP_VERSION}-gettext \
        php${PHP_VERSION}-cgi \
        php${PHP_VERSION}-dom \
        php${PHP_VERSION}-ftp \
        php${PHP_VERSION}-iconv \
        php${PHP_VERSION}-pdo \
        php${PHP_VERSION}-simplexml \
        php${PHP_VERSION}-tokenizer \
        php${PHP_VERSION}-mongodb \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-xmlwriter \
        php${PHP_VERSION}-xmlreader \
        php${PHP_VERSION}-fileinfo \
        php${PHP_VERSION}-uploadprogress \
        php${PHP_VERSION}-sqlite3 \
        php${PHP_VERSION}-gmp \
        php${PHP_VERSION}-dev \
        libcurl4-openssl-dev \
    && \
    pecl install --configureoptions 'enable-openssl="yes" enable-hook-curl="yes"' openswoole && \
    echo "extension=openswoole.so" > /etc/php/${PHP_VERSION}/cli/conf.d/20-openswoole.ini && \
    apt autoremove -y && \
    apt clean -y && \
    # Cleanup
    rm -rf /tmp/* /src

# Set workdir
WORKDIR /app

# Copy the code
COPY . /app

# Get composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install packages
#RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --no-suggest --optimize-autoloader --apcu-autoloader
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --no-suggest --optimize-autoloader --apcu-autoloader

# Copy in the module configurations
COPY .docker/config/modules/* /etc/php/${PHP_VERSION}/mods-available/

# Copy in production configs
ARG PHP_VERSION="8.3"
COPY .docker/config/php.ini /etc/php/${PHP_VERSION}/cli/php.ini

# Copy in entrypoint
COPY .docker/config/entrypoint.sh /entrypoint.sh

# Set permissions
RUN chmod +x /entrypoint.sh

WORKDIR /app
ARG PHP_VERSION="8.3"
ENV PHP_VERSION=${PHP_VERSION}
EXPOSE 9201
CMD ["php", "/app/bin/console", "server"]
