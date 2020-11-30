<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Imports\CrmPartnersImport;

class DataImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rcuk:import
                {name : The data type to import (crm_partners)}
                {file : The path to the file containing the data to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import RCUK data from CSV files';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $datatype = $this->argument('name');
        $datafile = $this->argument('file');

        if (!file_exists($datafile)) {
            $this->error("{$datafile} does not exist");
            return;
        }

        if (!is_readable($datafile)) {
            $this->error("{$datafile} can't be read");
            return;
        }

        $importObject = null;
        switch ($datatype) {
            case 'crm_partners':
                $importObject = new CrmPartnersImport($datafile);
                $importObject->import();
                break;

            default:
                $this->error("{$datatype} type not recognised");
                return;
        }

        $this->comment("\n\n");

        if (count($importObject->getWarnings())) {
            $this->comment(__(
                'Imports.warnings_raised',
                [
                    'count' => count($importObject->getWarnings()),
                ]
            ));
            foreach ($importObject->getWarnings() as $warning) {
                $this->info($warning);
            }
            $this->comment("\n");
        }

        foreach ($importObject->getRecordsCreatedSummary() as $type => $count) {
            $this->info(__(
                'Imports.records_created',
                [
                    'type' => $type,
                    'count' => $count,
                ]
            ));
        }
    }
}
