<?php

namespace App\Http\Controllers;

use App\Modules\Platform\Models\Council;
use Inertia\Inertia;

class LandingController extends Controller
{
    public function index()
    {
        $council = Council::where('code', 'DEMO')->first();

        return Inertia::render('Landing', [
            'council' => $council ? [
                'name' => $council->name,
                'code' => $council->code,
                'currency' => $council->currency_code,
            ] : null,
            'demoCredentials' => [
                'admin' => [
                    'email' => 'admin@demo.manicerp.test',
                    'password' => 'Demo!Pass2026',
                    'mfa_code' => '123456',
                    'role' => 'Super Administrator / Town Clerk',
                ],
                'citizen' => [
                    'email' => 'citizen@example.com',
                    'password' => 'Demo!Pass2026',
                    'role' => 'Citizen / Ratepayer',
                ],
            ],
        ]);
    }
}

