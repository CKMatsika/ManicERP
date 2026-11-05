<?php

use App\Http\Controllers\Accounting\ChartController;
use App\Http\Controllers\Accounting\JournalController;
use App\Http\Controllers\Accounting\ReportController;
use App\Http\Controllers\Accounting\StandardsController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Ai\AssistantController;
use App\Http\Controllers\Auth\CouncilController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MfaController;
use App\Http\Controllers\Customer\PortalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Revenue\AccountController;
use App\Http\Controllers\Revenue\BankController;
use App\Http\Controllers\Revenue\BillingController;
use App\Http\Controllers\Revenue\CashierController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('home'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');

    Route::get('/councils', [CouncilController::class, 'index'])->name('councils.index');
});

Route::middleware('auth')->group(function () {
    Route::get('/home', HomeController::class)->name('home');
    Route::post('/logout', [LogoutController::class, 'destroy'])->name('logout');
    Route::get('/councils', [CouncilController::class, 'index'])->name('councils.index');
    Route::post('/councils/select', [CouncilController::class, 'select'])->name('councils.select');

    Route::get('/security/mfa/challenge', [MfaController::class, 'challenge'])->name('security.mfa.challenge');
    Route::post('/security/mfa/challenge', [MfaController::class, 'verify'])->name('security.mfa.verify');
    Route::get('/security/mfa/setup', [MfaController::class, 'setup'])->name('security.mfa.setup');
    Route::post('/security/mfa/setup', [MfaController::class, 'confirm'])->name('security.mfa.confirm');
});

/*
|--------------------------------------------------------------------------
| Staff ERP
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'council:staff', 'mfa'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/ai/assistant', AssistantController::class)
        ->middleware(['module:ai', 'can:ai.assistant.use'])->name('ai.assistant');

    Route::get('/admin/modules', [ModuleController::class, 'index'])
        ->middleware('can:platform.modules.manage')->name('admin.modules.index');
    Route::patch('/admin/modules', [ModuleController::class, 'update'])
        ->middleware('can:platform.modules.manage')->name('admin.modules.update');

    Route::get('/admin/audit', [AuditController::class, 'index'])
        ->middleware('can:platform.audit.view')->name('admin.audit.index');
    Route::post('/admin/audit/verify', [AuditController::class, 'verify'])
        ->middleware('can:platform.audit.view')->name('admin.audit.verify');

    Route::get('/documents/{document}', [DocumentController::class, 'download'])
        ->middleware('can:platform.documents.view')->name('documents.download');

    Route::middleware(['module:accounting'])->prefix('accounting')->name('accounting.')->group(function () {
        Route::get('/chart', [ChartController::class, 'index'])
            ->middleware('can:accounting.chart.view')->name('chart.index');
        Route::post('/chart/import', [ChartController::class, 'import'])
            ->middleware('can:accounting.chart.manage')->name('chart.import');
        Route::post('/chart/batches/{batch}/approve', [ChartController::class, 'approve'])
            ->middleware('can:accounting.chart.approve')->name('chart.approve');

        Route::get('/journals', [JournalController::class, 'index'])
            ->middleware('can:accounting.journal.view')->name('journals.index');
        Route::post('/journals', [JournalController::class, 'store'])
            ->middleware('can:accounting.journal.post')->name('journals.store');
        Route::post('/journals/{journal}/reverse', [JournalController::class, 'reverse'])
            ->middleware('can:accounting.journal.reverse')->name('journals.reverse');

        Route::get('/reports/trial-balance', [ReportController::class, 'trialBalance'])
            ->middleware('can:accounting.reports.view')->name('reports.trial-balance');

        Route::get('/standards', [StandardsController::class, 'index'])
            ->middleware('can:accounting.chart.view')->name('standards.index');
        Route::get('/reports/position', [ReportController::class, 'position'])
            ->middleware('can:accounting.reports.view')->name('reports.position');
        Route::get('/reports/performance', [ReportController::class, 'performance'])
            ->middleware('can:accounting.reports.view')->name('reports.performance');
        Route::get('/reports/ledger', [ReportController::class, 'ledger'])
            ->middleware('can:accounting.reports.view')->name('reports.ledger');
    });

    Route::middleware(['module:revenue'])->prefix('revenue')->name('revenue.')->group(function () {
        Route::get('/accounts', [AccountController::class, 'index'])
            ->middleware('can:revenue.account.view')->name('accounts.index');
        Route::get('/accounts/{account}', [AccountController::class, 'show'])
            ->middleware('can:revenue.account.view')->name('accounts.show');

        Route::get('/billing', [BillingController::class, 'index'])
            ->middleware('can:revenue.tariff.view')->name('billing.index');
        Route::post('/billing/preview', [BillingController::class, 'preview'])
            ->middleware('can:revenue.billing.preview')->name('billing.preview');
        Route::post('/billing/{run}/approve', [BillingController::class, 'approve'])
            ->middleware('can:revenue.billing.approve')->name('billing.approve');
        Route::post('/billing/{run}/post', [BillingController::class, 'post'])
            ->middleware('can:revenue.billing.approve')->name('billing.post');

        Route::get('/cashier', [CashierController::class, 'index'])
            ->middleware('can:revenue.cashier.open')->name('cashier.index');
        Route::post('/cashier/open', [CashierController::class, 'open'])
            ->middleware('can:revenue.cashier.open')->name('cashier.open');
        Route::post('/cashier/capture', [CashierController::class, 'capture'])
            ->middleware('can:revenue.payment.capture')->name('cashier.capture');
        Route::post('/cashier/close', [CashierController::class, 'close'])
            ->middleware('can:revenue.cashier.close')->name('cashier.close');

        Route::get('/bank', [BankController::class, 'index'])
            ->middleware('can:revenue.reconciliation.manage')->name('bank.index');
        Route::post('/bank/import', [BankController::class, 'import'])
            ->middleware('can:revenue.reconciliation.manage')->name('bank.import');
        Route::get('/bank/{statement}', [BankController::class, 'show'])
            ->middleware('can:revenue.reconciliation.manage')->name('bank.show');
        Route::post('/bank/lines/{line}/match', [BankController::class, 'match'])
            ->middleware('can:revenue.reconciliation.manage')->name('bank.match');
        Route::post('/bank/{statement}/reconcile', [BankController::class, 'reconcile'])
            ->middleware('can:revenue.reconciliation.manage')->name('bank.reconcile');
    });
});

/*
|--------------------------------------------------------------------------
| Citizen portal
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'council:citizen', 'module:customer'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/accounts/{account}', [PortalController::class, 'account'])->name('accounts.show');
    Route::get('/accounts/{account}/statement', [PortalController::class, 'statement'])->name('accounts.statement');

    Route::get('/pay', [PortalController::class, 'payForm'])->name('pay');
    Route::post('/pay', [PortalController::class, 'pay'])->name('pay.store');
    Route::get('/pay/{intent}', [PortalController::class, 'payShow'])->name('pay.show');
    Route::post('/pay/{intent}/sandbox-confirm', [PortalController::class, 'sandboxConfirm'])->name('pay.sandbox');

    Route::get('/link', [PortalController::class, 'linkForm'])->name('link');
    Route::post('/link', [PortalController::class, 'requestLink'])->name('link.request');
    Route::post('/link/{link}/verify', [PortalController::class, 'verifyLink'])->name('link.verify');
    Route::post('/link/{link}/revoke', [PortalController::class, 'revokeLink'])->name('link.revoke');

    Route::get('/requests', [PortalController::class, 'requests'])->name('requests');
    Route::post('/requests', [PortalController::class, 'storeRequest'])->name('requests.store');
});
