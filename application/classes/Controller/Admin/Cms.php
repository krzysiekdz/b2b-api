<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  Zarządzanie CMS
************************************************************************************/

class Controller_Admin_Cms extends Controller {
    private $view;

    public function before() {
        if (!Model_BSX_Core::testPermission('admin')) return;
        if (!Model_BSX_Admin::test_login($this)) return;
        parent::before();

        $this->view=Model_BSX_Core::create_view($this,'index_standard','padmin');

        $this->root=Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/';
        $this->link=$this->root.'cms';
        $this->view->sidebar_active='cms';
        $this->view->sidebar_active_menu='ms';

        $this->view->path=array(
            array('caption'=>'Home','url'=>$this->root),
            array('caption'=>'CMS','url'=>$this->root.'/cms'),
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
        Header('Location: /admin/articles');
        exit;
    }



}