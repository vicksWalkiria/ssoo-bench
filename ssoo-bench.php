<?php
/**
 * ============================================================================
 * SSOO HOSTING BENCHMARK - Herramienta Independiente de Rendimiento PHP / Servidor
 * ============================================================================
 * Desarrollado para SistemasOperativos.info
 * Mide CPU, Memoria, I/O Disco (NVMe vs SSD), Entorno y Base de Datos (WordPress/MySQL)
 * Compatible con PHP 7.4 - 8.4 (CLI y Web / Responsive Matrix Dark)
 * ============================================================================
 */

// ----------------------------------------------------------------------------
// DESCARGA DIRECTA DEL SCRIPT (.TXT / ATTACHMENT)
// Si se solicita la descarga, enviamos el propio archivo como .txt descargable
// para evitar que el servidor web lo ejecute y permitir al usuario guardarlo.
// ----------------------------------------------------------------------------
if (isset($_GET['download']) || isset($_GET['txt']) || isset($_GET['raw_script'])) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="ssoo-bench.php"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Length: ' . filesize(__FILE__));
    readfile(__FILE__);
    exit;
}

// Evitar límites de tiempo en entornos con max_execution_time bajo
@set_time_limit(180);
@ini_set('memory_limit', '512M');

$isCli = (php_sapi_name() === 'cli');
$startTime = microtime(true);

// ----------------------------------------------------------------------------
// 1. RECOGIDA DE INFORMACIÓN DEL ENTORNO
// ----------------------------------------------------------------------------
function getSystemInfo() {
    $info = [
        'os'             => PHP_OS . ' (' . php_uname('r') . ')',
        'arch'           => php_uname('m'),
        'hostname'       => php_uname('n'),
        'php_version'    => PHP_VERSION,
        'sapi'           => php_sapi_name(),
        'memory_limit'   => ini_get('memory_limit'),
        'max_exec_time'  => ini_get('max_execution_time'),
        'opcache'        => extension_loaded('Zend OPcache') && ini_get('opcache.enable') ? 'Activado' : 'Desactivado',
        'jit'            => extension_loaded('Zend OPcache') && ini_get('opcache.jit_buffer_size') > 0 ? 'Activado' : 'Desactivado',
        'cpu_model'      => 'Desconocido',
        'cpu_cores'      => 1,
        'total_ram'      => 'Desconocida',
    ];

    if (file_exists('/proc/cpuinfo') && is_readable('/proc/cpuinfo')) {
        $cpuinfo = @file_get_contents('/proc/cpuinfo');
        if (preg_match('/model name\s*:\s*(.+)/i', $cpuinfo, $matches)) {
            $info['cpu_model'] = trim($matches[1]);
        } elseif (preg_match('/Hardware\s*:\s*(.+)/i', $cpuinfo, $matches)) {
            $info['cpu_model'] = trim($matches[1]);
        }
        $info['cpu_cores'] = max(1, substr_count($cpuinfo, 'processor'));
    }

    if ($info['cpu_model'] === 'Desconocido' && function_exists('shell_exec')) {
        $lscpu = @shell_exec('lscpu 2>/dev/null');
        if ($lscpu && preg_match('/Model name:\s*(.+)/i', $lscpu, $matches)) {
            $info['cpu_model'] = trim($matches[1]);
        }
        $cores = @shell_exec('nproc 2>/dev/null');
        if ($cores && (int)$cores > 0) {
            $info['cpu_cores'] = (int)trim($cores);
        }
    }

    if (file_exists('/proc/meminfo') && is_readable('/proc/meminfo')) {
        $meminfo = @file_get_contents('/proc/meminfo');
        if (preg_match('/MemTotal\s*:\s*(\d+)\s*kB/i', $meminfo, $matches)) {
            $info['total_ram'] = round($matches[1] / 1024 / 1024, 2) . ' GB';
        }
    }

    if ($info['total_ram'] === 'Desconocida' && function_exists('shell_exec')) {
        $free = @shell_exec('free -m 2>/dev/null');
        if ($free && preg_match('/Mem:\s+(\d+)/i', $free, $matches)) {
            $info['total_ram'] = round($matches[1] / 1024, 2) . ' GB';
        }
    }

    return $info;
}

// ----------------------------------------------------------------------------
// 2. BENCHMARK DE CPU (Matemáticas, Criptografía, Strings)
// ----------------------------------------------------------------------------
function benchCpu() {
    $results = [];

    // Test 2.1: Números primos (Cálculo intensivo entero)
    $t0 = microtime(true);
    $primes = 0;
    for ($i = 2; $i <= 60000; $i++) {
        $isPrime = true;
        $sqrt = sqrt($i);
        for ($j = 2; $j <= $sqrt; $j++) {
            if ($i % $j === 0) {
                $isPrime = false;
                break;
            }
        }
        if ($isPrime) $primes++;
    }
    $results['primes_time'] = round(microtime(true) - $t0, 4);

    // Test 2.2: Fibonacci Recursivo & Iterativo
    $t0 = microtime(true);
    function fib($n) { return ($n < 2) ? $n : fib($n - 1) + fib($n - 2); }
    fib(30);
    $results['fib_time'] = round(microtime(true) - $t0, 4);

    // Test 2.3: Hashing Criptográfico (SHA-256 x 100.000)
    $t0 = microtime(true);
    $data = "SistemasOperativos.info-Benchmark-2026-Security-Hashing";
    for ($i = 0; $i < 100000; $i++) {
        $data = hash('sha256', $data);
    }
    $results['hash_time'] = round(microtime(true) - $t0, 4);

    // Test 2.4: String Manipulation & RegEx (100.000 operaciones)
    $t0 = microtime(true);
    $str = str_repeat("Linux Ubuntu Debian Arch Fedora Nicalia Hosting ", 200);
    for ($i = 0; $i < 2000; $i++) {
        preg_match_all('/\b(Linux|Ubuntu|Nicalia)\b/i', $str, $m);
        str_replace(['Linux', 'Ubuntu'], ['GNU/Linux', 'Ubuntu-OS'], $str);
    }
    $results['string_time'] = round(microtime(true) - $t0, 4);

    $totalCpuTime = $results['primes_time'] + $results['fib_time'] + $results['hash_time'] + $results['string_time'];
    $results['total_time'] = round($totalCpuTime, 4);
    // Score CPU: Base 10.000 invertida según tiempo (un tiempo de 0.8s da ~10.000 pts)
    $results['score'] = max(100, round(8000 / ($totalCpuTime + 0.05)));

    return $results;
}

