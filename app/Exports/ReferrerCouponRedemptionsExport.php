<?php

namespace App\Exports;

use App\Coupon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class ReferrerCouponRedemptionsExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct($referrer = null, $start_date = null, $end_date = null)
    {
        $this->referrer = $referrer;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Code',
            'Redemption Date/Time',
            'Title',
            'Reference',
            'Value GBP',
            'Value EUR',
        ];
    }

    public function map($data): array
    {
        return [
            $data->id,
            $data->barcode,
            $data->redeemed_datetime,
            $data->voucher->title,
            $data->voucher->reference,
            $data->voucher->value_gbp,
            $data->voucher->value_eur,
        ];
    }

    public function collection()
    {
        $coupons = $this->referrer ?
            Coupon::where('referrer_id', $this->referrer->id) :
            new Coupon;

        if ($this->start_date) {
            $coupons = $coupons->where('redeemed_datetime', '>=', $this->start_date . ' 23:59:59');
        }

        if ($this->end_date) {
            $coupons = $coupons->where('redeemed_datetime', '<=', $this->end_date . ' 00:00:00');
        }

        $coupons = $coupons->with('voucher');
        $coupons = $coupons->orderBy('redeemed_datetime', 'desc');

        return $coupons->get();
    }
}
