import { registerToastFunction } from '@/utils/toastGlobalAccess';
import {
    createContext,
    ReactNode,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
} from 'react';

export type ToastType = 'success' | 'error' | 'info' | 'warning';

export interface ToastAction {
    label: string;
    onClick: () => void;
    variant?: 'default' | 'outline' | 'destructive' | 'ghost';
    disabled?: boolean;
}

export interface ToastState {
    id?: string;
    message: string;
    type: ToastType;
    persistent?: boolean;
    actions?: ToastAction[];
}

interface ToastProviderProps {
    children: ReactNode;
}

type ToastFunction = (options: ToastState) => void;

interface ToastStateContextType {
    dismissToast: (id?: string) => void;
    toastState: ToastState | null;
}

const ToastStateContext = createContext<ToastStateContextType | undefined>(
    undefined,
);
const ToastContext = createContext<ToastFunction | undefined>(undefined);

export function ToastProvider({ children }: ToastProviderProps) {
    const [toastState, setToastState] = useState<ToastState | null>(null);

    const toast = useCallback<ToastFunction>((options) => {
        setToastState(options);
    }, []);

    const dismissToast = useCallback((id?: string) => {
        setToastState((current) => {
            if (id && current?.id !== id) {
                return current;
            }

            return null;
        });
    }, []);

    useEffect(() => {
        registerToastFunction(toast);
    }, [toast]);

    const stateContext = useMemo(
        () => ({ dismissToast, toastState }),
        [dismissToast, toastState],
    );

    return (
        <ToastStateContext.Provider value={stateContext}>
            <ToastContext.Provider value={toast}>
                {children}
            </ToastContext.Provider>
        </ToastStateContext.Provider>
    );
}

export function useToastState() {
    const context = useContext(ToastStateContext);

    if (context === undefined) {
        throw new Error('useToastState must be used inside a ToastProvider');
    }

    return context;
}

export function useToast() {
    const context = useContext(ToastContext);

    if (context === undefined) {
        throw new Error('useToast must be used inside a ToastProvider');
    }

    return context;
}
