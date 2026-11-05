<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Models\Council;
use App\Modules\Platform\Models\Role;

/**
 * Documented default role set created for every council. Permissions are
 * always taken from the module manifests; roles are configurable afterwards.
 */
class DefaultRoles
{
    /** @return array<string, array{name: string, description: string, permissions: array<int, string>, requires_mfa: bool}> */
    public static function definitions(): array
    {
        $all = [
            'platform.councils.manage', 'platform.users.manage', 'platform.modules.manage',
            'platform.audit.view', 'platform.settings.manage',
            'accounting.chart.view', 'accounting.chart.manage', 'accounting.chart.approve',
            'accounting.journal.view', 'accounting.journal.create', 'accounting.journal.post',
            'accounting.journal.reverse', 'accounting.period.lock', 'accounting.reports.view',
            'accounting.policy.manage',
            'revenue.party.view', 'revenue.party.manage', 'revenue.property.view',
            'revenue.property.manage', 'revenue.account.view', 'revenue.account.manage',
            'revenue.connection.view', 'revenue.connection.manage', 'revenue.reading.capture',
            'revenue.reading.confirm', 'revenue.tariff.view', 'revenue.tariff.manage',
            'revenue.tariff.approve', 'revenue.billing.preview', 'revenue.billing.approve',
            'revenue.cashier.open', 'revenue.cashier.close', 'revenue.payment.capture',
            'revenue.payment.verify', 'revenue.payment.reverse', 'revenue.reconciliation.manage',
            'revenue.reports.view',
            'customer.portal.access', 'customer.link.request', 'customer.link.verify',
            'customer.service_request.create', 'customer.service_request.manage',
            'ai.assistant.use', 'ai.assistant.manage',
        ];

        return [
            'council_admin' => [
                'name' => 'Council administrator',
                'description' => 'Full operational administration for this council. Cannot disable core safeguards.',
                'permissions' => ['*'],
                'requires_mfa' => true,
            ],
            'finance_officer' => [
                'name' => 'Finance officer',
                'description' => 'Prepares journals and chart data; cannot post restricted entries without an approver.',
                'permissions' => [
                    'accounting.chart.view', 'accounting.chart.manage', 'accounting.journal.view',
                    'accounting.journal.create', 'accounting.journal.post', 'accounting.reports.view',
                    'revenue.party.view', 'revenue.account.view', 'revenue.reports.view', 'platform.audit.view',
                ],
                'requires_mfa' => true,
            ],
            'finance_approver' => [
                'name' => 'Finance approver',
                'description' => 'Approves charts, tariffs and journals, reverses entries and locks periods.',
                'permissions' => [
                    'accounting.chart.view', 'accounting.chart.approve', 'accounting.journal.view',
                    'accounting.journal.reverse', 'accounting.period.lock', 'accounting.reports.view',
                    'accounting.policy.manage', 'revenue.billing.approve', 'revenue.tariff.approve',
                    'revenue.payment.reverse', 'revenue.reconciliation.manage', 'revenue.reports.view',
                    'platform.audit.view',
                ],
                'requires_mfa' => true,
            ],
            'revenue_clerk' => [
                'name' => 'Revenue clerk',
                'description' => 'Maintains parties, properties, accounts, readings and billing previews.',
                'permissions' => [
                    'revenue.party.view', 'revenue.party.manage', 'revenue.property.view',
                    'revenue.property.manage', 'revenue.account.view', 'revenue.account.manage',
                    'revenue.connection.view', 'revenue.connection.manage', 'revenue.reading.capture',
                    'revenue.reading.confirm', 'revenue.tariff.view', 'revenue.billing.preview',
                    'revenue.account.view', 'revenue.reports.view', 'customer.service_request.manage',
                ],
                'requires_mfa' => false,
            ],
            'cashier' => [
                'name' => 'Cashier',
                'description' => 'Captures verified payments and closes cashier sessions. Cannot approve or reverse.',
                'permissions' => [
                    'revenue.account.view', 'revenue.payment.capture', 'revenue.cashier.open',
                    'revenue.cashier.close', 'revenue.reports.view',
                ],
                'requires_mfa' => false,
            ],
            'auditor' => [
                'name' => 'Auditor',
                'description' => 'Read-only access to accounting, revenue and the audit trail.',
                'permissions' => [
                    'platform.audit.view', 'accounting.chart.view', 'accounting.journal.view',
                    'accounting.reports.view', 'revenue.account.view', 'revenue.reports.view',
                    'revenue.party.view', 'revenue.property.view',
                ],
                'requires_mfa' => true,
            ],
            'engineer' => [
                'name' => 'Engineering officer',
                'description' => 'Property, connection and meter reading operations.',
                'permissions' => [
                    'revenue.property.view', 'revenue.connection.view', 'revenue.connection.manage',
                    'revenue.reading.capture', 'revenue.reading.confirm',
                ],
                'requires_mfa' => false,
            ],
            'citizen' => [
                'name' => 'Citizen / customer',
                'description' => 'Access to the citizen portal for linked accounts only.',
                'permissions' => [
                    'customer.portal.access', 'customer.link.request', 'customer.service_request.create',
                ],
                'requires_mfa' => false,
            ],
        ];
    }

    public function seed(Council $council): void
    {
        foreach (self::definitions() as $code => $definition) {
            Role::updateOrCreate(
                ['council_id' => $council->id, 'code' => $code],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'permissions' => $definition['permissions'],
                    'requires_mfa' => $definition['requires_mfa'],
                    'is_system' => true,
                ],
            );
        }
    }
}
