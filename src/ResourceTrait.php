<?php

namespace Crystoline\Resource;

use Crystoline\Resource\Interfaces\IFileUpload;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\File\Exception\NoFileException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rest Api trait for Api Controller. Provide CRUD.
 */
trait ResourceTrait
{

    protected $statusCodes = [
        'done' => 200,
        'created' => 201,
        'removed' => 204,
        'not_valid' => 400,
        'not_found' => 404,
        'not_record' => 407,
        'conflict' => 409,
        'permissions' => 401,
        'server_error' => 500,
    ];


    /**
     * @var int Number of pages
     */
    protected $pages = 50;

    /**
     * get The Model name used. with full namespace
     * @return string
     */
    public static function getModel(): string
    {
        return Model::class;
    }

    /**
     * Return array of searchable fields
     * @return array
     */
    public static function searchable(): array
    {
        return [];
    }

    /**
     * Filter data using request
     * @param Request $request
     * @param Builder $query
     * @return ResourceQueryFilterCollection
     */
    public static function filter(Request $request, Builder $query): ResourceQueryFilterCollection
    {
        return new ResourceQueryFilterCollection();
    }

    /**
     * @return array
     */
    public static function getValidationRules(): array
    {
        return [];
    }

    /**
     * @param string $method
     * @return string
     */
    public static function getMethodName(string $method): string
    {
        $method_a = explode('::', $method);
        return array_key_exists(1, $method_a) ? $method_a[1] : '';
    }


    /**
     * @param Request $request
     * @return Factory|View
     */
    public function index(Request $request)
    {

        /** @var Model $m */
        @$m = self::getModel();

        //$table = (new $m())->getTable();

        @$data = $m::query();//select(["{$table}.*"]);
        $pages = self::getPages();
        $searchables = self::searchable();
        $orderBy = self::orderBy() ?: [];
        $filters = self::doFilter($request, $data);

        self::doSearch($request, $data, $searchables);
        self::doOrderBy($request, $data, $orderBy);
        $model_data = clone $data;
        $data = self::paginate($request, $data, $pages);

        if ($data instanceof Builder) {
            $data = $data->get();
        }

        $this->beforeList($data);
        $view_data  = $this-> appendDependencies($request, __METHOD__, ['data' => $data, 'filters' => $filters], $model_data);
        return $this->loadView(self::getMethodName(__METHOD__), $view_data);
    }

    /**
     * return number pages for pagination
     * @return int
     */
    public static function getPages(): int
    {
        return 50;
    }

    /**
     * @return array
     */
    public static function orderBy(): array
    {
        return [
        ];
    }

    private static function doFilter(Request $request, Builder $query)
    {
        $filters = self::filter($request, $query);
        $filters->performFilterActions($request, $query);
        return $filters;
    }

    /**
     * Perform wild-card search
     * @param Request $request
     * @param Builder $builder
     * @param $searchables
     * return none, Builder passed by reference
     */
    public static function doSearch(Request $request, Builder $builder, $searchables) /*:Builder*/
    {
        $builder->where(function (Builder $builder) use ($request, $searchables) {
            if ($search = $request->input('search')) {
                $keywords = explode(' ', trim($search));
                if ($searchables) {
                    foreach ($searchables as $searchable) {
                        foreach ($keywords as $keyword) {
                            $builder->orWhere($searchable, 'like', "%{$keyword}%");
                        }
                    }
                }
            }
            if ($search = $request->input('qsearch')) {
                if ($searchables) {
                    foreach ($searchables as $searchable) {
                        $builder->orWhere($searchable, 'like', "%{$search}%");
                    }
                }
            }
        });

        //return $builder;
    }

    /**
     * Order Data
     * @param Request $request
     * @param Builder $builder
     * @param array $orderBy
     */
    public static function doOrderBy(Request $request, Builder $builder, array $orderBy)
    {
        if ($orderBy) {
            foreach ($orderBy as $field => $direction) {
                $builder->orderBy($field, $direction);
            }
        }
    }

    /**
     * Paginate Data
     * @param Request $request
     * @param Builder $data
     * @param int $pages
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|Builder
     */
    public static function paginate(Request $request, $data, $pages = 50)
    {
        $should_paginate = $request->input('paginate', 'yes');

        if ('yes' == $should_paginate) {
            $data = $data->paginate($request->input('pages', $pages));
        }

        return $data;
    }

    /**
     * Perform action before data list
     * @param $data
     */
    public function beforeList($data)
    {
    }

