<?php

namespace App\Console\Commands;

use App\Services\DriversAssignService;
use App\Services\ReadFileService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Hungarian\Hungarian;

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

        // Extract rows for addresses file
        $addressesRows = $this->readFileService->extractFileRows($addressesFileName, true);

        // Extract rows for names file
        $namesRows = $this->readFileService->extractFileRows($namesFileName, true);

        $countAddresses = count($addressesRows);
        $countNames = count($namesRows);

        if ($countAddresses !== $countNames) {
            $this->error('A mismatch between count of addresses and drivers has been found');

            return;
        }

        // Calculate SS for addresses and driver's names
        $costs = $this->driversAssignService->calculateSuitableScores($addressesRows, $namesRows);

        $this->info("Costs Matrix: " . json_encode($costs));

        $resultsIndexes = $this->driversAssignService->solveDriverAssignment($costs);

        $this->info("Solution indexes: " . json_encode($resultsIndexes));

        $results = $this->driversAssignService
            ->translateAssignments(
                $costs,
                $resultsIndexes,
                $addressesRows,
                $namesRows
            );


        foreach ($results as $result) {
            $driver = $result['driver'];
            $address = $result['address'];
            $ss = $result['ss'];

            $this->info("Driver \"$driver\" should take \"$address\" with a SS of $ss");
        }

        return 0;
    }
}
