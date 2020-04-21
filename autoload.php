<?php
/**
 * Created by PhpStorm.
 * Project: htdocs
 * Date: 17/05/2019
 * Time: 08:37
 */

namespace steph\db_query;


class autoload
{
    public static function register()
    {
        spl_autoload_register(array(__CLASS__,'autoload'));
    }

    public static function autoload($class)
    {
        $class=str_replace('stph\\db_query','',$class);
        $class=str_replace('\\','/',$class);
        require_once 'src/' . $class . '.php';
    }
}