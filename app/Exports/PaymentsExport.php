<?php

namespace App\Exports;

use App\Models\CashFlow;
use Carbon\Carbon;
use App\Models\Payment;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PaymentsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle, WithEvents
{
    protected $totalAmount = 0;
    protected $totalCash = 0;
    protected $totalCOD = 0;
    protected $totalSign = 0;
    protected $totalReturned = 0;
    protected $totalRefund = 0;
    // protected $totalMoneyIn = 0;
    // protected $totalMoneyOut = 0;
    protected $totalCOH = 0;
    protected $transactionCount = 0;

    public function __construct(
        public $startDate,
        public $endDate,
        public $paymentMethod,
        public $paymentScheme
    ) {}

    public function query()
    {
        $query = Payment::query()
            ->whereHas('order', function($q) {
                $q->where('is_void', false);
            })
            ->whereBetween('created_at', [$this->startDate, Carbon::parse($this->endDate)->endOfDay()]);

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
                'Customer Name',
                'Payment Method',
                'Payment Scheme',
                'Amount (₱)'
            ]
        ];
    }

    public function map($payment): array
    {
        $this->transactionCount++;
        $this->totalAmount += $payment->amount_paid;

        // Calculate different payment types
        switch ($payment->payment_method) {
            case 'cash':
                $this->totalCash += $payment->amount_paid;
                break;
            case 'cod':
                $this->totalCOD += $payment->amount_paid;
                break;
            case 'sign':
                $this->totalSign += $payment->amount_paid;
                break;
            case 'returned':
                $this->totalReturned += $payment->amount_paid;
                break;
            case 'refund':
                $this->totalRefund += $payment->amount_paid;
                break;
            // Add other cases as needed
        }

        // Add calculations for other metrics as needed
        // $this->totalSign += ...;
        // $this->totalReturned += ...;
        // $this->totalRefund += ...;
        // $this->totalMoneyIn += ...;
        // $this->totalMoneyOut += ...;
        // $this->totalCOH = $this->totalMoneyIn - $this->totalMoneyOut;

        return [
            $payment->created_at->format('Y-m-d'),
            $payment->order->order_number,
            $payment->order->customer->name ?? 'Walk-In',
            ucfirst($payment->payment_method),
            ucfirst($payment->payment_scheme),
            $payment->amount_paid,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Set paper size to A4 and orientation to landscape
        $sheet->getPageSetup()
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);

        // Header styles
        $sheet->mergeCells('A1:F1');
        $sheet->mergeCells('A2:F2');
        $sheet->mergeCells('A3:F3');

        $sheet->getStyle('A1:F1')->applyFromArray([
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

        $sheet->getStyle('A2:F3')->applyFromArray([
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
        $sheet->getStyle('A5:F5')->applyFromArray([
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
            $sheet->getStyle('A' . $i . ':F' . $i)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->setStartColor(new Color($fillColor));
        }

        // Format amount column with ₱ sign
        $sheet->getStyle('F6:F' . $lastDataRow)->getNumberFormat()
            ->setFormatCode('"₱"#,##0.00');

        // Auto-size columns for better fit
        foreach (range('A', 'F') as $column) {
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
                $sheet->setCellValue('E' . $totalRow, 'TOTAL:');
                $sheet->setCellValue('F' . $totalRow, $this->totalAmount);

                // Style the label cell
                $sheet->getStyle('E' . $totalRow)->applyFromArray([
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
                $sheet->getStyle('F' . $totalRow)->applyFromArray([
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

                // Add summary section
                $summaryStartRow = $totalRow + 2;

                $summaryData = [
                    ['Total Sales', '₱' . number_format($this->totalAmount, 2)],
                    ['Number of Transaction', $this->transactionCount],
                    ['Total Cash', '₱' . number_format($this->totalCash, 2)],
                    ['Total COD', '₱' . number_format($this->totalCOD, 2)],
                    ['Total Sign', '₱' . number_format($this->totalSign, 2)],
                    ['Total Returned', '₱' . number_format($this->totalReturned, 2)],
                    ['Total Refund', '₱' . number_format($this->totalRefund, 2)],
                    ['Total Money-In', '₱' . number_format($this->totalMoneyIn(), 2)],
                    ['Total Money-Out', '₱' . number_format($this->totalMoneyOut(), 2)],
                    ['COH', '₱' . number_format($this->totalCash + ($this->totalMoneyIn() - $this->totalMoneyOut()), 2)]
                ];

                // Add summary headers
                $sheet->setCellValue('A' . $summaryStartRow, 'SUMMARY');
                $sheet->mergeCells('A' . $summaryStartRow . ':B' . $summaryStartRow);
                $sheet->getStyle('A' . $summaryStartRow)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 14,
                        'color' => ['rgb' => 'FFFFFF']
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '2F75B5']
                    ]
                ]);

                // Add summary data
                $currentRow = $summaryStartRow + 1;
                foreach ($summaryData as $item) {
                    $sheet->setCellValue('A' . $currentRow, $item[0]);
                    $sheet->setCellValue('B' . $currentRow, $item[1]);

                    // Style for label
                    $sheet->getStyle('A' . $currentRow)->applyFromArray([
                        'font' => [
                            'bold' => true,
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'D9E1F2']
                        ],
                        'borders' => [
                            'left' => ['borderStyle' => Border::BORDER_THIN],
                            'top' => ['borderStyle' => Border::BORDER_THIN],
                            'bottom' => ['borderStyle' => Border::BORDER_THIN],
                        ]
                    ]);

                    // Style for value
                    $sheet->getStyle('B' . $currentRow)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFFFFF']
                        ],
                        'borders' => [
                            'right' => ['borderStyle' => Border::BORDER_THIN],
                            'top' => ['borderStyle' => Border::BORDER_THIN],
                            'bottom' => ['borderStyle' => Border::BORDER_THIN],
                        ]
                    ]);

                    $currentRow++;
                }

                // Adjust column widths
                $sheet->getColumnDimension('A')->setWidth(25);
                $sheet->getColumnDimension('B')->setWidth(20);

                // Set print area to include the summary
                $sheet->getPageSetup()->setPrintArea('A1:E' . ($currentRow - 1));
            }
        ];
    }

    public function totalMoneyIn(): float
    {
        $totalMoneyIn = CashFlow::where('type', 'in')
            ->whereBetween('created_at', [$this->startDate, Carbon::parse($this->endDate)->endOfDay()])
            ->sum('amount');
        return $totalMoneyIn ?? 0;
    }
    public function totalMoneyOut(): float
    {
        $totalMoneyOut = CashFlow::where('type', 'out')
            ->whereBetween('created_at', [$this->startDate, Carbon::parse($this->endDate)->endOfDay()])
            ->sum('amount');
        return $totalMoneyOut ?? 0;
    }
}
