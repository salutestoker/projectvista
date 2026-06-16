import { cn } from '@/lib/utils';
import {
    Dialog,
    DialogPanel,
    Transition,
    TransitionChild,
} from '@headlessui/react';
import { PropsWithChildren } from 'react';

type ProjectVistaModalMaxWidth =
    | 'sm'
    | 'md'
    | 'lg'
    | 'xl'
    | '2xl'
    | '3xl'
    | '4xl'
    | '5xl';

const maxWidthClasses: Record<ProjectVistaModalMaxWidth, string> = {
    sm: 'sm:max-w-sm',
    md: 'sm:max-w-md',
    lg: 'sm:max-w-lg',
    xl: 'sm:max-w-xl',
    '2xl': 'sm:max-w-2xl',
    '3xl': 'sm:max-w-3xl',
    '4xl': 'sm:max-w-4xl',
    '5xl': 'sm:max-w-5xl',
};

export function ProjectVistaModal({
    children,
    show = false,
    maxWidth = '2xl',
    closeable = true,
    className,
    panelClassName,
    onClose = () => {},
}: PropsWithChildren<{
    show: boolean;
    maxWidth?: ProjectVistaModalMaxWidth;
    closeable?: boolean;
    className?: string;
    panelClassName?: string;
    onClose?: () => void;
}>) {
    const close = () => {
        if (closeable) {
            onClose();
        }
    };

    return (
        <Transition show={show} leave="duration-200">
            <Dialog
                as="div"
                className={cn(
                    'fixed inset-0 z-50 flex transform items-center overflow-y-auto px-4 py-6 transition-all sm:px-6',
                    className,
                )}
                onClose={close}
            >
                <TransitionChild
                    enter="ease-out duration-200"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-150"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-black/70 backdrop-blur-sm" />
                </TransitionChild>

                <TransitionChild
                    enter="ease-out duration-200"
                    enterFrom="translate-y-3 opacity-0 scale-95"
                    enterTo="translate-y-0 opacity-100 scale-100"
                    leave="ease-in duration-150"
                    leaveFrom="translate-y-0 opacity-100 scale-100"
                    leaveTo="translate-y-3 opacity-0 scale-95"
                >
                    <DialogPanel
                        className={cn(
                            'border-border bg-card text-card-foreground relative z-10 mx-auto mb-6 w-full overflow-hidden rounded-xl border shadow-2xl shadow-black/40 transition-all',
                            maxWidthClasses[maxWidth],
                            panelClassName,
                        )}
                    >
                        {children}
                    </DialogPanel>
                </TransitionChild>
            </Dialog>
        </Transition>
    );
}
