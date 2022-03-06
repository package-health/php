# Docker

This project relies on the following 3 application images:

* [php.package.health/nginx](docker/nginx.Dockerfile): Serves static assets (css, fonts etc.) and routes dynamic traffic to php-fpm;
* [php.package.health/php-fpm](docker/php.Dockerfile): Serves dynamic traffic based in the application code;
* [php.package.health/php-cli](docker/php.Dockerfile): Used to perform maintenance tasks, such as database migration.

## Building the base images

From the root directory of this repository:

```bash
docker build --file docker/nginx.Dockerfile --tag php.package.health/nginx:latest .
docker build --file docker/php.Dockerfile --target fpm --tag php.package.health/php-fpm:latest .
docker build --file docker/php.Dockerfile --target cli --tag php.package.health/php-cli:latest .
```

## Creating a network

The network will be shared by the containers, so they can communicate without exposing ports to the host machine.

```bash
docker network create php-package-health-network
```

## Starting the containers

Start the database container:

```bash
docker run \
  --detach \
  --env-file "$(pwd -P)/.env" \
  --volume "$(pwd -P)/var/db":/var/lib/postgresql/data \
  --network php-package-health-network \
  --name pph-postgres \
  postgres:14.2-alpine3.15
```

Start the PHP-FPM container:

```bash
docker run \
  --rm \
  --detach \
  --env-file "$(pwd -P)/.env" \
  --network php-package-health-network \
  --name pph-php-fpm \
  php.package.health/php-fpm:latest
```

Start the NGINX container:

```bash
docker run \
  --rm \
  --detach \
  --network php-package-health-network \
  --publish 8080:80/tcp \
  --name pph-nginx \
  php.package.health/nginx:latest
```

## Database Migration

Start the PHP-CLI container:

```bash
docker run \
  --rm \
  --interactive \
  --tty \
  --env-file "$(pwd -P)/.env" \
  --network php-package-health-network \
  --name pph-php-cli \
  php.package.health/php-cli:latest \
  sh
```

Check migration status:

```bash
../vendor/bin/phinx status --configuration ../phinx.php --environment "${PHP_ENV}"
```

Run migrations:

```bash
../vendor/bin/phinx migrate --configuration ../phinx.php --environment "${PHP_ENV}"
```

## Accessing the application

Open your browser and head to [http://localhost:8080/](http://localhost:8080/).

## Maintenance

The following sections are dedicated to maintenance tasks only.

### Application Console

Start the PHP-CLI container:

```bash
docker run \
  --rm \
  --interactive \
  --tty \
  --env-file "$(pwd -P)/.env" \
  --network php-package-health-network \
  --name pph-php-cli \
  php.package.health/php-cli:latest \
  sh
```

List console commands:

```bash
php console.php
```

### PostgreSQL Command Line Interface (PSQL)

Execute `sh` in the running `pph-postgres` container:

```bash
docker exec \
  --interactive \
  --tty \
  pph-postgres \
  sh
```

Run `psql`:

```bash
psql \
  --username "${POSTGRES_USER}" \
  --dbname "${POSTGRES_DB}"
```
