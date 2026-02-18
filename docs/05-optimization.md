# Optimización del Sistema

Guía completa para optimizar el rendimiento de Moodle 5.1 en AWS, basada en experiencia real del proyecto ACG Calidad.

## 🎯 Objetivos de Optimización

- Maximizar rendimiento con recursos limitados
- Prevenir Out of Memory (OOM) kills
- Optimizar respuesta bajo carga
- Minimizar costos de AWS
- Asegurar estabilidad 24/7

## 📊 Perfil de Carga Típico Moodle

### Consumo de Recursos

**PHP-FPM Process:**
- Memoria base: ~50-80 MB
- Con Moodle cargado: ~150-225 MB
- Picos durante cron: ~200-300 MB

**Apache Worker:**
- MPM Event: ~15-20 MB por worker
- MPM Prefork: ~50-70 MB por worker (no recomendado)

**Cron de Moodle:**
- Proceso individual: ~60-150 MB
- Múltiples simultáneos: 3-6 procesos
- Picos cada hora (tareas programadas)

## ⚙️ PHP-FPM Optimization

### Cálculo de Configuración Óptima

**Fórmula:**
```
max_children = (RAM_disponible × 0.8) / memoria_por_proceso
```

**Ejemplo t4g.medium (4 GB RAM):**
```
RAM disponible: 3.7 GB (~3800 MB)
RAM para sistema: 800 MB
RAM para PHP-FPM: 3000 MB
Memoria por proceso PHP: ~200 MB

max_children = 3000 / 200 = 15 procesos
```

**Ejemplo t4g.large (8 GB RAM):**
```
RAM disponible: 7.5 GB (~7700 MB)
RAM para sistema: 1200 MB
RAM para PHP-FPM: 6500 MB
Memoria por proceso PHP: ~200 MB

max_children = 6500 / 200 = 32 procesos
```

### Configuración por Tamaño de Instancia

#### t4g.small (2 GB RAM) - No recomendado

```ini
[www]
pm = dynamic
pm.max_children = 8
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500

; Limites
request_terminate_timeout = 300
php_admin_value[memory_limit] = 256M
```

**Problemas:**
- Muy justo para Moodle + cron
- Requiere SWAP obligatorio
- Performance limitado

#### t4g.medium (4 GB RAM) - Básico ✅

```ini
[www]
pm = dynamic
pm.max_children = 15
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 5
pm.max_requests = 500

; Limites
request_terminate_timeout = 300
php_admin_value[memory_limit] = 384M
```

**Características:**
- 50-100 usuarios concurrentes
- SWAP recomendado (2 GB)
- Monitoreo activo necesario

#### t4g.large (8 GB RAM) - Recomendado ⭐

```ini
[www]
pm = dynamic
pm.max_children = 35
pm.start_servers = 5
pm.min_spare_servers = 3
pm.max_spare_servers = 10
pm.max_requests = 500

; Limites
request_terminate_timeout = 300
php_admin_value[memory_limit] = 512M
```

**Características:**
- 200-500 usuarios concurrentes
- SWAP opcional (seguridad)
- Estable sin monitoreo constante

#### t4g.xlarge (16 GB RAM) - Alto tráfico

```ini
[www]
pm = dynamic
pm.max_children = 75
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

; Limites
request_terminate_timeout = 300
php_admin_value[memory_limit] = 512M
```

### Script de Configuración Automática

