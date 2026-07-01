# Testify

Testify is a QA workspace for teams that need to plan test coverage, execute runs, track defects, and report release readiness in one place. The product is designed around the `testify.id` brand and keeps the full testing workflow connected from project setup to final reporting.

## What Testify Does

Testify helps QA teams work through the full delivery cycle:

- Organize projects and repository modules
- Write and maintain reusable test cases
- Group cases into test plans
- Execute test runs and capture results
- Track issues linked to plans and runs
- Generate QA reports for release review

## Features

### Project Management

- Single-owner projects
- Project dashboards and workspace views
- Project-scoped test assets and reporting

### Test Repository

- Create reusable test cases
- Structure cases with ordered steps
- Classify cases by priority, automation status, behavior, and type
- Keep test assets grouped by repository module

### Test Plans

- Build release or sprint-specific test plans
- Attach and detach test cases from a plan
- Export plan details as PDF
- Review plan coverage and status at a glance

### Test Execution

- Create test runs from selected plans and cases
- Track execution status per case
- Update case results during a run
- Mark runs as started, in progress, or completed

### Issue Tracking

- Create and manage issues from test findings
- Track priority, severity, status, assignee, and environment
- Link issues to the related test plan or test run

### Reporting

- Generate quality reports for stakeholders
- Export reports to PDF
- Use report summaries to understand readiness and risk

### AI Assistance

- Generate QA drafts with AI support
- Use AI for faster plan, test, and issue drafting workflows

### Authentication and API

- Inertia-powered web app authentication
- Sanctum Bearer token API access
- Versioned `/api/v1` JSON:API endpoints
- OpenAPI documentation for supported endpoints

## Stack

- Laravel 13
- Inertia.js + Svelte
- Tailwind CSS 4
- Laravel Sanctum
- Laravel Wayfinder
- Pest for tests

## Demo Accounts

The local seeder includes demo users for trying the app:

- `admin@testify.id`
- `demo1@testify.id`
- `demo2@testify.id`
- `demo3@testify.id`
- `demo4@testify.id`
- `demo5@testify.id`

## Development

Install dependencies:

```bash
composer install
npm install
```

Configure the app:

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

Run the app:

```bash
composer run dev
```

Run tests:

```bash
php artisan test --compact
```

## API Documentation

The versioned API is available at:

- `GET /api/v1/openapi`

The OpenAPI spec is stored in:

- `docs/openapi/v1.yaml`

## License

This project is proprietary to the Testify workspace unless a separate license is added later.
