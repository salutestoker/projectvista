import { Button } from '@/Components/ui/button';
import { useToastState } from '@/Context/ToastContext';
import { CheckCircle2, Info, TriangleAlert, X, XCircle } from 'lucide-react';
import { useEffect } from 'react';

const iconByType = {
    success: CheckCircle2,
    error: XCircle,
    info: Info,
    warning: TriangleAlert,
};

const labelByType = {
    success: 'Success',
    error: 'Error',
    info: 'Notice',
    warning: 'Warning',
};

export function Toast() {
    const { dismissToast, toastState } = useToastState();

    useEffect(() => {
        if (!toastState) {
            return;
        }

        const timer = window.setTimeout(dismissToast, 5000);

        return () => window.clearTimeout(timer);
    }, [dismissToast, toastState]);

    if (!toastState) {
        return null;
    }

    const Icon = iconByType[toastState.type];

    return (
        <div
            aria-live="polite"
            aria-atomic="true"
            className="animate-in fade-in-0 slide-in-from-bottom-3 fixed right-5 bottom-5 z-50 w-[calc(100vw-2.5rem)] max-w-md duration-300"
        >
            <div className="border-primary/30 bg-background/95 text-foreground shadow-2xl shadow-black/20 ring-primary/10 rounded-lg border px-4 py-3 ring-1 backdrop-blur">
                <div className="flex items-start gap-3">
                    <div className="bg-primary/10 text-primary mt-0.5 rounded-full p-1">
                        <Icon />
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="text-primary text-xs font-semibold tracking-[0.18em] uppercase">
                            {labelByType[toastState.type]}
                        </div>
                        <p className="mt-1 text-sm">{toastState.message}</p>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        className="-mt-1 -mr-1"
                        onClick={dismissToast}
                    >
                        <X />
                        <span className="sr-only">Dismiss notification</span>
                    </Button>
                </div>
            </div>
        </div>
    );
}
