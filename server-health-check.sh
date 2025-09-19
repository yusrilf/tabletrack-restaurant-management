#!/bin/bash

# TableTrack Server Health Check Script
# Monitors application health, database connectivity, and server resources

set -e

# Configuration
PROJECT_PATH="${1:-$(pwd)}"
LOG_FILE="${2:-$PROJECT_PATH/storage/logs/health-check.log}"
ALERT_EMAIL="${ALERT_EMAIL:-}"
SLACK_WEBHOOK="${SLACK_WEBHOOK:-}"

# Thresholds
CPU_THRESHOLD=80
MEMORY_THRESHOLD=80
DISK_THRESHOLD=85
RESPONSE_TIME_THRESHOLD=5000  # milliseconds

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Health check results
HEALTH_STATUS="HEALTHY"
ISSUES=()
WARNINGS=()

# Logging function
log() {
    local message="$1"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo -e "$message"
    echo "[$timestamp] $message" >> "$LOG_FILE" 2>/dev/null || true
}

# Error handling
error() {
    log "${RED}❌ ERROR: $1${NC}"
    HEALTH_STATUS="CRITICAL"
    ISSUES+=("$1")
}

# Warning message
warning() {
    log "${YELLOW}⚠️  WARNING: $1${NC}"
    if [ "$HEALTH_STATUS" = "HEALTHY" ]; then
        HEALTH_STATUS="WARNING"
    fi
    WARNINGS+=("$1")
}

# Success message
success() {
    log "${GREEN}✅ $1${NC}"
}

# Info message
info() {
    log "${BLUE}ℹ️  $1${NC}"
}

# Show usage
show_usage() {
    echo "TableTrack Server Health Check Script"
    echo ""
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --path PATH           Project path (default: current directory)"
    echo "  --log-file FILE       Log file path (default: storage/logs/health-check.log)"
    echo "  --alert-email EMAIL   Email for alerts"
    echo "  --slack-webhook URL   Slack webhook for notifications"
    echo "  --cpu-threshold NUM   CPU usage threshold % (default: 80)"
    echo "  --memory-threshold NUM Memory usage threshold % (default: 80)"
    echo "  --disk-threshold NUM  Disk usage threshold % (default: 85)"
    echo "  --response-threshold NUM Response time threshold ms (default: 5000)"
    echo "  --json                Output results in JSON format"
    echo "  --quiet               Suppress output (for cron jobs)"
    echo ""
    echo "Environment Variables:"
    echo "  ALERT_EMAIL          Email for alerts"
    echo "  SLACK_WEBHOOK        Slack webhook URL"
    echo ""
    echo "Examples:"
    echo "  $0                                    # Basic health check"
    echo "  $0 --json                            # JSON output"
    echo "  $0 --alert-email admin@example.com   # With email alerts"
}

# Check if Laravel application is accessible
check_laravel_app() {
    info "Checking Laravel application..."
    
    cd "$PROJECT_PATH"
    
    if [ ! -f "artisan" ]; then
        error "Laravel artisan file not found"
        return 1
    fi
    
    # Check if application is up
    if php artisan --version >/dev/null 2>&1; then
        success "Laravel application is accessible"
    else
        error "Laravel application is not responding"
        return 1
    fi
    
    # Check application environment
    APP_ENV=$(php artisan tinker --execute="echo config('app.env');" 2>/dev/null | tail -1)
    APP_DEBUG=$(php artisan tinker --execute="echo config('app.debug') ? 'true' : 'false';" 2>/dev/null | tail -1)
    
    info "Application environment: $APP_ENV"
    
    if [ "$APP_ENV" = "production" ] && [ "$APP_DEBUG" = "true" ]; then
        warning "Debug mode is enabled in production"
    fi
}

