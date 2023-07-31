<?php

namespace App\Console\Commands;

use App\Services\DriversAssignService;
use App\Services\ReadFileService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DriverToShipmentAssignment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'DriverToShipmentAssignment {addressesFile} {driversFile}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign drivers to shipments based on SS looking for lowered costs';

    private $readFileService;
    private $driversAssignService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->readFileService = new ReadFileService();
        $this->driversAssignService = new DriversAssignService();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $addressesFileName = $this->argument('addressesFile');
        $namesFileName = $this->argument('driversFile');

        $addressesRows = $this->readFileService->extractFileRows($addressesFileName, true);
        $namesRows = $this->readFileService->extractFileRows($namesFileName, true);

        $countAddresses = count($addressesRows);
        $countNames = count($namesRows);

        if ($countAddresses !== $countNames) {
            $this->error('A mismatch between count of addresses and drivers has been found');

            return;
        }

        $costs = $this->driversAssignService->calculateSuitableScores($addressesRows, $namesRows);

        Log::info(json_encode($costs));

        return 0;
    }
}
