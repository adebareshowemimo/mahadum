<?php

namespace App\Console\Commands;

use App\Models\SchoolClass;
use App\Models\SeatAllocation;
use App\Models\TeacherCompensationEntry;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Monthly teacher compensation accrual, run on the 1st for the month that just
 * ended (compensation for service already rendered). Per teacher, per
 * organization they teach in: count distinct enrolled students across their
 * classes in that org, but only if the org currently holds an active (paid,
 * unexpired) seat allocation — school students are billed via seats, not
 * personal subscriptions, so seat activity is the "paying" signal. Amount =
 * count × the admin-configured rate (Settings `teacher_compensation.rate_per_student_minor`).
 * Idempotent per (teacher, org, period) via upsert.
 */
class AccrueTeacherCompensation extends Command
{
    protected $signature = 'compensation:accrue-teachers';

    protected $description = 'Accrue monthly per-student compensation for teachers with actively-seated classes';

    public function handle(Settings $settings): int
    {
        $rate = (int) $settings->get('teacher_compensation.rate_per_student_minor', 0);
        if ($rate <= 0) {
            $this->info('Compensation rate is 0 — nothing to accrue.');

            return self::SUCCESS;
        }

        $period = Carbon::now()->subMonthNoOverflow()->format('Y-m');
        $accrued = 0;

        $teacherIds = User::role('teacher')->pluck('id');

        foreach ($teacherIds as $teacherId) {
            $classesByOrg = SchoolClass::where('teacher_user_id', $teacherId)
                ->withoutTenancy()
                ->with('enrollments')
                ->get()
                ->groupBy('organization_id');

            foreach ($classesByOrg as $organizationId => $classes) {
                if (! $this->hasActiveSeats((int) $organizationId)) {
                    continue;
                }

                $payingCount = $classes->flatMap->enrollments->pluck('learner_profile_id')->unique()->count();
                if ($payingCount === 0) {
                    continue;
                }

                TeacherCompensationEntry::withoutTenancy()->updateOrCreate(
                    ['teacher_user_id' => $teacherId, 'organization_id' => $organizationId, 'period' => $period],
                    ['paying_student_count' => $payingCount, 'rate_minor' => $rate, 'amount_minor' => $payingCount * $rate],
                );
                $accrued++;
            }
        }

        $this->info("Accrued compensation for {$accrued} teacher/organization pair(s) for {$period}.");

        return self::SUCCESS;
    }

    private function hasActiveSeats(int $organizationId): bool
    {
        return SeatAllocation::withoutTenancy()
            ->where('organization_id', $organizationId)
            ->where('total_purchased', '>', 0)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }
}
