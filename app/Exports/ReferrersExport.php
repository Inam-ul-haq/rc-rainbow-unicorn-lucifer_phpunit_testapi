<?php

namespace App\Exports;

use App\Referrer;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class ReferrersExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct($search = null, $blacklist = null)
    {
        $this->searchTerm = $search;
        $this->blacklistFlag = $blacklist;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Title',
            'First Name',
            'Last Name',
            'Referrer Points',
            'Email',
            'On Blacklist',
            'Blacklisted At',
            'Created at',
            'Updated at',
        ];
    }

    public function map($data): array
    {
        return [
            $data->id,
            $data->nameTitle->title,
            $data->first_name,
            $data->last_name,
            $data->referrer_points,
            $data->email,
            $data->blacklisted ? 'Yes' : 'No',
            $data->blacklisted_at,
            $data->created_at,
            $data->updated_at,
        ];
    }

    public function collection()
    {
        $referrers = $this->searchTerm ?
                        Referrer::search($this->searchTerm) :
                        new Referrer;

        if (is_numeric($this->blacklistFlag)) {
            $referrers = $consumers->where('blacklisted', $this->blacklistFlag);
        }

        return $referrers->get();
    }
}
