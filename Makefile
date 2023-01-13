.DEFAULT_GOAL := help

web: ## Open the site url using the default browser
	@xdg-open http://localhost/
.PHONY: web

rmq: ## Open the RabbitMQ admin url using the default browser
	@xdg-open http://localhost:15672/
.PHONY: rmq

psql: ## Open the Postgres Command Line utility
	@sudo docker exec -ti pph-postgres-dev sh -c 'psql -U $${POSTGRES_USER} $${POSTGRES_DB}'
.PHONY: psql

console:  ## Start an application console
	@sudo ./shell/console.sh
.PHONY: app-console

app-start:  ## Start application containers
	@sudo ./shell/up.sh
.PHONY: app-start

app-stop:  ## Stop application containers
	@sudo ./shell/down.sh
.PHONY: app-stop

app-restart: ## Restart application containers
	@sudo docker restart pph-nginx-dev pph-php-fpm-dev
.PHONY: app-restart

app-log: ## Show application log
	@sudo docker logs --follow pph-php-fpm-dev
.PHONY: app-log

daemon-start:  ## Start daemon containers
	@sudo ./shell/daemon-up.sh
.PHONY: daemon-start

daemon-stop:  ## Stop daemon containers
	@sudo ./shell/daemon-down.sh
.PHONY: daemon-stop

docker: ## Build docker images
	@sudo ./shell/build.sh
.PHONY: docker

install-deps: composer.lock ## Install PHP dependencies
	@composer validate --strict
	@composer install

update-deps: composer.json ## Update PHP dependencies
	@composer update -W

vendor: composer.json composer.lock
	install-deps

db-migrate: vendor phinx.php ## Execute database migration
	@./vendor/bin/phinx migrate --configuration=./phinx.php

db-rollback: vendor phinx.php ## Rollback database migration
	@./vendor/bin/phinx rollback --configuration=./phinx.php --target=0

lint: vendor ## Run php code linter
	@./vendor/bin/parallel-lint -j $(shell nproc) --exclude ./vendor .

# infection: vendor ## Run mutation test
# 	mkdir -p .build/infection
# 	vendor/bin/infection --configuration=./infection.json

phpcs: vendor ruleset.xml ## Run phpcs coding standards check
	@./vendor/bin/phpcs --standard=./ruleset.xml ./app ./bin ./public ./src ./tests

phpcbf: vendor ruleset.xml ## Run phpcbf coding standards fixer
	@./vendor/bin/phpcbf --standard=./ruleset.xml ./app ./bin ./public ./src tests

phpstan: vendor ## Run phpstan static code analysis
	@./vendor/bin/phpstan analyse --level=max --autoload-file=./vendor/autoload.php ./app ./bin/ ./public ./src

phpunit: vendor ## Run phpunit test suite
	@./vendor/bin/phpunit ./tests --coverage-html=./report/coverage/ --whitelist=./src --testdox-html=./report/testdox.html --disallow-test-output --process-isolation
# 	vendor/bin/phpunit --configuration=test/Unit/phpunit.xml
# 	vendor/bin/phpunit --configuration=test/Integration/phpunit.xml

psalm: vendor ## Run psalm taint analysis
	@./vendor/bin/psalm --taint-analysis

help: ## Show this help
	@printf "\033[37mUsage:\033[0m\n"
	@printf "  \033[37mmake [target]\033[0m\n\n"
	@printf "\033[34mAvailable targets:\033[0m\n"
	@grep -E '^[0-9a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[0;36m%-12s\033[m %s\n", $$1, $$2}'
	@printf "\n"
.PHONY: help
