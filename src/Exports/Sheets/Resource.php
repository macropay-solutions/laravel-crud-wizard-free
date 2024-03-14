<?php

namespace MacropaySolutions\LaravelCrudWizard\Exports\Sheets;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use MacropaySolutions\LaravelCrudWizard\Models\BaseModel;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class Resource extends StringValueBinder implements
    FromCollection,
    WithTitle,
    WithStrictNullComparison,
    WithHeadings,
    WithStyles,
    ShouldAutoSize
{
    private BaseModel $baseModel;
    private Collection $collection;

    /**
     * @throws \Throwable
     */
    public function __construct(BaseModel $baseModel, Collection $collection)
    {
        $this->baseModel = $baseModel;
        $this->collection = $collection;
    }

    /**
     * @inheritDoc
     */
    public function collection(): Collection
    {
        return $this->collection;
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return $this->baseModel::RESOURCE_NAME;
    }

    public function headings(): array
    {
        return \array_keys((array)$this->collection->first());
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getDefaultRowDimension()->setRowHeight(15);

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Download Resource sheet error for ' . $this->baseModel::RESOURCE_NAME . ', error: ' .
            $e->getMessage());
    }
}
