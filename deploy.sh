#!/bin/bash

# TableTrack Deployment Script for cPanel
# This script should be run on the server after files are uploaded

set -e

echo "🚀 Starting TableTrack deployment..."

# Configuration
PROJECT_PATH="${1:-$HOME/public_html}"
BACKUP_PATH="${2:-$HOME/backups}"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

echo "📁 Project path: $PROJECT_PATH"
echo "💾 Backup path: $BACKUP_PATH"

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_PATH"

# Backup current deployment (if exists)
if [ -d "$PROJECT_PATH" ]; then
    echo "📦 Creating backup of current deployment..."
    tar -czf "$BACKUP_PATH/tabletrack_backup_$TIMESTAMP.tar.gz" -C "$PROJECT_PATH" . 2>/dev/null || echo "⚠️  Backup creation failed or directory is empty"
fi

# Navigate to project directory
cd "$PROJECT_PATH"

# Check if composer is available and install dependencies
if command -v composer &> /dev/null; then
    echo "📦 Installing/updating Composer dependencies using global composer..."
    composer install --optimize-autoloader --no-dev --no-interaction
elif [ -f "composer.phar" ]; then
    echo "📦 Installing/updating Composer dependencies using local composer.phar..."
    php composer.phar install --optimize-autoloader --no-dev --no-interaction
else
    echo "📦 Downloading and installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    echo "📦 Installing/updating Composer dependencies..."
    php composer.phar install --optimize-autoloader --no-dev --no-interaction
fi

# Set proper permissions
echo "🔐 Setting file permissions..."
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Create storage directories if they don't exist
mkdir -p storage/logs
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/app/public

# Create symlink for storage (if not exists)
if [ ! -L "public/storage" ]; then
    echo "🔗 Creating storage symlink..."
    ln -sf ../storage/app/public public/storage
fi

# Copy environment file if it doesn't exist
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        echo "📝 Creating .env file from example..."
        cp .env.example .env
        echo "⚠️  Please update .env file with your production settings!"
    else
        echo "❌ No .env.example found. Please create .env file manually."
    fi
fi

# Generate application key if not set
if ! grep -q "APP_KEY=base64:" .env 2>/dev/null; then
    echo "🔑 Generating application key..."
    php artisan key:generate --force
fi

# Clear and cache configuration
echo "🧹 Clearing and caching configuration..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Cache configuration for production
echo "⚡ Caching configuration for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations
echo "🗄️  Running database migrations..."
php artisan migrate --force

# Create required directories for uploads
mkdir -p public/qrcodes
mkdir -p public/user-uploads
chmod 775 public/qrcodes
chmod 775 public/user-uploads

# Create .htaccess files for upload directories
cat > public/qrcodes/.htaccess << 'EOF'
Options -Indexes
<Files "*.php">
    Order Deny,Allow
    Deny from all
</Files>
EOF

cat > public/user-uploads/.htaccess << 'EOF'
Options -Indexes
<Files "*.php">
    Order Deny,Allow
    Deny from all
</Files>
EOF

# Optimize for production
echo "⚡ Optimizing for production..."
php artisan optimize

# Final permission check
echo "🔐 Final permission check..."
chmod -R 755 storage
chmod -R 755 bootstrap/cache

echo "✅ Deployment completed successfully!"
echo ""
echo "📋 Post-deployment checklist:"
echo "1. Update .env file with production database credentials"
echo "2. Update .env file with production APP_URL"
echo "3. Configure mail settings in .env"
echo "4. Set up cron jobs for Laravel scheduler"
echo "5. Configure SSL certificate"
echo "6. Test the application"
echo ""
echo "🔧 Recommended cron job (add to cPanel):"
echo "* * * * * cd $PROJECT_PATH && php artisan schedule:run >> /dev/null 2>&1"
echo ""
echo "🌐 Your application should now be accessible at your domain!"