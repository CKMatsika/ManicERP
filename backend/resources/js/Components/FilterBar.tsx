import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import Icon from './Icon';

export interface FilterOption {
    label: string;
    value: string;
}

export interface FilterConfig {
    key: string;
    label: string;
    options: FilterOption[];
    value?: string;
}

interface FilterBarProps {
    searchPlaceholder?: string;
    searchValue?: string;
    filters?: FilterConfig[];
    dateRange?: {
        from?: string;
        to?: string;
        fromKey?: string;
        toKey?: string;
    };
    onSearchChange?: (val: string) => void;
    onFilterChange?: (key: string, val: string) => void;
    onClear?: () => void;
    className?: string;
    exportOptions?: Array<{
        label: string;
        onClick: () => void;
    }>;
}

export default function FilterBar({
    searchPlaceholder = 'Search...',
    searchValue = '',
    filters = [],
    dateRange,
    onSearchChange,
    onFilterChange,
    onClear,
    className = '',
    exportOptions,
}: FilterBarProps) {
    const [searchTerm, setSearchTerm] = useState(searchValue);
    const [exportOpen, setExportOpen] = useState(false);

    useEffect(() => {
        setSearchTerm(searchValue);
    }, [searchValue]);

    function handleSearchSubmit(e: React.FormEvent) {
        e.preventDefault();
        applyFilter('q', searchTerm);
    }

    function applyFilter(key: string, value: string) {
        if (onFilterChange) {
            onFilterChange(key, value);
            return;
        }

        const url = new URL(window.location.href);
        if (value && value.trim() !== '') {
            url.searchParams.set(key, value);
        } else {
            url.searchParams.delete(key);
        }
        url.searchParams.delete('page'); // Reset to page 1 on filter
        router.get(url.pathname + url.search, {}, { preserveState: true, replace: true });
    }

    function handleClearAll() {
        if (onClear) {
            onClear();
            return;
        }
        router.get(window.location.pathname, {}, { preserveState: true, replace: true });
    }

    // Determine active filter chips
    const activeChips: Array<{ key: string; label: string; value: string; displayVal: string }> = [];
    if (searchTerm) {
        activeChips.push({ key: 'q', label: 'Search', value: searchTerm, displayVal: `"${searchTerm}"` });
    }
    filters.forEach((f) => {
        if (f.value) {
            const matchedOpt = f.options.find((o) => o.value === f.value);
            activeChips.push({
                key: f.key,
                label: f.label,
                value: f.value,
                displayVal: matchedOpt ? matchedOpt.label : f.value,
            });
        }
    });

    return (
        <div className={`mb-4 space-y-2.5 ${className}`}>
            <div className="flex flex-wrap items-center justify-between gap-3">
                {/* Search & dropdowns */}
                <div className="flex flex-1 flex-wrap items-center gap-2.5">
                    {/* Search box */}
                    <form onSubmit={handleSearchSubmit} className="relative min-w-[240px] max-w-sm flex-1">
                        <Icon
                            name="Search"
                            className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                        />
                        <input
                            type="search"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            onBlur={() => {
                                if (searchTerm !== searchValue) {
                                    applyFilter('q', searchTerm);
                                }
                            }}
                            placeholder={searchPlaceholder}
                            className="h-9 w-full rounded-lg border border-slate-300 bg-white pl-9 pr-3 text-sm text-slate-800 placeholder-slate-400 transition-colors focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                        />
                    </form>

                    {/* Filter dropdowns */}
                    {filters.map((filter) => (
                        <div key={filter.key} className="relative">
                            <select
                                value={filter.value || ''}
                                onChange={(e) => applyFilter(filter.key, e.target.value)}
                                className="h-9 rounded-lg border border-slate-300 bg-white px-3 pr-8 text-sm text-slate-700 transition-colors hover:border-slate-400 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                aria-label={filter.label}
                            >
                                <option value="">{filter.label}: All</option>
                                {filter.options.map((opt) => (
                                    <option key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    ))}

                    {/* Date inputs if provided */}
                    {dateRange && (
                        <div className="flex items-center gap-1.5 text-xs text-slate-600">
                            <input
                                type="date"
                                value={dateRange.from || ''}
                                onChange={(e) => applyFilter(dateRange.fromKey || 'from', e.target.value)}
                                className="h-9 rounded-lg border border-slate-300 bg-white px-2.5 text-sm text-slate-700 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                aria-label="Start date"
                            />
                            <span>to</span>
                            <input
                                type="date"
                                value={dateRange.to || ''}
                                onChange={(e) => applyFilter(dateRange.toKey || 'to', e.target.value)}
                                className="h-9 rounded-lg border border-slate-300 bg-white px-2.5 text-sm text-slate-700 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                aria-label="End date"
                            />
                        </div>
                    )}

                    {activeChips.length > 0 && (
                        <button
                            type="button"
                            onClick={handleClearAll}
                            className="text-xs font-medium text-slate-500 hover:text-slate-900 hover:underline"
                        >
                            Clear filters
                        </button>
                    )}
                </div>

                {/* Export menu if supplied */}
                {exportOptions && exportOptions.length > 0 && (
                    <div className="relative">
                        <button
                            type="button"
                            onClick={() => setExportOpen(!exportOpen)}
                            className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700"
                        >
                            <Icon name="Download" className="h-4 w-4 text-slate-500" />
                            <span>Export</span>
                            <Icon name="ChevronDown" className="h-3.5 w-3.5 text-slate-400" />
                        </button>
                        {exportOpen && (
                            <div className="absolute right-0 z-20 mt-1 w-40 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                                {exportOptions.map((opt, i) => (
                                    <button
                                        key={i}
                                        type="button"
                                        onClick={() => {
                                            setExportOpen(false);
                                            opt.onClick();
                                        }}
                                        className="w-full px-3 py-1.5 text-left text-xs text-slate-700 hover:bg-slate-50"
                                    >
                                        {opt.label}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </div>

            {/* Removable chips */}
            {activeChips.length > 0 && (
                <div className="flex flex-wrap items-center gap-1.5 pt-1">
                    <span className="text-xs text-slate-400">Active:</span>
                    {activeChips.map((chip) => (
                        <span
                            key={chip.key}
                            className="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-700"
                        >
                            <span className="text-slate-500">{chip.label}:</span>
                            <span className="font-medium">{chip.displayVal}</span>
                            <button
                                type="button"
                                onClick={() => {
                                    if (chip.key === 'q') setSearchTerm('');
                                    applyFilter(chip.key, '');
                                }}
                                className="ml-0.5 text-slate-400 hover:text-slate-700"
                                aria-label={`Remove filter for ${chip.label}`}
                            >
                                ×
                            </button>
                        </span>
                    ))}
                </div>
            )}
        </div>
    );
}

