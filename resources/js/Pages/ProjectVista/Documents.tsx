import { DataTable } from '@/Components/ProjectVista/DataTable';
import { ProjectVistaShell } from '@/Components/ProjectVista/ProjectVistaShell';
import { StatusPill } from '@/Components/ProjectVista/StatusPill';
import { ProjectPayload } from '@/types/projectvista';
import { Head } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { Download } from 'lucide-react';

interface DocumentsProps {
    project: ProjectPayload;
}

type ProjectDocument = ProjectPayload['documents'][number];

const documentColumns: ColumnDef<ProjectDocument>[] = [
    {
        accessorKey: 'title',
        header: 'Document',
        meta: {
            headerClassName: 'px-4 py-3 text-white/55',
            cellClassName: 'px-4 py-4',
        },
        cell: ({ row }) => (
            <div>
                <div className="font-medium">{row.original.title}</div>
                <div className="text-xs text-white/45">
                    Version {row.original.version}
                </div>
            </div>
        ),
    },
    {
        accessorKey: 'category',
        header: 'Category',
        meta: {
            headerClassName: 'px-4 py-3 text-white/55',
            cellClassName: 'px-4 py-4 text-white/65',
        },
    },
    {
        accessorKey: 'visibility',
        header: 'Visibility',
        meta: {
            headerClassName: 'px-4 py-3 text-white/55',
            cellClassName: 'px-4 py-4',
        },
        cell: ({ row }) => <StatusPill status={row.original.visibility} />,
    },
    {
        accessorKey: 'updated_at',
        header: 'Updated',
        meta: {
            headerClassName: 'px-4 py-3 text-white/55',
            cellClassName: 'px-4 py-4 text-white/55',
        },
    },
    {
        id: 'actions',
        header: '',
        meta: {
            headerClassName: 'px-4 py-3 text-white/55',
            cellClassName: 'px-4 py-4 text-right',
        },
        cell: ({ row }) => (
            <a
                href={row.original.url}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-2 rounded-md border border-white/10 px-3 py-2 text-white/70"
            >
                <Download className="h-4 w-4" />
                Open
            </a>
        ),
    },
];

export default function Documents({ project }: DocumentsProps) {
    return (
        <ProjectVistaShell
            title="Documents Library"
            eyebrow={project.name}
            role={project.role}
            project={project}
        >
            <Head title={`${project.name} Documents`} />
            <div className="overflow-hidden rounded-lg border border-white/10 bg-white/[0.04]">
                <DataTable
                    columns={documentColumns}
                    data={project.documents}
                    emptyMessage="No documents yet."
                    getRowId={(document) => document.id.toString()}
                />
            </div>
        </ProjectVistaShell>
    );
}
