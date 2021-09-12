<?php
/**
 * Created by PhpStorm.
 * User: crysto
 * Date: 19/03/31
 * Time: 5:20 PM
 */

namespace Crystoline\Resource;


class ResourceView
{

    public static function defaultIndex(): string
    {
        return 'r.index';
    }
    public static function defaultShow(): string
    {
        return 'r.show';
    }
    public static function defaultCreate(): string
    {
        return 'r.create';
    }
    public static function defaultEdit(): string
    {
        return 'r.edit';
    }
}
