<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CivicApiController;
use App\Http\Controllers\Api\CreditControlApiController;
use App\Http\Controllers\Api\HousingAllocationApiController;
use App\Http\Controllers\Api\LicensingApiController;
use App\Http\Controllers\Api\PortalApiController;
use App\Http\Controllers\Api\WaitingListApiController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Licensing\NotificationController;
use App\Modules\Customer\Services\AccountLinkService;
use App\Modules\Revenue\Models\Refund;
use App\Modules\Revenue\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json(['status' => 'ok', 'time' => now()->toIso8601String()]));

    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::middleware(['council:citizen', 'throttle:api'])->group(function () {
            Route::get('/accounts', [PortalApiController::class, 'accounts']);
            Route::get('/accounts/{account}', [PortalApiController::class, 'account']);
            Route::get('/accounts/{account}/bills', [PortalApiController::class, 'bills']);
            Route::get('/accounts/{account}/payments', [PortalApiController::class, 'payments']);
            Route::get('/accounts/{account}/refunds', function (Request $request, string $account) {
                app(AccountLinkService::class)->requireAccount($request->user(), $account);

                return response()->json(['data' => Refund::where('customer_account_id', $account)->latest()->get()
                    ->map(fn ($refund) => app(RefundService::class)->status($refund))]);
            });
            Route::get('/payments', [PortalApiController::class, 'allPayments']);
            Route::post('/payments/intents', [PortalApiController::class, 'createIntent']);

            Route::post('/account-links', [PortalApiController::class, 'requestLink']);
            Route::post('/account-links/{link}/verify', [PortalApiController::class, 'verifyLink']);
            Route::delete('/account-links/{link}', [PortalApiController::class, 'revokeLink']);

            Route::get('/service-requests', [PortalApiController::class, 'serviceRequests']);
            Route::post('/service-requests', [PortalApiController::class, 'storeServiceRequest']);

            // Housing Waiting List Management (Urban Councils Act compliance)
            Route::get('/waiting-list/categories', [WaitingListApiController::class, 'categories']);
            Route::get('/waiting-list', [WaitingListApiController::class, 'index']);
            Route::post('/waiting-list', [WaitingListApiController::class, 'apply']);
            Route::post('/waiting-list/{id}/renew', [WaitingListApiController::class, 'renew']);
            Route::get('/waiting-list/offer', [HousingAllocationApiController::class, 'currentOffer']);
            Route::post('/waiting-list/offer/{id}/accept', [HousingAllocationApiController::class, 'accept']);
            Route::post('/waiting-list/offer/{id}/reject', [HousingAllocationApiController::class, 'reject']);
            Route::post('/waiting-list/offer/{id}/deposit', [HousingAllocationApiController::class, 'payDeposit']);

            // Civic Engagement Hub (News, Events, Consultations, Resolutions, Newsletters)
            Route::get('/civic', [CivicApiController::class, 'index']);
            Route::get('/civic/{id}', [CivicApiController::class, 'show']);

            // Credit Control & Payment Agreements (Urban Councils Act Section 279)
            Route::get('/credit-control/plan-options', [CreditControlApiController::class, 'options']);
            Route::get('/credit-control/active-plan', [CreditControlApiController::class, 'activePlan']);
            Route::post('/credit-control/payment-plans', [CreditControlApiController::class, 'store']);
            Route::post('/credit-control/installments/{id}/pay', [CreditControlApiController::class, 'payInstallment']);

            // Water Metering & AMR Consumption
            Route::get('/water/meter', [\App\Http\Controllers\Api\WaterMeterApiController::class, 'currentMeter']);

            // Cemeteries & Grave Register (Citizen Grave Booking)
            Route::get('/cemeteries', [\App\Http\Controllers\Api\CitizenCemeteryApiController::class, 'index']);
            Route::get('/cemeteries/my-reservations', [\App\Http\Controllers\Api\CitizenCemeteryApiController::class, 'myReservations']);
            Route::post('/cemeteries/reserve', [\App\Http\Controllers\Api\CitizenCemeteryApiController::class, 'reserve']);

            // Emergency Services & 24/7 Call Centre
            Route::get('/emergency/hotlines', [\App\Http\Controllers\Api\CitizenEmergencyApiController::class, 'hotlines']);
            Route::get('/emergency/my-incidents', [\App\Http\Controllers\Api\CitizenEmergencyApiController::class, 'myIncidents']);
            Route::post('/emergency/report', [\App\Http\Controllers\Api\CitizenEmergencyApiController::class, 'report']);
        });

        Route::middleware(['council:citizen', 'module:licensing', 'throttle:api'])->prefix('licensing')->name('api.v1.licensing.')->group(function () {
            Route::get('/types', [LicensingApiController::class, 'catalog'])->name('types');
            Route::get('/guidance', [LicensingApiController::class, 'guidance'])->middleware('can:licensing.assistant.use')->name('guidance');
            Route::get('/applications', [LicensingApiController::class, 'applications'])->name('applications');
            Route::get('/notifications', [NotificationController::class, 'index'])->middleware('can:licensing.notifications.view')->name('notifications');
            Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->middleware('can:licensing.notifications.view')->name('notifications.read');
            Route::post('/drafts', [LicensingApiController::class, 'draft'])->name('drafts');
            Route::post('/applications', [LicensingApiController::class, 'submit'])->name('applications.submit');
            Route::post('/applications/{application}/respond', [LicensingApiController::class, 'respond'])->name('applications.respond');
            Route::post('/applications/{application}/refunds', [LicensingApiController::class, 'refund'])->name('applications.refunds');
            Route::post('/applications/{application}/documents', [LicensingApiController::class, 'upload'])->name('documents');
            Route::post('/applications/{application}/payment', [LicensingApiController::class, 'payment'])->name('payment');
            Route::get('/certificates/{certificate}', [LicensingApiController::class, 'certificate'])->name('certificate');
            Route::get('/certificates/{certificate}/download', [LicensingApiController::class, 'downloadCertificate'])->name('certificate.download');
        });
    });

    Route::post('/webhooks/payments/sandbox', [WebhookController::class, 'sandbox']);
    Route::post('/webhooks/payments/paynow', [WebhookController::class, 'paynow']);
    Route::post('/sandbox/paynow/complete/{reference}', [WebhookController::class, 'simulatePaynowSuccess']);
});
