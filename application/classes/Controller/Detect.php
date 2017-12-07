<?php defined('SYSPATH') or die('No direct script access.');


class Controller_Detect extends Controller {

    public function before() {
        parent::before();
        //-- jak nie jest włączona obsługa strony WWW (tylko sam CMS), to przekierowujemy do CMS-a
        if (!Model_BSX_Core::testPermission('site')) return;
    }

    public function after() {
           parent::after();
    }

 	public function action_detect()
	{
       $m=$this->request->param('modrewrite');
       $w=explode('/',$m);
       while (count($w)<=5) $w[]='';
       if ($m=='')
       {
           //strona główna
           if (isset(Model_BSX_Core::$bsx_cfg['index_page'])) $start=Model_BSX_Core::$bsx_cfg['index_page']; else $start='';

           if ($start!='')
           {
               if ($start[0]=='#')
               {
                   header('Location: '.substr($start,1));
                   exit;
               } else if ($start=='commingsoon') return $this->action_comingSoon();
           }
           return $this->action_showHome();
       }

       if (!empty(Model_BSX_Core::$bsx_cfg['model']) && ($w[0]==Model_BSX_Core::$bsx_cfg['model'])) $this->showFromModel($w);
       else if ($w[0]=='news' || $w[0]=='wiadomosci' || $w[0]=='aktualnosci') return $this->showNews($w);
       else if ($w[0]=='blog') return $this->showBlog($w);
       else if ($w[0]=='events' || $w[0]=='wydarzenia') return $this->showEvents($w);
       else if ($w[0]=='gallery' || $w[0]=='galerie' || $w[0]=='galleries' || $w[0]=='galeria') return $this->showGalleries($w);

       return $this->showArticle($w);
	}

    public function action_comingSoon()
    {
        $view=Model_BSX_Core::create_view($this,'index_comingsoon');
        if (!$view) $view=Model_BSX_Core::create_view($this,'index_start');
        if (!$view) die('Brak szablonu: index_comingsoon i index_start');
        $this->response->body($view);
    }

    public function action_showHome()
    {
        $view=Model_BSX_Core::create_view($this,'index_home');
        if (!$view) $view=Model_BSX_Core::create_view($this,'index_start');
        if (!$view) $view=Model_BSX_Core::create_view($this,'index_standard');
        if (!$view) {
            $this->action_comingSoon();
            return;
        }
        //if ($article!==false) $view->content=$article;
        $this->response->body($view);
    }

    public function showArticle($w=null)
    {
        if (!empty($w[1])) { $mod=$w[0]; $art=$w[1]; }
        else { $mod='artykuly'; $art=$w[0]; }
        if ($art=='articles' || $art=='artykuly') { $mod=$art; $art=''; }
        if (isset($_GET['idarticle'])) {
            $mod='articles';
            $art=(int)$_GET['idarticle'];
        }

        if ($art!='') {
            $cms=Model_BSX_CMS::init();
            $p=$cms->getArticleByModrewrite($art,$mod,$this);
            if (!$p) throw new HTTP_Exception_404(':file does not exist!', array(':file' => $w[0]));


            if (!empty($p['pseo_desc'])) Model_BSX_Core::$bsx_cfg['pmdesc']=$p['pseo_desc'];
            if (!empty($p['pseo_title'])) {
                Model_BSX_Core::meta_page('title',$p['pseo_title'],0);
            } else {
                Model_BSX_Core::meta_page('title',$p['ptitle'],1);
            }
            if (!empty($p['pseo_keyw'])) Model_BSX_Core::$bsx_cfg['pmkeys']=$p['pseo_keyw'];
            Model_BSX_Page::$active_tab=$p['pactivetab'];

            $page_tpl='page_article';
            $index_tpl='index_article';
            if (!empty($p['ptpl']))
            {
                if (substr($p['ptpl'],0,6)=='index_' && strpos($p['ptpl'],'#')===FALSE)
                {
                    $index_tpl=$p['ptpl'];
                } else {
                    $k = strpos($p['ptpl'], '#');
                    if ($k !== FALSE) {
                        $index_tpl = substr($p['ptpl'], $k + 1);
                        $p['ptpl'] = substr($p['ptpl'], 0, $k);
                    }
                    $k = strpos($p['ptpl'], '\\');
                    if ($k !== FALSE) {
                        $a = substr($p['ptpl'], 0, $k);
                        $b = substr($p['ptpl'], $k + 1);
                    } else {
                        $a = $p['ptpl'];
                        $b = $a;
                    }
                    if (strpos($b, '.') !== FALSE) $b = substr($b, 0, -4);
                    if (substr($b, 0, 5) == 'page_') $page_tpl = $b;
                }
            }

            $page_tpl=getGetPost('page_tpl',$page_tpl);
            $index_tpl=getGetPost('index_tpl',$index_tpl);

            //pobieramy stronę z bazy (z formatowaniem)
            $view=Model_BSX_Core::create_view($this,$index_tpl);
            if (!$view) die('Brak szablonu: '.$index_tpl);
            $view->set('title',$p['ptitle']);
            $view->path[]=array('caption'=>'Artykuły','url'=>$mod);
            $view->path[]=array('caption'=>$p['ptitle'],'url'=>$p['url']);
            $page=Model_BSX_Core::create_view($this,$page_tpl);
            $page->path=$view->path;
            $page->set('article',$p);
            $view->set('content',$page);

            sql_query('UPDATE bsc_articles SET pshows=pshows+1 WHERE id=:id',array(':id'=>$p['id']));

        } else {
            //lista artykułów
            $view=Model_BSX_Core::create_view($this,'index_standard');
            $view->set('title','Artykuły');
            $view->path[]=array('caption'=>'Artykuły','url'=>'artykuly');
            $page=Model_BSX_Core::create_view($this,'page_article');
            $page->set('title',$view->title);
            $page->path=$view->path;

            $cms=Model_BSX_CMS::init();
            $results=$cms->getArticles('');

            $r='<ul>';
            foreach ($results as $row) {
                $r.='<li><a href="'.$row['url'].'">'.$row['ptitle'].'</a></li>';
            }
            $r.='</ul>';

            $p=array();
            $p['pbody']=$r;

            $page->set('article',$p);
            $view->set('content',$page);

        }
        $this->response->body($view);
    }

