import React from 'react';
import Icon from './Icon';

export interface BulkActionItem {
    label: string;
    onClick: () => void;
    icon?: string;
    tone?: 'default' | 'danger';
}

interface BulkActionsProps {
    selectedCount: number;
    totalCount?: number;
    onClearSelection: () => void;
    actions: BulkActionItem[];
    className?: string;
}

export default function BulkActions({
    selectedCount,
    totalCount,
    onClearSelection,
    actions,
    className = '',
}: BulkActionsProps) {
    if (selectedCount === 0) return null;

    return (
        <div
            className={`mb-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-2 text-sm text-emerald-950 transition-all ${className}`}
        >
            <div className="flex items-center gap-2">
                <span className="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-700 text-xs font-semibold text-white">
                    {selectedCount}
                </span>
                <span className="font-medium">
                    {selectedCount} {selectedCount === 1 ? 'item' : 'items'} selected
                    {totalCount ? ` of ${totalCount}` : ''}
                </span>
                <button
                    type="button"
                    onClick={onClearSelection}
                    className="ml-2 text-xs font-medium text-emerald-800 underline hover:text-emerald-950"
                >
                    Deselect all
                </button>
            </div>

            <div className="flex items-center gap-2">
                {actions.map((act, i) => (
                    <button
                        key={i}
                        type="button"
                        onClick={act.onClick}
                        className={`inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium transition-colors ${
                            act.tone === 'danger'
                                ? 'bg-rose-600 text-white hover:bg-rose-700'
                                : 'border border-emerald-300 bg-white text-emerald-900 hover:bg-emerald-100'
                        }`}
                    >
                        {act.icon && <Icon name={act.icon} className="h-3.5 w-3.5" />}
                        <span>{act.label}</span>
                    </button>
                ))}
            </div>
        </div>
    );
}

