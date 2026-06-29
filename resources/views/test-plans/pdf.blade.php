<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $testPlan->name }} - Test Plan</title>
    <style>
        @page {
            margin: 34px 36px 52px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #172033;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9px;
            line-height: 1.55;
        }

        h1, h2, h3, p {
            margin-top: 0;
        }

        .document-header {
            border-bottom: 3px solid #0b63ce;
            margin-bottom: 24px;
            padding-bottom: 16px;
        }

        .brand-row,
        .detail-table,
        .scenario-table {
            border-collapse: collapse;
            width: 100%;
        }

        .brand-row td {
            padding: 0;
            vertical-align: top;
        }

        .brand {
            color: #0b63ce;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .document-label {
            color: #64748b;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.7px;
            text-align: right;
            text-transform: uppercase;
        }

        h1 {
            color: #082f5b;
            font-size: 24px;
            line-height: 1.2;
            margin: 14px 0 6px;
        }

        .subtitle {
            color: #526176;
            font-size: 10px;
            margin-bottom: 0;
        }

        .detail-table {
            border: 1px solid #cfd9e6;
            margin-bottom: 24px;
            table-layout: fixed;
            width: 100%;
        }

        .detail-table td {
            border-bottom: 1px solid #dce4ee;
            padding: 12px 14px;
            vertical-align: top;
        }

        .detail-table tr:last-child td {
            border-bottom: 0;
        }

        .detail-label {
            background: #082f5b;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            width: 31%;
        }

        .detail-value {
            background: #ffffff;
            color: #354257;
            font-size: 10px;
            font-weight: 400;
            overflow-wrap: break-word;
            white-space: pre-line;
        }

        .section {
            margin-bottom: 28px;
            padding: 6px 0 10px;
        }

        .scenario-section {
            page-break-before: auto;
            padding-top: 0;
        }

        .section-heading {
            border-bottom: 1px solid #dce4ee;
            margin-bottom: 14px;
            padding-bottom: 8px;
        }

        .section-heading h2 {
            color: #082f5b;
            font-size: 14px;
            line-height: 1.3;
            margin: 0;
        }

        .section-number {
            background: #0b63ce;
            color: #ffffff;
            display: inline-block;
            font-size: 9px;
            margin-right: 7px;
            padding: 3px 7px;
            vertical-align: 2px;
        }

        .narrative {
            color: #354257;
            font-size: 9.5px;
            line-height: 1.7;
            margin-bottom: 0;
            padding: 8px 2px 2px 0;
            white-space: pre-line;
        }

        .section-table {
            margin-top: 16px;
        }

        .module-block {
            margin-bottom: 28px;
        }

        .module-block + .module-block {
            page-break-before: auto;
        }

        .module-title {
            background: #eef5fc;
            border-left: 4px solid #0b63ce;
            color: #082f5b;
            font-size: 11px;
            margin: 0;
            padding: 10px 12px;
            page-break-after: avoid;
        }

        .preconditions {
            background: #f8fafc;
            border: 1px solid #dce4ee;
            border-top: 0;
            color: #475569;
            padding: 10px 12px;
            page-break-after: avoid;
        }

        .preconditions strong {
            color: #172033;
        }

        .preconditions ul {
            margin: 4px 0 0 16px;
            padding: 0;
        }

        .scenario-table {
            border: 1px solid #cfd9e6;
            table-layout: fixed;
            page-break-before: avoid;
        }

        .scenario-table thead {
            display: table-header-group;
            page-break-after: avoid;
        }

        .scenario-table tr {
            page-break-inside: avoid;
        }

        .scenario-table th {
            background: #082f5b;
            border: 1px solid #284d72;
            color: #ffffff;
            font-size: 7px;
            font-weight: 700;
            letter-spacing: 0.2px;
            padding: 7px 5px;
            text-align: left;
            text-transform: uppercase;
            vertical-align: middle;
        }

        .scenario-table td {
            border: 1px solid #dce4ee;
            color: #354257;
            font-size: 7.5px;
            line-height: 1.45;
            overflow-wrap: break-word;
            padding: 7px 5px;
            vertical-align: top;
        }

        .scenario-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .case-id {
            color: #0b63ce;
            font-weight: 700;
        }

        .case-title {
            color: #172033;
            font-weight: 700;
        }

        .empty-state {
            background: #f8fafc;
            border: 1px dashed #b9c6d6;
            color: #64748b;
            padding: 28px;
            text-align: center;
        }

        .document-footer {
            bottom: -35px;
            color: #7b8798;
            font-size: 7px;
            left: 0;
            position: fixed;
        }
    </style>
