#!/bin/bash

# PolyTrans Development Tools
# Docker-based development workflow for consistent environment

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[PolyTrans]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[PolyTrans]${NC} $1"
}

print_error() {
    echo -e "${RED}[PolyTrans]${NC} $1"
}

# Help function
show_help() {
    echo "PolyTrans Development Tools"
    echo ""
    echo "Usage: ./dev.sh [command]"
    echo ""
    echo "Commands:"
    echo "  setup         Build Docker containers and install dependencies"
    echo "  phpcs         Run PHP CodeSniffer (check coding standards)"
    echo "  phpcbf        Run PHP Code Beautifier (fix coding standards)"
    echo "  phpmd         Run PHP Mess Detector"
    echo "  test          Run PHPUnit tests"
    echo "  coverage      Run tests with coverage report"
    echo "  shell         Open interactive shell in development container"
    echo "  clean         Remove Docker containers and images"
    echo "  all           Run all quality checks (phpcs, phpmd, test)"
    echo ""
    echo "Examples:"
    echo "  ./dev.sh setup"
    echo "  ./dev.sh phpcs"
    echo "  ./dev.sh test"
}

# Setup function
setup() {
    print_status "Setting up PolyTrans development environment..."
    
    # Build containers
    print_status "Building Docker containers..."
    docker compose build
    
    # Install dependencies
    print_status "Installing Composer dependencies..."
    docker compose run --rm polytrans-dev composer install
    
    print_status "Setup complete! You can now run quality checks."
    echo ""
    echo "Try: ./dev.sh phpcs"
}

# Run PHPCS
run_phpcs() {
    print_status "Running PHP CodeSniffer..."
    docker compose run --rm polytrans-dev composer run phpcs
}

# Run PHPCBF
run_phpcbf() {
    print_status "Running PHP Code Beautifier..."
    docker compose run --rm polytrans-dev composer run phpcbf
}

# Run PHPMD
run_phpmd() {
    print_status "Running PHP Mess Detector..."
    docker compose run --rm polytrans-dev composer run phpmd
}

# Run tests
run_tests() {
    print_status "Running PHPUnit tests..."
    docker compose run --rm polytrans-dev composer run test
}

# Run tests with coverage
run_coverage() {
    print_status "Running tests with coverage..."
    docker compose run --rm polytrans-dev composer run test-coverage
}

# Open shell
open_shell() {
    print_status "Opening development shell..."
    docker compose run --rm polytrans-dev bash
}

# Clean up
clean() {
    print_status "Cleaning up Docker containers and images..."
    docker compose down --rmi all --volumes
    print_status "Cleanup complete!"
}

# Run all checks
run_all() {
    print_status "Running all quality checks..."
    
    echo ""
    print_status "1/3 - Running PHP CodeSniffer..."
    if docker compose run --rm polytrans-dev composer run phpcs; then
        print_status "✓ PHPCS passed"
    else
        print_error "✗ PHPCS failed"
        return 1
    fi
    
    echo ""
    print_status "2/3 - Running PHP Mess Detector..."
    if docker compose run --rm polytrans-dev composer run phpmd; then
        print_status "✓ PHPMD passed"
    else
        print_warning "✗ PHPMD found issues"
    fi
    
    echo ""
    print_status "3/3 - Running tests..."
    if docker compose run --rm polytrans-dev composer run test; then
        print_status "✓ Tests passed"
    else
        print_error "✗ Tests failed"
        return 1
    fi
    
    echo ""
    print_status "All checks completed!"
}

# Main script logic
case "${1:-}" in
    setup)
        setup
        ;;
    phpcs)
        run_phpcs
        ;;
    phpcbf)
        run_phpcbf
        ;;
    phpmd)
        run_phpmd
        ;;
    test)
        run_tests
        ;;
    coverage)
        run_coverage
        ;;
    shell)
        open_shell
        ;;
    clean)
        clean
        ;;
    all)
        run_all
        ;;
    help|--help|-h)
        show_help
        ;;
    "")
        show_help
        ;;
    *)
        print_error "Unknown command: $1"
        echo ""
        show_help
        exit 1
        ;;
esac
