<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  Zarządzanie firmą
************************************************************************************/

class Controller_Admin_Company extends Controller {
    private $view;

    public function before() {
        if (!Model_BSX_Core::testPermission('admin')) return;
        if (!Model_BSX_Admin::test_login($this)) return;
        parent::before();

        $this->view=Model_BSX_Core::create_view($this,'index_standard','padmin');

        $this->root=Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/';
        $this->link=$this->root.'company';
        $this->view->sidebar_active='managment';
        $this->view->sidebar_active_menu='company';

        $this->view->path=array(
            array('caption'=>'Home','url'=>$this->root),
            array('caption'=>'Firma','url'=>$this->root.'company'),
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
        //$this->view->path[]=array('caption'=>'Zarządzanie firmą','url'=>$this->link.'/list');
        $this->view->title='Zarządzanie firmą';

        echo '//TODO Zarządzanie firmą';
    }

    public function action_banks()
    {
        $this->view->path[]=array('caption'=>'Konta bankowe','url'=>$this->link.'/banks');
        $this->view->title='Zarządzanie kontami bankowymi';

        echo '//TODO Zarządzanie kontami bankowymi';
    }
}