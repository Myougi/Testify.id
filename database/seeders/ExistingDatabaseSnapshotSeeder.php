<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExistingDatabaseSnapshotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->upsert('projects', $this->projects(), 'project_id');
            $this->upsert('test_repository_modules', $this->repositoryModules(), 'id');
            $this->upsert('test_plans', $this->testPlans(), 'id');
            $this->upsert('test_cases', $this->testCases(), 'id');
            $this->upsert('test_case_steps', $this->testCaseSteps(), 'id');
            $this->upsert('test_case_test_plan', $this->testCaseTestPlans(), 'id');
            $this->upsert('test_runs', $this->testRuns(), 'id');
            $this->upsert('test_case_test_run', $this->testCaseTestRuns(), 'id');
            $this->upsert('issues', $this->issues(), 'id');
            $this->upsert('issue_attachments', $this->issueAttachments(), 'id');
        });
    }

    /**
     * @param  list<array<string, int|string|null>>  $records
     * @param  non-empty-string  $uniqueBy
     */
    private function upsert(string $table, array $records, string $uniqueBy): void
    {
        if ($records === []) {
            return;
        }

        $updateColumns = array_values(array_diff(array_keys($records[0]), [$uniqueBy]));

        DB::table($table)->upsert(
            $records,
            $uniqueBy,
            $updateColumns,
        );
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    private function projects(): array
    {
        return [
            [
                'project_id' => 1,
                'project_name' => 'Spider Healthcare',
                'project_description' => 'Dexa group technical test',
                'project_url' => null,
                'project_status' => 'Active',
                'user_id' => 1,
                'created_at' => '2026-06-30 20:11:47',
                'updated_at' => '2026-07-01 01:52:16',
                'deleted_at' => null,
                'api_version' => 1,
            ],
        ];
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    private function repositoryModules(): array
    {
        return [
            ['id' => 1, 'project_id' => 1, 'parent_id' => null, 'name' => 'Login', 'key_prefix' => 'LG', 'sort_order' => 1, 'created_at' => '2026-07-01 03:35:55', 'updated_at' => '2026-07-01 03:35:55', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 2, 'project_id' => 1, 'parent_id' => null, 'name' => 'Change Password', 'key_prefix' => 'CP', 'sort_order' => 2, 'created_at' => '2026-07-01 03:36:05', 'updated_at' => '2026-07-01 03:36:05', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 3, 'project_id' => 1, 'parent_id' => null, 'name' => 'Shopping Cart', 'key_prefix' => 'SC', 'sort_order' => 3, 'created_at' => '2026-07-01 03:36:36', 'updated_at' => '2026-07-01 03:36:36', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 4, 'project_id' => 1, 'parent_id' => null, 'name' => 'Delivery', 'key_prefix' => 'DL', 'sort_order' => 4, 'created_at' => '2026-07-01 03:37:01', 'updated_at' => '2026-07-01 03:37:01', 'deleted_at' => null, 'api_version' => 1],
        ];
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    private function testPlans(): array
    {
        return [
            ['id' => 1, 'project_id' => 1, 'name' => 'Authentication and Security - Login & Password Management', 'description' => 'Comprehensive validation of US-01 (Secure Login) and US-02 (Password Change) including security constraints and session management.', 'scope' => 'Validation of email/password authentication, account lockout mechanism, password complexity enforcement, and cross-device session invalidation.', 'strategy' => 'Combination of Functional Testing (Positive/Negative), Security Testing (Encryption/Lockout), and State Transition Testing (Session Management). Testing will be performed across Web and Mobile API endpoints.', 'criteria' => 'Entry: User stories signed off, API endpoints deployed to staging, test accounts created. Exit: 100% of High/Urgent test cases passed, zero  bugs open, lockout timer verified.', 'status' => 'In Progress', 'priority' => 'High', 'start_date' => '2026-07-06', 'due_date' => '2026-07-10', 'release_version' => '1.0.0-beta', 'tester' => 'Jody Maulana', 'created_at' => '2026-07-01 02:05:12', 'updated_at' => '2026-07-01 09:08:31', 'deleted_at' => null, 'api_version' => 3],
            ['id' => 2, 'project_id' => 1, 'name' => 'Shopping Cart and Transaction Management', 'description' => 'End-to-end validation of the shopping cart functionality, focusing on quantity manipulation, real-time stock synchronization, and price calculation accuracy.', 'scope' => 'Validation of item list display (name, dosage, unit price, qty, subtotal), real-time stock validation, quantity increment/decrement logic, item removal, and automatic total amount recalculation.', 'strategy' => 'Combination of Functional Testing, Boundary Value Analysis (BVA) for quantity inputs, and Integration Testing for real-time inventory checks. Testing will cover positive paths, edge cases (max stock), and negative paths (zero/negative quantities).', 'criteria' => 'Transaction Management - Shopping Cart Validation', 'status' => 'In Progress', 'priority' => 'High', 'start_date' => '2026-07-13', 'due_date' => '2026-07-17', 'release_version' => '1.0.0-beta', 'tester' => 'Toto Suhariyanto', 'created_at' => '2026-07-01 02:14:27', 'updated_at' => '2026-07-01 09:08:19', 'deleted_at' => null, 'api_version' => 2],
            ['id' => 3, 'project_id' => 1, 'name' => 'Delivery Information Management', 'description' => 'Comprehensive validation of the delivery data entry form to ensure logistical accuracy and integration with courier sub-systems.', 'scope' => 'Validation of mandatory fields (Receiver Name, Phone Number, Complete Address, Postal Code), input constraints for phone numbers (min 10 digits, numeric only), boundary analysis for address length, and the \'Submit/Continue\' trigger for courier sub-system integration.', 'strategy' => 'Combination of positive functional testing, negative boundary value analysis on numeric fields, and integration testing for the hand-off to the logistics API.', 'criteria' => 'Entry: Delivery form UI available and courier API endpoint accessible in staging. Exit: All \'High\' and \'Urgent\' priority bugs resolved; successful data transmission to logistics sub-system verified for valid inputs.', 'status' => 'In Progress', 'priority' => 'High', 'start_date' => '2026-07-20', 'due_date' => '2026-07-24', 'release_version' => '1.0.0-beta', 'tester' => 'Abdul Aziz', 'created_at' => '2026-07-01 02:34:35', 'updated_at' => '2026-07-01 09:08:11', 'deleted_at' => null, 'api_version' => 2],
        ];
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    private function testCases(): array
    {
        return [
            ['id' => 3, 'project_id' => 1, 'module_id' => 1, 'case_key' => 'TC-LG-01', 'title' => 'Successful login with valid Kode ID and Password', 'priority' => 'Urgent', 'automation_status' => 'Manual', 'test_behavior' => 'Positive', 'test_type' => 'Smoke', 'preconditions' => 'User has an active account with Kode ID \'USER001\' and Password \'Password123!\'', 'created_at' => '2026-07-01 07:10:52', 'updated_at' => '2026-07-01 07:15:34', 'deleted_at' => null, 'api_version' => 2],
            ['id' => 4, 'project_id' => 1, 'module_id' => 1, 'case_key' => 'TC-LG-02', 'title' => 'Login failure with invalid Password', 'priority' => 'High', 'automation_status' => 'Manual', 'test_behavior' => 'Negative', 'test_type' => 'Functional', 'preconditions' => 'User has an active account with Kode ID \'USER001\'', 'created_at' => '2026-07-01 07:10:52', 'updated_at' => '2026-07-01 07:15:42', 'deleted_at' => null, 'api_version' => 2],
            ['id' => 5, 'project_id' => 1, 'module_id' => 1, 'case_key' => 'TC-LG-03', 'title' => 'Login failure with non-existent Kode ID', 'priority' => 'High', 'automation_status' => 'Manual', 'test_behavior' => 'Negative', 'test_type' => 'Functional', 'preconditions' => 'Kode ID \'NONEXISTENT\' is not registered in the system', 'created_at' => '2026-07-01 07:10:52', 'updated_at' => '2026-07-01 07:15:51', 'deleted_at' => null, 'api_version' => 2],
            ['id' => 6, 'project_id' => 1, 'module_id' => 1, 'case_key' => 'TC-LG-04', 'title' => 'Login attempt with empty fields', 'priority' => 'Medium', 'automation_status' => 'Manual', 'test_behavior' => 'Negative', 'test_type' => 'UI/UX', 'preconditions' => 'User is on the Login page', 'created_at' => '2026-07-01 07:10:52', 'updated_at' => '2026-07-01 07:10:52', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 7, 'project_id' => 1, 'module_id' => 1, 'case_key' => 'TC-LG-05', 'title' => 'Account lockout after maximum failed attempts', 'priority' => 'Urgent', 'automation_status' => 'Manual', 'test_behavior' => 'Negative', 'test_type' => 'Security', 'preconditions' => 'User \'USER001\' exists; Lockout threshold is 5 attempts', 'created_at' => '2026-07-01 07:10:52', 'updated_at' => '2026-07-01 07:16:02', 'deleted_at' => null, 'api_version' => 2],
            ['id' => 8, 'project_id' => 1, 'module_id' => 1, 'case_key' => 'TC-LG-06', 'title' => 'Login using SQL Injection payload in Kode ID', 'priority' => 'High', 'automation_status' => 'Manual', 'test_behavior' => 'Negative', 'test_type' => 'Security', 'preconditions' => 'User is on the Login page', 'created_at' => '2026-07-01 07:10:52', 'updated_at' => '2026-07-01 07:16:08', 'deleted_at' => null, 'api_version' => 2],
            ['id' => 9, 'project_id' => 1, 'module_id' => 2, 'case_key' => 'TC-CP-01', 'title' => 'Verify successful password change with valid data', 'priority' => 'Urgent', 'automation_status' => 'Manual', 'test_behavior' => 'Positive', 'test_type' => 'Functional', 'preconditions' => 'User is logged into the Spider Healthcare application and is on the \'Change Password\' page.', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 10, 'project_id' => 1, 'module_id' => 2, 'case_key' => 'TC-CP-02', 'title' => 'Verify password change failure with incorrect old password', 'priority' => 'High', 'automation_status' => 'Manual', 'test_behavior' => 'Negative', 'test_type' => 'Security', 'preconditions' => 'User is logged into the application and is on the \'Change Password\' page.', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 11, 'project_id' => 1, 'module_id' => 2, 'case_key' => 'TC-CP-03', 'title' => 'Verify boundary value analysis for minimum password length (7 chars)', 'priority' => 'High', 'automation_status' => 'Manual', 'test_behavior' => 'Negative', 'test_type' => 'Functional', 'preconditions' => 'User is logged into the application and is on the \'Change Password\' page.', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 12, 'project_id' => 1, 'module_id' => 2, 'case_key' => 'TC-CP-04', 'title' => 'Verify password complexity enforcement (Missing numbers/special chars)', 'priority' => 'High', 'automation_status' => 'Manual', 'test_behavior' => 'Negative', 'test_type' => 'Security', 'preconditions' => 'User is logged into the application and is on the \'Change Password\' page.', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 13, 'project_id' => 1, 'module_id' => 2, 'case_key' => 'TC-CP-05', 'title' => 'Verify password mismatch between New and Confirm fields', 'priority' => 'Medium', 'automation_status' => 'Manual', 'test_behavior' => 'Negative', 'test_type' => 'Functional', 'preconditions' => 'User is logged into the application and is on the \'Change Password\' page.', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 14, 'project_id' => 1, 'module_id' => 3, 'case_key' => 'TC-SC-01', 'title' => 'Add medicine to shopping cart', 'priority' => 'High', 'automation_status' => 'Manual', 'test_behavior' => 'Positive', 'test_type' => 'Functional', 'preconditions' => 'User is logged in; Medicine item exists in inventory with stock > 0.', 'created_at' => '2026-07-01 07:45:36', 'updated_at' => '2026-07-01 07:45:36', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 15, 'project_id' => 1, 'module_id' => 3, 'case_key' => 'TC-SC-02', 'title' => 'Increase quantity of medicine in cart', 'priority' => 'Medium', 'automation_status' => 'Manual', 'test_behavior' => 'Positive', 'test_type' => 'Functional', 'preconditions' => 'User has one item of a specific medicine in the cart.', 'created_at' => '2026-07-01 07:45:36', 'updated_at' => '2026-07-01 07:45:36', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 16, 'project_id' => 1, 'module_id' => 3, 'case_key' => 'TC-SC-03', 'title' => 'Decrease quantity of medicine in cart', 'priority' => 'Medium', 'automation_status' => 'Manual', 'test_behavior' => 'Positive', 'test_type' => 'Functional', 'preconditions' => 'User has 2 or more items of a specific medicine in the cart.', 'created_at' => '2026-07-01 07:45:36', 'updated_at' => '2026-07-01 07:45:36', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 17, 'project_id' => 1, 'module_id' => 3, 'case_key' => 'TC-SC-04', 'title' => 'Remove medicine from shopping cart', 'priority' => 'High', 'automation_status' => 'Manual', 'test_behavior' => 'Positive', 'test_type' => 'Functional', 'preconditions' => 'User has at least one item in the shopping cart.', 'created_at' => '2026-07-01 07:45:36', 'updated_at' => '2026-07-01 07:45:36', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 18, 'project_id' => 1, 'module_id' => 3, 'case_key' => 'TC-SC-05', 'title' => 'Exceed maximum available stock (BVA)', 'priority' => 'High', 'automation_status' => 'Manual', 'test_behavior' => 'Negative', 'test_type' => 'Integration', 'preconditions' => 'Medicine \'A\' has a stock of 5 units. User has 4 units in cart.', 'created_at' => '2026-07-01 07:45:36', 'updated_at' => '2026-07-01 07:45:36', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 19, 'project_id' => 1, 'module_id' => 3, 'case_key' => 'TC-SC-06', 'title' => 'Decrease quantity to zero (Edge Case)', 'priority' => 'Medium', 'automation_status' => 'Manual', 'test_behavior' => 'Negative', 'test_type' => 'Functional', 'preconditions' => 'User has 1 item of a specific medicine in the cart.', 'created_at' => '2026-07-01 07:45:36', 'updated_at' => '2026-07-01 07:45:36', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 20, 'project_id' => 1, 'module_id' => 3, 'case_key' => 'TC-SC-07', 'title' => 'Verify total amount calculation with delivery cost', 'priority' => 'High', 'automation_status' => 'Manual', 'test_behavior' => 'Positive', 'test_type' => 'Functional', 'preconditions' => 'User has multiple items in the cart; Delivery cost is defined in system settings.', 'created_at' => '2026-07-01 07:45:37', 'updated_at' => '2026-07-01 07:45:37', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 21, 'project_id' => 1, 'module_id' => 3, 'case_key' => 'TC-SC-08', 'title' => 'Manual quantity input validation (BVA)', 'priority' => 'Medium', 'automation_status' => 'Manual', 'test_behavior' => 'Negative', 'test_type' => 'Functional', 'preconditions' => 'User is on the Shopping Cart page with an item present.', 'created_at' => '2026-07-01 07:45:37', 'updated_at' => '2026-07-01 07:45:37', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 22, 'project_id' => 1, 'module_id' => 4, 'case_key' => 'TC-DL-01', 'title' => 'Verify successful Delivery Form submission with all valid data', 'priority' => 'High', 'automation_status' => 'Manual', 'test_behavior' => 'Positive', 'test_type' => 'Functional', 'preconditions' => 'User has items in the shopping cart and is on the Delivery Form page.', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 23, 'project_id' => 1, 'module_id' => 4, 'case_key' => 'TC-DL-02', 'title' => 'Verify mandatory field validation on Delivery Form', 'priority' => 'High', 'automation_status' => 'Manual', 'test_behavior' => 'Negative', 'test_type' => 'Functional', 'preconditions' => 'User is on the Delivery Form page.', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 24, 'project_id' => 1, 'module_id' => 4, 'case_key' => 'TC-DL-03', 'title' => 'Verify email format validation', 'priority' => 'Medium', 'automation_status' => 'Manual', 'test_behavior' => 'Negative', 'test_type' => 'Functional', 'preconditions' => 'User is on the Delivery Form page.', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 25, 'project_id' => 1, 'module_id' => 4, 'case_key' => 'TC-DL-04', 'title' => 'Verify Whatsapp number numeric validation', 'priority' => 'Medium', 'automation_status' => 'Manual', 'test_behavior' => 'Negative', 'test_type' => 'Functional', 'preconditions' => 'User is on the Delivery Form page.', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 26, 'project_id' => 1, 'module_id' => 4, 'case_key' => 'TC-DL-05', 'title' => 'Verify kode pos boundary and format (5 digits)', 'priority' => 'Medium', 'automation_status' => 'Manual', 'test_behavior' => 'Negative', 'test_type' => 'Functional', 'preconditions' => 'User is on the Delivery Form page.', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 27, 'project_id' => 1, 'module_id' => 4, 'case_key' => 'TC-DL-06', 'title' => 'Verify calculation of Subtotal, Delivery Cost, and Total', 'priority' => 'High', 'automation_status' => 'Manual', 'test_behavior' => 'Positive', 'test_type' => 'Functional', 'preconditions' => 'User has items in cart with a known total (e.g., 100,000). Delivery cost is determined by address (e.g., 15,000).', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 28, 'project_id' => 1, 'module_id' => 4, 'case_key' => 'TC-DL-07', 'title' => 'Verify delivery cost updates when address changes', 'priority' => 'Medium', 'automation_status' => 'Manual', 'test_behavior' => 'Positive', 'test_type' => 'Functional', 'preconditions' => 'User is on the Delivery Form page with a filled address.', 'created_at' => '2026-07-01 08:17:27', 'updated_at' => '2026-07-01 08:17:27', 'deleted_at' => null, 'api_version' => 1],
        ];
    }

    /**
     * @return list<array<string, int|string>>
     */
    private function testCaseSteps(): array
    {
        return [
            ['id' => 14, 'test_case_id' => 6, 'position' => 1, 'action' => 'Leave Kode ID field empty', 'expected_result' => 'Field remains empty', 'created_at' => '2026-07-01 07:10:52', 'updated_at' => '2026-07-01 07:10:52'],
            ['id' => 15, 'test_case_id' => 6, 'position' => 2, 'action' => 'Leave Password field empty', 'expected_result' => 'Field remains empty', 'created_at' => '2026-07-01 07:10:52', 'updated_at' => '2026-07-01 07:10:52'],
            ['id' => 16, 'test_case_id' => 6, 'position' => 3, 'action' => 'Click Login button', 'expected_result' => 'Validation messages \'Kode ID is required\' and \'Password is required\' appear', 'created_at' => '2026-07-01 07:10:52', 'updated_at' => '2026-07-01 07:10:52'],
            ['id' => 22, 'test_case_id' => 3, 'position' => 1, 'action' => 'Enter \'USER001\' in Kode ID field', 'expected_result' => 'Kode ID is displayed correctly', 'created_at' => '2026-07-01 07:15:34', 'updated_at' => '2026-07-01 07:15:34'],
            ['id' => 23, 'test_case_id' => 3, 'position' => 2, 'action' => 'Enter \'Password123!\' in Password field', 'expected_result' => 'Characters are masked for security', 'created_at' => '2026-07-01 07:15:34', 'updated_at' => '2026-07-01 07:15:34'],
            ['id' => 24, 'test_case_id' => 3, 'position' => 3, 'action' => 'Click Login button', 'expected_result' => 'User is redirected to the dashboard/home page', 'created_at' => '2026-07-01 07:15:34', 'updated_at' => '2026-07-01 07:15:34'],
            ['id' => 25, 'test_case_id' => 4, 'position' => 1, 'action' => 'Enter \'USER001\' in Kode ID field', 'expected_result' => 'Kode ID is displayed', 'created_at' => '2026-07-01 07:15:42', 'updated_at' => '2026-07-01 07:15:42'],
            ['id' => 26, 'test_case_id' => 4, 'position' => 2, 'action' => 'Enter \'WrongPass123\' in Password field', 'expected_result' => 'Password is displayed as masked', 'created_at' => '2026-07-01 07:15:42', 'updated_at' => '2026-07-01 07:15:42'],
            ['id' => 27, 'test_case_id' => 4, 'position' => 3, 'action' => 'Click Login button', 'expected_result' => 'Error message \'Invalid Kode ID or Password\' is displayed', 'created_at' => '2026-07-01 07:15:42', 'updated_at' => '2026-07-01 07:15:42'],
            ['id' => 28, 'test_case_id' => 5, 'position' => 1, 'action' => 'Enter \'NONEXISTENT\' in Kode ID field', 'expected_result' => 'Kode ID is displayed', 'created_at' => '2026-07-01 07:15:51', 'updated_at' => '2026-07-01 07:15:51'],
            ['id' => 29, 'test_case_id' => 5, 'position' => 2, 'action' => 'Enter \'AnyPassword123\' in Password field', 'expected_result' => 'Password is masked', 'created_at' => '2026-07-01 07:15:51', 'updated_at' => '2026-07-01 07:15:51'],
            ['id' => 30, 'test_case_id' => 5, 'position' => 3, 'action' => 'Click Login button', 'expected_result' => 'Error message \'Invalid Kode ID or Password\' is displayed', 'created_at' => '2026-07-01 07:15:51', 'updated_at' => '2026-07-01 07:15:51'],
            ['id' => 31, 'test_case_id' => 7, 'position' => 1, 'action' => 'Enter \'USER001\' and an incorrect password 5 times consecutively', 'expected_result' => 'System records 5 failed attempts', 'created_at' => '2026-07-01 07:16:02', 'updated_at' => '2026-07-01 07:16:02'],
            ['id' => 32, 'test_case_id' => 7, 'position' => 2, 'action' => 'Enter \'USER001\' and the CORRECT password on the 6th attempt', 'expected_result' => 'Login is denied; Error message \'Account is locked. Please try again later\' is displayed', 'created_at' => '2026-07-01 07:16:02', 'updated_at' => '2026-07-01 07:16:02'],
            ['id' => 33, 'test_case_id' => 8, 'position' => 1, 'action' => 'Enter " \' OR \'1\'=\'1 " in Kode ID field', 'expected_result' => 'Input is accepted', 'created_at' => '2026-07-01 07:16:08', 'updated_at' => '2026-07-01 07:16:08'],
            ['id' => 34, 'test_case_id' => 8, 'position' => 2, 'action' => 'Enter any string in Password field', 'expected_result' => 'Input is accepted', 'created_at' => '2026-07-01 07:16:08', 'updated_at' => '2026-07-01 07:16:08'],
            ['id' => 35, 'test_case_id' => 8, 'position' => 3, 'action' => 'Click Login button', 'expected_result' => 'Access is denied; System returns standard \'Invalid Kode ID or Password\' error', 'created_at' => '2026-07-01 07:16:08', 'updated_at' => '2026-07-01 07:16:08'],
            ['id' => 36, 'test_case_id' => 9, 'position' => 1, 'action' => 'Enter valid old password in \'Old Password\' field', 'expected_result' => 'Text is entered correctly', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27'],
            ['id' => 37, 'test_case_id' => 9, 'position' => 2, 'action' => 'Enter valid new password (8+ chars, alpha, numeric, special char) in \'New Password\' field', 'expected_result' => 'Text is entered correctly', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27'],
            ['id' => 38, 'test_case_id' => 9, 'position' => 3, 'action' => 'Enter identical password in \'Confirm Password\' field', 'expected_result' => 'Text is entered correctly', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27'],
            ['id' => 39, 'test_case_id' => 9, 'position' => 4, 'action' => 'Click \'Submit\' button', 'expected_result' => 'Password is changed successfully and success message is displayed', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27'],
            ['id' => 40, 'test_case_id' => 10, 'position' => 1, 'action' => 'Enter valid old password in \'Old Password\' field', 'expected_result' => 'Text is entered correctly', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27'],
            ['id' => 41, 'test_case_id' => 10, 'position' => 2, 'action' => 'Enter incorrect old password in \'Old Password\' field', 'expected_result' => 'Text is entered correctly', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27'],
            ['id' => 42, 'test_case_id' => 10, 'position' => 3, 'action' => 'Enter valid new passwords in \'New Password\' and \'Confirm Password\' fields', 'expected_result' => 'Text is entered correctly', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27'],
            ['id' => 43, 'test_case_id' => 10, 'position' => 4, 'action' => 'Click \'Submit\' button', 'expected_result' => 'Error message \'Incorrect old password\' is displayed; password is not changed', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27'],
            ['id' => 44, 'test_case_id' => 11, 'position' => 1, 'action' => 'Enter valid old password in \'Old Password\' field', 'expected_result' => 'Text is entered correctly', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27'],
            ['id' => 45, 'test_case_id' => 11, 'position' => 2, 'action' => 'Enter password with 7 characters in \'New Password\' field', 'expected_result' => 'Text is entered correctly', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27'],
            ['id' => 46, 'test_case_id' => 11, 'position' => 3, 'action' => 'Enter identical 7-character password in \'Confirm Password\' field', 'expected_result' => 'Text is entered correctly', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27'],
            ['id' => 47, 'test_case_id' => 11, 'position' => 4, 'action' => 'Click \'Submit\' button', 'expected_result' => 'Validation error indicating password must be at least 8 characters is displayed', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27'],
            ['id' => 48, 'test_case_id' => 12, 'position' => 1, 'action' => 'Enter valid old password in \'Old Password\' field', 'expected_result' => 'Text is entered correctly', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27'],
            ['id' => 49, 'test_case_id' => 12, 'position' => 2, 'action' => 'Enter password containing only alphabet characters in \'New Password\' field', 'expected_result' => 'Text is entered correctly', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27'],
            ['id' => 50, 'test_case_id' => 12, 'position' => 3, 'action' => 'Enter identical password in \'Confirm Password\' field', 'expected_result' => 'Text is entered correctly', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27'],
            ['id' => 51, 'test_case_id' => 12, 'position' => 4, 'action' => 'Click \'Submit\' button', 'expected_result' => 'Validation error indicating missing numeric and special characters is displayed', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27'],
            ['id' => 52, 'test_case_id' => 13, 'position' => 1, 'action' => 'Enter valid old password in \'Old Password\' field', 'expected_result' => 'Text is entered correctly', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27'],
            ['id' => 53, 'test_case_id' => 13, 'position' => 2, 'action' => 'Enter valid new password (e.g., \'Pass123!\') in \'New Password\' field', 'expected_result' => 'Text is entered correctly', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27'],
            ['id' => 54, 'test_case_id' => 13, 'position' => 3, 'action' => 'Enter a different password (e.g., \'Diff456#\') in \'Confirm Password\' field', 'expected_result' => 'Text is entered correctly', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27'],
            ['id' => 55, 'test_case_id' => 13, 'position' => 4, 'action' => 'Click \'Submit\' button', 'expected_result' => 'Validation error indicating \'Passwords do not match\' is displayed', 'created_at' => '2026-07-01 07:23:27', 'updated_at' => '2026-07-01 07:23:27'],
            ['id' => 56, 'test_case_id' => 14, 'position' => 1, 'action' => 'Click \'Add to Cart\' button on a medicine item page.', 'expected_result' => 'Medicine is added to the cart; Cart badge item count increments by 1.', 'created_at' => '2026-07-01 07:45:36', 'updated_at' => '2026-07-01 07:45:36'],
            ['id' => 57, 'test_case_id' => 14, 'position' => 2, 'action' => 'Navigate to Shopping Cart page.', 'expected_result' => 'The added medicine is displayed with correct name, dosage, and unit price.', 'created_at' => '2026-07-01 07:45:36', 'updated_at' => '2026-07-01 07:45:36'],
            ['id' => 58, 'test_case_id' => 15, 'position' => 1, 'action' => 'Click the \'+\' (increment) button for the medicine item.', 'expected_result' => 'Quantity increases to 2; Subtotal for the item is recalculated correctly (Unit Price * 2).', 'created_at' => '2026-07-01 07:45:36', 'updated_at' => '2026-07-01 07:45:36'],
            ['id' => 59, 'test_case_id' => 15, 'position' => 2, 'action' => 'Verify the total amount of the cart.', 'expected_result' => 'Total amount updates in real-time to reflect the increased quantity.', 'created_at' => '2026-07-01 07:45:36', 'updated_at' => '2026-07-01 07:45:36'],
            ['id' => 60, 'test_case_id' => 16, 'position' => 1, 'action' => 'Click the \'-\' (decrement) button for the medicine item.', 'expected_result' => 'Quantity decreases by 1; Subtotal for the item is recalculated correctly.', 'created_at' => '2026-07-01 07:45:36', 'updated_at' => '2026-07-01 07:45:36'],
            ['id' => 61, 'test_case_id' => 17, 'position' => 1, 'action' => 'Click the \'Remove\' or \'Trash\' icon for a specific medicine item.', 'expected_result' => 'The item is removed from the list; Total amount and subtotal are updated immediately.', 'created_at' => '2026-07-01 07:45:36', 'updated_at' => '2026-07-01 07:45:36'],
            ['id' => 62, 'test_case_id' => 18, 'position' => 1, 'action' => 'Click the \'+\' button to increase quantity to 5.', 'expected_result' => 'Quantity becomes 5; Item is successfully added.', 'created_at' => '2026-07-01 07:45:36', 'updated_at' => '2026-07-01 07:45:36'],
            ['id' => 63, 'test_case_id' => 18, 'position' => 2, 'action' => 'Click the \'+\' button again to increase quantity to 6.', 'expected_result' => 'System prevents increase; Display error message \'Maximum stock reached\' or \'Insufficient stock\'.', 'created_at' => '2026-07-01 07:45:36', 'updated_at' => '2026-07-01 07:45:36'],
            ['id' => 64, 'test_case_id' => 19, 'position' => 1, 'action' => 'Click the \'-\' (decrement) button.', 'expected_result' => 'System either removes the item automatically or disables the \'-\' button to prevent zero/negative quantity.', 'created_at' => '2026-07-01 07:45:36', 'updated_at' => '2026-07-01 07:45:36'],
            ['id' => 65, 'test_case_id' => 20, 'position' => 1, 'action' => 'View the cart summary section.', 'expected_result' => 'The \'Medicine Price\' (sum of all item subtotals), \'Delivery Cost\', and \'Total Amount\' (Medicine Price + Delivery Cost) are displayed clearly and calculated accurately.', 'created_at' => '2026-07-01 07:45:37', 'updated_at' => '2026-07-01 07:45:37'],
            ['id' => 66, 'test_case_id' => 21, 'position' => 1, 'action' => 'Click the quantity input field and enter \'-1\'.', 'expected_result' => 'Input is rejected or automatically reset to 1; Error message displayed.', 'created_at' => '2026-07-01 07:45:37', 'updated_at' => '2026-07-01 07:45:37'],
            ['id' => 67, 'test_case_id' => 21, 'position' => 2, 'action' => 'Enter a non-numeric character (e.g., \'abc\').', 'expected_result' => 'Input is blocked or ignored; Field remains unchanged.', 'created_at' => '2026-07-01 07:45:37', 'updated_at' => '2026-07-01 07:45:37'],
            ['id' => 68, 'test_case_id' => 22, 'position' => 1, 'action' => 'Enter \'John Doe\' in Name field', 'expected_result' => 'Text is accepted', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26'],
            ['id' => 69, 'test_case_id' => 22, 'position' => 2, 'action' => 'Enter \'628123456789\' in Whatsapp number field', 'expected_result' => 'Text is accepted', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26'],
            ['id' => 70, 'test_case_id' => 22, 'position' => 3, 'action' => 'Enter \'john@example.com\' in email field', 'expected_result' => 'Text is accepted', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26'],
            ['id' => 71, 'test_case_id' => 22, 'position' => 4, 'action' => 'Select \'Male\' from gender selection', 'expected_result' => 'Gender \'Male\' is selected', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26'],
            ['id' => 72, 'test_case_id' => 22, 'position' => 5, 'action' => 'Enter \'Jl. Sudirman No. 1\' in address field', 'expected_result' => 'Text is accepted', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26'],
            ['id' => 73, 'test_case_id' => 22, 'position' => 6, 'action' => 'Enter \'Kebayoran Baru\' in kecamatan field', 'expected_result' => 'Text is accepted', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26'],
            ['id' => 74, 'test_case_id' => 22, 'position' => 7, 'action' => 'Enter \'Senayan\' in kelurahan field', 'expected_result' => 'Text is accepted', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26'],
            ['id' => 75, 'test_case_id' => 22, 'position' => 8, 'action' => 'Enter \'Jakarta Selatan\' in kota field', 'expected_result' => 'Text is accepted', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26'],
            ['id' => 76, 'test_case_id' => 22, 'position' => 9, 'action' => 'Enter \'12190\' in kode pos field', 'expected_result' => 'Text is accepted', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26'],
            ['id' => 77, 'test_case_id' => 22, 'position' => 10, 'action' => 'Click Submit button', 'expected_result' => 'Form is submitted successfully and user is redirected to order confirmation/payment page', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26'],
            ['id' => 78, 'test_case_id' => 23, 'position' => 1, 'action' => 'Leave all fields empty', 'expected_result' => 'Fields remain empty', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26'],
            ['id' => 79, 'test_case_id' => 23, 'position' => 2, 'action' => 'Click Submit button', 'expected_result' => 'System displays validation error messages for all mandatory fields (Name, Whatsapp, email, gender, address, kecamatan, kelurahan, kota, kode pos)', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26'],
            ['id' => 80, 'test_case_id' => 24, 'position' => 1, 'action' => 'Enter \'invalid-email-format\' in email field', 'expected_result' => 'System accepts input initially', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26'],
            ['id' => 81, 'test_case_id' => 24, 'position' => 2, 'action' => 'Click Submit button', 'expected_result' => 'System displays error \'Invalid email format\'', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26'],
            ['id' => 82, 'test_case_id' => 25, 'position' => 1, 'action' => 'Enter \'ABC12345\' in Whatsapp number field', 'expected_result' => 'System prevents non-numeric input or displays error \'Numbers only allowed\'', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26'],
            ['id' => 83, 'test_case_id' => 26, 'position' => 1, 'action' => 'Enter \'123\' in kode pos field', 'expected_result' => 'System displays error \'Kode pos must be 5 digits\'', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26'],
            ['id' => 84, 'test_case_id' => 26, 'position' => 2, 'action' => 'Enter \'123456\' in kode pos field', 'expected_result' => 'System limits input to 5 characters or displays error \'Kode pos must be 5 digits\'', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26'],
            ['id' => 85, 'test_case_id' => 27, 'position' => 1, 'action' => 'Navigate to Delivery Form page', 'expected_result' => 'Subtotal displays \'100,000\'', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26'],
            ['id' => 86, 'test_case_id' => 27, 'position' => 2, 'action' => 'Fill in delivery address details', 'expected_result' => 'Delivery Cost is calculated and displayed as \'15,000\'', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26'],
            ['id' => 87, 'test_case_id' => 27, 'position' => 3, 'action' => 'Observe Total amount', 'expected_result' => 'Total displays \'115,000\' (Subtotal + Delivery Cost)', 'created_at' => '2026-07-01 08:17:26', 'updated_at' => '2026-07-01 08:17:26'],
            ['id' => 88, 'test_case_id' => 28, 'position' => 1, 'action' => 'Change \'kota\' to a different city with a different shipping rate', 'expected_result' => 'Delivery Cost updates immediately to reflect the new rate', 'created_at' => '2026-07-01 08:17:27', 'updated_at' => '2026-07-01 08:17:27'],
            ['id' => 89, 'test_case_id' => 28, 'position' => 2, 'action' => 'Observe Total amount', 'expected_result' => 'Total recalculates correctly based on updated Delivery Cost', 'created_at' => '2026-07-01 08:17:27', 'updated_at' => '2026-07-01 08:17:27'],
        ];
    }

    /**
     * @return list<array<string, int|string>>
     */
    private function testCaseTestPlans(): array
    {
        return [
            ['id' => 1, 'test_plan_id' => 1, 'test_case_id' => 3, 'created_at' => '2026-07-01 08:45:08', 'updated_at' => '2026-07-01 08:45:08'],
            ['id' => 2, 'test_plan_id' => 1, 'test_case_id' => 4, 'created_at' => '2026-07-01 08:45:08', 'updated_at' => '2026-07-01 08:45:08'],
            ['id' => 3, 'test_plan_id' => 1, 'test_case_id' => 5, 'created_at' => '2026-07-01 08:45:08', 'updated_at' => '2026-07-01 08:45:08'],
            ['id' => 4, 'test_plan_id' => 1, 'test_case_id' => 6, 'created_at' => '2026-07-01 08:45:08', 'updated_at' => '2026-07-01 08:45:08'],
            ['id' => 5, 'test_plan_id' => 1, 'test_case_id' => 7, 'created_at' => '2026-07-01 08:45:08', 'updated_at' => '2026-07-01 08:45:08'],
            ['id' => 6, 'test_plan_id' => 1, 'test_case_id' => 8, 'created_at' => '2026-07-01 08:45:08', 'updated_at' => '2026-07-01 08:45:08'],
            ['id' => 7, 'test_plan_id' => 1, 'test_case_id' => 9, 'created_at' => '2026-07-01 08:45:15', 'updated_at' => '2026-07-01 08:45:15'],
            ['id' => 8, 'test_plan_id' => 1, 'test_case_id' => 10, 'created_at' => '2026-07-01 08:45:15', 'updated_at' => '2026-07-01 08:45:15'],
            ['id' => 9, 'test_plan_id' => 1, 'test_case_id' => 11, 'created_at' => '2026-07-01 08:45:15', 'updated_at' => '2026-07-01 08:45:15'],
            ['id' => 10, 'test_plan_id' => 1, 'test_case_id' => 12, 'created_at' => '2026-07-01 08:45:15', 'updated_at' => '2026-07-01 08:45:15'],
            ['id' => 11, 'test_plan_id' => 1, 'test_case_id' => 13, 'created_at' => '2026-07-01 08:45:15', 'updated_at' => '2026-07-01 08:45:15'],
            ['id' => 12, 'test_plan_id' => 2, 'test_case_id' => 20, 'created_at' => '2026-07-01 08:45:47', 'updated_at' => '2026-07-01 08:45:47'],
            ['id' => 13, 'test_plan_id' => 2, 'test_case_id' => 21, 'created_at' => '2026-07-01 08:45:47', 'updated_at' => '2026-07-01 08:45:47'],
            ['id' => 14, 'test_plan_id' => 2, 'test_case_id' => 14, 'created_at' => '2026-07-01 08:45:47', 'updated_at' => '2026-07-01 08:45:47'],
            ['id' => 15, 'test_plan_id' => 2, 'test_case_id' => 15, 'created_at' => '2026-07-01 08:45:47', 'updated_at' => '2026-07-01 08:45:47'],
            ['id' => 16, 'test_plan_id' => 2, 'test_case_id' => 16, 'created_at' => '2026-07-01 08:45:47', 'updated_at' => '2026-07-01 08:45:47'],
            ['id' => 17, 'test_plan_id' => 2, 'test_case_id' => 17, 'created_at' => '2026-07-01 08:45:47', 'updated_at' => '2026-07-01 08:45:47'],
            ['id' => 18, 'test_plan_id' => 2, 'test_case_id' => 18, 'created_at' => '2026-07-01 08:45:47', 'updated_at' => '2026-07-01 08:45:47'],
            ['id' => 19, 'test_plan_id' => 2, 'test_case_id' => 19, 'created_at' => '2026-07-01 08:45:47', 'updated_at' => '2026-07-01 08:45:47'],
            ['id' => 20, 'test_plan_id' => 3, 'test_case_id' => 28, 'created_at' => '2026-07-01 08:47:37', 'updated_at' => '2026-07-01 08:47:37'],
            ['id' => 21, 'test_plan_id' => 3, 'test_case_id' => 22, 'created_at' => '2026-07-01 08:47:37', 'updated_at' => '2026-07-01 08:47:37'],
            ['id' => 22, 'test_plan_id' => 3, 'test_case_id' => 23, 'created_at' => '2026-07-01 08:47:37', 'updated_at' => '2026-07-01 08:47:37'],
            ['id' => 23, 'test_plan_id' => 3, 'test_case_id' => 24, 'created_at' => '2026-07-01 08:47:37', 'updated_at' => '2026-07-01 08:47:37'],
            ['id' => 24, 'test_plan_id' => 3, 'test_case_id' => 25, 'created_at' => '2026-07-01 08:47:37', 'updated_at' => '2026-07-01 08:47:37'],
            ['id' => 25, 'test_plan_id' => 3, 'test_case_id' => 26, 'created_at' => '2026-07-01 08:47:37', 'updated_at' => '2026-07-01 08:47:37'],
            ['id' => 26, 'test_plan_id' => 3, 'test_case_id' => 27, 'created_at' => '2026-07-01 08:47:37', 'updated_at' => '2026-07-01 08:47:37'],
        ];
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    private function testRuns(): array
    {
        return [
            ['id' => 1, 'test_plan_id' => 1, 'name' => 'Test Run 2026/07/01/001 - Login & Change Password', 'environment' => 'Development', 'priority' => 'Medium', 'description' => null, 'tester_name' => 'Jody Maulana', 'status' => 'In Progress', 'execution_started_at' => '2026-07-01 09:08:04', 'execution_completed_at' => null, 'created_at' => '2026-07-01 08:49:20', 'updated_at' => '2026-07-01 09:08:04', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 2, 'test_plan_id' => 2, 'name' => 'Test Run 2026/07/01/001 - Shopping Cart', 'environment' => 'Development', 'priority' => 'Medium', 'description' => null, 'tester_name' => 'Toto Suharinyanto', 'status' => 'In Progress', 'execution_started_at' => '2026-07-01 09:08:03', 'execution_completed_at' => null, 'created_at' => '2026-07-01 08:52:28', 'updated_at' => '2026-07-01 09:08:03', 'deleted_at' => null, 'api_version' => 1],
            ['id' => 3, 'test_plan_id' => 3, 'name' => 'Test Run 2026/07/01/001 - Delivery Form', 'environment' => 'Development', 'priority' => 'Medium', 'description' => null, 'tester_name' => 'Abdul Aziz', 'status' => 'In Progress', 'execution_started_at' => '2026-07-01 09:08:02', 'execution_completed_at' => null, 'created_at' => '2026-07-01 09:00:26', 'updated_at' => '2026-07-01 09:08:02', 'deleted_at' => null, 'api_version' => 1],
        ];
    }

    /**
     * @return list<array<string, int|string>>
     */
    private function testCaseTestRuns(): array
    {
        return [
            ['id' => 1, 'test_run_id' => 1, 'test_case_id' => 9, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 08:49:20', 'updated_at' => '2026-07-01 08:49:20'],
            ['id' => 2, 'test_run_id' => 1, 'test_case_id' => 10, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 08:49:20', 'updated_at' => '2026-07-01 08:49:20'],
            ['id' => 3, 'test_run_id' => 1, 'test_case_id' => 11, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 08:49:20', 'updated_at' => '2026-07-01 08:49:20'],
            ['id' => 4, 'test_run_id' => 1, 'test_case_id' => 12, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 08:49:20', 'updated_at' => '2026-07-01 08:49:20'],
            ['id' => 5, 'test_run_id' => 1, 'test_case_id' => 13, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 08:49:20', 'updated_at' => '2026-07-01 08:49:20'],
            ['id' => 6, 'test_run_id' => 1, 'test_case_id' => 3, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 08:49:20', 'updated_at' => '2026-07-01 08:49:20'],
            ['id' => 7, 'test_run_id' => 1, 'test_case_id' => 4, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 08:49:20', 'updated_at' => '2026-07-01 08:49:20'],
            ['id' => 8, 'test_run_id' => 1, 'test_case_id' => 5, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 08:49:20', 'updated_at' => '2026-07-01 08:49:20'],
            ['id' => 9, 'test_run_id' => 1, 'test_case_id' => 6, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 08:49:20', 'updated_at' => '2026-07-01 08:49:20'],
            ['id' => 10, 'test_run_id' => 1, 'test_case_id' => 7, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 08:49:20', 'updated_at' => '2026-07-01 08:49:20'],
            ['id' => 11, 'test_run_id' => 1, 'test_case_id' => 8, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 08:49:20', 'updated_at' => '2026-07-01 08:49:20'],
            ['id' => 23, 'test_run_id' => 3, 'test_case_id' => 22, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 09:00:26', 'updated_at' => '2026-07-01 09:00:26'],
            ['id' => 24, 'test_run_id' => 3, 'test_case_id' => 23, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 09:00:26', 'updated_at' => '2026-07-01 09:00:26'],
            ['id' => 25, 'test_run_id' => 3, 'test_case_id' => 24, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 09:00:26', 'updated_at' => '2026-07-01 09:00:26'],
            ['id' => 26, 'test_run_id' => 3, 'test_case_id' => 25, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 09:00:26', 'updated_at' => '2026-07-01 09:00:26'],
            ['id' => 27, 'test_run_id' => 3, 'test_case_id' => 26, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 09:00:26', 'updated_at' => '2026-07-01 09:00:26'],
            ['id' => 28, 'test_run_id' => 3, 'test_case_id' => 27, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 09:00:26', 'updated_at' => '2026-07-01 09:00:26'],
            ['id' => 29, 'test_run_id' => 3, 'test_case_id' => 28, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 09:00:26', 'updated_at' => '2026-07-01 09:00:26'],
            ['id' => 30, 'test_run_id' => 2, 'test_case_id' => 14, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 09:00:54', 'updated_at' => '2026-07-01 09:00:54'],
            ['id' => 31, 'test_run_id' => 2, 'test_case_id' => 15, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 09:00:54', 'updated_at' => '2026-07-01 09:00:54'],
            ['id' => 32, 'test_run_id' => 2, 'test_case_id' => 16, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 09:00:54', 'updated_at' => '2026-07-01 09:00:54'],
            ['id' => 33, 'test_run_id' => 2, 'test_case_id' => 17, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 09:00:54', 'updated_at' => '2026-07-01 09:00:54'],
            ['id' => 34, 'test_run_id' => 2, 'test_case_id' => 18, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 09:00:54', 'updated_at' => '2026-07-01 09:00:54'],
            ['id' => 35, 'test_run_id' => 2, 'test_case_id' => 19, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 09:00:54', 'updated_at' => '2026-07-01 09:00:54'],
            ['id' => 36, 'test_run_id' => 2, 'test_case_id' => 20, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 09:00:54', 'updated_at' => '2026-07-01 09:00:54'],
            ['id' => 37, 'test_run_id' => 2, 'test_case_id' => 21, 'execution_status' => 'Not Run', 'created_at' => '2026-07-01 09:00:54', 'updated_at' => '2026-07-01 09:00:54'],
        ];
    }

    /**
     * @return list<array<string, int|string|null>>
     */
    private function issues(): array
    {
        return [
            ['id' => 1, 'project_id' => 1, 'test_plan_id' => 1, 'test_run_id' => 1, 'title' => 'Inactive Kode ID allows successful login to the application', 'description' => "Steps to reproduce:\r\n1. Navigate to the Login page.\r\n2. Enter a 'Kode ID' that is currently set to 'Inactive' status.\r\n3. Enter the correct valid password associated with that account.\r\n4. Click 'Login' button.", 'expected_result' => 'The system must block access for inactive accounts and display a specific error message: \'Kode ID is inactive\'.', 'actual_result' => 'The system allows the user to successfully authenticate and redirects them to the dashboard despite the \'Kode ID\' being marked as inactive in the database.', 'priority' => 'High', 'severity' => 'Critical', 'status' => 'Open', 'reporter' => 'QA Engineer', 'assignee' => 'Zamzam', 'environment' => 'SIT', 'defect_type' => 'Functional', 'closed_at' => null, 'created_at' => '2026-07-01 09:07:07', 'updated_at' => '2026-07-01 09:07:07', 'deleted_at' => null, 'api_version' => 1],
        ];
    }

    /**
     * @return list<array<string, int|string>>
     */
    private function issueAttachments(): array
    {
        return [
            ['id' => 1, 'issue_id' => 1, 'disk' => 'local', 'path' => 'issues/1/1/rrcBEzEWBYRZ2qfzfzxyjJ157yfXrPwPcBnsMYmy.png', 'original_name' => 'Screenshot 2026-07-01 085427.png', 'mime_type' => 'image/png', 'size' => 126667, 'created_at' => '2026-07-01 09:07:09', 'updated_at' => '2026-07-01 09:07:09'],
        ];
    }
}
