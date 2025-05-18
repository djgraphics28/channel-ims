<?php

namespace App\Exports;

use App\Models\Payment;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PaymentsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle, WithEvents
{
    protected $totalAmount = 0;

    public function __construct(
        public $startDate,
        public $endDate,
        public $paymentMethod,
        public $paymentScheme
    ) {}

    public function query()
    {
        $query = Payment::query()
            ->whereHas('order')
            ->whereBetween('created_at', [$this->startDate, $this->endDate]);

        if ($this->paymentMethod) {
            $query->where('payment_method', $this->paymentMethod);
        }

        if ($this->paymentScheme) {
            $query->where('payment_scheme', $this->paymentScheme);
        }

        return $query;
    }

    public function title(): string
    {
        return 'Payments Report';
    }

    public function headings(): array
    {
        return [
            ['SALES REPORT'],
            ['Generated on: ' . now()->format('Y-m-d H:i:s')],
            ['Period: ' . $this->startDate . ' to ' . $this->endDate],
            [], // Empty row for spacing
            [
                'Date',
                'Reference Number',
                'Payment Method',
                'Payment Scheme',
                'Amount (₱)'
            ]
        ];
    }

    public function map($payment): array
    {
        $this->totalAmount += $payment->amount_paid; // Calculate total

        return [
            $payment->created_at->format('Y-m-d'),
            $payment->order->order_number,
            ucfirst($payment->payment_method),
            ucfirst($payment->payment_scheme),
            $payment->amount_paid, // Raw value (formatting handled in styles)
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Set paper size to A4 and orientation to landscape
        $sheet->getPageSetup()
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);

        // Header styles
        $sheet->mergeCells('A1:E1');
        $sheet->mergeCells('A2:E2');
        $sheet->mergeCells('A3:E3');

        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2F75B5']
            ]
        ]);

        $sheet->getStyle('A2:E3')->applyFromArray([
            'font' => [
                'size' => 11,
                'color' => ['rgb' => '333333']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D9E1F2']
            ]
        ]);

        // Column headers style
        $sheet->getStyle('A5:E5')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '5B9BD5']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Data rows style
        $lastDataRow = $sheet->getHighestRow();
        $dataRange = 'A6:E' . $lastDataRow;

        $sheet->getStyle($dataRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'DDDDDD']
                ]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
            ]
        ]);

        // Alternate row coloring
        for ($i = 6; $i <= $lastDataRow; $i++) {
            $fillColor = $i % 2 == 0 ? 'FFFFFF' : 'EFF2F7';
            $sheet->getStyle('A' . $i . ':E' . $i)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->setStartColor(new Color($fillColor));
        }

        // Format amount column with ₱ sign
        $sheet->getStyle('E6:E' . $lastDataRow)->getNumberFormat()
            ->setFormatCode('"₱"#,##0.00');

        // Auto-size columns for better fit
        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Freeze header row
        $sheet->freezePane('A6');
    }

    public function registerEvents(): array
{
    return [
        AfterSheet::class => function(AfterSheet $event) {
            $sheet = $event->sheet;
            $lastRow = $sheet->getHighestRow();

            // Add Total row
            $totalRow = $lastRow + 1;

            // Label in column D
            $sheet->setCellValue('D' . $totalRow, 'TOTAL:');

            // Value in column E (with Peso sign formatting)
            $sheet->setCellValue('E' . $totalRow, $this->totalAmount);

            // Style the label cell
            $sheet->getStyle('D' . $totalRow)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_RIGHT,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_THIN],
                    'bottom' => ['borderStyle' => Border::BORDER_THIN],
                    'left' => ['borderStyle' => Border::BORDER_THIN],
                ]
            ]);

            // Style the value cell
            $sheet->getStyle('E' . $totalRow)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_RIGHT,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_THIN],
                    'bottom' => ['borderStyle' => Border::BORDER_THIN],
                    'right' => ['borderStyle' => Border::BORDER_THIN],
                ],
                'numberFormat' => [
                    'formatCode' => '"₱"#,##0.00'
                ]
            ]);

            // Adjust column widths after adding total
            foreach (range('A', 'E') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Set print area to include the total row
            $sheet->getPageSetup()->setPrintArea('A1:E' . $totalRow);
        }
    ];
}

    // public function registerEvents(): array
    // {
    //     return [
    //         AfterSheet::class => function(AfterSheet $event) {
    //             $sheet = $event->sheet;
    //             $lastRow = $sheet->getHighestRow();

    //             // Add Total row
    //             $totalRow = $lastRow + 1;
    //             $sheet->setCellValue('D' . $totalRow, 'TOTAL:');
    //             $sheet->setCellValue('E' . $totalRow, $this->totalAmount);

    //             // Merge cells for label
    //             $sheet->mergeCells('D' . $totalRow . ':E' . $totalRow);

    //             // Style the Total row
    //             $sheet->getStyle('D' . $totalRow . ':E' . $totalRow)->applyFromArray([
    //                 'font' => [
    //                     'bold' => true,
    //                     'size' => 12,
    //                     'color' => ['rgb' => 'FFFFFF']
    //                 ],
    //                 'alignment' => [
    //                     'horizontal' => Alignment::HORIZONTAL_RIGHT,
    //                 ],
    //                 'fill' => [
    //                     'fillType' => Fill::FILL_SOLID,
    //                     'startColor' => ['rgb' => '4472C4']
    //                 ],
    //                 'borders' => [
    //                     'allBorders' => [
    //                         'borderStyle' => Border::BORDER_THIN,
    //                         'color' => ['rgb' => '000000']
    //                     ]
    //                 ],
    //                 'numberFormat' => [
    //                     'formatCode' => '"₱"#,##0.00'
    //                 ]
    //             ]);

    //             // Adjust column widths after adding total
    //             foreach (range('A', 'E') as $column) {
    //                 $sheet->getColumnDimension($column)->setAutoSize(true);
    //             }

    //             // Set print area to include the total row
    //             $sheet->getPageSetup()->setPrintArea('A1:E' . $totalRow);
    //         }
    //     ];
    // }
}
