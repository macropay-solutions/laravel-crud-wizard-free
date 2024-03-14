<?php

namespace MacropaySolutions\LaravelCrudWizard\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MacropaySolutions\LaravelCrudWizard\Helpers\GeneralHelper;

abstract class BaseModel extends Model
{
    public const RESOURCE_NAME = null;
    public const WITH_RELATIONS = [];
    public const CREATED_AT_FORMAT = 'Y-m-d H:i:s';
    public const UPDATED_AT_FORMAT = 'Y-m-d H:i:s';
    public const COMPOSITE_PK_SEPARATOR = '_';
    public $timestamps = false;
    protected array $ignoreUpdateFor = [];
    protected array $ignoreExternalCreateFor = [];
    protected array $allowNonExternalUpdatesFor = [];
    protected bool $indexRequiredOnFiltering = true;
    protected $hidden = [
        'laravel_through_key'
    ];

    /**
     * @inheritdoc
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->append('primary_key_identifier');
    }

    public function getColumns(bool $includingPrimary = true): array
    {
        $columns = $includingPrimary ?
            /** $this->primaryKey can be null or empty string so, it must not be included */
            \array_merge(\array_filter([$this->primaryKey]), $this->fillable) :
            /** $this->primaryKey may be in the fillable so, it must be included */
            \array_diff($this->fillable, \array_diff([$this->primaryKey], $this->fillable));

        if (
            \env('LIVE_MODE') === false
            && [] !== $reservedUsed = \array_intersect(['page', 'limit'], $columns)
        ) {
            Log::warning('LaravelCrudWizard warning: the resource ' . $this::RESOURCE_NAME .
                ' uses reserved words as columns: ' . \implode(',', $reservedUsed));
        }