```bash
#!/bin/bash
# Auto-configure PHP-FPM based on available RAM

# Detectar RAM total en MB
TOTAL_RAM=$(free -m | awk '/^Mem:/{print $2}')
echo "RAM Total: ${TOTAL_RAM} MB"

# Calcular max_children
SYSTEM_RAM=800
AVAILABLE_RAM=$((TOTAL_RAM - SYSTEM_RAM))
MEMORY_PER_PROCESS=200
MAX_CHILDREN=$((AVAILABLE_RAM / MEMORY_PER_PROCESS))

# Mínimo 5, máximo 100
[[ $MAX_CHILDREN -lt 5 ]] && MAX_CHILDREN=5
[[ $MAX_CHILDREN -gt 100 ]] && MAX_CHILDREN=100

# Calcular otros valores
START_SERVERS=$((MAX_CHILDREN / 5))
[[ $START_SERVERS -lt 2 ]] && START_SERVERS=2

MIN_SPARE=$((MAX_CHILDREN / 7))
[[ $MIN_SPARE -lt 1 ]] && MIN_SPARE=1

MAX_SPARE=$((MAX_CHILDREN / 3))
[[ $MAX_SPARE -lt 3 ]] && MAX_SPARE=3

echo "Configuración calculada:"
echo "  pm.max_children = $MAX_CHILDREN"
echo "  pm.start_servers = $START_SERVERS"
echo "  pm.min_spare_servers = $MIN_SPARE"
echo "  pm.max_spare_servers = $MAX_SPARE"

# Aplicar a configuración
sudo sed -i "s/^pm.max_children = .*/pm.max_children = $MAX_CHILDREN/" /etc/php-fpm.d/www.conf
sudo sed -i "s/^pm.start_servers = .*/pm.start_servers = $START_SERVERS/" /etc/php-fpm.d/www.conf
sudo sed -i "s/^pm.min_spare_servers = .*/pm.min_spare_servers = $MIN_SPARE/" /etc/php-fpm.d/www.conf
sudo sed -i "s/^pm.max_spare_servers = .*/pm.max_spare_servers = $MAX_SPARE/" /etc/php-fpm.d/www.conf

# Reiniciar PHP-FPM
sudo systemctl restart php-fpm
echo "PHP-FPM optimizado y reiniciado"
```

### Configuración PHP Adicional

```ini
; /etc/php.d/99-moodle.ini

; Memoria
memory_limit = 384M
post_max_size = 100M
upload_max_filesize = 100M

; Timeouts
max_execution_time = 300
max_input_time = 300

; OPcache
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 60
opcache.fast_shutdown = 1
opcache.save_comments = 0

; Session
session.save_handler = files
session.gc_probability = 1
session.gc_divisor = 100

; Error reporting
display_errors = Off
log_errors = On
error_log = /var/log/php-fpm/php-error.log
```

## 🌐 Apache Optimization

### MPM Event (Recomendado)

```apache
# /etc/httpd/conf.modules.d/00-mpm.conf
LoadModule mpm_event_module modules/mod_mpm_event.so

# /etc/httpd/conf.d/mpm.conf
<IfModule mpm_event_module>
    # t4g.medium (4GB)
    ServerLimit              16
    StartServers             3
    MinSpareThreads          25
    MaxSpareThreads          75
    ThreadsPerChild          25
    MaxRequestWorkers        400
    MaxConnectionsPerChild   10000

    # KeepAlive
    KeepAlive On
    MaxKeepAliveRequests 100
    KeepAliveTimeout 5
</IfModule>
```

**Para t4g.large (8GB):**
```apache
<IfModule mpm_event_module>
    ServerLimit              32
    StartServers             5
    MinSpareThreads          50
    MaxSpareThreads          150
    ThreadsPerChild          25
    MaxRequestWorkers        800
    MaxConnectionsPerChild   10000
</IfModule>
```

### Compression

```apache
# /etc/httpd/conf.d/deflate.conf
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css
    AddOutputFilterByType DEFLATE text/javascript application/javascript application/x-javascript
    AddOutputFilterByType DEFLATE application/json application/xml

    # Excluir imágenes (ya comprimidas)
    SetEnvIfNoCase Request_URI \.(?:gif|jpe?g|png|webp)$ no-gzip

    # Nivel de compresión (1-9, default 6)
    DeflateCompressionLevel 6
</IfModule>
```

### Cache Headers

```apache
# /etc/httpd/conf.d/cache.conf
<IfModule mod_expires.c>
    ExpiresActive On

    # Assets estáticos
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType application/font-woff2 "access plus 1 year"

    # HTML y PHP (sin cache)
    ExpiresByType text/html "access plus 0 seconds"
</IfModule>

<IfModule mod_headers.c>
    # Security headers
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"

    # Cache control para assets
    <FilesMatch "\.(jpg|jpeg|png|gif|webp|css|js|woff2)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>
</IfModule>
```

### Limits

```apache
# /etc/httpd/conf.d/limits.conf
<IfModule mpm_event_module>
    Timeout 300
    LimitRequestBody 104857600
    LimitRequestFields 100
    LimitRequestFieldSize 8190
    LimitRequestLine 8190
</IfModule>
```

## 💾 SWAP Configuration

### ¿Cuándo es Necesario SWAP?

**Obligatorio:**
- t4g.small (2 GB RAM)
- t4g.medium (4 GB RAM)

**Recomendado:**
- t4g.large (8 GB RAM) - por seguridad

**Opcional:**
- t4g.xlarge+ (16+ GB RAM)

### Tamaño de SWAP

