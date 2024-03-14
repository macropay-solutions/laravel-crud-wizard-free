<?php

namespace MacropaySolutions\LaravelCrudWizard\Helpers;

use MacropaySolutions\LaravelCrudWizard\Models\BaseModel;

class ResourceHelper
{
    /**
     * @param array $modelFqnToControllerMap
     *     [BaseModelChild::class => ResourceControllerChild::class]
     * @throws \Throwable
     */
    public static function getResourceNameToControllerFQNMap(array $modelFqnToControllerMap): array
    {
        static $resourceNameToControllerFQNMap;

        if (isset($resourceNameToControllerFQNMap)) {
            return $resourceNameToControllerFQNMap;
        }

        $map = [];

        foreach ($modelFqnToControllerMap as $resourceFQN => $controllerFQN) {
            /** @var BaseModel $resourceFQN */
            if (\strlen($resourceFQN::RESOURCE_NAME) > 0 && \strlen($controllerFQN) > 0) {
                $map[$resourceFQN::RESOURCE_NAME] = $controllerFQN;
            }
        }

        return $resourceNameToControllerFQNMap = $map;
    }

    /**
     * @param array $modelFqnToControllerMap
     *     [BaseModelChild::class => ResourceControllerChild::class]
     */
    public static function getResourceControllerToModelFQNMap(array $modelFqnToControllerMap): array
    {
        static $resourceControllerToModelFQNMap;

        if (isset($resourceControllerToModelFQNMap)) {
            return $resourceControllerToModelFQNMap;
        }

        return $resourceControllerToModelFQNMap = \array_flip($modelFqnToControllerMap);
    }
}
