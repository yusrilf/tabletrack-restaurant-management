# TableTrack - Restaurant Management System

[![Laravel](https://img.shields.io/badge/Laravel-12.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Build Status](https://github.com/username/tabletrack-restaurant-management/workflows/CI/badge.svg)](https://github.com/username/tabletrack-restaurant-management/actions)

## 🎯 Overview

**TableTrack** adalah sistem manajemen restoran lengkap berbasis Laravel yang menyediakan solusi terintegrasi untuk:

- 🍽️ **Point of Sale (POS)** - Sistem kasir modern dan intuitif
- 📱 **QR Code Menu** - Menu digital dengan QR code untuk pelanggan
- 🧾 **Order Management** - Manajemen pesanan real-time
- 💳 **Multi-Payment Gateway** - Integrasi dengan berbagai metode pembayaran
- 📊 **Analytics & Reports** - Laporan penjualan dan analitik bisnis
- 👥 **Staff Management** - Manajemen karyawan dan role-based access
- 🖨️ **Kitchen Order Tickets** - Sistem tiket dapur otomatis
- 📦 **Inventory Management** - Manajemen stok dan bahan baku

## 🛠 Technology Stack

### Backend
- **Laravel 12.x** - PHP Framework
- **PHP 8.2+** - Programming Language
- **MySQL 8.0+** - Database
- **Redis** - Caching & Sessions
- **Livewire 3.5** - Reactive UI Components

### Frontend
- **Tailwind CSS 3.4** - CSS Framework
- **Alpine.js** - JavaScript Framework
- **Vite 5.0** - Build Tool
- **Flowbite 2.4** - UI Components
- **ApexCharts 3.49** - Charts & Analytics

### Key Features
- **Multi-tenant Architecture** - Support multiple restaurants
- **Real-time Updates** - Live order tracking
- **Thermal Printing** - Kitchen receipt printing
- **Mobile Responsive** - Works on all devices
- **Multi-language** - 20+ language support
- **PWA Ready** - Progressive Web App capabilities

## 🚀 Quick Start

### Prerequisites
- PHP 8.2 or higher
- Composer 2.x
- Node.js 18+ & npm
- MySQL 8.0+
- Redis (optional, recommended for production)

### Installation

1. **Clone the repository**
```bash
git clone https://github.com/username/tabletrack-restaurant-management.git
cd tabletrack-restaurant-management
```

2. **Install dependencies**
```bash
composer install
npm install
```

3. **Environment setup**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Database setup**
```bash
# Configure your database in .env file
php artisan migrate
php artisan db:seed
```

5. **Build assets**
```bash
npm run build
```

6. **Start development server**
```bash
php artisan serve
npm run dev
```

Visit `http://localhost:8000` to access the application.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
