# 🔍 PHP Server Diagnostics Tool

A comprehensive visual PHP diagnostics tool that displays detailed information about your server environment, installed extensions, cache systems, databases, and performance metrics with a modern dark mode interface.

## ✨ Features

### 🌙 Modern Interface
- Beautiful dark mode design
- Fully responsive layout
- Color-coded status indicators
- Interactive card-based layout
- Progress bars for memory and cache usage
- 5px border radius for clean aesthetics

### 🔧 Diagnostic Information

#### 1. **Memory Usage**
- Current memory usage
- Peak memory usage
- Memory limit configuration

#### 2. **System Load**
- CPU core count
- Load average (1, 5, 15 minutes)
- Load percentage visualization
- System uptime

#### 3. **Disk Usage**
- All disk partitions
- Size, used, and available space
- Usage percentage with progress bars
- Inode usage monitoring
- Automatic filtering of temp filesystems

#### 4. **Server Information**
- Operating system
- Server API (Apache, Nginx, CLI, etc.)
- Architecture (32/64-bit)
- Zend version
- Server software
- Document root
- Timezone

#### 5. **OPcache Status**
- Enabled/Disabled status
- Cache full indicator
- Number of cached scripts
- Hit rate percentage
- Memory usage with visual progress bar
- Color-coded warnings (orange >75%, red >90%)

#### 6. **APCu Cache**
- Status indicator
- Number of cached keys
- Hits and misses statistics
- Memory usage visualization

#### 7. **Redis Cache** 🔴
- Auto-detection (Unix socket and TCP)
- Connection type display
- Version information
- Uptime tracking
- Total keys count
- Hit rate percentage
- Hits/Misses statistics
- Connected clients
- Evicted keys monitoring
- Memory usage with limits

#### 8. **Memcached Cache** 🗃️
- Auto-detection (socket and TCP)
- Extension type (memcached/memcache)
- Version information
- Current items count
- Hit rate statistics
- Evictions monitoring
- Memory usage visualization

#### 9. **MySQL/MariaDB** 🐬
- Auto-detection (socket and TCP)
- Automatic MariaDB recognition
- Version display
- Uptime tracking
- Connected threads
- Query statistics
- Data sent/received
- InnoDB buffer pool information

#### 10. **PostgreSQL** 🐘
- Multi-connection attempt support
- Server and client version
- Database count
- Active connections monitoring

#### 11. **MongoDB** 🍃
- Connection status
- Version information
- Uptime tracking
- Connection count
- Operation counters (insert/query/update)

#### 12. **PDO Connections** 🔌
- Support for all PDO drivers:
  - MySQL (with MariaDB detection)
  - PostgreSQL
  - SQLite
  - Oracle
  - SQL Server
- Individual status cards for each driver
- Version information per connection

#### 13. **SQLite3 Extension** 📦
- Extension status
- SQLite version
- Version number

#### 14. **PHP Extensions**
Categorized display:
- **Database**: mysqli, PDO variants, MongoDB, Redis, PostgreSQL, SQLite3
- **Cache**: APCu, OPcache, Memcached, Redis
- **Compression**: zlib, bz2, zip
- **Encryption**: OpenSSL, Sodium, Hash
- **Image**: GD, Imagick, EXIF
- **XML/JSON**: XML, DOM, JSON, LibXML
- **String/Text**: mbstring, iconv, intl
- **Network**: cURL, FTP, Sockets
- **Other**: Additional extensions

#### 15. **PHP Configuration**
Categorized directives:
- **Performance**: execution time, memory limit, upload limits
- **Error Handling**: error reporting, logging
- **Security**: expose_php, allow_url_fopen, disable_functions
- **Session**: save handler, cookie settings

#### 16. **Configuration Files**
- Loaded php.ini file location
- List of additional .ini files

## 🚀 Usage

### Basic Usage

1. Upload the `diagnostics.php` file to your server
2. Open in your browser:
   ```
   http://your-domain.com/diagnostics.php
   ```

### Local Development

```bash
# Start PHP built-in server
php -S localhost:8000

# Open in browser
http://localhost:8000/diagnostics.php
```

## 🔒 Security Considerations

⚠️ **IMPORTANT**: This tool contains sensitive system information!

### Production Environment

