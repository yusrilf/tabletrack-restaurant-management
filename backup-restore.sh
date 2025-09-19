#!/bin/bash

# TableTrack Backup and Restore Script
# Handles database and file backups/restores safely

set -e

# Configuration
PROJECT_PATH="${1:-$(pwd)}"
BACKUP_PATH="${2:-$PROJECT_PATH/storage/backups}"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
RETENTION_DAYS=30

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "$1"
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

# Show usage
show_usage() {
    echo "TableTrack Backup and Restore Script"
    echo ""
    echo "Usage: $0 [COMMAND] [OPTIONS]"
    echo ""
    echo "Commands:"
    echo "  backup-db          Create database backup"
    echo "  backup-files       Create files backup"
    echo "  backup-full        Create full backup (database + files)"
    echo "  restore-db [FILE]  Restore database from backup"
    echo "  list-backups       List available backups"
    echo "  cleanup            Remove old backups"
    echo ""
    echo "Options:"
    echo "  --path PATH        Project path (default: current directory)"
    echo "  --backup-path PATH Backup directory (default: storage/backups)"
    echo "  --retention DAYS   Backup retention days (default: 30)"
    echo ""
    echo "Examples:"
    echo "  $0 backup-full"
    echo "  $0 restore-db storage/backups/database_backup_20240101_120000.sql"
    echo "  $0 cleanup --retention 7"
}

# Get database configuration
get_db_config() {
    if [ ! -f "artisan" ]; then
        error_exit "artisan file not found. Please run this script from the Laravel project root."
    fi

    DB_HOST=$(php artisan tinker --execute="echo config('database.connections.mysql.host');" 2>/dev/null | tail -1)
    DB_DATABASE=$(php artisan tinker --execute="echo config('database.connections.mysql.database');" 2>/dev/null | tail -1)
    DB_USERNAME=$(php artisan tinker --execute="echo config('database.connections.mysql.username');" 2>/dev/null | tail -1)
    DB_PASSWORD=$(php artisan tinker --execute="echo config('database.connections.mysql.password');" 2>/dev/null | tail -1)
    DB_PORT=$(php artisan tinker --execute="echo config('database.connections.mysql.port');" 2>/dev/null | tail -1)
}

# Create database backup
backup_database() {
    info "Creating database backup..."
    
    get_db_config
    mkdir -p "$BACKUP_PATH"
    
    BACKUP_FILE="$BACKUP_PATH/database_backup_$TIMESTAMP.sql"
    
    if command -v mysqldump &> /dev/null; then
        info "Using mysqldump for backup..."
        
        # Create backup with structure and data
        if mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" -p"$DB_PASSWORD" \
            --single-transaction \
            --routines \
            --triggers \
            --add-drop-table \
            --add-locks \
            --extended-insert \
            "$DB_DATABASE" > "$BACKUP_FILE" 2>/dev/null; then
            
            # Compress backup
            gzip "$BACKUP_FILE"
            BACKUP_FILE="$BACKUP_FILE.gz"
            
            success "Database backup created: $BACKUP_FILE"
            
            # Show backup size
            BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
            info "Backup size: $BACKUP_SIZE"
            
            return 0
        else
            error_exit "Database backup failed"
        fi
    else
        error_exit "mysqldump not available"
    fi
}

# Create files backup
backup_files() {
    info "Creating files backup..."
    
    mkdir -p "$BACKUP_PATH"
    
    BACKUP_FILE="$BACKUP_PATH/files_backup_$TIMESTAMP.tar.gz"
    
    # Files and directories to backup
    BACKUP_ITEMS=(
        "public/user-uploads"
        "public/qrcodes"
        "storage/app/public"
        ".env"
    )
    
    # Create tar archive
    tar -czf "$BACKUP_FILE" \
        --exclude="storage/logs/*" \
        --exclude="storage/framework/cache/*" \
        --exclude="storage/framework/sessions/*" \
        --exclude="storage/framework/views/*" \
        "${BACKUP_ITEMS[@]}" 2>/dev/null || warning "Some files may not have been backed up"
    
    if [ -f "$BACKUP_FILE" ]; then
        success "Files backup created: $BACKUP_FILE"
        
        # Show backup size
        BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
        info "Backup size: $BACKUP_SIZE"
    else
        error_exit "Files backup failed"
    fi
}

# Create full backup
backup_full() {
    info "Creating full backup (database + files)..."
    
    # Create database backup
    backup_database
    
    # Create files backup
    backup_files
    
    success "Full backup completed"
}

