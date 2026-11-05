<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Posting account mapping (DEMONSTRATION defaults)
    |--------------------------------------------------------------------------
    |
    | These codes exist in the demonstration chart only. A real council must
    | import and approve its own chart and then approve a posting-rule matrix;
    | the production readiness blocker stays visible until that happens.
    |
    */
    'receivables' => [
        'rates' => '1310',
        'water' => '1320',
        'refuse' => '1320',
        'rental' => '1330',
    ],
    'revenue' => [
        'rates' => '4010',
        'water' => '4020',
        'refuse' => '4030',
        'rental' => '4040',
    ],
    'receipts' => [
        'cash' => '1110',
        'bank' => '1120',
        'unapplied' => '2050',
        'refund' => '2050',
    ],
    'billing_rules' => [
        'rates' => 'billing.rates',
        'water' => 'billing.water',
        'refuse' => 'billing.refuse',
        'rental' => 'billing.rental',
    ],
    'bill_default_due_days' => 30,
];
