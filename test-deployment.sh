#!/bin/bash

# TableTrack Deployment Test Script
# Tests deployment workflow locally before pushing to GitHub

set -e

# Configuration
PROJECT_PATH=""
TEST_ENV_FILE=".env.testing"
BACKUP_DIR="storage/backups/test"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test results
TESTS_PASSED=0
TESTS_FAILED=0
TEST_RESULTS=()

# Logging function
log() {
    echo -e "$1"
}

# Error handling
error() {
    log "${RED}❌ FAIL: $1${NC}"
    TESTS_FAILED=$((TESTS_FAILED + 1))
    TEST_RESULTS+=("FAIL: $1")
}

# Success message
success() {
    log "${GREEN}✅ PASS: $1${NC}"
    TESTS_PASSED=$((TESTS_PASSED + 1))
    TEST_RESULTS+=("PASS: $1")
}

# Warning message
warning() {
    log "${YELLOW}⚠️  WARNING: $1${NC}"
}

# Info message
info() {
    log "${BLUE}ℹ️  $1${NC}"
}

# Show usage
show_usage() {
    echo "TableTrack Deployment Test Script"
    echo ""
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --path PATH           Project path (default: current directory)"
    echo "  --skip-tests          Skip PHPUnit tests"
    echo "  --skip-build          Skip asset building"
    echo "  --skip-db             Skip database tests"
    echo "  --verbose             Verbose output"
    echo ""
    echo "Tests performed:"
    echo "  - Environment configuration"
    echo "  - Dependencies check"
    echo "  - Database connectivity"
    echo "  - PHPUnit tests"
    echo "  - Asset building"
    echo "  - File permissions"
    echo "  - Deployment scripts"
    echo "  - Health checks"
    echo ""
    echo "Examples:"
    echo "  $0                    # Run all tests"
    echo "  $0 --skip-tests       # Skip PHPUnit tests"
    echo "  $0 --verbose          # Verbose output"
}

# Test environment configuration
test_environment() {
    info "Testing environment configuration..."
    
    # Check if .env file exists
    if [ -f ".env" ]; then
        success "Environment file exists"
    else
        error "Environment file (.env) not found"
        return 1
    fi
    
    # Check required environment variables
    REQUIRED_VARS=("APP_NAME" "APP_KEY" "DB_CONNECTION" "DB_DATABASE")
    
    for var in "${REQUIRED_VARS[@]}"; do
        if grep -q "^${var}=" .env; then
            success "Required environment variable: $var"
        else
            error "Missing environment variable: $var"
        fi
    done
    
    # Check if APP_KEY is set
    APP_KEY=$(grep "^APP_KEY=" .env | cut -d'=' -f2)
    if [ -n "$APP_KEY" ] && [ "$APP_KEY" != "base64:" ]; then
        success "Application key is configured"
    else
        error "Application key not configured"
    fi
}

# Test dependencies
test_dependencies() {
    info "Testing dependencies..."
    
    # Check PHP version
    PHP_VERSION=$(php -v | /usr/bin/head -n1 | cut -d' ' -f2 | cut -d'.' -f1,2)
    if [ "$(echo "$PHP_VERSION >= 8.2" | bc 2>/dev/null || echo 0)" -eq 1 ]; then
        success "PHP version: $PHP_VERSION"
    else
        error "PHP version $PHP_VERSION is below required 8.2"
    fi
    
    # Check Composer
    if command -v composer >/dev/null 2>&1; then
        success "Composer is available"
        
        # Check if vendor directory exists
        if [ -d "vendor" ]; then
            success "Vendor dependencies installed"
        else
            warning "Vendor dependencies not installed, installing..."
            composer install --no-dev --optimize-autoloader
        fi
    else
        error "Composer not found"
    fi
    
    # Check Node.js and npm
    if command -v node >/dev/null 2>&1 && command -v npm >/dev/null 2>&1; then
        NODE_VERSION=$(node -v)
        success "Node.js is available: $NODE_VERSION"
        
        # Check if node_modules exists
        if [ -d "node_modules" ]; then
            success "Node dependencies installed"
        else
            warning "Node dependencies not installed, installing..."
            npm install
        fi
    else
        error "Node.js or npm not found"
    fi
}

# Test database connectivity
test_database() {
    info "Testing database connectivity..."
    
    # Test database connection
    if php artisan db:show >/dev/null 2>&1; then
        success "Database connection working"
    else
        error "Database connection failed"
        return 1
    fi
    
    # Check migrations
    if php artisan migrate:status >/dev/null 2>&1; then
        success "Migration status check passed"
    else
        error "Migration status check failed"
    fi
    
    # Test database queries
    if php artisan tinker --execute="DB::table('users')->count();" >/dev/null 2>&1; then
        success "Database queries working"
    else
        error "Database queries failed"
    fi
}

# Test PHPUnit tests
test_phpunit() {
    info "Running PHPUnit tests..."
    
    if [ -f "phpunit.xml" ] || [ -f "phpunit.xml.dist" ]; then
        if php artisan test --parallel >/dev/null 2>&1; then
            success "PHPUnit tests passed"
        else
            error "PHPUnit tests failed"
        fi
    else
        warning "PHPUnit configuration not found, skipping tests"
    fi
}

# Test asset building
test_assets() {
    info "Testing asset building..."
    
    if [ -f "package.json" ]; then
        # Test development build
        if npm run dev >/dev/null 2>&1; then
            success "Development build successful"
        else
            error "Development build failed"
        fi
        
        # Test production build
        if npm run build >/dev/null 2>&1; then
            success "Production build successful"
        else
            error "Production build failed"
        fi
    else
        warning "package.json not found, skipping asset tests"
    fi
}

