import { exportMethod as reportsExport } from '@/routes/reports';
import { requestFile } from './client';
import type { FileDownload } from './client';

export type ReportExportFilters = {
    scope: string;
    range: string;
    start_date: string;
    end_date: string;
    grouping: 'daily' | 'weekly';
    sections: string[];
};

export class ReportExportApiError extends Error {
    public status: number;

    public errors: Record<string, string>;

    public constructor(
        message: string,
        status: number,
        errors: Record<string, string> = {},
        public code: string | null = null,
    ) {
        super(message);

        this.name = 'ReportExportApiError';
        this.status = status;
        this.errors = errors;
    }
}

const toUrl = (routeValue: string | { url: string }): string =>
    typeof routeValue === 'string' ? routeValue : routeValue.url;

export async function exportReportPdf(
    filters: ReportExportFilters,
): Promise<FileDownload> {
    return requestFile(
        toUrl(reportsExport()),
        {
            body: JSON.stringify({
                ...filters,
                sections: filters.sections.join(','),
            }),
            method: 'POST',
        },
        ReportExportApiError,
        'Unable to generate the AI report PDF.',
    );
}
