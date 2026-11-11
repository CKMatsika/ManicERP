import React from 'react';

export type StatusType =
    | 'draft'
    | 'pending_approval'
    | 'pending'
    | 'previewed'
    | 'partially_paid'
    | 'posted'
    | 'paid'
    | 'active'
    | 'approved'
    | 'completed'
    | 'verified'
    | 'issued'
    | 'enabled'
    | 'overdue'
    | 'rejected'
    | 'failed'
    | 'revoked'
    | 'cancelled'
    | 'void'
    | 'reversed'
    | 'closed'
    | 'matched'
    | 'imported'
    | 'locked'
    | string;

interface StatusBadgeProps {
    status: StatusType;
    label?: string;
    className?: string;
}

export function getStatusConfig(status: string): {
    tone: 'grey' | 'amber' | 'green' | 'red' | 'slate';
    bgClass: string;
    textClass: string;
    dotClass: string;
    borderClass: string;
    label: string;
} {
    const normalized = (status || '').toLowerCase().trim();

    // Draft / New = grey
    if (['draft', 'new', 'unposted', 'unallocated'].includes(normalized)) {
        return {
            tone: 'grey',
            bgClass: 'bg-slate-100',
            textClass: 'text-slate-700',
            dotClass: 'bg-slate-500',
            borderClass: 'border-slate-200',
            label: formatStatusLabel(status),
        };
    }

    // Pending / Review = amber
    if (['pending_approval', 'pending', 'previewed', 'partially_paid', 'in_review', 'submitted', 'review'].includes(normalized)) {
        return {
            tone: 'amber',
            bgClass: 'bg-amber-50',
            textClass: 'text-amber-800',
            dotClass: 'bg-amber-500',
            borderClass: 'border-amber-200',
            label: formatStatusLabel(status),
        };
    }

    // Posted / Paid / Active / Approved = green
    if (['posted', 'paid', 'active', 'approved', 'completed', 'verified', 'issued', 'enabled', 'balanced', 'allocated'].includes(normalized)) {
        return {
            tone: 'green',
            bgClass: 'bg-emerald-50',
            textClass: 'text-emerald-800',
            dotClass: 'bg-emerald-600',
            borderClass: 'border-emerald-200',
            label: formatStatusLabel(status),
        };
    }

    // Overdue / Rejected / Failed / Void = red
    if (['overdue', 'rejected', 'failed', 'revoked', 'void', 'defaulted', 'unbalanced', 'error'].includes(normalized)) {
        return {
            tone: 'red',
            bgClass: 'bg-rose-50',
            textClass: 'text-rose-800',
            dotClass: 'bg-rose-600',
            borderClass: 'border-rose-200',
            label: formatStatusLabel(status),
        };
    }

    // Reversed / Cancelled / Closed = slate
    return {
        tone: 'slate',
        bgClass: 'bg-slate-100',
        textClass: 'text-slate-700',
        dotClass: 'bg-slate-400',
        borderClass: 'border-slate-200',
        label: formatStatusLabel(status),
    };
}

function formatStatusLabel(status: string): string {
    if (!status) return '—';
    const spaced = status.replace(/_/g, ' ');
    return spaced.charAt(0).toUpperCase() + spaced.slice(1).toLowerCase();
}

export default function StatusBadge({ status, label, className = '' }: StatusBadgeProps) {
    const config = getStatusConfig(status);
    const displayLabel = label || config.label;

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium ${config.bgClass} ${config.textClass} ${config.borderClass} ${className}`}
        >
            <span className={`h-1.5 w-1.5 rounded-full ${config.dotClass}`} aria-hidden="true" />
            <span>{displayLabel}</span>
        </span>
    );
}

