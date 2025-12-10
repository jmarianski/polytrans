#!/bin/bash
# PolyTrans Test Runner
# Runs Pest tests inside Docker container

set -e

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}PolyTrans Test Suite${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Parse arguments
TEST_FILTER=""
COVERAGE=false
PARALLEL=false

while [[ $# -gt 0 ]]; do
  case $1 in
    --filter=*)
      TEST_FILTER="${1#*=}"
      shift
      ;;
    --coverage)
      COVERAGE=true
      shift
      ;;
    --parallel)
      PARALLEL=true
      shift
      ;;
    *)
      echo -e "${RED}Unknown option: $1${NC}"
      exit 1
      ;;
  esac
done

# Build test container
echo -e "${BLUE}Building test container...${NC}"
docker compose -f docker-compose.test.yml build polytrans-test

# Install dependencies if needed
if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
  echo -e "${BLUE}Installing Composer dependencies...${NC}"
  docker compose -f docker-compose.test.yml run --rm polytrans-test composer install --no-interaction
fi

# Start MySQL
echo -e "${BLUE}Starting MySQL test database...${NC}"
docker compose -f docker-compose.test.yml up -d mysql-test

# Wait for MySQL to be ready
echo -e "${BLUE}Waiting for MySQL...${NC}"
for i in {1..30}; do
  if docker compose -f docker-compose.test.yml exec -T mysql-test mysqladmin ping -h localhost -u root -ptest_root_pass &> /dev/null; then
    echo -e "${GREEN}MySQL ready!${NC}"
    break
  fi
  echo -n "."
  sleep 1
done

# Build test command
TEST_CMD="./vendor/bin/pest"

if [ "$COVERAGE" = true ]; then
  TEST_CMD="$TEST_CMD --coverage --min=80"
fi

if [ "$PARALLEL" = true ]; then
  TEST_CMD="$TEST_CMD --parallel"
fi

if [ -n "$TEST_FILTER" ]; then
  TEST_CMD="$TEST_CMD --filter=$TEST_FILTER"
fi

# Run tests
echo -e "${BLUE}Running tests...${NC}"
echo -e "${BLUE}Command: $TEST_CMD${NC}"
echo ""

docker compose -f docker-compose.test.yml run --rm polytrans-test $TEST_CMD

# Capture exit code
EXIT_CODE=$?

# Cleanup
echo ""
echo -e "${BLUE}Stopping test services...${NC}"
docker compose -f docker-compose.test.yml down

if [ $EXIT_CODE -eq 0 ]; then
  echo -e "${GREEN}✓ All tests passed!${NC}"
else
  echo -e "${RED}✗ Tests failed with exit code $EXIT_CODE${NC}"
fi

exit $EXIT_CODE
