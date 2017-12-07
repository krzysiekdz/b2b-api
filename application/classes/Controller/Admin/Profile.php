<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  Zarządzanie profilem użytkownika, tj.:
  - zakładka Profil
  - zmiana hasła
  - mój kalendarz
  - moje zadania
************************************************************************************/

class Controller_Admin_Profile extends Controller {
    private $view;

    public function before() {
        if (!Model_BSX_Core::testPermission('admin')) return;
        if (!Model_BSX_Admin::test_login($this)) return;
        parent::before();

        $this->view=Model_BSX_Core::create_view($this,'index_standard','padmin');

        $this->root=Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/';
        $this->link=$this->root.'profile';
        $this->table='bs_users';

        $this->view->sidebar_active='inne';
        $this->view->sidebar_active_menu='profil';

        $this->view->path=array(
            array('caption'=>'Home','url'=>$this->root),
            array('caption'=>'Profil','url'=>$this->link),
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
        $this->view->title='Profil użytkownika';
        echo '//TODO Profil';
    }

    public function action_calendar()
    {
        $this->view->path[]=array('caption'=>'Kalendarz','url'=>$this->link.'/calendar');
        $this->view->title='Kalendarz';

        echo '//TODO Kalendarz';
    }

    public function action_messages()
    {
        $this->view->path[]=array('caption'=>'Kalendarz','url'=>$this->link.'/messages');
        $this->view->title='Wiadomości';
        echo '//TODO Wiadomości';
    }

    public function action_tasks()
    {
        $this->view->path[]=array('caption'=>'Kalendarz','url'=>$this->link.'/tasks');
        $this->view->title='Zadania';
        echo '//TODO Zadania';
    }


}