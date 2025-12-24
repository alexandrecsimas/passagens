FROM serversideup/php:8.4-fpm-nginx


USER root

# Install the intl extension with root permissions
RUN install-php-extensions intl
RUN install-php-extensions gd
RUN install-php-extensions bcmath
RUN install-php-extensions sockets
RUN install-php-extensions pcov
RUN install-php-extensions exif

COPY zzz-custom-php.ini /usr/local/etc/php/conf.d/

RUN apt-get update \
    && apt-get upgrade -y \
    && apt-get install -f \
    && apt-get install -y git default-mysql-client nodejs npm \
        libnspr4 libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 \
        libxkbcommon0 libgbm1 libasound2 libpango-1.0-0 libxshmfence1 \
        libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 libgtk-3-0 \
        fonts-liberation libappindicator3-1 libnss3-tools xdg-utils \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# Instalar dependências do Composer (quando o container iniciar)
# Note: composer install é executado via entrypoint ou volume montado

# Instalar Chromium para Playwright (durante o build)
RUN npx playwright install chromium

# Copiar binários do Playwright para o diretório do www-data
RUN mkdir -p /var/www/.cache/ms-playwright && \
    cp -r /root/.cache/ms-playwright/* /var/www/.cache/ms-playwright/ && \
    chown -R www-data:www-data /var/www/.cache/ms-playwright/

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN docker-php-serversideup-set-id www-data $USER_ID:$GROUP_ID
RUN docker-php-serversideup-set-file-permissions --owner $USER_ID:$GROUP_ID --service nginx

USER www-data
