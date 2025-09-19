# TableTrack CI/CD Deployment Guide

## 📋 Overview

This guide explains how to set up Continuous Integration and Continuous Deployment (CI/CD) for TableTrack using GitHub Actions to deploy to your cPanel hosting.

## 🚀 Quick Start

1. **Push your code to GitHub**
2. **Configure GitHub Secrets**
3. **Trigger deployment by pushing to main/master branch**

## 📁 Files Created

- `.github/workflows/deploy.yml` - GitHub Actions workflow
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
```

### 2. Configure GitHub Secrets

Go to your GitHub repository → Settings → Secrets and variables → Actions

Add the following secrets:

#### FTP Deployment Secrets
- `CPANEL_FTP_HOST` - Your cPanel FTP hostname (e.g., ftp.yourdomain.com)
- `CPANEL_FTP_USERNAME` - Your cPanel FTP username
- `CPANEL_FTP_PASSWORD` - Your cPanel FTP password
- `CPANEL_DEPLOY_PATH` - Path to your web directory (e.g., /public_html/)

#### SSH Deployment Secrets (Optional - for post-deployment commands)
- `CPANEL_SSH_HOST` - Your server SSH hostname
- `CPANEL_SSH_USERNAME` - Your SSH username
- `CPANEL_SSH_PASSWORD` - Your SSH password
- `CPANEL_SSH_PORT` - SSH port (usually 22)

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

### Automatic Deployment

The deployment triggers automatically when you:
1. Push to `main` or `master` branch
2. Create a pull request to these branches
3. Manually trigger via GitHub Actions tab

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