<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class GenerateWorkSchedules extends Command
{
    protected $signature = 'schedule:generate {--date=}';
    protected $description = 'Generate work schedules for drivers from vehicle assignments (Spec 2.4)';

    public function handle(): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date'))->toDateString() : now()->addDays(1)->toDateString();
        $this->info("Generating work schedules for date: {$date}");

        $companies = DB::table('companies')->pluck('id');

        foreach ($companies as $companyId) {
            $assignments = DB::table('vehicle_assignments')
                ->where('company_id', $companyId)
                ->where('from_date', '<=', $date)
                ->where(function ($q) use ($date) {
                    $q->whereNull('to_date')
                        ->orWhere('to_date', '>=', $date);
                })
                ->get();

            foreach ($assignments as $assignment) {
                // Check if schedule already exists
                $exists = DB::table('driver_work_schedules')
                    ->where('driver_id', $assignment->driver_id)
                    ->where('company_id', $companyId)
                    ->whereDate('scheduled_date', $date)
                    ->exists();

                if (!$exists) {
                    DB::table('driver_work_schedules')->insert([
                        'company_id' => $companyId,
                        'driver_id' => $assignment->driver_id,
                        'scheduled_date' => $date,
                        'status' => 'scheduled',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->info('✓ Work schedules generated successfully');

        return self::SUCCESS;
    }
}
