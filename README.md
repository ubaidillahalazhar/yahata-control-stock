# Yahata Tools Control Stock Dashboard

**Production Department Control Stock Tool — PT. Yahata Fastener Indonesia**

Digitalisasi sheet `control stock` pada `yahata tools (1).xlsx` menjadi aplikasi web real-time:
dashboard stok, transaksi IN/OUT, form kerusakan via QR Code, sinkronisasi master Excel,
Stock Opname berbasis Excel, dan audit log seluruh mutasi stok.

| Layer    | Teknologi |
|----------|-----------|
| Backend  | Laravel 13 (otomatis Laravel 12 bila PHP 8.2), Inertia.js v2, `maatwebsite/excel` 3.1 |
| Frontend | React 19, Tailwind CSS 4, Lucide Icons, `qrcode.react` |
| Database | PostgreSQL (default) — kompatibel MySQL 8 |

---

## 1. Persyaratan

- PHP **8.3+** (8.2 masih bisa → Composer memilih Laravel 12) dengan ekstensi:
  `pdo_pgsql` (atau `pdo_mysql`), `gd`, `zip`, `xml`, `mbstring`, `fileinfo`
  *(XAMPP/Laragon: aktifkan `extension=gd` dan `extension=zip` di `php.ini`)*
- Composer 2
- Node.js **20.19+** atau **22.12+** (syarat Vite 7)
- PostgreSQL 14+ (atau MySQL 8)

## 2. Instalasi

```bash
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate

# Buat database "yahata_tools" lalu sesuaikan DB_* di .env
php artisan migrate --seed    # --seed = import otomatis contoh yahata_tools.xlsx (37 item)

npm install
npm run build                 # atau `npm run dev` saat development

php artisan serve --host=0.0.0.0 --port=8000
```

Buka `http://localhost:8000`. Mode development (server + Vite sekaligus): `composer dev`.

### Akses dari HP operator (QR Code)

1. Isi `APP_URL` di `.env` dengan IP server di jaringan pabrik, mis. `APP_URL=http://192.168.1.10:8000`.
2. Jalankan server dengan `--host=0.0.0.0` dan izinkan port 8000 di firewall.
3. Buka menu **Label QR Code** → cetak → tempel di mesin/rak.
   - QR per item → `/damage-reports/create?tool_id=12` (item langsung terpilih)
   - QR per mesin & rak → `/damage-reports/create?machine=BF-01%20CBP-136&rack=D6` (daftar item difilter)

Import master juga bisa dari terminal:
`php artisan tooling:import "path/yahata tools (1).xlsx" [--sync-stock --pic="Nama"]`

---

## 3. Modul

| Modul | Halaman | Keterangan |
|-------|---------|------------|
| 1. Live Stock Dashboard | `/` | KPI (Total, ORDER, SECURE, Stok 0), pencarian + filter Status/Rak (React `useMemo`), tabel kolom Excel, aksi cepat IN/OUT/Rusak/Detail |
| Detail item | `/tools/{id}` | Breakdown 5 bulan (Need & Balance), rantai rumus A–E, histori transaksi & kerusakan, QR |
| 2. Transaksi IN/OUT | `/transactions/create` | Form + preview stok sesudah & status; histori ke `stock_transactions` |
| 3. Form Kerusakan (QR) | `/damage-reports/create` | Mobile-first: operator, shift, qty, shot count, alasan, tindakan. `REPLACE_NEW` memotong stok + `DAMAGE_REPLACEMENT` |
| Riwayat & slip | `/damage-reports`, `/damage-reports/{id}` | Filter, ringkasan per alasan, slip resmi siap cetak (`window.print()`, `?print=1` auto-print) |
| Label QR | `/qr-labels` | Label per item / per mesin+rak, siap cetak |
| 4. Excel Sync & Opname | `/sync` | Import master (bulk upsert), export control stock, template opname → upload → review selisih → terapkan |
| 5. Audit Log | `/audit-log` | Filter tanggal, jenis, PIC, item; ringkasan per jenis; export Excel |
| REST API | `/api/v1/tools`, `/api/v1/tools/summary`, `/api/v1/tools/{id}` | Read-only, memakai `ToolingResource` yang sama |

---

## 4. Rumus Excel → `app/Services/ToolingCalculatorService.php`

Seluruh rumus dihitung terpusat di service ini dan diekspos lewat `app/Http/Resources/ToolingResource.php`.

| # | Kolom | Excel | Implementasi |
|---|-------|-------|--------------|
| 1 | R `forecast_avg` | `AVERAGEIF(N3:Q3,">0")` | rata-rata bulan ke-2..5 yang > 0, semua 0 → 0 |
| 2 | S..W `monthly_need` | `ROUND(M3/$L3,2)` | `excelRound(qty / life_time, 2)` |
| 3 | X..AB `monthly_balance` | `ROUND(K3-$S3,2)` | `excelRound(stock - need, 2)` |
| 4 | AC `tooling_need_a` | `ROUND(R3/L3,2)` | `excelRound(avg / life_time, 2)` |
| 5 | AD `safety_stock_lead_time_b` | `ROUNDUP((AC3/20)*AN3,0)` | `excelRoundUp((A / 20) * lead_time)` |
| 6 | AE `safety_stock_fluct_c` | `AC3*20%` | `A * 0.2` |
| 7 | AF `safety_stock_add_d` | `IF(L3<=400000,"1","0")` | `life_time <= 400000 ? 1 : 0` |
| 8 | AG `min_stock_e` | `ROUNDUP(AC3+AF3,0)` | `excelRoundUp(A + D)` |
| 9 | AH `max_total_stand_by` | `ROUNDUP(AD3+AE3+AF3,0)` | `excelRoundUp(B + C + D)` |
| 10 | AI `status_info` | `IF(K3>=AG3,"SECURE","ORDER")` | `stock >= E` |
| 11 | AJ..AM | `AG*L`, `AH*L`, `IF(AJ>=R,…)` | `est_prod_qty_*`, `koreksi_*` |