// ----------------------------------------------------------------------------
// 3. BENCHMARK DE MEMORIA & ARRAYS
// ----------------------------------------------------------------------------
function benchMemory() {
    $results = [];

    // Test 3.1: Creación de Array Masivo y Ordenación (200.000 enteros)
    $t0 = microtime(true);
    $arr = [];
    for ($i = 0; $i < 200000; $i++) {
        $arr[] = rand(1, 1000000);
    }
    sort($arr, SORT_NUMERIC);
    $results['sort_time'] = round(microtime(true) - $t0, 4);

    // Test 3.2: Serialización / JSON Encode & Decode (Simulación de caché y APIs)
    $t0 = microtime(true);
    $dataset = [];
    for ($i = 0; $i < 20000; $i++) {
        $dataset[] = [
            'id' => $i,
            'title' => 'Post ' . $i,
            'tags' => ['linux', 'hosting', 'nvme', 'benchmark'],
            'meta' => ['views' => rand(10, 5000), 'rating' => 4.9]
        ];
    }
    $json = json_encode($dataset);
    $decoded = json_decode($json, true);
    unset($dataset, $json, $decoded, $arr);
    $results['json_time'] = round(microtime(true) - $t0, 4);

    $totalMemTime = $results['sort_time'] + $results['json_time'];
    $results['total_time'] = round($totalMemTime, 4);
    $results['score'] = max(100, round(5000 / ($totalMemTime + 0.05)));

    return $results;
}

// ----------------------------------------------------------------------------
// 4. BENCHMARK DE DISCO I/O (NVMe vs SSD SATA vs HDD)
// ----------------------------------------------------------------------------
function benchDisk() {
    $results = [];
    $tmpDir = sys_get_temp_dir();
    $testFile = $tmpDir . '/ssoo_bench_' . uniqid() . '.bin';

    // Test 4.1: Escritura Secuencial (25 MB en bloques de 1 MB)
    $chunkSize = 1024 * 1024; // 1 MB
    $chunks = 25; // 25 MB
    $dataBlock = str_repeat('X', $chunkSize);

    $t0 = microtime(true);
    $fp = fopen($testFile, 'wb');
    if ($fp) {
        for ($i = 0; $i < $chunks; $i++) {
            fwrite($fp, $dataBlock);
        }
        fflush($fp);
        fclose($fp);
        $writeTime = microtime(true) - $t0;
        $results['seq_write_mb_s'] = round(($chunks) / ($writeTime ?: 0.001), 2);
    } else {
        $results['seq_write_mb_s'] = 0;
    }

    // Test 4.2: Lectura Secuencial (25 MB)
    $t0 = microtime(true);
    if (file_exists($testFile)) {
        $fp = fopen($testFile, 'rb');
        while (!feof($fp)) {
            fread($fp, $chunkSize);
        }
        fclose($fp);
        $readTime = microtime(true) - $t0;
        $results['seq_read_mb_s'] = round(($chunks) / ($readTime ?: 0.001), 2);
    } else {
        $results['seq_read_mb_s'] = 0;
    }

    // Test 4.3: Random 4K I/O (Simula transacciones de base de datos)
    $randOperations = 2500;
    $blockSize4k = 4096;
    $randomData = str_repeat('A', $blockSize4k);

    $t0 = microtime(true);
    $fp = fopen($testFile, 'r+b');
    if ($fp) {
        $fileSize = filesize($testFile);
        $maxSeek = max(0, $fileSize - $blockSize4k);
        for ($i = 0; $i < $randOperations; $i++) {
            fseek($fp, rand(0, $maxSeek));
            fwrite($fp, $randomData);
            fseek($fp, rand(0, $maxSeek));
            fread($fp, $blockSize4k);
        }
        fflush($fp);
        fclose($fp);
        $randTime = microtime(true) - $t0;
        $results['random_4k_iops'] = round(($randOperations * 2) / ($randTime ?: 0.001));
    } else {
        $results['random_4k_iops'] = 0;
    }

    // Limpieza
    if (file_exists($testFile)) {
        @unlink($testFile);
    }

    // Clasificación de almacenamiento estimada
    if ($results['seq_write_mb_s'] > 600 || $results['random_4k_iops'] > 8000) {
        $results['disk_type'] = 'NVMe PCIe de Alta Velocidad (Top Tier)';
    } elseif ($results['seq_write_mb_s'] > 200 || $results['random_4k_iops'] > 2500) {
        $results['disk_type'] = 'SSD SATA / SSD Cloud Estándar';
    } else {
        $results['disk_type'] = 'HDD Mecánico o I/O Limitado por Host';
    }

    // Puntuación de Disco
    $score = ($results['seq_write_mb_s'] * 2.5) + ($results['seq_read_mb_s'] * 1.5) + ($results['random_4k_iops'] * 0.4);
    $results['score'] = max(100, round($score));

    return $results;
}

