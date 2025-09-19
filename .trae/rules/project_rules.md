# TableTrack Project Rules & Guidelines

## 📋 Table of Contents
1. [Project Overview](#project-overview)
2. [Technology Stack](#technology-stack)
3. [Project Structure](#project-structure)
4. [Development Guidelines](#development-guidelines)
5. [Code Standards](#code-standards)
6. [Database Guidelines](#database-guidelines)
7. [Security Rules](#security-rules)
8. [API Guidelines](#api-guidelines)
9. [Testing Requirements](#testing-requirements)
10. [Deployment Rules](#deployment-rules)
11. [Module Development](#module-development)
12. [Performance Guidelines](#performance-guidelines)

---

## 🎯 Project Overview

**TableTrack** adalah aplikasi manajemen restoran berbasis Laravel yang menyediakan solusi lengkap untuk:
- Manajemen menu dan item makanan
- Sistem POS (Point of Sale)
- Manajemen pesanan dan reservasi
- Sistem pembayaran multi-gateway
- Laporan dan analitik
- Manajemen staff dan customer
- QR Code menu
- Kitchen Order Tickets (KOT)

### Core Business Models
- **Restaurant**: Model utama untuk restoran
- **Order**: Manajemen pesanan
- **Customer**: Data pelanggan
- **MenuItem**: Item menu makanan
- **Table**: Manajemen meja
- **User**: Staff dan admin restoran

---

## 🛠 Technology Stack

### Backend Framework
- **Laravel 12.x** - Framework PHP utama
- **PHP 8.2+** - Versi PHP minimum
- **MySQL** - Database utama
- **Livewire 3.5** - Komponen reactive UI

### Frontend Technologies
- **Tailwind CSS 3.4** - Framework CSS
- **Alpine.js** (via Livewire)
- **Vite 5.0** - Build tool
- **Flowbite 2.4** - UI components
- **ApexCharts 3.49** - Charting library
- **SweetAlert2 11.12** - Alert dialogs

### Key Dependencies
- **Laravel Jetstream 5.1** - Authentication scaffolding
- **Spatie Laravel Permission 6.9** - Role & permission management
- **Laravel Cashier 15.4** - Subscription billing
- **Intervention Image 3.11** - Image processing
- **DomPDF 3.0** - PDF generation
- **Endroid QR Code 5.0** - QR code generation

---

## 📁 Project Structure

```
tabletrack-v1.2.43/script/
├── app/
│   ├── Actions/           # Jetstream actions
│   ├── Console/           # Artisan commands
│   ├── Enums/            # PHP enums (OrderStatus, PackageType, etc.)
│   ├── Events/           # Event classes
│   ├── Exports/          # Excel export classes
│   ├── Helper/           # Helper functions
│   ├── Http/
│   │   ├── Controllers/  # HTTP controllers
│   │   └── Middleware/   # Custom middleware
│   ├── Imports/          # Excel import classes
│   ├── Jobs/             # Queue jobs
│   ├── Listeners/        # Event listeners
│   ├── Livewire/         # Livewire components
│   ├── Models/           # Eloquent models
│   ├── Notifications/    # Notification classes
│   ├── Observers/        # Model observers
│   ├── Providers/        # Service providers
│   ├── Scopes/           # Query scopes
│   ├── Traits/           # Reusable traits
│   └── View/             # View composers
├── config/               # Configuration files
├── database/             # Migrations, seeders, factories
├── lang/                 # Multi-language files (20+ languages)
├── Modules/              # Custom modules (modular architecture)
├── public/               # Public assets
├── resources/            # Views, CSS, JS
├── routes/               # Route definitions
├── storage/              # File storage
└── vendor/               # Composer dependencies
```

---

## 🔧 Development Guidelines

### 1. Environment Setup
```bash
# Minimum requirements
- PHP 8.2+
- Composer 2.x
- Node.js 18+
- MySQL 8.0+
- Redis (optional, for caching)
```

### 2. Local Development
```bash
# Setup commands
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run dev
```

### 3. Development Environment Variables
```env
APP_NAME=TableTrack
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tabletrack
DB_USERNAME=root
DB_PASSWORD=

# Queue (for production)
QUEUE_CONNECTION=redis
```

---

## 📝 Code Standards

### 1. PHP Coding Standards
- **PSR-12** compliance mandatory
- **Type hints** required for all methods
- **Docstrings** required for all classes and methods
- **Error handling** with try-catch blocks
- **Logging** for debugging and monitoring

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

/**
 * Restaurant model for managing restaurant data
 * 
 * @property int $id
 * @property string $name
 * @property string $email
 */
class Restaurant extends Model
{
    protected $fillable = ['name', 'email', 'phone'];
    
    /**
     * Get all orders for this restaurant
     * 
     * @return HasMany<Order>
     */
    public function orders(): HasMany
    {
        try {
            return $this->hasMany(Order::class);
        } catch (\Exception $e) {
            Log::error('Error fetching restaurant orders: ' . $e->getMessage());
            throw $e;
        }
    }
}
```

### 2. File Organization Rules
- **Maximum 200 lines per file** - Refactor if exceeded
- **Single responsibility principle**
- **Descriptive naming conventions**
- **Consistent indentation** (4 spaces)

### 3. Livewire Component Standards
```php
<?php

namespace App\Livewire\Order;

use Livewire\Component;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * Order management component
 */
class OrderManager extends Component
{
    public Order $order;
    public string $status = '';
    
    protected $rules = [
        'order.total' => 'required|numeric|min:0',
        'status' => 'required|string|in:pending,confirmed,completed'
    ];
    
    /**
     * Update order status with error handling
     */
    public function updateStatus(): void
    {
        try {
            $this->validate();
            
            $this->order->update(['status' => $this->status]);
            
            $this->dispatch('order-updated', $this->order->id);
            
            Log::info('Order status updated', [
                'order_id' => $this->order->id,
                'new_status' => $this->status
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update order status: ' . $e->getMessage());
            $this->addError('status', 'Failed to update order status');
        }
    }
    
    public function render()
    {
        return view('livewire.order.order-manager');
    }
}
```

---

## 🗄 Database Guidelines

### 1. Migration Standards
- **Descriptive migration names**
- **Foreign key constraints**
- **Proper indexing**
- **Rollback support**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('table_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('total', 10, 2);
            $table->enum('status', ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled']);
            $table->timestamp('order_date');
            $table->timestamps();
            
            // Indexes
            $table->index(['restaurant_id', 'status']);
            $table->index(['customer_id', 'created_at']);
            $table->index('order_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
```

### 2. Model Relationships
- **Explicit relationship definitions**
- **Proper foreign key naming**
- **Cascade delete rules**

### 3. Query Optimization
- **Eager loading** untuk relasi
- **Database indexes** untuk kolom yang sering di-query
- **Query scopes** untuk filter umum

---

## 🔒 Security Rules

### 1. Authentication & Authorization
- **Multi-tenant architecture** dengan restaurant_id scope
- **Role-based permissions** menggunakan Spatie Permission
- **Middleware protection** untuk semua routes

```php
// Middleware stack example
Route::middleware(['auth', 'verified', 'restaurant.access'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

### 2. Data Validation
- **Form Request validation** untuk semua input
- **CSRF protection** enabled
- **XSS protection** dengan proper escaping

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('create_orders');
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'nullable|exists:customers,id',
            'table_id' => 'nullable|exists:tables,id',
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.quantity' => 'required|integer|min:1',
        ];
    }
}
```

### 3. API Security
- **API key authentication** untuk desktop app
- **Rate limiting** untuk API endpoints
- **CORS configuration** yang proper

### 4. Data Protection
- **Sensitive data encryption**
- **No secrets in code** - gunakan environment variables
- **Audit logging** untuk perubahan penting

---

## 🌐 API Guidelines

### 1. RESTful API Design
- **Consistent naming conventions**
- **Proper HTTP status codes**
- **JSON response format**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    /**
     * Get order details
     */
    public function show(Order $order): JsonResponse
    {
        try {
            $order->load(['items.menuItem', 'customer', 'table']);
            
            return response()->json([
                'success' => true,
                'data' => $order,
                'message' => 'Order retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve order: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve order'
            ], 500);
        }
    }
}
```

### 2. Print Job API
- **Desktop app integration** untuk thermal printing
- **Job queue management**
- **Status tracking**

---

## 🧪 Testing Requirements

### 1. Unit Tests
```php
<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test order total calculation
     */
    public function test_order_calculates_total_correctly(): void
    {
        $restaurant = Restaurant::factory()->create();
        $order = Order::factory()->for($restaurant)->create();
        
        // Add test logic here
        $this->assertIsNumeric($order->total);
        $this->assertGreaterThan(0, $order->total);
    }
}
```

### 2. Feature Tests
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test order creation flow
     */
    public function test_user_can_create_order(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->for($restaurant)->create();
        
        $response = $this->actingAs($user)
            ->post('/orders', [
                'customer_id' => null,
                'table_id' => 1,
                'items' => [
                    ['menu_item_id' => 1, 'quantity' => 2]
                ]
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'restaurant_id' => $restaurant->id
        ]);
    }
}
```

### 3. Integration Tests
- **Payment gateway integration**
- **Email notification testing**
- **Print job processing**

---

## 🚀 Deployment Rules

### 1. Environment Configuration
```env
# Production settings
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Security
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=yourdomain.com

# Performance
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

### 2. Build Process
```bash
# Production build
composer install --optimize-autoloader --no-dev
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 3. Server Requirements
- **PHP 8.2+** dengan extensions: BCMath, Ctype, Fileinfo, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML
- **MySQL 8.0+** atau MariaDB 10.3+
- **Redis** untuk caching dan sessions
- **SSL certificate** mandatory
- **Regular backups** automated

---

## 🧩 Module Development

### 1. Modular Architecture
- **Nwidart Laravel Modules** untuk struktur modular
- **Independent module development**
- **Module-specific routes, views, dan controllers**

### 2. Module Structure
```
Modules/
├── ModuleName/
│   ├── Config/
│   ├── Console/
│   ├── Database/
│   ├── Entities/
│   ├── Http/
│   ├── Providers/
│   ├── Resources/
│   ├── Routes/
│   └── Tests/
```

### 3. Module Guidelines
- **Self-contained functionality**
- **Proper dependency injection**
- **Module-specific migrations**
- **Independent testing**

---

## ⚡ Performance Guidelines

### 1. Database Optimization
- **Query optimization** dengan proper indexing
- **Eager loading** untuk relasi
- **Database connection pooling**
- **Query caching** untuk data yang jarang berubah

### 2. Caching Strategy
```php
// Cache configuration
'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
    ],
],

// Cache usage example
Cache::remember('restaurant.menu.' . $restaurantId, 3600, function () use ($restaurantId) {
    return MenuItem::where('restaurant_id', $restaurantId)
        ->with(['category', 'variations'])
        ->get();
});
```

### 3. Asset Optimization
- **Vite bundling** untuk CSS/JS
- **Image optimization** dengan Intervention Image
- **CDN usage** untuk static assets
- **Lazy loading** untuk images

### 4. Queue Management
```php
// Queue configuration
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
    ],
],
```

---

## 📊 Monitoring & Logging

### 1. Application Monitoring
- **Error tracking** dengan proper logging
- **Performance monitoring**
- **Database query monitoring**
- **Queue job monitoring**

### 2. Logging Standards
```php
// Logging example
Log::info('Order created successfully', [
    'order_id' => $order->id,
    'restaurant_id' => $order->restaurant_id,
    'total' => $order->total,
    'user_id' => auth()->id()
]);

Log::error('Payment processing failed', [
    'order_id' => $order->id,
    'error' => $exception->getMessage(),
    'trace' => $exception->getTraceAsString()
]);
```

---

## 🔄 Maintenance Guidelines

### 1. Regular Updates
- **Laravel framework updates** quarterly
- **Dependency updates** monthly
- **Security patches** immediately
- **Database maintenance** weekly

### 2. Backup Strategy
- **Daily database backups**
- **File storage backups**
- **Configuration backups**
- **Automated backup verification**

### 3. Health Checks
- **Application health monitoring**
- **Database connection checks**
- **Queue worker monitoring**
- **Storage space monitoring**

---

## 📞 Support & Documentation

### 1. Code Documentation
- **Inline comments** untuk logic kompleks
- **API documentation** dengan OpenAPI/Swagger
- **Database schema documentation**
- **Deployment documentation**

### 2. User Documentation
- **Admin user guides**
- **API integration guides**
- **Troubleshooting guides**
- **FAQ documentation**

---

## 🎯 Best Practices Summary

1. **Follow Laravel conventions** dan best practices
2. **Implement proper error handling** di semua layer
3. **Use type hints dan docstrings** untuk semua methods
4. **Write comprehensive tests** untuk semua features
5. **Optimize database queries** dan gunakan caching
6. **Implement proper security measures** di semua endpoints
7. **Follow modular architecture** untuk maintainability
8. **Monitor application performance** secara regular
9. **Keep dependencies updated** dan secure
10. **Document everything** untuk future maintenance

---

*Dokumen ini harus diupdate secara berkala seiring dengan perkembangan project dan perubahan requirements.*