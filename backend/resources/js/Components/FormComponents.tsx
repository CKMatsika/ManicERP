import React, { useState, useEffect, useRef } from 'react';
import Icon from './Icon';

/**
 * FormSection: Grouped fields into titled sections with short descriptions
 */
export function FormSection({
    title,
    description,
    children,
    className = '',
}: {
    title: string;
    description?: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div className={`rounded-xl border border-slate-200 bg-white p-5 ${className}`}>
            <div className="border-b border-slate-100 pb-3">
                <h3 className="text-base font-semibold text-slate-900">{title}</h3>
                {description && <p className="mt-0.5 text-xs text-slate-500">{description}</p>}
            </div>
            <div className="mt-4 space-y-4">{children}</div>
        </div>
    );
}

/**
 * Field: Label (14px medium), helper text (12-13px), required mark, error with icon
 */
export function Field({
    label,
    id,
    required = false,
    helper,
    error,
    children,
    className = '',
}: {
    label: string;
    id?: string;
    required?: boolean;
    helper?: string;
    error?: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div className={className}>
            <div className="flex items-center justify-between">
                <label htmlFor={id} className="block text-sm font-medium text-slate-700">
                    {label} {required && <span className="text-rose-600">*</span>}
                </label>
            </div>
            <div className="mt-1">{children}</div>
            {helper && !error && <p className="mt-1 text-xs text-slate-500">{helper}</p>}
            {error && (
                <div className="mt-1 flex items-center gap-1.5 text-xs text-rose-600">
                    <Icon name="AlertCircle" className="h-3.5 w-3.5 flex-shrink-0 text-rose-600" />
                    <span>{error}</span>
                </div>
            )}
        </div>
    );
}

/**
 * MoneyInput: Prefix currency, tabular-nums, right or left aligned
 */
export function MoneyInput({
    id,
    value,
    onChange,
    placeholder = '0.00',
    currency = 'USD',
    required = false,
    disabled = false,
    className = '',
}: {
    id?: string;
    value: string | number;
    onChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
    placeholder?: string;
    currency?: string;
    required?: boolean;
    disabled?: boolean;
    className?: string;
}) {
    const symbol = currency === 'USD' ? 'US$' : currency;

    return (
        <div className={`relative rounded-lg shadow-sm ${className}`}>
            <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <span className="text-sm font-medium text-slate-500">{symbol}</span>
            </div>
            <input
                type="text"
                id={id}
                inputMode="decimal"
                value={value}
                onChange={onChange}
                placeholder={placeholder}
                required={required}
                disabled={disabled}
                className="h-10 w-full rounded-lg border border-slate-300 bg-white pl-12 pr-3 text-right text-sm font-medium text-slate-900 tabular-nums transition-colors placeholder:text-slate-400 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600 disabled:bg-slate-50 disabled:text-slate-500"
            />
        </div>
    );
}

/**
 * DateInput: Styled HTML date picker with icon
 */
export function DateInput({
    id,
    value,
    onChange,
    required = false,
    disabled = false,
    className = '',
}: {
    id?: string;
    value: string;
    onChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
    required?: boolean;
    disabled?: boolean;
    className?: string;
}) {
    return (
        <div className={`relative ${className}`}>
            <input
                type="date"
                id={id}
                value={value}
                onChange={onChange}
                required={required}
                disabled={disabled}
                className="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600 disabled:bg-slate-50"
            />
        </div>
    );
}

/**
 * Select: Dropdown select with consistent styling
 */
export function Select({
    id,
    value,
    onChange,
    options,
    required = false,
    disabled = false,
    placeholder,
    className = '',
}: {
    id?: string;
    value: string;
    onChange: (e: React.ChangeEvent<HTMLSelectElement>) => void;
    options: Array<{ value: string; label: string }>;
    required?: boolean;
    disabled?: boolean;
    placeholder?: string;
    className?: string;
}) {
    return (
        <div className={`relative ${className}`}>
            <select
                id={id}
                value={value}
                onChange={onChange}
                required={required}
                disabled={disabled}
                className="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 pr-8 text-sm text-slate-900 transition-colors focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600 disabled:bg-slate-50"
            >
                {placeholder && <option value="">{placeholder}</option>}
                {options.map((opt) => (
                    <option key={opt.value} value={opt.value}>
                        {opt.label}
                    </option>
                ))}
            </select>
        </div>
    );
}

