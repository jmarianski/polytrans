# PolyTrans Installation Guide

This guide covers various installation methods for the PolyTrans WordPress plugin.

## Quick Installation (Recommended)

### Method 1: WordPress Admin Upload
1. Download the latest plugin package from your source
2. Log into your WordPress admin dashboard
3. Navigate to **Plugins → Add New**
4. Click **Upload Plugin**
5. Choose the downloaded `.zip` file
6. Click **Install Now**
7. Click **Activate Plugin**

### Method 2: FTP/Manual Upload
1. Download and extract the plugin package
2. Upload the `polytrans` folder to `/wp-content/plugins/`
3. Ensure proper file permissions (755 for directories, 644 for files)
4. Log into WordPress admin
5. Navigate to **Plugins → Installed Plugins**
6. Find "PolyTrans" and click **Activate**

## Advanced Installation

### Manual Installation
For advanced users who want more control over the installation process:

1. **Download and Extract**
   ```bash
   cd /wp-content/plugins/
   wget [plugin-download-url]
   unzip polytrans.zip
   rm polytrans.zip
   ```

2. **Set Permissions**
   ```bash
   chmod -R 755 polytrans/
   find polytrans/ -name "*.php" -exec chmod 644 {} \;
   ```

3. **Verify Installation**
   - Check that the main plugin file exists: `polytrans/polytrans.php`
   - Ensure WordPress can read the plugin directory

### Development Installation
For developers who want to contribute or customize the plugin:

1. **Clone from Repository** (if available)
   ```bash
   cd /wp-content/plugins/
   git clone [repository-url] polytrans
   cd polytrans
   ```

2. **Install Dependencies**
   ```bash
   composer install
   npm install  # if frontend assets exist
   ```

3. **Set Development Mode**
   Add to `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('POLYTRANS_DEBUG', true);
   ```

## Post-Installation Setup

### 1. Initial Configuration
After activation, you'll see a setup notice in your WordPress admin:

1. Navigate to **Settings → Translation Settings**
2. Choose your translation provider:
   - **Google Translate**: No setup required
   - **OpenAI**: Requires API key configuration
3. Configure allowed languages
4. Set default post status for translations

### 2. Translation Provider Setup

#### Google Translate (Easiest)
- No additional setup required
- Works immediately after plugin activation
- Uses public Google Translate API

#### OpenAI (Advanced)
1. Get an OpenAI API key from [OpenAI Platform](https://platform.openai.com/)
2. Navigate to **Settings → Translation Settings**
3. Select "OpenAI" as provider
4. Enter your API key
5. Configure language mappings and assistants

### 3. Language Configuration
1. In **Translation Settings**, configure:
   - **Source Languages**: Languages you translate from
   - **Target Languages**: Languages you translate to
   - **Default Behavior**: How translations should be handled

### 4. User Permissions
Ensure your team has appropriate permissions:
- **Translators**: Need `edit_posts` capability
- **Reviewers**: Need `edit_posts` and `publish_posts` capabilities
- **Administrators**: Need `manage_options` capability

## Verification

### Check Plugin Status
1. Go to **Plugins → Installed Plugins**
2. Verify "PolyTrans" is listed and active
3. Check for any error messages

### Test Basic Functionality
1. Edit any post or page
2. Look for the "Translation" and "Translation Scheduler" meta boxes
3. Try scheduling a simple translation
4. Check **PolyTrans → Translation Logs** for activity

### Verify API Connections
1. Go to **Settings → Translation Settings**
2. Use the "Test Configuration" feature (if available)
3. Check that your translation provider responds correctly

## Troubleshooting Installation Issues

### Common Problems

#### Plugin Not Appearing in Admin
- **Check**: File permissions (directories: 755, files: 644)
- **Check**: Plugin file structure is correct
- **Check**: WordPress can access the plugins directory

#### Activation Errors
- **Check**: PHP version compatibility (7.4+ required)
- **Check**: WordPress version (5.0+ required)
- **Check**: Required PHP extensions are installed

#### Missing Dependencies
If using Composer dependencies:
```bash
cd /wp-content/plugins/polytrans/
composer install --no-dev
```

#### File Permission Issues
```bash
# Fix typical permission issues
cd /wp-content/plugins/
chown -R www-data:www-data polytrans/
chmod -R 755 polytrans/
```

### Error Messages

#### "Plugin could not be activated"
- Check WordPress debug log: `/wp-content/debug.log`
- Verify PHP syntax by running: `php -l polytrans/polytrans.php`
- Check for conflicts with other plugins

#### "Missing required files"
- Ensure complete upload (check file sizes)
- Re-download and re-upload the plugin
- Verify all directories and files are present

## Requirements Check

Before installation, verify your environment meets these requirements:

### Core Requirements
- ✅ WordPress 5.0 or higher
- ✅ PHP 7.4 or higher  
- ✅ MySQL 5.7+ or MariaDB 10.1+

### PHP Extensions
- ✅ JSON Extension
- ✅ cURL Extension  
- ✅ mbstring Extension
- ✅ OpenSSL Extension

### Recommended Plugins
- **Polylang**: For full multilingual support
- **Yoast SEO** or **RankMath**: For SEO metadata translation

### Server Requirements
- **Memory**: 256MB minimum, 512MB recommended
- **Execution Time**: 300 seconds recommended
- **HTTPS**: Required for API communication

## Next Steps

After successful installation:

1. **[Quick Start Guide](../../QUICK_START.md)**: Get up and running quickly
2. **[Configuration Guide](../admin/CONFIGURATION.md)**: Detailed configuration options
3. **[User Interface Guide](INTERFACE.md)**: Learn the admin interface
4. **[FAQ & Troubleshooting](FAQ.md)**: Common questions and issues

## Support

If you encounter installation issues:

1. Check the [FAQ & Troubleshooting](FAQ.md) guide
2. Enable WordPress debug logging
3. Check server error logs
4. Contact support with specific error messages

---

*Need help? Check our [troubleshooting guide](FAQ.md) or contact support.*
