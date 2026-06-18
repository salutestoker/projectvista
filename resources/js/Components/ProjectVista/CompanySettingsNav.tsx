import { Badge } from '@/Components/ui/badge';
import { cn } from '@/lib/utils';
import { CompanySettingsNavPayload } from '@/types/projectvista';
import { Link } from '@inertiajs/react';

export function CompanySettingsNav({
    nav,
}: {
    nav: CompanySettingsNavPayload;
}) {
    return (
        <nav className="flex flex-wrap gap-2">
            {nav.items.map((item) => {
                const active = item.key === nav.active;

                return (
                    <Link
                        key={item.key}
                        href={item.href}
                        className={cn(
                            'border-border bg-background/70 text-muted-foreground hover:bg-muted inline-flex h-9 items-center rounded-lg border px-3 text-sm font-medium transition',
                            active &&
                                'border-primary/40 bg-primary/10 text-primary',
                        )}
                    >
                        {item.label}
                        {active ? (
                            <Badge variant="secondary" className="ml-2">
                                Active
                            </Badge>
                        ) : null}
                    </Link>
                );
            })}
        </nav>
    );
}
