<?php

namespace App\Exports;

use App\Consumer;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class ConsumersExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct($search = null, $active = null, $blacklist = null)
    {
        $this->searchTerm = $search;
        $this->activeFlag = $active;
        $this->blacklistFlag = $blacklist;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Title',
            'First Name',
            'Last Name',
            'CRM ID',
            'Address Line 1',
            'Town',
            'County',
            'Postcode',
            'Email',
            'Telephone',
            'On Blacklist',
            'Blacklisted At',
            'Active',
            'Deactivated at',
            'Created at',
        ];
    }

    public function map($data): array
    {
        return [
            $data->id,
            $data->nameTitle->title,
            $data->first_name,
            $data->last_name,
            $data->crm_id,
            $data->address_line_1,
            $data->town,
            $data->county,
            $data->postcode,
            $data->email,
            $data->telephone,
            $data->blacklisted ? 'Yes' : 'No',
            $data->blacklisted_at,
            $data->active ? 'Yes' : 'No',
            $data->deactivated_at,
            $data->created_at,
        ];
    }

    public function collection()
    {
        $consumers = $this->searchTerm ?
                        Consumer::search($this->searchTerm) :
                        new Consumer;

        if (is_numeric($this->activeFlag)) {
            $consumers = $consumers->where('active', $this->activeFlag);
        }

        if (is_numeric($this->blacklistFlag)) {
            $consumers = $consumers->where('blacklisted', $this->blacklistFlag);
        }

        return $consumers->get();
    }
}
