# PHP with Composer and the ftp extension: the harness installs this package and
# every omnidrive/* provider at start.
FROM php:8.4-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && docker-php-ext-install -j"$(nproc)" ftp \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_CACHE_DIR=/harness/.cache
WORKDIR /harness
ENTRYPOINT ["/omnidrive/core/docker/entrypoint.sh"]
CMD ["bare"]
