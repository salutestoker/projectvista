import { cn } from '@/lib/utils';

interface ProjectVistaLogoProps {
    className?: string;
    compact?: boolean;
}

export function ProjectVistaLogo({
    className,
    compact = false,
}: ProjectVistaLogoProps) {
    return (
        <img
            src="/brand/project-vista-logo-500x500.png"
            alt="Project Vista"
            className={cn(
                compact
                    ? 'h-12 w-auto rounded-md object-contain'
                    : 'h-16 w-auto object-contain',
                className,
            )}
        />
    );
}
