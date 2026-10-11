DDEV ?= ddev
EXEC := $(DDEV) exec

.DEFAULT_GOAL := help

.PHONY: help
help: ## Lists the available targets.
	@grep -hE '^[a-zA-Z0-9_.-]+:.*?## ' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

.PHONY: install
install: ## Boots DDEV, installs dependencies, keys, schema and fixtures.
	$(DDEV) start
	$(DDEV) composer install
	$(EXEC) php bin/console lexik:jwt:generate-keypair --skip-if-exists
	$(EXEC) php bin/console doctrine:migrations:migrate --no-interaction
	$(MAKE) fixtures

.PHONY: fixtures
fixtures: ## Reloads the development data set.
	$(EXEC) php bin/console doctrine:fixtures:load --no-interaction

.PHONY: migrate
migrate: ## Applies the pending database migrations.
	$(EXEC) php bin/console doctrine:migrations:migrate --no-interaction

.PHONY: consume
consume: ## Drains the notification queue once, in the foreground.
	$(EXEC) php bin/console messenger:consume async --time-limit=10 -vv

.PHONY: qa
qa: ## Runs the full quality gate (same checks as CI).
	$(DDEV) composer qa

.PHONY: cs
cs: ## Applies the coding standard.
	$(DDEV) composer cs:fix

.PHONY: stan
stan: ## Runs static analysis.
	$(DDEV) composer stan

.PHONY: deptrac
deptrac: ## Verifies the module boundaries.
	$(DDEV) composer deptrac

.PHONY: test
test: ## Runs PHPUnit and Behat.
	$(DDEV) composer test

.PHONY: mutation
mutation: ## Runs mutation testing (Infection); report in var/infection/.
	$(DDEV) composer mutation

.PHONY: mail
mail: ## Opens Mailpit.
	$(DDEV) mailpit
