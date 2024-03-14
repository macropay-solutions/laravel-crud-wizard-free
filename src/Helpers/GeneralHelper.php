<?php

namespace MacropaySolutions\LaravelCrudWizard\Helpers;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class GeneralHelper
{
    public static function filterDataByKeys(array $data, array $keys): array
    {
        return \array_intersect_key($data, \array_flip($keys));
    }

    public static function getSafeErrorMessage(\Throwable $e, string $messagePrefix = 'apicrud'): string
    {
        $message = \strtolower($e->getMessage());

        if ($e instanceof QueryException || \str_contains($message, 'sql') || \str_contains($message, 'on line')) {
            Log::error($messagePrefix . ', error: ' . $e->getMessage());

            if (false !== $duplicateEntryPos = \stripos($e->getMessage(), 'Duplicate entry')) {
                return \substr(
                    $e->getMessage(),
                    $duplicateEntryPos,
                    \stripos($e->getMessage(), 'for key') - $duplicateEntryPos - 1
                );
            }

            return 'Something went wrong. Please contact us mentioning current time.';
        }

        if ($e instanceof ModelNotFoundException) {
            return ($e->getModel()::RESOURCE_NAME ?? 'Resource') . ' not found.';
        }

        return $e->getMessage();
    }

    /**
     * @return \Closure|Container|mixed|object|null
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public static function app(mixed $abstract = null, array $parameters = []): mixed
    {
        if (is_null($abstract)) {
            return Container::getInstance();
        }

        return Container::getInstance()->make($abstract, $parameters);
    }

    public static function isDebug(): bool
    {
        return \env('LIVE_MODE') === false && \function_exists('request') && null !== \request('sqlDebug');
    }
}
