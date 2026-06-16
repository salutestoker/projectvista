import { useToast } from '@/Context/ToastContext';
import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

export function useGlobalEffects() {
    const { flash } = usePage<PageProps>().props;
    const toast = useToast();

    useEffect(() => {
        if (flash.toast?.message) {
            toast({
                message: flash.toast.message,
                type: flash.toast.type ?? 'info',
            });

            return;
        }

        if (flash.success) {
            toast({ message: flash.success, type: 'success' });

            return;
        }

        if (flash.error) {
            toast({ message: flash.error, type: 'error' });
        }
    }, [flash.error, flash.success, flash.toast, toast]);
}