# Restore database
restore_database() {
    local backup_file="$1"
    
    if [ -z "$backup_file" ]; then
        error_exit "Please specify backup file to restore"
    fi
    
    if [ ! -f "$backup_file" ]; then
        error_exit "Backup file not found: $backup_file"
    fi
    
    info "Restoring database from: $backup_file"
    
    # Confirm restore
    warning "This will overwrite the current database!"
    read -p "Are you sure you want to continue? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        info "Restore cancelled"
        exit 0
    fi
    
    get_db_config
    
    # Create backup of current database before restore
    info "Creating backup of current database before restore..."
    CURRENT_BACKUP="$BACKUP_PATH/pre_restore_backup_$TIMESTAMP.sql.gz"
    backup_database
    
    # Restore database
    if [[ "$backup_file" == *.gz ]]; then
        info "Decompressing and restoring backup..."
        if gunzip -c "$backup_file" | mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE"; then
            success "Database restored successfully"
        else
            error_exit "Database restore failed"
        fi
    else
        info "Restoring backup..."
        if mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < "$backup_file"; then
            success "Database restored successfully"
        else
            error_exit "Database restore failed"
        fi
    fi
    
    # Run migrations to ensure database is up to date
    info "Running migrations to ensure database is current..."
    php artisan migrate --force
    
    success "Database restore completed"
}

# List available backups
list_backups() {
    info "Available backups in $BACKUP_PATH:"
    
    if [ ! -d "$BACKUP_PATH" ]; then
        warning "Backup directory does not exist: $BACKUP_PATH"
        return
    fi
    
    echo ""
    echo "Database Backups:"
    echo "=================="
    ls -lah "$BACKUP_PATH"/database_backup_*.sql* 2>/dev/null | while read -r line; do
        echo "$line"
    done || echo "No database backups found"
    
    echo ""
    echo "File Backups:"
    echo "============="
    ls -lah "$BACKUP_PATH"/files_backup_*.tar.gz 2>/dev/null | while read -r line; do
        echo "$line"
    done || echo "No file backups found"
    
    echo ""
    
    # Show total backup size
    if [ -d "$BACKUP_PATH" ]; then
        TOTAL_SIZE=$(du -sh "$BACKUP_PATH" 2>/dev/null | cut -f1)
        info "Total backup size: $TOTAL_SIZE"
    fi
}

# Cleanup old backups
cleanup_backups() {
    info "Cleaning up backups older than $RETENTION_DAYS days..."
    
    if [ ! -d "$BACKUP_PATH" ]; then
        warning "Backup directory does not exist: $BACKUP_PATH"
        return
    fi
    
    # Find and delete old backups
    DELETED_COUNT=0
    
    # Database backups
    find "$BACKUP_PATH" -name "database_backup_*.sql*" -type f -mtime +$RETENTION_DAYS -print0 | while IFS= read -r -d '' file; do
        rm -f "$file"
        info "Deleted: $(basename "$file")"
        ((DELETED_COUNT++))
    done
    
    # File backups
    find "$BACKUP_PATH" -name "files_backup_*.tar.gz" -type f -mtime +$RETENTION_DAYS -print0 | while IFS= read -r -d '' file; do
        rm -f "$file"
        info "Deleted: $(basename "$file")"
        ((DELETED_COUNT++))
    done
    
    success "Cleanup completed. Deleted $DELETED_COUNT old backup files"
}

# Parse command line arguments
COMMAND=""
while [[ $# -gt 0 ]]; do
    case $1 in
        backup-db|backup-files|backup-full|restore-db|list-backups|cleanup)
            COMMAND="$1"
            shift
            ;;
        --path)
            PROJECT_PATH="$2"
            shift 2
            ;;
        --backup-path)
            BACKUP_PATH="$2"
            shift 2
            ;;
        --retention)
            RETENTION_DAYS="$2"
            shift 2
            ;;
        -h|--help)
            show_usage
            exit 0
            ;;
        *)
            if [ "$COMMAND" = "restore-db" ] && [ -z "$RESTORE_FILE" ]; then
                RESTORE_FILE="$1"
            fi
            shift
            ;;
    esac
done

# Change to project directory
cd "$PROJECT_PATH"

# Execute command
case $COMMAND in
    backup-db)
        backup_database
        ;;
    backup-files)
        backup_files
        ;;
    backup-full)
        backup_full
        ;;
    restore-db)
        restore_database "$RESTORE_FILE"
        ;;
    list-backups)
        list_backups
        ;;
    cleanup)
        cleanup_backups
        ;;
    "")
        error_exit "No command specified. Use --help for usage information."
        ;;
    *)
        error_exit "Unknown command: $COMMAND. Use --help for usage information."
        ;;
esac