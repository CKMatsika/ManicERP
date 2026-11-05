import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { Badge, EmptyState } from '../../Components/Ui';
import type { SharedProps } from '../../lib';

type Source = { id: string; title: string; version: string; effective_from: string | null; source_url: string | null; excerpt: string; score: number };

export default function Assistant({
    question,
    result,
    enabled,
    budgetRemainingMinor,
    disclaimer,
}: {
    question: string;
    result: { answer: string | null; generated: boolean; sources: Source[]; disclaimer: string; notice: string } | null;
    enabled: boolean;
    budgetRemainingMinor: number | null;
    disclaimer: string;
}) {
    const { permissions } = usePage<SharedProps>().props;

    if (!permissions.includes('ai.assistant.use')) {
        return (
            <AppLayout title="Policy assistant">
                <EmptyState title="AI assistance is not enabled for your role" hint="Ask a council administrator to grant ai.assistant.use." />
            </AppLayout>
        );
    }

    return (
        <AppLayout title="Policy assistant">
            <Head title="Policy assistant" />

            <div className="mb-4 rounded border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900">
                <p className="font-medium">Assistance only, never an official decision.</p>
                <p>{disclaimer}</p>
                <p className="mt-1 text-xs">
                    Provider generation: {enabled ? 'enabled' : 'disabled'} · sources are restricted to approved policy documents for this council.
                    {budgetRemainingMinor !== null ? ` Budget remaining: ${(budgetRemainingMinor / 100).toFixed(2)}.` : ''}
                </p>
            </div>

            <form method="get" action="/ai/assistant" className="mb-4 flex flex-wrap gap-2">
                <label htmlFor="q" className="sr-only">Policy question</label>
                <input id="q" name="q" defaultValue={question} placeholder="e.g. What notice is required before a water disconnection?" className="min-w-0 flex-1 rounded border border-slate-300 px-3 py-2 text-sm" />
                <button type="submit" className="rounded bg-teal-700 px-3 py-2 text-sm font-medium text-white hover:bg-teal-800">Search approved policy</button>
            </form>

            {!result ? (
                <EmptyState title="Ask a policy question" hint="Answers are limited to approved, versioned documents and cite their sources." />
            ) : (
                <div className="space-y-4">
                    {result.answer ? (
                        <section className="rounded-lg border border-slate-200 bg-white p-4">
                            <h2 className="text-sm font-semibold">Suggested answer</h2>
                            <p className="mt-2 whitespace-pre-line text-sm text-slate-800">{result.answer}</p>
                        </section>
                    ) : (
                        <div className="rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            {result.notice === 'no_sources' && 'No approved policy source matched this question.'}
                            {result.notice === 'ai_disabled' && 'AI generation is disabled. The approved source excerpts below are shown without a generated answer.'}
                            {result.notice === 'budget_exhausted' && 'The council AI budget is exhausted for this month. Source excerpts are still shown.'}
                            {result.notice === 'provider_failure' && 'The AI provider was unavailable. Source excerpts are shown; core operations are unaffected.'}
                        </div>
                    )}

                    <section className="rounded-lg border border-slate-200 bg-white">
                        <div className="border-b border-slate-200 px-4 py-3">
                            <h2 className="text-sm font-semibold">Sources consulted</h2>
                            <p className="text-xs text-slate-500">Only approved documents are eligible. Retrieved text is treated as data, never as instructions.</p>
                        </div>
                        {result.sources.length === 0 ? (
                            <div className="p-4"><EmptyState title="No approved sources matched" /></div>
                        ) : (
                            <ol className="divide-y divide-slate-100">
                                {result.sources.map((source, index) => (
                                    <li key={source.id} className="px-4 py-3 text-sm">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="font-medium">[{index + 1}] {source.title}</p>
                                            <Badge tone="sky">v{source.version}</Badge>
                                        </div>
                                        <p className="text-xs text-slate-500">Effective {source.effective_from ?? 'not stated'}</p>
                                        <p className="mt-1 text-slate-700">{source.excerpt}</p>
                                        {source.source_url && (
                                            <Link href={source.source_url} className="mt-1 inline-block text-xs text-teal-700 hover:underline">Source reference</Link>
                                        )}
                                    </li>
                                ))}
                            </ol>
                        )}
                    </section>
                </div>
            )}
        </AppLayout>
    );
}
