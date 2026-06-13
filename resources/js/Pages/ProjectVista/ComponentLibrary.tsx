import { AnimatedProgress } from '@/Components/ProjectVista/AnimatedProgress';
import { ApprovalDonut } from '@/Components/ProjectVista/ApprovalDonut';
import { ProjectVistaLogo } from '@/Components/ProjectVista/ProjectVistaLogo';
import { ProjectVistaShell } from '@/Components/ProjectVista/ProjectVistaShell';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { Separator } from '@/Components/ui/separator';
import { Skeleton } from '@/Components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Textarea } from '@/Components/ui/textarea';
import { Head } from '@inertiajs/react';
import { Bell, CheckCircle2, Sparkles } from 'lucide-react';

export default function ComponentLibrary() {
    return (
        <ProjectVistaShell
            title="Component Arsenal"
            eyebrow="Super Admin"
            role="super_admin"
        >
            <Head title="Component Arsenal" />
            <div className="flex flex-col gap-8">
                <section className="grid gap-5 xl:grid-cols-[380px_1fr]">
                    <Card className="pv-card">
                        <CardHeader>
                            <CardTitle>Brand</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-5">
                            <ProjectVistaLogo />
                            <div className="grid grid-cols-5 gap-2">
                                {[
                                    'bg-background',
                                    'bg-card',
                                    'bg-primary',
                                    'bg-pv-green',
                                    'bg-pv-blue',
                                ].map((token) => (
                                    <div key={token}>
                                        <div
                                            className={`${token} border-border h-14 rounded-lg border`}
                                        />
                                        <div className="text-muted-foreground mt-2 text-xs">
                                            {token.replace('bg-', '')}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="pv-card">
                        <CardHeader>
                            <CardTitle>Typography</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            <div className="text-5xl font-semibold">
                                Luxury project clarity.
                            </div>
                            <p className="text-muted-foreground max-w-3xl text-lg">
                                The design system prioritizes calm dashboards,
                                high contrast, restrained gold accents, and
                                dense operational scans.
                            </p>
                            <div className="text-primary text-xs tracking-[0.28em] uppercase">
                                ProjectVista eyebrow
                            </div>
                        </CardContent>
                    </Card>
                </section>

                <section className="grid gap-5 xl:grid-cols-3">
                    <Card className="pv-card">
                        <CardHeader>
                            <CardTitle>Actions</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-3">
                            <Button>
                                <Sparkles data-icon="inline-start" />
                                Primary
                            </Button>
                            <Button variant="secondary">Secondary</Button>
                            <Button variant="outline">
                                <Bell data-icon="inline-start" />
                                Outline
                            </Button>
                            <Button variant="ghost">Ghost</Button>
                        </CardContent>
                    </Card>

                    <Card className="pv-card">
                        <CardHeader>
                            <CardTitle>Badges And Avatars</CardTitle>
                        </CardHeader>
                        <CardContent className="flex items-center gap-3">
                            <Badge>Active</Badge>
                            <Badge className="bg-pv-green/20 text-pv-green">
                                On Schedule
                            </Badge>
                            <Badge className="bg-pv-red/20 text-pv-red">
                                Blocked
                            </Badge>
                            <Avatar>
                                <AvatarFallback className="bg-primary text-primary-foreground">
                                    PV
                                </AvatarFallback>
                            </Avatar>
                        </CardContent>
                    </Card>

                    <Card className="pv-card">
                        <CardHeader>
                            <CardTitle>Progress</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            <AnimatedProgress value={62} />
                            <AnimatedProgress value={88} />
                            <AnimatedProgress value={35} />
                        </CardContent>
                    </Card>
                </section>

                <section className="grid gap-5 xl:grid-cols-[1fr_380px]">
                    <Card className="pv-card">
                        <CardHeader>
                            <CardTitle>Project Table</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Project</TableHead>
                                        <TableHead>Progress</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Messages</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {[
                                        [
                                            'Smith Residence',
                                            '62%',
                                            'In Progress',
                                            '5',
                                        ],
                                        [
                                            'Williams Residence',
                                            '75%',
                                            'Needs Approval',
                                            '8',
                                        ],
                                    ].map((row) => (
                                        <TableRow key={row[0]}>
                                            {row.map((cell) => (
                                                <TableCell key={cell}>
                                                    {cell}
                                                </TableCell>
                                            ))}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card className="pv-card">
                        <CardHeader>
                            <CardTitle>Approval Chart</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ApprovalDonut
                                total={14}
                                data={[
                                    {
                                        label: 'Pending',
                                        value: 6,
                                        color: 'var(--pv-red)',
                                    },
                                    {
                                        label: 'In Review',
                                        value: 5,
                                        color: 'var(--pv-gold)',
                                    },
                                    {
                                        label: 'Approved',
                                        value: 3,
                                        color: 'var(--pv-green)',
                                    },
                                ]}
                            />
                        </CardContent>
                    </Card>
                </section>

                <section className="grid gap-5 xl:grid-cols-3">
                    <Card className="pv-card">
                        <CardHeader>
                            <CardTitle>Form Fields</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field>
                                    <FieldLabel htmlFor="component-name">
                                        Component name
                                    </FieldLabel>
                                    <Input
                                        id="component-name"
                                        placeholder="Payment snapshot"
                                    />
                                    <FieldDescription>
                                        shadcn field primitives are used for
                                        form layout.
                                    </FieldDescription>
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="component-notes">
                                        Notes
                                    </FieldLabel>
                                    <Textarea
                                        id="component-notes"
                                        placeholder="Describe usage guidance."
                                    />
                                </Field>
                                <Field orientation="horizontal">
                                    <Checkbox id="component-enabled" />
                                    <FieldLabel htmlFor="component-enabled">
                                        Enabled in dashboards
                                    </FieldLabel>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card className="pv-card">
                        <CardHeader>
                            <CardTitle>Alerts</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            <Alert>
                                <CheckCircle2 />
                                <AlertTitle>On schedule</AlertTitle>
                                <AlertDescription>
                                    Tile installation remains on track for the
                                    current milestone.
                                </AlertDescription>
                            </Alert>
                            <Separator />
                            <Skeleton className="h-8 w-full" />
                            <Skeleton className="h-8 w-2/3" />
                        </CardContent>
                    </Card>

                    <Card className="pv-card">
                        <CardHeader>
                            <CardTitle>Sidebar Item</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            <div className="bg-sidebar-accent text-sidebar-accent-foreground flex h-11 items-center gap-3 rounded-lg px-4">
                                <Sparkles className="size-4" />
                                <span className="flex-1">Selections</span>
                                <Badge>2</Badge>
                            </div>
                            <div className="text-muted-foreground flex h-11 items-center gap-3 rounded-lg px-4">
                                <Bell className="size-4" />
                                <span>Messaging</span>
                            </div>
                        </CardContent>
                    </Card>
                </section>
            </div>
        </ProjectVistaShell>
    );
}