    public function showNews($w)
    {
        $cms=Model_BSX_CMS::init();
        Model_BSX_Page::$active_tab='news';
        $cat=$cms->getNewsCategory($w[1]);
        if ($w[1]=='' || is_numeric($w[1]) || $cat) {

            $view=Model_BSX_Core::create_view($this,'index_standard');
            $view->set('title','Wiadomości');
            $view->path[]=array('caption'=>'Wiadomości','url'=>$w[0]);

            $q=getGet('q');
            if ($q!='') $addQ='?q='.$q; else $addQ='';
            $results=$cms->getNewsList($w[0],$w[0].'/{start}'.$addQ,(int)$w[1],10,false,$q,$cat);

            $page=Model_BSX_Core::create_view($this,'page_news_list');
            $page->set('results',$results);
            $view->set('content',$page);

            $this->response->body($view);
        } else {
            $p=$cms->getNewsByModrewrite($w[1],$w[0],$this);
            if (!$p)
            {
                throw new HTTP_Exception_404(':file does not exist!', array(':file' => $w[1]));
            }

            if (!empty($p['pseo_desc'])) Model_BSX_Core::$bsx_cfg['pmdesc']=$p['pseo_desc'];
            if (!empty($p['pseo_title'])) {
                Model_BSX_Core::meta_page('title',$p['pseo_title'],0);
            } else {
                Model_BSX_Core::meta_page('title',$p['ptitle'],1);
            }
            if (!empty($p['pseo_keyw'])) Model_BSX_Core::$bsx_cfg['pmkeys']=$p['pseo_keyw'];
            if (!empty($p['pactivetab'])) Model_BSX_Page::$active_tab=$p['pactivetab'];

            $page_tpl='page_news_item';
            if (!empty($p['ptpl']))
            {
                $k=strpos($p['ptpl'],'\\');
                if ($k!==FALSE) {
                    $a=substr($p['ptpl'],0,$k);
                    $b=substr($p['ptpl'],$k+1);
                } else {
                    $a=$p['ptpl'];
                    $b=$a;
                }
                if (strpos($b,'.')!==FALSE) $b=substr($b,0,-4);
                if (substr($b,0,5)=='page_') $page_tpl=$b;
            }

            $view=Model_BSX_Core::create_view($this,'index_standard');
            $view->set('title',$p['ptitle']);
            $view->path[]=array('caption'=>'Wiadomości','url'=>$w[0]);
            $view->path[]=array('caption'=>$p['ptitle'],'url'=>$p['url']);
            $page=Model_BSX_Core::create_view($this,$page_tpl);
            $page->set('news',$p);
            $view->set('content',$page);

            sql_query('UPDATE bsc_news SET pshows=pshows+1 WHERE id=:id',array(':id'=>$p['id']));

            $this->response->body($view);

        }
    }