```
RAM < 2 GB   → SWAP = 2 × RAM
RAM 2-8 GB   → SWAP = RAM
RAM > 8 GB   → SWAP = 8 GB (máximo)
```

### Configuración

```bash
#!/bin/bash
# Crear SWAP de 2GB

# Calcular tamaño
RAM_GB=$(free -g | awk '/^Mem:/{print $2}')
if [ $RAM_GB -lt 2 ]; then
    SWAP_SIZE=$((RAM_GB * 2))
elif [ $RAM_GB -le 8 ]; then
    SWAP_SIZE=$RAM_GB
else
    SWAP_SIZE=8
fi

SWAP_SIZE_MB=$((SWAP_SIZE * 1024))

# Crear archivo swap
sudo dd if=/dev/zero of=/swapfile bs=1M count=$SWAP_SIZE_MB status=progress
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile

# Hacer permanente
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab

# Configurar swappiness (cuánto usar swap)
echo 'vm.swappiness=10' | sudo tee -a /etc/sysctl.conf
sudo sysctl -p

echo "SWAP de ${SWAP_SIZE}GB configurado"
```

### Swappiness

```bash
# Ver valor actual
cat /proc/sys/vm/swappiness

# Configurar (0-100)
# 0  = usar swap solo en emergencia
# 10 = usar swap moderadamente (recomendado)
# 60 = default Linux (agresivo)
# 100 = usar swap agresivamente

sudo sysctl vm.swappiness=10
```

## 🗄️ Moodle Cache Configuration

### config.php

```php
// Cache store configuration
// NOTA: Las rutas de cache en /moodledata NO cambian en Moodle 5.1
$CFG->cachedir = '/moodledata/cache';

// Session handler
$CFG->session_handler_class = '\core\session\database';
$CFG->session_database = 'default';

// Alternative file-based (menos performance, más simple)
// $CFG->session_handler_class = '\core\session\file';
// $CFG->session_file_path = '/moodledata/sessions';

// Cache stores
$CFG->cachestore_file_autocreate = true;
$CFG->cachestore_file_path = '/moodledata/cache';

// Performance
$CFG->enablecaching = true;
$CFG->cachejs = true;
$CFG->yuicomboloading = true;
$CFG->langstringcache = true;

// Theme designer mode (desactivar en producción)
$CFG->themedesignermode = false;
```

### Cache Stores (Admin UI)

**Recomendado para single server:**

1. **Application Cache:** File
   - Default store para application cache
   - Path: /moodledata/cache

2. **Session Cache:** Database
   - Persiste entre requests
   - Limpieza automática

3. **Request Cache:** Static
   - En memoria, muy rápido
   - Solo durante request actual

### Plugins de Cache (Avanzado)

Para mejor performance (requiere servicios adicionales):

```php
// Redis (requiere Redis server)
$CFG->session_handler_class = '\core\session\redis';
$CFG->session_redis_host = '127.0.0.1';
$CFG->session_redis_port = 6379;
$CFG->session_redis_database = 0;
$CFG->session_redis_prefix = 'mdl_';

// Memcached (requiere Memcached server)
$CFG->session_handler_class = '\core\session\memcached';
$CFG->session_memcached_save_path = '127.0.0.1:11211';
$CFG->session_memcached_prefix = 'mdl_';
```

## ⏰ Cron Optimization

### Configuración

```bash
# /etc/cron.d/moodle
# NOTA: La ruta CLI NO cambia en Moodle 5.1 (permanece ARRIBA de /public)
* * * * * apache /usr/bin/php /var/www/html/moodle/admin/cli/cron.php > /dev/null 2>&1
```

### Prevenir Múltiples Ejecuciones

Moodle maneja esto internamente con locks, pero puedes agregar protección:

```bash
#!/bin/bash
# /usr/local/bin/moodle-cron-safe.sh

LOCKFILE=/var/lock/moodle-cron.lock
LOCKFD=99

_lock() {
    flock -$1 $LOCKFD
}

_no_more_locking() {
    _lock u
    _lock xn && rm -f $LOCKFILE
}

_prepare_locking() {
    eval "exec $LOCKFD>\"$LOCKFILE\""
    trap _no_more_locking EXIT
}

_prepare_locking

# Intentar lock (no esperar)
if ! _lock xn; then
    echo "Cron ya está ejecutándose"
    exit 1
fi

# Ejecutar cron
/usr/bin/php /var/www/html/moodle/admin/cli/cron.php

exit 0
```

### Monitorear Cron

