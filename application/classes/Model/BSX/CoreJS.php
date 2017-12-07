<?php defined('SYSPATH') or die('No direct script access.');

class Model_BSX_CoreJS
{
    public static $_HEADERCSS=array();
    public static $_HEADERCSSPLUGINS=array();
    public static $_HEADERPLUGINS=array();
    public static $_FOOTERPLUGINS=array();
    public static $_FOOTERJS=array();
    public static $_FOOTERINIT=array();
    public static $_FOOTERCODE=array();

    public static function addJS($url,$place='headerplugins')
    {
        $link='<script src="'.$url.'" type="text/javascript"></script>';
        if ($place=='headerplugins' && !in_array($link,Model_BSX_CoreJS::$_HEADERPLUGINS,$link)) Model_BSX_CoreJS::$_HEADERPLUGINS[]=$link;
        if ($place=='footerplugins' && !in_array($link,Model_BSX_CoreJS::$_FOOTERPLUGINS,$link)) Model_BSX_CoreJS::$_FOOTERPLUGINS[]=$link;
        if ($place=='footer' && !in_array($link,Model_BSX_CoreJS::$_FOOTERJS,$link)) Model_BSX_CoreJS::$_FOOTERJS[]=$link;
    }

    public static function addCSS($url,$place='css')
    {
        $link='<link href="'.$url.'" rel="stylesheet" type="text/css" />';
        if ($place=='css' && !in_array($link,Model_BSX_CoreJS::$_HEADERCSS,$link)) Model_BSX_CoreJS::$_HEADERCSS[]=$link;
        if ($place=='headerplugins' && !in_array($link,Model_BSX_CoreJS::$_HEADERCSSPLUGINS,$link)) Model_BSX_CoreJS::$_HEADERCSSPLUGINS[]=$link;
    }

    public static function addJSInit($code)
    {
        Model_BSX_CoreJS::$_FOOTERINIT[]=$code;
    }

    public static function addJSCode($code)
    {
        Model_BSX_CoreJS::$_FOOTERCODE[]=$code;
    }
}