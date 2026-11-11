import PortalLayout from '@/Layouts/PortalLayout';
import { Head, router } from '@inertiajs/react';
import { ChangeEvent, useState } from 'react';

type Field = { name: string; label: string; required?: boolean; max_length?: number };
type Type = { code: string; name: string; version: number; configuration: { label?: string; application_fields?: Field[]; required_documents?: { code: string; label: string }[]; charges?: { application_minor?: number; inspection_minor?: number; issuance_minor?: number; renewal_minor?: number; currency?: string }; renewal_window_days?: number } };
type Application = { id: string; reference: string; account_id: string; licence_id?: string; type: string; type_code: string; attempt_type: string; status: string; fee_minor: number; fee_currency: string; fee_bill_id?: string; paid: boolean; charges: { id: string; stage: string; amount_minor: number; currency: string; status: string; balance_minor: number }[]; reason?: string; conditions?: string[]; form_data?: Record<string, string>; certificates?: { id: string; reference: string; version: number; expires_on: string }[]; refunds?: { refund_number: string; status: string }[]; refund_eligible_payments?: { id: string; reference: string; amount_minor: number; charge_id: string }[] };
type Props = { types: Type[]; accounts: { id: string; number: string; name?: string }[]; applications: Application[] };

export default function Licensing({ types, accounts, applications }: Props) {
  const [typeCode, setTypeCode] = useState(types[0]?.code ?? '');
  const [accountId, setAccountId] = useState(accounts[0]?.id ?? '');
  const [applicationId, setApplicationId] = useState('');
  const [licenceId, setLicenceId] = useState('');
  const [form, setForm] = useState<Record<string, string>>({});
  const [files, setFiles] = useState<Record<string, File>>({});
  const [responseFiles, setResponseFiles] = useState<Record<string, File>>({});
  const [message, setMessage] = useState('');
  const [refundNote, setRefundNote] = useState<Record<string, string>>({});
  const selected = types.find(type => type.code === typeCode);

  const post = (url: string, data: any, done?: (page: any) => void) => router.post(url, data, { forceFormData: true, preserveScroll: true, onSuccess: page => { setMessage('Your changes were saved.'); done?.(page); } });
  const save = () => post('/portal/licensing/drafts', { account_id: accountId, licence_type: typeCode, application_id: applicationId || null, licence_id: licenceId || null, form }, page => {
    const latest = (page.props.applications as Application[]).find(item => item.status === 'draft' && item.type_code === typeCode && item.licence_id === (licenceId || undefined));
    if (latest) setApplicationId(latest.id);
  });
  const uploadFiles = async (id: string) => {
    for (const document of selected?.configuration.required_documents ?? []) {
      const file = files[document.code];
      if (!file) continue;
      await new Promise<void>((resolve, reject) => router.post(`/portal/licensing/applications/${id}/documents`, { account_id: accountId, document_type: document.code, file }, { forceFormData: true, preserveScroll: true, onSuccess: () => resolve(), onError: () => reject(new Error(`Could not upload ${document.label}.`)) }));
    }
  };
  const submit = async () => {
    if (!accountId || !typeCode) { setMessage('Link a council account and choose a licence type first.'); return; }
    try {
      let id = applicationId;
      if (!id) {
        await new Promise<void>((resolve, reject) => post('/portal/licensing/drafts', { account_id: accountId, licence_type: typeCode, form }, page => {
          const draft = (page.props.applications as Application[]).find(item => item.status === 'draft' && item.type_code === typeCode);
          if (draft) { id = draft.id; setApplicationId(draft.id); resolve(); } else reject(new Error('The draft was saved, refresh the page and continue.'));
        }));
      }
      await uploadFiles(id);
      post('/portal/licensing/applications', { account_id: accountId, licence_type: typeCode, application_id: id, idempotency_key: `licence:${id}:submit`, form });
    } catch (error) { setMessage(error instanceof Error ? error.message : 'Upload failed.'); }
  };
  const beginRenewal = (application: Application) => {
    if (!application.licence_id) return;
    setTypeCode(application.type_code); setAccountId(application.account_id); setLicenceId(application.licence_id); setApplicationId(''); setForm({}); setMessage(`Renewal draft started for ${application.reference}.`);
  };
  const respond = async (application: Application) => {
    const config = types.find(item => item.code === application.type_code)?.configuration;
    try {
      for (const document of config?.required_documents ?? []) {
        const file = responseFiles[`${application.id}:${document.code}`];
        if (file) await new Promise<void>((resolve, reject) => router.post(`/portal/licensing/applications/${application.id}/documents`, { account_id: application.account_id, document_type: document.code, file }, { forceFormData: true, preserveScroll: true, onSuccess: () => resolve(), onError: () => reject(new Error(`Could not upload ${document.label}.`)) }));
      }
      router.post(`/portal/licensing/applications/${application.id}/respond`, { account_id: application.account_id, form: application.form_data ?? {} }, { preserveScroll: true, onSuccess: () => setMessage('Response submitted for council review.') });
    } catch (error) { setMessage(error instanceof Error ? error.message : 'Could not submit the response.'); }
  };
  const onFile = (code: string, event: ChangeEvent<HTMLInputElement>) => { const file = event.target.files?.[0]; if (file) setFiles(current => ({ ...current, [code]: file })); };

  return <PortalLayout title="Licences and renewals"><Head title="Licences and renewals" /><div className="mx-auto max-w-5xl space-y-6 p-6">
    <header><p className="text-sm font-semibold uppercase tracking-wide text-teal-700">Council services</p><h1 className="text-2xl font-semibold">Licences and renewals</h1><p className="mt-1 text-sm text-slate-600">Only your verified linked accounts are shown. Offline drafts remain on your device until sent through the mobile app.</p></header>
    <section className="rounded border bg-white p-5"><h2 className="font-semibold">Start an application</h2>{!accounts.length ? <p className="mt-2 text-sm">Link and verify a council account before applying.</p> : <div className="mt-4 grid gap-4">
      <label className="grid gap-1 text-sm">Linked account<select className="rounded border p-2" value={accountId} onChange={e => setAccountId(e.target.value)}>{accounts.map(account => <option key={account.id} value={account.id}>{account.number}{account.name ? ` · ${account.name}` : ''}</option>)}</select></label>
      <label className="grid gap-1 text-sm">Licence type<select className="rounded border p-2" value={typeCode} onChange={e => { setTypeCode(e.target.value); setApplicationId(''); setLicenceId(''); setForm({}); }} >{types.map(type => <option key={type.code} value={type.code}>{type.name}</option>)}</select></label>
      {selected && <><div className="rounded bg-amber-50 p-3 text-sm text-amber-900">{selected.configuration.label ?? 'Configuration status must be checked with the council.'} · Version {selected.version} · Application fee {selected.configuration.charges?.currency ?? 'USD'} {((selected.configuration.charges?.application_minor ?? 0) / 100).toFixed(2)} <a className="ml-2 underline" href={`/portal/licensing/assistant?q=${encodeURIComponent(`${selected.name} application requirements`)}`}>Ask about these requirements</a></div>
        {(selected.configuration.application_fields ?? []).map(field => <label key={field.name} className="grid gap-1 text-sm">{field.label}{field.required ? ' *' : ''}<input className="rounded border p-2" maxLength={field.max_length} value={form[field.name] ?? ''} onChange={e => setForm({ ...form, [field.name]: e.target.value })} /></label>)}
        {(selected.configuration.required_documents ?? []).map(document => <label key={document.code} className="grid gap-1 text-sm">{document.label}<input type="file" onChange={event => onFile(document.code, event)} /><span className="text-xs text-slate-500">Stored privately. Replacements keep prior versions.</span></label>)}
      </>}
      <div className="flex flex-wrap gap-2"><button onClick={save} className="rounded border px-4 py-2 text-sm">Save draft</button><button onClick={submit} className="rounded bg-teal-700 px-4 py-2 text-sm text-white">Upload selected files and submit</button></div>
      {message && <p role="status" className="text-sm text-teal-800">{message}</p>}
    </div>}</section>
    <section className="space-y-3"><h2 className="font-semibold">Your applications and licences</h2>{applications.map(application => <article key={application.id} className="rounded border bg-white p-4"><div className="flex flex-wrap justify-between gap-2"><div><h3 className="font-semibold">{application.reference} · {application.type}</h3><p className="text-sm text-slate-600">{application.attempt_type} · {application.status}</p></div><div className="text-sm">{application.fee_currency} {(application.fee_minor / 100).toFixed(2)} · {application.paid ? 'paid' : 'payment required'}</div></div>
      {application.reason && <p className="mt-2 rounded bg-slate-50 p-2 text-sm">Council message: {application.reason}</p>}{application.conditions?.length ? <ul className="list-disc pl-5 text-sm">{application.conditions.map(condition => <li key={condition}>{condition}</li>)}</ul> : null}
      {application.refunds?.map(refund => <p key={refund.refund_number} className="mt-2 text-sm">Refund request {refund.refund_number}: {refund.status}</p>)}
      {application.refund_eligible_payments?.map(payment => <div key={`${payment.id}:${payment.charge_id}`} className="mt-3 flex flex-wrap items-center gap-2 rounded bg-amber-50 p-3"><span className="text-sm">Refund from {payment.reference} · up to {application.fee_currency} {(payment.amount_minor / 100).toFixed(2)}</span><input value={refundNote[application.id] ?? ''} onChange={event => setRefundNote({ ...refundNote, [application.id]: event.target.value })} placeholder="Refund request reason" className="rounded border p-2 text-sm"/><button disabled={!(refundNote[application.id] ?? '').trim()} onClick={() => post(`/portal/licensing/applications/${application.id}/refunds`, { payment_id: payment.id, charge_id: payment.charge_id, amount_minor: payment.amount_minor, customer_note: refundNote[application.id], idempotency_key: `licence-refund:${application.id}:${payment.id}` })} className="rounded border px-3 py-2 text-sm disabled:opacity-50">Request refund</button></div>)}
      {application.status === 'information_requested' && <div className="mt-3 space-y-2 rounded bg-amber-50 p-3"><p className="text-sm font-medium">The council requested more information. Upload replacement evidence, then send a response.</p>{(types.find(item => item.code === application.type_code)?.configuration.required_documents ?? []).map(document => <label key={document.code} className="block text-sm">{document.label}<input type="file" className="ml-2" onChange={event => { const file = event.target.files?.[0]; if (file) setResponseFiles({ ...responseFiles, [`${application.id}:${document.code}`]: file }); }} /></label>)}<button onClick={() => respond(application)} className="rounded border px-3 py-2 text-sm">Send response</button></div>}
      {application.charges.filter(charge => charge.balance_minor > 0 && !['waived','cancelled'].includes(charge.status)).map(charge => <button key={charge.id} onClick={() => post(`/portal/licensing/applications/${application.id}/payment`, { charge_id: charge.id, idempotency_key: `licence:${application.id}:${charge.id}:payment` })} className="mt-3 rounded bg-teal-700 px-3 py-2 text-sm text-white">Pay {charge.stage} charge · {charge.currency} {(charge.balance_minor / 100).toFixed(2)}</button>)}
      {application.certificates?.map(certificate => <div key={certificate.id} className="mt-3 flex items-center gap-3 text-sm"><span>Certificate {certificate.reference} v{certificate.version} · expires {certificate.expires_on}</span><a className="underline" href={`/portal/licensing/certificates/${certificate.id}`}>Download certificate</a></div>)}
      {application.licence_id && application.status === 'issued' && <button onClick={() => beginRenewal(application)} className="mt-3 rounded border px-3 py-2 text-sm">Start renewal attempt</button>}
    </article>)}</section>
  </div></PortalLayout>;
}
