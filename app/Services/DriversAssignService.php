<?php

namespace App\Services;

use Error;
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

    public function negateMatrix($arr)
    {
        $result = [];

        foreach ($arr as $row) {
            $rowNegate = [];

            foreach ($row as $item) {
                $rowNegate []= $item * -1;
            }

            $result []= $rowNegate;
        }

        return $result;
    }

    public function findLowestValueInMatrix($arr)
    {
        if (count($arr) === 0) {
            throw new Error("Empty array");
        }

        $minValue = $arr[0][0];

        foreach ($arr as $row) {
            foreach ($row as $item) {
                $minValue = $minValue > $item ? $item : $minValue;
            }
        }

        return $minValue;
    }

    public function makeNonNegativeMatrix($arr, $lowest) {
        $result = [];

        foreach ($arr as $row) {
            $rowRes = [];

            foreach ($row as $item) {
                $rowRes []= $item + abs($lowest);
            }

            $result []= $rowRes;
        }

        return $result;
    }

    public function reduceCostsMatrix($arr)
    {
        $reducedMatrix = [];

        if (count($arr) === 0) {
            throw new Error("Empty array");
        }

        // Search for lowest value for each row
        foreach ($arr as $row) {
            $rowLowest = $row[0];
            $reducedRow = [];
            $rowLowest = min($row);

            foreach ($row as $item) {
                $reducedRow []= $item - $rowLowest;
            }

            $reducedMatrix []= $reducedRow;
        }

        $countCols = count($reducedMatrix[0]);

        for ($rowIndex = 0; $rowIndex < $countCols; $rowIndex++) {
            $col = [];

            for ($colIndex = 0; $colIndex < $countCols; $colIndex++) {
                $col []= $reducedMatrix[$colIndex][$rowIndex];
            }

            $colLowest = min($col);

            for ($colIndex = 0; $colIndex < $countCols; $colIndex++) {
                $colItem = $reducedMatrix[$colIndex][$rowIndex];
                $reducedMatrix[$colIndex][$rowIndex] = $colItem - $colLowest;
            }
        }

        return $reducedMatrix;
    }

    private function allValuesAreTheSameInArray($arr) {
        return count(array_unique($arr, SORT_REGULAR)) === 1;
    }

    public function allLinesCovered($arr)
    {
        $lines = 0;
        $requiredLines = count($arr[0]);
        $coveredRows = [];
        $coveredCols = [];

        foreach ($arr as $index => $row) {
            $coveredLine = $this->allValuesAreTheSameInArray($row);

            if ($coveredLine) {
                $lines += 1;
                $coveredRows []= $index;
            }
        }

        for ($rowIndex = 0; $rowIndex < $requiredLines; $rowIndex++) {
            $col = [];

            for ($colIndex = 0; $colIndex < $requiredLines; $colIndex++) {
                $col []= $arr[$colIndex][$rowIndex];
            }

            $coveredCol = $this->allValuesAreTheSameInArray($col);

            if ($coveredCol) {
                $lines += 1;
                $coveredCols []= $rowIndex;
            }
        }

        return [
            'covered' => $lines === $requiredLines,
            'coveredRows' => $coveredRows,
            'coveredCols' => $coveredCols
        ];
    }

    public function reduceUncoveredArray($arr, $coveredRows, $coveredCols)
    {
        $requiredLines = count($arr[0]);
        $minInArray = $arr[0][0];

        for ($rowIndex = 0; $rowIndex < $requiredLines; $rowIndex++) {
            for ($colIndex = 0; $colIndex < $requiredLines; $colIndex++) {
                $arrItem = $arr[$rowIndex][$colIndex];

                $minInArray = $arrItem < $minInArray && $arrItem !== 0 ? $arrItem : $minInArray;
            }
        }

        for ($rowIndex = 0; $rowIndex < $requiredLines; $rowIndex++) {
            for ($colIndex = 0; $colIndex < $requiredLines; $colIndex++) {
                $arrItem = $arr[$rowIndex][$colIndex];

                // Uncovered value
                if ($arrItem !== 0) {
                    $arr[$rowIndex][$colIndex] = $arrItem - $minInArray;
                }

                // Covered twice value
                if (in_array($rowIndex, $coveredRows) && in_array($colIndex, $coveredCols)) {
                    $arr[$rowIndex][$colIndex] = $arrItem + $minInArray;
                }
            }
        }

        return $arr;
    }

    public function solveDriverAssignment($costs)
    {
        $negateMatrix = $this->negateMatrix($costs);
        $lowest = $this->findLowestValueInMatrix($negateMatrix);
        $nonNegativeCosts = $this->makeNonNegativeMatrix($negateMatrix, $lowest);
        $reducedMatrixCosts = $this->reduceCostsMatrix($nonNegativeCosts);

        $coveredValues = $this->allLinesCovered($reducedMatrixCosts);
        $optimalAssignment = $coveredValues['covered'];

        if (!$optimalAssignment) {
            $reducedMatrixCosts = $this
                ->reduceUncoveredArray(
                    $reducedMatrixCosts,
                    $coveredValues['coveredRows'],
                    $coveredValues['coveredCols']
                );
        }

        $assignedCols = [];

        foreach ($reducedMatrixCosts as $indexRow => $row) {
            foreach ($row as $indexCol => $item) {
                if ($item === 0 && !in_array($indexCol, $assignedCols)) {
                    $assignedCols []= $indexCol;

                    break;
                }
            }
        }

        return $assignedCols;
    }

    public function translateAssignments($costs, $results, $addresses, $names)
    {
        $assignments = [];

        for ($dataIndex = 0; $dataIndex < count($results); $dataIndex++) {
            $assignments []= [
                'driver' => $names[$dataIndex],
                'address' => $addresses[$results[$dataIndex]],
                'ss' => $costs[$dataIndex][$results[$dataIndex]]
            ];
        }

        return $assignments;
    }
}
