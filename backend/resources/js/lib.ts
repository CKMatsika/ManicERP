export type SharedProps = {
    appName: string;
    auth: { id: string; name: string; email: string; is_platform_admin: boolean; mfa_enabled: boolean } | null;
    council: { code: string; name: string; is_demo: boolean; currency: string; timezone: string } | null;
    membership: { type: string; role: string | null; role_name: string | null; requires_mfa: boolean } | null;
    permissions: string[];
    modules: Record<string, boolean>;
    displayTimezone: string;
    aiEnabled: boolean;
    flash: { success?: string | null; error?: string | null; warning?: string | null; info?: string | null; masked?: string | null; recovery_codes?: string[] | null };
};

export function money(minor: number | null | undefined, currency = 'USD'): string {
    const value = (minor ?? 0) / 100;
    const prefix = currency === 'USD' ? 'US$' : `${currency} `;

    try {
        const formatted = new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(value);
        return `${prefix}${formatted}`;
    } catch {
        return `${prefix}${value.toFixed(2)}`;
    }
}

export function reportMoney(minor: number | null | undefined, currency = 'USD', useParens = true): string {
    const value = (minor ?? 0) / 100;
    const prefix = currency === 'USD' ? 'US$' : `${currency} `;
    const absVal = Math.abs(value);

    try {
        const formatted = new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(absVal);
        if (value < 0) {
            return useParens ? `(${prefix}${formatted})` : `-${prefix}${formatted}`;
        }
        return `${prefix}${formatted}`;
    } catch {
        if (value < 0) {
            return useParens ? `(${prefix}${absVal.toFixed(2)})` : `-${prefix}${absVal.toFixed(2)}`;
        }
        return `${prefix}${value.toFixed(2)}`;
    }
}

export function plainMoney(minor: number | null | undefined): string {
    return ((minor ?? 0) / 100).toFixed(2);
}

export function formatDateTime(value: string | null | undefined, timezone = 'Africa/Harare'): string {
    if (!value) return '—';

    try {
        return new Intl.DateTimeFormat('en-GB', {
            timeZone: timezone,
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(value));
    } catch {
        return value;
    }
}

export function formatDate(value: string | null | undefined): string {
    if (!value) return '—';

    try {
        return new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value));
    } catch {
        return value;
    }
}

export function can(permissions: string[], permission: string): boolean {
    return permissions.includes('*') || permissions.includes(permission);
}
