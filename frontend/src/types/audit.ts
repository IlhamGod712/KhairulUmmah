export interface AuditPelaku {
    id: number;
    name: string;
}

export interface AuditLog {
    id: number;
    action: string;
    description: string | null;
    subject_type: string | null;
    subject_id: number | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string | null;
    pelaku: AuditPelaku | null;
}

export interface AuditTrailFilter {
    user_id?: number;
    action?: string;
    from?: string;
    to?: string;
    page?: number;
    per_page?: number;
}

export interface AuditLogPagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface AuditLogListResponse {
    data: AuditLog[];
    meta: AuditLogPagination;
}
