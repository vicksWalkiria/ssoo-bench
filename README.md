# 🚀 SSOO Benchmark Suite

> **Herramienta independiente de pruebas de rendimiento para servidores, Hosting Compartido y VPS.**  
> Desarrollada por [SistemasOperativos.info](https://sistemasoperativos.info) para medir con transparencia la potencia real de CPU, memoria, discos NVMe y bases de datos.

[![PHP Version](https://img.shields.io/badge/PHP-7.4%20|%208.0%20|%208.1%20|%208.2%20|%208.3%20|%208.4-777bb4.svg)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Community Driven](https://img.shields.io/badge/Community-Open%20Benchmarks-00ff66.svg)](https://sistemasoperativos.info/linux/comparativa-hostings-vps-benchmark/)

---

## ⚡ Características Principales

* **Cero dependencias:** Un único archivo PHP autónomo (`ssoo-bench.php`) sin librerías externas ni extensiones raras.
* **Doble interfaz:**
  * **Modo CLI (Terminal):** Salida limpia con colores ANSI para administradores de sistemas y SSH.
  * **Modo Web:** Interfaz gráfica responsive en modo oscuro *Ultra-Flow* con auditoría de hardware y calculadora en tiempo real de **Puntos por Euro**.
* **Detección nativa de WordPress:** Si se aloja en una raíz con `wp-config.php`, conecta automáticamente a la base de datos MySQL/MariaDB real sin pedir contraseñas. Si no hay base de datos disponible, realiza fallback a SQLite en memoria.
* **Anti-Caché:** Pruebas directas de disco sin sesgo por buffers del sistema operativo.

---

## 🧪 ¿Qué mide exactamente?

1. **CPU & Cómputo PHP:**
   * Cálculo de números primos (algoritmo iterativo intensivo).
   * Generación recursiva de Fibonacci.
   * Hashes criptográficos masivos (`md5`, `sha256`, `sha512`).
   * Manipulación y concatenación de cadenas en bucle.
2. **Memoria RAM & Estructuras de Datos:**
   * Ordenación de arrays grandes (200.000 elementos aleatorios en memoria).
   * Serialización y parseo de estructuras JSON complejas (20.000 nodos).
3. **Almacenamiento & Disco I/O (NVMe vs SSD):**
   * **Escritura secuencial (MB/s):** Bloques de 64 MB con volcado `fsync()`.
   * **Lectura secuencial (MB/s):** Lectura limpia midiendo el ancho de banda del bus.
   * **Acceso aleatorio 4K (IOPS):** Simulación de cargas reales de servidores web y bases de datos.
4. **Base de Datos (MySQL / MariaDB / SQLite):**
   * **Inserciones masivas bajo transacción ACID:** 2.000 registros con control de integridad.
   * **Consultas complejas:** 500 `SELECT` con cláusulas `WHERE`, agregaciones (`AVG`), ordenación y conteos.

---

## 💻 Cómo Ejecutarlo

### 1. Vía Terminal / SSH (Recomendado para VPS)

```bash
# Descargar y ejecutar directamente con PHP
curl -sLO https://raw.githubusercontent.com/vicksWalkiria/ssoo-bench/main/ssoo-bench.php
php ssoo-bench.php
```

Para exportar el resultado en formato JSON estándar:
```bash
php ssoo-bench.php --json > mi-servidor.json
```

### 2. Vía Web (Para Hosting Compartido / cPanel / Plesk)

1. Descarga el archivo [`ssoo-bench.php`](ssoo-bench.php).
2. Súbelo a la carpeta pública de tu hosting (por ejemplo `public_html/ssoo-bench.php`).
3. Ábrelo en tu navegador: `https://tudominio.com/ssoo-bench.php`.
4. Visualiza los resultados en vivo y descárgalos en JSON con el botón inferior.

---

## 📊 Resultados Auditados de la Comunidad (`results/`)

En el directorio [`results/`](results/) se recopilan los benchmarks reales ejecutados por la comunidad:

| Proveedor | Entorno / Plan | CPU | RAM | Disco 4K IOPS | Puntuación Global | Archivo JSON |
| :--- | :--- | :--- | :---: | :---: | :---: | :--- |
| **Oracle Cloud** | Always Free Ampere A1 (ARM64) | 2 Cores ARM | 12 GB | 429.146 | **69.499 pts** | [`oracle-cloud-free.json`](results/oracle-cloud-free.json) |
| **OVH Cloud** | VPS Essential (KVM) | Haswell (6 cores) | 12 GB | 321.624 | **52.676 pts** | [`ovh-cloud-vps.json`](results/ovh-cloud-vps.json) |
| **LucusHost** *(hl247)* | Hosting SSD Senior (Legacy 3) | AMD EPYC 4584PX | LVE (98 GB) | 225.224 | **51.256 pts** | [`lucushost-ssd-senior.json`](results/lucushost-ssd-senior.json) |
| **Nicalia** | Hosting Elástico NVMe (LiteSpeed) | Xeon Gold 6248R | LVE | 280.000 | **48.170 pts** | [`nicalia-hosting.json`](results/nicalia-hosting.json) |
| **Raiola Networks** *(ha1006)* | Hosting LiteSpeed HA | Xeon E5-2687W v3 | LVE | 204.565 | **39.677 pts** | [`raiola-networks-ha1006.json`](results/raiola-networks-ha1006.json) |
| **Raiola Networks** *(Colaborador)* | Hosting LiteSpeed (`com1023`) | Xeon E5-2687W v3 | LVE | 200.569 | **37.276 pts** | [`raiola-networks-com1023.json`](results/raiola-networks-com1023.json) |
| **BanaHosting** *(bh8962)* | [Bana Corporate](https://www.banahosting.com/web-hosting/) | Xeon E5-2699 v4 | LVE (251 GB) | 103.838 | **19.980 pts** | [`banahosting-bana-corporate.json`](results/banahosting-bana-corporate.json) |
| **LucusHost** *(hl111)* | Hosting WP Master | AMD EPYC 7351P | LVE (125 GB) | 58.525 | **19.525 pts** | [`lucushost-wp-master.json`](results/lucushost-wp-master.json) |

Puedes consultar el análisis comparativo completo y los ratios de **Rendimiento / Precio** en el artículo oficial:
👉 [**Comparativa de Hostings y VPS: El Gran Ranking Técnico en SistemasOperativos.info**](https://sistemasoperativos.info/linux/comparativa-hostings-vps-benchmark/)

---

## 🤝 Cómo Contribuir con tu Hosting

1. Ejecuta el benchmark en tu proveedor (`php ssoo-bench.php --json > mi-hosting.json`).
2. Haz un Fork de este repositorio.
3. Añade tu archivo JSON a la carpeta `results/`.
4. Envía una Pull Request con el nombre de tu empresa de hosting y el plan contratado.

---

## 📄 Licencia

Este proyecto está bajo la Licencia MIT. Consulta el archivo [LICENSE](LICENSE) para más detalles.
