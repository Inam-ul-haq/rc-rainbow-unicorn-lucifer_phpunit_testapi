<?php

namespace App\Helpers;

use DB;
use Auth;
use Storage;
use TCPDFBarcode;
use \App\SystemVariable;
use \App\JobNotification;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

class Helper
{
    public static function systemStatus()
    {
        $runlevel = SystemVariable::where('variable_name', 'open_mode')->first()->variable_value;
        $motd = SystemVariable::where('variable_name', 'motd')->first()->variable_value;
        $motd_level = SystemVariable::where('variable_name', 'motd_level')->first()->variable_value;

        switch ($runlevel) {
            case 0:
                return([
                        'level' => $runlevel,
                        'status' => 'System Closed',
                        'motd' => $motd,
                        'motd_level' => $motd_level,
                    ]);
            case 1:
                return([
                        'level' => $runlevel,
                        'status' => 'Maintenance Mode',
                        'motd_level' => $motd_level,
                        'motd' => $motd,
                    ]);
            case 2:
                return([
                        'level' => $runlevel,
                        'status' => 'System Open',
                        'motd' => $motd,
                        'motd_level' => $motd_level,
                    ]);
            default:
                return([
                        'level' => -1,
                        'status' => 'Unknown Status',
                        'motd' => $motd,
                        'motd_level' => $motd_level,
                    ]);
        }
    }

    public static function getFileExtension(string $filename)
    {
        return pathinfo($filename, PATHINFO_EXTENSION);
    }

    public static function formatConsoleLine(string $text = '', int $line_length = 85)
    {
        $text = "* {$text}";
        $spaces = $line_length - strlen($text) - 2;
        for ($i=0; $i < $spaces; $i++) {
            $text .= ' ';
        }
        $text .= ' *';
        return $text;
    }

    // Checks the provided Date matches the given Format after parsing
    public static function dateMatchesFormat(string $date, string $format = 'Y-m-d')
    {
        try {
            $valid = Carbon::createFromFormat($format, $date)->format($format) === $date;
        } catch (\Exception $e) {
            return false;
        }

        return $valid;
    }

    //   https://www.gs1.org/services/how-calculate-check-digit-manually
    public static function generateBarcodeNumber(
        int $digits = 13,
        int $prefix = 501,
        string $tablename = null,
        string $fieldname = null
    ) {
        if ($tablename and !$fieldname or
            !$tablename and $fieldname) {
            throw new \Exception('When one of tablename or fieldname are supplied, the other is required');
        }

        $code = '';
        $code_attempts = 0;
        $valid_code_found = 0;

        while (!$valid_code_found) {
            if ($code_attempts >= env('MAX_GEN_BARCODE_ATTEMPTS', 20)) {
                throw new \Exception(
                    'Failed to generate barcode after ' . env(MAX_GEN_BARCODE_ATTEMPTS, 20) . ' attempts.'
                );
            }

            $code = $prefix;

            for ($i = 0; $i < $digits-1-strlen($prefix); $i++) {  // $digits-1 to allow for checksum
                $code .= mt_rand(0, 9);
            }

            // Set the starting multiplier value based on length of code (an odd number starts
            // with x1, even starts with x3)
            $multiplier = $digits % 2 ? 1 : 3;
            $sum = 0;

            // For each element of the code, multiply by 1 or 3 and sum the results
            // of that together with previous calculations
            for ($i = 0; $i < strlen($code); $i++) {
                $sum += $multiplier * substr($code, $i, 1);
                $multiplier = $multiplier===3 ? 1 : 3;
            }

            // Calculate checksum based on $sum, and add to end of code (subtract sum
            // from the nearest equal or higher multiple of ten)
            $code .= round($sum+5, -1) - $sum == 10 ? 0 : round($sum+5, -1) - $sum;

            if ($tablename and $fieldname) {
                if (DB::table($tablename)->where($fieldname, '=', $code)->count() !== 0) {
                    $code_attempts++;
                    continue;
                }
            }
            $valid_code_found = 1;
        }

        return $code;
    }

    public static function validateBarcode($barcode)
    {
        $code = substr($barcode, 0, -1);
        $check_digit = substr($barcode, -1);

        $multiplier = strlen($barcode)%2 ? 1 : 3;
        $sum = 0;

        for ($i = 0; $i< strlen($code); $i++) {
            $sum += $multiplier * substr($code, $i, 1);
            $multiplier = $multiplier === 3 ? 1 : 3;
        }

        $checksum = round($sum+5, -1) - $sum == 10 ? 0 : round($sum+5, -1) - $sum;

        return (int)$check_digit === (int)$checksum;
    }

    /**
     * Write a 1D barcode to PNG file.
     *
     * @param $barcode - barcode number to encode in barcode. Required. Must be a valid EAN13 code.
     * @param $filename - filename of image to write to. Required.
     * @param $overwrite - if $filename already exists, overwrite it if this is set to true
     * @param $bar_width - Width of a single bar element in pixels.
     * @param $bar_height - Height of a single bar element in pixels.
     * @param $colour - RGB (0-255) foreground colour  for bar elements (background is transparent)
     */
    public static function write1DBarcodePngImage(
        $barcode,
        $filename,
        $overwrite = true,
        $bar_width = 2,
        $bar_height = 30,
        $colour = array(0,0,0)
    ) {

        $format = 'EAN13';

        if (!Helper::validateBarcode($barcode)) {
            throw new \Exception("Barcode is not valid");
        }

        if (Storage::disk('public_web_assets')->exists("/barcodes/{$filename}" and $overwrite !== true)) {
            throw new \Exception("{$filename} exists, but overwrite not set");
        }

        $tcpdf = new TCPDFBarcode($barcode, $format);
        Storage::disk('public_web_assets')->put(
            $filename,
            $tcpdf->getBarcodePNGData($bar_width, $bar_height, $colour)
        );

        return true;
    }

    public static function getCurrentGuardName()
    {
        foreach (array_keys(config('auth.guards')) as $guard) {
            if (auth()->guard($guard)->check()) {
                return $guard;
            }
        }
        return null;
    }

    public static function getCurrentSource()
    {
        switch (Helper::getCurrentGuardName()) {
            case 'api':
                return env('SITE_SOURCE_VALUE', 'default_source');
            break;

            case 'api_key':
                return auth()->guard('api_key')->user()->token()->name;
            break;

            default:
                return 'unknown_guard, no source';
        }
    }
}
