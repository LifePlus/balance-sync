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

        $schools = [
            6, // TIS
            108, // CDIS
            153, // ISQ
            106, // ISW
        ];

        foreach ($schools as $school) {
            $this->info("Processing school {$school}");

            $page = 1;
            $continue = true;

            while ($continue) {
                $results = $this->getStudents($school, $page++);
                $students = is_array(optional($results->students)->student)
                    ? $results->students->student
                    : [optional($results->students)->student];

                if ($students[0] === null) {
                    break;
                }

                foreach ($students as $student) {
                    if (!isset($student->_extension_data)) {
                        continue;
                    }

                    $fields = [
                        $student->local_id, // student_number
                        $student->_extension_data->_table_extension->_field[0]->value, // balance1
                    ];

                    fputcsv($resource, $fields);
                }
            }
        }

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
        // $schedule->command(static::class)->everyMinute();
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
