<?php

namespace MacropaySolutions\LaravelCrudWizard\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Excel;
use MacropaySolutions\LaravelCrudWizard\Exceptions\CrudValidationException;
use MacropaySolutions\LaravelCrudWizard\Exports\ListResourceExcel;
use MacropaySolutions\LaravelCrudWizard\Helpers\GeneralHelper;
use MacropaySolutions\LaravelCrudWizard\Helpers\ResourceHelper;
use MacropaySolutions\LaravelCrudWizard\Models\BaseModel;
use MacropaySolutions\LaravelCrudWizard\Services\ResourceServiceInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Call $this->>init() on the constructor where this trait is used
 */
trait ResourceControllerTrait
{
    protected string $label = '';
    protected bool $simplePaginate = false;
    protected array $modelFqnToControllerMap = [];
    protected ResourceServiceInterface $resourceService;
    protected array $paginationKeys = [
        'current_page',
        'data',
        'from',
        'last_page',
        'per_page',
        'to',
        'total',
    ];

    /**
     * @throws \Throwable
     */
    protected function init(): void
    {
        $this->setModelFqnToControllerMap();
        $this->setResourceService();
        $this->label = $this->resourceService->getResourceName();
    }

    public function list(Request $request): Response
    {
        $allRequest = $request->all();
        $this->setSimplePaginate($allRequest);

        return $this->handleList($allRequest, $request);
    }

