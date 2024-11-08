<?php

namespace App\Exports;

use App\Models\Waitlist;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class WaitlistExport implements FromCollection, WithHeadings, WithMapping, WithEvents, WithTitle
{
    // protected $user_id;

    // public function __construct($user_id)
    // {
    //     $this->$user_id = $$user_id;
    // }
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $data = Waitlist::get([
            'name',
            'email',
            // 'status',
            'created_at'
        ]);
    
        Log::info("Exporting " . $data->count() . " waitlist records.");
    
        return $data;
    }

    public function headings(): array
    {
        return [
            'Name',
            'Email',
            'Status',
            'Date Created'
        ];
    }

    public function map($waitlist): array
    {
        return [
            $waitlist->name ?? null,
            $waitlist->email ?? null,
            // $waitlist->status ?? null,
            $waitlist->created_at ?? null
        ];
    }

    public function title(): string
    {
        return 'Waitlists';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Merge cells for main heading and set its value
                $sheet->mergeCells('A1:D1');
                $sheet->setCellValue('A1', 'Waitlists');

                // Set subheadings for the details
                $sheet->setCellValue('A2', 'Name');
                $sheet->setCellValue('B2', 'Email');
                // $sheet->setCellValue('C2', 'Status');
                $sheet->setCellValue('C2', 'Joined');


                // Apply styling to the main heading (A1:F1)
                $sheet->getStyle('A1:D1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 14, // Increase font size for the main heading
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'outline' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
                        ],
                    ],
                ]);

                // Apply styling to subheadings (A2:F2)
                $sheet->getStyle('A2:D2')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 12, // Font size for subheadings
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ]);

                // Center content for all data cells from A3:F100
                $sheet->getStyle('A3:D100')->applyFromArray([
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Optional: Auto-size columns to fit content
                foreach (range('A', 'D') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                // Set specific column widths
                $event->sheet->getDelegate()->getColumnDimension('A')->setWidth(15); // Name
                $event->sheet->getDelegate()->getColumnDimension('B')->setWidth(20); // Email
                // $event->sheet->getDelegate()->getColumnDimension('C')->setWidth(25); // Status
                $event->sheet->getDelegate()->getColumnDimension('C')->setWidth(20); // Created

            },
        ];
    }
}
