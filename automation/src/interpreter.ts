import type { Locator, Page } from 'playwright';
import { resolveSecret } from './credentials.js';
import type {
    AutomationDefinition,
    AutomationStep,
    CredentialValues,
    LocatorDefinition,
} from './types.js';

export class AssertionFailure extends Error {
    public constructor(
        message: string,
        public readonly stepNumber: number,
    ) {
        super(message);
    }
}

export class StepExecutionFailure extends Error {
    public constructor(
        message: string,
        public readonly stepNumber: number,
        public readonly action: AutomationStep['action'],
        public readonly cause: unknown,
    ) {
        super(message);
    }
}

export async function executeDefinition(
    page: Page,
    definition: AutomationDefinition,
    baseUrl: URL,
    credentials: CredentialValues,
): Promise<void> {
    for (const [index, step] of definition.steps.entries()) {
        try {
            await executeStep(page, step, baseUrl, credentials, index + 1);
            assertSameOrigin(page.url(), baseUrl, index + 1);
        } catch (error) {
            if (error instanceof AssertionFailure) {
                throw error;
            }

            throw new StepExecutionFailure(
                `Step ${index + 1} (${step.action}) failed: ${error instanceof Error ? error.message : String(error)}`,
                index + 1,
                step.action,
                error,
            );
        }
    }
}

async function executeStep(
    page: Page,
    step: AutomationStep,
    baseUrl: URL,
    credentials: CredentialValues,
    stepNumber: number,
): Promise<void> {
    switch (step.action) {
        case 'navigate':
            await page.goto(new URL(step.path, baseUrl).toString(), {
                waitUntil: 'domcontentloaded',
            });

            return;
        case 'click':
            await locator(page, step.locator).click();

            return;
        case 'fill':
            await locator(page, step.locator).fill(
                step.secret
                    ? resolveSecret(step.secret, credentials)
                    : (step.value ?? ''),
            );

            return;
        case 'select':
            await locator(page, step.locator).selectOption(
                step.secret
                    ? resolveSecret(step.secret, credentials)
                    : (step.value ?? ''),
            );

            return;
        case 'check':
            await locator(page, step.locator).check();

            return;
        case 'uncheck':
            await locator(page, step.locator).uncheck();

            return;
        case 'press':
            await locator(page, step.locator).press(step.key);

            return;
        case 'wait':
            await page.waitForTimeout(step.milliseconds);

            return;
        case 'assert_visible':
            if (!(await locator(page, step.locator).isVisible())) {
                throw new AssertionFailure(
                    `Step ${stepNumber}: expected element to be visible.`,
                    stepNumber,
                );
            }

            return;
        case 'assert_text': {
            const value = await locator(page, step.locator).textContent();

            if (!value?.includes(step.value)) {
                throw new AssertionFailure(
                    `Step ${stepNumber}: expected element text to contain ${JSON.stringify(step.value)}.`,
                    stepNumber,
                );
            }

            return;
        }
        case 'assert_url': {
            const current = new URL(page.url());

            if (
                `${current.pathname}${current.search}${current.hash}` !==
                step.path
            ) {
                throw new AssertionFailure(
                    `Step ${stepNumber}: expected URL path ${step.path}.`,
                    stepNumber,
                );
            }

            return;
        }
        case 'assert_value': {
            const value = await locator(page, step.locator).inputValue();

            if (value !== step.value) {
                throw new AssertionFailure(
                    `Step ${stepNumber}: expected input value ${JSON.stringify(step.value)}.`,
                    stepNumber,
                );
            }
        }
    }
}

export function locator(page: Page, definition: LocatorDefinition): Locator {
    switch (definition.by) {
        case 'role':
            return definition.name
                ? page.getByRole(
                      definition.role as Parameters<Page['getByRole']>[0],
                      { name: definition.name },
                  )
                : page.getByRole(
                      definition.role as Parameters<Page['getByRole']>[0],
                  );
        case 'label':
            return page.getByLabel(definition.value);
        case 'test_id':
            return page.getByTestId(definition.value);
        case 'text':
            return page.getByText(definition.value, { exact: true });
        case 'css':
            return page.locator(definition.value);
    }
}

function assertSameOrigin(
    currentUrl: string,
    baseUrl: URL,
    stepNumber: number,
): void {
    if (new URL(currentUrl).origin !== baseUrl.origin) {
        throw new AssertionFailure(
            `Step ${stepNumber}: navigation left the configured project origin.`,
            stepNumber,
        );
    }
}