# Check database connectivity
check_database() {
    info "Checking database connectivity..."
    
    if php artisan db:show >/dev/null 2>&1; then
        success "Database connection is working"
        
        # Check database size
        DB_SIZE=$(php artisan tinker --execute="
            \$size = DB::select('SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = DATABASE()')[0]->size_mb ?? 0;
            echo \$size;
        " 2>/dev/null | tail -1)
        
        info "Database size: ${DB_SIZE}MB"
        
        # Check for failed migrations
        if ! php artisan migrate:status --pending >/dev/null 2>&1; then
            warning "There may be pending migrations"
        fi
        
    else
        error "Database connection failed"
        return 1
    fi
}

# Check queue status
check_queue() {
    info "Checking queue status..."
    
    # Check if queue workers are running
    QUEUE_WORKERS=$(ps aux | grep -c "[a]rtisan queue:work" || echo "0")
    
    if [ "$QUEUE_WORKERS" -gt 0 ]; then
        success "Queue workers are running ($QUEUE_WORKERS active)"
    else
        warning "No queue workers detected"
    fi
    
    # Check failed jobs
    FAILED_JOBS=$(php artisan queue:failed --format=json 2>/dev/null | jq length 2>/dev/null || echo "0")
    
    if [ "$FAILED_JOBS" -gt 0 ]; then
        warning "Failed jobs detected: $FAILED_JOBS"
    else
        success "No failed jobs"
    fi
}

# Check storage permissions and space
check_storage() {
    info "Checking storage..."
    
    # Check storage directories
    STORAGE_DIRS=("storage/logs" "storage/framework/cache" "storage/framework/sessions" "storage/app/public")
    
    for dir in "${STORAGE_DIRS[@]}"; do
        if [ -d "$dir" ] && [ -w "$dir" ]; then
            success "Storage directory writable: $dir"
        else
            error "Storage directory not writable: $dir"
        fi
    done
    
    # Check disk space
    DISK_USAGE=$(df "$PROJECT_PATH" | awk 'NR==2 {print $5}' | sed 's/%//')
    
    if [ "$DISK_USAGE" -lt "$DISK_THRESHOLD" ]; then
        success "Disk usage: ${DISK_USAGE}%"
    else
        error "High disk usage: ${DISK_USAGE}% (threshold: ${DISK_THRESHOLD}%)"
    fi
}

# Check system resources
check_system_resources() {
    info "Checking system resources..."
    
    # Check CPU usage
    if command -v top >/dev/null 2>&1; then
        CPU_USAGE=$(top -l 1 | grep "CPU usage" | awk '{print $3}' | sed 's/%//' 2>/dev/null || echo "0")
        
        if [ "${CPU_USAGE%.*}" -lt "$CPU_THRESHOLD" ]; then
            success "CPU usage: ${CPU_USAGE}%"
        else
            warning "High CPU usage: ${CPU_USAGE}% (threshold: ${CPU_THRESHOLD}%)"
        fi
    fi
    
    # Check memory usage
    if command -v vm_stat >/dev/null 2>&1; then
        # macOS memory check
        MEMORY_PRESSURE=$(memory_pressure 2>/dev/null | grep "System-wide memory free percentage" | awk '{print $5}' | sed 's/%//' || echo "50")
        MEMORY_USAGE=$((100 - MEMORY_PRESSURE))
        
        if [ "$MEMORY_USAGE" -lt "$MEMORY_THRESHOLD" ]; then
            success "Memory usage: ${MEMORY_USAGE}%"
        else
            warning "High memory usage: ${MEMORY_USAGE}% (threshold: ${MEMORY_THRESHOLD}%)"
        fi
    elif command -v free >/dev/null 2>&1; then
        # Linux memory check
        MEMORY_USAGE=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100.0}')
        
        if [ "$MEMORY_USAGE" -lt "$MEMORY_THRESHOLD" ]; then
            success "Memory usage: ${MEMORY_USAGE}%"
        else
            warning "High memory usage: ${MEMORY_USAGE}% (threshold: ${MEMORY_THRESHOLD}%)"
        fi
    fi
}

# Check web server response
check_web_response() {
    info "Checking web server response..."
    
    # Get application URL
    APP_URL=$(php artisan tinker --execute="echo config('app.url');" 2>/dev/null | tail -1)
    
    if [ -n "$APP_URL" ] && [ "$APP_URL" != "null" ]; then
        # Check response time
        if command -v curl >/dev/null 2>&1; then
            RESPONSE_TIME=$(curl -o /dev/null -s -w "%{time_total}" "$APP_URL" 2>/dev/null || echo "999")
            RESPONSE_TIME_MS=$(echo "$RESPONSE_TIME * 1000" | bc 2>/dev/null || echo "999000")
            
            if [ "${RESPONSE_TIME_MS%.*}" -lt "$RESPONSE_TIME_THRESHOLD" ]; then
                success "Response time: ${RESPONSE_TIME}s"
            else
                warning "Slow response time: ${RESPONSE_TIME}s (threshold: ${RESPONSE_TIME_THRESHOLD}ms)"
            fi
            
            # Check HTTP status
            HTTP_STATUS=$(curl -o /dev/null -s -w "%{http_code}" "$APP_URL" 2>/dev/null || echo "000")
            
            if [ "$HTTP_STATUS" = "200" ]; then
                success "HTTP status: $HTTP_STATUS"
            else
                error "HTTP error: $HTTP_STATUS"
            fi
        else
            warning "curl not available for web response check"
        fi
    else
        warning "APP_URL not configured"
    fi
}

