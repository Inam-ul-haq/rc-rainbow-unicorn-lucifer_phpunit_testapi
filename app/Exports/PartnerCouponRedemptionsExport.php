<?php

namespace App\Exports;

use App\Coupon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class PartnerCouponRedemptionsExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct($partner = null, $start_date = null, $end_date = null)
    {
        $this->partner = $partner;
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
        $coupons = $this->partner ?
            Coupon::where('redemption_partner_id', $this->partner->id) :
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
