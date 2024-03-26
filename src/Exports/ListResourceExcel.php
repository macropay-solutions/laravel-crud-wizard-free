<?php

namespace MacropaySolutions\LaravelCrudWizard\Exports;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use MacropaySolutions\LaravelCrudWizard\Exports\Sheets\Resource;
use MacropaySolutions\LaravelCrudWizard\Helpers\GeneralHelper;
use MacropaySolutions\LaravelCrudWizard\Models\BaseModel;

class ListResourceExcel implements WithMultipleSheets
{
    use Exportable;

    private BaseModel $baseModel;
    private array $data;
    private array $withRelations;
    private ?BaseModel $baseResourceModel;

    public function __construct(
        BaseModel $baseModel,
        LengthAwarePaginator | Paginator | CursorPaginator $lap,
        array $withRelations = [],
        ?BaseModel $baseResourceModel = null
    ) {
        $this->baseModel = $baseModel;
        $this->baseResourceModel = $baseResourceModel;
        $this->data = $lap->items();
        $this->withRelations = \array_values($withRelations);
    }

    /**
     * @throws \Throwable
     */
    public function sheets(): array
    {
        $sheets = [];

        if ($this->baseResourceModel instanceof BaseModel && (string)$this->baseResourceModel::RESOURCE_NAME !== '') {
            $sheets [] = GeneralHelper::app(
                Resource::class,
                [
                    'baseModel' => $this->baseResourceModel,
                    'collection' => \collect([$this->baseResourceModel->toArray()])
                ]
            );

            $this->removeMainResourceFromWithRelations();
        }

        $sheets[] = GeneralHelper::app(
            Resource::class,
            ['baseModel' => $this->baseModel, 'collection' => $this->getCollection()]
        );

        foreach ($this->withRelations as $relationName) {
            try {
                /** @var Relation $relationsRelation */
                $relationsRelation = $this->baseModel->{$relationName}();
                $sheets[] = GeneralHelper::app(Resource::class, [
                    'baseModel' => $relationsRelation->getRelated(),
                    'collection' => $this->getRelationCollectionWithoutDuplicates($relationName)
                ]);
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $sheets;
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Download ListResourceExcel error for ' . $this->baseModel::RESOURCE_NAME . ', error: ' .
            $e->getMessage());
    }

    private function getCollection(): Collection
    {
        $result = [];

        foreach ($this->data as $resourceWithRelationsModel) {
            /** @var BaseModel $resourceWithRelationsModel */
            $result[] = \array_diff_key($resourceWithRelationsModel->attributesToArray(), \array_flip(\array_map(
                fn (string $relation): string => Str::snake($relation),
                $this->withRelations
            )));
        }

        return \collect($result);
    }

    /**
     * @throws \Throwable
     */
    private function getRelationCollectionWithoutDuplicates(string $key): Collection
    {
        $result = [];

        foreach ($this->data as $resourceWithRelationsModel) {
            /** @var BaseModel $resourceWithRelationsModel */
            $properties = $resourceWithRelationsModel->toArray();
            $snakeKey = Str::snake($key);

            if (!isset($properties[$snakeKey]) || !\is_array($properties[$snakeKey])) {
                continue;
            }

            if (!\is_numeric(\array_key_first($properties[$snakeKey]))) {
                $result[] = \array_map(
                    fn (mixed $val): string => (string)(\is_array($val) ? 'array not exported' : ($val ?? 'null')),
                    $properties[$snakeKey]
                );

                continue;
            }

            foreach ($properties[$snakeKey] as $relatedRow) {
                $result[] = \array_map(
                    fn(mixed $val): string => (string)(\is_array($val) ? 'array not exported' : ($val ?? 'null')),
                    $relatedRow
                );
            }
        }

        return \collect(\array_map('unserialize', \array_unique(\array_map('serialize', $result))));
    }

    private function removeMainResourceFromWithRelations(): void
    {
        foreach ($this->baseModel::WITH_RELATIONS as $relationName) {
            try {
                if (
                    \get_class($this->baseModel->{$relationName}()
                        ->getRelated()) === \get_class($this->baseResourceModel)
                ) {
                    $this->withRelations = \array_values(
                        \array_diff(
                            $this->withRelations,
                            [$relationName]
                        )
                    );

                    return;
                }
            } catch (\Throwable $e) {
            }
        }
    }
}
