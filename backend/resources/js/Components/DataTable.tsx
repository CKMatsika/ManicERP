import React, { useState, useId } from 'react';
import Icon from './Icon';

export interface Column<T> {
    key: string;
    header: string;
    align?: 'left' | 'center' | 'right';
    width?: string;
    sortable?: boolean;
    render?: (row: T, index: number) => React.ReactNode;
}

export interface RowAction<T> {
    label: string;
    onClick: (row: T) => void;
    icon?: string;
    tone?: 'default' | 'danger';
    hidden?: (row: T) => boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface DataTableProps<T> {
    columns: Column<T>[];
    data: T[];
    keyField: keyof T | ((row: T) => string);
    isLoading?: boolean;
    emptyTitle?: string;
    emptyHint?: string;
    emptyAction?: {
        label: string;
        onClick?: () => void;
        href?: string;
    };
    rowActions?: RowAction<T>[];
    onRowClick?: (row: T) => void;
    // Selection
    selectedRows?: string[];
    onSelectRows?: (keys: string[]) => void;
    // Sorting
    sortColumn?: string;
    sortDirection?: 'asc' | 'desc';
    onSort?: (key: string) => void;
    // Pagination
    pagination?: {
        total?: number;
        from?: number;
        to?: number;
        perPage?: number;
        links?: PaginationLink[];
        onPageChange?: (url: string) => void;
        onPerPageChange?: (perPage: number) => void;
    };
    className?: string;
}

export default function DataTable<T extends Record<string, any>>({
    columns,
    data,
    keyField,
    isLoading = false,
    emptyTitle = 'No records found',
    emptyHint = 'There are no items matching this criteria.',
    emptyAction,
    rowActions,
    onRowClick,
    selectedRows,
    onSelectRows,
    sortColumn,
    sortDirection,
    onSort,
    pagination,
    className = '',
}: DataTableProps<T>) {
    const [openActionRow, setOpenActionRow] = useState<string | null>(null);
    const [hiddenColumns, setHiddenColumns] = useState<string[]>([]);
    const [columnMenuOpen, setColumnMenuOpen] = useState(false);

    function getRowKey(row: T): string {
        return typeof keyField === 'function' ? keyField(row) : String(row[keyField]);
    }

    const visibleColumns = columns.filter((col) => !hiddenColumns.includes(col.key));

    const isAllSelected =
        data.length > 0 && selectedRows && selectedRows.length === data.length;
    const isIndeterminate =
        selectedRows && selectedRows.length > 0 && selectedRows.length < data.length;

    function handleSelectAll() {
        if (!onSelectRows) return;
        if (isAllSelected) {
            onSelectRows([]);
        } else {
            onSelectRows(data.map(getRowKey));
        }
    }

    function handleSelectRow(key: string) {
        if (!onSelectRows || !selectedRows) return;
        if (selectedRows.includes(key)) {
            onSelectRows(selectedRows.filter((k) => k !== key));
        } else {
            onSelectRows([...selectedRows, key]);
        }
    }

    function toggleColumn(key: string) {
        if (hiddenColumns.includes(key)) {
            setHiddenColumns(hiddenColumns.filter((k) => k !== key));
        } else {
            if (visibleColumns.length > 1) {
                setHiddenColumns([...hiddenColumns, key]);
            }
        }
    }

    return (
        <div className={`overflow-hidden rounded-xl border border-slate-200 bg-white ${className}`}>
            {/* Toolbar for column visibility if desired */}
            <div className="flex items-center justify-end px-3 py-1.5 border-b border-slate-100 bg-slate-50/50 print:hidden">
                <div className="relative">
                    <button
                        type="button"
                        onClick={() => setColumnMenuOpen(!columnMenuOpen)}
                        className="inline-flex items-center gap-1.5 rounded px-2 py-1 text-xs text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                        title="Customize table columns"
                    >
                        <Icon name="Columns" className="h-3.5 w-3.5" />
                        <span>Columns</span>
                    </button>
                    {columnMenuOpen && (
                        <div className="absolute right-0 z-30 mt-1 w-48 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                            <p className="mb-1 text-xs font-semibold text-slate-700">Toggle columns</p>
                            <div className="space-y-1">
                                {columns.map((c) => (
                                    <label key={c.key} className="flex items-center gap-2 text-xs text-slate-700">
                                        <input
                                            type="checkbox"
                                            checked={!hiddenColumns.includes(c.key)}
                                            onChange={() => toggleColumn(c.key)}
                                            className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                        />
                                        <span>{c.header}</span>
                                    </label>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full text-left text-sm text-slate-700">
                    {/* Sticky Table Header */}
                    <thead className="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/90 text-xs font-semibold uppercase tracking-wider text-slate-500 backdrop-blur">
                        <tr>
                            {onSelectRows && (
                                <th scope="col" className="w-10 px-3 py-3 text-center">
                                    <input
                                        type="checkbox"
                                        checked={isAllSelected}
                                        ref={(input) => {
                                            if (input) input.indeterminate = !!isIndeterminate;
                                        }}
                                        onChange={handleSelectAll}
                                        className="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                        aria-label="Select all rows"
                                    />
                                </th>
                            )}
                            {visibleColumns.map((col) => {
                                const isSorted = sortColumn === col.key;
                                return (
                                    <th
                                        key={col.key}
                                        scope="col"
                                        style={{ width: col.width }}
                                        className={`px-4 py-3 ${
                                            col.align === 'right'
                                                ? 'text-right'
                                                : col.align === 'center'
                                                ? 'text-center'
                                                : 'text-left'
                                        } ${col.sortable ? 'cursor-pointer select-none hover:text-slate-900' : ''}`}
                                        onClick={() => col.sortable && onSort && onSort(col.key)}
                                    >
                                        <div
                                            className={`inline-flex items-center gap-1.5 ${
                                                col.align === 'right' ? 'justify-end' : ''
                                            }`}
                                        >
                                            <span>{col.header}</span>
                                            {col.sortable && (
                                                <span className="text-slate-400">
                                                    {isSorted ? (
                                                        sortDirection === 'asc' ? (
                                                            <Icon name="ArrowUp" className="h-3.5 w-3.5 text-emerald-700" />
                                                        ) : (
                                                            <Icon name="ArrowDown" className="h-3.5 w-3.5 text-emerald-700" />
                                                        )
                                                    ) : (
                                                        <Icon name="ArrowUpDown" className="h-3.5 w-3.5 opacity-40 hover:opacity-100" />
                                                    )}
                                                </span>
                                            )}
                                        </div>
                                    </th>
                                );
                            })}
                            {rowActions && rowActions.length > 0 && (
                                <th scope="col" className="w-14 px-3 py-3 text-right">
                                    <span className="sr-only">Actions</span>
                                </th>
                            )}
                        </tr>
                    </thead>

                    {/* Table Body */}
                    <tbody className="divide-y divide-slate-100">
                        {isLoading ? (
                            // Loading Skeleton
                            Array.from({ length: 5 }).map((_, i) => (
                                <tr key={i} className="h-12 animate-pulse">
                                    {onSelectRows && <td className="px-3 py-3"><div className="h-4 w-4 rounded bg-slate-200" /></td>}
                                    {visibleColumns.map((col) => (
                                        <td key={col.key} className="px-4 py-3">
                                            <div className="h-4 w-3/4 rounded bg-slate-200" />
                                        </td>
                                    ))}
                                    {rowActions && <td className="px-3 py-3"><div className="h-4 w-4 rounded bg-slate-200 ml-auto" /></td>}
                                </tr>
                            ))
                        ) : data.length === 0 ? (
                            // Empty State
                            <tr>
                                <td
                                    colSpan={visibleColumns.length + (onSelectRows ? 1 : 0) + (rowActions ? 1 : 0)}
                                    className="py-12 text-center"
                                >
                                    <div className="mx-auto flex max-w-sm flex-col items-center">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                            <Icon name="FileText" className="h-5 w-5" />
                                        </div>
                                        <h3 className="mt-3 text-sm font-semibold text-slate-800">{emptyTitle}</h3>
                                        <p className="mt-1 text-xs text-slate-500">{emptyHint}</p>
                                        {emptyAction && (
                                            <div className="mt-4">
                                                {emptyAction.href ? (
                                                    <a
                                                        href={emptyAction.href}
                                                        className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-800"
                                                    >
                                                        <span>{emptyAction.label}</span>
                                                    </a>
                                                ) : (
                                                    <button
                                                        type="button"
                                                        onClick={emptyAction.onClick}
                                                        className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-800"
                                                    >
                                                        <span>{emptyAction.label}</span>
                                                    </button>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ) : (
                            // Rows
                            data.map((row, idx) => {
                                const rowKey = getRowKey(row);
                                const isSelected = selectedRows?.includes(rowKey);
                                const isActionMenuOpen = openActionRow === rowKey;

                                return (
                                    <tr
                                        key={rowKey}
                                        onClick={() => onRowClick && onRowClick(row)}
                                        className={`h-12 transition-colors ${
                                            onRowClick ? 'cursor-pointer' : ''
                                        } ${
                                            isSelected
                                                ? 'bg-emerald-50/60'
                                                : 'hover:bg-slate-50/80'
                                        }`}
                                    >
                                        {onSelectRows && (
                                            <td
                                                className="w-10 px-3 py-3 text-center"
                                                onClick={(e) => e.stopPropagation()}
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={isSelected}
                                                    onChange={() => handleSelectRow(rowKey)}
                                                    className="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                                    aria-label={`Select row ${idx + 1}`}
                                                />
                                            </td>
                                        )}

                                        {visibleColumns.map((col) => (
                                            <td
                                                key={col.key}
                                                className={`px-4 py-3 text-sm ${
                                                    col.align === 'right'
                                                        ? 'text-right tabular-nums'
                                                        : col.align === 'center'
                                                        ? 'text-center'
                                                        : 'text-left'
                                                }`}
                                            >
                                                {col.render ? col.render(row, idx) : row[col.key] ?? '—'}
                                            </td>
                                        ))}

                                        {rowActions && rowActions.length > 0 && (
                                            <td
                                                className="relative w-14 px-3 py-3 text-right"
                                                onClick={(e) => e.stopPropagation()}
                                            >
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setOpenActionRow(isActionMenuOpen ? null : rowKey)
                                                    }
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700"
                                                    aria-label="Row actions"
                                                    title="More actions"
                                                >
                                                    <Icon name="MoreHorizontal" className="h-4 w-4" />
                                                </button>

                                                {isActionMenuOpen && (
                                                    <div className="absolute right-3 top-10 z-20 w-44 rounded-lg border border-slate-200 bg-white py-1 shadow-lg ring-1 ring-black/5">
                                                        {rowActions
                                                            .filter((act) => !act.hidden || !act.hidden(row))
                                                            .map((act, i) => (
                                                                <button
                                                                    key={i}
                                                                    type="button"
                                                                    onClick={() => {
                                                                        setOpenActionRow(null);
                                                                        act.onClick(row);
                                                                    }}
                                                                    className={`flex w-full items-center gap-2 px-3 py-2 text-left text-xs ${
                                                                        act.tone === 'danger'
                                                                            ? 'text-rose-600 hover:bg-rose-50'
                                                                            : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'
                                                                    }`}
                                                                >
                                                                    {act.icon && (
                                                                        <Icon
                                                                            name={act.icon}
                                                                            className={`h-3.5 w-3.5 ${
                                                                                act.tone === 'danger'
                                                                                    ? 'text-rose-500'
                                                                                    : 'text-slate-400'
                                                                            }`}
                                                                        />
                                                                    )}
                                                                    <span>{act.label}</span>
                                                                </button>
                                                            ))}
                                                    </div>
                                                )}
                                            </td>
                                        )}
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>

            {/* Pagination Controls */}
            {pagination && (pagination.total !== undefined || (pagination.links && pagination.links.length > 0)) && (
                <div className="flex flex-col items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 sm:flex-row text-xs text-slate-500 bg-slate-50/50">
                    <div>
                        {pagination.from !== undefined && pagination.to !== undefined && pagination.total !== undefined ? (
                            <span>
                                Showing <strong className="font-semibold text-slate-700 tabular-nums">{pagination.from}</strong> to{' '}
                                <strong className="font-semibold text-slate-700 tabular-nums">{pagination.to}</strong> of{' '}
                                <strong className="font-semibold text-slate-700 tabular-nums">{pagination.total}</strong> results
                            </span>
                        ) : pagination.total !== undefined ? (
                            <span>Total <strong className="font-semibold text-slate-700 tabular-nums">{pagination.total}</strong> records</span>
                        ) : null}
                    </div>

                    {/* Pagination Links */}
                    {pagination.links && pagination.links.length > 0 && (
                        <div className="flex items-center gap-1">
                            {pagination.links.map((link, i) => {
                                if (!link.url && !link.active) {
                                    return (
                                        <span
                                            key={i}
                                            className="px-2.5 py-1 text-slate-300"
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    );
                                }
                                return (
                                    <button
                                        key={i}
                                        type="button"
                                        onClick={() => link.url && pagination.onPageChange && pagination.onPageChange(link.url)}
                                        className={`rounded-md px-2.5 py-1 text-xs font-medium transition-colors ${
                                            link.active
                                                ? 'bg-emerald-700 text-white'
                                                : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-100'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                );
                            })}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

