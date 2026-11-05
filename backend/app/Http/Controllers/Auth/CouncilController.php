<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Services\CouncilResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CouncilController extends Controller
{
    public function __construct(private readonly CouncilResolver $resolver) {}

    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Councils/Index', [
            'staff' => $this->resolver->availableCouncils($user, 'staff'),
            'citizen' => $this->resolver->availableCouncils($user, 'citizen'),
        ]);
    }

    public function select(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:24']]);

        $user = $request->user();
        $membership = $user->activeMemberships()
            ->whereHas('council', fn ($query) => $query->where('code', $data['code'])->where('status', 'active'))
            ->with('council')
            ->first();

        if (! $membership) {
            abort(403, 'You are not a member of that council.');
        }

        $request->session()->put(config('tenancy.session_key'), $membership->council->code);

        return redirect()->route('home');
    }
}
