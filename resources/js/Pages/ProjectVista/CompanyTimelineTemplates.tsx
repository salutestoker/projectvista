import { CompanySettingsNav } from '@/Components/ProjectVista/CompanySettingsNav';
import { ProjectVistaShell } from '@/Components/ProjectVista/ProjectVistaShell';
import { TimelineTemplateCard } from '@/Components/ProjectVista/TimelineTemplateCard';
import {
    CompanyPayload,
    CompanySettingsNavPayload,
    CompanySettingsPermissionsPayload,
    ProjectVistaRole,
    SubcontractorTypePayload,
    TimelineTemplatePayload,
} from '@/types/projectvista';
import { Head } from '@inertiajs/react';

interface CompanyTimelineTemplatesProps {
    company: CompanyPayload;
    role: ProjectVistaRole;
    settingsNav: CompanySettingsNavPayload;
    permissions: CompanySettingsPermissionsPayload;
    timeline_templates: TimelineTemplatePayload[];
    subcontractor_types: SubcontractorTypePayload[];
}

export default function CompanyTimelineTemplates({
    company,
    role,
    settingsNav,
    permissions,
    timeline_templates,
    subcontractor_types,
}: CompanyTimelineTemplatesProps) {
    return (
        <ProjectVistaShell
            title="Timeline Templates"
            eyebrow={company.name}
            role={role}
            company={company}
        >
            <Head title={`${company.name} Timeline Templates`} />
            <div className="flex flex-col gap-6">
                <CompanySettingsNav nav={settingsNav} />
                <TimelineTemplateCard
                    company={company}
                    templates={timeline_templates}
                    subcontractorTypes={subcontractor_types}
                    canManage={permissions.can_manage_templates}
                    canDelete={permissions.can_delete_templates}
                />
            </div>
        </ProjectVistaShell>
    );
}
