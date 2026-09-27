FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libpq-dev \
    libsqlite3-dev \
    default-mysql-client \
    postgresql-client \
 && docker-php-ext-install pdo_mysql pdo_pgsql pdo_sqlite \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN git config --global --add safe.directory /app

WORKDIR /app
