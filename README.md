# XML Product Sync Enhanced

**Verzija:** 2.0.0  
**Autor:** MiniMax Agent  
**Zahteva:** WordPress 5.0+, WooCommerce 5.0+, PHP 7.4+  
**Testirano do:** WordPress 6.4, WooCommerce 8.5  

## Opis

XML Product Sync Enhanced je napredni WordPress/WooCommerce plugin za automatsku sinhronizaciju proizvoda iz XML feed-a. Plugin je dizajniran sa fokusom na sigurnost, performance i jednostavnost korišćenja.

## Ključne Karakteristike

### 🚀 **Napredna Sinhronizacija**
- Automatska sinhronizacija sa konfigurabilnim intervalima
- Batch processing za velike količine proizvoda
- Podrška za product varijante
- Pametna kategorizacija sa hijerarhijskim strukturama
- Automatsko preuzimanje i optimizacija slika

### 🔒 **Sigurnost**
- CSRF protection za sve admin forme
- Input sanitization i validation
- Sigurno file handling
- Permission checks
- Nonce verification

### 📈 **Monitoring i Logging**
- Detaljno logiranje sa različitim nivoima
- Real-time progress tracking
- Email notifikacije
- Export/import logova
- Webhook podrška

### ⚙️ **Performance Optimizacije**
- Memory management
- Database query optimizacija
- Image caching i processing
- Batch delay konfiguracij
- Cleanup automatksi

### 📊 **Admin Dashboard**
- Real-time sync status
- Detaljne statistike
- System informacije
- Alati za maintenance
- Kategory management

## Instalacija

1. **Upload plugin-a:**
   ```
   wp-content/plugins/xml-product-sync-enhanced/
   ```

2. **Aktivacija:**
   - Idite na WordPress Admin > Plugins
   - Pronađite "XML Product Sync Enhanced"
   - Kliknite "Activate"

3. **Osnovne postavke:**
   - Idite na XML Product Sync > Postavke
   - Unesite XML Feed URL
   - Konfigurišite sync interval
   - Sačuvajte postavke

## Konfiguracija

### Opšte Postavke

| Postavka | Opis | Default |
|----------|------|----------|
| XML URL | URL na XML feed sa proizvod podacima | (potrebno uneti) |
| Sync Interval | Učestalost automatske sinhronizacije | Svakih 6 sati |
| Batch Size | Broj proizvoda po batch-u | 100 |
| Batch Delay | Pauza između batch-ova (sekunde) | 15 |
| Max Retries | Maksimalan broj pokušaja | 3 |

### Postavke Proizvoda

| Postavka | Opis | Default |
|----------|------|----------|
| Handle Variants | Podrška za product varijante | Da |
| Auto Update Existing | Automatsko ažuriranje postojećih | Da |
| Skip Images Update | Preskoči ažuriranje slika | Ne |
| Update Stock Only | Ažuriraj samo stock podatke | Ne |

### Postavke Kategorija

| Postavka | Opis | Default |
|----------|------|----------|
| Create Missing Categories | Kreiraj nepostojeće kategorije | Da |
| Default Category | Default kategorija | "Bez kategorije" |
| Enable Fuzzy Matching | Fuzzy prepoznavanje kategorija | Ne |

### Postavke Slika

| Postavka | Opis | Default |
|----------|------|----------|
| Download Timeout | Timeout za preuzimanje (sekunde) | 30 |
| Max Image Size | Maksimalna veličina slike (MB) | 10 |
| Min Image Width/Height | Minimalne dimenzije (px) | 50x50 |
| Resize on Upload | Resize tokom upload-a | Da |

## XML Format

