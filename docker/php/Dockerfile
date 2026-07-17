# Base images are pulled from ECR Public mirror of Docker Hub official images,
# so the build works even where docker.io is unreachable.
ARG REGISTRY=public.ecr.aws/docker/library

FROM ${REGISTRY}/php:8.5-fpm-alpine AS base
RUN apk add --no-cache icu-libs \
    && apk add --no-cache --virtual .build-deps icu-dev $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" intl \
    && apk del .build-deps
COPY docker/php/app.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/zz-app-pool.conf /usr/local/etc/php-fpm.d/zz-app-pool.conf
WORKDIR /app

FROM base AS dev
RUN apk add --no-cache git unzip \
    && printf '[www]\nuser = root\ngroup = root\n' > /usr/local/etc/php-fpm.d/zz-dev-root.conf
COPY --from=public.ecr.aws/docker/library/composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
# Dev runs as root to avoid host-mount permission friction; prod stage runs non-root.
CMD ["php-fpm", "-R"]

FROM base AS prod
ENV APP_ENV=prod APP_DEBUG=0
COPY --from=public.ecr.aws/docker/library/composer:2 /usr/bin/composer /usr/bin/composer
COPY app/composer.json app/composer.lock app/symfony.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-scripts --prefer-dist --no-progress --no-interaction
COPY app/ ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --optimize --classmap-authoritative \
    && APP_ENV=prod php bin/console cache:warmup \
    && addgroup -g 1000 app && adduser -u 1000 -G app -D app \
    && chown -R app:app /app/var
USER app
CMD ["php-fpm"]

# Single-container deploy target (Render/Railway/any Docker host): Caddy serves
# HTTP on $PORT and proxies to php-fpm inside the same container. This is the
# LAST stage on purpose - a plain `docker build .` (what Render runs) picks it.
FROM prod AS serve
USER root
RUN apk add --no-cache caddy \
    && printf '#!/bin/sh\nset -e\nphp-fpm -D\nexec caddy run --config /etc/caddy/Caddyfile\n' > /usr/local/bin/serve \
    && chmod +x /usr/local/bin/serve
COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile
USER app
EXPOSE 8080
CMD ["serve"]