    public function create(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateCreateRequest($request);

            return GeneralHelper::app(JsonResponse::class, [
                'data' => $this->resourceService->create($validated)->toArray(),
                'status' => 201
            ]);
        } catch (ValidationException | CrudValidationException $e) {
            return GeneralHelper::app(JsonResponse::class, [
                'data' => [
                    'message' => $e->getMessage(),
                    'errors' => $e->errors()
                ],
                'status' => 400
            ]);
        } catch (\Throwable $e) {
            Log::error($this->label . ' create error = ' . $e->getMessage());

            return GeneralHelper::app(JsonResponse::class, [
                'data' => ['message' => GeneralHelper::getSafeErrorMessage($e)],
                'status' => 400
            ]);
        }
    }

    public function get(string $identifier, Request $request): JsonResponse
    {
        try {
            return GeneralHelper::app(JsonResponse::class, [
                'data' => $this->resourceService->get(
                    $identifier,
                    $this->getFilteredRelations((array)$request->get('withRelations'))
                )->toArray(),
                'status' => 200
            ]);
        } catch (\Throwable $e) {
            Log::error($this->label . ' get for identifier: ' . $identifier . ', error = ' . $e->getMessage());

            return GeneralHelper::app(JsonResponse::class, [
                'data' => ['message' => GeneralHelper::getSafeErrorMessage($e)],
                'status' => 400
            ]);
        }
    }

    public function update(string $identifier, Request $request): JsonResponse
    {
        try {
            $validated = $this->validateUpdateRequest($request);

            try {
                return GeneralHelper::app(JsonResponse::class, [
                    'data' => $this->resourceService->update($identifier, $validated)->toArray(),
                    'status' => 200
                ]);
            } catch (ModelNotFoundException $e) {
                if (!$this->resourceService->isUpdateOrCreateAble($request->all())) {
                    throw $e;
                }

                return $this->create($request);
            }
        } catch (ValidationException | CrudValidationException $e) {
            return GeneralHelper::app(JsonResponse::class, [
                'data' => [
                    'message' => $e->getMessage(),
                    'errors' => $e->errors()
                ],
                'status' => 400
            ]);
        } catch (\Throwable $e) {
            Log::error($this->label . ' update for identifier: ' . $identifier . ', error = ' . $e->getMessage());

            return GeneralHelper::app(JsonResponse::class, [
                'data' => ['message' => GeneralHelper::getSafeErrorMessage($e)],
                'status' => 400
            ]);
        }
    }

    public function delete(string $identifier): JsonResponse
    {
        try {
            return GeneralHelper::app(JsonResponse::class, [
                'status' => $this->resourceService->delete($identifier) ? 204 : 400
            ]);
        } catch (\Throwable $e) {
            Log::error($this->label . ' delete for identifier: ' . $identifier . ', error = ' . $e->getMessage());

            return GeneralHelper::app(JsonResponse::class, [
                'data' => ['message' => GeneralHelper::getSafeErrorMessage($e)],
                'status' => 400
            ]);
        }
    }

    /**
     * @throws \Exception
     */
    protected function getFilteredRelations(array $relations, ?BaseModel $baseModel = null): array
    {
        return \array_intersect(($baseModel instanceof BaseModel ?
            $baseModel :
            $this->getResourceAsModelFQN())::WITH_RELATIONS, $relations);
    }

    protected function handleList(array $allRequest, Request $request): Response
    {
        try {
            $paginator = $this->resourceService->list(
                $allRequest
            )->{$this->simplePaginate ? 'simplePaginate' : 'paginate'}(
                max((int)($allRequest['limit'] ?? 10), 1),
                ['*'],
                'page',
                \max((int)($allRequest['page'] ?? 1), 1)
            );

            if ($request->header('Accept') === 'application/xls') {
                return $this->downloadXLS($paginator);
            }

            if ([] !== $indexRequiredOnFiltering = $this->resourceService->getIndexRequiredOnFiltering()) {
                $appends['index_required_on_filtering'] = $indexRequiredOnFiltering;
            }

            return $this->getJsonResponse($paginator, $appends ?? []);
        } catch (\Throwable $e) {
            if (isset($allRequest['logError'])) {
                Log::error($this->label . ' list for ' . \json_encode($allRequest) . ', error = ' . $e->getMessage());
            }

            return $this->getEmptyPaginatedResponse($allRequest);
        }
    }

    /**
     * @throws \Exception
     */
    protected function getResourceAsModelFQN(): string
    {
        $resource = ResourceHelper::getResourceControllerToModelFQNMap($this->modelFqnToControllerMap)[$this::class] ??
            '';

        if (\strlen($resource) > 0) {
            return $resource;
        }

        throw new \Exception('Could not getResourceAsModelFQN.');
    }


    protected function getEmptyPaginatedResponse(array $request): JsonResponse
    {
        $data = [
            'items' => [],
            'perPage' => \max(0, $request['limit'] ?? 10),
            'currentPage' => 1,
        ];

        return $this->getJsonResponse(
            $this->simplePaginate ?
                GeneralHelper::app(Paginator::class, $data) :
                GeneralHelper::app(LengthAwarePaginator::class, \array_merge($data, ['total' => 0])),
            ['sums' => [], 'avgs' => [], 'mins' => [], 'maxs' => []]
        );
    }

    protected function getJsonResponse(LengthAwarePaginator | Paginator $paginator, array $appends = []): JsonResponse
    {
        return GeneralHelper::app(JsonResponse::class, [
            'data' => \array_merge($appends, GeneralHelper::filterDataByKeys(
                $paginator->toArray(),
                $this->paginationKeys
            )),
            'status' => 200
        ]);
    }

    /**
     * @throws \Throwable
     */
    protected function validateCreateRequest(Request $request): array
    {
        throw new \Exception('Development error. validateCreateRequest not implemented');
    }

    /**
     * @throws \Throwable
     */
    protected function validateUpdateRequest(Request $request): array
    {
        throw new \Exception('Development error. validateUpdateRequest not implemented');
    }

    /**
     * @throws \Throwable
     */
    protected function setResourceService(): void
    {
        throw new \Exception('Development error. setResourceService not implemented');
    }

    /**
     * @throws \Throwable
     */
    protected function setModelFqnToControllerMap(): void
    {
        throw new \Exception('Development error. setModelFqnToControllerMap not implemented');
    }

    protected function getDownloadHeaders(): array
    {
        return [];
    }

    protected function downloadXLS(LengthAwarePaginator | Paginator $paginator): Response
    {
        /** @var ListResourceExcel $exporter */
        $exporter = GeneralHelper::app(ListResourceExcel::class, [
            'baseModel' => $relatedModel ?? GeneralHelper::app($this->getResourceAsModelFQN()),
            'lap' => $paginator
        ]);

        return $exporter->download(
            $this->label . '.xls',
            Excel::XLS,
            $this->getDownloadHeaders()
        );
    }

    protected function setSimplePaginate(array $allRequest): void
    {
        $this->simplePaginate = isset($allRequest['simplePaginate']) ? true : $this->simplePaginate;
    }
}