# Check log files for errors
check_logs() {
    info "Checking recent log files..."
    
    LOG_DIR="$PROJECT_PATH/storage/logs"
    
    if [ -d "$LOG_DIR" ]; then
        # Check for recent errors
        RECENT_ERRORS=$(find "$LOG_DIR" -name "*.log" -mtime -1 -exec grep -l "ERROR\|CRITICAL\|EMERGENCY" {} \; 2>/dev/null | wc -l)
        
        if [ "$RECENT_ERRORS" -gt 0 ]; then
            warning "Recent errors found in $RECENT_ERRORS log files"
        else
            success "No recent errors in log files"
        fi
        
        # Check log file sizes
        LARGE_LOGS=$(find "$LOG_DIR" -name "*.log" -size +100M 2>/dev/null | wc -l)
        
        if [ "$LARGE_LOGS" -gt 0 ]; then
            warning "$LARGE_LOGS log files are larger than 100MB"
        fi
    else
        warning "Log directory not found: $LOG_DIR"
    fi
}

# Send alert notification
send_alert() {
    local subject="$1"
    local message="$2"
    
    # Email alert
    if [ -n "$ALERT_EMAIL" ] && command -v mail >/dev/null 2>&1; then
        echo "$message" | mail -s "$subject" "$ALERT_EMAIL" 2>/dev/null || true
    fi
    
    # Slack notification
    if [ -n "$SLACK_WEBHOOK" ] && command -v curl >/dev/null 2>&1; then
        curl -X POST -H 'Content-type: application/json' \
            --data "{\"text\":\"$subject\n$message\"}" \
            "$SLACK_WEBHOOK" 2>/dev/null || true
    fi
}

# Generate health report
generate_report() {
    local format="${1:-text}"
    
    if [ "$format" = "json" ]; then
        # JSON output
        cat << EOF
{
    "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "status": "$HEALTH_STATUS",
    "issues": $(printf '%s\n' "${ISSUES[@]}" | jq -R . | jq -s .),
    "warnings": $(printf '%s\n' "${WARNINGS[@]}" | jq -R . | jq -s .),
    "checks_performed": [
        "laravel_app",
        "database",
        "queue",
        "storage",
        "system_resources",
        "web_response",
        "logs"
    ]
}
EOF
    else
        # Text output
        echo ""
        echo "========================================="
        echo "TableTrack Health Check Report"
        echo "========================================="
        echo "Timestamp: $(date)"
        echo "Status: $HEALTH_STATUS"
        echo ""
        
        if [ ${#ISSUES[@]} -gt 0 ]; then
            echo "Issues:"
            printf ' - %s\n' "${ISSUES[@]}"
            echo ""
        fi
        
        if [ ${#WARNINGS[@]} -gt 0 ]; then
            echo "Warnings:"
            printf ' - %s\n' "${WARNINGS[@]}"
            echo ""
        fi
        
        echo "========================================="
    fi
}

# Parse command line arguments
OUTPUT_FORMAT="text"
QUIET=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --path)
            PROJECT_PATH="$2"
            shift 2
            ;;
        --log-file)
            LOG_FILE="$2"
            shift 2
            ;;
        --alert-email)
            ALERT_EMAIL="$2"
            shift 2
            ;;
        --slack-webhook)
            SLACK_WEBHOOK="$2"
            shift 2
            ;;
        --cpu-threshold)
            CPU_THRESHOLD="$2"
            shift 2
            ;;
        --memory-threshold)
            MEMORY_THRESHOLD="$2"
            shift 2
            ;;
        --disk-threshold)
            DISK_THRESHOLD="$2"
            shift 2
            ;;
        --response-threshold)
            RESPONSE_TIME_THRESHOLD="$2"
            shift 2
            ;;
        --json)
            OUTPUT_FORMAT="json"
            shift
            ;;
        --quiet)
            QUIET=true
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

# Redirect output if quiet mode
if [ "$QUIET" = true ]; then
    exec > /dev/null 2>&1
fi

# Create log directory if it doesn't exist
mkdir -p "$(dirname "$LOG_FILE")"

# Change to project directory
cd "$PROJECT_PATH"

# Run health checks
info "Starting TableTrack health check..."

check_laravel_app
check_database
check_queue
check_storage
check_system_resources
check_web_response
check_logs

# Generate and display report
if [ "$QUIET" != true ]; then
    generate_report "$OUTPUT_FORMAT"
fi

# Send alerts if there are issues
if [ "$HEALTH_STATUS" != "HEALTHY" ]; then
    ALERT_SUBJECT="TableTrack Health Alert - $HEALTH_STATUS"
    ALERT_MESSAGE=$(generate_report "text")
    send_alert "$ALERT_SUBJECT" "$ALERT_MESSAGE"
fi

# Exit with appropriate code
case $HEALTH_STATUS in
    "HEALTHY")
        exit 0
        ;;
    "WARNING")
        exit 1
        ;;
    "CRITICAL")
        exit 2
        ;;
esac