<?php

namespace App\Notifications;

use App\Models\DamageReport;

class DamageReportSubmitted extends AdminAlert
{
    public function __construct(private readonly DamageReport $report) {}

    protected function kind(): string
    {
        return 'damage_report';
    }

    protected function title(): string
    {
        return 'Laporan kerusakan '.$this->report->report_no;
    }

    protected function body(): string
    {
        $tool = $this->report->tool;

        return sprintf(
            '%s · %s · %s, qty %d — oleh %s',
            $tool?->tools_name_yes ?? 'Tool #'.$this->report->tool_id,
            $tool?->machine_name ?? '—',
            $this->report->damage_reason->label(),
            $this->report->qty_replaced,
            $this->report->operator_name,
        );
    }

    protected function url(): string
    {
        return route('damage-reports.show', $this->report, absolute: false);
    }
}
