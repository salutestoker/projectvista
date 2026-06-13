export type ProjectVistaRole =
    | 'super_admin'
    | 'company_admin'
    | 'company_manager'
    | 'subcontractor'
    | 'client'
    | 'viewer';

export interface CompanyPayload {
    id: number;
    name: string;
    slug: string;
    plan: string;
    subscription_status: string;
    brand_primary_color: string;
    brand_accent_color: string;
    feature_flags: Record<string, boolean>;
    projects_count?: number;
    users_count?: number;
}

export interface ProjectCardPayload {
    id: number;
    name: string;
    slug: string;
    company?: string;
    phase: string;
    status: string;
    percent_complete: number;
    health_status: string;
    hero_image_url?: string | null;
    pending_approvals: number;
    pending_selections: number;
    blocked_tasks: number;
    role: ProjectVistaRole;
}

export interface DashboardMetricPayload {
    label: string;
    value: string | number;
    detail: string;
    tone?: 'default' | 'gold';
}

export interface ProjectIndexRowPayload {
    id: number;
    name: string;
    code: string;
    slug: string;
    location: string;
    progress: number;
    status: string;
    health_status: string;
    status_label: string;
    hero_image_url?: string | null;
    manager?: string | null;
    manager_id?: number | null;
    client?: string | null;
    client_id?: number | null;
    next_step?: string;
    date_range?: string | null;
    approvals?: number;
    payment_percent?: number;
    payment_paid?: string;
    payment_total?: string;
    messages?: number;
    role_label?: string;
    current_task?: string;
    start_date?: string | null;
    due_date?: string | null;
    work_status?: string;
}

export interface ProjectIndexFiltersPayload {
    statuses: string[];
    managers: { id: number; name: string }[];
    clients: { id: number; name: string }[];
}

export interface DashboardProjectRowPayload {
    id: number;
    name: string;
    code: string;
    slug: string;
    location: string;
    progress: number;
    next_step: string;
    date_range?: string | null;
    approvals: number;
    payment_percent: number;
    payment_paid: string;
    payment_total: string;
    messages: number;
    hero_image_url?: string | null;
    role_label?: string;
    current_task?: string;
    due_date?: string | null;
    work_status?: string;
}

export interface BusinessHomePayload {
    type: 'owner' | 'manager';
    title: string;
    subtitle: string;
    metrics: DashboardMetricPayload[];
    project_rows: DashboardProjectRowPayload[];
    messages: { author: string; body: string }[];
    approvals_overview: { label: string; value: number; color: string }[];
    timeline: {
        percent: number;
        status: string;
        next_milestone: string;
        date_range?: string | null;
    };
    payments: {
        collected: string;
        total: string;
        percent: number;
        upcoming: number;
    };
}

export interface SubcontractorHomePayload {
    type: 'subcontractor';
    title: string;
    subtitle: string;
    metrics: DashboardMetricPayload[];
    project_rows: DashboardProjectRowPayload[];
    this_week: {
        title: string;
        project?: string | null;
        date_range?: string | null;
    }[];
    waiting_on: string[];
}

export interface ClientHomePayload {
    type: 'client';
    project: {
        name: string;
        location: string;
        status_label: string;
        next_step: string;
        date_range?: string | null;
        approvals_pending: number;
        payments_paid: string;
        payments_total: string;
        payment_percent: number;
        latest_update?: string | null;
        next_step_copy?: string | null;
    } | null;
    updates: {
        title: string;
        date: string;
        image_url?: string | null;
    }[];
}

export interface SuperAdminHomePayload {
    type: 'super_admin';
    title: string;
    subtitle: string;
}

export type DashboardHomePayload =
    | BusinessHomePayload
    | SubcontractorHomePayload
    | ClientHomePayload
    | SuperAdminHomePayload;

export interface TimelineTaskPayload {
    id: number;
    title: string;
    phase: string;
    description?: string | null;
    sort_order: number;
    status: string;
    starts_on?: string | null;
    due_on?: string | null;
    completed_on?: string | null;
    client_visible: boolean;
    subcontractor_visible: boolean;
    requires_acknowledgement: boolean;
}

export interface SelectionPayload {
    id: number;
    category?: string | null;
    name: string;
    description?: string | null;
    image_url?: string | null;
    status: string;
    manager_note?: string | null;
    client_response?: string | null;
    due_on?: string | null;
    approved_at?: string | null;
}

export interface ApprovalPayload {
    id: number;
    title: string;
    body: string;
    status: string;
    due_on?: string | null;
    response_note?: string | null;
    responded_at?: string | null;
    selection?: string | null;
}

export interface PaymentPayload {
    id: number;
    label: string;
    description?: string | null;
    amount?: string | null;
    status: string;
    due_on?: string | null;
    completed_on?: string | null;
    payment_url?: string | null;
    provider_label?: string | null;
}

export interface DocumentPayload {
    id: number;
    title: string;
    category: string;
    visibility: string;
    version: number;
    mime_type?: string | null;
    size?: number | null;
    client_visible: boolean;
    subcontractor_visible: boolean;
    url: string;
    uploaded_by?: string | null;
    updated_at: string;
}

export interface ThreadPayload {
    id: number;
    subject: string;
    status: string;
    last_message_at?: string | null;
    messages: {
        id: number;
        author: string;
        author_id: number;
        body: string;
        created_at: string;
    }[];
}

export interface ProjectPayload {
    id: number;
    name: string;
    slug: string;
    company: CompanyPayload;
    manager?: {
        id: number;
        name: string;
        email: string;
    } | null;
    client?: {
        id: number;
        name: string;
        email: string;
    } | null;
    address: string;
    project_type: string;
    status: string;
    phase: string;
    percent_complete: number;
    health_status: string;
    contract_amount?: string | null;
    starts_on?: string | null;
    estimated_completion_on?: string | null;
    hero_image_url?: string | null;
    client_summary?: string | null;
    latest_update?: string | null;
    next_step?: string | null;
    role: ProjectVistaRole;
    permissions: {
        can_edit_project: boolean;
        can_manage_standards: boolean;
        can_message: boolean;
        can_view_payments: boolean;
        can_upload_documents: boolean;
    };
    timeline: TimelineTaskPayload[];
    selections: SelectionPayload[];
    approvals: ApprovalPayload[];
    payments: PaymentPayload[];
    documents: DocumentPayload[];
    threads: ThreadPayload[];
    team: {
        id: number;
        name: string;
        email: string;
        role: string;
        assigned_scope?: string | null;
    }[];
}
