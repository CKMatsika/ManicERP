<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'admin@demo.manicerp.test')->first();
$council = App\Modules\Platform\Models\Council::where('code', 'DEMO')->first();
$membership = $user->memberships()->where('council_id', $council->id)->first();
$role = $membership->role;

echo "User: " . $user->name . " (" . $user->email . ")\n";
echo "Role: " . ($role ? $role->name : 'none') . "\n";
echo "Permissions count: " . ($role ? count($role->permissions ?? []) : 0) . "\n";
if ($role) {
    echo "Permissions:\n";
    foreach ($role->permissions as $p) {
        echo "  - $p\n";
    }
}

$moduleRegistry = app(App\Modules\Platform\Services\ModuleRegistry::class);
echo "\nModule States for Council:\n";
foreach ($moduleRegistry->states($council) as $mod => $state) {
    echo "  - $mod: " . ($state ? 'ENABLED' : 'DISABLED') . "\n";
}

