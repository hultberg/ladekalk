FROM docker.io/bitnami/git AS git_clone

RUN git clone https://github.com/hultberg/ladekalk /srv/app/

FROM docker.io/library/composer:2 AS composer

COPY --from=git_clone /srv/app /srv/app
RUN cd /srv/app \
    && composer install --no-dev \
    && composer dump-autoload -o

FROM docker.io/library/php:8.2-cli

COPY --from=composer /srv/app /srv/app

WORKDIR /srv/app

