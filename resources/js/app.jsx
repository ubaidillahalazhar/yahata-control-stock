import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Yahata Control Stock';
const pages = import.meta.glob('./Pages/**/*.jsx');

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) => {
        const importPage = pages[`./Pages/${name}.jsx`];

        if (!importPage) {
            throw new Error(`Halaman Inertia tidak ditemukan: ${name}`);
        }

        return importPage();
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#2563eb',
        showSpinner: false,
    },
});
