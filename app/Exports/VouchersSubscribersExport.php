<?php

namespace App\Exports;

use DB;
use App\Voucher;
use App\Subscribers;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class VouchersSubscribersExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct(Voucher $voucher = null, $orders = [])
    {
        $this->voucher = $voucher;
        $this->orders = [];

        if (is_array($orders) and count($orders) === 0) {
            foreach ($orders as $order_field => $order_dir) {
                list($table, $field) = explode('.', $order_field);
                if (Schema::hasColumn($table, $field)) {
                    $this->orders[$order_field] = $order_dir;
                }
            }
        }
    }

    public function headings(): array
    {
        return [
            'Coupon ID',
            'Consumer ID',
            'First Name',
            'Last Name',
            'Voucher Name',
            'Status',
            'Unique Code Used',
            'Access Code Used',
            'Issued Date',
            'Redeemed Date',
        ];
    }

    public function map($data): array
    {
        return [
            $data->coupon_uuid,
            $data->consumer_uuid,
            $data->first_name,
            $data->last_name,
            $data->voucher_name,
            $data->status,
            $data->unique_code_used,
            $data->access_code_used,
            $data->issued_at,
            $data->redeemed_at,
        ];
    }

    public function collection()
    {
        $records = DB::table('coupons')
            ->select(
                'coupons.uuid AS coupon_uuid',
                'consumers.uuid AS consumer_uuid',
                'consumers.first_name AS first_name',
                'consumers.last_name AS last_name',
                'vouchers.public_name AS voucher_name',
                'coupons.status AS status',
                'voucher_unique_codes.code AS unique_code_used',
                'voucher_access_codes.access_code AS access_code_used',
                'coupons.issued_at',
                'coupons.redeemed_datetime AS redeemed_at'
            )
            ->join('consumers', function ($join) {
                $join->on('consumers.id', '=', 'coupons.restrict_consumer_id');
                $join->orOn('consumers.id', '=', 'redeemed_by_consumer_id');
            })
            ->join(
                'voucher_unique_codes',
                'coupons.vouchers_unique_codes_used_id',
                '=',
                'voucher_unique_codes.id',
                'left outer'
            )
            ->join(
                'voucher_access_codes',
                'coupons.access_code_id',
                '=',
                'voucher_access_codes.id',
                'left outer'
            )
            ->join(
                'vouchers',
                'coupons.voucher_id',
                '=',
                'vouchers.id'
            )
            ->where('coupons.voucher_id', '=', $this->voucher->id);

        return $records->get();
    }
}
