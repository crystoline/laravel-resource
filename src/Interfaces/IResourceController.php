<?php
/**
 * Created by PhpStorm.
 * User: cryst
 * Date: 19/04/01
 * Time: 4:20 PM
 */

namespace Crystoline\Resource\Interfaces;


use Illuminate\Database\Eloquent\Model;

interface IResourceController
{
    public static function getModel(): string;
}
