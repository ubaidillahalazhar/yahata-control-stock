<?php

namespace App\Notifications;

use App\Models\DamageReport;

/** Counter mesin lebih kecil dari life sistem → kemungkinan Excel kelebihan input / salah baca counter. */
class DamageReportNeedsReview extends AdminAlert
{
    public function __construct(private readonly DamageReport $report) {}

    protected function kind(): string
    {
        return 'review';
    }

    protected function title(): string
    {
        return 'Perlu ditinjau: '.$this->report->report_no;
    }

    protected function body(): string
    {
        return sprintf(
            '%s · counter %s < sistem %s (selisih %s) — oleh %s',
            $this->report->tool?->tools_name_yes ?? 'Tool #'.$this->report->tool_id,
            number_format((int) $this->report->actual_shot_count, 0, ',', '.'),
            number_format((int) $this->report->system_shot_count, 0, ',', '.'),
            number_format((int) $this->report->shot_variance, 0, ',', '.'),
            $this->report->operator_name,
        );
    }

    protected function url(): string
    {
        return route('damage-reports.show', $this->report, absolute: false);
    }
}