```bash
# Ver últimas ejecuciones
sudo -u apache /usr/bin/php /var/www/html/moodle/admin/cli/scheduled_task.php --list

# Ver tareas pendientes
sudo -u apache /usr/bin/php /var/www/html/moodle/admin/cli/scheduled_task.php --list --failed

# Ejecutar tarea específica manualmente
sudo -u apache /usr/bin/php /var/www/html/moodle/admin/cli/scheduled_task.php --execute=\\core\\task\\session_cleanup_task
```

## 📊 Monitoring & Alerting

### Script de Monitoreo

```bash
#!/bin/bash
# /usr/local/bin/memory-monitor.sh

LOG_FILE="/var/log/memory-monitor.log"
TIMESTAMP=$(date "+%Y-%m-%d %H:%M:%S")

# Rotar log si > 10MB
if [ -f $LOG_FILE ] && [ $(stat -f%z "$LOG_FILE" 2>/dev/null || stat -c%s "$LOG_FILE") -gt 10485760 ]; then
    mv $LOG_FILE ${LOG_FILE}.old
    gzip ${LOG_FILE}.old
fi

# Registrar métricas
{
    echo "=== $TIMESTAMP ==="
    free -h
    echo "--- PHP-FPM: $(ps aux | grep php-fpm | grep -v grep | wc -l) procesos"
    echo "--- Cron: $(ps aux | grep 'cron.php' | grep -v grep | wc -l) procesos"
    echo "--- Apache: $(ps aux | grep httpd | grep -v grep | wc -l) procesos"
    echo ""
} >> $LOG_FILE

# Alerta si memoria < 500MB
AVAILABLE_MB=$(free -m | awk '/^Mem:/{print $7}')
if [ $AVAILABLE_MB -lt 500 ]; then
    echo "ALERTA: Memoria disponible baja ($AVAILABLE_MB MB)" >> $LOG_FILE
    # Enviar notificación (email, SNS, etc)
fi
```

### Cron para Monitoreo

```bash
# Cada hora
0 * * * * /usr/local/bin/memory-monitor.sh
```

## 🎯 Checklist de Optimización

### Inicial (Deployment)

- [ ] PHP-FPM configurado según RAM
- [ ] Apache MPM Event habilitado
- [ ] SWAP configurado (si aplica)
- [ ] OPcache habilitado
- [ ] Compression habilitada
- [ ] Cache headers configurados
- [ ] Moodle cache configurado
- [ ] Cron funcionando

### Semanal

- [ ] Revisar logs de memoria
- [ ] Verificar uso de SWAP
- [ ] Revisar cron execution logs
- [ ] Verificar espacio en disco

### Mensual

- [ ] Analizar patrones de uso
- [ ] Ajustar PHP-FPM si es necesario
- [ ] Limpiar cache antiguo
- [ ] Revisar performance Moodle

## 📈 Performance Benchmarks

### Antes de Optimización (Configuración Default)

```
Apache: MPM Prefork
PHP-FPM: max_children = 50
Sin SWAP
Sin OPcache

Resultados:
- Requests/sec: ~50
- Usuarios concurrentes: ~20
- OOM kill después de 2 días
```

### Después de Optimización

```
Apache: MPM Event
PHP-FPM: max_children = 15 (t4g.medium)
SWAP: 2GB
OPcache: Habilitado

Resultados:
- Requests/sec: ~120
- Usuarios concurrentes: ~100
- Estable por semanas
- Memoria disponible: 2-3 GB
```

## 🔧 Troubleshooting

### PHP-FPM No Inicia

```bash
# Ver logs
sudo journalctl -u php-fpm -n 50

# Verificar configuración
sudo php-fpm -t

# Problemas comunes
- max_children muy alto
- Socket/port ya en uso
- Permisos de archivos
```

### Performance Pobre

```bash
# Verificar PHP-FPM status
curl http://localhost/fpm-status

# Ver procesos activos
ps aux --sort=-%mem | head -20

# Verificar OPcache
php -i | grep opcache
```

### Out of Memory

```bash
# Ver logs de OOM
sudo dmesg | grep -i "out of memory"
sudo grep -i "killed process" /var/log/messages

# Soluciones inmediatas
1. Reiniciar PHP-FPM: sudo systemctl restart php-fpm
2. Reducir max_children
3. Agregar/aumentar SWAP
4. Upgrade de instancia
```

---

**Fecha:** 2026-02-11
**Versión:** 1.1.0
**Basado en:** Proyecto ACG Calidad - Optimización Real
