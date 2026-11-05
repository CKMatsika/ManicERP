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

    try {
        return new Intl.NumberFormat('en-ZW', {
            style: 'currency',
            currency,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(value);
    } catch {
        return `${currency} ${value.toFixed(2)}`;
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
    return permissions.includes(permission);
}
