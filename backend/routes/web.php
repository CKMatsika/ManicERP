<?php

use App\Http\Controllers\Accounting\ChartController;
use App\Http\Controllers\Accounting\FinancialReadinessController;
use App\Http\Controllers\Accounting\JournalController;
use App\Http\Controllers\Accounting\ReportController;
use App\Http\Controllers\Accounting\StandardsController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Ai\AssistantController;
use App\Http\Controllers\Auth\CitizenRegistrationController;
use App\Http\Controllers\Auth\CouncilController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MfaController;
use App\Http\Controllers\Customer\PortalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Licensing\LicenceAiController;
use App\Http\Controllers\Licensing\LicensingController;
use App\Http\Controllers\Licensing\NotificationController;
use App\Http\Controllers\Procurement\ProcurementController;
use App\Http\Controllers\Procurement\ProcurementDocumentController;
use App\Http\Controllers\Revenue\AccountController;
use App\Http\Controllers\Revenue\BankController;
use App\Http\Controllers\Revenue\BillingController;
use App\Http\Controllers\Revenue\CashierController;
use App\Http\Controllers\Revenue\RefundController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('landing');

Route::get('/councils/{councilCode}/licences/verify/{verificationId}', [LicensingController::class, 'publicVerify'])
    ->where(['councilCode' => '[A-Za-z0-9_-]+', 'verificationId' => '[A-Za-z0-9]+'])
    ->name('licensing.public.verify');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('/register', [CitizenRegistrationController::class, 'show'])->name('register');
    Route::post('/register', [CitizenRegistrationController::class, 'store'])->middleware('throttle:5,60')->name('register.store');
    Route::get('/register/verify', [CitizenRegistrationController::class, 'verifyForm'])->middleware('signed')->name('register.verify');
    Route::post('/register/verify', [CitizenRegistrationController::class, 'verify'])->middleware(['signed', 'throttle:10,1'])->name('register.verify.confirm');

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

    Route::middleware(['module:procurement'])->prefix('procurement')->name('procurement.')->group(function () {
        Route::get('/', [ProcurementController::class, 'index'])->name('index');
        Route::post('/actions', [ProcurementController::class, 'action'])->name('actions');
        Route::post('/documents/{type}/{record}', [ProcurementDocumentController::class, 'upload'])->whereIn('type', ['supplier_evidence', 'quotation', 'purchase_order', 'receiving_evidence', 'invoice'])->name('documents.upload');
        Route::get('/documents/{document}/download', [ProcurementDocumentController::class, 'download'])->name('documents.download');
        Route::get('/documents/{document}/preview', [ProcurementDocumentController::class, 'preview'])->name('documents.preview');
        Route::post('/documents/{record}/retention', [ProcurementDocumentController::class, 'retention'])->name('documents.retention');
    });

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

    // Land, GIS Cadastre & Housing Allocation Management (Urban Councils Act compliance)
    Route::get('/land/gis', [\App\Http\Controllers\Land\GisCadastreController::class, 'index'])
        ->name('land.gis.index');
    Route::get('/land/parcels', [\App\Http\Controllers\Land\GisCadastreController::class, 'index'])
        ->name('land.parcels.index');
    Route::post('/land/gis/parcels', [\App\Http\Controllers\Land\GisCadastreController::class, 'store'])
        ->name('land.gis.store');
    Route::get('/land/housing-allocations', [\App\Http\Controllers\Land\HousingAllocationController::class, 'index'])
        ->name('land.allocations.index');
    Route::post('/land/housing-allocations', [\App\Http\Controllers\Land\HousingAllocationController::class, 'store'])
        ->name('land.allocations.store');

    // Credit Control & Debt Recovery (Urban Councils Act Section 279)
    Route::get('/revenue/credit-control', [\App\Http\Controllers\Revenue\CreditControlController::class, 'index'])
        ->name('revenue.credit-control.index');

    // Fixed Assets & Infrastructure (IPSAS 17)
    Route::get('/assets', [\App\Http\Controllers\Accounting\FixedAssetController::class, 'index'])
        ->name('assets.index');
    Route::post('/assets/register', [\App\Http\Controllers\Accounting\FixedAssetController::class, 'store'])
        ->name('assets.store');
    Route::post('/assets/depreciate', [\App\Http\Controllers\Accounting\FixedAssetController::class, 'depreciate'])
        ->name('assets.depreciate');
    Route::post('/assets/{id}/audit', [\App\Http\Controllers\Accounting\FixedAssetController::class, 'audit'])
        ->name('assets.audit');

    // Central Stores & Inventory Management
    Route::get('/stores', [\App\Http\Controllers\Procurement\CentralStoresController::class, 'index'])
        ->name('stores.index');
    Route::post('/stores/items', [\App\Http\Controllers\Procurement\CentralStoresController::class, 'store'])
        ->name('stores.items.store');
    Route::post('/stores/receive', [\App\Http\Controllers\Procurement\CentralStoresController::class, 'receive'])
        ->name('stores.receive');
    Route::post('/stores/issue', [\App\Http\Controllers\Procurement\CentralStoresController::class, 'issue'])
        ->name('stores.issue');

    // Municipal HR & Payroll (Zimbabwe Statutory Compliance)
    Route::get('/hr/payroll', [\App\Http\Controllers\Hr\PayrollController::class, 'index'])
        ->name('hr.payroll.index');
    Route::post('/hr/payroll/process', [\App\Http\Controllers\Hr\PayrollController::class, 'process'])
        ->name('hr.payroll.process');
    Route::get('/hr/payroll/{id}/bank-export', [\App\Http\Controllers\Hr\PayrollController::class, 'exportBank'])
        ->name('hr.payroll.bank-export');

    // Water Metering & AMR Billing
    Route::get('/engineering/water-meters', [\App\Http\Controllers\Engineering\WaterMeterController::class, 'index'])
        ->name('engineering.water-meters.index');
    Route::post('/engineering/water-meters/capture', [\App\Http\Controllers\Engineering\WaterMeterController::class, 'capture'])
        ->name('engineering.water-meters.capture');
    Route::post('/engineering/water-meters/billing-run', [\App\Http\Controllers\Engineering\WaterMeterController::class, 'billingRun'])
        ->name('engineering.water-meters.billing-run');

    // Cemeteries & Grave Register (Community Services)
    Route::get('/community/cemeteries', [\App\Http\Controllers\Community\CemeteryController::class, 'index'])
        ->name('community.cemeteries.index');
    Route::post('/community/cemeteries/interments', [\App\Http\Controllers\Community\CemeteryController::class, 'storeInterment'])
        ->name('community.cemeteries.interments.store');

    // Emergency Services & Fire Brigade
    Route::get('/emergency', [\App\Http\Controllers\Emergency\EmergencyController::class, 'index'])
        ->name('emergency.index');
    Route::post('/emergency/incidents', [\App\Http\Controllers\Emergency\EmergencyController::class, 'storeIncident'])
        ->name('emergency.incidents.store');

    // Fleet & Heavy Plant Management
    Route::get('/fleet', [\App\Http\Controllers\Fleet\FleetController::class, 'index'])
        ->name('fleet.index');
    Route::post('/fleet/coupons', [\App\Http\Controllers\Fleet\FleetController::class, 'issueCoupon'])
        ->name('fleet.coupons.store');

    // Devolution & Capital Infrastructure Projects
    Route::get('/projects/capital', [\App\Http\Controllers\Projects\CapitalProjectsController::class, 'index'])
        ->name('projects.capital.index');
    Route::post('/projects/capital/certificates', [\App\Http\Controllers\Projects\CapitalProjectsController::class, 'issueCertificate'])
        ->name('projects.capital.certificates.store');

    Route::middleware(['module:licensing'])->prefix('licensing')->name('licensing.')->group(function () {
        Route::get('/', [LicensingController::class, 'staffIndex'])->middleware('can:licensing.applications.view')->name('index');
        Route::post('/types/seed-demo', [LicensingController::class, 'seedDemo'])->middleware('can:licensing.types.manage')->name('types.seed-demo');
        Route::post('/types', [LicensingController::class, 'createType'])->middleware('can:licensing.types.manage')->name('types.store');
        Route::post('/types/{type}/versions', [LicensingController::class, 'reviseType'])->middleware('can:licensing.types.manage')->name('types.revise');
        Route::post('/type-versions/{version}/approve', [LicensingController::class, 'approveType'])->middleware('can:licensing.types.approve')->name('types.approve');
        Route::post('/applications/{application}/review', [LicensingController::class, 'review'])->middleware('can:licensing.applications.review')->name('applications.review');
        Route::post('/applications/{application}/information', [LicensingController::class, 'information'])->middleware('can:licensing.applications.review')->name('applications.information');
        Route::post('/applications/{application}/inspections', [LicensingController::class, 'assignInspection'])->middleware('can:licensing.inspections.manage')->name('applications.inspections.assign');
        Route::post('/inspections/{inspection}/result', [LicensingController::class, 'inspectionResult'])->middleware('can:licensing.inspections.manage')->name('inspections.result');
        Route::post('/applications/{application}/decision', [LicensingController::class, 'decide'])->middleware('can:licensing.applications.approve')->name('applications.decision');
        Route::post('/applications/{application}/issue', [LicensingController::class, 'issue'])->middleware('can:licensing.certificates.issue')->name('applications.issue');
        Route::post('/applications/{application}/charges/{charge}/release', [LicensingController::class, 'releaseCharge'])->middleware('can:licensing.charges.release')->name('applications.charges.release');
        Route::post('/charges/{charge}/waive', [LicensingController::class, 'waiveCharge'])->middleware('can:licensing.charges.waive')->name('charges.waive');
        Route::post('/applications/{application}/documents/{document}/extract', [LicenceAiController::class, 'extract'])->middleware('can:licensing.assistant.extract')->name('applications.documents.extract');
        Route::post('/applications/{application}/extractions/{extraction}/review', [LicenceAiController::class, 'reviewExtraction'])->middleware('can:licensing.assistant.extract')->name('applications.extractions.review');
        Route::post('/applications/{application}/assistant-drafts', [LicenceAiController::class, 'draft'])->middleware('can:licensing.assistant.draft')->name('applications.assistant-drafts.store');
        Route::post('/applications/{application}/assistant-drafts/{draft}/review', [LicenceAiController::class, 'reviewDraft'])->middleware('can:licensing.assistant.draft')->name('applications.assistant-drafts.review');
        Route::post('/applications/{application}/cancel-fee', [LicensingController::class, 'cancelFee'])->middleware(['can:licensing.applications.approve', 'can:revenue.payment.reverse'])->name('applications.cancel-fee');
        Route::post('/licences/{licence}/{action}', [LicensingController::class, 'lifecycle'])->whereIn('action', ['suspend', 'revoke', 'reinstate'])->middleware('can:licensing.applications.approve')->name('lifecycle');
        Route::get('/notifications', [NotificationController::class, 'index'])->middleware('can:licensing.notifications.view')->name('notifications');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->middleware('can:licensing.notifications.view')->name('notifications.read');
    });

    Route::get('/documents/{document}', [DocumentController::class, 'download'])
        ->middleware('can:platform.documents.view')->name('documents.download');

    Route::middleware(['module:accounting'])->prefix('accounting')->name('accounting.')->group(function () {
        $readiness = FinancialReadinessController::class;
        Route::get('/readiness', [$readiness, 'index'])->name('readiness');
        Route::post('/readiness/sources', [$readiness, 'sources'])->middleware('can:accounting.requirements.manage')->name('readiness.sources');
        Route::post('/readiness/guidance', [$readiness, 'guidance'])->middleware('can:accounting.requirements.manage')->name('readiness.guidance');
        Route::get('/opening-balances/template/{type}', [$readiness, 'template'])->middleware('can:accounting.opening_balance.manage')->name('opening.template');
        Route::post('/opening-balances/import', [$readiness, 'import'])->middleware('can:accounting.opening_balance.manage')->name('opening.import');
        Route::post('/adoption-dates', [$readiness, 'adoption'])->middleware('can:accounting.opening_balance.approve')->name('adoption');
        Route::post('/opening-balances/{batch}/{action}', [$readiness, 'batch'])->whereIn('action', ['approve', 'post'])->middleware('can:accounting.opening_balance.approve')->name('opening.action');
        Route::post('/report-mappings/templates', [$readiness, 'templates'])->middleware('can:accounting.report_mapping.manage')->name('mappings.templates');
        Route::post('/report-mappings', [$readiness, 'mapping'])->middleware('can:accounting.report_mapping.manage')->name('mappings.store');
        Route::post('/report-mappings/{report}/version', [$readiness, 'version'])->middleware('can:accounting.report_mapping.manage')->name('mappings.version');
        Route::post('/report-mappings/{report}/approve', [$readiness, 'reportApprove'])->middleware('can:accounting.report_mapping.approve')->name('mappings.approve');
        Route::post('/revenue-policies', [$readiness, 'policy'])->middleware('can:accounting.revenue_policy.manage')->name('revenue-policies.store');
        Route::post('/revenue-policies/{policy}/approve', [$readiness, 'policyApprove'])->middleware('can:accounting.revenue_policy.approve')->name('revenue-policies.approve');
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
        $refunds = RefundController::class;
        Route::get('/refunds', [$refunds, 'index'])->middleware('can:revenue.refund.view')->name('refunds');
        Route::post('/refunds', [$refunds, 'store'])->middleware('can:revenue.refund.request')->name('refunds.store');
        Route::post('/refunds/{refund}/{action}', [$refunds, 'action'])->whereIn('action', ['approve', 'reject'])->middleware('can:revenue.refund.approve')->name('refunds.review');
        Route::post('/refunds/{refund}/settle', [$refunds, 'action'])->defaults('action', 'settle')->middleware('can:revenue.refund.settle')->name('refunds.settle');
        Route::post('/allocations/{allocation}/reverse', [$refunds, 'reverseAllocation'])->middleware('can:revenue.payment.reverse')->name('allocations.reverse');
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

    Route::middleware(['module:licensing'])->prefix('licensing')->name('licensing.')->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index'])->middleware('can:licensing.notifications.view')->name('notifications');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->middleware('can:licensing.notifications.view')->name('notifications.read');
        Route::get('/', [LicensingController::class, 'citizen'])->name('index');
        Route::get('/assistant', [LicensingController::class, 'citizenAssistant'])->middleware('can:licensing.assistant.use')->name('assistant');
        Route::post('/drafts', [LicensingController::class, 'saveDraft'])->name('drafts.store');
        Route::post('/applications', [LicensingController::class, 'submit'])->name('applications.store');
        Route::post('/applications/{application}/respond', [LicensingController::class, 'respond'])->name('applications.respond');
        Route::post('/applications/{application}/refunds', [LicensingController::class, 'refund'])->name('applications.refunds');
        Route::post('/applications/{application}/documents', [LicensingController::class, 'upload'])->name('applications.documents');
        Route::post('/applications/{application}/payment', [LicensingController::class, 'pay'])->name('applications.payment');
        Route::get('/certificates/{certificate}', [LicensingController::class, 'downloadCertificate'])->name('certificates.download');
    });
});
