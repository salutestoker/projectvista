import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { useConfirmState } from '@/Context/ConfirmContext';
import { cn } from '@/lib/utils';
import {
    Dialog,
    DialogPanel,
    DialogTitle,
    Transition,
    TransitionChild,
} from '@headlessui/react';
import { XCircle } from 'lucide-react';
import { useState } from 'react';

export function ConfirmDialog() {
    const { closeConfirm, confirmState } = useConfirmState();
    const [inputValue, setInputValue] = useState('');
    const currentConfirmState = confirmState[confirmState.length - 1];
    const isOpen = confirmState.length > 0;

    if (!isOpen || !currentConfirmState) {
        return null;
    }

    const closeConfirmWrapper = () => {
        closeConfirm();
        setInputValue('');
    };

    const handleCancel = () => {
        currentConfirmState.resolve(false);
        closeConfirmWrapper();
    };

    const handleClose = () => {
        if (!currentConfirmState.requireExplicitAction) {
            handleCancel();
        }
    };

    const handleConfirm = () => {
        if (
            currentConfirmState.requiredConfirmationText &&
            inputValue !== currentConfirmState.requiredConfirmationText
        ) {
            return;
        }

        currentConfirmState.resolve(true);
        closeConfirmWrapper();
    };

    return (
        <Transition show={isOpen}>
            <Dialog
                className="fixed inset-0 z-50 flex items-center justify-center px-4 py-6"
                onClose={handleClose}
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
                    <DialogPanel className="border-border bg-card text-card-foreground relative z-10 w-full max-w-md rounded-xl border p-6 shadow-2xl shadow-black/40">
                        <div className="flex items-start gap-4">
                            <div
                                className={cn(
                                    'mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-full border',
                                    currentConfirmState.danger
                                        ? 'border-destructive/30 bg-destructive/10 text-destructive'
                                        : 'border-primary/30 bg-primary/10 text-primary',
                                )}
                            >
                                <XCircle className="size-5" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <DialogTitle className="text-xl font-semibold">
                                    {currentConfirmState.title ?? 'Confirm'}
                                </DialogTitle>
                                <div className="text-muted-foreground mt-2 text-sm leading-6">
                                    {currentConfirmState.message}
                                </div>
                            </div>
                        </div>

                        {currentConfirmState.requiredConfirmationText ? (
                            <div className="mt-5">
                                <label
                                    htmlFor="confirmation-input"
                                    className="text-muted-foreground text-sm"
                                >
                                    Type{' '}
                                    <span className="text-foreground font-semibold">
                                        {
                                            currentConfirmState.requiredConfirmationText
                                        }
                                    </span>{' '}
                                    to confirm.
                                </label>
                                <Input
                                    id="confirmation-input"
                                    type="text"
                                    value={inputValue}
                                    autoComplete="off"
                                    className="mt-2"
                                    onChange={(event) =>
                                        setInputValue(event.target.value.trim())
                                    }
                                />
                            </div>
                        ) : null}

                        <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleCancel}
                            >
                                {currentConfirmState.cancelText ?? 'Cancel'}
                            </Button>
                            <Button
                                type="button"
                                variant={
                                    currentConfirmState.danger
                                        ? 'destructive'
                                        : 'default'
                                }
                                disabled={
                                    !!currentConfirmState.requiredConfirmationText &&
                                    inputValue !==
                                        currentConfirmState.requiredConfirmationText
                                }
                                onClick={handleConfirm}
                            >
                                {currentConfirmState.confirmText ?? 'Confirm'}
                            </Button>
                        </div>
                    </DialogPanel>
                </TransitionChild>
            </Dialog>
        </Transition>
    );
}
