# laravel-crud-wizard-free

This is a stripped down untested version from the paid tested version [Laravel crud wizard](https://github.com/macropay-solutions/laravel-lumen-crud-wizard) (Tested on laravel/lumen 8, laravel 9, laravel 10 and it should work also on laravel 11).

## Install

    composer install macropay-solutions/laravel-crud-wizard-free


## Start using it

Register
\MacropaySolutions\LaravelCrudWizard\Providers\CrudProvider 
\MacropaySolutions\LaravelCrudWizard\Http\Middleware\UnescapedJsonMiddleware::class in lumen or laravel.

Create a constant in your code

    class DbCrudMap
    {
        public const MODEL_FQN_TO_CONTROLLER_MAP = [
            BaseModelChild::class => ResourceControllerTraitIncludedChild::class,
            ...
        ];
    }

Extend BaseModel and BaseResourceService and use ResourceControllerTrait in a BaseController (that you extend) and add this new resource to the above map.

The BaseController should call $this->init() from its construct.

Register the crud routes in your application using (for example in Laravel)

    try {
        foreach (
            ResourceHelper::getResourceNameToControllerFQNMap(DbCrudMap::MODEL_FQN_TO_CONTROLLER_MAP) as $resource => $controller
        ) {
            Route::get('/' . $resource, [$controller, 'list'])->name('apiinfo.list_' . $resource);
            Route::post('/' . $resource, [$controller, 'create'])->name('apiinfo.create_' . $resource);
            Route::put('/' . $resource . '/{identifier}', [$controller, 'update'])->name('apiinfo.update_' . $resource);
            Route::get('/' . $resource . '/{identifier}', [$controller, 'get'])->name('apiinfo.get_' . $resource);
            Route::delete('/' . $resource . '/{identifier}', [$controller, 'delete'])->name('apiinfo.delete_' . $resource);
            // Route::get('/' . $resource . '/{identifier}/{relation}', [$controller, 'listRelation']); // paid version only
        }
    } catch (Throwable $e) {
        \Illuminate\Support\Facades\Log::error($e->getMessage());
    }
    
for example for lumen:

    try {
        foreach (
            ResourceHelper::getResourceNameToControllerFQNMap(
                DbCrudMap::MODEL_FQN_TO_CONTROLLER_MAP
            ) as $resource => $controllerFqn
        ) {
            $controllerFqnExploded = \explode('\\', $controllerFqn);
            $controller = \end($controllerFqnExploded);
            //$router->get('/' . $resource . '/{identifier}/{relation}', [
            //    'as' => $resource . '.listRelation',
            //    'uses' => $controller . '@listRelation',
            //]); // paid version only
            $router->get('/' . $resource, [
                'as' => $resource . '.list',
                'uses' => $controller . '@list',
            ]);
            $router->post('/' . $resource, [
                'as' => $resource . '.create',
                'uses' => $controller . '@create',
            ]);
            $router->put('/' . $resource . '/{identifier}', [
                'as' => $resource . '.update',
                'uses' => $controller . '@update',
            ]);
            $router->get('/' . $resource . '/{identifier}', [
                'as' => $resource . '.get',
                'uses' => $controller . '@get',
            ]);
            $router->delete('/' . $resource . '/{identifier}', [
                'as' => $resource . '.delete',
                'uses' => $controller . '@delete',
            ]);
        }
    } catch (Throwable $e) {
        \Illuminate\Support\Facades\Log::error($e->getMessage());
    }

See also [Laravel crud wizard demo](https://github.com/macropay-solutions/laravel-crud-wizard-demo)

### I. Crud routes
```The identifier can be a primary key or a combination of primary keys with _ between them if the resource has a combined primary key!!!```

see \MacropaySolutions\LaravelCrudWizard\Models\BaseModel::COMPOSITE_PK_SEPARATOR


#### I.1 Create resource
**POST** /{resource}

headers:

      Authorization: Bearer ... // if needed. not coded in this lib
      
      Accept: application/json
      
      ContentType: application/json

body:

      {
         "column_name":"value",
         ...
      }

Json Response:

201:

    {
        "column_name":"value",
        ...
    }


400:

    {
        "message": "The given data was invalid.", // or other message
        "errors": {
            "column_name1": [
                "The column name 1 field is required."
            ],
            "column_name_2": [
               "The column name 2 field is required."
            ],
            ...
         }
    }

The above "errors" are optional and appear only for validation errors while "message" will always be present.


#### I.2 Get resource
**GET** /{resource}/{identifier}?withRelations[]=has_many_relation&withRelations[]=has_one_relation

headers:

      Authorization: Bearer ... // if needed. not coded in this lib
      
      Accept: application/json

Json Response:

200:

    {
        "identifier":"value",
        "column_name":"value",
        ...
        "index_required_on_filtering": [
           "column_name_1",
           "column_name2"
        ],
        "has_one_relation":{...},
        "has_many_relation":[
            {
                "id": ...,
                "name": "...",
                "pivot": {
                   "key1": 25,
                   "key2": 5
                }
            }
        ],
    }

400:

    {
        "message": ...
    }


The identifier can be composed by multiple identifiers for pivot resources that have composite primary key.
Example:/table1-table2-pivot/3_10

The relations will be retrieved as well when required. The relation keys CAN'T be used for filtering!!!

```index_required_on_filtering``` key CAN'T be used for filtering.

```pivot``` is optional and appears only on relations that are tied via a pivot.

#### I.3 List filtered resource
**GET** /{resource}?page=1&limit=10 // filters are available only in the paid version

**GET** /{resource}/{identifier}/{relation}?... // available only in paid version

headers:

      Authorization: Bearer ... // if needed. not coded in this lib
      
      Accept: application/json or application/xls

      InHeaderQuery: ...

Json Response:

200:

    {
        "index_required_on_filtering": [
           "column_name1",
           "column_name2"
        ],
        "current_page": 1, // not present when cursor is present in request 
        "data": [
            {
               "identifier":"value",
               "column_name":"value",
               ...
            }
        ],
        "from": 1, // not present when cursor is present in request
        "last_page": 1, // not present when cursor is present in request or when simplePaginate is true in controller or present in request
        "per_page": 10,
        "to": 1, // not present when cursor is present in request
        "total": 1, // not present when cursor is present in request or simplePaginate is true in controller or present in request
        "has_more_pages": bool,
        "cursor": "..." // present only when cursor is present in request
    }

and for application/xls: binary with contents from `data`

The reserved words / parameters that will be used as query params are:

        page,
        limit,
        simplePaginate
        cursor

Defaults:

    page=1;
    limit=10;
    simplePaginate is false by default and only its presence is check in request, not its value
    cursor is not defined

Obs.

    index_required_on_filtering key CAN'T be used for filtering.
    use ?cursor= for cursor pagination and ?simplePaginate=1 for simplePaginate. Use none of them for length aware paginator.

#### I.4 Update resource (or create)
**PUT** /{resource}/{identifier}

headers:

      Authorization: Bearer ... // if needed. not coded in this lib
      
      Accept: application/json
      
      ContentType: application/json

body:

      {
         "column_name":"value",
         ...
      }

Json Response:

200 | 201:

    {
        // all resource's fields
    }

400:

    {
        "message": "The given data was invalid.", // or other message
        "errors": {
            "column_name": [
               "The column name field is invalid."
            ],
            ...
        }
    }

The above "errors" are optional and appear only for validation errors while "message" will always be present.

The identifier can be composed by multiple identifiers for pivot resources that have composite primary key (and empty string primary key in their model).
Example:/resources/3_10

Update is not available on some resources.

**UpdateOrCreate** is available on resources that have their model defined with incrementing = false ONLY if the request contains all the keys from the primary key (found also in function getPrimaryKeyFilter).


#### I.5 Delete resource
**DELETE** /{resource}/{identifier}

headers:

      Authorization: Bearer ... // if needed. not coded in this lib

Json Response:

204:

    []

400:
    
    {
        "message": ...
    }

Delete is not available by default.
