import AppLayout from '@/Layouts/AppLayout';
import PortalLayout from '@/Layouts/PortalLayout';
import { Head, router } from '@inertiajs/react';

type Notification = { id: string; event: string; subject: string; preview: string; channel: string; status: string; created_at: string; read_at?: string | null };
type Props = { notifications: Notification[]; portal: boolean };

export default function Notifications({ notifications, portal }: Props) {
  const content = <><Head title="Licensing notifications" /><div className="space-y-3">
    <p className="text-sm text-slate-600">In-app notices are delivered here. Email, SMS and push remain unavailable until council adapters are configured.</p>
    {notifications.length ? notifications.map(item => <article key={item.id} className={`rounded border bg-white p-4 ${item.read_at ? 'opacity-70' : ''}`}>
      <div className="flex flex-wrap justify-between gap-2"><h2 className="font-semibold">{item.subject}</h2><span className="text-xs text-slate-500">{item.channel} · {item.status}</span></div>
      <p className="mt-1 text-sm text-slate-700">{item.preview}</p><p className="mt-2 text-xs text-slate-500">{new Date(item.created_at).toLocaleString()}</p>
      {!item.read_at && <button className="mt-2 rounded border px-3 py-1.5 text-sm" onClick={() => router.post(`${portal ? '/portal' : ''}/licensing/notifications/${item.id}/read`, {}, { preserveScroll: true })}>Mark as read</button>}
    </article>) : <p className="rounded border bg-white p-4 text-sm text-slate-500">No notifications yet.</p>}
  </div></>;
  return portal ? <PortalLayout title="Notifications">{content}</PortalLayout> : <AppLayout title="Licensing inbox">{content}</AppLayout>;
}
