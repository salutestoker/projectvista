import { Button } from '@/Components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/Components/ui/tooltip';
import { cn } from '@/lib/utils';
import { XCircle } from 'lucide-react';
import { MouseEvent, PointerEvent } from 'react';

interface DeleteIconButtonProps {
    label: string;
    disabled?: boolean;
    className?: string;
    onClick: (event: MouseEvent<HTMLButtonElement>) => void;
    onPointerDown?: (event: PointerEvent<HTMLButtonElement>) => void;
}

export function DeleteIconButton({
    label,
    disabled = false,
    className,
    onClick,
    onPointerDown,
}: DeleteIconButtonProps) {
    return (
        <Tooltip>
            <TooltipTrigger
                render={
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        aria-label={label}
                        disabled={disabled}
                        className={cn(
                            'text-destructive hover:bg-destructive/10 hover:text-destructive focus-visible:border-destructive/40 focus-visible:ring-destructive/20',
                            className,
                        )}
                        onClick={onClick}
                        onPointerDown={onPointerDown}
                    >
                        <XCircle className="size-4" />
                    </Button>
                }
            />
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}
