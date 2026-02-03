<?php

namespace App\Commands;

use GrantHolle\PowerSchool\Api\Facades\PowerSchool;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class GenerateCsv extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'make:csv';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Generates a csv file for lunch balances';

    protected $outputPath;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->outputPath = storage_path('app/uploads/import.csv');

        if (!file_exists($this->outputPath)) {
            mkdir(dirname($this->outputPath), 0755, true);
        }

        $resource = fopen($this->outputPath, 'w');
        $totalWritten = 0;
        $totalStudents = 0;
        $skippedNoExtension = 0;

        $schools = [
            6, // TIS
            108, // CDIS
            353, // CDES
            153, // ISQ
            106, // ISW
            303, // YHIS
            104, // WYIS
        ];

        foreach ($schools as $school) {
            $this->info("Processing school {$school}");

            $page = 1;
            $continue = true;
            $schoolWritten = 0;

            while ($continue) {
                $results = $this->getStudents($school, $page);

                // Debug: dump raw response for first page of first school
                if ($school === 6 && $page === 1) {
                    $this->warn("Raw API response for school 6, page 1:");
                    $this->line(json_encode($results, JSON_PRETTY_PRINT));
                }

                $page++;

                $students = $results->data ?? $results->students->student ?? [];

                // Normalize to array if single result
                if (!is_array($students)) {
                    $students = [$students];
                }

                $this->line("  Page " . ($page - 1) . ": " . count($students) . " students returned");

                if (empty($students) || $students[0] === null) {
                    $this->line("  No more students, moving to next school");
                    break;
                }

                $totalStudents += count($students);

                foreach ($students as $student) {
                    if (!isset($student->_extension_data)) {
                        $skippedNoExtension++;
                        continue;
                    }

                    $fields = [
                        $student->local_id, // student_number
                        $student->_extension_data->_table_extension->_field[0]->value, // balance1
                    ];

                    fputcsv($resource, $fields);
                    $totalWritten++;
                    $schoolWritten++;
                }
            }

            $this->info("  School {$school} complete: {$schoolWritten} rows written");
        }

        fclose($resource);

        $this->info("=== Summary ===");
        $this->info("Total students fetched: {$totalStudents}");
        $this->info("Skipped (no extension data): {$skippedNoExtension}");
        $this->info("Total rows written to CSV: {$totalWritten}");
        $this->info("Output file: {$this->outputPath}");
        $this->info("Done!");

        return 0;
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
         $schedule->command(static::class)->dailyAt('13:15');
    }

    protected function getStudents($school, $page)
    {
        return PowerSchool::endpoint("/ws/v1/school/{$school}/student")
            ->extensions('u_powermenu_plus_extension')
            ->q('school_enrollment.enroll_status==A')
            ->pageSize(100)
            ->page($page)
            ->get();
    }

    protected function getSchools()
    {
        return PowerSchool::endpoint('/ws/v1/district/school')
            ->get();
    }

    protected function getBalances($page)
    {
        return PowerSchool::table('u_powermenu_student_balance')
            ->pageSize(100)
            ->page($page)
            ->get();
    }
}
