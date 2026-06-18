import { useToast, useToastState } from '@/Context/ToastContext';
import { useEffect, useRef } from 'react';

interface DirtySaveToastOptions {
    id: string;
    isDirty: boolean;
    isProcessing?: boolean;
    message: string;
    onSave: () => void;
    onCancel: () => void;
}

export function useDirtySaveToast({
    id,
    isDirty,
    isProcessing = false,
    message,
    onSave,
    onCancel,
}: DirtySaveToastOptions): void {
    const toast = useToast();
    const { dismissToast } = useToastState();
    const saveRef = useRef(onSave);
    const cancelRef = useRef(onCancel);

    useEffect(() => {
        saveRef.current = onSave;
        cancelRef.current = onCancel;
    }, [onCancel, onSave]);

    useEffect(() => () => dismissToast(id), [dismissToast, id]);

    useEffect(() => {
        if (!isDirty) {
            dismissToast(id);

            return;
        }

        toast({
            id,
            message,
            type: 'warning',
            persistent: true,
            actions: [
                {
                    label: 'Cancel',
                    variant: 'outline',
                    disabled: isProcessing,
                    onClick: () => cancelRef.current(),
                },
                {
                    label: isProcessing ? 'Saving...' : 'Save',
                    variant: 'default',
                    disabled: isProcessing,
                    onClick: () => saveRef.current(),
                },
            ],
        });
    }, [dismissToast, id, isDirty, isProcessing, message, toast]);
}
