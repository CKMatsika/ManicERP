import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import type { ComponentType } from 'react';

const pages = import.meta.glob('./Pages/**/*.tsx') as Record<string, () => Promise<{ default: ComponentType<any> }>>;
const PageLoadError: ComponentType = () => <main role="alert" className="m-6 rounded border bg-white p-6">This page could not be loaded. Reload the page and try again.</main>;

createInertiaApp({
    title: (title) => (title ? `${title} - ManicERP` : 'ManicERP'),
    resolve: (name) => {
        const load = pages[`./Pages/${name}.tsx`];

        if (!load) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        return load().then((module) => module.default).catch((error: unknown) => {
            console.error('Unable to load application page.', error);
            return PageLoadError;
        });
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#0f766e',
    },
});
