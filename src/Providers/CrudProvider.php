<?php

namespace MacropaySolutions\LaravelCrudWizard\Providers;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class CrudProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        EloquentBuilder::macro(
            'createHydrated',
            /**
             * @throws \Throwable
             */
            function (...$arguments): Model {
                /** @var EloquentBuilder $this */
                $model = $this->create(...$arguments);
                $key = $model->getKey();

                if (\strlen($key) > 0) {
                    return $model::query()->useWritePdo()->findOrFail($key);
                }

                return $model::query()->useWritePdo()->where($model->getPrimaryKeyFilter())->firstOrFail();
            }
        );
    }
}
