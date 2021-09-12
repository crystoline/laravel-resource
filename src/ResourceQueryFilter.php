<?php
/**
 * Created by PhpStorm.
 * User: cryst
 * Date: 19/03/31
 * Time: 1:44 PM
 */

namespace Crystoline\Resource;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ResourceQueryFilter extends Collection
{

    const FILTER_TYPE_SELECT = 'select';
    const FILTER_TYPE_RADIO = 'radio';
    const FILTER_TYPE_TEXT = 'text';
    const FILTER_TYPE_DATE = 'date';
    const FILTER_TYPE_CHECKBOX = 'checkbox';
    const FILTER_TYPE_NUMBER = 'number';
    const FILTER_TYPE_EMAIL = 'email';
    private $callback;
    public $type;


    /**
     * ResourceQueryFilter constructor.
     * @param $type
     * @param $items
     * @param $callback
     */
    public function __construct($type, $items, $callback = null )
    {
        $this->callback = $callback;
        parent::__construct($items);
        $this->type = $type;
    }

    /**
     * @param Request $request
     * @param Builder $builder
     */
    public function action(Request $request, Builder $builder)
    {
        $callback = $this->callback;
        if(is_callable($callback)){
            $callback($request, $builder);
        }
    }

    public static function Field2Name($field): string
    {
        return strtolower(str_replace(' ', '_', $field));
    }
}