/**
 * Combobox: Searchable dropdown for accounts, customers, etc.
 */
export function Combobox<T extends { id?: string; code?: string; name: string; subtitle?: string }>({
    items,
    value,
    onChange,
    placeholder = 'Search item...',
    id,
    required = false,
    className = '',
}: {
    items: T[];
    value: string;
    onChange: (selectedValue: string, selectedItem?: T) => void;
    placeholder?: string;
    id?: string;
    required?: boolean;
    className?: string;
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [query, setQuery] = useState('');
    const wrapperRef = useRef<HTMLDivElement>(null);

    const selectedItem = items.find(
        (it) => (it.code || it.id || it.name) === value
    );

    const filteredItems = query === ''
        ? items
        : items.filter((it) => {
              const full = `${it.code || ''} ${it.name} ${it.subtitle || ''}`.toLowerCase();
              return full.includes(query.toLowerCase());
          });

    useEffect(() => {
        function handleClickOutside(e: MouseEvent) {
            if (wrapperRef.current && !wrapperRef.current.contains(e.target as Node)) {
                setIsOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    return (
        <div ref={wrapperRef} className={`relative ${className}`}>
            <div
                onClick={() => setIsOpen(!isOpen)}
                className="flex h-10 w-full cursor-pointer items-center justify-between rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 transition-colors hover:border-slate-400 focus-within:border-emerald-600 focus-within:ring-1 focus-within:ring-emerald-600"
            >
                <span className={selectedItem ? 'text-slate-900 font-medium' : 'text-slate-400'}>
                    {selectedItem
                        ? `${selectedItem.code ? selectedItem.code + ' — ' : ''}${selectedItem.name}`
                        : placeholder}
                </span>
                <Icon name="ChevronDown" className="h-4 w-4 text-slate-400" />
            </div>

            {isOpen && (
                <div className="absolute left-0 right-0 z-40 mt-1 max-h-60 overflow-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg ring-1 ring-black/5">
                    <div className="border-b border-slate-100 p-2">
                        <input
                            type="search"
                            autoFocus
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Type to filter..."
                            className="h-8 w-full rounded border border-slate-200 px-2 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                            onClick={(e) => e.stopPropagation()}
                        />
                    </div>
                    {filteredItems.length === 0 ? (
                        <div className="px-3 py-2 text-xs text-slate-500">No results found</div>
                    ) : (
                        filteredItems.map((item, idx) => {
                            const itemVal = item.code || item.id || item.name;
                            const isSelected = itemVal === value;
                            return (
                                <button
                                    key={idx}
                                    type="button"
                                    onClick={() => {
                                        onChange(itemVal, item);
                                        setIsOpen(false);
                                    }}
                                    className={`flex w-full items-center justify-between px-3 py-2 text-left text-xs ${
                                        isSelected
                                            ? 'bg-emerald-50 text-emerald-900 font-semibold'
                                            : 'hover:bg-slate-50 text-slate-700'
                                    }`}
                                >
                                    <div>
                                        <p className="font-medium text-slate-900">
                                            {item.code && <span className="font-mono text-slate-500 mr-1.5">{item.code}</span>}
                                            {item.name}
                                        </p>
                                        {item.subtitle && <p className="text-[11px] text-slate-400">{item.subtitle}</p>}
                                    </div>
                                    {isSelected && <Icon name="Check" className="h-3.5 w-3.5 text-emerald-700" />}
                                </button>
                            );
                        })
                    )}
                </div>
            )}
        </div>
    );
}

/**
 * Modal dialog with focus trap & escape key handler
 */
export function Modal({
    isOpen,
    onClose,
    title,
    description,
    children,
    maxWidth = 'max-w-lg',
}: {
    isOpen: boolean;
    onClose: () => void;
    title: string;
    description?: string;
    children: React.ReactNode;
    maxWidth?: string;
}) {
    useEffect(() => {
        function handleKeyDown(e: KeyboardEvent) {
            if (e.key === 'Escape' && isOpen) {
                onClose();
            }
        }
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, onClose]);

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true">
            <div className="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity" onClick={onClose} />
            <div className={`relative w-full ${maxWidth} overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-slate-900/10 transition-all`}>
                <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <h3 className="text-base font-semibold text-slate-900">{title}</h3>
                        {description && <p className="mt-0.5 text-xs text-slate-500">{description}</p>}
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700"
                        aria-label="Close dialog"
                    >
                        <Icon name="X" className="h-4 w-4" />
                    </button>
                </div>
                <div className="p-5">{children}</div>
            </div>
        </div>
    );
}

/**
 * ConfirmDialog: Names item, explains consequence, danger action on right
 */
export function ConfirmDialog({
    isOpen,
    onClose,
    onConfirm,
    title,
    itemName,
    consequence,
    confirmLabel = 'Confirm',
    tone = 'danger',
    isProcessing = false,
}: {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title: string;
    itemName?: string;
    consequence: string;
    confirmLabel?: string;
    tone?: 'danger' | 'warning' | 'primary';
    isProcessing?: boolean;
}) {
    if (!isOpen) return null;

    return (
        <Modal isOpen={isOpen} onClose={onClose} title={title} maxWidth="max-w-md">
            <div className="space-y-3">
                {itemName && (
                    <p className="text-sm font-semibold text-slate-800">
                        Target: <span className="text-slate-950">{itemName}</span>
                    </p>
                )}
                <p className="text-sm text-slate-600">{consequence}</p>
                <div className="mt-6 flex justify-end gap-2.5 pt-3">
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={isProcessing}
                        className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={isProcessing}
                        className={`rounded-lg px-4 py-2 text-sm font-medium text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-50 ${
                            tone === 'danger'
                                ? 'bg-rose-600 hover:bg-rose-700 focus-visible:ring-rose-600'
                                : 'bg-emerald-700 hover:bg-emerald-800 focus-visible:ring-emerald-700'
                        }`}
                    >
                        {isProcessing ? 'Processing...' : confirmLabel}
                    </button>
                </div>
            </div>
        </Modal>
    );
}

/**
 * Toast: Auto-dismiss for success, persist for errors
 */
export function Toast({
    message,
    type = 'success',
    onDismiss,
}: {
    message: string;
    type?: 'success' | 'error' | 'warning' | 'info';
    onDismiss: () => void;
}) {
    useEffect(() => {
        if (type === 'success' || type === 'info') {
            const timer = setTimeout(() => {
                onDismiss();
            }, 4000);
            return () => clearTimeout(timer);
        }
    }, [type, onDismiss]);

    const styles = {
        success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
        error: 'border-rose-200 bg-rose-50 text-rose-900',
        warning: 'border-amber-200 bg-amber-50 text-amber-900',
        info: 'border-sky-200 bg-sky-50 text-sky-900',
    };

    return (
        <div
            className={`fixed bottom-5 right-5 z-50 flex items-center gap-3 rounded-xl border p-4 shadow-lg ${styles[type]}`}
            role="status"
        >
            <p className="text-sm font-medium">{message}</p>
            <button
                type="button"
                onClick={onDismiss}
                className="ml-auto text-slate-400 hover:text-slate-700"
                aria-label="Dismiss toast"
            >
                <Icon name="X" className="h-4 w-4" />
            </button>
        </div>
    );
}

/**
 * EmptyState: Consistently styled empty states with optional clear or primary action
 */
export function EmptyState({
    title,
    hint,
    action,
}: {
    title: string;
    hint?: string;
    action?: { label: string; onClick?: () => void; href?: string };
}) {
    return (
        <div className="rounded-xl border border-dashed border-slate-300 p-8 text-center bg-white/50">
            <div className="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                <Icon name="FileText" className="h-5 w-5" />
            </div>
            <h3 className="mt-3 text-sm font-semibold text-slate-800">{title}</h3>
            {hint && <p className="mt-1 text-xs text-slate-500 max-w-sm mx-auto">{hint}</p>}
            {action && (
                <div className="mt-4">
                    {action.href ? (
                        <a
                            href={action.href}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-800"
                        >
                            <span>{action.label}</span>
                        </a>
                    ) : (
                        <button
                            type="button"
                            onClick={action.onClick}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-800"
                        >
                            <span>{action.label}</span>
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}

