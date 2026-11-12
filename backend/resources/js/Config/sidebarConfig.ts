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

/** A launchable department/app. Its pages become the module's navigation. */
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
        description: 'Council executive dashboard and strategic indicators',
        items: [
            { label: 'Executive dashboard', href: '/dashboard', icon: 'LayoutDashboard', section: 'Overview', isPrimary: true, keywords: ['home', 'kpi', 'executive', 'stats', 'council'] },
        ],
    },
    {
        id: 'finance',
        title: 'Finance & accounting',
        icon: 'Landmark',
        description: 'General ledger, IPSAS statements, journals and trial balance',
        items: [
            { label: 'General ledger', href: '/accounting/reports/ledger', icon: 'BookOpen', section: 'Financial reports', module: 'accounting', isPrimary: true, keywords: ['ledger', 'gl', 'accounts', 'double-entry'] },
            { label: 'Trial balance', href: '/accounting/reports/trial-balance', icon: 'Scale', section: 'Financial reports', module: 'accounting', isPrimary: true, keywords: ['trial balance', 'debits', 'credits'] },
            { label: 'Balance sheet (IPSAS 1)', href: '/accounting/reports/position', icon: 'PieChart', section: 'Financial reports', module: 'accounting', isPrimary: true, keywords: ['financial position', 'balance sheet', 'assets', 'liabilities'] },
            { label: 'Financial performance (P&L)', href: '/accounting/reports/performance', icon: 'TrendingUp', section: 'Financial reports', module: 'accounting', isPrimary: true, keywords: ['income statement', 'operating surplus', 'revenue', 'expense'] },
            { label: 'Chart of accounts', href: '/accounting/chart', icon: 'ListTree', section: 'Accounting setup', module: 'accounting', isPrimary: true, keywords: ['coa', 'gl codes', 'structure'] },
            { label: 'Journal vouchers', href: '/accounting/journals', icon: 'FileSpreadsheet', section: 'Accounting setup', module: 'accounting', isPrimary: true, keywords: ['journal', 'vouchers', 'posting', 'accruals'] },
            { label: 'Financial readiness & policy', href: '/accounting/readiness', icon: 'ClipboardCheck', section: 'Accounting setup', module: 'accounting', isPrimary: false, keywords: ['auditor-general', 'readiness', 'ipsas matrix'] },
            { label: 'IPSAS standards register', href: '/accounting/standards', icon: 'BookmarkCheck', section: 'Accounting setup', module: 'accounting', isPrimary: false, keywords: ['ipsas', 'standards', 'disclosures'] },
        ],
    },
    {
        id: 'revenue',
        title: 'Revenue & billing',
        icon: 'Receipt',
        description: 'Customer accounts, tariffs, billing, cashiering and reconciliation',
        items: [
            { label: 'Customer accounts', href: '/revenue/accounts', icon: 'Users', section: 'Accounts & billing', module: 'revenue', isPrimary: true, keywords: ['ratepayers', 'properties', 'debtors'] },
            { label: 'Billing runs & tariffs', href: '/revenue/billing', icon: 'Calculator', section: 'Accounts & billing', module: 'revenue', isPrimary: true, keywords: ['bills', 'rates', 'monthly assessment'] },
            { label: 'Cashier & receipts', href: '/revenue/cashier', icon: 'Coins', section: 'Collections', module: 'revenue', isPrimary: true, keywords: ['cashier', 'pos', 'receipts', 'ecocash', 'till'] },
            { label: 'Bank reconciliation', href: '/revenue/bank', icon: 'CheckCheck', section: 'Collections', module: 'revenue', isPrimary: true, keywords: ['bank statement', 'reconciliation', 'matching'] },
            { label: 'Credit control & arrears (s279)', href: '/revenue/credit-control', icon: 'ShieldAlert', section: 'Account controls', isPrimary: false, keywords: ['credit control', 'payment plans', 'section 279', 'debt recovery'] },
            { label: 'Refunds & waivers', href: '/revenue/refunds', icon: 'ArrowLeftRight', section: 'Account controls', module: 'revenue', isPrimary: false, keywords: ['refunds', 'waivers', 'four-eyes'] },
        ],
    },
    {
        id: 'procurement',
        title: 'Procurement & supply chain',
        icon: 'ShoppingBag',
        description: 'Public procurement (PRAZ), requisitions, purchase orders, stores and AP',
        items: [
            { label: 'Procurement cycle (PRAZ)', href: '/procurement', icon: 'ShoppingBag', section: 'PRAZ procurement', module: 'procurement', isPrimary: true, keywords: ['procurement', 'purchase orders', 'tenders', 'praz', '3-way match', 'requisitions', 'grn', 'receiving'] },
            { label: 'Purchase requisitions', href: '/procurement?tab=requisitions', icon: 'FileText', section: 'PRAZ procurement', module: 'procurement', isPrimary: true, keywords: ['requisitions', 'vote', 'budget commitment'] },
            { label: 'Quotations & awards (PRAZ)', href: '/procurement?tab=quotations', icon: 'FileBadge', section: 'PRAZ procurement', module: 'procurement', isPrimary: true, keywords: ['quotes', 'praz', 'sourcing', 'tenders', 'comparative schedule'] },
            { label: 'Purchase orders (PO)', href: '/procurement?tab=orders', icon: 'ShoppingBag', section: 'Purchasing', module: 'procurement', isPrimary: true, keywords: ['purchase order', 'po', 'contract', 'commitment'] },
            { label: 'Central stores & bin cards', href: '/stores', icon: 'Boxes', section: 'Stores & inventory', module: 'procurement', isPrimary: true, keywords: ['inventory', 'stock', 'bin card', 'warehouse', 'grv', 'sin'] },
            { label: 'Goods receiving (GRN)', href: '/procurement?tab=receiving', icon: 'Truck', section: 'Stores & inventory', module: 'procurement', isPrimary: true, keywords: ['grn', 'goods received', 'delivery note', 'inspection'] },
            { label: 'Supplier invoices & 3-way match', href: '/procurement?tab=invoices', icon: 'ClipboardCheck', section: 'Accounts payable', module: 'procurement', isPrimary: true, keywords: ['invoice', '3-way match', 'matching exception', 'ap'] },
            { label: 'Payment proposals & settlement', href: '/procurement?tab=payments', icon: 'CreditCard', section: 'Accounts payable', module: 'procurement', isPrimary: true, keywords: ['payment proposal', 'settlement', 'dual control'] },
            { label: 'Approved suppliers & banking', href: '/procurement?tab=suppliers', icon: 'Building2', section: 'Supplier management', module: 'procurement', isPrimary: false, keywords: ['suppliers', 'vetting', 'bank accounts', 'dual control'] },
        ],
    },
    {
        id: 'assets',
        title: 'Assets & property',
        icon: 'Building2',
        description: 'Fixed asset registers (IPSAS 17), depreciation and municipal fleet',
        items: [
            { label: 'Fixed assets (IPSAS 17)', href: '/assets', icon: 'Building2', section: 'Asset management', isPrimary: true, keywords: ['asset register', 'depreciation', 'plant', 'equipment'] },
            { label: 'Fleet & heavy plant', href: '/fleet', icon: 'Truck', section: 'Asset management', isPrimary: true, keywords: ['vehicles', 'plant', 'fuel coupons', 'logbook'] },
        ],
    },
    {
        id: 'engineering',
        title: 'Engineering & works',
        icon: 'Wrench',
        description: 'Water utilities, smart metering and capital infrastructure',
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
            { label: 'Licences & statutory permits', href: '/licensing', icon: 'FileBadge', section: 'Licensing', module: 'licensing', isPrimary: true, keywords: ['shop licence', 'health permit', 'liquor', 'inspections'] },
            { label: 'Licensing inbox & approvals', href: '/licensing/notifications', icon: 'Inbox', section: 'Licensing', module: 'licensing', keywords: ['licensing tasks', 'statutory approvals'] },
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
            { label: 'Audit trail & security', href: '/admin/audit', icon: 'History', section: 'Governance', isPrimary: true, keywords: ['audit', 'sha-256', 'tamper-evident', 'logs'] },
            { label: 'Module registry', href: '/admin/modules', icon: 'Layers', section: 'Platform settings', isPrimary: false, keywords: ['modules', 'licensing', 'activation', 'tenant'] },
        ],
    },
];
