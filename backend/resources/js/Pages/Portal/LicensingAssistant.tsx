import PortalLayout from '@/Layouts/PortalLayout';
import { Head, Link } from '@inertiajs/react';

type Source = { title: string; version: string; section_ref?: string | null; page_ref?: string | null; authority_label: string; applicability_status: string; source_hash?: string | null; excerpt: string };
type Result = { answer: string | null; generated: boolean; notice: string; disclaimer: string; sources: Source[] };

export default function LicensingAssistant({ question, result, disclaimer, enabled }: { question: string; result: Result | null; disclaimer: string; enabled: boolean }) {
  return <PortalLayout title="Licence application help"><Head title="Licence application help" /><div className="mx-auto max-w-3xl space-y-4">
    <div className="rounded border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950"><strong>Guidance only.</strong> The assistant cannot decide eligibility, set fees, approve an application or issue a certificate. {disclaimer}<div className="mt-1 text-xs">Provider generation: {enabled ? 'enabled; live generation is not independently verified' : 'disabled'}</div></div>
    <form method="get" action="/portal/licensing/assistant" className="flex gap-2"><input name="q" defaultValue={question} placeholder="Ask about an approved licence requirement" className="min-w-0 flex-1 rounded border px-3 py-2"/><button className="rounded bg-teal-700 px-4 py-2 text-white">Ask</button></form>
    {result && <section className="rounded border bg-white p-4"><h2 className="font-semibold">Suggested explanation</h2>{result.answer ? <p className="mt-2 whitespace-pre-wrap text-sm">{result.answer}</p> : <p className="mt-2 text-sm text-slate-600">{result.notice === 'no_sources' ? 'No approved licence configuration matched. Ask the council licensing team.' : 'No generated answer is available. The source excerpts are shown below.'}</p>}
      <h3 className="mt-4 font-medium">Citations</h3>{result.sources.length ? result.sources.map((source, index) => <article key={`${source.title}-${index}`} className="mt-3 border-t pt-3 text-sm"><p className="font-medium">[{index + 1}] {source.title} · version {source.version}</p><p className="text-xs text-slate-600">{source.authority_label} · {source.section_ref ?? 'section unspecified'} · {source.page_ref ?? 'page unspecified'} · {source.applicability_status}</p>{source.source_hash && <p className="break-all text-[10px] text-slate-500">SHA-256 {source.source_hash}</p>}<p className="mt-1">{source.excerpt}</p></article>) : <p className="text-sm text-slate-600">Only independently approved and effective council licence configurations are used as guidance sources.</p>}
    </section>}
    <Link href="/portal/licensing" className="inline-block text-sm text-teal-700 underline">Return to licences and renewals</Link>
  </div></PortalLayout>;
}
