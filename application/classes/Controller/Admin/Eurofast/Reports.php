<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  Raporty i zestawienia
************************************************************************************/

class Controller_Admin_Eurofast_Reports extends Controller {
    private $view;
    public $messageStr;
    public $messageClass = 'alert-success';

    public function before() {
        if (!Model_BSX_Core::testPermission('admin')) return;
        if (!Model_BSX_Admin::test_login($this)) return;
        parent::before();

        $this->view=Model_BSX_Core::create_view($this,'index_standard','padmin');

        $this->root=Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/';
        $this->link=$this->root.'eurofast/reports';
        $this->view->sidebar_active='home';
        $this->view->sidebar_active_menu='statystyki';


        $this->view->path=array(
            array('caption'=>'Home','url'=>$this->root),
            array('caption'=>'Raporty i statystyki','url'=>$this->link),
        );

        BinUtils::buffer_start();
    }

    public function after() {
        $this->view->content=BinUtils::buffer_end();

        if (Model_BSX_Core::global_variable('ajax'))
        {
            echo json_encode(array('data'=>$this->view->content));
        } else {
            Model_BSX_Core::create_sidebar($this, $this->view);  //menu panelu administracyjnego
            $this->response->body($this->view);
        }
        parent::after();
    }


    public function action_index()
    {
        $this->action_list();
    }

    public function action_list()
    {
        $this->view->title='Statystyki';

        $view = Model_BSX_Core::create_view($this, 'Eurofast/part_reports_admin_stat', 'padmin');
        //$view->set('f', $f);
        //$view->set('id', $id);
        echo $view;

    }

}