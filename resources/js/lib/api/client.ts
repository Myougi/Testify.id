type JsonErrorBody = {
    message?: string;
    errors?: Record<string, string[]>;
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
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            ...init.headers,
        },
        ...init,
    });

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

export async function requestFile<TError extends Error>(
    url: string,
    init: RequestInit,
    ErrorType: ApiErrorConstructor<TError>,
    fallbackMessage: string,
): Promise<FileDownload> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/pdf, application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            ...init.headers,
        },
        ...init,
    });

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
