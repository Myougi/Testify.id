type JsonErrorBody = {
    message?: string;
    errors?: Record<string, string[]>;
    code?: string;
    login_url?: string;
};

export type ApiErrorConstructor<T extends Error> = new (
    message: string,
    status: number,
    errors: Record<string, string>,
) => T;

export type FileDownload = {
    blob: Blob;
    filename: string | null;
};

const csrfToken = (): string =>
    (
        document.querySelector(
            'meta[name="csrf-token"]',
        ) as HTMLMetaElement | null
    )?.content ?? '';

const xsrfToken = (): string => {
    const xsrfCookie = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='));

    if (!xsrfCookie) {
        return '';
    }

    return decodeURIComponent(xsrfCookie.slice('XSRF-TOKEN='.length));
};

const csrfHeaders = (): HeadersInit => {
    const token = csrfToken();
    const xsrf = xsrfToken();

    return {
        'X-Requested-With': 'XMLHttpRequest',
        ...(xsrf !== ''
            ? { 'X-XSRF-TOKEN': xsrf }
            : token !== ''
              ? { 'X-CSRF-TOKEN': token }
              : {}),
    };
};

const sessionExpiredLoginUrl = '/login?expired=1';

const errorBody = async (response: Response): Promise<JsonErrorBody> => {
    try {
        return (await response.clone().json()) as JsonErrorBody;
    } catch {
        return {};
    }
};

const redirectToLogin = async (response: Response): Promise<void> => {
    const body = await errorBody(response);

    window.location.assign(body.login_url ?? sessionExpiredLoginUrl);
};

const refreshCsrfCookie = async (): Promise<Response> =>
    fetch('/sanctum/csrf-cookie', {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

const fetchWithCsrfRecovery = async (
    url: string,
    request: () => Promise<Response>,
): Promise<Response> => {
    const response = await request();

    if (response.status !== 419) {
        return response;
    }

    const refreshResponse = await refreshCsrfCookie();

    if (!refreshResponse.ok) {
        await redirectToLogin(response);

        return response;
    }

    const retryResponse = await request();

    if (retryResponse.status === 401 || retryResponse.status === 419) {
        await redirectToLogin(retryResponse);
    }

    return retryResponse;
};

const toErrorBag = (
    errors?: Record<string, string[]>,
): Record<string, string> =>
    Object.fromEntries(
        Object.entries(errors ?? {}).map(([key, value]) => [
            key,
            Array.isArray(value) && value.length > 0
                ? value[0]
                : 'Invalid value.',
        ]),
    );

const responseFilename = (response: Response): string | null => {
    const disposition = response.headers.get('Content-Disposition');

    if (!disposition) {
        return null;
    }

    const encodedMatch = disposition.match(/filename\*=UTF-8''([^;]+)/i);

    if (encodedMatch?.[1]) {
        return decodeURIComponent(encodedMatch[1]);
    }

    const filenameMatch = disposition.match(/filename="?([^";]+)"?/i);

    return filenameMatch?.[1] ?? null;
};

export const buildQueryString = <T extends object>(filters: T): string => {
    const params = new URLSearchParams();

    Object.entries(filters).forEach(([key, value]) => {
        if (value === '' || value === null || value === undefined) {
            return;
        }

        params.set(key, String(value));
    });

    const queryString = params.toString();

    return queryString === '' ? '' : `?${queryString}`;
};

export async function requestJson<T, TError extends Error>(
    url: string,
    init: RequestInit,
    ErrorType: ApiErrorConstructor<TError>,
    fallbackMessage: string,
): Promise<T> {
    const response = await fetchWithCsrfRecovery(url, () =>
        fetch(url, {
            credentials: 'same-origin',
            ...init,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...csrfHeaders(),
                ...init.headers,
            },
        }),
    );

    if (!response.ok) {
        let body: JsonErrorBody = {};

        try {
            body = (await response.json()) as JsonErrorBody;
        } catch {
            body = {};
        }

        throw new ErrorType(
            body.message ?? fallbackMessage,
            response.status,
            toErrorBag(body.errors),
        );
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return (await response.json()) as T;
}

export async function requestFormData<T, TError extends Error>(
    url: string,
    body: FormData,
    ErrorType: ApiErrorConstructor<TError>,
    fallbackMessage: string,
): Promise<T> {
    const response = await fetchWithCsrfRecovery(url, () =>
        fetch(url, {
            body,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...csrfHeaders(),
            },
            method: 'POST',
        }),
    );

    if (!response.ok) {
        let errorBody: JsonErrorBody = {};

        try {
            errorBody = (await response.json()) as JsonErrorBody;
        } catch {
            errorBody = {};
        }

        throw new ErrorType(
            errorBody.message ?? fallbackMessage,
            response.status,
            toErrorBag(errorBody.errors),
        );
    }

    return (await response.json()) as T;
}

export async function requestFile<TError extends Error>(
    url: string,
    init: RequestInit,
    ErrorType: ApiErrorConstructor<TError>,
    fallbackMessage: string,
): Promise<FileDownload> {
    const response = await fetchWithCsrfRecovery(url, () =>
        fetch(url, {
            credentials: 'same-origin',
            ...init,
            headers: {
                Accept: 'application/pdf, application/json',
                'Content-Type': 'application/json',
                ...csrfHeaders(),
                ...init.headers,
            },
        }),
    );

    if (!response.ok) {
        let body: JsonErrorBody = {};

        try {
            body = (await response.json()) as JsonErrorBody;
        } catch {
            body = {};
        }

        throw new ErrorType(
            body.message ?? fallbackMessage,
            response.status,
            toErrorBag(body.errors),
        );
    }

    return {
        blob: await response.blob(),
        filename: responseFilename(response),
    };
}
