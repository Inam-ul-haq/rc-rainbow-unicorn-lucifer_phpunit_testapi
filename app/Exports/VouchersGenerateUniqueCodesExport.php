<?php

namespace App\Exports;

use DB;
use App\Voucher;
use App\VoucherUniqueCode;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class VouchersGenerateUniqueCodesExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct(
        Voucher $voucher = null,
        string  $prefix = null,
        int     $length = 10,
        int     $quantity = 100,
        string  $charset = 'ABCDEFGHJKLMNPQRTUVWXYZ2346789'
    ) {
        $this->voucher = $voucher;
        $this->prefix = $prefix;
        $this->length = $length;
        $this->quantity = $quantity;
        $this->charset = $charset;
    }

    public function headings(): array
    {
        return [
            'Code',
        ];
    }

    public function map($data): array
    {
        return [
            $data->code,
        ];
    }

    public function collection()
    {
        return collect($this->generateCodes());
    }

    public function generateCodes()
    {
        $max_tries_per_code = 100;

        $codes = [];
        $unique_codes_generated = 0;

        DB::beginTransaction();

        try {
            while ($unique_codes_generated < $this->quantity) {
                $code = $this->prefix;
                while (strlen($code) < $this->length) {
                    $code .= $this->charset[rand(0, strlen($this->charset)-1)];
                }
                if (DB::table('voucher_unique_codes')->where('code', '=', $code)->count() === 0) {
                    $unique_codes_generated++;
                    VoucherUniqueCode::create(
                        [
                            'code' => $code,
                            'voucher_id' => $this->voucher->id,
                        ]
                    );
                    $codes[] = $code;
                    $tries = 0;
                } else {
                    $tries++;
                }

                if ($tries == $max_tries_per_code) {
                    throw new \Exception('Unique code pool exhaustion');
                }
            }

            activity('voucher actions')
                ->on($this->voucher)
                ->tap('setLogLabel', 'generate unique codes')
                ->log('New unique codes generated');
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        DB::commit();

        // Adjust data structure as needed for export
        $newcodes = [];
        foreach ($codes as $key => $code) {
            $newcode = (object)null;
            $newcode->code = $code;
            array_push($newcodes, $newcode);
        }

        return $newcodes;
    }
}
