<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $payload['options']['project']['name'] }} - QA Report</title>
    <style>
        @page { margin: 42px 42px 58px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #10243e; font-family: "DejaVu Sans", sans-serif; font-size: 10px; line-height: 1.55; }
        h1, h2, h3, p { margin: 0; }
        h1 { color: #071a2f; font-size: 23px; line-height: 1.2; }
        h2 { color: #071a2f; font-size: 15px; line-height: 1.3; }
        h3 { color: #071a2f; font-size: 11px; line-height: 1.35; }
        .header { border-bottom: 2px solid #0b5cab; padding-bottom: 18px; }
        .brand { color: #0b5cab; font-size: 9px; font-weight: 700; letter-spacing: 1.6px; text-transform: uppercase; }
        .title { margin-top: 7px; }
        .meta { color: #64748b; margin-top: 7px; }
        .meta-table { border-collapse: collapse; margin-top: 13px; width: 100%; }
        .meta-table td { border: 1px solid #dbe3ec; padding: 7px 9px; width: 33.333%; }
        .label { color: #64748b; display: block; font-size: 7.5px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase; }
        .value { color: #10243e; display: block; font-size: 9.5px; font-weight: 700; margin-top: 2px; }
        .section { margin-top: 20px; }
        .section-header { border-bottom: 1px solid #dbe3ec; margin-bottom: 10px; padding-bottom: 6px; }
        .section-kicker { color: #0b5cab; font-size: 7.5px; font-weight: 700; letter-spacing: 1px; margin-bottom: 2px; text-transform: uppercase; }
        .summary-box { background: #f1f6fb; border-left: 4px solid #0b5cab; padding: 13px 15px; }
        .analysis-table, .metric-table, .data-table { border-collapse: separate; border-spacing: 0; width: 100%; }
        .analysis-table td { padding: 0 5px 10px; vertical-align: top; width: 50%; }
        .analysis-table td:first-child { padding-left: 0; }
        .analysis-table td:last-child { padding-right: 0; }
        .analysis-card { border: 1px solid #dbe3ec; padding: 11px 12px; }
        .analysis-card ul, .action-list { margin: 8px 0 0; padding-left: 17px; }
        .analysis-card li, .action-list li { margin-bottom: 6px; }
        .risk-card { border: 1px solid #fecaca; background: #fff7f7; }
        .action-card { border: 1px solid #bae6d3; background: #f2fbf7; }
        .metric-table { border-spacing: 8px 0; margin-left: -8px; width: calc(100% + 16px); }
        .metric-table td { border: 1px solid #dbe3ec; padding: 11px 10px; vertical-align: top; width: 25%; }
        .metric-value { color: #071a2f; display: block; font-size: 20px; font-weight: 700; margin-top: 3px; }
        .data-table { border: 1px solid #dbe3ec; }
        .data-table thead { display: table-header-group; }
        .data-table th { background: #edf3f8; color: #52657d; font-size: 7.5px; letter-spacing: 0.7px; padding: 7px 8px; text-align: left; text-transform: uppercase; }
        .data-table td { border-top: 1px solid #e7edf3; padding: 7px 8px; vertical-align: top; }
        .data-table tr { page-break-inside: avoid; }
        .number { text-align: right; }
        .muted { color: #64748b; }
        .empty { color: #64748b; font-style: italic; padding: 10px 0; }
        .badge { border-radius: 10px; display: inline-block; font-size: 8px; font-weight: 700; padding: 2px 7px; }
        .badge-low { background: #fef9c3; color: #854d0e; }
        .badge-medium { background: #ffedd5; color: #9a3412; }
        .badge-high, .badge-critical { background: #fee2e2; color: #991b1b; }
        .notes { background: #fffbea; border: 1px solid #fde68a; padding: 10px 12px; }
        .notes ul { margin: 5px 0 0; padding-left: 17px; }
        .footer { bottom: -39px; color: #64748b; font-size: 7.5px; left: 0; position: fixed; right: 0; }
        .footer-line { border-top: 1px solid #dbe3ec; padding-top: 7px; }
        .avoid-break { page-break-inside: avoid; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <footer class="footer">
        <div class="footer-line">
            Testify AI Quality Report | Generated {{ $generatedAt->format('M j, Y H:i') }}
        </div>
    </footer>

    <header class="header">
        <div class="brand">Testify Quality Intelligence</div>
        <h1 class="title">{{ $payload['options']['project']['name'] }} - QA Report</h1>
        <p class="meta">AI-assisted analysis grounded in the selected QA report data.</p>

        <table class="meta-table">
            <tr>
                <td>
                    <span class="label">Analysis Scope</span>
                    <span class="value">{{ $scopeName }}</span>
                </td>
                <td>
                    <span class="label">Reporting Period</span>
                    <span class="value">{{ $payload['filters']['start_date'] }} to {{ $payload['filters']['end_date'] }}</span>
                </td>
                <td>
                    <span class="label">Grouping</span>
                    <span class="value">{{ ucfirst($payload['filters']['grouping']) }}</span>
                </td>
            </tr>
        </table>
    </header>

    <section class="section avoid-break">
        <div class="section-header">
            <div class="section-kicker">Leadership Brief</div>
            <h2>Executive Summary</h2>
        </div>
        <div class="summary-box">{{ $summary['executive_summary'] }}</div>
    </section>

    <section class="section">
        <div class="section-header">
            <div class="section-kicker">AI Analysis</div>
            <h2>Risk and Completion Guidance</h2>
        </div>
        <table class="analysis-table">
            <tr>
                <td>
                    <div class="analysis-card risk-card">
                        <h3>Ranked Quality Risks</h3>
                        @if ($summary['quality_risks'] !== [])
                            <ol class="action-list">
                                @foreach ($summary['quality_risks'] as $risk)
                                    <li>{{ $risk }}</li>
                                @endforeach
                            </ol>
                        @else
                            <p class="empty">No material quality risks were identified.</p>
                        @endif
                    </div>
                </td>
                <td>
                    <div class="analysis-card action-card">
                        <h3>Recommended Actions</h3>
                        @if ($summary['recommendations'] !== [])
                            <ol class="action-list">
                                @foreach ($summary['recommendations'] as $recommendation)
                                    <li>{{ $recommendation }}</li>
                                @endforeach
                            </ol>
                        @else
                            <p class="empty">No additional action was recommended.</p>
                        @endif
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="analysis-card">
                        <h3>Coverage Assessment</h3>
                        @if ($summary['coverage_notes'] !== [])
                            <ul>
                                @foreach ($summary['coverage_notes'] as $note)
                                    <li>{{ $note }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="empty">No coverage observations were returned.</p>
                        @endif
                    </div>
                </td>
                <td>
                    <div class="analysis-card">
                        <h3>Issue Analysis</h3>
                        @if ($summary['issue_analysis'] !== [])
                            <ul>
                                @foreach ($summary['issue_analysis'] as $analysis)
                                    <li>{{ $analysis }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="empty">No issue patterns were identified.</p>
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        <div class="analysis-card action-card avoid-break">
            <h3>Prioritized Next Steps</h3>
            @if ($summary['next_actions'] !== [])
                <ol class="action-list">
                    @foreach ($summary['next_actions'] as $action)
                        <li>{{ $action }}</li>
                    @endforeach
                </ol>
            @else
                <p class="empty">No additional next steps were returned.</p>
            @endif
        </div>
    </section>

    @if (in_array('metrics', $payload['filters']['sections'], true))
        <section class="section avoid-break">
            <div class="section-header">
                <div class="section-kicker">Source Data</div>
                <h2>Project Quality Snapshot</h2>
            </div>
            <table class="metric-table">
                <tr>
                    <td><span class="label">Test Cases</span><span class="metric-value">{{ number_format($payload['metrics']['totalTestCases']) }}</span></td>
                    <td><span class="label">Test Runs</span><span class="metric-value">{{ number_format($payload['metrics']['totalTestRuns']) }}</span></td>
                    <td><span class="label">Issues</span><span class="metric-value">{{ number_format($payload['metrics']['totalIssues']) }}</span></td>
                    <td><span class="label">Coverage</span><span class="metric-value">{{ number_format($payload['metrics']['executionCoverage'], 1) }}%</span></td>
                </tr>
            </table>
        </section>
    @endif

    @if (in_array('issue_distribution', $payload['filters']['sections'], true))
        <section class="section avoid-break">
            <div class="section-header"><h2>Issue Distribution</h2></div>
            <table class="data-table">
                <thead><tr><th>Severity</th><th class="number">Issues</th></tr></thead>
                <tbody>
                    @foreach ($payload['distribution'] as $item)
                        <tr>
                            <td><span class="badge badge-{{ strtolower($item['label']) }}">{{ $item['label'] }}</span></td>
                            <td class="number">{{ number_format($item['value']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    @if (in_array('result_summary', $payload['filters']['sections'], true))
        <section class="section avoid-break">
            <div class="section-header"><h2>Test Results Summary</h2></div>
            <table class="data-table">
                <thead><tr><th>Execution Status</th><th class="number">Cases</th><th class="number">Share</th></tr></thead>
                <tbody>
                    @foreach ($payload['results']['statuses'] as $status)
                        <tr><td>{{ $status['label'] }}</td><td class="number">{{ number_format($status['value']) }}</td><td class="number">{{ $status['percent'] }}%</td></tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    @if (in_array('issue_trend', $payload['filters']['sections'], true))
        <section class="section">
            <div class="section-header"><h2>Issue Trend</h2></div>
            <table class="data-table">
                <thead><tr><th>Period</th><th class="number">Issues</th></tr></thead>
                <tbody>
                    @forelse ($payload['trend'] as $point)
                        <tr><td>{{ $point['bucket'] }}</td><td class="number">{{ number_format($point['value']) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="empty">No issue activity in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @endif

    @if (in_array('team_workload', $payload['filters']['sections'], true))
        <section class="section avoid-break">
            <div class="section-header"><h2>Team Workload</h2></div>
            <table class="data-table">
                <thead><tr><th>Tester</th><th class="number">Active Runs</th><th class="number">Assigned Cases</th></tr></thead>
                <tbody>
                    @forelse ($payload['workload'] as $member)
                        <tr><td>{{ $member['name'] }}</td><td class="number">{{ $member['activeRuns'] }}</td><td class="number">{{ $member['assignedCases'] }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="empty">No active workload in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @endif

    @if (in_array('recent_executions', $payload['filters']['sections'], true))
        <section class="section">
            <div class="section-header"><h2>Recent Executions</h2></div>
            <table class="data-table">
                <thead><tr><th>Run</th><th>Test Plan</th><th>Tester</th><th>Result</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse ($payload['recentExecutions'] as $execution)
                        <tr>
                            <td>{{ $execution['name'] }}</td>
                            <td>{{ $execution['plan']['name'] ?? 'Unassigned' }}</td>
                            <td>{{ $execution['tester'] }}</td>
                            <td>{{ $execution['result'] }}</td>
                            <td>{{ $execution['date'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">No executions in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @endif

    @if ($warnings !== [])
        <section class="section avoid-break">
            <div class="notes">
                <h3>Generation Notes</h3>
                <ul>
                    @foreach ($warnings as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
</body>
</html>
