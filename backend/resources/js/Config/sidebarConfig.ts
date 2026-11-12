export type SidebarItem = {
    label: string;
    href: string;
    icon: string;
    permission?: string;
    anyPermissions?: string[];
    module?: string;
    section?: string;
    isPrimary?: boolean;
    badge?: string;
    keywords?: string[];
};

/** A launchable app. Its pages become the selected app's left navigation. */
export type SidebarGroup = {
    id: string;
    title: string;
    icon: string;
    description: string;
    items: SidebarItem[];
};

export const SIDEBAR_GROUPS: SidebarGroup[] = [
    {
        id: 'overview',
        title: 'Executive overview',
        icon: 'LayoutDashboard',
        description: 'Council dashboard and policy guidance',
        items: [
            { label: 'Executive dashboard', href: '/dashboard', icon: 'LayoutDashboard', section: 'Overview', isPrimary: true, keywords: ['home', 'kpi', 'executive', 'stats'] },
            { label: 'AI Policy assistant', href: '/ai/assistant', icon: 'Sparkles', section: 'Tools', permission: 'ai.assistant.use', module: 'ai', isPrimary: true, keywords: ['by-laws', 'ai', 'statutory', 'acts'] },
        ],
    },
    {
        id: 'finance',
        title: 'Finance & accounting',
        icon: 'Landmark',
        description: 'Ledger, financial reports and accounting setup',
        items: [
            { label: 'General ledger', href: '/accounting/reports/ledger', icon: 'BookOpen', section: 'Financial reports', permission: 'accounting.reports.view', module: 'accounting', isPrimary: true, keywords: ['ledger', 'gl', 'accounts', 'double-entry'] },
            { label: 'Trial balance', href: '/accounting/reports/trial-balance', icon: 'Scale', section: 'Financial reports', permission: 'accounting.reports.view', module: 'accounting', isPrimary: true, keywords: ['trial balance', 'debits', 'credits'] },
            { label: 'Balance sheet (IPSAS 1)', href: '/accounting/reports/position', icon: 'PieChart', section: 'Financial reports', permission: 'accounting.reports.view', module: 'accounting', isPrimary: true, keywords: ['financial position', 'balance sheet', 'assets', 'liabilities'] },
            { label: 'Financial performance (P&L)', href: '/accounting/reports/performance', icon: 'TrendingUp', section: 'Financial reports', permission: 'accounting.reports.view', module: 'accounting', isPrimary: true, keywords: ['income statement', 'operating surplus', 'revenue', 'expense'] },
            { label: 'Chart of accounts', href: '/accounting/chart', icon: 'ListTree', section: 'Accounting setup', permission: 'accounting.chart.view', module: 'accounting', keywords: ['coa', 'gl codes', 'structure'] },
            { label: 'Journal vouchers', href: '/accounting/journals', icon: 'FileSpreadsheet', section: 'Accounting setup', permission: 'accounting.journal.view', module: 'accounting', keywords: ['journal', 'vouchers', 'posting', 'accruals'] },
            { label: 'Financial readiness & policy', href: '/accounting/readiness', icon: 'ClipboardCheck', section: 'Accounting setup', anyPermissions: ['accounting.requirements.view', 'accounting.opening_balance.manage', 'accounting.opening_balance.approve', 'accounting.report_mapping.manage', 'accounting.report_mapping.approve', 'accounting.revenue_policy.manage', 'accounting.revenue_policy.approve', 'accounting.statements.view'], module: 'accounting', keywords: ['auditor-general', 'readiness', 'ipsas matrix'] },
            { label: 'IPSAS standards register', href: '/accounting/standards', icon: 'BookmarkCheck', section: 'Accounting setup', permission: 'accounting.chart.view', module: 'accounting', keywords: ['ipsas', 'standards', 'disclosures'] },
        ],
    },
    {
        id: 'revenue',
        title: 'Revenue & billing',
        icon: 'Receipt',
        description: 'Customer accounts, billing and collections',
        items: [
            { label: 'Customer accounts', href: '/revenue/accounts', icon: 'Users', section: 'Accounts & billing', permission: 'revenue.account.view', module: 'revenue', isPrimary: true, keywords: ['ratepayers', 'properties', 'debtors'] },
            { label: 'Billing runs & tariffs', href: '/revenue/billing', icon: 'Calculator', section: 'Accounts & billing', permission: 'revenue.tariff.view', module: 'revenue', isPrimary: true, keywords: ['bills', 'rates', 'monthly assessment'] },
            { label: 'Cashier & receipts', href: '/revenue/cashier', icon: 'Coins', section: 'Collections', permission: 'revenue.cashier.open', module: 'revenue', isPrimary: true, keywords: ['cashier', 'pos', 'receipts', 'ecocash', 'till'] },
            { label: 'Bank reconciliation', href: '/revenue/bank', icon: 'CheckCheck', section: 'Collections', permission: 'revenue.reconciliation.manage', module: 'revenue', isPrimary: true, keywords: ['bank statement', 'reconciliation', 'matching'] },
            { label: 'Credit control & arrears (s279)', href: '/revenue/credit-control', icon: 'ShieldAlert', section: 'Account controls', isPrimary: false, keywords: ['credit control', 'payment plans', 'section 279', 'debt recovery'] },
            { label: 'Refunds & waivers', href: '/revenue/refunds', icon: 'ArrowLeftRight', section: 'Account controls', permission: 'revenue.refund.view', module: 'revenue', isPrimary: false, keywords: ['refunds', 'waivers', 'four-eyes'] },
        ],
    },
    {
        id: 'assets',
        title: 'Assets & supply chain',
        icon: 'Building2',
        description: 'Assets, stores, fleet and procurement',
        items: [
            { label: 'Fixed assets (IPSAS 17)', href: '/assets', icon: 'Building2', section: 'Asset operations', isPrimary: true, keywords: ['asset register', 'depreciation', 'plant', 'equipment'] },
            { label: 'Central stores & bin cards', href: '/stores', icon: 'Boxes', section: 'Asset operations', isPrimary: true, keywords: ['inventory', 'stock', 'bin card', 'warehouse'] },
            { label: 'Fleet & heavy plant', href: '/fleet', icon: 'Truck', section: 'Asset operations', isPrimary: true, keywords: ['vehicles', 'plant', 'fuel coupons', 'logbook'] },
            { label: 'Procurement (PRAZ)', href: '/procurement', icon: 'ShoppingBag', section: 'Supply chain', isPrimary: true, permission: 'procurement.reports.view', module: 'procurement', keywords: ['procurement', 'purchase orders', 'tenders', 'praz', '3-way match', 'requisitions', 'grn', 'receiving'] },
        ],
    },
    {
        id: 'engineering',
        title: 'Engineering & works',
        icon: 'Wrench',
        description: 'Water utilities and capital projects',
        items: [
            { label: 'Water meters & AMR billing', href: '/engineering/water-meters', icon: 'Droplets', section: 'Utilities', isPrimary: true, keywords: ['water', 'amr', 'iot meters', 'bursts', 'consumption'] },
            { label: 'Devolution capital projects', href: '/projects/capital', icon: 'HardHat', section: 'Capital works', isPrimary: true, keywords: ['devolution', 'capital works', 'retention money', 'contractor certificates'] },
        ],
    },
    {
        id: 'planning',
        title: 'Land & housing',
        icon: 'MapPin',
        description: 'Cadastre, parcels and housing allocations',
        items: [
            { label: 'GIS cadastre & property database', href: '/land/gis', icon: 'Map', section: 'Land management', isPrimary: true, keywords: ['gis', 'cadastre', 'beacons', 'surveyor general', 'stands', 'plots'] },
            { label: 'Housing waiting list & allocations', href: '/land/housing-allocations', icon: 'Home', section: 'Housing', isPrimary: true, keywords: ['housing', 'waiting list', 'allocations', 'stands'] },
        ],
    },
    {
        id: 'hr',
        title: 'HR & payroll',
        icon: 'UserCheck',
        description: 'Staff records and municipal payroll',
        items: [
            { label: 'HR & municipal payroll', href: '/hr/payroll', icon: 'CreditCard', section: 'People', isPrimary: true, keywords: ['payroll', 'salaries', 'paye', 'nssa', 'lapf', 'aids levy', 'employees'] },
        ],
    },
    {
        id: 'community',
        title: 'Community & safety',
        icon: 'HeartHandshake',
        description: 'Licensing, community services and emergency response',
        items: [
            { label: 'Licences & statutory permits', href: '/licensing', icon: 'FileBadge', section: 'Licensing', anyPermissions: ['licensing.applications.view', 'licensing.types.manage'], module: 'licensing', isPrimary: true, keywords: ['shop licence', 'health permit', 'liquor', 'inspections'] },
            { label: 'Licensing inbox & approvals', href: '/licensing/notifications', icon: 'Inbox', section: 'Licensing', permission: 'licensing.notifications.view', module: 'licensing', keywords: ['licensing tasks', 'statutory approvals'] },
            { label: 'Cemeteries & grave register', href: '/community/cemeteries', icon: 'Cross', section: 'Community services', isPrimary: true, keywords: ['cemetery', 'graves', 'burial orders', 'interment'] },
            { label: 'Emergency dispatch & fire brigade', href: '/emergency', icon: 'Flame', section: 'Emergency response', isPrimary: true, keywords: ['emergency', 'fire brigade', '993', 'hydrants', 'incidents'] },
        ],
    },
    {
        id: 'governance',
        title: 'Administration',
        icon: 'ShieldCheck',
        description: 'Security, audit and platform administration',
        items: [
            { label: 'Audit trail & security', href: '/admin/audit', icon: 'History', section: 'Governance', permission: 'platform.audit.view', isPrimary: true, keywords: ['audit', 'sha-256', 'tamper-evident', 'logs'] },
            { label: 'Module registry', href: '/admin/modules', icon: 'Layers', section: 'Platform settings', permission: 'platform.modules.manage', isPrimary: false, keywords: ['modules', 'licensing', 'activation', 'tenant'] },
        ],
    },
];
