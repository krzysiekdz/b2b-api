<?php defined('SYSPATH') or die('No direct script access.');

/*
  Obsługa artykułu

*/

function RunPHP($s)
{
    $teraz=ob_get_contents();
    ob_end_clean();
    ob_start();

    eval($s);

    $kod=ob_get_contents();
    ob_end_clean();
    ob_start();
    echo $teraz;
    return $kod;
}

class Model_BSX_Page
{
    public static $active_tab='';
    public $page;
    public $title;
    public $body;
    public $pr;
    public $controller;

    public function __construct($page, $controller=null)
	{
      $this->controller=$controller;
      $int_page=(int)$page;
      if ($int_page<=0)
      {
       $page = sql_row('SELECT * FROM bsc_articles WHERE (idsite=:idsite OR idsite=0) AND pmodrewrite=:modrewrite AND pstatus>0',array(':idsite'=>Model_BSX_Core::$bsx_cfg['id'],':modrewrite'=>$page));
       if (!$page) return;

      }
      $this->page=$page;
      $this->title=$page['ptitle'];

      if ($page['pstatus']==1) Model_BSX_Page::$active_tab=$page['pactivetab'];

      $s=$page['pbody'];


      $useMarkdown=($page['popc_mark']==1);
      //-------------- MARKDOWN -------------------
      if (substr($s,0,8)=='[%html%]')
      {
	    $s=substr($s,8);
  	    $useMarkdown=false;
      }

      $s=Model_BSX_Core::parseText($s,$useMarkdown);

      //-------------- PHP -------------
      $pz=strpos($s,'[php]');
      if ($pz===false) $pz=strpos($s,'[PHP]');
      while ($pz!==false)
      {
          $a=substr($s,0,$pz);
          $s=substr($s,$pz+5);
          $pz=strpos($s,'[/php]');
          if ($pz===false) $pz=strpos($s,'[/PHP]');
          if ($pz>=0)
          {
              $b=substr($s,0,$pz);
              $s=substr($s,$pz+6);
              $kod=RunPHP($b);
              $s=$a.$kod.$s;
          } else
          {
              $s=$a.$s;
              break;
          }
          $pz=strpos($s,'[php]');
          if ($pz===false) $pz=strpos($s,'[PHP]');
      }
      //---------------- CODE -------------------------
      $pz=strpos($s,'[code');
      if ($pz===false) $pz=strpos($s,'[CODE');
      while ($pz!==false)
      {
          $a=substr($s,0,$pz);
          $s=substr($s,$pz+5);
          $pz=strpos($s,']');
          $prm='';
          if ($pz===false) $pz=-1;
          if ($pz>=0)
          {
              if ($pz>0) $prm=substr($s,1,$pz-1); else $pz='';
              $s=substr($s,$pz+1);
              $pz=strpos($s,'[/code]');
              if ($pz===false) $pz=strpos($s,'[/CODE]');
          }
          if ($pz>=0)
          {
              $b=substr($s,0,$pz);
              $s=substr($s,$pz+7);
              $b=str_replace('<','&lt;',$b);
              $b=str_replace('>','&gt;',$b);
              $b=str_replace("\n\n","\n",$b);
              $kod='<pre class="syntax '.$prm.'">'.trim($b).'</pre>';
              $s=$a.$kod.$s;
          } else
          {
              $s=$a.$s;
              break;
          }
          $pz=strpos($s,'[code');
          if ($pz===false) $pz=strpos($s,'[CODE');
      }
      //-----------------------------------------------


      $this->body=$s;
	}

    public function render()
    {
         echo $this->body;
    }

    public function get_render()
    {
         return $this->body;
    }


    public function show_article($showIndexTemplate=true)
    {
       //wydobywany szablon, jaki ma być użyty do wyświetlenia artykułu

       $page_tpl='page_article';
       if (!empty($this->page['ptpl']))
       {
            $k=strpos($this->page['ptpl'],'\\');
            if ($k!==FALSE) {
                 $a=substr($this->page['ptpl'],0,$k);
                 $b=substr($this->page['ptpl'],$k+1);
            } else {
                 $a=$this->page['ptpl'];
                 $b=$a;
            }
            if (strpos($b,'.')!==FALSE) $b=substr($b,0,-4);
            if (substr($b,0,5)=='page_') $page_tpl=$b;

       }

       if (!empty($this->page['pseo_desc'])) Model_BSX_Core::$bsx_cfg['pmdesc']=$this->page['pseo_desc'];
       if (!empty($this->page['pseo_title'])) Model_BSX_Core::$bsx_cfg['pmtitle']=$this->page['pseo_title'];
       if (!empty($this->page['pseo_keyw'])) Model_BSX_Core::$bsx_cfg['pmkeys']=$this->page['pseo_keyw'];

       $site_path=array();
       $site_path[]=array('caption'=>'Home','url'=>URL::site());
       $site_path[]=array('caption'=>'Artykuły','url'=>URL::site('articles'));
       $site_path[]=array('caption'=>$this->page['ptitle'],'url'=>'');

       //tworzymy widok artykułu w oparciu o szablon
       $article=Model_BSX_Core::create_view($this->controller,$page_tpl);
       if ($article!==false)
       {
         $article->body=$this->body;
         $article->title=$this->title;
         $article->pr=$this->pr;
         $article->set('article',$this);
         $article->set('path',$site_path);
         $article=$article->render();
       } else
       {
         $article=$this->body;
       }

        if ($showIndexTemplate) {
            //tworzymy widok strony, w którym osadzamy widok artykułu
            $view = Model_BSX_Core::create_view($this->controller, 'index_article');
            $view->title = $this->title;
            $view->set('path', $site_path);
            $view->content = $article;
        } else {
            $view=$article;
        }

       return $view;
    }
}