Plugin očekuje XML format sa sljedećom strukturom:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<root>
    <item>
        <Sifra><![CDATA[ proizvod-sku-123 ]]></Sifra>
        <Naziv><![CDATA[ Naziv Proizvoda ]]></Naziv>
        <Opis><![CDATA[ <p>HTML opis proizvoda</p> ]]></Opis>
        <Osnovna-cijena>199.00</Osnovna-cijena>
        <Preporucena-cijena>299.00</Preporucena-cijena>
        <Kolicina>10</Kolicina>
        <Package-weight>1.5</Package-weight>
        <Width>10.0</Width>
        <Height>15.0</Height>
        <Length>20.0</Length>
        <EAN><![CDATA[ 1234567890123 ]]></EAN>
        <Kategorija1><![CDATA[ Glavna Kategorija ]]></Kategorija1>
        <Kategorija2><![CDATA[ Podkategorija ]]></Kategorija2>
        <Kategorija3><![CDATA[ Pod-podkategorija ]]></Kategorija3>
        <Slika1><![CDATA[ https://example.com/image1.jpg ]]></Slika1>
        <Slika2><![CDATA[ https://example.com/image2.jpg ]]></Slika2>
        <Varijant-sifra>glavni-proizvod-sku</Varijant-sifra>
        <Varijant-definicija>Veličina</Varijant-definicija>
        <Specifikacija><![CDATA[ brand:Brand Name§color:Red ]]></Specifikacija>
    </item>
    <!-- Dodatni proizvodi... -->
</root>
```

### Podržana Polja

- **Sifra** - SKU proizvoda (obavezno)
- **Naziv** - Naziv proizvoda
- **Opis** - HTML opis proizvoda
- **Osnovna-cijena** - Osnovna cena
- **Preporucena-cijena** - Preporučena cena (prioritet nad osnovnom)
- **Kolicina** - Stock količina
- **Package-weight** - Težina
- **Width/Height/Length** - Dimenzije
- **EAN** - EAN kod
- **Kategorija1-5** - Hijerarhijske kategorije
- **Slika1-10** - URL-ovi slika
- **Varijant-sifra** - SKU glavnog proizvoda (za varijante)
- **Varijant-definicija** - Definicija varijante
- **Specifikacija** - Dodatne specifikacije

## Product Variants

Plugin automatski prepoznaje product varijante na osnovu `Varijant-sifra` polja:

```xml
<!-- Glavni proizvod -->
<item>
    <Sifra>glavna-123</Sifra>
    <Naziv>Proizvod Glavni</Naziv>
    <Varijant-sifra>glavna-123</Varijant-sifra>
    <!-- ... -->
</item>

<!-- Varijanta 1 -->
<item>
    <Sifra>varijanta-123-s</Sifra>
    <Naziv>Proizvod Glavni - S</Naziv>
    <Varijant-sifra>glavna-123</Varijant-sifra>
    <Varijant-definicija>Veličina: S</Varijant-definicija>
    <!-- ... -->
</item>

<!-- Varijanta 2 -->
<item>
    <Sifra>varijanta-123-m</Sifra>
    <Naziv>Proizvod Glavni - M</Naziv>
    <Varijant-sifra>glavna-123</Varijant-sifra>
    <Varijant-definicija>Veličina: M</Varijant-definicija>
    <!-- ... -->
</item>
```

## Kategory Management

### Automatska Kreacija
Plugin automatski kreira hijerarhijske kategorije na osnovu `Kategorija1-5` polja.

### Custom Mapping
Možete kreirati custom mapiranje kategorija u admin interface-u:

```
XML Kategorija -> WooCommerce Kategorija
"Bicikli MTB" -> "Mountain Bikes"
"Garmin" -> "Garmin proizvodi"
```

### Validacija
Kategorije se validiraju prema sljedećim pravilima:
- Minimum 2 karaktera
- Nije samo brojevi
- Dozvoljeni karakteri: slova, brojevi, razmaci, crtice, zagrade
- Nije na blacklist listi

## Logging

### Log Nivoi
- **DEBUG** - Detaljne informacije za debugging
- **INFO** - Opšte informacije o operacijama
- **WARNING** - Upozorenja koja ne sprečavaju rad
- **ERROR** - Greške koje sprečavaju obradu
- **CRITICAL** - Kritične greške koje zaustavljaju sync

### Log Lokacije
- **Database** - wp_xpse_sync_logs tabela
- **WordPress Debug Log** - ako je WP_DEBUG uključen
- **Email** - kritične greške se šalju na email

## Performance Tuning

### Memory Management
```php
// Preporučene postavke
memory_limit = 512M
max_execution_time = 300
```

### Batch Size Optimizacija
- **Mala memoria (<256MB)**: Batch size 50-100
- **Srednja memoria (256-512MB)**: Batch size 100-200
- **Velika memoria (>512MB)**: Batch size 200-500

### Image Processing
- Ograničite veličinu slika (max 10MB)
- Uključite resize on upload
- Postavite adekvatan timeout (30s)

## Troubleshooting

### Česti Problemi

**1. Memory Limit Exceeded**
```
Solution: Povećajte memory_limit ili smanjite batch_size
```

**2. XML Parsing Error**
```
Solution: Provjerite XML format i enkodiranje
```

**3. Images Not Downloading**
```
Solution: Provjerite URL-ove slika i network konekciju
```

**4. Categories Not Created**
```
Solution: Provjerite dozvole i omogućite "Create Missing Categories"
```

### Debug Mode
Uključite debug mode u postavkama za detaljno logiranje:

```php
// U wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Test Mode
Koristite test mode za simulaciju bez stvarnih promena:
- Uključite "Test Mode" u postavkama
- Plugin će simulirati operacije bez kreiranja/ažuriranja proizvoda

## API Reference

### Hooks

#### Actions
```php
// Pre početka sync-a
do_action('xpse_before_sync', $session_id);

// Posle završetka sync-a
do_action('xpse_after_sync', $session_id, $stats);

// Pre obrade proizvoda
do_action('xpse_before_product_process', $item, $product_id);

// Posle obrade proizvoda
do_action('xpse_after_product_process', $item, $product_id, $result);
```

#### Filters
```php
// Filtriraj XML prije obrade
apply_filters('xpse_xml_data', $xml);

// Filtriraj product data
apply_filters('xpse_product_data', $product_data, $item);

// Filtriraj dozvoljena HTML tagova
apply_filters('xpse_allowed_html_tags', $allowed_tags);

// Filtriraj category blacklist
apply_filters('xpse_category_blacklist', $blacklist);
```

### Helper Functions

```php
// Dobij glavnu instancu plugin-a
$plugin = xpse();

// Dobij logger
$logger = $plugin->get_logger();
$logger->info('Custom log message');

// Dobij sync engine
$sync_engine = $plugin->get_sync_engine();
$status = $sync_engine->get_sync_status();

// Provjeri da li je sync u toku
if ($sync_engine->is_sync_running()) {
    // Sync je aktivan
}
```

## Sigurnosne Preporuke

### Server Konfiguracija
```apache
# .htaccess - zabrani direktan pristup
<Files "*.php">
    Order Deny,Allow
    Deny from all
</Files>

<Files "xml-product-sync-enhanced.php">
    Order Allow,Deny
    Allow from all
</Files>
```

### WordPress Sigurnost
```php
// Ograniči access na admin stranice
if (!current_user_can('manage_options')) {
    wp_die('Nemate dozvole.');
}

// Koristi nonce za sve forme
wp_nonce_field('xpse_action', 'xpse_nonce');
```

## Backup i Recovery

### Automatski Backup
Plugin automatski kreira backup proizvoda prije ažuriranja:
- Backup se čuva u transient podatcima
- Retention period: 7 dana (konfigurabilan)

### Manual Restore
```php
// Restore proizvod iz backup-a
XPSE_Utilities::restore_product($product_id);
```

### Export/Import Postavki
- Export: XML Product Sync > Postavke > Export
- Import: Upload JSON datoteke sa postavkama

## Maintenance

### Cleanup Operacije
- **Orphaned Images**: Ukloni slike koje nisu povezane sa proizvodima
- **Empty Categories**: Ukloni prazne kategorije
- **Old Logs**: Automatsko brisanje starih logova
- **Temp Files**: Cleanup privremenih datoteka

### Performance Monitoring
- Memory usage tracking
- Execution time monitoring
- Database query counting
- Error rate tracking

## Podrška

Za podršku i dodatne informacije:

- **Documentation**: Detaljni admin interfejs sa objasnenjima
- **Logs**: Detaljno logiranje za troubleshooting
- **System Info**: Pregled sistemskih informacija
- **Test Tools**: Alati za testiranje konekcije i konfiguracije

---

**XML Product Sync Enhanced** - Napredna sinhronizacija proizvoda za WooCommerce

© 2025 MiniMax Agent. Sva prava zadržana.