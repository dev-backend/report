<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromArray;

class OrdersExport implements FromArray, WithHeadings
{
    protected $orders;

    public function __construct(array $orders)
    {
        $this->orders = $orders;
    }

    public function array(): array
    {
        return $this->orders;
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';'
        ];
    }

    public function headings(): array
    {
        return [
            'Email',
            'Date',
            'Value',
            'Site ID',
            'Order Creared',
            'Order Status',
            'Type'
        ];
    }
}