1. **IP Whitelist**: By default, only accessible from localhost. In production, add IP restrictions:

```php
// Modify at the top of diagnostics.php:
$allowed_ips = ['127.0.0.1', '::1', 'YOUR_IP_ADDRESS'];
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowed_ips)) {
    die('Access denied.');
}
```

2. **HTTP Authentication**: Add .htaccess protection:

```apache
# .htaccess
<Files "diagnostics.php">
    AuthType Basic
    AuthName "Diagnostics Access"
    AuthUserFile /path/to/.htpasswd
    Require valid-user
</Files>
```

3. **Delete After Use**: Remove the file from your server when not needed.

4. **robots.txt**: Exclude from search engines:

```
User-agent: *
Disallow: /diagnostics.php
```

## 📋 System Requirements

- PHP 7.0 or higher (recommended: PHP 8.0+)
- Modern browser (Chrome, Firefox, Safari, Edge)
- Optional: Redis, Memcached, MySQL/MariaDB, PostgreSQL, MongoDB for their respective monitoring features

## 🎨 Customization

### Color Scheme

Modify colors in the `<style>` section:

```css
/* Main background */
body {
    background: #0f172a;
}

/* Card icon colors */
.card-icon {
    background: linear-gradient(135deg, #yourcolor1 0%, #yourcolor2 100%);
}
```

### Adding Categories

Extension categories can be modified in the `getExtensionsByCategory()` function.

## 📊 What Does It Show?

### Performance Metrics
- Script execution time (ms)
- Memory usage (current, peak, limit)
- OPcache hit rate
- APCu cache efficiency
- Redis hit rate and evictions
- Memcached performance
- System load percentages
- Disk usage and inode consumption

### Environment Information
- PHP version and Zend version
- Server software and OS
- Complete list of installed extensions
- All PHP directive values
- Loaded configuration files
- All database connections and versions
- Cache system statistics
- Disk partition information

### Connection Detection
The tool automatically tries multiple connection methods for:
- **Redis**: Unix sockets (`/var/run/redis/*.sock`, `/tmp/redis.sock`) and TCP (`localhost:6379`, `127.0.0.1:6379`)
- **Memcached**: Socket and TCP connections
- **MySQL/MariaDB**: Unix socket (`/var/run/mysqld/mysqld.sock`, `/tmp/mysql.sock`) and TCP
- **PostgreSQL**: Unix socket (`/var/run/postgresql`) and TCP
- **MongoDB**: TCP connection (`localhost:27017`)
- **PDO**: Multiple connection attempts for each driver

## 🐛 Troubleshooting

### "Access denied" message
- Check that you're accessing from localhost, or modify the IP whitelist

### OPcache/APCu information not displayed
- Verify installation and enable status: `php -m | grep opcache`

### Redis/Memcached not showing
- Ensure the service is running: `systemctl status redis` or `systemctl status memcached`
- Check socket permissions
- Verify PHP extension is loaded: `php -m | grep redis`

### MySQL/MariaDB connection fails
- Check if MySQL service is running
- Verify socket path matches your system
- Ensure PHP mysqli extension is loaded

### Empty extension list
- Check PHP installation: `php -v` and `php -m`

### Disk information not showing (Linux)
- Ensure `df` command is available
- Check shell_exec is not disabled in PHP

## 🌟 Highlights

- **Auto-detection**: Automatically finds and connects to all available services
- **Dark Mode**: Easy on the eyes with modern dark theme
- **Zero Configuration**: Works out of the box, no setup required
- **Comprehensive**: Monitors PHP, caching systems, databases, and system resources
- **Universal**: English language interface for worldwide use
- **Secure**: Localhost-only by default with configurable access control

## 📝 License

This is an open-source diagnostic tool. Free to use and modify.

## 🤝 Contributing

Feature suggestions and bug reports are welcome! Feel free to fork and submit pull requests.

## 📸 Screenshots

The tool provides a clean, organized view of:
- System resources at a glance
- Cache performance metrics
- Database connection statuses
- Complete PHP environment details

---

**Version**: 1.0.0  
**Last Updated**: 2024-11-23  
**Compatibility**: PHP 7.0 - 8.3+  
**Theme**: Dark Mode 🌙  
**Language**: English 🌍
