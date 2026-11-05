<?php

namespace App\Modules\Platform\Services;

use App\Models\User;
use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Models\Membership;
use Illuminate\Http\Request;

/**
 * Resolves the council from the host and the authenticated user's validated
 * membership. A council identifier supplied in a request body is never used.
 */
class CouncilResolver
{
    public function __construct(private readonly TenantManager $tenants) {}

    /**
     * @return array{0: Council|null, 1: Membership|null}
     */
    public function resolve(Request $request, ?User $user, string $memberType): array
    {
        $host = strtolower($request->getHost());

        $hostCouncil = Council::query()
            ->whereIn('id', function ($query) use ($host) {
                $query->select('council_id')
                    ->from('council_domains')
                    ->whereRaw('lower(host) = ?', [$host]);
            })
            ->where('status', 'active')
            ->first();

        if ($hostCouncil) {
            $membership = $user
                ? $this->membershipFor($user, $hostCouncil, $memberType)
                : null;

            // A verified council host is authoritative: never silently fall
            // back to another council when the user is not a member here.
            return [$hostCouncil, $membership];
        }

        if (! $user) {
            return [null, null];
        }

        $sessionCode = $request->session()->get(config('tenancy.session_key'));
        $sessionAllowed = config('tenancy.allow_session_council_selection');

        if ($sessionCode && $sessionAllowed) {
            $council = Council::query()
                ->where('code', $sessionCode)
                ->where('status', 'active')
                ->first();

            if ($council) {
                $membership = $this->membershipFor($user, $council, $memberType);

                if ($membership) {
                    return [$council, $membership];
                }
            }
        }

        $memberships = $user->activeMemberships()
            ->where('member_type', $memberType)
            ->with(['council', 'role'])
            ->get()
            ->filter(fn (Membership $m) => $m->council?->status === 'active');

        if ($memberships->count() === 1) {
            $membership = $memberships->first();

            return [$membership->council, $membership];
        }

        return [null, null];
    }

    public function membershipFor(User $user, Council $council, string $memberType): ?Membership
    {
        return $user->activeMemberships()
            ->where('council_id', $council->id)
            ->where('member_type', $memberType)
            ->with('role')
            ->first();
    }

    public function availableCouncils(User $user, string $memberType): array
    {
        return $user->activeMemberships()
            ->where('member_type', $memberType)
            ->with(['council', 'role'])
            ->get()
            ->filter(fn (Membership $m) => $m->council?->status === 'active')
            ->map(fn (Membership $m) => [
                'code' => $m->council->code,
                'name' => $m->council->name,
                'role' => $m->role?->name,
                'demo' => $m->council->is_demo,
            ])
            ->values()
            ->all();
    }
}