# Test file permissions
test_permissions() {
    info "Testing file permissions..."
    
    # Check storage directory permissions
    STORAGE_DIRS=("storage/logs" "storage/framework/cache" "storage/framework/sessions" "storage/app/public")
    
    for dir in "${STORAGE_DIRS[@]}"; do
        if [ -d "$dir" ] && [ -w "$dir" ]; then
            success "Directory writable: $dir"
        else
            error "Directory not writable: $dir"
        fi
    done
    
    # Check bootstrap/cache permissions
    if [ -d "bootstrap/cache" ] && [ -w "bootstrap/cache" ]; then
        success "Bootstrap cache directory writable"
    else
        error "Bootstrap cache directory not writable"
    fi
}

# Test deployment scripts
test_deployment_scripts() {
    info "Testing deployment scripts..."
    
    # Check if deployment scripts exist and are executable
    SCRIPTS=("deploy.sh" "database-optimize.sh" "backup-restore.sh" "server-health-check.sh")
    
    for script in "${SCRIPTS[@]}"; do
        if [ -f "$script" ]; then
            if [ -x "$script" ]; then
                success "Deployment script executable: $script"
            else
                error "Deployment script not executable: $script"
            fi
        else
            error "Deployment script missing: $script"
        fi
    done
    
    # Test deployment script syntax
    for script in "${SCRIPTS[@]}"; do
        if [ -f "$script" ]; then
            if bash -n "$script" 2>/dev/null; then
                success "Script syntax valid: $script"
            else
                error "Script syntax error: $script"
            fi
        fi
    done
}

# Test Laravel optimization commands
test_laravel_optimization() {
    info "Testing Laravel optimization commands..."
    
    # Test config cache
    if php artisan config:cache >/dev/null 2>&1; then
        success "Config cache successful"
        php artisan config:clear >/dev/null 2>&1  # Clean up
    else
        error "Config cache failed"
    fi
    
    # Test route cache
    if php artisan route:cache >/dev/null 2>&1; then
        success "Route cache successful"
        php artisan route:clear >/dev/null 2>&1  # Clean up
    else
        error "Route cache failed"
    fi
    
    # Test view cache
    if php artisan view:cache >/dev/null 2>&1; then
        success "View cache successful"
        php artisan view:clear >/dev/null 2>&1  # Clean up
    else
        error "View cache failed"
    fi
}

# Test health check script
test_health_check() {
    info "Testing health check script..."
    
    if [ -f "server-health-check.sh" ]; then
        if ./server-health-check.sh --quiet >/dev/null 2>&1; then
            success "Health check script passed"
        else
            warning "Health check script reported issues"
        fi
    else
        error "Health check script not found"
    fi
}

# Test GitHub Actions workflow
test_github_workflow() {
    info "Testing GitHub Actions workflow..."
    
    if [ -f ".github/workflows/deploy.yml" ]; then
        success "GitHub Actions workflow file exists"
        
        # Basic YAML syntax check
        if command -v yamllint >/dev/null 2>&1; then
            if yamllint .github/workflows/deploy.yml >/dev/null 2>&1; then
                success "Workflow YAML syntax valid"
            else
                error "Workflow YAML syntax error"
            fi
        else
            warning "yamllint not available for YAML validation"
        fi
    else
        error "GitHub Actions workflow file missing"
    fi
    
    # Check required secrets documentation
    if [ -f "GITHUB-SECRETS-SETUP.md" ]; then
        success "GitHub secrets documentation exists"
    else
        error "GitHub secrets documentation missing"
    fi
}

# Generate test report
generate_test_report() {
    echo ""
    echo "========================================="
    echo "TableTrack Deployment Test Report"
    echo "========================================="
    echo "Timestamp: $(date)"
    echo "Tests Passed: $TESTS_PASSED"
    echo "Tests Failed: $TESTS_FAILED"
    echo ""
    
    if [ $TESTS_FAILED -eq 0 ]; then
        log "${GREEN}🎉 All tests passed! Ready for deployment.${NC}"
    else
        log "${RED}❌ $TESTS_FAILED test(s) failed. Please fix issues before deployment.${NC}"
        echo ""
        echo "Failed Tests:"
        for result in "${TEST_RESULTS[@]}"; do
            if [[ $result == FAIL:* ]]; then
                echo " - ${result#FAIL: }"
            fi
        done
    fi
    
    echo ""
    echo "========================================="
}

# Parse command line arguments
SKIP_TESTS=false
SKIP_BUILD=false
SKIP_DB=false
VERBOSE=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --path)
            PROJECT_PATH="$2"
            shift 2
            ;;
        --skip-tests)
            SKIP_TESTS=true
            shift
            ;;
        --skip-build)
            SKIP_BUILD=true
            shift
            ;;
        --skip-db)
            SKIP_DB=true
            shift
            ;;
        --verbose)
            VERBOSE=true
            shift
            ;;
        -h|--help)
            show_usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            show_usage
            exit 1
            ;;
    esac
done

# Ensure PROJECT_PATH is set to current directory if not specified
if [ -z "$PROJECT_PATH" ]; then
    PROJECT_PATH="$(pwd)"
fi

# Change to project directory
cd "$PROJECT_PATH"

# Run tests
info "Starting TableTrack deployment tests..."
echo ""

test_environment
test_dependencies

if [ "$SKIP_DB" != true ]; then
    test_database
fi

if [ "$SKIP_TESTS" != true ]; then
    test_phpunit
fi

if [ "$SKIP_BUILD" != true ]; then
    test_assets
fi

test_permissions
test_deployment_scripts
test_laravel_optimization
test_health_check
test_github_workflow

# Generate report
generate_test_report

# Exit with appropriate code
if [ $TESTS_FAILED -eq 0 ]; then
    exit 0
else
    exit 1
fi