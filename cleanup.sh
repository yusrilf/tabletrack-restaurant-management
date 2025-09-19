#!/bin/bash

# Script untuk membersihkan file cache dan sementara yang mungkin menyebabkan error 500
# Created: $(date)

# Lokasi aplikasi
APP_PATH="$(cd "$(dirname "$0")/.." && pwd)"
echo "Lokasi aplikasi: $APP_PATH"

# Fungsi untuk menampilkan pesan
function show_message() {
    echo "====================================================="
    echo "$1"
    echo "====================================================="
}

# Periksa apakah script dijalankan dengan hak akses yang cukup
if [ "$(id -u)" != "0" ]; then
    show_message "PERINGATAN: Script ini sebaiknya dijalankan dengan sudo untuk memastikan izin yang cukup"
    echo "Lanjutkan tanpa sudo? (y/n)"
    read -r answer
    if [ "$answer" != "y" ]; then
        exit 1
    fi
fi

# Bersihkan file cache Laravel
show_message "Membersihkan cache Laravel..."
cd "$APP_PATH" || { echo "Gagal mengakses direktori aplikasi"; exit 1; }

if [ -f "artisan" ]; then
    php artisan cache:clear
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    php artisan optimize:clear
    echo "Cache Laravel berhasil dibersihkan"
else
    echo "File artisan tidak ditemukan. Pastikan Anda berada di direktori Laravel yang benar."
fi

# Bersihkan file cache di storage
show_message "Membersihkan file cache di storage..."
CACHE_DIRS=(
    "storage/framework/cache"
    "storage/framework/sessions"
    "storage/framework/views"
    "bootstrap/cache"
)

for dir in "${CACHE_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        echo "Membersihkan $dir..."
        rm -rf "$dir"/*
        echo "Membuat kembali direktori $dir..."
        mkdir -p "$dir"
        echo "Mengatur izin untuk $dir..."
        chmod -R 775 "$dir"
    else
        echo "Direktori $dir tidak ditemukan, membuat direktori..."
        mkdir -p "$dir"
        chmod -R 775 "$dir"
    fi
done

# Periksa dan perbaiki izin file
show_message "Memperbaiki izin file dan direktori..."
DIRS_TO_FIX=(
    "storage"
    "bootstrap/cache"
    "public/user-uploads"
)

for dir in "${DIRS_TO_FIX[@]}"; do
    if [ -d "$dir" ]; then
        echo "Mengatur izin untuk $dir..."
        chmod -R 775 "$dir"
        echo "Mengatur kepemilikan untuk $dir..."
        # Gunakan user dan group yang sesuai dengan server web Anda
        # Contoh untuk Apache: www-data, untuk Nginx: nginx
        # chown -R www-data:www-data "$dir"
        echo "Izin untuk $dir telah diperbaiki"
    else
        echo "Direktori $dir tidak ditemukan, membuat direktori..."
        mkdir -p "$dir"
        chmod -R 775 "$dir"
    fi
done

# Periksa file .htaccess
show_message "Memeriksa file .htaccess..."
HTACCESS_FILE="$APP_PATH/public/.htaccess"

if [ -f "$HTACCESS_FILE" ]; then
    echo "File .htaccess ditemukan"
    echo "Membuat backup .htaccess..."
    cp "$HTACCESS_FILE" "$HTACCESS_FILE.bak"
    echo "Backup .htaccess disimpan di $HTACCESS_FILE.bak"
    
    # Membuat file .htaccess standar Laravel jika diperlukan
    echo "Membuat file .htaccess standar Laravel..."
    cat > "$HTACCESS_FILE" << 'EOL'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
EOL
    echo "File .htaccess standar Laravel telah dibuat"
else
    echo "File .htaccess tidak ditemukan, membuat file baru..."
    cat > "$HTACCESS_FILE" << 'EOL'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
EOL
    echo "File .htaccess baru telah dibuat"
fi

# Periksa file .env
show_message "Memeriksa file .env..."
ENV_FILE="$APP_PATH/.env"

if [ -f "$ENV_FILE" ]; then
    echo "File .env ditemukan"
    echo "Membuat backup .env..."
    cp "$ENV_FILE" "$ENV_FILE.bak"
    echo "Backup .env disimpan di $ENV_FILE.bak"
    
    # Pastikan APP_DEBUG diatur dengan benar
    if grep -q "APP_DEBUG=true" "$ENV_FILE"; then
        echo "APP_DEBUG diatur ke true, mengubah ke false untuk produksi..."
        sed -i '' 's/APP_DEBUG=true/APP_DEBUG=false/g' "$ENV_FILE" || sed -i 's/APP_DEBUG=true/APP_DEBUG=false/g' "$ENV_FILE"
    fi
else
    echo "File .env tidak ditemukan. Pastikan file .env ada dan dikonfigurasi dengan benar."
fi

# Buat symlink storage jika belum ada
show_message "Memeriksa symlink storage..."
if [ ! -L "$APP_PATH/public/storage" ]; then
    echo "Symlink storage tidak ditemukan, membuat symlink baru..."
    cd "$APP_PATH" || { echo "Gagal mengakses direktori aplikasi"; exit 1; }
    php artisan storage:link
    echo "Symlink storage telah dibuat"
else
    echo "Symlink storage sudah ada"
fi

show_message "Proses pembersihan selesai!"
echo "Jika masih mengalami error 500, silakan periksa log error di:"
echo "- $APP_PATH/storage/logs/laravel.log"
echo "- Log server web (Apache/Nginx)"

echo ""
echo "Untuk mengaktifkan mode debug sementara, edit file .env dan ubah APP_DEBUG=false menjadi APP_DEBUG=true"
echo "Setelah melihat pesan error, jangan lupa ubah kembali ke APP_DEBUG=false untuk keamanan"