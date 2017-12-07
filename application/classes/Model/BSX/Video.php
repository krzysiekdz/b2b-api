<?php defined('SYSPATH') or die('No direct script access.');


class Model_BSX_Video
{
    public function __construct($page)
	{

	}


    public static function show_video($url, $params=null)
    {
       $width  = 640;
       $height = 360;
       $controls=1;
       $align='center';
       if (is_array($params))
       {
          if (!empty($params[0]))
          {
            $k=strpos($params[0],'x');
            $width=substr($params[0],0,$k);
            $height=substr($params[0],$k+1);
          }
          if (!empty($params[1])) $align=$params[1];
          if (!empty($params[2])) $controls=$params[2];
       }

       $pp=strpos($url,'v=');
       if ($pp>0) {$url=substr($url,$pp+2);$pz=strpos($url,'&');if ($pz>0) $url=substr($url,0,$pz);}
       $kod='<div style="text-align:'.$align.'">
          <iframe width="'.$width.'" height="'.$height.'" src="//www.youtube.com/embed/'.$url.'" frameborder="0" allowfullscreen></iframe>
          </div>';
       echo $kod;
    }
}