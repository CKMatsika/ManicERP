import React, { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Search, X, ArrowRight } from 'lucide-react';
import { SIDEBAR_GROUPS, type SidebarItem } from '../Config/sidebarConfig';
import Icon from './Icon';

export default function CommandPalette({
    isOpen,
    onClose,
}: {
    isOpen: boolean;
    onClose: () => void;
}) {
    const [query, setQuery] = useState('');
    const [selectedIndex, setSelectedIndex] = useState(0);
    const inputRef = useRef<HTMLInputElement>(null);

    // Flatten all items with group title
    const allItems = React.useMemo(() => {
        const flat: (SidebarItem & { groupTitle: string })[] = [];
        for (const group of SIDEBAR_GROUPS) {
            for (const item of group.items) {
                flat.push({ ...item, groupTitle: group.title });
            }
        }
        return flat;
    }, []);

    const filtered = React.useMemo(() => {
        if (!query.trim()) return allItems.slice(0, 8);
        const q = query.toLowerCase().trim();
        return allItems.filter(
            (item) =>
                item.label.toLowerCase().includes(q) ||
                item.groupTitle.toLowerCase().includes(q) ||
                item.href.toLowerCase().includes(q) ||
                (item.keywords && item.keywords.some((k) => k.toLowerCase().includes(q)))
        );
    }, [allItems, query]);

    useEffect(() => {
        setSelectedIndex(0);
    }, [query]);

    useEffect(() => {
        if (isOpen) {
            setTimeout(() => inputRef.current?.focus(), 50);
        } else {
            setQuery('');
        }
    }, [isOpen]);

    useEffect(() => {
        function handleKeyDown(e: KeyboardEvent) {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                if (isOpen) onClose();
                else onClose(); // parent handles toggle
            }
            if (!isOpen) return;

            if (e.key === 'Escape') {
                e.preventDefault();
                onClose();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                setSelectedIndex((prev) => (prev + 1) % Math.max(1, filtered.length));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setSelectedIndex((prev) => (prev - 1 + filtered.length) % Math.max(1, filtered.length));
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (filtered[selectedIndex]) {
                    onClose();
                    router.visit(filtered[selectedIndex].href);
                }
            }
        }

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, filtered, selectedIndex, onClose]);

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center p-4 sm:pt-20">
            {/* Backdrop */}
            <div
                className="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity"
                onClick={onClose}
                aria-hidden="true"
            />

            {/* Modal Box */}
            <div
                className="relative w-full max-w-xl overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl transition-all"
                role="dialog"
                aria-modal="true"
                aria-label="Command search"
            >
                {/* Search Input Bar */}
                <div className="flex items-center border-b border-slate-200 px-3.5 py-2.5">
                    <Search className="h-4 w-4 text-slate-400 mr-2.5 shrink-0" aria-hidden="true" />
                    <input
                        ref={inputRef}
                        type="text"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search municipal modules, ledger accounts, reports... (Esc to close)"
                        className="w-full bg-transparent text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none"
                    />
                    {query && (
                        <button
                            type="button"
                            onClick={() => setQuery('')}
                            className="p-1 text-slate-400 hover:text-slate-600 rounded"
                            aria-label="Clear search"
                        >
                            <X className="h-3.5 w-3.5" />
                        </button>
                    )}
                </div>

                {/* Results List */}
                <div className="max-h-80 overflow-y-auto p-1.5">
                    {filtered.length === 0 ? (
                        <div className="py-8 text-center text-xs text-slate-500">
                            No matching council modules or screens found for "{query}".
                        </div>
                    ) : (
                        <ul className="space-y-0.5" role="listbox">
                            {filtered.map((item, idx) => {
                                const isSelected = idx === selectedIndex;
                                return (
                                    <li
                                        key={item.href}
                                        role="option"
                                        aria-selected={isSelected}
                                        onMouseEnter={() => setSelectedIndex(idx)}
                                        onClick={() => {
                                            onClose();
                                            router.visit(item.href);
                                        }}
                                        className={`flex items-center justify-between rounded-lg px-3 py-2 text-xs cursor-pointer transition ${
                                            isSelected ? 'bg-slate-100 text-slate-900' : 'text-slate-700 hover:bg-slate-50'
                                        }`}
                                    >
                                        <div className="flex items-center gap-2.5 min-w-0">
                                            <div
                                                className={`flex h-7 w-7 items-center justify-center rounded-md ${
                                                    isSelected ? 'bg-white text-[#0F5C3A] shadow-xs' : 'bg-slate-100 text-slate-600'
                                                }`}
                                            >
                                                <Icon name={item.icon} className="h-3.5 w-3.5" />
                                            </div>
                                            <div className="truncate">
                                                <span className="font-medium text-slate-900 text-sm block leading-tight">
                                                    {item.label}
                                                </span>
                                                <span className="text-[11px] text-slate-500 block">
                                                    {item.groupTitle} · {item.href}
                                                </span>
                                            </div>
                                        </div>
                                        <ArrowRight className="h-3.5 w-3.5 text-slate-400 shrink-0 ml-2" />
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>

                {/* Keyboard hints footer */}
                <div className="flex items-center justify-between border-t border-slate-100 bg-slate-50/70 px-3.5 py-2 text-[11px] text-slate-500">
                    <div className="flex items-center gap-3">
                        <span>
                            <kbd className="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[10px] text-slate-600 shadow-2xs">
                                ↑
                            </kbd>{' '}
                            <kbd className="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[10px] text-slate-600 shadow-2xs">
                                ↓
                            </kbd>{' '}
                            navigate
                        </span>
                        <span>
                            <kbd className="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[10px] text-slate-600 shadow-2xs">
                                ↵
                            </kbd>{' '}
                            select
                        </span>
                    </div>
                    <span>
                        <kbd className="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[10px] text-slate-600 shadow-2xs">
                            Esc
                        </kbd>{' '}
                        close
                    </span>
                </div>
            </div>
        </div>
    );
}