    /**
     * @param $method_name
     * @param array $data
     * @return Factory|View
     */
    private function loadView($method_name, $data = [])
    {

        $class_name = basename(__CLASS__);
        $views = $this->getViews();
        //$view_data =  [ 'data' => $data];
        //dd([basename(__CLASS__), $method_name]);
        if (array_key_exists($method_name, $views)) {
            return view($views[$method_name], $data);
        } elseif (file_exists(resource_path(strtolower('views' . $class_name . '.' . $method_name . '.blade.php')))) {
            return view(resource_path(strtolower($class_name . '.' . $method_name . '.blade.php')), $data);
        }

        throw new NoFileException('View not fount for ' . __NAMESPACE__ . '\\' . $class_name . '@' . $method_name, 404);
    }

    public function create(Request $request)
    {
        $data  = $this-> appendDependencies($request, __METHOD__, []);
        return $this->loadView(self::getMethodName(__METHOD__), $data);
    }

    public function edit(Request $request, $id)
    {
        /** @var Model $m */
        $m = self::getModel();
        $model = $m::find($id);
        $model_data  = $this-> appendDependencies($request, __METHOD__, ['data' => $model]);
        return $this->loadView(self::getMethodName(__METHOD__), $model_data);
    }

    public function getViews()
    {
        return [
            'index' => ResourceView::defaultIndex()
        ];
    }

    /**
     * @param Request $request
     * @param Model|null $model_data
     * @return array
     */
    public function dependencies(Request $request, Model $model_data = null): array
    {
        return [
            // 'create'  => [ 'user' => new ResourceDependency(User::query(), 'name', 'id') ]
        ];
    }
    private function appendDependencies(Request $request, $method, $view_data = array(), $nodel_data= null){
        $method_name = self::getMethodName($method);
        $all_dependencies = $this->dependencies($request, $nodel_data);
        if(array_key_exists($method_name, $all_dependencies)){
            $dependencies = $all_dependencies[$method_name];
            /** @var ResourceDependency $dependency */
            foreach ($dependencies as $name => $dependency){
                $view_data[$name] = $dependency instanceof ResourceDependency? $dependency->toArray(): $dependency;
            }
        }
        return $view_data;
    }

    /**
     * Show records.
     * @param int $id
     * @return Factory|View
     */
    public function show(int $id)
    {
        /** @var Model $m */
        $m = self::getModel();
        $data = $m::query()->find($id);

        if (is_null($data)) {
            $this->loadView(self::getMethodName(__METHOD__))->with(['message' => 'Record was not found']);
            //return $this->respond(['message' => 'Record was not found'], self::$STATUS_CODE_NOT_FOUND);
        }
        $this->beforeShow($data);

        return $this->loadView(self::getMethodName(__METHOD__), ['data' => $data]);
    }

    /**
     * Perform action before data show
     * @param $data
     */
    public function beforeShow($data)
    {
    }

    /**
     * Store Record.
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var Model $m */
        $m = self::getModel();
        $rules = self::getValidationRules();
        $message = self::getValidationMessages();

        $validator = Validator::make($request->all(), $rules, $message);
        if ($validator->fails()) {
            return Redirect::to($request->url() . '/create')->withErrors($validator)->with(['message' => 'Error with form']);

        }

        DB::beginTransaction();

        //try{
        if (!$this->beforeStore($request)) {
            DB::rollback();
            Redirect::to($request->url())->with(['message' => 'could not create record (Duplicate Record)\'']);

            // return $this->respond(['message' => 'could not create record (Duplicate Record)'], self::$STATUS_CODE_SERVER_ERROR);
        }
        self::doUpload($request);
        $input = $request->input();


        /*unset($input['school']);
        unset($input['staff']);*/

        //dump($input);
        $data = $m::query()->create($input);

        //catch (\Exception $exception){
        //DB::rollback();
        //todo remove Exception message
        //return $this->respond( ['message' => 'An error occurred while creating record: '.$exception->getMessage().', Line:'.$exception->getFile().'/'.$exception->getLine()], self::$STATUS_CODE_CONFLICT);
        //}
        if (!$this->afterStore($request, $data)) {
            DB::rollback();
            return Redirect::to($request->url())->with(['message' => 'could not successfully create record']);
            //return $this->respond(['message' => 'could not successfully create record'], self::$STATUS_CODE_SERVER_ERROR);
        }

        DB::commit();


