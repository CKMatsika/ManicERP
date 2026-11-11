import { Form, Head, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { can, type SharedProps } from '../../lib';

const input = 'rounded border border-slate-300 px-2 py-1 text-sm';
const button = 'rounded bg-teal-700 px-3 py-1.5 text-sm text-white disabled:opacity-50';
function Errors({ errors }: { errors: Record<string, string> }) { return <div role="alert" className="text-sm text-rose-800">{Object.values(errors).join(' ')}</div>; }
function Submit({ label }: { label: string }) { return <button className={button} type="submit">{label}</button>; }

export default function Readiness({ sources, requirements, batches, reports, policies, statement, adoption }: any) {
    const { permissions, council } = usePage<SharedProps>().props;
    const allowed = (p: string) => can(permissions, p);
    const lines = reports.filter((r: any) => r.status === 'draft').flatMap((r: any) => r.lines.map((l: any) => ({ ...l, report: `${r.name} v${r.version}` })));
    return <AppLayout title="Financial readiness"><Head title="Financial readiness" />
        <div className="mb-4 rounded border border-amber-300 bg-amber-50 p-3 text-sm">Configuration and management reports only. The official posting schedule, current applicability, complete disclosures and council approvals remain required. No automatic IPSAS compliance statement is issued.</div>
        {allowed('accounting.requirements.view') && <section className="mb-6 rounded border bg-white p-4">
            <h2 className="mb-3 font-semibold">Source-backed requirements register</h2>
            {allowed('accounting.requirements.manage') && <div className="mb-3 flex flex-wrap gap-3"><Form action="/accounting/readiness/sources" method="post"><Submit label="Register supplied source documents" /></Form><Form action="/accounting/readiness/guidance" method="post"><Submit label="Approve excerpts for assistant retrieval as guidance" /></Form></div>}
            {sources.map((s: any) => <div key={s.id} className="mb-3 text-sm"><strong>{s.internal_title} · {s.edition}</strong><p>{s.issuing_authority} · {s.document_date} · {s.examination_status} · Current applicability: {s.currency_status}</p><p className="break-all font-mono text-xs">SHA-256 {s.file_hash}</p></div>)}
            <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead><tr><th>Requirement / source</th><th>Five separate evidence states</th><th>Unresolved requirement</th></tr></thead><tbody>{requirements.map((r: any) => <tr key={r.code} className="border-t align-top"><td className="p-2"><strong>{r.code}: {r.title}</strong><p>{r.source} §{r.section} · {r.page}</p></td><td className="p-2">{Object.entries(r.states).map(([k, v]) => <p key={k}>{k.replaceAll('_', ' ')}: {String(v)}</p>)}</td><td className="p-2">{r.blocker}</td></tr>)}</tbody></table></div>
        </section>}
        {(allowed('accounting.opening_balance.manage') || allowed('accounting.opening_balance.approve')) && <section className="mb-6 rounded border bg-white p-4">
            <h2 className="font-semibold">Opening balances and migration staging</h2><p className="mb-3 text-sm">Cutover does not change the IPSAS adoption date. Import customer control balances through customer staging; exclude those controls from GL staging. Posted imports require controlled corrections.</p>
            <div className="mb-4 rounded border border-slate-200 bg-slate-50 p-3 text-sm">
                <h3 className="mb-2 font-medium">IPSAS adoption and ERP migration dates</h3>
                <p className="mb-2">Record the council approved reporting adoption date separately from the technical migration cutover date. This setting does not determine applicability or certify compliance.</p>
                {adoption && <p className="mb-2">Recorded adoption: {adoption.value?.adoption_start_date ?? 'not set'} · Migration cutover: {adoption.value?.migration_cutover_date ?? 'not set'} · Approved {adoption.approved_at ? new Date(adoption.approved_at).toLocaleDateString() : ''}</p>}
                {allowed('accounting.opening_balance.approve') && <Form action="/accounting/adoption-dates" method="post" className="flex flex-wrap items-end gap-3">{({ errors, processing }) => <>
                    <label>Adoption start date<input name="adoption_start_date" type="date" required defaultValue={adoption?.value?.adoption_start_date ?? ''} className={input} /></label>
                    <label>Migration cutover date<input name="migration_cutover_date" type="date" required defaultValue={adoption?.value?.migration_cutover_date ?? ''} className={input} /></label>
                    <button disabled={processing} className={button}>Record approved dates</button><Errors errors={errors} />
                </>}</Form>}
            </div>
            {allowed('accounting.opening_balance.manage') && <><p className="mb-3 flex gap-4 text-sm text-teal-800"><a href="/accounting/opening-balances/template/opening_gl">Download GL CSV template</a><a href="/accounting/opening-balances/template/opening_customer">Download customer CSV template</a></p>
            <Form action="/accounting/opening-balances/import" method="post" className="mb-4 flex flex-wrap items-end gap-3">{({ errors, processing }) => <>
                <label>Import type<select name="type" className={input}><option value="opening_gl">Opening GL</option><option value="opening_customer">Customer balances</option></select></label>
                <label>Cutover date<input aria-label="Cutover date" type="date" name="cutover_date" required className={input} /></label>
                <label>Currency<input name="currency" defaultValue={council?.currency ?? 'USD'} maxLength={3} required className={input} /></label>
                <label>CSV file<input name="file" type="file" accept=".csv" required className={input} /></label><button disabled={processing} className={button}>Stage and validate</button><Errors errors={errors} />
            </>}</Form></>}
            {batches.map((b: any) => <details key={b.id} className="mb-2 rounded border p-3"><summary className="cursor-pointer text-sm font-medium">{b.batch_number} · {b.type} · {b.status} · {b.row_count} rows / {b.error_count} errors</summary>
                <p className="my-2 break-all text-xs">{b.file_name} · SHA-256 {b.file_hash} · {b.cutover_date?.slice(0, 10)} · {b.currency_code}</p>
                {allowed('accounting.opening_balance.approve') && <div className="my-2 flex gap-3">{b.status === 'validated' && <Form action={`/accounting/opening-balances/${b.id}/approve`} method="post"><Submit label="Approve independently" /></Form>}{b.status === 'approved' && <Form action={`/accounting/opening-balances/${b.id}/post`} method="post"><Submit label="Post approved balances once" /></Form>}</div>}
                <p className="text-sm">Control totals: {JSON.stringify(b.totals)} · Reconciliation: {JSON.stringify(b.reconciliation)}</p>
                <div className="overflow-x-auto"><table className="w-full text-left text-xs"><thead><tr><th>Row</th><th>Account / reference</th><th>Minor units</th><th>Status / errors</th></tr></thead><tbody>{b.rows.map((r: any) => <tr key={r.id} className="border-t"><td>{r.row_number}</td><td>{r.account_code ?? r.account_number} {r.reference}</td><td>{r.amount_minor}</td><td>{r.status} {(r.errors ?? []).join('; ')}</td></tr>)}</tbody></table></div>
            </details>)}
        </section>}
        {(allowed('accounting.report_mapping.manage') || allowed('accounting.report_mapping.approve') || allowed('accounting.statements.view')) && <section className="mb-6 rounded border bg-white p-4">
            <h2 className="mb-2 font-semibold">Versioned ZFRM report mappings</h2>
            <p className="mb-3 text-sm">Expenses appear by nature, with function/programme notes. Appendix 5 budget amounts and basis reconciliation are unavailable until budget accounting is implemented. Headings are not official Ministry posting codes.</p>
            {allowed('accounting.report_mapping.manage') && <><Form action="/accounting/report-mappings/templates" method="post" className="mb-3"><Submit label="Configure source-backed draft templates" /></Form>
                <Form action="/accounting/report-mappings" method="post" className="mb-3 flex flex-wrap gap-2">{({ errors }) => <>
                    <select name="report_line_id" required className={input}><option value="">Select draft heading</option>{lines.map((l: any) => <option key={l.id} value={l.id}>{l.report}: {l.label} ({l.sign})</option>)}</select>
                    <input name="gl_account_code" placeholder="Posting account code" aria-label="Posting account code" required className={input} />
                    <label>From<input name="effective_from" type="date" required className={input} /></label><label>To<input name="effective_to" type="date" className={input} /></label>
                    <input name="dimension_filters" placeholder='Dimension filters as JSON' aria-label="Dimension filters as JSON" className={input} /><Submit label="Save draft mapping" /><Errors errors={errors} />
                </>}</Form></>}
            {reports.map((r: any) => <details key={r.id} className="mb-2 rounded border p-3"><summary className="cursor-pointer text-sm">{r.name} · v{r.version} · {r.status} · {r.currency_code} / {r.units}</summary><p className="my-2 text-xs">{r.source_ref}</p>
                <ul className="mb-2 text-sm">{r.lines.map((l: any) => <li key={l.id}>{l.section} / {l.label} · sign {l.sign}: {l.mappings.map((m: any) => `${m.gl_account_code} [${m.status}] ${JSON.stringify(m.dimension_filters)}`).join(', ') || 'Unmapped'}</li>)}</ul>
                {allowed('accounting.report_mapping.manage') && <Form action={`/accounting/report-mappings/${r.id}/version`} method="post" className="mb-2"><Submit label="Create next draft version" /></Form>}
                {r.status === 'draft' && allowed('accounting.report_mapping.approve') && <Form action={`/accounting/report-mappings/${r.id}/approve`} method="post" className="flex flex-wrap gap-2">{({ errors }) => <><label>Effective from<input name="effective_from" type="date" required className={input} /></label><input name="currency_code" aria-label="Approved reporting currency" placeholder="Currency" defaultValue={r.currency_code} required className={input} /><select name="units" className={input}><option>units</option><option>thousands</option></select><Submit label="Approve independently" /><Errors errors={errors} /></>}</Form>}
            </details>)}
            {allowed('accounting.statements.view') && <Form action="/accounting/readiness" method="get" className="mt-4 flex flex-wrap gap-2">{({ errors }) => <><select name="report_id" required className={input}>{reports.map((r: any) => <option key={r.id} value={r.id}>{r.name} v{r.version}</option>)}</select><label>From<input name="from" type="date" required className={input} /></label><label>To<input name="to" type="date" required className={input} /></label><label>Comparative from<input name="compare_from" type="date" className={input} /></label><label>Comparative to<input name="compare_to" type="date" className={input} /></label><Submit label="Preview mapped statement" /><Errors errors={errors} /></>}</Form>}
            {statement && <div className="mt-4"><h3 className="font-semibold">{statement.definition.name} · {statement.from} to {statement.to}</h3><ul className="my-2 text-sm text-amber-900">{statement.blockers.map((b: string) => <li key={b}>{b}</li>)}</ul>
                <p className="text-sm">Trial-balance reconciliation: {statement.current.reconciles ? 'agrees for supported mapped data' : 'incomplete'} · Unmapped: {statement.current.unmapped_accounts.join(', ') || 'none'} · Ambiguous: {statement.current.ambiguous_accounts.join(', ') || 'none'}</p>
                <table className="mt-2 w-full text-left text-sm"><thead><tr><th>Heading</th><th>Opening</th><th>Movement</th><th>Closing / current</th><th>Comparative</th></tr></thead><tbody>{Object.entries(statement.current.lines).map(([code, row]: any) => {
                    const divisor = statement.definition.units === 'thousands' ? 100000 : 100;
                    const amount = (n: number) => (n / divisor).toFixed(statement.definition.units === 'thousands' ? 3 : 2);
                    return <tr key={code} className="border-t"><td>{row.label}</td><td>{amount(row.opening_minor)}</td><td>{amount(row.movement_minor)}</td><td>{amount(row.closing_minor)}</td><td>{statement.comparative ? amount(statement.comparative.lines[code]?.closing_minor ?? 0) : 'Not supplied'}</td></tr>;
                })}</tbody></table><p className="mt-2 text-xs">Functional expense notes (minor units): {JSON.stringify(statement.current.functional_expense_notes)} · Unclosed result (minor units): {statement.current.unclosed_result_minor ?? 'not applicable'}</p>
            </div>}
        </section>}
        {(allowed('accounting.revenue_policy.manage') || allowed('accounting.revenue_policy.approve')) && <section className="rounded border bg-white p-4"><h2 className="mb-2 font-semibold">Revenue recognition policies</h2><p className="mb-3 text-sm">Assess binding arrangements and compliance obligations per charge. Billing, receipt and recognition are separate. Deferred amounts remain liabilities until approved recognition journals are posted; deposits are not automatically released. Existing postings remain unchanged.</p>
            {allowed('accounting.revenue_policy.manage') && <Form action="/accounting/revenue-policies" method="post" className="mb-4 flex flex-wrap gap-2">{({ errors }) => <>
                <select name="charge_type" className={input}>{['rates', 'water', 'refuse', 'rental'].map(t => <option key={t}>{t}</option>)}</select><select name="recognition_method" className={input}>{['immediate', 'deferred', 'advance_receipt', 'refundable_deposit'].map(t => <option key={t}>{t}</option>)}</select>
                <input name="receivable_account_code" placeholder="Receivable account" aria-label="Receivable account" required className={input} /><input name="revenue_account_code" placeholder="Revenue account" aria-label="Revenue account" className={input} /><input name="deferred_account_code" placeholder="Liability account" aria-label="Liability account" className={input} />
                <select name="source_document_id" required className={input}><option value="">Source document</option>{sources.map((s: any) => <option key={s.id} value={s.id}>{s.internal_title}</option>)}</select><input name="source_ref" placeholder="Source section/page" aria-label="Source section/page" required className={input} />
                <label>From<input name="effective_from" type="date" required className={input} /></label><label>To<input name="effective_to" type="date" className={input} /></label><textarea name="notes" aria-label="Binding arrangement and compliance obligation assessment" placeholder="Binding arrangement, compliance obligations, recognition evidence and council policy rationale" required className={input} /><Submit label="Draft policy" /><Errors errors={errors} />
            </>}</Form>}
            {policies.map((p: any) => <div key={p.id} className="mb-2 border-t py-2 text-sm"><p>{p.charge_type} · v{p.version} · {p.recognition_method} · {p.status} · {p.effective_from?.slice(0, 10)} to {p.effective_to?.slice(0, 10) ?? 'open-ended'}</p><p>{p.source_ref} · {p.notes}</p>{p.status === 'draft' && allowed('accounting.revenue_policy.approve') && <Form action={`/accounting/revenue-policies/${p.id}/approve`} method="post"><Submit label="Approve independently" /></Form>}</div>)}
        </section>}
    </AppLayout>;
}
