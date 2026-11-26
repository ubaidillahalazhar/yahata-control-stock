<?php

namespace App\Notifications;

use App\Models\DamageReport;

/** Laporan "count belum dibawa" sudah melewati batas waktu (DamageReport::COUNT_OVERDUE_HOURS). */
class DamageCountOverdue extends AdminAlert
{
    public function __construct(private readonly DamageReport $report) {}

    protected function kind(): string
    {
        return 'count_overdue';
    }

    protected function title(): string
    {
        return sprintf('Count belum diisi > %d jam', DamageReport::COUNT_OVERDUE_HOURS);
    }

    protected function body(): string
    {
        return sprintf(
            '%s · %s — oleh %s, dilaporkan %s',
            $this->report->report_no,
            $this->report->tool?->tools_name_yes ?? 'Tool #'.$this->report->tool_id,
            $this->report->operator_name,
            $this->report->reported_at->format('d/m/Y H:i'),
        );
    }

    protected function url(): string
    {
        return route('damage-reports.show', $this->report, absolute: false);
    }
}
