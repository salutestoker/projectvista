import {
    ReactNode,
    createContext,
    useCallback,
    useContext,
    useState,
} from 'react';

export interface ConfirmOptions {
    title?: string;
    message: ReactNode;
    confirmText?: string;
    cancelText?: string;
    danger?: boolean;
    requireExplicitAction?: boolean;
    requiredConfirmationText?: string;
}

interface ConfirmState extends ConfirmOptions {
    resolve: (didConfirm: boolean) => void;
}

type ConfirmContextType = (options: ConfirmOptions) => Promise<boolean>;

interface ConfirmStateContextType {
    closeConfirm: () => void;
    confirmState: ConfirmState[];
}

const ConfirmContext = createContext<ConfirmContextType | undefined>(undefined);
const ConfirmStateContext = createContext<ConfirmStateContextType | undefined>(
    undefined,
);

export function ConfirmProvider({ children }: { children: ReactNode }) {
    const [confirmState, setConfirmState] = useState<ConfirmState[]>([]);

    const confirm = useCallback<ConfirmContextType>((options) => {
        let resolveConfirm: (value: boolean) => void = () => {};
        const promise = new Promise<boolean>((resolve) => {
            resolveConfirm = resolve;
        });

        setConfirmState((current) => [
            ...current,
            {
                ...options,
                resolve: resolveConfirm,
            },
        ]);

        return promise;
    }, []);

    const closeConfirm = useCallback(() => {
        setConfirmState((current) => current.slice(0, -1));
    }, []);

    return (
        <ConfirmStateContext.Provider value={{ closeConfirm, confirmState }}>
            <ConfirmContext.Provider value={confirm}>
                {children}
            </ConfirmContext.Provider>
        </ConfirmStateContext.Provider>
    );
}

export function useConfirmState() {
    const context = useContext(ConfirmStateContext);

    if (!context) {
        throw new Error(
            'useConfirmState must be used within a ConfirmProvider',
        );
    }

    return context;
}

export function useConfirm() {
    const context = useContext(ConfirmContext);

    if (!context) {
        throw new Error('useConfirm must be used within a ConfirmProvider');
    }

    return context;
}
