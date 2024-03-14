<?php

namespace MacropaySolutions\LaravelCrudWizard\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use MacropaySolutions\LaravelCrudWizard\Helpers\GeneralHelper;
use MacropaySolutions\LaravelCrudWizard\Models\BaseModel;

abstract class BaseResourceService implements ResourceServiceInterface
{
    protected BaseModel $model;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->setBaseModel();
    }

    public function getResourceName(): string
    {
        return $this->model::RESOURCE_NAME;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $identifier): bool
    {
        throw new \Exception('Forbidden');
    }

    /**
     * @inheritDoc
     */
    public function get(string $identifier, array $withRelations = [], bool $appendIndex = true): BaseModel
    {
        $model = $this->model::query()->where(
            $this->extractIdentifierConditions($identifier)
        )->firstOrFail()->appendIndexRequiredOnFilteringAttribute($appendIndex);
        $this->addRelationsToExistingModel($withRelations, $model);

        return $model;
    }

    /**
     * Use this only via api call (from controller) because it cals BaseModel::getIgnoreExternalCreateFor
     * For non-api create use $this->model::query()->createHydrated([...])
     * @inheritDoc
     */
    public function create(array $request): BaseModel
    {
        return $this->model::query()->createHydrated(GeneralHelper::filterDataByKeys($request, \array_diff(
            $this->model->getColumns(false),
            $this->model->getIgnoreExternalCreateFor()
        )));
    }

    /**
     * Use this only via api call (from controller) because it cals BaseModel::getIgnoreUpdateFor
     * For non-api update use $this->model->update([...]) or $this->get($identifier, appendIndex: false)->update([...])
     * @inheritDoc
     */
    public function update(string $identifier, array $request): BaseModel
    {
        ($model = $this->get($identifier, appendIndex: false))->update(
            GeneralHelper::filterDataByKeys($request, \array_diff(
                $this->model->getColumns(false),
                $this->model->getIgnoreUpdateFor()
            ))
        );

        return $model;
    }

    /**
     * @inheritDoc
     */
    public function list(array $request): Builder
    {
        return $this->model::query();
    }

    /**
     * @throws \Exception
     */
    public function isUpdateOrCreateAble(array $requestBody = []): bool
    {
        if ($this->model->incrementing) {
            return false;
        }

        foreach ($this->model->getPrimaryKeyFilter() as $column => $value) {
            if (!\is_array($value) && \is_string($column)) {
                if (!\array_key_exists($column, $requestBody)) {
                    return false;
                }

                continue;
            }

            if (!\array_key_exists(\reset($value), $requestBody)) {
                return false;
            }
        }

        return true;
    }

    public function getTableName(): string
    {
        return $this->model->getTable();
    }

    public function getIndexRequiredOnFiltering(): array
    {
        return $this->model->getIndexRequiredOnFilteringAttribute();
    }

    /**
     * @throws \Exception
     */
    abstract protected function setBaseModel(): void;

    protected function addRelationsToExistingModel(array $withRelations, BaseModel $model): void
    {
        foreach ($withRelations as $relation) {
            $model->setAttribute(Str::snake($relation), $model->{$relation}()->getResults());
        }
    }

    /**
     *         $exploded = \explode($this->model::COMPOSITE_PK_SEPARATOR, $identifier);
     *
     *         return [
     *             ['col1', \reset($exploded)],
     *             ['col2', \next($exploded)],
     *             ...
     *         ];
     * @throws \Exception
     */
    protected function extractIdentifierConditions(string $identifier): array
    {
        if (\strlen($this->model->getKeyName()) === 0) {
            throw new \Exception('Development error. extractIdentifierConditions function not defined for this model.');
        }

        return [
            [$this->model->getKeyName(), $identifier]
        ];
    }
}
