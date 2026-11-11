<?php

use App\Models\User;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Models\Membership;
use App\Modules\Platform\Services\TenantManager;
use App\Modules\Procurement\Models\SupplierInvoice;
use App\Modules\Procurement\Models\SupplierPaymentProposal;
use App\Modules\Procurement\Services\AccountsPayableService;
use App\Modules\Procurement\Services\SourcingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $councilId, $userId, $action, $lockId, $recordId, $mode, $marker] = $argv;
$council = Council::findOrFail($councilId);
if (! preg_match('/^manicerp_test_proc_race_[a-z0-9]{12}$/', $council->database_name)) {
    throw new RuntimeException('Procurement concurrency helper requires its dedicated temporary database.');
}
$user = User::findOrFail($userId);
$membership = Membership::query()->where('council_id', $councilId)->where('user_id', $userId)->firstOrFail();
app(TenantManager::class)->connect($council, $membership);
Auth::setUser($user);
$db = DB::connection('tenant');
$db->select("SELECT set_config('application_name', ?, false)", [$marker]);

if ($mode === 'hold') {
    $db->beginTransaction();
    match ($action) {
        'submit_requisition' => DB::connection('tenant')->table('procurement_budget_lines')->where('id', $lockId)->lockForUpdate()->firstOrFail(),
        'approve_payment', 'settle_payment' => DB::connection('tenant')->table('procurement_supplier_invoices')->where('id', $lockId)->lockForUpdate()->firstOrFail(),
        'approve_amendment', 'cancel_order' => DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $lockId)->lockForUpdate()->firstOrFail(),
        'approve_invoice' => DB::connection('tenant')->table('procurement_purchase_orders')->where('id', $lockId)->lockForUpdate()->firstOrFail(),
        default => throw new RuntimeException('Unsupported concurrency action.'),
    };
    echo "locked\n";
    fflush(STDOUT);
    fgets(STDIN);
} else {
    echo "attempting\n";
    fflush(STDOUT);
}

try {
    $result = match ($action) {
        'submit_requisition' => app(SourcingService::class)->submitRequisition($recordId, $user),
        'approve_payment' => app(AccountsPayableService::class)->approvePayment(SupplierPaymentProposal::findOrFail($recordId), $user),
        'settle_payment' => app(AccountsPayableService::class)->settleSandbox(SupplierPaymentProposal::findOrFail($recordId), 'RACE-SETTLE-'.$recordId, $user),
        'approve_amendment' => app(SourcingService::class)->approveOrderAmendment($recordId, $user),
        'cancel_order' => app(SourcingService::class)->cancelPurchaseOrder($recordId, 'Concurrent cancellation check', $user),
        'approve_invoice' => app(AccountsPayableService::class)->approveInvoice(SupplierInvoice::findOrFail($recordId), $user),
        default => throw new RuntimeException('Unsupported concurrency action.'),
    };
    if ($mode === 'hold') {
        $db->commit();
    }
    echo "ok:{$result->id}\n";
} catch (DomainException $exception) {
    if ($mode === 'hold' && $db->transactionLevel() > 0) {
        $db->commit();
    }
    echo "rejected:{$exception->getMessage()}\n";
}
