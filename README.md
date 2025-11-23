# 🔍 PHP Server Diagnostics Tool

Vizuális PHP diagnosztikai eszköz, amely részletes információkat jelenít meg a szerver környezetéről, telepített kiegészítőkről, cache rendszerekről és teljesítmény metrikákról.

## ✨ Funkciók

### 📊 Vizuális Megjelenítés
- Modern, responsive design
- Színkódolt státuszok
- Interaktív kártya alapú elrendezés
- Progressz bárok memória és cache használathoz

### 🔧 Diagnosztikai Információk

#### 1. **Memory Usage** (Memóriahasználat)
- Aktuális memóriahasználat
- Csúcs memóriahasználat
- Memória limit beállítás

#### 2. **Server Information** (Szerver információk)
- Operációs rendszer
- Server API (Apache, Nginx, CLI, stb.)
- Architektúra (32/64-bit)
- Zend verzió
- Szerver szoftver
- Időzóna

#### 3. **OPcache Status** (OPcache állapot)
- Engedélyezve/Letiltva státusz
- Cache telítettség
- Gyorsítótárazott scriptek száma
- Hit rate (találati arány) %
- Memóriahasználat vizuális progressz bárral
- Színkódolt figyelmeztetések (75% felett narancssárga, 90% felett piros)

#### 4. **APCu Cache** (APCu gyorsítótár)
- Státusz
- Gyorsítótárazott kulcsok száma
- Találatok és kihagyások
- Memóriahasználat vizualizáció

#### 5. **PHP Extensions** (PHP kiegészítők)
Kategorizált megjelenítés:
- **Database**: mysqli, PDO, MongoDB, Redis, stb.
- **Cache**: APCu, OPcache, Memcached, Redis
- **Compression**: zlib, bz2, zip
- **Encryption**: OpenSSL, Sodium, Hash
- **Image**: GD, Imagick, EXIF
- **XML/JSON**: XML, DOM, JSON, LibXML
- **String/Text**: mbstring, iconv, intl
- **Network**: cURL, FTP, Sockets
- **Other**: Egyéb kiegészítők

#### 6. **PHP Configuration** (PHP konfiguráció)
Kategorizált direktívák:
- **Performance**: execution time, memory limit, upload limits
- **Error Handling**: error reporting, logging
- **Security**: expose_php, allow_url_fopen, disable_functions
- **Session**: save handler, cookie settings

#### 7. **Configuration Files** (Konfigurációs fájlok)
- Betöltött php.ini fájl helye
- További .ini fájlok listája

## 🚀 Használat

### Alapvető használat

1. Töltsd fel a `diagnostics.php` fájlt a szerveredre
2. Nyisd meg böngészőben:
   ```
   http://your-domain.com/diagnostics.php
   ```

### Helyi fejlesztés

```bash
# PHP beépített szerver indítása
php -S localhost:8000

# Böngészőben nyisd meg
http://localhost:8000/diagnostics.php
```

## 🔒 Biztonsági Megfontolások

⚠️ **FONTOS**: Ez az eszköz érzékeny rendszer információkat tartalmaz!

### Éles környezetben

1. **IP whitelist**: Alapértelmezetten csak localhost-ról érhető el. Éles környezetben adj hozzá IP korlátozást:

```php
// diagnostics.php elején módosítsd:
$allowed_ips = ['127.0.0.1', '::1', 'YOUR_IP_ADDRESS'];
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowed_ips)) {
    die('Access denied.');
}
```

2. **HTTP Authentication**: Adj hozzá .htaccess védelmet:

```apache
# .htaccess
<Files "diagnostics.php">
    AuthType Basic
    AuthName "Diagnostics Access"
    AuthUserFile /path/to/.htpasswd
    Require valid-user
</Files>
```

3. **Törlés használat után**: Ha már nincs rá szükség, töröld a fájlt a szerverről.

4. **robots.txt**: Zárd ki a keresőmotoroktól:

```
User-agent: *
Disallow: /diagnostics.php
```

## 📋 Rendszerkövetelmények

- PHP 7.0 vagy újabb (ajánlott: PHP 8.0+)
- Modern böngésző (Chrome, Firefox, Safari, Edge)

## 🎨 Testreszabás

### Színséma módosítása

A `<style>` szekcióban módosíthatod a színeket:

```css
/* Fő gradient */
body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

/* Kártya ikonok színei */
.card-icon {
    background: linear-gradient(135deg, #yourcolor1 0%, #yourcolor2 100%);
}
```

### Kategóriák hozzáadása

Az extension kategóriák a `getExtensionsByCategory()` függvényben módosíthatók.

## 📊 Mit mutat?

### Teljesítmény metrikák
- Script végrehajtási idő (ms)
- Memóriahasználat (aktuális, csúcs, limit)
- OPcache találati arány
- APCu cache hatékonyság

### Környezeti információk
- PHP verzió és Zend verzió
- Szerver szoftver és OS
- Telepített extensions teljes listája
- Összes PHP direktíva értéke
- Betöltött konfigurációs fájlok

## 🐛 Hibaelhárítás

### "Access denied" üzenet
- Ellenőrizd, hogy localhost-ról éred-e el, vagy módosítsd az IP white-list-et

### OPcache/APCu információ nem jelenik meg
- Ellenőrizd, hogy telepítve és engedélyezve van-e: `php -m | grep opcache`

### Üres extension lista
- Ellenőrizd a PHP telepítést: `php -v` és `php -m`

## 📝 Licensz

Ez egy nyílt forráskódú diagnosztikai eszköz. Szabadon használható és módosítható.

## 🤝 Hozzájárulás

Funkció javaslatok és hibajelentések közvetlenül a fejlesztő felé.

---

**Verzió**: 1.0.0
**Utolsó frissítés**: 2025-11-23
**Kompatibilitás**: PHP 7.0 - 8.3+
