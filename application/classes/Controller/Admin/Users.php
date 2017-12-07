<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  Zarządzanie Użytkownikami systemu
************************************************************************************/

class Controller_Admin_Users extends Controller {
    private $view;

    public function before() {
        if (!Model_BSX_Core::testPermission('admin')) return;
        if (!Model_BSX_Admin::test_login($this)) return;
        parent::before();

        $this->view=Model_BSX_Core::create_view($this,'index_standard','padmin');

        $this->root=Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/';
        $this->link=$this->root.'users';
        $this->view->sidebar_active='managment';
        $this->view->sidebar_active_menu='users';

        $this->table='bs_users';
        $this->statusList=array(0=>'Administrator',2=>'Administrator oddziału',5=>'Użytkownik',7=>'Reseller',10=>'Nieaktywny');

        $this->view->path=array(
            array('caption'=>'Home','url'=>$this->root),
            array('caption'=>'Użytkownicy','url'=>$this->link),
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
        $this->view->path[]=array('caption'=>'Lista użytkowników','url'=>$this->link.'list');
        $this->view->title='Użytkownicy';

        $t=new Model_BSX_Table($this->table,'tbl',$this);
        $t->formURL=$this->link.'/list';
        $t->tableView=Model_BSX_Core::create_view($this,'part_table_std','padmin');
        $t->showView=Model_BSX_Core::create_view($this,'part_table_show_std','padmin');
        $t->tableView->title='Lista użytkowników';
        $t->showPagesExacly=true;
        $t->showSearchPanel=true;
        $t->showEditBtn=false;
        $t->showDelBtn=true;
        $t->showShowBtn=true;

        $t->addField('id','ID','center','int','60px');
        $t->addField('plastname','Imię i nazwisko','left','varchar','',null,'{plastname} {pfirstname}');
        $t->addField('pstatus','Status','center','select','',$this->statusList);
        $t->options['showViewData']=array(
            'title'=>'Użytkownik',
            'subtitle'=>'{plastname} {pfirstname}',
            'groups'=>array(
                array(
                    'title'=>'Informacje',
                    'rows'=>array(
                        array(
                            array('name'=>'pfirstname','caption'=>'Imie'),
                            array('name'=>'pstatus','caption'=>'Status','type'=>'select','data'=>$this->statusList),
                        ),
                        array(
                            array('name'=>'plastname','caption'=>'Nazwisko'),

                        ),
                    ),
                ),
            ),
        );


        echo $t->render();
    }

    public function action_add()
    {
        echo 'Add';
    }


}