<?php

namespace App\Support\DataMigration;

final class DurableTableMap
{
    /**
     * @return list<array{table: non-empty-string, primary_key: non-empty-string, sequence_column: non-empty-string|null}>
     */
    public static function definitions(): array
    {
        return [
            ['table' => 'users', 'primary_key' => 'id', 'sequence_column' => 'id'],
            ['table' => 'projects', 'primary_key' => 'project_id', 'sequence_column' => 'project_id'],
            ['table' => 'project_environments', 'primary_key' => 'id', 'sequence_column' => 'id'],
            ['table' => 'project_credential_profiles', 'primary_key' => 'id', 'sequence_column' => 'id'],
            ['table' => 'test_repository_modules', 'primary_key' => 'id', 'sequence_column' => 'id'],
            ['table' => 'test_plans', 'primary_key' => 'id', 'sequence_column' => 'id'],
            ['table' => 'test_cases', 'primary_key' => 'id', 'sequence_column' => 'id'],
            ['table' => 'test_case_steps', 'primary_key' => 'id', 'sequence_column' => 'id'],
            ['table' => 'test_case_automations', 'primary_key' => 'id', 'sequence_column' => 'id'],
            ['table' => 'test_case_test_plan', 'primary_key' => 'id', 'sequence_column' => 'id'],
            ['table' => 'test_runs', 'primary_key' => 'id', 'sequence_column' => 'id'],
            ['table' => 'test_case_test_run', 'primary_key' => 'id', 'sequence_column' => 'id'],
            ['table' => 'automation_runs', 'primary_key' => 'id', 'sequence_column' => null],
            ['table' => 'automation_executions', 'primary_key' => 'id', 'sequence_column' => null],
            ['table' => 'automation_healing_attempts', 'primary_key' => 'id', 'sequence_column' => null],
            ['table' => 'automation_artifacts', 'primary_key' => 'id', 'sequence_column' => 'id'],
            ['table' => 'issues', 'primary_key' => 'id', 'sequence_column' => 'id'],
            ['table' => 'issue_attachments', 'primary_key' => 'id', 'sequence_column' => 'id'],
            ['table' => 'personal_access_tokens', 'primary_key' => 'id', 'sequence_column' => 'id'],
            ['table' => 'api_idempotency_keys', 'primary_key' => 'id', 'sequence_column' => 'id'],
            ['table' => 'api_audit_logs', 'primary_key' => 'id', 'sequence_column' => 'id'],
            ['table' => 'agent_conversations', 'primary_key' => 'id', 'sequence_column' => null],
            ['table' => 'agent_conversation_messages', 'primary_key' => 'id', 'sequence_column' => null],
        ];
    }

    /**
     * @return list<non-empty-string>
     */
    public static function tableNames(): array
    {
        return array_column(self::definitions(), 'table');
    }

    /**
     * @return array{table: non-empty-string, primary_key: non-empty-string, sequence_column: non-empty-string|null}
     */
    public static function definition(string $table): array
    {
        foreach (self::definitions() as $definition) {
            if ($definition['table'] === $table) {
                return $definition;
            }
        }

        throw new \InvalidArgumentException("Unsupported durable table [{$table}].");
    }
}
