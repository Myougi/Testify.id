<?php

return [
    'enabled' => (bool) env('AUTOMATION_ENABLED', false),

    'ai' => [
        'model' => env('AUTOMATION_AI_MODEL'),
        'timeout_seconds' => (int) env('AUTOMATION_AI_TIMEOUT_SECONDS', 180),
    ],

    'ai_healing' => [
        'enabled' => (bool) env('AUTOMATION_AI_HEALING_ENABLED', false),
        'model' => env('AUTOMATION_AI_HEALING_MODEL', env('AUTOMATION_AI_MODEL')),
        'timeout_seconds' => (int) env('AUTOMATION_AI_HEALING_TIMEOUT_SECONDS', 180),
        'max_attempts' => (int) env('AUTOMATION_MAX_AUTO_HEAL_ATTEMPTS', 1),
        'diagnostic_max_characters' => (int) env('AUTOMATION_DIAGNOSTIC_MAX_CHARACTERS', 20000),
    ],

    'github' => [
        'owner' => env('AUTOMATION_GITHUB_OWNER'),
        'repository' => env('AUTOMATION_GITHUB_REPOSITORY'),
        'workflow' => env('AUTOMATION_GITHUB_WORKFLOW', 'automated-test-run.yml'),
        'ref' => env('AUTOMATION_GITHUB_REF', 'main'),
        'token' => env('AUTOMATION_GITHUB_TOKEN'),
        'api_url' => env('AUTOMATION_GITHUB_API_URL', 'https://api.github.com'),
    ],

    'runner' => [
        'shared_secret' => env('AUTOMATION_RUNNER_SHARED_SECRET'),
        'signature_tolerance_seconds' => (int) env('AUTOMATION_SIGNATURE_TOLERANCE_SECONDS', 300),
        'rate_limit_per_minute' => (int) env('AUTOMATION_RUNNER_RATE_LIMIT', 120),
    ],

    'limits' => [
        'cases_per_run' => (int) env('AUTOMATION_MAX_CASES_PER_RUN', 25),
        'case_timeout_seconds' => (int) env('AUTOMATION_CASE_TIMEOUT_SECONDS', 120),
        'run_timeout_minutes' => (int) env('AUTOMATION_RUN_TIMEOUT_MINUTES', 30),
        'console_excerpt_length' => (int) env('AUTOMATION_CONSOLE_EXCERPT_LENGTH', 8000),
        'artifact_size_kilobytes' => (int) env('AUTOMATION_ARTIFACT_MAX_KILOBYTES', 15360),
    ],

    'artifacts' => [
        'disk' => env('AUTOMATION_ARTIFACTS_DISK', 's3'),
        'allowed_mime_types' => [
            'image/png',
            'application/zip',
            'text/plain',
            'application/json',
        ],
    ],
];
