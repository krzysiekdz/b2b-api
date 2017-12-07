<?php defined('SYSPATH') or die('No direct script access.');

class HTTP_Exception_404 extends Kohana_HTTP_Exception_404 {

    public function get_response()
    {
       //Błąd 404 - Nie znaleziono dokumentu
       //Route wychwytuje wszystkie wyrazy mogące być kontrolerami. Domyślnie szuka kontrolera w folderze związanym z daną stroną.
       //Jeśli go tam nie znajdzie, mamy 404. Wówczas tworzymy przekierowanie na root/controler by szukać kontrolera w folderze
       //głównym. Jeśli i wówczas nie znajdziemy dokumentu, wtedy
       //może jest to nazwą dokumentu (modrewrite)? Pobieramy REQUEST_URI i szukamy takiego dokumentu. Jak znajdziemy, przekierowujemy do niego.

/*

       $m=substr(Arr::get($_SERVER, 'REQUEST_URI'),1);
       if (Model_BSX_Core::$bsx_cfg['psite_class']!='')
       {
           // var_dump(class_exists('Model_'.Model_BSX_Core::$bsx_cfg['psite_class'].'_Core',true));

            $c='Model_'.Model_BSX_Core::$bsx_cfg['psite_class'].'_Core::detect_url';
            if (is_callable($c, false, $callable_name))
            {
                 $view=call_user_func('Model_'.Model_BSX_Core::$bsx_cfg['psite_class'].'_Core::detect_url',$m);
                 if ($view!==false)
                 {
                      $view=Request::factory($view)
                        ->execute()
                        ->send_headers()
                        ->body();

                       $response = Response::factory()
                            ->body($view);

                        return $response;
                 }
            }
       }

       if (!Model_BSX_Core::$redirectToControler)
       {
                  Model_BSX_Core::$redirectToControler=true;
                  $view=Request::factory('root/'.$m)
					->execute()
					->send_headers()
					->body();

                   $response = Response::factory()
                        ->body($view);

                    return $response;
       }
       if ($m!='')
       {



          $p = sql_row('SELECT * FROM bsc_articles WHERE (idsite=:idsite OR idsite=0) AND pmodrewrite=:modrewrite AND pstatus=1',array(':idsite'=>Model_BSX_Core::$bsx_cfg['id'],':modrewrite'=>$m));
          if ($p)
          {
                  $view=Request::factory('/article/'.$p['id'])
					->execute()
					->send_headers()
					->body();

                   $response = Response::factory()
                        ->body($view);

                    return $response;
                    exit;
          }

       }


*/
       $w='';
       if (!empty($_SERVER['REQUEST_URI'])) $u=$_SERVER['REQUEST_URI']; else $u='';
       if (substr($u,1,strlen(Model_BSX_Core::$bsx_cfg['puser_prefix']))==Model_BSX_Core::$bsx_cfg['puser_prefix']) $w='puser';
       else if (substr($u,1,strlen(Model_BSX_Core::$bsx_cfg['preseller_prefix']))==Model_BSX_Core::$bsx_cfg['preseller_prefix']) $w='preseller';
       else if (substr($u,1,strlen(Model_BSX_Core::$bsx_cfg['padmin_prefix']))==Model_BSX_Core::$bsx_cfg['padmin_prefix']) $w='padmin';
       else if (substr($u,1,strlen(Model_BSX_Core::$bsx_cfg['pshop_prefix']))==Model_BSX_Core::$bsx_cfg['pshop_prefix']) $w='pshop';
       else $w='psite';

        //ok, rzeczywiście nie ma takiego dokumentu.. wówczas wyświetlamy ładną stronę z komunikatem
        $article=Model_BSX_Core::create_view($this,'page_error404',$w);

        if ($article!==FALSE)
        {
            $article->message = $this->getMessage();
            $article=$article->render();
        } else
        {
            $article='Nie odnaleziono takiej strony.<br />Msg:'.$this->getMessage();
        }


       $view=Model_BSX_Core::create_view($this,'index_standard',$w);
       if (!$view) die('Nie odnaleziono takiej strony!');
       $view->title='Nie znaleziono takiej strony!';
       $view->site_path=array();
       $view->site_path[]=array('caption'=>'Home','url'=>URL::site());
       $view->site_path[]=array('caption'=>'404','url'=>'');


       $view->content=$article;
       $response = Response::factory()
            ->status(404)
            ->body($view->render());

        return $response;
    }
}