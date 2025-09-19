#!/bin/bash

# TableTrack Database Migration and Optimization Script
# This script handles database migrations and optimizations safely

set -e

echo "🗄️  TableTrack Database Migration & Optimization"
echo "================================================"

# Configuration
PROJECT_PATH="${1:-$(pwd)}"
BACKUP_PATH="${2:-$PROJECT_PATH/storage/backups}"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
LOG_FILE="$PROJECT_PATH/storage/logs/database-migration-$TIMESTAMP.log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "$1" | tee -a "$LOG_FILE"
}

# Error handling
error_exit() {
    log "${RED}❌ Error: $1${NC}"
    exit 1
}

# Success message
success() {
    log "${GREEN}✅ $1${NC}"
}

# Warning message
warning() {
    log "${YELLOW}⚠️  $1${NC}"
}

# Info message
info() {
    log "${BLUE}ℹ️  $1${NC}"
}

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    error_exit "artisan file not found. Please run this script from the Laravel project root."
fi

# Create necessary directories
mkdir -p "$BACKUP_PATH"
mkdir -p "$(dirname "$LOG_FILE")"

log "🚀 Starting database migration and optimization at $(date)"
log "📁 Project path: $PROJECT_PATH"
log "💾 Backup path: $BACKUP_PATH"
log "📝 Log file: $LOG_FILE"

# Check database connection
info "Testing database connection..."
if php artisan migrate:status > /dev/null 2>&1; then
    success "Database connection successful"
else
    error_exit "Cannot connect to database. Please check your .env configuration."
fi

# Create database backup
info "Creating database backup..."
DB_HOST=$(php artisan tinker --execute="echo config('database.connections.mysql.host');" 2>/dev/null | tail -1)
DB_DATABASE=$(php artisan tinker --execute="echo config('database.connections.mysql.database');" 2>/dev/null | tail -1)
DB_USERNAME=$(php artisan tinker --execute="echo config('database.connections.mysql.username');" 2>/dev/null | tail -1)
DB_PASSWORD=$(php artisan tinker --execute="echo config('database.connections.mysql.password');" 2>/dev/null | tail -1)

BACKUP_FILE="$BACKUP_PATH/database_backup_$TIMESTAMP.sql"

# Create database backup using mysqldump
if command -v mysqldump &> /dev/null; then
    info "Creating MySQL dump backup..."
    if mysqldump -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" > "$BACKUP_FILE" 2>/dev/null; then
        success "Database backup created: $BACKUP_FILE"
    else
        warning "mysqldump failed, continuing without backup"
    fi
else
    warning "mysqldump not available, skipping database backup"
fi

# Check for pending migrations
info "Checking for pending migrations..."
PENDING_MIGRATIONS=$(php artisan migrate:status | grep -c "No" || true)

if [ "$PENDING_MIGRATIONS" -gt 0 ]; then
    info "Found $PENDING_MIGRATIONS pending migrations"
    
    # Show pending migrations
    log "Pending migrations:"
    php artisan migrate:status | grep "No" | tee -a "$LOG_FILE"
    
    # Run migrations
    info "Running database migrations..."
    if php artisan migrate --force; then
        success "Database migrations completed successfully"
    else
        error_exit "Database migration failed"
    fi
else
    success "No pending migrations found"
fi

# Optimize database tables
info "Optimizing database tables..."

