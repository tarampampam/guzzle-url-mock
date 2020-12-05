#!/usr/bin/make
# Makefile readme (ru): <http://linux.yaroslavl.ru/docs/prog/gnu_make_3-79_russian_manual.html>
# Makefile readme (en): <https://www.gnu.org/software/make/manual/html_node/index.html#SEC_Contents>

SHELL = /bin/sh
RUN_APP_ARGS = --rm --user "$(shell id -u):$(shell id -g)" app

.PHONY : help build latest install lowest test test-cover clean
.DEFAULT_GOAL : help

# This will output the help for each task. thanks to https://marmelab.com/blog/2016/02/29/auto-documented-makefile.html
help: ## Show this help
	@printf "\033[33m%s:\033[0m\n" 'Available commands'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  \033[32m%-14s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

build: ## Build docker images, required for current package environment
	docker-compose build

latest: clean ## Install latest php dependencies
	docker-compose run $(RUN_APP_ARGS) composer update -n --ansi --prefer-dist --prefer-stable

install: clean ## Install regular php dependencies
	docker-compose run $(RUN_APP_ARGS) composer update -n --prefer-dist

lowest: clean ## Install lowest php dependencies
	docker-compose run $(RUN_APP_ARGS) composer update -n --ansi --prefer-dist --prefer-lowest

test: ## Execute php tests and linters
	docker-compose run $(RUN_APP_ARGS) composer test

test-cover: ## Execute php tests with coverage
	docker-compose run --rm --user "0:0" app sh -c 'docker-php-ext-enable xdebug && su $(shell whoami) -s /bin/sh -c "composer phpunit-cover"'

shell: ## Start shell into container with php
	docker-compose run -e "PS1=\[\033[1;32m\]\[\033[1;36m\][\u@docker] \[\033[1;34m\]\w\[\033[0;35m\] \[\033[1;36m\]# \[\033[0m\]" $(RUN_APP_ARGS) sh

clean: ## Remove all dependencies and unimportant files
	-rm -Rf ./composer.lock ./vendor ./coverage
