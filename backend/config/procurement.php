<?php

return [
    // Demonstration-only defaults. Councils must approve their own thresholds,
    // procurement rules, expense mappings and AP control account.
    'demonstration_only' => true,
    'accounting' => [
        'ap_control' => '2100',
        'bank_account' => '1120',
    ],
    'approval_thresholds_minor' => [
        'quotation_count' => 3,
        'independent_award_approval' => true,
    ],
    'matching_tolerance_minor' => 0,
    'sandbox_settlement_enabled' => true,
];
