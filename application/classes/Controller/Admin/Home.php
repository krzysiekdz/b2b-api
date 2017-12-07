<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  Zarządzanie oknem powitalnym
************************************************************************************/

class Controller_Admin_Home extends Controller {
    private $view;

    public function before() {
        if (!Model_BSX_Core::testPermission('admin')) return;
        if (!Model_BSX_Admin::test_login($this)) return;
        parent::before();

        $this->view=Model_BSX_Core::create_view($this,'index_standard','padmin');

        $this->root=Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/';
        $this->link=$this->root.'home';


        $this->view->sidebar_active='home';


        $this->view->path=array(
            array('caption'=>'Home','url'=>$this->root),
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
        return $this->action_start();
    }

    public function action_start()
    {
        $this->view->path[]=array('caption'=>'Wprowadzenie','url'=>$this->link);
        $this->view->title='Witaj '.$_SESSION['admin_user']['pfirstname'].'!';
        $this->view->sidebar_active_menu='start';
        echo '//TODO Powitanie';
    }

    public function action_alerts()
    {
        $this->view->path[]=array('caption'=>'Powiadomienia','url'=>$this->link.'/alerts');
        $this->view->title='Powiadomienia';
        $this->view->sidebar_active_menu='alerts';
        echo '//TODO Powiadomienia';
    }

    public function action_statistics()
    {
        $this->view->path[]=array('caption'=>'Statystyki','url'=>$this->link.'/statistics');
        $this->view->title='Statystyki';
        $this->view->sidebar_active_menu='statistics';
        echo '//TODO Statystyki';
    }
}