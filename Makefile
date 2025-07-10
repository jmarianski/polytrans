# PolyTrans Plugin Development Makefile
# Provides easy commands for development workflow

.PHONY: help setup phpcs phpcbf phpmd test coverage shell clean all

# Default target
help: ## Show this help message
	@echo "PolyTrans Plugin Development Commands"
	@echo ""
	@echo "Available commands:"
	@awk 'BEGIN {FS = ":.*##"} /^[a-zA-Z_-]+:.*##/ { printf "  %-12s %s\n", $$1, $$2 }' $(MAKEFILE_LIST)
	@echo ""
	@echo "Requirements: Docker and Docker Compose"

setup: ## Build containers and install dependencies
	@echo "Setting up development environment..."
	@docker compose build
	@docker compose run --rm polytrans-dev composer install
	@echo "✅ Setup complete!"

phpcs: ## Run PHP CodeSniffer (coding standards check)
	@echo "Running PHP CodeSniffer..."
	@docker compose run --rm polytrans-dev composer run phpcs

phpcs-relaxed: ## Run PHP CodeSniffer with relaxed rules (no indentation)
	@echo "Running PHP CodeSniffer (relaxed)..."
	@docker compose run --rm polytrans-dev composer run phpcs-relaxed

phpcs-syntax: ## Run PHP CodeSniffer syntax check only
	@echo "Running PHP CodeSniffer (syntax only)..."
	@docker compose run --rm polytrans-dev composer run phpcs-syntax

phpcbf: ## Fix coding standards automatically
	@echo "Running PHP Code Beautifier..."
	@docker compose run --rm polytrans-dev composer run phpcbf

phpmd: ## Run PHP Mess Detector
	@echo "Running PHP Mess Detector..."
	@docker compose run --rm polytrans-dev composer run phpmd

test: ## Run PHPUnit tests
	@echo "Running tests..."
	@docker compose run --rm polytrans-dev composer run test

coverage: ## Run tests with coverage report
	@echo "Running tests with coverage..."
	@docker compose run --rm polytrans-dev composer run test-coverage

shell: ## Open interactive shell in development container
	@echo "Opening development shell..."
	@docker compose run --rm polytrans-dev bash

clean: ## Remove all Docker containers and images
	@echo "Cleaning up..."
	@docker compose down --rmi all --volumes
	@echo "✅ Cleanup complete!"

all: ## Run all quality checks (phpcs, phpmd, tests)
	@echo "Running all quality checks..."
	@echo ""
	@echo "1/3 - Checking coding standards..."
	@docker compose run --rm polytrans-dev composer run phpcs
	@echo ""
	@echo "2/3 - Running mess detector..."
	@docker compose run --rm polytrans-dev composer run phpmd
	@echo ""
	@echo "3/3 - Running tests..."
	@docker compose run --rm polytrans-dev composer run test
	@echo ""
	@echo "✅ All checks completed!"

# Quick aliases
cs: phpcs ## Alias for phpcs
fix: phpcbf ## Alias for phpcbf
md: phpmd ## Alias for phpmd