</head>
<body>
    <footer class="document-footer">
        Generated by Testify on {{ $generatedAt->format('d M Y, H:i') }}
    </footer>

    <header class="document-header">
        <table class="brand-row">
            <tr>
                <td class="brand">Testify</td>
                <td class="document-label">Quality Assurance Test Plan</td>
            </tr>
        </table>
        <h1>{{ $testPlan->name }}</h1>
        <p class="subtitle">{{ $testPlan->project?->project_name }} - Test planning and scenario specification</p>
    </header>

    <table class="detail-table">
        <colgroup>
            <col style="width: 31%">
            <col style="width: 69%">
        </colgroup>
        <tr>
            <td class="detail-label">Project</td>
            <td class="detail-value">{{ $testPlan->project?->project_name ?? 'Not set' }}</td>
        </tr>
        <tr>
            <td class="detail-label">Status</td>
            <td class="detail-value">{{ $testPlan->status }}</td>
        </tr>
        <tr>
            <td class="detail-label">Priority</td>
            <td class="detail-value">{{ $testPlan->priority }}</td>
        </tr>
        <tr>
            <td class="detail-label">Release Version</td>
            <td class="detail-value">{{ $testPlan->release_version ?? 'Not set' }}</td>
        </tr>
        <tr>
            <td class="detail-label">Tester</td>
            <td class="detail-value">{{ $testPlan->tester ?? 'Unassigned' }}</td>
        </tr>
        <tr>
            <td class="detail-label">Start Date</td>
            <td class="detail-value">{{ $testPlan->start_date?->format('d M Y') ?? 'Not set' }}</td>
        </tr>
        <tr>
            <td class="detail-label">Due Date</td>
            <td class="detail-value">{{ $testPlan->due_date?->format('d M Y') ?? 'Not set' }}</td>
        </tr>
        <tr>
            <td class="detail-label">Assigned Cases</td>
            <td class="detail-value">{{ $testPlan->testCases->count() }}</td>
        </tr>
    </table>

    @php
        $sectionNumber = 1;
    @endphp

    @if (filled($testPlan->description))
        <section class="section">
            <div class="section-heading">
                <h2><span class="section-number">{{ $sectionNumber++ }}</span>Overview</h2>
            </div>
            <p class="narrative">{{ $testPlan->description }}</p>
        </section>
    @endif

    @if (filled($testPlan->scope))
        <section class="section">
            <div class="section-heading">
                <h2><span class="section-number">{{ $sectionNumber++ }}</span>Scope of Testing</h2>
            </div>
            <table class="detail-table section-table">
                <colgroup>
                    <col style="width: 31%">
                    <col style="width: 69%">
                </colgroup>
                <tr>
                    <td class="detail-label">Scope</td>
                    <td class="detail-value">{{ $testPlan->scope }}</td>
                </tr>
            </table>
        </section>
    @endif

    @if (filled($testPlan->strategy))
        <section class="section">
            <div class="section-heading">
                <h2><span class="section-number">{{ $sectionNumber++ }}</span>Test Strategy</h2>
            </div>
            <p class="narrative">{{ $testPlan->strategy }}</p>
        </section>
    @endif

    @if (filled($testPlan->criteria))
        <section class="section">
            <div class="section-heading">
                <h2><span class="section-number">{{ $sectionNumber++ }}</span>Entry and Exit Criteria</h2>
            </div>
            <table class="detail-table section-table">
                <colgroup>
                    <col style="width: 31%">
                    <col style="width: 69%">
                </colgroup>
                <tr>
                    <td class="detail-label">Criteria</td>
                    <td class="detail-value">{{ $testPlan->criteria }}</td>
                </tr>
            </table>
        </section>
    @endif

    <section class="section scenario-section">
        <div class="section-heading">
            <h2><span class="section-number">{{ $sectionNumber }}</span>Test Scenarios</h2>
        </div>

        @forelse ($testCasesByModule as $moduleName => $testCases)
            @php
                $preconditions = $testCases
                    ->pluck('preconditions')
                    ->filter()
                    ->unique()
                    ->values();
            @endphp

            <div class="module-block">
                <h3 class="module-title">{{ $moduleName }}</h3>

                @if ($preconditions->isNotEmpty())
                    <div class="preconditions">
                        <strong>Preconditions</strong>
                        <ul>
                            @foreach ($preconditions as $precondition)
                                <li>{{ $precondition }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <table class="scenario-table">
                    <colgroup>
                        <col style="width: 9%">
                        <col style="width: 13%">
                        <col style="width: 24%">
                        <col style="width: 11%">
                        <col style="width: 10%">
                        <col style="width: 33%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Test ID</th>
                            <th>Feature / Module</th>
                            <th>Test Case</th>
                            <th>Test Type</th>
                            <th>Behavior</th>
                            <th>Expected Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($testCases as $testCase)
                            @php
                                $lastStep = $testCase->steps->sortBy('position')->last();
                            @endphp
                            <tr>
                                <td><span class="case-id">{{ $testCase->case_key }}</span></td>
                                <td>{{ $moduleName }}</td>
                                <td><span class="case-title">{{ $testCase->title }}</span></td>
                                <td>{{ $testCase->test_type }}</td>
                                <td>{{ $testCase->test_behavior }}</td>
                                <td>
                                    @if ($lastStep)
                                        {{ $lastStep->expected_result }}
                                    @else
                                        No expected result has been documented.
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <div class="empty-state">
                No test cases have been assigned to this test plan.
            </div>
        @endforelse
    </section>
</body>
</html>
