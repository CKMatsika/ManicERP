<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $memberships = $user->activeMemberships()->with('council')->get()
            ->filter(fn ($membership) => $membership->council?->status === 'active');

        if ($memberships->contains(fn ($membership) => $membership->member_type === 'staff')) {
            return redirect()->route('dashboard');
        }

        if ($memberships->contains(fn ($membership) => $membership->member_type === 'citizen')) {
            return redirect()->route('portal.dashboard');
        }

        return redirect()->route('councils.index');
    }
}
