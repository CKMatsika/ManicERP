<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenancy / council isolation
    |--------------------------------------------------------------------------
    |
    | ManicERP keeps a central platform database (council directory, identity,
    | memberships, sessions, queues) and one database per council for
    | operational data. The tenant database name always comes from the
    | server-side council directory, never from a request payload.
    |
    */
    'central_connection' => env('DB_CONNECTION', 'pgsql'),
    'tenant_connection' => 'tenant',
    'session_key' => 'council_code',
    'request_attribute' => 'resolved_council',

    'central_migrations' => 'database/migrations/central',
    'tenant_migrations' => 'database/migrations/tenant',

    // Hosts that are allowed to resolve a council in local development. In
    // production, councils are resolved from verified council_domains rows.
    'allow_session_council_selection' => env('TENANCY_ALLOW_SESSION_SELECTION', true),
];