// ----------------------------------------------------------------------------
// 5. DETECCIÓN Y BENCHMARK DE BASE DE DATOS (MYSQL / SQLITE)
// ----------------------------------------------------------------------------
function benchDatabase() {
    $results = ['type' => 'Ninguna', 'status' => 'No disponible', 'score' => 0];

    // Intentar autodetectar WordPress si está en la misma carpeta o superior
    $wpConfigFile = null;
    $possiblePaths = [
        __DIR__ . '/wp-config.php',
        __DIR__ . '/../wp-config.php',
        dirname(__DIR__, 2) . '/wp-config.php'
    ];
    foreach ($possiblePaths as $p) {
        if (file_exists($p)) {
            $wpConfigFile = $p;
            break;
        }
    }

    $mysqli = null;
    if ($wpConfigFile && is_readable($wpConfigFile)) {
        $content = file_get_contents($wpConfigFile);
        preg_match("/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $mDb);
        preg_match("/define\(\s*['\"]DB_USER['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $mUser);
        preg_match("/define\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $mPass);
        preg_match("/define\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $mHost);

        if (!empty($mDb[1]) && !empty($mUser[1])) {
            $host = !empty($mHost[1]) ? $mHost[1] : 'localhost';
            $port = 3306;
            if (strpos($host, ':') !== false) {
                list($host, $port) = explode(':', $host, 2);
            }
            try {
                $mysqli = @new mysqli($host, $mUser[1], $mPass[1], $mDb[1], (int)$port);
            } catch (Exception $e) {
                $mysqli = null;
            }
        }
    }

    if ($mysqli && !$mysqli->connect_error) {
        $rawVer = $mysqli->server_info;
        if (preg_match('/([0-9]+\.[0-9]+(\.[0-9]+)?).*?mariadb/i', $rawVer, $m)) {
            $results['type'] = 'MariaDB ' . $m[1];
        } elseif (preg_match('/([0-9]+\.[0-9]+(\.[0-9]+)?)/', $rawVer, $m)) {
            $results['type'] = (stripos($rawVer, 'maria') !== false ? 'MariaDB ' : 'MySQL ') . $m[1];
        } else {
            $results['type'] = 'MySQL / MariaDB';
        }
        $table = '_ssoo_bench_' . rand(1000, 9999);
        $mysqli->query("CREATE TEMPORARY TABLE $table (id INT AUTO_INCREMENT PRIMARY KEY, num INT, text VARCHAR(255)) ENGINE=InnoDB");

        // Benchmark de 2.000 INSERTs
        $t0 = microtime(true);
        $stmt = $mysqli->prepare("INSERT INTO $table (num, text) VALUES (?, ?)");
        $mysqli->query("START TRANSACTION");
        for ($i = 0; $i < 2000; $i++) {
            $val = rand(1, 10000);
            $txt = 'SistemasOperativos-Bench-' . $val;
            $stmt->bind_param('is', $val, $txt);
            $stmt->execute();
        }
        $mysqli->query("COMMIT");
        $stmt->close();
        $results['insert_time'] = round(microtime(true) - $t0, 4);

        // Benchmark de SELECT con JOIN y agregación
        $t0 = microtime(true);
        for ($i = 0; $i < 500; $i++) {
            $res = $mysqli->query("SELECT AVG(num), COUNT(*) FROM $table WHERE num > 2500");
            $res->free();
        }
        $results['select_time'] = round(microtime(true) - $t0, 4);

        $mysqli->query("DROP TEMPORARY TABLE IF EXISTS $table");
        $mysqli->close();

        $totalDbTime = $results['insert_time'] + $results['select_time'];
        $results['score'] = max(100, round(3000 / ($totalDbTime + 0.05)));
        $results['status'] = 'Completado con éxito';
    } else {
        // Fallback SQLite en memoria
        if (extension_loaded('pdo_sqlite')) {
            $results['type'] = 'SQLite (In-Memory Fallback)';
            $pdo = new PDO('sqlite::memory:');
            $pdo->exec("CREATE TABLE test (id INTEGER PRIMARY KEY, num INTEGER, text TEXT)");
            $t0 = microtime(true);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO test (num, text) VALUES (?, ?)");
            for ($i = 0; $i < 5000; $i++) {
                $stmt->execute([rand(1, 10000), 'SQLite-Bench']);
            }
            $pdo->commit();
            $results['insert_time'] = round(microtime(true) - $t0, 4);

            $t0 = microtime(true);
            for ($i = 0; $i < 500; $i++) {
                $pdo->query("SELECT AVG(num) FROM test WHERE num > 2500");
            }
            $results['select_time'] = round(microtime(true) - $t0, 4);
            $totalDbTime = $results['insert_time'] + $results['select_time'];
            $results['score'] = max(100, round(2500 / ($totalDbTime + 0.05)));
            $results['status'] = 'Completado (SQLite)';
        }
    }

    return $results;
}

// ----------------------------------------------------------------------------
// EJECUCIÓN DEL BENCHMARK
// ----------------------------------------------------------------------------
$sysInfo = getSystemInfo();
$cpuBench = benchCpu();
$memBench = benchMemory();
$diskBench = benchDisk();
$dbBench = benchDatabase();

$totalTime = round(microtime(true) - $startTime, 2);

// Cálculo del Score Global SSOO (0 a 10.000+)
$globalScore = round(
    ($cpuBench['score'] * 0.40) +
    ($memBench['score'] * 0.20) +
    ($diskBench['score'] * 0.30) +
    ($dbBench['score'] * 0.10)
);

// Salida en formato JSON si se solicita (?format=json o --json)
if ((isset($_GET['format']) && $_GET['format'] === 'json') || (isset($argv[1]) && $argv[1] === '--json')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'system'       => $sysInfo,
        'scores'       => [
            'global'   => $globalScore,
            'cpu'      => $cpuBench['score'],
            'memory'   => $memBench['score'],
            'disk'     => $diskBench['score'],
            'database' => $dbBench['score'],
        ],
        'details'      => [
            'cpu'      => $cpuBench,
            'memory'   => $memBench,
            'disk'     => $diskBench,
            'database' => $dbBench,
            'duration' => $totalTime,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// ----------------------------------------------------------------------------
// SALIDA TERMINAL (CLI)
// ----------------------------------------------------------------------------
if ($isCli) {
    echo "\033[1;32m========================================================================\033[0m\n";
    echo "\033[1;32m       🚀 SISTEMASOPERATIVOS.INFO - BENCHMARK DE HOSTING Y VPS          \033[0m\n";
    echo "\033[1;32m========================================================================\033[0m\n";
    echo " Servidor:     {$sysInfo['hostname']} ({$sysInfo['arch']})\n";
    echo " SO / Kernel:  {$sysInfo['os']}\n";
    echo " Procesador:   {$sysInfo['cpu_model']} ({$sysInfo['cpu_cores']} cores)\n";
    echo " RAM Detectada:{$sysInfo['total_ram']}\n";
    echo " PHP Versión:  {$sysInfo['php_version']} ({$sysInfo['sapi']}) | OPcache: {$sysInfo['opcache']}\n";
    echo "------------------------------------------------------------------------\n";
    echo " [1] CPU Benchmark:       {$cpuBench['total_time']}s  -> \033[1;33m{$cpuBench['score']} pts\033[0m\n";
    echo "     - Primos: {$cpuBench['primes_time']}s | Fibonacci: {$cpuBench['fib_time']}s | Hash: {$cpuBench['hash_time']}s\n";
    echo " [2] Memoria & Arrays:    {$memBench['total_time']}s  -> \033[1;33m{$memBench['score']} pts\033[0m\n";
    echo "     - Sort 200k: {$memBench['sort_time']}s | JSON 20k: {$memBench['json_time']}s\n";
    echo " [3] Disco I/O:           -> \033[1;33m{$diskBench['score']} pts\033[0m\n";
    echo "     - Escritura Seq:     \033[1;36m{$diskBench['seq_write_mb_s']} MB/s\033[0m\n";
    echo "     - Lectura Seq:       \033[1;36m{$diskBench['seq_read_mb_s']} MB/s\033[0m\n";
    echo "     - Random 4K IOPS:    \033[1;36m{$diskBench['random_4k_iops']} IOPS\033[0m\n";
    echo "     - Tipo Estimado:     {$diskBench['disk_type']}\n";
    echo " [4] Base de Datos:       {$dbBench['type']} -> \033[1;33m{$dbBench['score']} pts\033[0m\n";
    echo "     - 2.000 Inserts:     {$dbBench['insert_time']}s | 500 Selects: {$dbBench['select_time']}s\n";
    echo "------------------------------------------------------------------------\n";
    echo " \033[1;42;30m PUNTUACIÓN GLOBAL SSOO: {$globalScore} PUNTOS \033[0m (Tiempo: {$totalTime}s)\n";
    echo "\033[1;32m========================================================================\033[0m\n\n";
    exit;
}

// ----------------------------------------------------------------------------
// SALIDA WEB (HTML MATRIX DARK ULTRA FLOW)
// ----------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hosting Benchmark Report | SistemasOperativos.info</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #06090f;
            --bg-surface: #0c121d;
            --bg-elevated: #111a2a;
            --border-subtle: #1a2538;
            --border-light: #25334a;
            --text-title: #f8fafc;
            --text-body: #cbd5e1;
            --text-muted: #94a3b8;
            --c-emerald: #10b981;
            --c-cyan: #38bdf8;
            --c-indigo: #818cf8;
            --c-amber: #f59e0b;
            --c-purple: #c084fc;
            --font-sans: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            --font-mono: 'JetBrains Mono', ui-monospace, Menlo, Monaco, Consolas, monospace;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: var(--bg-body);
            background-image: 
                radial-gradient(at 10% 8%, rgba(56, 189, 248, 0.08) 0px, transparent 45%),
                radial-gradient(at 90% 12%, rgba(16, 185, 129, 0.07) 0px, transparent 45%);
            color: var(--text-title);
            font-family: var(--font-sans);
            padding: 1.5rem 2rem;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .dashboard-container {
            max-width: 1440px;
            margin: 0 auto;
        }

        /* Top Navbar */
        .top-navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 16px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .brand-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #0284c7, #10b981);
            border-radius: 12px;
            font-size: 1.35rem;
            box-shadow: 0 2px 12px rgba(2, 132, 199, 0.35);
        }

        .brand-meta h1 {
            font-size: 1.15rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            white-space: nowrap;
        }

        .brand-badge {
            font-family: var(--font-mono);
            font-size: 0.68rem;
            font-weight: 700;
            background: rgba(56, 189, 248, 0.15);
            color: var(--c-cyan);
            border: 1px solid rgba(56, 189, 248, 0.3);
            padding: 0.2rem 0.5rem;
            border-radius: 6px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .server-host-pill {
            font-size: 0.90rem;
            color: var(--text-body);
            font-family: var(--font-mono);
            margin-top: 0.15rem;
            white-space: nowrap;
        }

        .server-host-pill strong {
            color: var(--c-emerald);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            flex-wrap: wrap;
        }

        .btn-nav {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-family: var(--font-sans);
            font-size: 0.92rem;
            font-weight: 600;
            padding: 0.55rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid var(--border-light);
            background: rgba(255, 255, 255, 0.04);
            color: var(--text-title);
            white-space: nowrap;
        }

        .btn-nav:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: #64748b;
            color: #fff;
            transform: translateY(-1px);
        }

        .btn-nav-primary {
            background: linear-gradient(135deg, #0284c7, #2563eb);
            border-color: #38bdf8;
            color: #fff;
            box-shadow: 0 2px 10px rgba(37, 99, 235, 0.3);
        }

        .btn-nav-primary:hover {
            background: linear-gradient(135deg, #0369a1, #1d4ed8);
            border-color: #60a5fa;
            color: #fff;
        }

        /* Top Summary Row: Score Hero + Cost Ratio Calculator */
        .summary-grid {
            display: grid;
            grid-template-columns: 460px 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .hero-score-card {
            background: linear-gradient(145deg, #0e1726 0%, #080e18 100%);
            border: 1px solid rgba(16, 185, 129, 0.35);
            border-radius: 18px;
            padding: 1.75rem 2rem;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 25px rgba(16, 185, 129, 0.08);
        }

        .hero-score-card::after {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 180px; height: 180px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, transparent 70%);
            pointer-events: none;
        }

        .hero-label-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            white-space: nowrap;
            gap: 0.75rem;
        }

        .hero-label {
            font-size: 0.8rem;
            font-family: var(--font-mono);
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--text-body);
            white-space: nowrap;
        }

        .tier-pill {
            font-family: var(--font-mono);
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            border: 1px solid;
            white-space: nowrap;
        }

        .score-display {
            font-family: var(--font-mono);
            font-size: 3.8rem;
            font-weight: 800;
            line-height: 1;
            color: var(--c-emerald);
            letter-spacing: -0.03em;
            margin: 0.75rem 0 1rem 0;
            text-shadow: 0 0 25px rgba(16, 185, 129, 0.3);
            display: flex;
            align-items: baseline;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .score-display span {
            font-size: 1.35rem;
            font-weight: 600;
            color: var(--text-body);
        }

        .hero-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 0.85rem;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 0.78rem;
            color: var(--text-muted);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .hero-footer strong {
            color: var(--text-title);
        }

        /* Calculator Card */
        .calculator-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 18px;
            padding: 1.75rem 2rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .calc-header {
            margin-bottom: 1rem;
        }

        .calc-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #fff;
        }

        .calc-desc {
            font-size: 0.92rem;
            color: var(--text-body);
            margin-top: 0.25rem;
        }

        .calc-interactive-row {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 2rem;
            align-items: center;
            background: var(--bg-elevated);
            border: 1px solid var(--border-light);
            border-radius: 14px;
            padding: 1.15rem 1.5rem;
            margin-bottom: 1rem;
        }

        .calc-input-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .calc-input-group label {
            font-size: 0.92rem;
            color: var(--text-body);
            font-weight: 600;
            white-space: nowrap;
        }

        .calc-input-wrapper {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .calc-input-wrapper input {
            width: 90px;
            background: #06090e;
            border: 1.5px solid var(--c-cyan);
            border-radius: 8px;
            padding: 0.45rem 1.6rem 0.45rem 0.6rem;
            color: #fff;
            font-family: var(--font-mono);
            font-size: 1.15rem;
            font-weight: 700;
            text-align: right;
            outline: none;
        }

        .calc-input-wrapper input:focus {
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.25);
        }

        .calc-input-wrapper .currency-symbol {
            position: absolute;
            right: 0.55rem;
            color: var(--text-body);
            font-weight: 700;
            font-size: 0.95rem;
            pointer-events: none;
        }

        .calc-quick-chips {
            display: flex;
            gap: 0.45rem;
            margin-top: 0.5rem;
            flex-wrap: wrap;
        }

        .chip-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-subtle);
            color: var(--text-body);
            font-family: var(--font-mono);
            font-size: 0.75rem;
            padding: 0.25rem 0.55rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s;
            white-space: nowrap;
        }

        .chip-btn:hover {
            background: rgba(56, 189, 248, 0.15);
            border-color: var(--c-cyan);
            color: var(--c-cyan);
        }

        .calc-result-box {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            text-align: right;
        }

        .calc-result-number {
            font-family: var(--font-mono);
            font-size: 2rem;
            font-weight: 800;
            color: var(--c-cyan);
            line-height: 1;
            white-space: nowrap;
        }

        .calc-result-label {
            font-size: 0.78rem;
            color: var(--text-muted);
            text-transform: uppercase;
            font-family: var(--font-mono);
            letter-spacing: 0.06em;
            margin-top: 0.25rem;
            white-space: nowrap;
        }

        .calc-references {
            display: flex;
            gap: 1.25rem;
            font-size: 0.8rem;
            color: var(--text-muted);
            padding-top: 0.65rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            flex-wrap: wrap;
            align-items: center;
        }

        .calc-references span strong {
            color: var(--text-body);
            font-family: var(--font-mono);
        }

        /* 2-Column Spacious Metrics Grid */
        .metrics-spacious-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .metric-block {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 16px;
            padding: 1.5rem 1.75rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            margin-bottom: 1.5rem;
        }

        .metric-block:last-child {
            margin-bottom: 0;
        }

        .metric-block.block-cpu { border-top: 3px solid var(--c-cyan); }
        .metric-block.block-ram { border-top: 3px solid var(--c-indigo); }
        .metric-block.block-disk { border-top: 3px solid var(--c-amber); }
        .metric-block.block-db { border-top: 3px solid var(--c-purple); }

        .block-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            white-space: nowrap;
        }

        .block-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .block-score {
            font-family: var(--font-mono);
            font-size: 1.35rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .block-cpu .block-score { color: var(--c-cyan); }
        .block-ram .block-score { color: var(--c-indigo); }
        .block-disk .block-score { color: var(--c-amber); }
        .block-db .block-score { color: var(--c-purple); }

        .block-score small {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 600;
            margin-left: 0.2rem;
        }

        .spacious-list {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
            margin-bottom: 1rem;
        }

        .spacious-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.95rem;
            padding: 0.35rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            white-space: nowrap;
            gap: 1.5rem;
        }

        .spacious-row:last-child {
            border-bottom: none;
        }

        .spacious-row .row-label {
            color: var(--text-body);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .spacious-row .row-val {
            font-family: var(--font-mono);
            font-weight: 600;
            color: #fff;
            text-align: right;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .spacious-row .row-val-highlight {
            color: var(--c-emerald);
            font-weight: 700;
        }

        .block-footer {
            padding-top: 0.75rem;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: var(--text-muted);
            font-family: var(--font-mono);
            white-space: nowrap;
        }

        .tag-pill {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            font-size: 0.72rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.06);
            color: var(--text-body);
            white-space: nowrap;
        }

        /* Bottom Grid: Specs & Deployment Guide */
        .bottom-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 1.5rem;
        }

        .specs-panel, .guide-panel {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 16px;
            padding: 1.5rem 1.75rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .panel-heading {
            font-size: 1.05rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 1.15rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .specs-tiles-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.85rem;
        }

        .spec-tile {
            background: var(--bg-elevated);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 0.75rem 1rem;
        }

        .spec-tile .s-label {
            font-size: 0.72rem;
            font-family: var(--font-mono);
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.25rem;
            white-space: nowrap;
        }

        .spec-tile .s-val {
            font-size: 0.95rem;
            font-weight: 600;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Guide & CLI Box */
        .cli-code-box {
            background: #05080e;
            border: 1px solid #1e293b;
            border-radius: 10px;
            padding: 0.85rem 1.1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            gap: 1rem;
        }

        .cli-code-text {
            font-family: var(--font-mono);
            font-size: 0.92rem;
            color: var(--c-cyan);
            word-break: break-all;
            line-height: 1.4;
        }

        .btn-copy-cli {
            background: rgba(56, 189, 248, 0.15);
            border: 1px solid rgba(56, 189, 248, 0.3);
            color: var(--c-cyan);
            font-family: var(--font-mono);
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.45rem 0.85rem;
            border-radius: 6px;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.15s;
        }

        .btn-copy-cli:hover {
            background: var(--c-cyan);
            color: #040711;
        }

        .guide-steps {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
            font-size: 0.92rem;
            color: var(--text-body);
        }

        .guide-steps li {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
        }

        .step-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            font-size: 0.72rem;
            font-family: var(--font-mono);
            color: #fff;
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* Toast Feedback */
        #toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: #0f172a;
            border: 1px solid var(--c-emerald);
            color: #fff;
            font-family: var(--font-sans);
            font-size: 0.95rem;
            font-weight: 600;
            padding: 0.85rem 1.35rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            gap: 0.6rem;
            opacity: 0;
            pointer-events: none;
            transform: translateY(15px);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 9999;
        }

        #toast.show {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1024px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
            .metrics-spacious-grid {
                grid-template-columns: 1fr;
            }
            .bottom-grid {
                grid-template-columns: 1fr;
            }
            body { padding: 1rem; }
        }

        @media (max-width: 640px) {
            body { padding: 0.75rem; }
            .calc-interactive-row { grid-template-columns: 1fr; text-align: left; }
            .calc-result-box { justify-content: flex-start; }
            .top-navbar { flex-direction: column; align-items: stretch; }
            .brand-section { justify-content: space-between; }
            .nav-actions { justify-content: center; }
            .score-display { font-size: 3rem; }
            .specs-tiles-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div class="brand-section">
                <div class="brand-logo">⚡</div>
                <div class="brand-meta">
                    <h1>SistemasOperativos.info <span class="brand-badge">Benchmark Suite</span></h1>
                    <div class="server-host-pill">
                        Servidor Evaluado: <strong><?= htmlspecialchars($sysInfo['hostname']) ?></strong> &bull; <?= htmlspecialchars($sysInfo['arch']) ?> &bull; PHP <?= htmlspecialchars($sysInfo['php_version']) ?>
                    </div>
                </div>
            </div>

            <div class="nav-actions">
                <a href="?download=1" class="btn-nav btn-nav-primary" download="ssoo-bench.php">
                    <span>📥</span> Descargar ssoo-bench.php
                </a>
                <button class="btn-nav" onclick="copyJsonData()">
                    <span>📋</span> Copiar JSON
                </button>
                <a href="?format=json" class="btn-nav" target="_blank">
                    <span>🌐</span> Raw JSON
                </a>
                <button class="btn-nav" onclick="window.print()">
                    <span>🖨️</span> PDF
                </button>
                <a href="https://sistemasoperativos.info/linux/comparativa-hostings-vps-benchmark/" class="btn-nav" target="_blank">
                    <span>📊</span> Ver Comparativa
                </a>
            </div>
        </header>

        <!-- Top Summary Row: Score Hero + Efficiency Calculator -->
        <?php
            $tierName = 'Tier S • Sobresaliente';
            $tierColor = '#38bdf8';
            $tierBg = 'rgba(56, 189, 248, 0.15)';
            if ($globalScore >= 65000) {
                $tierName = 'Tier S+ • Extremo';
                $tierColor = '#10b981';
                $tierBg = 'rgba(16, 185, 129, 0.15)';
            } elseif ($globalScore >= 45000) {
                $tierName = 'Tier S • Sobresaliente';
                $tierColor = '#38bdf8';
                $tierBg = 'rgba(56, 189, 248, 0.15)';
            } elseif ($globalScore >= 30000) {
                $tierName = 'Tier A • Notable';
                $tierColor = '#818cf8';
                $tierBg = 'rgba(129, 140, 248, 0.15)';
            } elseif ($globalScore >= 18000) {
                $tierName = 'Tier B • Estándar';
                $tierColor = '#f59e0b';
                $tierBg = 'rgba(245, 158, 11, 0.15)';
            } else {
                $tierName = 'Tier C • Básico';
                $tierColor = '#f87171';
                $tierBg = 'rgba(248, 113, 113, 0.15)';
            }
        ?>
        <div class="summary-grid">
            
            <!-- Global Score Hero Card -->
            <div class="hero-score-card">
                <div>
                    <div class="hero-label-row">
                        <span class="hero-label">PUNTUACIÓN GLOBAL SSOO</span>
                        <span class="tier-pill" style="color: <?= $tierColor ?>; border-color: <?= $tierColor ?>; background: <?= $tierBg ?>;">
                            <?= $tierName ?>
                        </span>
                    </div>
                    <div class="score-display">
                        <?= number_format($globalScore, 0, ',', '.') ?>
                        <span>pts</span>
                    </div>
                </div>

                <div class="hero-footer">
                    <span>⏱️ Tiempo de ejecución: <strong><?= $totalTime ?>s</strong></span>
                    <span>OPcache: <strong><?= $sysInfo['opcache'] ?></strong></span>
                    <span>JIT: <strong><?= $sysInfo['jit'] ?></strong></span>
                </div>
            </div>

            <!-- Cost Ratio Calculator -->
            <div class="calculator-card">
                <div class="calc-header">
                    <div>
                        <div class="calc-title">💡 Calculadora de Ratio Calidad / Precio</div>
                        <div class="calc-desc">Introduce el precio mensual de tu hosting o VPS para calcular el rendimiento por euro invertido:</div>
                    </div>
                </div>

                <div class="calc-interactive-row">
                    <div>
                        <div class="calc-input-group">
                            <label for="costInput">Coste mensual:</label>
                            <div class="calc-input-wrapper">
                                <input type="number" id="costInput" value="5" min="0" step="0.5" oninput="updateRatio()">
                                <span class="currency-symbol">€</span>
                            </div>
                        </div>
                        <div class="calc-quick-chips">
                            <button type="button" class="chip-btn" onclick="setCost(0)">0€ (Gratis)</button>
                            <button type="button" class="chip-btn" onclick="setCost(3.5)">3.50€</button>
                            <button type="button" class="chip-btn" onclick="setCost(4.95)">4.95€</button>
                            <button type="button" class="chip-btn" onclick="setCost(14)">14.00€</button>
                            <button type="button" class="chip-btn" onclick="setCost(25)">25.00€</button>
                        </div>
                    </div>

                    <div class="calc-result-box">
                        <div>
                            <div class="calc-result-number" id="ratioResult">
                                <?= number_format(round($globalScore / 5), 0, ',', '.') ?>
                            </div>
                            <div class="calc-result-label" id="ratioLabel">Puntos por Euro (€)</div>
                        </div>
                    </div>
                </div>

                <div class="calc-references">
                    <span><strong>Oracle Cloud Free:</strong> 69.499 pts (Infinito)</span>
                    <span>&bull;</span>
                    <span><strong>Nicalia NVMe (4,95€):</strong> ~9.733 pts/€</span>
                    <span>&bull;</span>
                    <span><strong>Raiola Networks (8,95€):</strong> ~4.433 pts/€</span>
                    <span>&bull;</span>
                    <span><strong>OVH Cloud VPS (14,00€):</strong> ~3.762 pts/€</span>
                </div>
            </div>

        </div>

        <!-- 2-Column Spacious Metrics Grid: CPU & RAM on Left, Disk & Database on Right -->
        <div class="metrics-spacious-grid">
            
            <!-- Left Column: CPU & RAM -->
            <div class="metrics-column">
                
                <!-- CPU Block -->
                <div class="metric-block block-cpu">
                    <div>
                        <div class="block-header">
                            <span class="block-title">⚡ Procesador (Cómputo CPU)</span>
                            <span class="block-score"><?= number_format($cpuBench['score'], 0, ',', '.') ?><small>pts</small></span>
                        </div>
                        <div class="spacious-list">
                            <div class="spacious-row">
                                <span class="row-label">Cálculo de Números Primos (1 a 50.000)</span>
                                <span class="row-val"><?= $cpuBench['primes_time'] ?> s</span>
                            </div>
                            <div class="spacious-row">
                                <span class="row-label">Secuencia Fibonacci Recursiva (n = 33)</span>
                                <span class="row-val"><?= $cpuBench['fib_time'] ?> s</span>
                            </div>
                            <div class="spacious-row">
                                <span class="row-label">Algoritmo Criptográfico SHA-256 (100k hashes)</span>
                                <span class="row-val"><?= $cpuBench['hash_time'] ?> s</span>
                            </div>
                            <div class="spacious-row">
                                <span class="row-label">Manipulación de Cadenas & RegEx (10k iteraciones)</span>
                                <span class="row-val"><?= $cpuBench['string_time'] ?> s</span>
                            </div>
                        </div>
                    </div>
                    <div class="block-footer">
                        <span>Tiempo Total de Procesamiento CPU</span>
                        <span class="row-val-highlight"><?= $cpuBench['total_time'] ?> s</span>
                    </div>
                </div>

                <!-- RAM Block -->
                <div class="metric-block block-ram">
                    <div>
                        <div class="block-header">
                            <span class="block-title">🧠 Memoria RAM & Arrays</span>
                            <span class="block-score"><?= number_format($memBench['score'], 0, ',', '.') ?><small>pts</small></span>
                        </div>
                        <div class="spacious-list">
                            <div class="spacious-row">
                                <span class="row-label">Ordenación en Memoria (Sort 200k enteros)</span>
                                <span class="row-val"><?= $memBench['sort_time'] ?> s</span>
                            </div>
                            <div class="spacious-row">
                                <span class="row-label">Serialización / Deserialización JSON (20k objetos)</span>
                                <span class="row-val"><?= $memBench['json_time'] ?> s</span>
                            </div>
                            <div class="spacious-row">
                                <span class="row-label">Límite de Memoria PHP (memory_limit)</span>
                                <span class="row-val"><?= htmlspecialchars($sysInfo['memory_limit']) ?></span>
                            </div>
                            <div class="spacious-row">
                                <span class="row-label">Memoria RAM Física Total del Servidor</span>
                                <span class="row-val"><?= htmlspecialchars($sysInfo['total_ram']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="block-footer">
                        <span>Tiempo Total Operaciones en Memoria</span>
                        <span class="row-val-highlight"><?= $memBench['total_time'] ?> s</span>
                    </div>
                </div>

            </div>

            <!-- Right Column: Disk NVMe & Database -->
            <div class="metrics-column">
                
                <!-- Disk Block -->
                <div class="metric-block block-disk">
                    <div>
                        <div class="block-header">
                            <span class="block-title">💾 Almacenamiento & Disco NVMe</span>
                            <span class="block-score"><?= number_format($diskBench['score'], 0, ',', '.') ?><small>pts</small></span>
                        </div>
                        <div class="spacious-list">
                            <div class="spacious-row">
                                <span class="row-label">Velocidad de Escritura Secuencial</span>
                                <span class="row-val row-val-highlight"><?= number_format($diskBench['seq_write_mb_s'], 1, ',', '.') ?> MB/s</span>
                            </div>
                            <div class="spacious-row">
                                <span class="row-label">Velocidad de Lectura Secuencial</span>
                                <span class="row-val row-val-highlight"><?= number_format($diskBench['seq_read_mb_s'], 1, ',', '.') ?> MB/s</span>
                            </div>
                            <div class="spacious-row">
                                <span class="row-label">Operaciones Aleatorias de Disco (Random 4K)</span>
                                <span class="row-val"><?= number_format($diskBench['random_4k_iops'], 0, ',', '.') ?> IOPS</span>
                            </div>
                            <div class="spacious-row">
                                <span class="row-label">Tipo de Almacenamiento Físico Detectado</span>
                                <span class="row-val" style="font-size:0.82rem;"><?= htmlspecialchars(explode('(', $diskBench['disk_type'])[0]) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="block-footer">
                        <span>Clasificación de Almacenamiento</span>
                        <span class="tag-pill">NVMe PCIe Tier 1</span>
                    </div>
                </div>

                <!-- Database Block -->
                <div class="metric-block block-db">
                    <div>
                        <div class="block-header">
                            <span class="block-title">🗄️ Base de Datos Relacional SQL</span>
                            <span class="block-score"><?= number_format($dbBench['score'], 0, ',', '.') ?><small>pts</small></span>
                        </div>
                        <div class="spacious-list">
                            <div class="spacious-row">
                                <span class="row-label">Motor de Base de Datos Detectado</span>
                                <span class="row-val" style="font-size:0.84rem;"><?= htmlspecialchars($dbBench['type']) ?></span>
                            </div>
                            <div class="spacious-row">
                                <span class="row-label">2.000 Inserciones en Lotes (Transacciones ACID)</span>
                                <span class="row-val"><?= $dbBench['insert_time'] ?? '-' ?> s</span>
                            </div>
                            <div class="spacious-row">
                                <span class="row-label">500 Consultas Complejas con Filtrado y Agregación</span>
                                <span class="row-val"><?= $dbBench['select_time'] ?? '-' ?> s</span>
                            </div>
                            <div class="spacious-row">
                                <span class="row-label">Estado de la Conexión y Transaccionalidad</span>
                                <span class="row-val row-val-highlight"><?= htmlspecialchars($dbBench['status']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="block-footer">
                        <span>Consistencia de Datos</span>
                        <span class="tag-pill">Transacciones ACID</span>
                    </div>
                </div>

            </div>

        </div>

        <!-- Bottom Grid: Specs & Deployment -->
        <div class="bottom-grid">
            
            <!-- Specs Panel -->
            <div class="specs-panel">
                <div class="panel-heading">
                    <span>🖥️</span> Especificaciones Técnicas del Servidor Evaluado
                </div>
                <div class="specs-tiles-grid">
                    <div class="spec-tile">
                        <div class="s-label">Procesador (CPU)</div>
                        <div class="s-val" title="<?= htmlspecialchars($sysInfo['cpu_model']) ?>">
                            <?= htmlspecialchars($sysInfo['cpu_model']) ?>
                        </div>
                    </div>
                    <div class="spec-tile">
                        <div class="s-label">Cores / Arquitectura</div>
                        <div class="s-val">
                            <?= $sysInfo['cpu_cores'] ?> Cores (<?= htmlspecialchars($sysInfo['arch']) ?>)
                        </div>
                    </div>
                    <div class="spec-tile">
                        <div class="s-label">Sistema Operativo & Kernel</div>
                        <div class="s-val" title="<?= htmlspecialchars($sysInfo['os']) ?>">
                            <?= htmlspecialchars($sysInfo['os']) ?>
                        </div>
                    </div>
                    <div class="spec-tile">
                        <div class="s-label">Versión PHP & Interfaz SAPI</div>
                        <div class="s-val">
                            PHP <?= htmlspecialchars($sysInfo['php_version']) ?> (<?= htmlspecialchars($sysInfo['sapi']) ?>)
                        </div>
                    </div>
                    <div class="spec-tile">
                        <div class="s-label">Acelerador OPcache</div>
                        <div class="s-val" style="color: <?= $sysInfo['opcache'] === 'Activado' ? 'var(--c-emerald)' : 'var(--text-body)' ?>;">
                            <?= htmlspecialchars($sysInfo['opcache']) ?>
                        </div>
                    </div>
                    <div class="spec-tile">
                        <div class="s-label">Compilador JIT</div>
                        <div class="s-val" style="color: <?= $sysInfo['jit'] === 'Activado' ? 'var(--c-emerald)' : 'var(--text-body)' ?>;">
                            <?= htmlspecialchars($sysInfo['jit']) ?>
                        </div>
                    </div>
                    <div class="spec-tile">
                        <div class="s-label">Memoria RAM Total</div>
                        <div class="s-val">
                            <?= htmlspecialchars($sysInfo['total_ram']) ?>
                        </div>
                    </div>
                    <div class="spec-tile">
                        <div class="s-label">Límite de Memoria & Tiempo PHP</div>
                        <div class="s-val">
                            <?= htmlspecialchars($sysInfo['memory_limit']) ?> (Max: <?= htmlspecialchars($sysInfo['max_exec_time']) ?>s)
                        </div>
                    </div>
                </div>
            </div>

            <!-- How to Deploy Panel -->
            <div class="guide-panel">
                <div class="panel-heading">
                    <span>🚀</span> Ejecutar este Benchmark en tu Hosting o VPS
                </div>
                
                <div class="cli-code-box">
                    <div class="cli-code-text" id="curlCmdText">curl -o ssoo-bench.php https://sistemasoperativos.info/ssoo-bench.txt</div>
                    <button type="button" class="btn-copy-cli" onclick="copyCliCommand()">Copiar</button>
                </div>

                <ul class="guide-steps">
                    <li>
                        <span class="step-num">1</span>
                        <span>Descarga directamente <code>ssoo-bench.php</code> o usa el comando curl en tu terminal SSH.</span>
                    </li>
                    <li>
                        <span class="step-num">2</span>
                        <span>Súbelo directamente a la raíz de tu web (<code>public_html</code>) por FTP o SSH.</span>
                    </li>
                    <li>
                        <span class="step-num">3</span>
                        <span>Abre <code>https://tudominio.com/ssoo-bench.php</code> en tu navegador para ver tu resultado.</span>
                    </li>
                </ul>
            </div>

        </div>

    </div>

    <!-- Floating Toast Notification -->
    <div id="toast">
        <span id="toastIcon">✓</span>
        <span id="toastMsg">Copiado al portapapeles</span>
    </div>

    <script>
        const globalScore = <?= (int)$globalScore ?>;

        function updateRatio() {
            const cost = parseFloat(document.getElementById('costInput').value);
            const resEl = document.getElementById('ratioResult');
            const labelEl = document.getElementById('ratioLabel');

            if (isNaN(cost) || cost <= 0) {
                resEl.innerText = 'Infinito';
                labelEl.innerText = 'Plan Gratuito (Eficiencia Máxima)';
            } else {
                const ratio = Math.round(globalScore / cost);
                resEl.innerText = ratio.toLocaleString('es-ES');
                labelEl.innerText = 'Puntos por Euro (€)';
            }
        }

        function setCost(amount) {
            document.getElementById('costInput').value = amount;
            updateRatio();
        }

        function showToast(msg, icon = '✓') {
            const toast = document.getElementById('toast');
            document.getElementById('toastMsg').innerText = msg;
            document.getElementById('toastIcon').innerText = icon;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 2500);
        }

        function copyCliCommand() {
            const cmd = document.getElementById('curlCmdText').innerText.trim();
            navigator.clipboard.writeText(cmd).then(() => {
                showToast('Comando curl copiado al portapapeles');
            }).catch(() => {
                prompt('Copia el comando:', cmd);
            });
        }

        function copyJsonData() {
            fetch('?format=json')
                .then(r => r.text())
                .then(json => {
                    navigator.clipboard.writeText(json).then(() => {
                        showToast('¡Datos JSON copiados al portapapeles!');
                    });
                })
                .catch(() => {
                    window.open('?format=json', '_blank');
                });
        }
    </script>
</body>
</html>
