<?php

use App\Models\User;
use App\Modules\Licensing\Models\LicenceApplication;
use App\Modules\Licensing\Services\LicensingService;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Models\Membership;
use App\Modules\Platform\Services\TenantManager;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $councilId, $userId, $applicationId, $action, $mode, $decision, $marker] = $argv;
$council = Council::findOrFail($councilId);
if (! str_starts_with($council->database_name, 'manicerp_test_licence_race_')) {
    throw new RuntimeException('Concurrency helper requires its dedicated temporary test database.');
}
$user = User::findOrFail($userId);
$membership = Membership::where('council_id', $councilId)->where('user_id', $userId)->firstOrFail();
app(TenantManager::class)->connect($council, $membership);
Auth::setUser($user);
$db = DB::connection('tenant');
$db->select('SELECT set_config(\'application_name\', ?, false)', [$marker]);
$application = LicenceApplication::findOrFail($applicationId);

if ($mode === 'hold') {
    $db->beginTransaction();
    LicenceApplication::whereKey($applicationId)->lockForUpdate()->firstOrFail();
    echo "locked\n";
    fflush(STDOUT);
    fgets(STDIN);
} else {
    echo "attempting\n";
    fflush(STDOUT);
}

try {
    if ($action === 'decide') {
        $result = app(LicensingService::class)->decide($application, $decision, 'Concurrent test decision', [], $user);
        $id = $result->id;
    } else {
        $result = app(LicensingService::class)->issue($application, $user);
        $id = $result->id;
    }
    if ($mode === 'hold') {
        $db->commit();
    }
    echo "ok:{$id}\n";
} catch (DomainException $exception) {
    if ($db->transactionLevel()) {
        $db->rollBack();
    }
    echo "rejected\n";
}
