import '../css/app.css';
import './bootstrap';

import { ConfirmDialog } from '@/Components/ProjectVista/ConfirmDialog';
import { Toast } from '@/Components/ProjectVista/Toast';
import { TooltipProvider } from '@/Components/ui/tooltip';
import { ConfirmProvider } from '@/Context/ConfirmContext';
import { ToastProvider } from '@/Context/ToastContext';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <TooltipProvider>
                <ToastProvider>
                    <ConfirmProvider>
                        <Toast />
                        <App {...props} />
                        <ConfirmDialog />
                    </ConfirmProvider>
                </ToastProvider>
            </TooltipProvider>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});
