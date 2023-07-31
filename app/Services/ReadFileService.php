<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ReadFileService
{
    public function __construct()
    {
    }

    public function extractFileRows($filename, $throwError)
    {
        $rows = [];

        try {
            $file = fopen(storage_path('app/' . $filename), 'r');

            while(!feof($file))  {
                $line = fgets($file);

                if ($line) {
                    $rows []= preg_replace("#\n#", "", $line);
                }
            }

            fclose($file);

            return $rows;
        } catch (\Exception $e) {
            if (!$throwError) {
                return [];
            }

            Log::info($e->getMessage());

            throw $e;
        }

    }
}