        $this->beforeShow($data);
        return Redirect::to($request->url())->with(['message' => 'Item was added in the databases', 'type' => 'success']);
        //return $this->respond($data, self::$STATUS_CODE_CREATED);
    }

    /**
     * @return array
     */
    public static function getValidationMessages(): array
    {
        return [];
    }

    /**
     * Perform action before data store
     * @param Request $request
     * @return bool
     */
    public function beforeStore(Request $request): bool
    {
        return true;
    }

    /**
     * Perform file upload for request
     * @param Request $request
     * @param Model|null $object
     */
    public static function doUpload(Request $request, Model $object = null)
    {
        $data = $request->all();
        foreach ($data as $key => $val) {

            if ($request->hasFile($key) and $request->file($key)->isValid()) {

                $original = $object->$key ?? null;

                $interfaces = class_implements(self::class);
                $base = (isset($interfaces[IFileUpload::class])) ? self::fileBasePath($request) : '';
                if ($base) {
                    $base = trim($base, '/,\\') . '/';
                }
                $path = $request->$key->store('public/' . $base . $key);
                $path = str_replace('public/', 'storage/', $path);

                $path_url = asset($path);

                $request->files->remove($key);
                $request->merge([$key => $path_url]);

                if (!is_null($original)) {
                    Storage::delete(str_replace('storage/', 'public/', $original));
                }
            }
        }

    }

    /**
     * Perform action after data store
     * @param Request $request
     * @param $data
     * @return bool
     */
    public function afterStore(Request $request, $data): bool
    {
        return true;
    }

    /**
     * Update Record.
     *
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        /** @var Model $m */
        $m = self::getModel();
        $model = $m::query()->find($id);

        if (is_null($model)) {
            throw new ModelNotFoundException('Record was not found', 404);
        }

        $rules = self::getValidationRulesForUpdate($model);
        $message = self::getValidationMessages();

        $validator = Validator::make($request->all(), $rules, $message);
        if ($validator->fails()) {
            return Redirect::to($request->url())->withErrors($validator)->withErrors(['message' => "Form error"]);
        }

        DB::beginTransaction();
        if (!$this->beforeUpdate($request)) {
            DB::rollback();
            return Redirect::to($request->url())->with(['message' => 'could not update record']);
        }
        self::doUpload($request, $model);
        $fieldsToUpdate = (method_exists($model, 'fieldsToUpdate')
            and !empty(self::fieldsToUpdate())) ?
            $request->only(self::fieldsToUpdate()) : $request->input();

        try {
            $model->update($fieldsToUpdate);
        } catch (\Exception $exception) {
            DB::rollback();
            //todo remove Exception message
            return Redirect::to($request->url())->with(['message' => 'An error occurred while updating record: ']);
        }
        if (!$this->afterUpdate($request, $model)) {
            DB::rollback();
            return Redirect::to($request->url())->with(['message' => 'could not successfully update record']);
        }

        DB::commit();

        return Redirect::to(dirname($request->url()))->with(['message' => 'Update was successful', 'type' => 'success']);
    }

    /**
     * @param Model $model
     * @return array
     */
    public static function getValidationRulesForUpdate(Model $model)
    {
        $id = $model->id;
        $rules = self::getValidationRules();
        $fields = self::getUniqueFields();
        foreach ($fields as $field) {
            if (isset($rules[$field])) {
                $rules[$field] .= ',' . $id;
            }
        }
        return $rules;
    }

    /**
     * Return array of unique field. Used for validation
     * @return array
     */
    public static function getUniqueFields(): array
    {
        return [];
    }

    /**
     * Run before update action
     * @param Request $request
     * @return bool
     */
    public function beforeUpdate(Request $request): bool
    {
        return true;
    }

    /**
     * get fields to be update
     * @return array
     */
    public static function fieldsToUpdate(): array
    {
        return [];
    }

    /**
     * Run after update action
     * @param Request $request
     * @param $data
     * @return bool
     */
    public function afterUpdate(Request $request, $data): bool
    {
        return true;
    }

    /**
     * Delete Record.
     *
     * @param Request $request
     * @param int $id
     *
     * @return JsonResponse|RedirectResponse
     */
    public function destroy(Request $request, int $id)
    {
        /** @var Model $m */
        $m = self::getModel();
        if (is_null($m::query()->find($id))) {
            return Redirect::to(dirname($request->url()))->with(['message' => 'record was not found']);
        }
        try {
            $m::destroy($id);
        } catch (\Exception $exception) {

        }
        return Redirect::to(dirname($request->url()));
    }

    /**
     * Perform action before data deletion
     * @param Request $request
     * @return bool
     */
    public function beforeDelete(Request $request): bool
    {
        return true;
    }

    /**
     * @param $status
     * @param array $data
     *
     * @return JsonResponse
     */
    protected function respond($status, array $data = []): JsonResponse
    {
        return Response::json($data, $this->statusCodes[$status]);
    }


}
