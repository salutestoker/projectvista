import { ToastState } from '@/Context/ToastContext';

type ToastFunction = (options: ToastState) => void;

export let toast: ToastFunction = (options) => {
    void options;
};

export function registerToastFunction(fn: ToastFunction): void {
    toast = fn;
}