`excelRound` / `excelRoundUp` meniru Excel: half-away-from-zero dan presisi 15 digit signifikan,
sehingga noise floating point (mis. `1.0000000000000002`) tidak membuat ROUNDUP melompat.

**Terverifikasi:** `tests/Unit/ToolingCalculatorServiceTest.php` membandingkan hasil service dengan
nilai cache Excel asli untuk 37 baris × 14 kolom kalkulasi (`tests/Fixtures/control_stock_expected.json`). Hasilnya 0 selisih.

### Keputusan & catatan data

- **AF**: header Excel menulis `< 500.000`, tetapi rumus aktual `<= 400000`. Yang dipakai rumus aktual.
- **Forecast teks** (`'0'` di kolom M/Q) di-cast ke angka. **Step / nama** di-trim (data mentah berisi trailing space).
- **Semua forecast 0** → Excel `#DIV/0!`, sistem `0`. **Life Time 0** ditolak saat import.
- **Tanggal bulan forecast** di baris 2 tertulis Nov-2026, Des-2026, **Jan-2026**… Sistem menaikkan tahunnya agar kronologis (Jan-2027 dst.) dan memberi peringatan saat import.
- **Re-import master tidak menimpa stok** item yang sudah ada (stok dikelola transaksi/opname). Centang opsi *Samakan Current Stock* bila memang ingin; selisihnya tercatat sebagai `OPNAME_ADJUST`. Item baru: stok kolom K menjadi stok awal dan tercatat di audit log.
- **Opname** menyetel stok = fisik; selisih dihitung terhadap stok sistem saat upload. Jika ada mutasi setelah template diunduh, item tersebut diberi tanda di preview.
- **REPLACE_NEW ditolak bila stok sistem kurang** (stok tidak pernah negatif). Operator diminta menghubungi admin.

---

## 5. Integritas stok (anti race condition)

Semua perubahan `current_stock` melewati `app/Services/StockMutationService.php`:

```
DB::transaction(attempts: 3)
  └─ SELECT … FOR UPDATE (lockForUpdate)  ← baris tool dikunci
     ├─ validasi stok tidak negatif
     ├─ UPDATE tools_inventory.current_stock
     └─ INSERT stock_transactions (qty_change, stock_before, stock_after, reference_no, pic_name, notes)
```

Dipakai oleh IN/OUT, `DAMAGE_REPLACEMENT` (`DamageReportService`), `OPNAME_ADJUST` (`StockOpnameService`,
lock berurutan per ID untuk menghindari deadlock), dan sinkronisasi stok saat import master.

---

## 6. Struktur project

```
app/
├── Enums/                 TransactionType, DamageReason, DamageAction
├── Exceptions/            InsufficientStockException
├── Exports/               ControlStockExport, StockOpnameTemplateExport (+Sheets/), AuditLogExport
├── Http/
│   ├── Controllers/       Dashboard, Tool, StockTransaction, DamageReport, ExcelSync, StockOpname, AuditLog, QrLabel, Api/ToolingApi
│   ├── Middleware/        HandleInertiaRequests (shared props & flash)
│   ├── Requests/          Form Request + pesan validasi Bahasa Indonesia
│   └── Resources/         ToolingResource, StockTransactionResource, DamageReportResource
├── Imports/               ControlStockSheetImport (2 baris header), StockOpnameImport
├── Models/                ToolsInventory, StockTransaction, DamageReport
└── Services/              ToolingCalculatorService, StockMutationService, DamageReportService,
                           ToolingMasterSyncService, StockOpnameService
database/migrations/       tools_inventory, damage_reports, stock_transactions
database/seeders/data/     yahata_tools.xlsx (contoh data)
resources/js/
├── Components/            ui, StatusBadge, KpiCard, Modal, ToolCombobox, TransactionForm, Pagination, FlashMessages
├── Layouts/               AppLayout (sidebar), MobileLayout (form QR)
├── Pages/                 Dashboard, Tools/Show, Transactions/Create, DamageReports/{Create,Index,Show},
│                          Sync/Index, AuditLog/Index, QrLabels/Index
└── lib/                   format (angka id-ID), urls (QR base URL), storage
tests/                     Unit (rumus vs Excel), Feature (mutasi stok, damage, opname, import file asli)
```

## 7. Testing

```bash
php artisan test        # memakai SQLite in-memory (lihat phpunit.xml)
```

## 8. Catatan pengembangan berikutnya

- Aplikasi **belum memakai login**. Sebelum dipakai di jaringan produksi, tambahkan autentikasi
  (mis. Laravel Breeze/Fortify) dan batasi menu Sinkronisasi/Opname untuk admin.
- Session/cache memakai driver `file`; untuk banyak pengguna bersamaan bisa diganti `database`/`redis`.
