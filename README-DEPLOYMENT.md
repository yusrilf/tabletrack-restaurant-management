# TableTrack Manual Deployment Guide

## 📋 Overview

This guide explains how to set up manual deployment for TableTrack to your cPanel hosting.

## 🚀 Quick Start

1. **Push your code to GitHub**
2. **Clone or pull the `cpanel-deploy` branch on your cPanel server**
3. **Run the deployment script manually**

## 📁 Files Created

- `deploy.sh` - Server-side deployment script
- `.env.production.example` - Production environment template

## ⚙️ GitHub Repository Setup

### 1. Create GitHub Repository

```bash
# Initialize git repository (if not already done)
cd /path/to/tabletrack
git init

# Add all files
git add .

# Create initial commit
git commit -m "Initial TableTrack commit"

# Add GitHub remote
git remote add origin https://github.com/yourusername/tabletrack.git

# Push to GitHub
git branch -M main
git push -u origin main

# Create deployment branch
git checkout -b cpanel-deploy
git push -u origin cpanel-deploy
```

### 2. cPanel Git Setup

1. Log in to your cPanel account
2. Navigate to Git Version Control
3. Create a new repository or clone the existing one
4. Use the `cpanel-deploy` branch for deployment

## 🔧 cPanel Server Setup

### 1. PHP Configuration

Ensure your cPanel hosting has:
- **PHP 8.2+** enabled
- Required PHP extensions:
  - BCMath
  - Ctype
  - Fileinfo
  - JSON
  - Mbstring
  - OpenSSL
  - PDO
  - Tokenizer
  - XML
  - GD
  - Zip
  - Curl

### 2. Database Setup

1. Create MySQL database in cPanel
2. Create database user with full privileges
3. Note down database credentials for `.env` file

### 3. File Permissions

The deployment script will handle permissions, but ensure your hosting allows:
- Write permissions to `storage/` directory
- Write permissions to `bootstrap/cache/` directory
- Ability to create symbolic links

## 🚀 Deployment Process

### Manual Deployment

To deploy your application:

1. **On your local machine:**
   ```bash
   # Make sure you're on the cpanel-deploy branch
   git checkout cpanel-deploy
   
   # Pull latest changes from main if needed
   git merge main
   
   # Push changes to GitHub
   git push origin cpanel-deploy
   ```

2. **On your cPanel server:**
   ```bash
   # Navigate to your website directory
   cd /home/yourusername/public_html
   
   # Pull the latest changes from the cpanel-deploy branch
   git pull origin cpanel-deploy
   
   # Make the deployment script executable
   chmod +x deploy.sh
   
   # Run the deployment script
   ./deploy.sh
   ```

### Composer Installation

If Composer is not available on your cPanel server, you need to install it:

1. **Install Composer:**
   ```bash
   # Download Composer installer
   curl -sS https://getcomposer.org/installer | php
   
   # Move to global location (optional)
   mv composer.phar composer
   chmod +x composer
   ```

2. **Run Composer manually if needed:**
   ```bash
   # Using local composer.phar
   php composer.phar install --optimize-autoloader --no-dev
   
   # Or if moved to global location
   ./composer install --optimize-autoloader --no-dev
   ```

> **IMPORTANT:** The error `Failed opening required '/home/username/public_html/vendor/autoload.php'` indicates that Composer dependencies are not installed. Always run Composer after deployment to ensure all required packages are available.
   
   # Pull latest changes
   git pull origin cpanel-deploy
   
   # Run deployment script
   bash deploy.sh
   ```

3. **Verify deployment:**
   - Check your website is functioning correctly
   - Verify database migrations were applied
   - Check logs for any errors

### Manual Deployment

You can also trigger deployment manually:
1. Go to your GitHub repository
2. Click "Actions" tab
3. Select "Deploy to cPanel" workflow
4. Click "Run workflow"

## 📋 Deployment Steps

The automated deployment process:

1. **Testing Phase**
   - Sets up PHP 8.2 environment
   - Installs dependencies
   - Runs database migrations on test database
   - Executes PHPUnit tests

2. **Build Phase**
   - Installs production dependencies
   - Builds frontend assets with Vite
   - Creates optimized deployment package

3. **Deploy Phase**
   - Uploads files via FTP to cPanel
   - Runs post-deployment script
   - Clears and caches configuration
   - Runs database migrations
   - Sets proper file permissions

## 🔧 Post-Deployment Configuration

After successful deployment:

### 1. Environment Configuration

1. Access your cPanel File Manager
2. Navigate to your web directory
3. Copy `.env.production.example` to `.env`
4. Update `.env` with your production settings:

```env
APP_URL=https://yourdomain.com
DB_HOST=localhost
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
MAIL_HOST=your-smtp-host
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-email-password
```

### 2. Cron Jobs Setup

Add this cron job in cPanel:

```bash
* * * * * cd /home/yourusername/public_html && php artisan schedule:run >> /dev/null 2>&1
```

### 3. SSL Certificate

Ensure SSL certificate is installed and configured for your domain.

## 🔍 Troubleshooting

### Common Issues

#### 1. FTP Upload Fails
- Check FTP credentials in GitHub secrets
- Verify FTP hostname and path
- Ensure FTP is enabled in cPanel

#### 2. SSH Commands Fail
- Verify SSH is enabled on your hosting
- Check SSH credentials and port
- Some shared hosting providers don't allow SSH

#### 3. Permission Errors
- The deployment script handles permissions
- If issues persist, manually set permissions:
  ```bash
  chmod -R 755 storage
  chmod -R 755 bootstrap/cache
  ```

#### 4. Database Connection Errors
- Verify database credentials in `.env`
- Ensure database exists and user has privileges
- Check if database server allows connections

#### 5. Composer Dependencies
- If composer is not available on server, upload `vendor/` directory
- Consider using shared hosting with composer support

### Debug Deployment

1. Check GitHub Actions logs:
   - Go to repository → Actions tab
   - Click on failed workflow
   - Review step-by-step logs

2. Check server logs:
   - Access cPanel Error Logs
   - Check Laravel logs in `storage/logs/`

## 📊 Monitoring

### GitHub Actions Status

Monitor deployment status:
- Green checkmark = Successful deployment
- Red X = Failed deployment
- Yellow circle = In progress

### Application Health

After deployment, verify:
- [ ] Website loads correctly
- [ ] Database connection works
- [ ] File uploads work
- [ ] Email notifications work
- [ ] Cron jobs are running

## 🔄 Rollback Process

If deployment fails:

1. **Automatic Rollback**
   - The deployment script creates backups
   - Access backup in `~/backups/` directory

2. **Manual Rollback**
   ```bash
   cd ~/backups
   tar -xzf tabletrack_backup_YYYYMMDD_HHMMSS.tar.gz -C ~/public_html
   ```

## 🛡️ Security Considerations

- Never commit `.env` files to repository
- Use strong passwords for FTP/SSH
- Regularly update dependencies
- Monitor server logs for suspicious activity
- Keep backups of database and files

## 📈 Performance Optimization

After deployment:
- Enable OPcache in cPanel
- Use Redis for caching (if available)
- Configure CDN for static assets
- Monitor database performance

## 🆘 Support

If you encounter issues:
1. Check this documentation
2. Review GitHub Actions logs
3. Check cPanel error logs
4. Verify server requirements
5. Contact your hosting provider for server-specific issues

## 📝 Changelog

Keep track of deployments:
- Use semantic versioning for releases
- Tag releases in GitHub
- Document changes in commit messages
- Monitor application performance after deployments