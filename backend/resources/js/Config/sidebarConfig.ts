export type SidebarItem = {
    label: string;
    href: string;
    icon: string;
    permission?: string;
    anyPermissions?: string[];
    module?: string;
    isPrimary?: boolean; // Highlighted / default visible when group is compact
    badge?: string;
    keywords?: string[];
};

export type SidebarGroup = {
    id: string;
    title: string;
    icon: string;
    items: SidebarItem[];
};

export const SIDEBAR_GROUPS: SidebarGroup[] = [
    {
        id: 'overview',
        title: 'Executive & overview',
        icon: 'LayoutDashboard',
        items: [
            { label: 'Executive dashboard', href: '/dashboard', icon: 'LayoutDashboard', isPrimary: true, keywords: ['home', 'kpi', 'executive', 'stats'] },
            { label: 'AI Policy assistant', href: '/ai/assistant', icon: 'Sparkles', permission: 'ai.assistant.use', module: 'ai', isPrimary: true, keywords: ['by-laws', 'ai', 'statutory', 'acts'] },
        ],
    },
    {
        id: 'finance',
        title: 'Finance & accounting',
        icon: 'Landmark',
        items: [
            { label: 'General ledger', href: '/accounting/reports/ledger', icon: 'BookOpen', permission: 'accounting.reports.view', module: 'accounting', isPrimary: true, keywords: ['ledger', 'gl', 'accounts', 'double-entry'] },
            { label: 'Trial balance', href: '/accounting/reports/trial-balance', icon: 'Scale', permission: 'accounting.reports.view', module: 'accounting', isPrimary: true, keywords: ['trial balance', 'debits', 'credits'] },
            { label: 'Balance sheet (IPSAS 1)', href: '/accounting/reports/position', icon: 'PieChart', permission: 'accounting.reports.view', module: 'accounting', isPrimary: true, keywords: ['financial position', 'balance sheet', 'assets', 'liabilities'] },
            { label: 'Financial performance (P&L)', href: '/accounting/reports/performance', icon: 'TrendingUp', permission: 'accounting.reports.view', module: 'accounting', isPrimary: true, keywords: ['income statement', 'operating surplus', 'revenue', 'expense'] },
            { label: 'Chart of accounts', href: '/accounting/chart', icon: 'ListTree', permission: 'accounting.chart.view', module: 'accounting', isPrimary: false, keywords: ['coa', 'gl codes', 'structure'] },
            { label: 'Journal vouchers', href: '/accounting/journals', icon: 'FileSpreadsheet', permission: 'accounting.journal.view', module: 'accounting', isPrimary: false, keywords: ['journal', 'vouchers', 'posting', 'accruals'] },
            { label: 'Financial readiness & policy', href: '/accounting/readiness', icon: 'ClipboardCheck', anyPermissions: ['accounting.requirements.view', 'accounting.opening_balance.manage'], module: 'accounting', isPrimary: false, keywords: ['auditor-general', 'readiness', 'ipsas matrix'] },
            { label: 'IPSAS standards register', href: '/accounting/standards', icon: 'BookmarkCheck', permission: 'accounting.chart.view', module: 'accounting', isPrimary: false, keywords: ['ipsas', 'standards', 'disclosures'] },
        ],
    },
    {
        id: 'revenue',
        title: 'Revenue & billing',
        icon: 'Receipt',
        items: [
            { label: 'Customer accounts', href: '/revenue/accounts', icon: 'Users', permission: 'revenue.account.view', module: 'revenue', isPrimary: true, keywords: ['ratepayers', 'properties', 'debtors'] },
            { label: 'Billing runs & tariffs', href: '/revenue/billing', icon: 'Calculator', permission: 'revenue.tariff.view', module: 'revenue', isPrimary: true, keywords: ['bills', 'rates', 'monthly assessment'] },
            { label: 'Cashier & receipts', href: '/revenue/cashier', icon: 'Coins', permission: 'revenue.cashier.open', module: 'revenue', isPrimary: true, keywords: ['cashier', 'pos', 'receipts', 'ecocash', 'till'] },
            { label: 'Bank reconciliation', href: '/revenue/bank', icon: 'CheckCheck', permission: 'revenue.reconciliation.manage', module: 'revenue', isPrimary: true, keywords: ['bank statement', 'reconciliation', 'matching'] },
            { label: 'Credit control & arrears (s279)', href: '/revenue/credit-control', icon: 'ShieldAlert', isPrimary: false, keywords: ['credit control', 'payment plans', 'section 279', 'debt recovery'] },
            { label: 'Refunds & waivers', href: '/revenue/refunds', icon: 'ArrowLeftRight', permission: 'revenue.refund.view', module: 'revenue', isPrimary: false, keywords: ['refunds', 'waivers', 'four-eyes'] },
        ],
    },
    {
        id: 'assets',
        title: 'Assets, stores & supply chain',
        icon: 'Building2',
        items: [
            { label: 'Fixed assets (IPSAS 17)', href: '/assets', icon: 'Building2', isPrimary: true, keywords: ['asset register', 'depreciation', 'plant', 'equipment'] },
            { label: 'Central stores & bin cards', href: '/stores', icon: 'Boxes', isPrimary: true, keywords: ['inventory', 'stock', 'bin card', 'warehouse'] },
            { label: 'Fleet & heavy plant', href: '/fleet', icon: 'Truck', isPrimary: true, keywords: ['vehicles', 'plant', 'fuel coupons', 'logbook'] },
            { label: 'Procurement (PRAZ)', href: '/procurement', icon: 'ShoppingBag', permission: 'procurement.reports.view', module: 'procurement', isPrimary: false, keywords: ['procurement', 'purchase orders', 'tenders', 'praz', '3-way match'] },
        ],
    },
    {
        id: 'engineering',
        title: 'Engineering & capital works',
        icon: 'Wrench',
        items: [
            { label: 'Water meters & AMR billing', href: '/engineering/water-meters', icon: 'Droplets', isPrimary: true, keywords: ['water', 'amr', 'iot meters', 'bursts', 'consumption'] },
            { label: 'Devolution capital projects', href: '/projects/capital', icon: 'HardHat', isPrimary: true, keywords: ['devolution', 'capital works', 'retention money', 'contractor certificates'] },
        ],
    },
    {
        id: 'planning',
        title: 'Town planning, land & housing',
        icon: 'MapPin',
        items: [
            { label: 'GIS Cadastre & property database', href: '/land/gis', icon: 'Map', isPrimary: true, keywords: ['gis', 'cadastre', 'beacons', 'surveyor general', 'stands', 'plots'] },
            { label: 'Housing waiting list & allocations', href: '/land/housing-allocations', icon: 'Home', isPrimary: true, keywords: ['housing', 'waiting list', 'allocations', 'stands'] },
        ],
    },
    {
        id: 'hr',
        title: 'Human resources & payroll',
        icon: 'UserCheck',
        items: [
            { label: 'HR & municipal payroll', href: '/hr/payroll', icon: 'CreditCard', isPrimary: true, keywords: ['payroll', 'salaries', 'paye', 'nssa', 'lapf', 'aids levy', 'employees'] },
        ],
    },
    {
        id: 'community',
        title: 'Community, health & emergency',
        icon: 'HeartHandshake',
        items: [
            { label: 'Licences & statutory permits', href: '/licensing', icon: 'FileBadge', anyPermissions: ['licensing.applications.view', 'licensing.types.manage'], module: 'licensing', isPrimary: true, keywords: ['shop licence', 'health permit', 'liquor', 'inspections'] },
            { label: 'Cemeteries & grave register', href: '/community/cemeteries', icon: 'Cross', isPrimary: true, keywords: ['cemetery', 'graves', 'burial orders', 'interment'] },
            { label: 'Emergency dispatch & fire brigade', href: '/emergency', icon: 'Flame', isPrimary: true, keywords: ['emergency', 'fire brigade', '993', 'hydrants', 'incidents'] },
            { label: 'Licensing inbox & approvals', href: '/licensing/notifications', icon: 'Inbox', permission: 'licensing.notifications.view', module: 'licensing', isPrimary: false, keywords: ['licensing tasks', 'statutory approvals'] },
        ],
    },
    {
        id: 'governance',
        title: 'Administration & governance',
        icon: 'ShieldCheck',
        items: [
            { label: 'Audit trail & security', href: '/admin/audit', icon: 'History', permission: 'platform.audit.view', isPrimary: true, keywords: ['audit', 'sha-256', 'tamper-evident', 'logs'] },
            { label: 'Module registry', href: '/admin/modules', icon: 'Layers', permission: 'platform.modules.manage', isPrimary: false, keywords: ['modules', 'licensing', 'activation', 'tenant'] },
        ],
    },
];

