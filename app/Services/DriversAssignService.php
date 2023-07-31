<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class DriversAssignService
{
    public function __construct()
    {
    }

    public function calculateSuitableScores($addresses, $drivers)
    {
        $result = [];

        foreach ($drivers as $driver) {
            $row = [];

            foreach ($addresses as $address) {
                $ss = 0;
                $driverNameLen = strlen($driver);
                $addressNameLen = strlen($address);

                $lenIsEven = $addressNameLen % 2 === 0;
                $gmp = gmp_gcd($driverNameLen, $addressNameLen);

                $driverStr = strtolower($driver);

                if ($lenIsEven) {
                    $ss = preg_match_all('/[aeiou]/i', $driverStr, $matches);
                } else {
                    $ss = preg_match_all('/[bcdfghijklmnpqrstvwxyz]/i', $driverStr, $matches);
                }

                $ss *= intval($gmp);

                $row []= $ss;
            }

            $result []= $row;
        }

        return $result;
    }
}
