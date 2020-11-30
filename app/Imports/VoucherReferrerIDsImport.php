<?php

namespace App\Imports;

use App\Voucher;
use App\Referrer;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class VoucherReferrerIDsImport implements ToCollection
{
    public function __construct(Voucher $voucher)
    {
        $this->voucher = $voucher;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if ($referrer = Referrer::where('uuid', $row)->get()) {
                if (!$this->voucher->referrerRestrictions->contains($referrer)) {
                    $this->voucher->referrerRestrictions()->attach($referrer);
                }
            }
        }
    }
}
