import { Progress } from '@/Components/ui/progress';
import { cn } from '@/lib/utils';
import { useGSAP } from '@gsap/react';
import gsap from 'gsap';
import { useRef, useState } from 'react';

gsap.registerPlugin(useGSAP);

interface AnimatedProgressProps {
    value: number;
    className?: string;
}

export function AnimatedProgress({ value, className }: AnimatedProgressProps) {
    const scope = useRef<HTMLDivElement>(null);
    const [displayValue, setDisplayValue] = useState(0);

    useGSAP(
        () => {
            gsap.to(
                { value: 0 },
                {
                    value,
                    duration: 0.9,
                    ease: 'power3.out',
                    onUpdate() {
                        setDisplayValue(Math.round(this.targets()[0].value));
                    },
                },
            );
        },
        { scope, dependencies: [value], revertOnUpdate: true },
    );

    return (
        <div ref={scope} className={cn('w-full', className)}>
            <Progress value={displayValue} />
        </div>
    );
}
