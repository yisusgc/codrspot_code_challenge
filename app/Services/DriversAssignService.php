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

                /*
                If the length of the shipment's destination street name is even,
                the base suitability score (SS) is the number of vowels in the driver’s
                name multiplied by 1.5.
                */
                if ($lenIsEven) {
                    $ss = preg_match_all('/[aeiou]/i', $driverStr, $matches) * 1.5;
                } else {
                /*
                If the length of the shipment's destination street name is odd,
                the base SS is the number of consonants in the driver’s name multiplied by 1.
                */
                    $ss = preg_match_all('/[bcdfghijklmnpqrstvwxyz]/i', $driverStr, $matches);
                }

                /*
                If the length of the shipment's destination street name shares any common factors (besides 1)
                with the length of the driver’s name, the SS is increased by 50% above the base SS.
                */
                $ss *= intval($gmp) !== 1 ? 1.5 : 1;
                $row []= $ss;
            }

            $result []= $row;
        }

        return $result;
    }

    // Step 1 -> negate the values in the costs matrix
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

    // Step 2 -> Find the lowest value in the matrix to substract from any other values
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

    // Step 3 -> Revert matrix negate and add the absolute value of the lowest value
    // in the result matrix of step 2
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

    // Step 4 -> reduce the current matrix by substract the min value of each row and each col
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

            // Substract the lowest value in row to each element in the row
            foreach ($row as $item) {
                $reducedRow []= $item - $rowLowest;
            }

            $reducedMatrix []= $reducedRow;
        }

        $countCols = count($reducedMatrix[0]);

        // Search for lowest value for each col
        for ($rowIndex = 0; $rowIndex < $countCols; $rowIndex++) {
            $col = [];

            // Visualize the array as columuns
            for ($colIndex = 0; $colIndex < $countCols; $colIndex++) {
                $col []= $reducedMatrix[$colIndex][$rowIndex];
            }

            $colLowest = min($col);

            // Substract the lowest value in row to each element in the col
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

    // Step 5 -> verify that all the rows/cols contains at least 1 zero
    // If the matrix is 3x3 then at least 3 rows or 3 cols or a mix of rows+cols should be 3
    public function allLinesCovered($arr)
    {
        $lines = 0;
        $requiredLines = count($arr[0]);
        $coveredRows = [];
        $coveredCols = [];

        // Verify that every value in a row is zero
        foreach ($arr as $index => $row) {
            $coveredLine = $this->allValuesAreTheSameInArray($row);

            if ($coveredLine) {
                $lines += 1;
                $coveredRows []= $index;
            }
        }

        // Verify that every value in a col is zero
        for ($rowIndex = 0; $rowIndex < $requiredLines; $rowIndex++) {
            $col = [];

            // Visualize the matrix as columns
            for ($colIndex = 0; $colIndex < $requiredLines; $colIndex++) {
                $col []= $arr[$colIndex][$rowIndex];
            }

            // Verify that every value in a col is zero
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

    // Step 6 -> if not all the lines are covered, we need to find the lowest value in the matrix
    // This value should be substract from any uncovered value in the cost matrix
    // This value should be added to those values that are covered by row and cols
    public function reduceUncoveredArray($arr, $coveredRows, $coveredCols)
    {
        $requiredLines = count($arr[0]);
        $minInArray = $arr[0][0];

        // Search for the lowest value in the array
        for ($rowIndex = 0; $rowIndex < $requiredLines; $rowIndex++) {
            for ($colIndex = 0; $colIndex < $requiredLines; $colIndex++) {
                $arrItem = $arr[$rowIndex][$colIndex];

                $minInArray = $arrItem < $minInArray && $arrItem !== 0 ? $arrItem : $minInArray;
            }
        }

        // Iterate over the matrix to look for uncovered values
        for ($rowIndex = 0; $rowIndex < $requiredLines; $rowIndex++) {
            for ($colIndex = 0; $colIndex < $requiredLines; $colIndex++) {
                $arrItem = $arr[$rowIndex][$colIndex];

                // Uncovered value found
                // The lowest value in the array should be substract from any uncovered value in the cost matrix
                if ($arrItem !== 0) {
                    $arr[$rowIndex][$colIndex] = $arrItem - $minInArray;
                }

                // Covered twice value
                // The lowest value should be added to those values that are covered by row and cols
                if (in_array($rowIndex, $coveredRows) && in_array($colIndex, $coveredCols)) {
                    $arr[$rowIndex][$colIndex] = $arrItem + $minInArray;
                }
            }
        }

        return $arr;
    }

    protected function generateCoveredIndexes($arr, $rows, $cols)
    {
        $indexes = [];
        $limit = count($arr[0]);

        // Create all possible indexes for solutions
        for ($rowIndex = 0; $rowIndex < $limit; $rowIndex++) {
            if (in_array($rowIndex, $rows)) {
                for ($assignIndex = 0; $assignIndex < $limit; $assignIndex++) {
                    $indexes []= [$rowIndex, $assignIndex];
                }
            }

            if (in_array($rowIndex, $cols)) {
                for ($colIndex = 0; $colIndex < $limit; $colIndex++) {
                    $indexes []= [$colIndex, $rowIndex];
                }
            }
        }

        $arrayUnique = [];

        // Delete all values that are double covered
        foreach ($indexes as $index) {
            if (!in_array($index, $arrayUnique)) {
                $arrayUnique []= $index;
            } else {
                $index = array_search($index, $arrayUnique);
                unset($arrayUnique[$index]);
            }
        }

        return $arrayUnique;
    }

    public function solveDriverAssignment($costs)
    {
        // Step 1 -> negate the values in the costs matrix
        $negateMatrix = $this->negateMatrix($costs);

        // Log::info(json_encode($negateMatrix));

        // Step 2 -> Find the lowest value in the matrix to substract from any other values
        $lowest = $this->findLowestValueInMatrix($negateMatrix);

        // Log::info($lowest);

        // Step 3 -> Revert matrix negate and add the absolute value of the lowest value
        // in the result matrix of step 2
        $nonNegativeCosts = $this->makeNonNegativeMatrix($negateMatrix, $lowest);

        // Log::info(json_encode($nonNegativeCosts));

        // Step 4 -> reduce the current matrix by substract the min value of each row and each col
        $reducedMatrixCosts = $this->reduceCostsMatrix($nonNegativeCosts);

        // Log::info(json_encode($reducedMatrixCosts));

        // Step 5 -> verify that all the rows/cols contains at least 1 zero
        // If the matrix is 3x3 then at least 3 rows or 3 cols or a mix of rows+cols should be 3
        $coveredValues = $this->allLinesCovered($reducedMatrixCosts);

        // Log::info(json_encode($coveredValues));

        $optimalAssignment = $coveredValues['covered'];

        // Step 6 -> if not all the lines are covered, we need to find the lowest value in the matrix
        // This value should be substract from any uncovered value in the cost matrix
        // This value should be added to those values that are covered by row and cols
        if (!$optimalAssignment) {
            $reducedMatrixCosts = $this
                ->reduceUncoveredArray(
                    $reducedMatrixCosts,
                    $coveredValues['coveredRows'],
                    $coveredValues['coveredCols']
                );
        }

        $solutionIndexes = $this->generateCoveredIndexes(
            $reducedMatrixCosts,
            $coveredValues['coveredRows'],
            $coveredValues['coveredCols']
        );

        // Log::info($solutionIndexes);

        $covered = [
            'rows' => [],
            'cols' => [],
        ];
        $assigned = [];

        // Step 7 -> look for optimal assignmanet by looking for zeros
        // And those values that aren't already assigned
        // NOTE: those values that are double covered in solution need to be ignored to prevent noise in optimal assignment

        for ($indexRow = 0; $indexRow < count($reducedMatrixCosts[0]); $indexRow++) {
            for ($indexCol = 0; $indexCol < count($reducedMatrixCosts[0]); $indexCol++) {
                $index = is_int(array_search([$indexRow, $indexCol], $solutionIndexes));
                $coveredRowIndex = in_array($indexRow, $covered['rows']);
                $coveredColIndex = in_array($indexCol, $covered['cols']);

                if ($index && !$coveredRowIndex && !$coveredColIndex) {
                    $assigned []= [$indexRow, $indexCol];
                    $covered['rows'] []= $indexRow;
                    $covered['cols'] []= $indexCol;
                }
            }
        }

        return $assigned;
    }

    // We look for the results of the optimal assignment in the matrix of results and match
    // with the addresses and driver's names rows
    public function translateAssignments($costs, $results, $addresses, $names)
    {
        $limit = count($costs[0]);
        $assignments = [];

        for ($rowIndex = 0; $rowIndex < $limit; $rowIndex++) {
            for ($colIndex = 0; $colIndex < $limit; $colIndex++) {
                $index = is_int(array_search([$rowIndex, $colIndex], $results));

                if ($index) {
                    $assignments []= [
                        'driver' => $names[$rowIndex],
                        'address' => $addresses[$rowIndex],
                        'ss' => $costs[$rowIndex][$colIndex]
                    ];
                }
            }
        }

        return $assignments;
    }
}
