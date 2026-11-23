<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DamageReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExcelSyncController;
use App\Http\Controllers\ImportIssueController;
use App\Http\Controllers\QrLabelController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\StockTransactionController;
use App\Http\Controllers\ToolController;
use App\Http\Controllers\ToolUsageController;
use Illuminate\Support\Facades\Route;

// Module 1 — Live Stock Control Dashboard
Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/tools/{tool}', [ToolController::class, 'show'])->whereNumber('tool')->name('tools.show');

// Module 2 — Quick Stock Transaction (IN / OUT)
Route::get('/transactions/create', [StockTransactionController::class, 'create'])->name('transactions.create');
Route::post('/transactions', [StockTransactionController::class, 'store'])->name('transactions.store');

// Input Forecast / Pemakaian life time harian
Route::get('/tool-usages', [ToolUsageController::class, 'index'])->name('tool-usages.index');
Route::post('/tool-usages', [ToolUsageController::class, 'store'])->name('tool-usages.store');
Route::post('/tool-usages/{usage}/cancel', [ToolUsageController::class, 'cancel'])->whereNumber('usage')->name('tool-usages.cancel');
Route::post('/tools/{tool}/life-reset', [ToolUsageController::class, 'reset'])->whereNumber('tool')->name('tools.life-reset');

// Module 3 — QR Damage & Part Replacement Form + History
Route::get('/damage-reports', [DamageReportController::class, 'index'])->name('damage-reports.index');
Route::get('/damage-reports/create', [DamageReportController::class, 'create'])->name('damage-reports.create');
Route::post('/damage-reports', [DamageReportController::class, 'store'])->name('damage-reports.store');
Route::get('/damage-reports/{damageReport}', [DamageReportController::class, 'show'])->whereNumber('damageReport')->name('damage-reports.show');
Route::get('/qr-labels', QrLabelController::class)->name('qr-labels.index');

// Module 4 — Excel Master Sync & Stock Opname
Route::get('/sync', [ExcelSyncController::class, 'index'])->name('sync.index');
Route::get('/import-issues', [ImportIssueController::class, 'index'])->name('import-issues.index');
Route::post('/sync/master-import', [ExcelSyncController::class, 'importMaster'])->name('sync.master-import');
Route::get('/sync/control-stock-export', [ExcelSyncController::class, 'exportControlStock'])->name('sync.control-stock-export');
Route::get('/sync/opname-template', [StockOpnameController::class, 'template'])->name('opname.template');
Route::post('/sync/opname-preview', [StockOpnameController::class, 'preview'])->name('opname.preview');
Route::post('/sync/opname-apply', [StockOpnameController::class, 'apply'])->name('opname.apply');

// Module 5 — Audit Log History
Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
Route::get('/audit-log/export', [AuditLogController::class, 'export'])->name('audit-log.export');
