import { CompanySettingsNav } from '@/Components/ProjectVista/CompanySettingsNav';
import { DataTable } from '@/Components/ProjectVista/DataTable';
import { DeleteIconButton } from '@/Components/ProjectVista/DeleteIconButton';
import { ProjectVistaShell } from '@/Components/ProjectVista/ProjectVistaShell';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
} from '@/Components/ui/select';
import { useDirtySaveToast } from '@/hooks/useDirtySaveToast';
import {
    CompanyPayload,
    CompanySettingsNavPayload,
    CompanySettingsPermissionsPayload,
    CompanySubcontractorPayload,
    ProjectVistaRole,
    SubcontractorTypePayload,
} from '@/types/projectvista';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { ReactNode, useCallback, useEffect, useMemo, useState } from 'react';

interface CompanySubcontractorsProps {
    company: CompanyPayload;
    role: ProjectVistaRole;
    settingsNav: CompanySettingsNavPayload;
    permissions: CompanySettingsPermissionsPayload;
    subcontractors: CompanySubcontractorPayload[];
    subcontractor_types: SubcontractorTypePayload[];
}

type SubcontractorTableRow = CompanySubcontractorPayload & {
    is_new?: boolean;
    temp_id?: string;
};

type DraftErrorMap = Record<string, Record<string, string>>;

let draftSubcontractorCounter = 0;