    public function showBlog($w)
    {
        $cms=Model_BSX_CMS::init();
        Model_BSX_Page::$active_tab='blog';
        $cat=$cms->getBlogCategory($w[1]);
        if ($w[1]=='' || is_numeric($w[1]) || $cat) {

            $view=Model_BSX_Core::create_view($this,'index_standard');
            $view->set('title','Blog');
            $view->path[]=array('caption'=>'Blog','url'=>$w[0]);

            $q=getGet('q');
            if ($q!='') $addQ='?q='.$q; else $addQ='';
            $results=$cms->getBlogList($w[0],$w[0].'/{start}'.$addQ,(int)$w[1],10,false,$q,$cat);

            $page=Model_BSX_Core::create_view($this,'page_blog_list');
            $page->set('results',$results);
            $page->path=$view->path;
            $page->title=$view->title;
            $view->set('content',$page);

            $this->response->body($view);
        } else {
            $p=$cms->getBlogByModrewrite($w[1],$w[0],$this);
            if (!$p)
            {
                throw new HTTP_Exception_404(':file does not exist!', array(':file' => $w[1]));
            }

            if (!empty($p['pseo_desc'])) Model_BSX_Core::$bsx_cfg['pmdesc']=$p['pseo_desc'];
            if (!empty($p['pseo_title'])) {
                Model_BSX_Core::meta_page('title',$p['pseo_title'],0);
            } else {
                Model_BSX_Core::meta_page('title',$p['ptitle'],1);
            }
            if (!empty($p['pseo_keyw'])) Model_BSX_Core::$bsx_cfg['pmkeys']=$p['pseo_keyw'];
            if (!empty($p['pactivetab'])) Model_BSX_Page::$active_tab=$p['pactivetab'];

            $page_tpl='page_blog_item';
            $index_tpl='index_standard';
            if (!empty($p['ptpl']))
            {
                $k=strpos($p['ptpl'],'#');
                if ($k!==FALSE) {
                    $index_tpl=substr($p['ptpl'],$k+1);
                    $p['ptpl']=substr($p['ptpl'],0,$k);
                }
                $k=strpos($p['ptpl'],'\\');
                if ($k!==FALSE) {
                    $a=substr($p['ptpl'],0,$k);
                    $b=substr($p['ptpl'],$k+1);
                } else {
                    $a=$p['ptpl'];
                    $b=$a;
                }
                if (strpos($b,'.')!==FALSE) $b=substr($b,0,-4);
                if (substr($b,0,5)=='page_') $page_tpl=$b;
            }

            $view=Model_BSX_Core::create_view($this,$index_tpl);
            $view->set('title',$p['ptitle']);
            $view->path[]=array('caption'=>'Blog','url'=>$w[0]);
            $view->path[]=array('caption'=>$p['ptitle'],'url'=>$p['url']);
            $page=Model_BSX_Core::create_view($this,$page_tpl);
            $page->set('news',$p);
            $page->path=$view->path;
            $page->title=$p['ptitle'];
            $view->set('content',$page);

            sql_query('UPDATE bsc_blog SET pshows=pshows+1 WHERE id=:id',array(':id'=>$p['id']));

            $this->response->body($view);

        }
    }

