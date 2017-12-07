<?php defined('SYSPATH') or die('No direct script access.');

class Model_BSX_Cloud
{
    private static $_init = false;


    public static function init()
    {
        if (Model_BSX_Cloud::$_init) return Model_BSX_Cloud::$_init;


        Model_BSX_Cloud::$_init = new Model_BSX_Cloud();
        return Model_BSX_Cloud::$_init;
    }

}