export default function CompanySubcontractors({
    company,
    role,
    settingsNav,
    permissions,
    subcontractors,
    subcontractor_types,
}: CompanySubcontractorsProps) {
    const [rows, setRows] =
        useState<CompanySubcontractorPayload[]>(subcontractors);
    const [baseline, setBaseline] =
        useState<CompanySubcontractorPayload[]>(subcontractors);
    const [draftRows, setDraftRows] = useState<SubcontractorTableRow[]>([]);
    const [createErrors, setCreateErrors] = useState<DraftErrorMap>({});
    const [processing, setProcessing] = useState(false);
    const canManage = permissions.can_manage_subcontractors;
    const tableRows = useMemo<SubcontractorTableRow[]>(
        () => [...draftRows, ...rows],
        [draftRows, rows],
    );
    const changedRows = useMemo(
        () =>
            rows.filter((row) => {
                const original = baseline.find((item) => item.id === row.id);

                return (
                    original !== undefined &&
                    JSON.stringify(normalizeSubcontractor(row)) !==
                        JSON.stringify(normalizeSubcontractor(original))
                );
            }),
        [baseline, rows],
    );
    const dirtyCount = changedRows.length + draftRows.length;
    const isDirty = dirtyCount > 0;

    useEffect(() => {
        setRows(subcontractors);
        setBaseline(subcontractors);
    }, [subcontractors]);

    const addDraftRow = useCallback(() => {
        if (!canManage) {
            return;
        }

        draftSubcontractorCounter += 1;
        setCreateErrors({});
        setDraftRows((currentRows) => [
            {
                id: -draftSubcontractorCounter,
                temp_id: `new-subcontractor-${draftSubcontractorCounter}`,
                is_new: true,
                name: '',
                email: '',
                title: '',
                subcontractor_type_id: null,
                scheduling_capacity_daily: 1,
                reliability_score: 80,
                scheduling_is_active: true,
            },
            ...currentRows,
        ]);
    }, [canManage]);

    const updateDraftRow = useCallback(
        (tempId: string, changes: Partial<SubcontractorTableRow>): void => {
            if (!canManage) {
                return;
            }

            setDraftRows((currentRows) =>
                currentRows.map((row) =>
                    row.temp_id === tempId ? { ...row, ...changes } : row,
                ),
            );
        },
        [canManage],
    );

    const removeDraftRow = useCallback((tempId: string): void => {
        setCreateErrors({});
        setDraftRows((currentRows) =>
            currentRows.filter((row) => row.temp_id !== tempId),
        );
    }, []);

    const updateRow = useCallback(
        (
            id: number,
            changes: Partial<CompanySubcontractorPayload>,
        ): void => {
            if (!canManage) {
                return;
            }

            setRows((currentRows) =>
                currentRows.map((row) =>
                    row.id === id ? { ...row, ...changes } : row,
                ),
            );
        },
        [canManage],
    );

    const cancelChanges = useCallback(() => {
        setRows(baseline);
        setDraftRows([]);
        setCreateErrors({});
    }, [baseline]);

    const saveChanges = useCallback(() => {
        if (processing || dirtyCount === 0) {
            return;
        }

        setProcessing(true);
        setCreateErrors({});

        const saveDraftRows = (): void => {
            if (draftRows.length === 0) {
                setBaseline(rows);
                setProcessing(false);

                return;
            }

            router.post(
                route('companies.subcontractors.store', company.slug),
                {
                    subcontractors: draftRows.map((row) => ({
                        name: row.name,
                        email: row.email,
                        title: row.title ?? '',
                        subcontractor_type_id: row.subcontractor_type_id,
                        scheduling_capacity_daily:
                            row.scheduling_capacity_daily,
                        reliability_score: row.reliability_score,
                        scheduling_is_active: row.scheduling_is_active,
                    })),
                },
                {
                    preserveScroll: true,
                    preserveState: 'errors',
                    onError: (errors) => {
                        setCreateErrors(
                            mapDraftErrorsByTempId(
                                errors as Record<string, string>,
                                draftRows,
                            ),
                        );
                        setProcessing(false);
                    },
                    onSuccess: () => {
                        setDraftRows([]);
                        setProcessing(false);
                    },
                },
            );
        };

        const saveNext = (index: number): void => {
            const row = changedRows[index];

            if (!row) {
                setBaseline(rows);
                saveDraftRows();

                return;
            }

            router.patch(
                route('companies.subcontractors.scheduling.update', [
                    company.slug,
                    row.id,
                ]),
                {
                    title: row.title ?? '',
                    subcontractor_type_id: row.subcontractor_type_id,
                    scheduling_capacity_daily:
                        row.scheduling_capacity_daily,
                    reliability_score: row.reliability_score,
                    scheduling_is_active: row.scheduling_is_active,
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    onError: () => setProcessing(false),
                    onSuccess: () => {
                        if (index + 1 < changedRows.length) {
                            saveNext(index + 1);

                            return;
                        }

                        setBaseline(rows);
                        saveDraftRows();
                    },
                },
            );
        };

        if (changedRows.length === 0) {
            saveDraftRows();

            return;
        }

        saveNext(0);
    }, [changedRows, company.slug, dirtyCount, draftRows, processing, rows]);

    useDirtySaveToast({
        id: `company-subcontractors-${company.id}`,
        isDirty,
        isProcessing: processing,
        message: saveToastMessage(draftRows.length, changedRows.length),
        onSave: saveChanges,
        onCancel: cancelChanges,
    });

    const draftError = useCallback(
        (row: SubcontractorTableRow, field: string): string | undefined => {
            if (!row.is_new || !row.temp_id) {
                return undefined;
            }

            return createErrors[row.temp_id]?.[field];
        },
        [createErrors],
    );

    const columns = useMemo<ColumnDef<SubcontractorTableRow>[]>(
        () => [
            {
                accessorKey: 'name',
                header: 'Subcontractor',
                cell: ({ row }) => {
                    if (row.original.is_new && row.original.temp_id) {
                        const nameError = draftError(row.original, 'name');
                        const emailError = draftError(row.original, 'email');

                        return (
                            <div className="flex min-w-56 flex-col gap-2">
                                <Input
                                    value={row.original.name}
                                    placeholder="Subcontractor name"
                                    aria-invalid={!!nameError}
                                    disabled={!canManage || processing}
                                    onChange={(event) =>
                                        updateDraftRow(row.original.temp_id!, {
                                            name: event.target.value,
                                        })
                                    }
                                />
                                <FieldErrorMessage error={nameError} />
                                <Input
                                    type="email"
                                    value={row.original.email}
                                    placeholder="email@example.com"
                                    aria-invalid={!!emailError}
                                    disabled={!canManage || processing}
                                    onChange={(event) =>
                                        updateDraftRow(row.original.temp_id!, {
                                            email: event.target.value,
                                        })
                                    }
                                />
                                <FieldErrorMessage error={emailError} />
                            </div>
                        );
                    }

                    return (
                        <div>
                            <div className="font-medium">
                                {row.original.name}
                            </div>
                            <div className="text-muted-foreground text-xs">
                                {row.original.email}
                            </div>
                        </div>
                    );
                },
            },
            {
                accessorKey: 'title',
                header: 'Company Title',
                cell: ({ row }) => {
                    const titleError = draftError(row.original, 'title');

                    return (
                        <div className="flex min-w-44 flex-col gap-2">
                            <Input
                                value={row.original.title ?? ''}
                                placeholder="Company title"
                                aria-invalid={!!titleError}
                                disabled={!canManage || processing}
                                onChange={(event) => {
                                    if (
                                        row.original.is_new &&
                                        row.original.temp_id
                                    ) {
                                        updateDraftRow(
                                            row.original.temp_id,
                                            {
                                                title: event.target.value,
                                            },
                                        );

                                        return;
                                    }

                                    updateRow(row.original.id, {
                                        title: event.target.value,
                                    });
                                }}
                            />
                            <FieldErrorMessage error={titleError} />
                        </div>
                    );
                },
            },
            {
                accessorKey: 'subcontractor_type_id',
                header: 'Trade Type',
                cell: ({ row }) => {
                    const selectedLabel =
                        subcontractor_types.find(
                            (type) =>
                                type.id ===
                                row.original.subcontractor_type_id,
                        )?.name ?? 'Unassigned';

                    return (
                        <div className="flex min-w-44 flex-col gap-2">
                            <Select
                                value={
                                    row.original.subcontractor_type_id?.toString() ??
                                    'unassigned'
                                }
                                onValueChange={(value) => {
                                    const subcontractorTypeId =
                                        value === 'unassigned'
                                            ? null
                                            : Number.parseInt(
                                                  String(value),
                                                  10,
                                              );

                                    if (
                                        row.original.is_new &&
                                        row.original.temp_id
                                    ) {
                                        updateDraftRow(
                                            row.original.temp_id,
                                            {
                                                subcontractor_type_id:
                                                    subcontractorTypeId,
                                            },
                                        );

                                        return;
                                    }

                                    updateRow(row.original.id, {
                                        subcontractor_type_id:
                                            subcontractorTypeId,
                                    });
                                }}
                            >
                                <SelectTrigger
                                    disabled={!canManage || processing}
                                    className="w-full data-[size=default]:h-9"
                                >
                                    <SelectedSelectLabel>
                                        {selectedLabel}
                                    </SelectedSelectLabel>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="unassigned">
                                            Unassigned
                                        </SelectItem>
                                        {subcontractor_types.map((type) => (
                                            <SelectItem
                                                key={type.id}
                                                value={type.id.toString()}
                                            >
                                                {type.name}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <FieldErrorMessage
                                error={draftError(
                                    row.original,
                                    'subcontractor_type_id',
                                )}
                            />
                        </div>
                    );
                },
            },
            {
                accessorKey: 'scheduling_capacity_daily',
                header: 'Daily Capacity',
                cell: ({ row }) => {
                    const capacityError = draftError(
                        row.original,
                        'scheduling_capacity_daily',
                    );

                    return (
                        <div className="flex min-w-28 flex-col gap-2">
                            <Input
                                type="number"
                                min={1}
                                max={20}
                                value={row.original.scheduling_capacity_daily}
                                aria-invalid={!!capacityError}
                                disabled={!canManage || processing}
                                onChange={(event) => {
                                    const value =
                                        Number.parseInt(
                                            event.target.value,
                                            10,
                                        ) || 1;

                                    if (
                                        row.original.is_new &&
                                        row.original.temp_id
                                    ) {
                                        updateDraftRow(
                                            row.original.temp_id,
                                            {
                                                scheduling_capacity_daily:
                                                    value,
                                            },
                                        );

                                        return;
                                    }

                                    updateRow(row.original.id, {
                                        scheduling_capacity_daily: value,
                                    });
                                }}
                            />
                            <FieldErrorMessage error={capacityError} />
                        </div>
                    );
                },
            },
            {
                accessorKey: 'reliability_score',
                header: 'Reliability',
                cell: ({ row }) => {
                    const reliabilityError = draftError(
                        row.original,
                        'reliability_score',
                    );

                    return (
                        <div className="flex min-w-28 flex-col gap-2">
                            <Input
                                type="number"
                                min={0}
                                max={100}
                                value={row.original.reliability_score}
                                aria-invalid={!!reliabilityError}
                                disabled={!canManage || processing}
                                onChange={(event) => {
                                    const value =
                                        Number.parseInt(
                                            event.target.value,
                                            10,
                                        ) || 0;

                                    if (
                                        row.original.is_new &&
                                        row.original.temp_id
                                    ) {
                                        updateDraftRow(
                                            row.original.temp_id,
                                            {
                                                reliability_score: value,
                                            },
                                        );

                                        return;
                                    }

                                    updateRow(row.original.id, {
                                        reliability_score: value,
                                    });
                                }}
                            />
                            <FieldErrorMessage error={reliabilityError} />
                        </div>
                    );
                },
            },
            {
                accessorKey: 'scheduling_is_active',
                header: 'Active',
                cell: ({ row }) => (
                    <div className="flex items-center gap-2">
                        <Checkbox
                            checked={row.original.scheduling_is_active}
                            disabled={!canManage || processing}
                            onCheckedChange={(checked) => {
                                if (
                                    row.original.is_new &&
                                    row.original.temp_id
                                ) {
                                    updateDraftRow(row.original.temp_id, {
                                        scheduling_is_active: checked === true,
                                    });

                                    return;
                                }

                                updateRow(row.original.id, {
                                    scheduling_is_active: checked === true,
                                });
                            }}
                        />
                        <Badge variant="secondary">
                            {row.original.scheduling_is_active
                                ? 'Active'
                                : 'Paused'}
                        </Badge>
                    </div>
                ),
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Actions</span>,
                cell: ({ row }) =>
                    row.original.is_new && row.original.temp_id ? (
                        <div className="text-right">
                            <DeleteIconButton
                                label="Remove new subcontractor row"
                                disabled={processing}
                                onClick={(event) => {
                                    event.stopPropagation();
                                    removeDraftRow(row.original.temp_id!);
                                }}
                            />
                        </div>
                    ) : null,
            },
        ],
        [
            canManage,
            draftError,
            processing,
            removeDraftRow,
            subcontractor_types,
            updateDraftRow,
            updateRow,
        ],
    );

    return (
        <ProjectVistaShell
            title="Subcontractors"
            eyebrow={company.name}
            role={role}
            company={company}
        >
            <Head title={`${company.name} Subcontractors`} />
            <div className="flex flex-col gap-6">
                <CompanySettingsNav nav={settingsNav} />
                <Card className="pv-card">
                    <CardHeader className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <CardTitle className="text-2xl">
                                Subcontractors
                            </CardTitle>
                            <CardDescription>
                                Manage trade, capacity, reliability, and
                                scheduling availability.
                            </CardDescription>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary">
                                {rows.length} Total
                            </Badge>
                            {draftRows.length > 0 ? (
                                <Badge variant="secondary">
                                    {draftRows.length} New
                                </Badge>
                            ) : null}
                            {canManage ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={processing}
                                    onClick={addDraftRow}
                                >
                                    <Plus data-icon="inline-start" />
                                    Add Row
                                </Button>
                            ) : null}
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-hidden rounded-lg border border-white/10">
                            <DataTable
                                columns={columns}
                                data={tableRows}
                                emptyMessage="No subcontractors are attached to this company."
                                getRowId={(row) =>
                                    row.temp_id ?? row.id.toString()
                                }
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </ProjectVistaShell>
    );
}

function saveToastMessage(newCount: number, changedCount: number): string {
    if (newCount > 0 && changedCount > 0) {
        return `Save ${newCount} new and ${changedCount} edited subcontractors?`;
    }

    if (newCount > 0) {
        return newCount === 1
            ? 'Save 1 new subcontractor?'
            : `Save ${newCount} new subcontractors?`;
    }

    return changedCount === 1
        ? 'Save 1 subcontractor change?'
        : `Save ${changedCount} subcontractor changes?`;
}

function FieldErrorMessage({ error }: { error?: string }) {
    if (!error) {
        return null;
    }

    return <p className="text-destructive text-xs">{error}</p>;
}

function mapDraftErrorsByTempId(
    errors: Record<string, string>,
    draftRows: SubcontractorTableRow[],
): DraftErrorMap {
    return Object.entries(errors).reduce<DraftErrorMap>(
        (mappedErrors, [key, message]) => {
            const match = /^subcontractors\.(\d+)\.(.+)$/.exec(key);

            if (!match) {
                return mappedErrors;
            }

            const row = draftRows[Number.parseInt(match[1], 10)];

            if (!row?.temp_id) {
                return mappedErrors;
            }

            mappedErrors[row.temp_id] = {
                ...(mappedErrors[row.temp_id] ?? {}),
                [match[2]]: message,
            };

            return mappedErrors;
        },
        {},
    );
}

function normalizeSubcontractor(row: CompanySubcontractorPayload): unknown {
    return {
        title: row.title ?? '',
        subcontractor_type_id: row.subcontractor_type_id ?? null,
        scheduling_capacity_daily: row.scheduling_capacity_daily,
        reliability_score: row.reliability_score,
        scheduling_is_active: row.scheduling_is_active,
    };
}

function SelectedSelectLabel({ children }: { children: ReactNode }) {
    return (
        <span
            data-slot="select-value"
            className="flex flex-1 items-center text-left"
        >
            {children}
        </span>
    );
}
