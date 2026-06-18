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
import { useDirtySaveToast } from '@/hooks/useDirtySaveToast';
import {
    CompanyPayload,
    CompanySettingsNavPayload,
    CompanySettingsPermissionsPayload,
    ProjectVistaRole,
    SubcontractorTypePayload,
} from '@/types/projectvista';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

interface CompanySubcontractorTypesProps {
    company: CompanyPayload;
    role: ProjectVistaRole;
    settingsNav: CompanySettingsNavPayload;
    permissions: CompanySettingsPermissionsPayload;
    subcontractor_types: SubcontractorTypePayload[];
}

type SubcontractorTypeTableRow = SubcontractorTypePayload & {
    is_new?: boolean;
    temp_id?: string;
};

type DraftErrorMap = Record<string, Record<string, string>>;

let draftTypeCounter = 0;

export default function CompanySubcontractorTypes({
    company,
    role,
    settingsNav,
    permissions,
    subcontractor_types,
}: CompanySubcontractorTypesProps) {
    const [rows, setRows] =
        useState<SubcontractorTypePayload[]>(subcontractor_types);
    const [baseline, setBaseline] =
        useState<SubcontractorTypePayload[]>(subcontractor_types);
    const [draftRows, setDraftRows] = useState<SubcontractorTypeTableRow[]>(
        [],
    );
    const [createErrors, setCreateErrors] = useState<DraftErrorMap>({});
    const [processing, setProcessing] = useState(false);
    const canManage = permissions.can_manage_subcontractor_types;
    const tableRows = useMemo<SubcontractorTypeTableRow[]>(
        () => [...draftRows, ...rows],
        [draftRows, rows],
    );
    const changedRows = useMemo(
        () =>
            rows.filter((row) => {
                const original = baseline.find((item) => item.id === row.id);

                return (
                    original !== undefined &&
                    JSON.stringify(normalizeSubcontractorType(row)) !==
                        JSON.stringify(normalizeSubcontractorType(original))
                );
            }),
        [baseline, rows],
    );
    const dirtyCount = changedRows.length + draftRows.length;
    const isDirty = dirtyCount > 0;

    useEffect(() => {
        setRows(subcontractor_types);
        setBaseline(subcontractor_types);
    }, [subcontractor_types]);

    const updateRow = useCallback(
        (id: number, changes: Partial<SubcontractorTypePayload>): void => {
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

    const updateDraftRow = useCallback(
        (
            tempId: string,
            changes: Partial<SubcontractorTypeTableRow>,
        ): void => {
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

    const addDraftRow = useCallback(() => {
        if (!canManage) {
            return;
        }

        const nextSortOrder =
            Math.max(
                0,
                ...rows.map((row) => row.sort_order),
                ...draftRows.map((row) => row.sort_order),
            ) + 10;

        draftTypeCounter += 1;
        setCreateErrors({});
        setDraftRows((currentRows) => [
            {
                id: -draftTypeCounter,
                temp_id: `new-subcontractor-type-${draftTypeCounter}`,
                is_new: true,
                name: '',
                slug: '',
                sort_order: nextSortOrder,
                is_active: true,
                allows_same_project_overlap: false,
            },
            ...currentRows,
        ]);
    }, [canManage, draftRows, rows]);

    const removeDraftRow = useCallback((tempId: string): void => {
        setCreateErrors({});
        setDraftRows((currentRows) =>
            currentRows.filter((row) => row.temp_id !== tempId),
        );
    }, []);

    const archiveRow = useCallback(
        (row: SubcontractorTypePayload): void => {
            if (!canManage || processing) {
                return;
            }

            setProcessing(true);
            router.delete(
                route('companies.subcontractor-types.destroy', [
                    company.slug,
                    row.id,
                ]),
                {
                    preserveScroll: true,
                    onFinish: () => setProcessing(false),
                },
            );
        },
        [canManage, company.slug, processing],
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

        const saveDraftNext = (index: number): void => {
            const row = draftRows[index];

            if (!row) {
                setDraftRows([]);
                setProcessing(false);

                return;
            }

            router.post(
                route('companies.subcontractor-types.store', company.slug),
                normalizeSubcontractorType(row),
                {
                    preserveScroll: true,
                    preserveState: 'errors',
                    onError: (errors) => {
                        if (row.temp_id) {
                            setCreateErrors({
                                [row.temp_id]: errors as Record<
                                    string,
                                    string
                                >,
                            });
                        }

                        setProcessing(false);
                    },
                    onSuccess: () => saveDraftNext(index + 1),
                },
            );
        };

        const saveNext = (index: number): void => {
            const row = changedRows[index];

            if (!row) {
                setBaseline(rows);
                saveDraftNext(0);

                return;
            }

            router.patch(
                route('companies.subcontractor-types.update', [
                    company.slug,
                    row.id,
                ]),
                normalizeSubcontractorType(row),
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
                        saveDraftNext(0);
                    },
                },
            );
        };

        if (changedRows.length === 0) {
            saveDraftNext(0);

            return;
        }

        saveNext(0);
    }, [
        changedRows,
        company.slug,
        dirtyCount,
        draftRows,
        processing,
        rows,
    ]);

    useDirtySaveToast({
        id: `company-subcontractor-types-${company.id}`,
        isDirty,
        isProcessing: processing,
        message: saveToastMessage(draftRows.length, changedRows.length),
        onSave: saveChanges,
        onCancel: cancelChanges,
    });

    const draftError = useCallback(
        (row: SubcontractorTypeTableRow, field: string): string | undefined => {
            if (!row.is_new || !row.temp_id) {
                return undefined;
            }

            return createErrors[row.temp_id]?.[field];
        },
        [createErrors],
    );

    const columns = useMemo<ColumnDef<SubcontractorTypeTableRow>[]>(
        () => [
            {
                accessorKey: 'name',
                header: 'Type',
                cell: ({ row }) => (
                    <div className="flex min-w-48 flex-col gap-2">
                        <Input
                            value={row.original.name}
                            placeholder="Trade type"
                            aria-invalid={!!draftError(row.original, 'name')}
                            disabled={!canManage || processing}
                            onChange={(event) => {
                                const name = event.target.value;
                                const slug =
                                    row.original.slug.trim() === ''
                                        ? slugify(name)
                                        : row.original.slug;

                                if (
                                    row.original.is_new &&
                                    row.original.temp_id
                                ) {
                                    updateDraftRow(row.original.temp_id, {
                                        name,
                                        slug,
                                    });

                                    return;
                                }

                                updateRow(row.original.id, { name });
                            }}
                        />
                        <FieldErrorMessage
                            error={draftError(row.original, 'name')}
                        />
                    </div>
                ),
            },
            {
                accessorKey: 'slug',
                header: 'Slug',
                cell: ({ row }) => (
                    <div className="flex min-w-44 flex-col gap-2">
                        <Input
                            value={row.original.slug}
                            placeholder="trade-type"
                            aria-invalid={!!draftError(row.original, 'slug')}
                            disabled={!canManage || processing}
                            onChange={(event) => {
                                const slug = slugify(event.target.value);

                                if (
                                    row.original.is_new &&
                                    row.original.temp_id
                                ) {
                                    updateDraftRow(row.original.temp_id, {
                                        slug,
                                    });

                                    return;
                                }

                                updateRow(row.original.id, { slug });
                            }}
                        />
                        <FieldErrorMessage
                            error={draftError(row.original, 'slug')}
                        />
                    </div>
                ),
            },
            {
                accessorKey: 'sort_order',
                header: 'Sort',
                cell: ({ row }) => (
                    <div className="flex min-w-24 flex-col gap-2">
                        <Input
                            type="number"
                            min={0}
                            max={10000}
                            value={row.original.sort_order}
                            aria-invalid={
                                !!draftError(row.original, 'sort_order')
                            }
                            disabled={!canManage || processing}
                            onChange={(event) => {
                                const sortOrder =
                                    Number.parseInt(
                                        event.target.value,
                                        10,
                                    ) || 0;

                                if (
                                    row.original.is_new &&
                                    row.original.temp_id
                                ) {
                                    updateDraftRow(row.original.temp_id, {
                                        sort_order: sortOrder,
                                    });

                                    return;
                                }

                                updateRow(row.original.id, {
                                    sort_order: sortOrder,
                                });
                            }}
                        />
                        <FieldErrorMessage
                            error={draftError(row.original, 'sort_order')}
                        />
                    </div>
                ),
            },
            {
                accessorKey: 'allows_same_project_overlap',
                header: 'Same Project Overlap',
                cell: ({ row }) => (
                    <div className="flex min-w-48 items-center gap-2">
                        <Checkbox
                            checked={row.original.allows_same_project_overlap}
                            disabled={!canManage || processing}
                            onCheckedChange={(checked) => {
                                const value = checked === true;

                                if (
                                    row.original.is_new &&
                                    row.original.temp_id
                                ) {
                                    updateDraftRow(row.original.temp_id, {
                                        allows_same_project_overlap: value,
                                    });

                                    return;
                                }

                                updateRow(row.original.id, {
                                    allows_same_project_overlap: value,
                                });
                            }}
                        />
                        <Badge variant="secondary">
                            {row.original.allows_same_project_overlap
                                ? 'Allowed'
                                : 'Restricted'}
                        </Badge>
                    </div>
                ),
            },
            {
                accessorKey: 'is_active',
                header: 'Status',
                cell: ({ row }) => (
                    <div className="flex items-center gap-2">
                        <Checkbox
                            checked={row.original.is_active}
                            disabled={!canManage || processing}
                            onCheckedChange={(checked) => {
                                const value = checked === true;

                                if (
                                    row.original.is_new &&
                                    row.original.temp_id
                                ) {
                                    updateDraftRow(row.original.temp_id, {
                                        is_active: value,
                                    });

                                    return;
                                }

                                updateRow(row.original.id, {
                                    is_active: value,
                                });
                            }}
                        />
                        <Badge variant="secondary">
                            {row.original.is_active ? 'Active' : 'Archived'}
                        </Badge>
                    </div>
                ),
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Actions</span>,
                cell: ({ row }) => {
                    if (row.original.is_new && row.original.temp_id) {
                        return (
                            <div className="text-right">
                                <DeleteIconButton
                                    label="Remove new subcontractor type row"
                                    disabled={processing}
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        removeDraftRow(row.original.temp_id!);
                                    }}
                                />
                            </div>
                        );
                    }

                    if (!row.original.is_active) {
                        return null;
                    }

                    return (
                        <div className="text-right">
                            <DeleteIconButton
                                label="Archive subcontractor type"
                                disabled={!canManage || processing}
                                onClick={(event) => {
                                    event.stopPropagation();
                                    archiveRow(row.original);
                                }}
                            />
                        </div>
                    );
                },
            },
        ],
        [
            archiveRow,
            canManage,
            draftError,
            processing,
            removeDraftRow,
            updateDraftRow,
            updateRow,
        ],
    );

    return (
        <ProjectVistaShell
            title="Subcontractor Types"
            eyebrow={company.name}
            role={role}
            company={company}
        >
            <Head title={`${company.name} Subcontractor Types`} />
            <div className="flex flex-col gap-6">
                <CompanySettingsNav nav={settingsNav} />
                <Card className="pv-card">
                    <CardHeader className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <CardTitle className="text-2xl">
                                Subcontractor Types
                            </CardTitle>
                            <CardDescription>
                                Manage assignment categories and same-project
                                overlap rules for scheduling.
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
                                emptyMessage="No subcontractor types have been created."
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
        return `Save ${newCount} new and ${changedCount} edited subcontractor types?`;
    }

    if (newCount > 0) {
        return newCount === 1
            ? 'Save 1 new subcontractor type?'
            : `Save ${newCount} new subcontractor types?`;
    }

    return changedCount === 1
        ? 'Save 1 subcontractor type change?'
        : `Save ${changedCount} subcontractor type changes?`;
}

function FieldErrorMessage({ error }: { error?: string }) {
    if (!error) {
        return null;
    }

    return <p className="text-destructive text-xs">{error}</p>;
}

function normalizeSubcontractorType(row: SubcontractorTypePayload) {
    return {
        name: row.name,
        slug: row.slug,
        sort_order: row.sort_order,
        is_active: row.is_active,
        allows_same_project_overlap: row.allows_same_project_overlap,
    };
}

function slugify(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}