# Get list of tables
TABLES=$(php artisan tinker --execute="
\$tables = DB::select('SHOW TABLES');
foreach (\$tables as \$table) {
    \$tableName = array_values((array) \$table)[0];
    echo \$tableName . PHP_EOL;
}
" 2>/dev/null | grep -v "Psy Shell" | grep -v ">>>" | grep -v "=>" | grep -v "^$")

# Optimize each table
for table in $TABLES; do
    if [ ! -z "$table" ]; then
        info "Optimizing table: $table"
        php artisan tinker --execute="DB::statement('OPTIMIZE TABLE $table');" > /dev/null 2>&1 || warning "Failed to optimize table: $table"
    fi
done

success "Database table optimization completed"

# Update database statistics
info "Updating database statistics..."
php artisan tinker --execute="DB::statement('ANALYZE TABLE ' . implode(', ', array_map(function(\$table) { return array_values((array) \$table)[0]; }, DB::select('SHOW TABLES'))));" > /dev/null 2>&1 || warning "Failed to update statistics"

# Clear and rebuild cache
info "Clearing application cache..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

info "Rebuilding optimized cache..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

success "Cache optimization completed"

# Database maintenance commands
info "Running database maintenance..."

# Clear expired sessions
php artisan tinker --execute="DB::table('sessions')->where('last_activity', '<', now()->subDays(30)->timestamp)->delete();" > /dev/null 2>&1 || warning "Failed to clear expired sessions"

# Clear old logs (keep last 30 days)
find "$PROJECT_PATH/storage/logs" -name "*.log" -type f -mtime +30 -delete 2>/dev/null || warning "Failed to clean old logs"

# Clear old backups (keep last 10)
if [ -d "$BACKUP_PATH" ]; then
    ls -t "$BACKUP_PATH"/database_backup_*.sql 2>/dev/null | tail -n +11 | xargs rm -f 2>/dev/null || true
fi

success "Database maintenance completed"

# Performance recommendations
info "Generating performance recommendations..."

# Check table sizes
log "📊 Database table sizes:"
php artisan tinker --execute="
\$tables = DB::select('
    SELECT 
        table_name AS \`Table\`,
        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS \`Size (MB)\`
    FROM information_schema.TABLES 
    WHERE table_schema = DATABASE()
    ORDER BY (data_length + index_length) DESC
    LIMIT 10
');
foreach (\$tables as \$table) {
    echo \$table->Table . ': ' . \$table->{'Size (MB)'} . ' MB' . PHP_EOL;
}
" 2>/dev/null | grep -v "Psy Shell" | grep -v ">>>" | grep -v "=>" | tee -a "$LOG_FILE"

# Check for missing indexes
log "🔍 Checking for potential index optimizations..."
php artisan tinker --execute="
\$slowQueries = DB::select('
    SELECT 
        table_name,
        column_name,
        cardinality
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND cardinality < 10
    ORDER BY cardinality ASC
    LIMIT 5
');
if (count(\$slowQueries) > 0) {
    echo 'Tables with low cardinality indexes (consider optimization):' . PHP_EOL;
    foreach (\$slowQueries as \$query) {
        echo '- ' . \$query->table_name . '.' . \$query->column_name . ' (cardinality: ' . \$query->cardinality . ')' . PHP_EOL;
    }
} else {
    echo 'No obvious index optimization opportunities found.' . PHP_EOL;
}
" 2>/dev/null | grep -v "Psy Shell" | grep -v ">>>" | grep -v "=>" | tee -a "$LOG_FILE"

# Final status check
info "Running final status check..."
if php artisan migrate:status > /dev/null 2>&1; then
    success "Database is in good state"
else
    warning "Database status check failed"
fi

# Summary
log ""
log "📋 Migration & Optimization Summary"
log "=================================="
log "✅ Database backup: $([ -f "$BACKUP_FILE" ] && echo "Created" || echo "Skipped")"
log "✅ Migrations: $([ "$PENDING_MIGRATIONS" -gt 0 ] && echo "$PENDING_MIGRATIONS applied" || echo "Up to date")"
log "✅ Table optimization: Completed"
log "✅ Cache optimization: Completed"
log "✅ Database maintenance: Completed"
log "📝 Log file: $LOG_FILE"
log ""

success "🎉 Database migration and optimization completed successfully!"

# Performance tips
log "💡 Performance Tips:"
log "- Consider setting up database connection pooling"
log "- Monitor slow query log for optimization opportunities"
log "- Regular maintenance should be run weekly"
log "- Consider Redis for session and cache storage"
log "- Monitor database size and plan for scaling"

log "🏁 Script completed at $(date)"