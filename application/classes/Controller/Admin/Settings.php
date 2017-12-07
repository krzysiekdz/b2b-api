<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  Zarządzanie ustawieniami
************************************************************************************/

class Controller_Admin_Settings extends Controller {
    private $view;

    public function before() {
        if (!Model_BSX_Core::testPermission('admin')) return;
        if (!Model_BSX_Admin::test_login($this)) return;
        parent::before();

        $this->view=Model_BSX_Core::create_view($this,'index_standard','padmin');

        $this->root=Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/';
        $this->link=$this->root.'settings';
        $this->view->sidebar_active='managment';
        $this->view->sidebar_active_menu='settings';


        $this->view->path=array(
            array('caption'=>'Home','url'=>$this->root),
            array('caption'=>'CMS','url'=>$this->root.'cms'),
        );

        BinUtils::buffer_start();
    }

    public function after() {
        $this->view->content=BinUtils::buffer_end();
        Model_BSX_Core::create_sidebar($this,$this->view);  //menu panelu administracyjnego
        $this->response->body($this->view);
        parent::after();
    }


    public function action_index()
    {
        return $this->action_simple();
    }

    public function action_simple()
    {
        $this->view->path[]=array('caption'=>'Podstawowe','url'=>$this->link.'/simple');
        $this->view->title='Ustawienia podstawowe';

        echo '//TODO Ustawienia podstawowe';
    }

    public function action_more()
    {
        $this->view->path[]=array('caption'=>'Zaawansowane','url'=>$this->link.'/more');
        $this->view->title='Ustawienia zaawansowane';

        echo '//TODO Ustawienia zaawansowane';
    }
}