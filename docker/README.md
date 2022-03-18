# Docker

This project relies on the following 3 application images:

* [package-health/nginx](docker/nginx.Dockerfile): Serves static assets (css, fonts etc.) and routes dynamic traffic to php-fpm;
* [package-health/php-fpm](docker/php.Dockerfile): Serves dynamic traffic based in the application code;
* [package-health/php-cli](docker/php.Dockerfile): Used to perform maintenance tasks, such as database migration.

## Requirements

Before running, the application images must be available in the docker daemon.

### Download the base images

Get the latest release links from [https://github.com/package-health/php/releases](https://github.com/package-health/php/releases).

**NGINX**
```shell
wget https://github.com/package-health/php/releases/download/prod%40f0d3f68/nginx-prod-f0d3f68.tar.gz && \
wget https://github.com/package-health/php/releases/download/prod%40f0d3f68/nginx-prod-f0d3f68.tar.gz.sha1 && \
shasum -c nginx-prod-f0d3f68.tar.gz.sha1 && \
docker load < nginx-prod-f0d3f68.tar.gz
```

**PHP-FPM**
```shell
wget https://github.com/package-health/php/releases/download/prod%40f0d3f68/php-fpm-prod-f0d3f68.tar.gz  && \
wget https://github.com/package-health/php/releases/download/prod%40f0d3f68/php-fpm-prod-f0d3f68.tar.gz.sha1 && \
shasum -c php-fpm-prod-f0d3f68.tar.gz.sha1 && \
docker load < php-fpm-prod-f0d3f68.tar.gz
```

**PHP-CLI**
```shell
wget https://github.com/package-health/php/releases/download/prod%40f0d3f68/php-cli-prod-f0d3f68.tar.gz && \
wget https://github.com/package-health/php/releases/download/prod%40f0d3f68/php-cli-prod-f0d3f68.tar.gz.sha1 && \
shasum -c php-cli-prod-f0d3f68.tar.gz.sha1 && \
docker load < php-cli-prod-f0d3f68.tar.gz
```

### Build the base images

From the root directory of this repository:

**NGINX**
```shell
docker build --file docker/nginx.Dockerfile --tag package-health/nginx:latest .
```

**PHP-FPM**
```shell
docker build --file docker/php.Dockerfile --target fpm --tag package-health/php-fpm:latest .
```

**PHP-CLI**
```shell
docker build --file docker/php.Dockerfile --target cli --tag package-health/php-cli:latest .
```

## Running

### Configuration

Copy `.env-dist` to `.env` and setup accordingly:

```
POSTGRES_USER=<database username>
POSTGRES_PASSWORD=<database password>
POSTGRES_DB=<database name>
POSTGRES_HOST=pph-postgres
AMQP_USER=<rabbitmq username>
AMQP_PASS=<rabbitmq password>
AMQP_HOST=pph-rabbit
PHP_ENV=development
DOCKER=true
```

### Creating a network

The network will be shared by the containers, so they can communicate without exposing ports to the host machine.

```shell
docker network create pph-network
```

### Starting the containers

Start the database container:

```shell
docker run \
  --detach \
  --env-file "$(pwd -P)/.env" \
  --volume "$(pwd -P)/run/db":/var/lib/postgresql/data \
  --network pph-network \
  --name pph-postgres \
  postgres:14.2-alpine3.15
```

Start the message broker container:

```shell
  docker run \
    --detach \
    --volume "$(pwd -P)/run/rmq":/var/lib/rabbitmq \
    --network pph-network \
    --name pph-rabbit \
    rabbitmq:3.9-management-alpine
```

Start the PHP-FPM container:

```shell
docker run \
  --rm \
  --detach \
  --env-file "$(pwd -P)/.env" \
  --network pph-network \
  --name pph-php-fpm \
  package-health/php-fpm:latest
```

Start the NGINX container:

```shell
docker run \
  --rm \
  --detach \
  --env PHP_FPM=pph-php-fpm \
  --network pph-network \
  --publish 8080:80/tcp \
  --name pph-nginx \
  package-health/nginx:latest
```

### Database Migration

Start the PHP-CLI container:

```shell
docker run \
  --rm \
  --interactive \
  --tty \
  --env-file "$(pwd -P)/.env" \
  --network pph-network \
  --name pph-php-cli \
  package-health/php-cli:latest \
  sh
```

Check migration status:

```shell
../vendor/bin/phinx status --configuration ../phinx.php --environment "${PHP_ENV}"
```

Run migrations:

```shell
../vendor/bin/phinx migrate --configuration ../phinx.php --environment "${PHP_ENV}"
```

### Accessing the application

Open your browser and head to [http://localhost:8080/](http://localhost:8080/).

## Maintenance

The following sections are dedicated to maintenance tasks only.

### Application Console

Start the PHP-CLI container:

```shell
docker run \
  --rm \
  --interactive \
  --tty \
  --env-file "$(pwd -P)/.env" \
  --network pph-network \
  --name pph-php-cli \
  package-health/php-cli:latest \
  sh
```

List console commands:

```shell
php console.php
```

### PostgreSQL Command Line Interface (PSQL)

Execute `sh` in the running `pph-postgres` container:

```shell
docker exec \
  --interactive \
  --tty \
  pph-postgres \
  sh
```

Run `psql`:

```shell
psql \
  --username "${POSTGRES_USER}" \
  --dbname "${POSTGRES_DB}"
```
