<?php

namespace App\Exports;

use App\Models\Payment;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PaymentsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        public $startDate,
        public $endDate,
        public $paymentMethod,
        public $paymentScheme
    ) {}

    public function query()
    {
        $query = Payment::query()
            ->whereBetween('created_at', [$this->startDate, $this->endDate]);

        if ($this->paymentMethod) {
            $query->where('payment_method', $this->paymentMethod);
        }

        if ($this->paymentScheme) {
            $query->where('payment_scheme', $this->paymentScheme);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Date',
            'Reference Number',
            'Payment Method',
            'Payment Scheme',
            'Amount'
        ];
    }

    public function map($payment): array
    {
        return [
            $payment->created_at->format('Y-m-d'),
            $payment->reference_number,
            ucfirst($payment->payment_method),
            ucfirst($payment->payment_scheme),
            number_format($payment->amount_paid, 2),
        ];
    }
}