        return \array_unique($columns);
    }

    /**
     * called via $this->append(['index_required_on_filtering'])
     */
    public function getIndexRequiredOnFilteringAttribute(): array
    {
        return $this->indexRequiredOnFiltering ? $this->retrieveFirstSeqIndexedColumns() : [];
    }

    /**
     * called via $this->append(['primary_key_identifier'])
     * @throws \Exception
     */
    public function getPrimaryKeyIdentifierAttribute(): string
    {
        return \implode(
            $this::COMPOSITE_PK_SEPARATOR, \array_map(
            fn (mixed $value): string => (string)(\is_array($value) ? \last($value) : $value),
            $this->getPrimaryKeyFilter()
        ));
    }

    public function appendIndexRequiredOnFilteringAttribute(bool $appendIndex = true): self
    {
        return $appendIndex && $this->indexRequiredOnFiltering ? $this->append(['index_required_on_filtering']) : $this;
    }

    public function getIgnoreUpdateFor(): array
    {
        return $this->ignoreUpdateFor;
    }

    public function getIgnoreExternalCreateFor(): array
    {
        return (string)$this->getKeyName() === '' ?
            $this->ignoreExternalCreateFor : \array_merge(
                $this->ignoreExternalCreateFor,
                \array_diff([$this->getKeyName()], $this->getFillable())
            );
    }

    /**
     * @inheritDoc
     */
    public function setAttribute(mixed $key, mixed $value): mixed
    {
        if (
            $this->exists
            && \in_array($key, \array_diff($this->ignoreUpdateFor, $this->allowNonExternalUpdatesFor))
        ) {
            if ($value !== ($attribute = $this->getAttribute($key))) {
                Log::error(
                    'Development bug. Tried to update an ignored column ' . $key . ' on ' . \get_class($this) .
                    ' with value: "' . $value . '" on ' . $this->getKeyName() . ' = ' . $this->getKey(
                    ) . '. BACKTRACE: ' .
                    \json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3))
                );
            }

            $value = $attribute;
        }

        return parent::setAttribute($key, $value);
    }

    public static function boot(): void
    {
        parent::boot();
        static::creating(function (BaseModel $baseModel): void {
            $baseModel->setCreatedAt(Carbon::now()->format($baseModel::CREATED_AT_FORMAT));
        });

        static::updating(function (BaseModel $baseModel): void {
            $updatedAtColumn = $baseModel->getUpdatedAtColumn();

            if ('' === $baseModel->getAttribute($updatedAtColumn)) {
                $baseModel->setUpdatedAt($baseModel->getOriginal($updatedAtColumn));

                return;
            }

            $baseModel->setUpdatedAt(Carbon::now()->format($baseModel::UPDATED_AT_FORMAT));
        });
    }

    /**
     * @throws \Exception
     */
    public function getPrimaryKeyFilter(): array
    {
        if (\strlen($this->getKeyName()) === 0) {
            throw new \Exception('Development error. getPrimaryKeyFilter function not defined for this model.');
        }

        return [
            [$this->getKeyName(), $this->getAttribute($this->getKeyName())],
        ];
    }

    /**
     * Overwrite this for sqlite and sqlsrv db drivers if needed
     * @throws \Throwable
     */
    public function retrieveIndexesFromTable(): Collection
    {
        if (!\in_array($driver = $this->getConnection()->getDriverName(), ['mariadb', 'mysql', 'pgsql'], true)) {
            throw new \Exception('Unsupported database driver ' . $driver . ' for retrieving indexes.');
        }

        static $result;
        $callback =
            /**
             * @throws \Throwable
             */
            function () use ($driver): array {
                $tableName = $this->getConnection()->getTablePrefix() . $this->getTable();
                return DB::connection($this->getConnectionName())->select([
                    'mariadb', 'mysql' => 'SHOW INDEX FROM ' . $tableName,
                    'pgsql' => "SELECT
                            array_position(ix.indkey, a.attnum) + 1 as Seq_in_index,
                            i.relname as Key_name,
                            a.attname as Column_names
                        from
                            pg_class t,
                            pg_class i,
                            pg_index ix,
                            pg_attribute a
                        where
                            t.oid = ix.indrelid
                            and i.oid = ix.indexrelid
                            and a.attrelid = t.oid
                            and a.attnum = any(ix.indkey)
                            and t.relkind = 'r'
                            and t.relname = " . $tableName,
                ][$driver]);
            };

        try {
            return $result[$this->getConnectionName() . $this->getTable()] ??= \collect(Cache::remember(
                $this::RESOURCE_NAME . 'IndexesForFiltering',
                Carbon::now()->addDay(),
                $callback
            ));
        } catch (\Throwable $e) {
            return \collect($callback());
        }
    }

    public function retrieveFirstSeqIndexedColumns(): array
    {
        try {
            static $indexes;

            $result = $indexes[$this->getConnectionName() . $this->getTable()] ??=
                $this->retrieveIndexesFromTable()->where('Seq_in_index', 1)->pluck('Column_name')->all();
        } catch (\Throwable $e) {
            if (GeneralHelper::isDebug()) {
                Log::error($this::class . ' error getting indexes: ' . $e->getMessage());
            }
        }

        if (\count($result ?? []) === 0) {
            $this->indexRequiredOnFiltering = false;
        }

        return \array_values(\array_unique($result ?? []));
    }

    /**
     * @throws \Throwable
     */
    public function getColumnIndex(string $column, bool $asFirst = false): string
    {
        $collection = $this->retrieveIndexesFromTable()->where('Column_name', $column);

        if ($asFirst) {
            $collection->where('Seq_in_index', 1);
        }

        return $collection->firstOrFail()->Key_name;
    }

    /**
     * @inheritDoc
     */
    protected function setKeysForSelectQuery($query)
    {
        $this->setKeysForSaveQuery($query);
    }

    /**
     * @inheritDoc
     */
    protected function setKeysForSaveQuery($query)
    {
        return $query->where($this->getPrimaryKeyFilter());
    }
}