    public function showEvents($w)
    {
        $cms=Model_BSX_CMS::init();
        Model_BSX_Page::$active_tab='events';
        $cat=$cms->getEventsCategory($w[1]);
        if ($w[1]=='' || is_numeric($w[1]) || $cat) {

            $view=Model_BSX_Core::create_view($this,'index_standard');
            $view->set('title','Wydarzenia');
            $view->path[]=array('caption'=>'Wydarzenia','url'=>$w[0]);

            $q=getGet('q');
            if ($q!='') $addQ='?q='.$q; else $addQ='';
            $results=$cms->getEventsList($w[0],$w[0].'/{start}'.$addQ,(int)$w[1],10,false,'all',$q,$cat);

            $page=Model_BSX_Core::create_view($this,'page_events_list');
            $page->set('results',$results);
            $view->set('content',$page);

            $this->response->body($view);
        } else {
            $cms=Model_BSX_CMS::init();
            $p=$cms->getEventsByModrewrite($w[1],$w[0],$this);
            if (!$p)
            {
                throw new HTTP_Exception_404(':file does not exist!', array(':file' => $w[1]));
            }

            if (!empty($p['pseo_desc'])) Model_BSX_Core::$bsx_cfg['pmdesc']=$p['pseo_desc'];
            if (!empty($p['pseo_title'])) {
                Model_BSX_Core::meta_page('title',$p['pseo_title'],0);
            } else {
                Model_BSX_Core::meta_page('title',$p['ptitle'],1);
            }
            if (!empty($p['pseo_keyw'])) Model_BSX_Core::$bsx_cfg['pmkeys']=$p['pseo_keyw'];
            if (!empty($p['pactivetab'])) Model_BSX_Page::$active_tab=$p['pactivetab'];

            $page_tpl='page_events_item';
            if (!empty($p['ptpl']))
            {
                $k=strpos($p['ptpl'],'\\');
                if ($k!==FALSE) {
                    $a=substr($p['ptpl'],0,$k);
                    $b=substr($p['ptpl'],$k+1);
                } else {
                    $a=$p['ptpl'];
                    $b=$a;
                }
                if (strpos($b,'.')!==FALSE) $b=substr($b,0,-4);
                if (substr($b,0,5)=='page_') $page_tpl=$b;
            }

            $view=Model_BSX_Core::create_view($this,'index_standard');
            $view->set('title',$p['ptitle']);
            $view->path[]=array('caption'=>'Wydarzenia','url'=>$w[0]);
            $view->path[]=array('caption'=>$p['ptitle'],'url'=>$p['url']);
            $page=Model_BSX_Core::create_view($this,$page_tpl);
            $page->set('event',$p);
            $view->set('content',$page);

            sql_query('UPDATE bsc_events SET pshows=pshows+1 WHERE id=:id',array(':id'=>$p['id']));

            $this->response->body($view);

        }
    }

    public function showGalleries($w=null)
    {
        if (!empty($w[1])) { $mod=$w[0]; $art=$w[1]; }
        else { $mod='galerie'; $art=$w[0]; }
        if ($art=='galleries' || $art=='galerie' || $art=='gallery' || $art=='galeria') { $mod=$art; $art=''; }

        if ($art!='') {
            $p=Model_BSX_Gallery::getGalleryByModRewrite($art,$art);
            if (!$p) throw new HTTP_Exception_404(':file does not exist!', array(':file' => $w[0]));


            if (!empty($p['pseo_desc'])) Model_BSX_Core::$bsx_cfg['pmdesc']=$p['pseo_desc'];
            if (!empty($p['pseo_title'])) {
                Model_BSX_Core::meta_page('title',$p['pseo_title'],0);
            } else {
                Model_BSX_Core::meta_page('title',$p['ptitle'],1);
            }
            if (!empty($p['pseo_keyw'])) Model_BSX_Core::$bsx_cfg['pmkeys']=$p['pseo_keyw'];
            Model_BSX_Page::$active_tab=$p['pactivetab'];

            $page_tpl='page_gallery';
            $index_tpl='index_article';
            if (!empty($p['ptpl']))
            {
                $k=strpos($p['ptpl'],'#');
                if ($k!==FALSE) {
                    $index_tpl=substr($p['ptpl'],$k+1);
                    $p['ptpl']=substr($p['ptpl'],0,$k);
                }
                $k=strpos($p['ptpl'],'\\');
                if ($k!==FALSE) {
                    $a=substr($p['ptpl'],0,$k);
                    $b=substr($p['ptpl'],$k+1);
                } else {
                    $a=$p['ptpl'];
                    $b=$a;
                }
                if (strpos($b,'.')!==FALSE) $b=substr($b,0,-4);
                if (substr($b,0,5)=='page_') $page_tpl=$b;
            }

            //pobieramy stronę z bazy (z formatowaniem)
            $view=Model_BSX_Core::create_view($this,$index_tpl);
            $view->set('title',$p['ptitle']);
            $view->path[]=array('caption'=>'Galerie','url'=>$mod);
            $view->path[]=array('caption'=>$p['ptitle'],'url'=>$p['url']);
            $page=Model_BSX_Core::create_view($this,$page_tpl);
            $page->path=$view->path;
            $page->set('gallery',$p);
            $view->set('content',$page);

            sql_query('UPDATE bsc_galleries SET pshows=pshows+1 WHERE id=:id',array(':id'=>$p['id']));

        } else {
            //lista galerii
            header('Location: /');
            exit;

        }
        $this->response->body($view);
    }

}
