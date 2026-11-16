<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Models\CouncilDomain;
use App\Modules\Platform\Services\CouncilProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CouncilManagementController extends Controller
{
    public function index(): Response
    {
        $councils = Council::query()
            ->with(['domains'])
            ->withCount(['memberships', 'modules' => fn ($q) => $q->where('status', 'enabled')])
            ->orderBy('name')
            ->get()
            ->map(fn (Council $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'type' => $c->type,
                'status' => $c->status,
                'currency_code' => $c->currency_code,
                'timezone' => $c->timezone,
                'is_demo' => (bool) $c->is_demo,
                'provisioned_at' => $c->provisioned_at?->toIso8601String(),
                'members_count' => $c->memberships_count,
                'modules_count' => $c->modules_count,
                'domains' => $c->domains->map(fn (CouncilDomain $d) => [
                    'id' => $d->id,
                    'host' => $d->host,
                    'is_primary' => (bool) $d->is_primary,
                ]),
            ]);

        return Inertia::render('Admin/Councils', [
            'councils' => $councils,
            'total_councils' => $councils->count(),
            'active_councils' => $councils->where('status', 'active')->count(),
            'total_domains' => $councils->sum(fn ($c) => count($c['domains'])),
        ]);
    }

    public function store(Request $request, CouncilProvisioner $provisioner): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:24', 'unique:councils,code', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:urban,rural,municipality,city'],
            'currency_code' => ['required', 'string', 'in:USD,ZWG,ZAR,BWP'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'domain' => ['nullable', 'string', 'max:191'],
        ]);

        $code = strtoupper(trim($data['code']));
        $domains = [];
        if (! empty($data['domain'])) {
            $domains[] = strtolower(trim($data['domain']));
        }

        try {
            $council = $provisioner->provision([
                'code' => $code,
                'name' => $data['name'],
                'type' => $data['type'],
                'currency_code' => $data['currency_code'],
                'timezone' => $data['timezone'] ?? 'Africa/Harare',
                'is_demo' => false,
                'domains' => $domains,
            ], $request->user());

            return back()->with('success', "Council '{$council->name}' [{$council->code}] provisioned successfully.");
        } catch (\Throwable $e) {
            return back()->with('error', "Provisioning failed: {$e->getMessage()}");
        }
    }

    public function update(Request $request, Council $council): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:urban,rural,municipality,city'],
            'currency_code' => ['required', 'string', 'in:USD,ZWG,ZAR,BWP'],
            'status' => ['required', 'string', 'in:active,suspended,pending'],
            'timezone' => ['required', 'string', 'max:64'],
        ]);

        $council->update($data);

        return back()->with('success', "Council '{$council->name}' updated successfully.");
    }
}
