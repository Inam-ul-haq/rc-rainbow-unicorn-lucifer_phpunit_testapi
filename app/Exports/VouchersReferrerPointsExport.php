<?php

namespace App\Exports;

use App\Voucher;
use App\ReferrerPoints;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class VouchersReferrerPointsExport implements FromCollection, WithHeadings, WithMapping
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
            'Transaction ID',
            'Transaction Type',
            'Transaction Date',
            'Points',
            'Voucher UUID',
            'Consumer UUID',
            'Consumer Email',
            'Consumer Name',
            'Referrer UUID',
            'Referrer Name',
            'Referrer Email',
            'Transaction Notes',
        ];
    }

    public function map($data): array
    {
        return [
            $data->id,
            $data->transaction_date,
            $data->transaction_type,
            $data->points,
            $data->voucher_uuid,
            $data->consumer_uuid,
            $data->consumer_email,
            $data->consumer_firstname . ' ' . $data->consumer_lastname,
            $data->referrer_uuid,
            $data->referrer_firstname . ' ' . $data->referrer_lastname,
            $data->referrer_email,
            $data->transaction_notes,
        ];
    }

    public function collection()
    {

        $records = ReferrerPoints
            ::join('coupons', 'referrer_points.coupon_id', '=', 'coupons.id')
            ->join('vouchers', 'coupons.voucher_id', '=', 'vouchers.id')
            ->join('consumers', 'referrer_points.consumer_id', '=', 'consumers.id')
            ->join('referrers', 'referrer_points.referrer_id', '=', 'referrers.id')
            ->select(
                [
                    'referrer_points.id AS transaction_id',
                    'referrer_points.transaction_date',
                    'referrer_points.transaction_type',
                    'referrer_points.notes AS transaction_notes',
                    'referrer_points.points',
                    'consumers.first_name AS consumer_firstname',
                    'consumers.last_name AS consumer_lastname',
                    'consumers.email AS consumer_email',
                    'consumers.uuid AS consumer_uuid',
                    'referrers.first_name AS referrer_firstname',
                    'referrers.last_name AS referrer_lastname',
                    'referrers.email AS referrer_email',
                    'referrers.uuid AS referrer_uuid',
                    'vouchers.uuid AS voucher_uuid',
                ]
            );

        if (isset($this->voucher) and $this->voucher) {
            $records = $records->where('vouchers.id', '=', $this->voucher->id);
        }

        return $records->get();
    }
}
