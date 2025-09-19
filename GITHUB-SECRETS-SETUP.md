# GitHub Secrets Setup Guide

## 📋 Overview

This guide explains how to configure GitHub Secrets for automated deployment of TableTrack to your cPanel hosting.

## 🔐 Required Secrets

### FTP Deployment Secrets (Required)

| Secret Name | Description | Example |
|-------------|-------------|---------|
| `CPANEL_FTP_HOST` | Your cPanel FTP hostname | `ftp.yourdomain.com` |
| `CPANEL_FTP_USERNAME` | Your cPanel FTP username | `username@yourdomain.com` |
| `CPANEL_FTP_PASSWORD` | Your cPanel FTP password | `your-ftp-password` |
| `CPANEL_DEPLOY_PATH` | Path to your web directory | `/public_html/` |

### SSH Deployment Secrets (Optional)

| Secret Name | Description | Example |
|-------------|-------------|---------|
| `CPANEL_SSH_HOST` | Your server SSH hostname | `yourdomain.com` |
| `CPANEL_SSH_USERNAME` | Your SSH username | `username` |
| `CPANEL_SSH_PASSWORD` | Your SSH password | `your-ssh-password` |
| `CPANEL_SSH_PORT` | SSH port number | `22` |

## 🚀 Step-by-Step Setup

### 1. Access GitHub Repository Settings

1. Go to your GitHub repository
2. Click on **Settings** tab
3. In the left sidebar, click **Secrets and variables**
4. Click **Actions**

### 2. Add FTP Secrets

Click **New repository secret** for each of the following:

#### CPANEL_FTP_HOST
- **Name**: `CPANEL_FTP_HOST`
- **Secret**: Your FTP hostname (e.g., `ftp.yourdomain.com`)
- Click **Add secret**

#### CPANEL_FTP_USERNAME
- **Name**: `CPANEL_FTP_USERNAME`
- **Secret**: Your FTP username (usually your cPanel username)
- Click **Add secret**

#### CPANEL_FTP_PASSWORD
- **Name**: `CPANEL_FTP_PASSWORD`
- **Secret**: Your FTP password
- Click **Add secret**

#### CPANEL_DEPLOY_PATH
- **Name**: `CPANEL_DEPLOY_PATH`
- **Secret**: Path to your web directory (e.g., `/public_html/`)
- Click **Add secret**

### 3. Add SSH Secrets (Optional)

If your hosting provider supports SSH:

#### CPANEL_SSH_HOST
- **Name**: `CPANEL_SSH_HOST`
- **Secret**: Your SSH hostname
- Click **Add secret**

#### CPANEL_SSH_USERNAME
- **Name**: `CPANEL_SSH_USERNAME`
- **Secret**: Your SSH username
- Click **Add secret**

#### CPANEL_SSH_PASSWORD
- **Name**: `CPANEL_SSH_PASSWORD`
- **Secret**: Your SSH password
- Click **Add secret**

#### CPANEL_SSH_PORT
- **Name**: `CPANEL_SSH_PORT`
- **Secret**: SSH port (usually `22`)
- Click **Add secret**

## 🔍 Finding Your cPanel Credentials

### FTP Information

1. **Login to cPanel**
2. **Find FTP Accounts section**
3. **Note down**:
   - FTP Server/Host
   - Username
   - Password (you set this)

### SSH Information (if available)

1. **Check with your hosting provider** if SSH is enabled
2. **SSH details are usually**:
   - Host: Your domain or server IP
   - Username: Your cPanel username
   - Port: 22 (default)
   - Password: Your cPanel password or SSH key

## 🛡️ Security Best Practices

### 1. Use Strong Passwords
- Generate strong, unique passwords for FTP/SSH
- Consider using password managers

### 2. Limit Access
- Create dedicated FTP accounts for deployment if possible
- Use least privilege principle

### 3. Regular Rotation
- Rotate passwords regularly
- Update secrets in GitHub when passwords change

### 4. Monitor Access
- Check cPanel access logs regularly
- Monitor GitHub Actions logs for failed deployments

## 🔧 Testing Your Setup

### 1. Manual FTP Test

Test your FTP credentials manually:

```bash
# Using command line FTP (macOS/Linux)
ftp your-ftp-host
# Enter username and password when prompted
# Try to navigate to your deploy path
cd /public_html/
ls
quit
```

### 2. Test Deployment

1. Make a small change to your code
2. Commit and push to main branch
3. Check GitHub Actions tab for deployment status
4. Verify files are uploaded to your server

## ❌ Troubleshooting

### Common Issues

#### 1. FTP Connection Failed
- **Check hostname**: Ensure it's correct (with or without `ftp.` prefix)
- **Check credentials**: Verify username and password
- **Check firewall**: Some networks block FTP ports

#### 2. SSH Connection Failed
- **Verify SSH is enabled**: Contact your hosting provider
- **Check port**: Default is 22, but some providers use different ports
- **Check credentials**: SSH might use different credentials than cPanel

#### 3. Permission Denied
- **Check FTP user permissions**: Ensure user can write to deploy path
- **Check path**: Verify the deploy path is correct

#### 4. Deployment Partially Fails
- **Check file permissions**: Some files might not upload due to permissions
- **Check disk space**: Ensure sufficient space on server
- **Check file size limits**: Some hosts limit file sizes

### Debug Steps

1. **Check GitHub Actions logs**:
   - Go to Actions tab in your repository
   - Click on the failed workflow
   - Expand each step to see detailed logs

2. **Test credentials locally**:
   - Use FTP client to test connection
   - Try SSH connection if using SSH deployment

3. **Check server logs**:
   - Access cPanel File Manager
   - Check error logs in cPanel

## 📞 Getting Help

### From Hosting Provider
- FTP/SSH access issues
- Server configuration problems
- File permission issues

### From GitHub
- GitHub Actions workflow issues
- Secrets management problems

### Common Hosting Providers

#### Shared Hosting
- Usually provides FTP access
- SSH might not be available
- Check provider documentation

#### VPS/Dedicated
- Usually provides both FTP and SSH
- More configuration flexibility
- May require additional setup

## ✅ Verification Checklist

After setting up secrets:

- [ ] All required FTP secrets are added
- [ ] SSH secrets are added (if using SSH)
- [ ] Secrets have correct values (no typos)
- [ ] FTP credentials work when tested manually
- [ ] SSH credentials work when tested manually (if applicable)
- [ ] Deploy path is correct and accessible
- [ ] GitHub Actions workflow runs without errors
- [ ] Files are successfully uploaded to server

## 🔄 Updating Secrets

When you need to update secrets:

1. Go to repository Settings → Secrets and variables → Actions
2. Click on the secret you want to update
3. Click **Update**
4. Enter new value
5. Click **Update secret**

## 📝 Notes

- Secrets are encrypted and cannot be viewed after creation
- Only repository collaborators with admin access can manage secrets
- Secrets are available to all workflows in the repository
- Use environment-specific secrets for different deployment targets