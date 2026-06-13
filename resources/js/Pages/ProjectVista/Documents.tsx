import { ProjectVistaShell } from '@/Components/ProjectVista/ProjectVistaShell';
import { StatusPill } from '@/Components/ProjectVista/StatusPill';
import { ProjectPayload } from '@/types/projectvista';
import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';

interface DocumentsProps {
    project: ProjectPayload;
}

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
                <table className="w-full text-left text-sm">
                    <thead className="bg-white/5 text-white/55">
                        <tr>
                            <th className="px-4 py-3">Document</th>
                            <th className="px-4 py-3">Category</th>
                            <th className="px-4 py-3">Visibility</th>
                            <th className="px-4 py-3">Updated</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-white/10">
                        {project.documents.map((document) => (
                            <tr key={document.id}>
                                <td className="px-4 py-4">
                                    <div className="font-medium">
                                        {document.title}
                                    </div>
                                    <div className="text-xs text-white/45">
                                        Version {document.version}
                                    </div>
                                </td>
                                <td className="px-4 py-4 text-white/65">
                                    {document.category}
                                </td>
                                <td className="px-4 py-4">
                                    <StatusPill status={document.visibility} />
                                </td>
                                <td className="px-4 py-4 text-white/55">
                                    {document.updated_at}
                                </td>
                                <td className="px-4 py-4 text-right">
                                    <a
                                        href={document.url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex items-center gap-2 rounded-md border border-white/10 px-3 py-2 text-white/70"
                                    >
                                        <Download className="h-4 w-4" />
                                        Open
                                    </a>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </ProjectVistaShell>
    );
}
