<?php
/**
 * Created by PhpStorm.
 * User: cryst
 * Date: 19/03/28
 * Time: 11:06 PM
 */

namespace Crystoline\Resource;


use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;

class ResourceDependency implements Arrayable
{
    /**
     * @var Builder
     */
    private $query;
    private $label;
    private $value;

    /**
     * ResourceDependency constructor.
     * @param Builder $query
     * @param $label
     * @param $value
     */
    public function __construct(Builder $query, $label, $value)
    {
        $this->query = $query;
        $this->label = $label;
        $this->value = $value;
    }
    public function getList(){
        return $this->query->get()->pluck($this->label, $this->value);
    }


    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->getList();
    }
}
