.PHONY: help
help: ## Display this help message
	@cat $(MAKEFILE_LIST) | grep -e "^[a-zA-Z_\-]*: *.*## *" | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

#################
### COMMANDS ####
#################

.PHONY: install
install: ## Install dependencies
		rm -fr composer.lock && docker build -t strictlyphp/domantra . && docker run --user=$(shell id -u):$(shell id -g) --rm --name strictlyphp-domantra -v "${PWD}":/usr/src/myapp -w /usr/src/myapp strictlyphp/domantra composer install

.PHONY: check-coverage
check-coverage: ## Check the test coverage of changed files
		git fetch origin && git diff origin/main > ${PWD}/diff.txt && docker build -t strictlyphp/domantra . && docker compose up -d && docker run --network domantra --user=$(shell id -u):$(shell id -g) --rm --name strictlyphp-domantra -v "${PWD}":/usr/src/myapp -w /usr/src/myapp strictlyphp/domantra ./build/check-coverage.sh && docker compose down

.PHONY: style
style: ## Check coding style
		docker build -t strictlyphp/domantra . && docker run --user=$(shell id -u):$(shell id -g) --rm --name strictlyphp-domantra -v "${PWD}":/usr/src/myapp -w /usr/src/myapp strictlyphp/domantra php ./vendor/bin/ecs

.PHONY: style-fix
style-fix: ## Check coding style
		docker build -t strictlyphp/domantra . && docker run --user=$(shell id -u):$(shell id -g) --rm --name strictlyphp-domantra -v "${PWD}":/usr/src/myapp -w /usr/src/myapp strictlyphp/domantra php ./vendor/bin/ecs --fix

.PHONY: analyze
analyze: ## Runs static analysis tools
		docker build -t strictlyphp82/dolphin . && docker run --user=$(shell id -u):$(shell id -g) --rm --name strictlyphp-domantra -v "${PWD}":/usr/src/myapp -w /usr/src/myapp strictlyphp/domantra php ./vendor/bin/phpstan analyse -l 6 -c phpstan.neon src tests