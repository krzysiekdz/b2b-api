<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  Zarządzanie wpisami na blogu
************************************************************************************/

class Controller_Admin_Blogs extends Controller {
    private $view;

    public function before() {
        if (!Model_BSX_Core::testPermission('admin')) return;
        if (!Model_BSX_Admin::test_login($this)) return;
        parent::before();

        $this->view=Model_BSX_Core::create_view($this,'index_standard','padmin');

        $this->root=Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/';
        $this->link=$this->root.'blogs';
        $this->view->sidebar_active='cms';
        $this->view->sidebar_active_menu='blogs';

        $this->table='bsc_blog';
        $this->statusList=array(0=>'Ukryty',1=>'Widoczny',2=>'Specjalny');
        $this->sitesList=Arr::merge(array(0=>'Dowolna'),sql_rows_asselect('SELECT id, ptitle FROM bsc_sites','{ptitle}'));


        $this->view->path=array(
            array('caption'=>'Home','url'=>$this->root),
            array('caption'=>'CMS','url'=>$this->root.'cms'),
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
        return $this->action_list();
    }

    public function action_list()
    {
        $this->view->path[]=array('caption'=>'Lista wpisów','url'=>$this->link.'/list');
        $this->view->title='Lista wpisów';

        $t=new Model_BSX_Table($this->table,'tbl',$this);
        $t->formURL=$this->link.'/list';
        $t->tableView=Model_BSX_Core::create_view($this,'part_table_std','padmin');
        $t->showView=Model_BSX_Core::create_view($this,'part_table_show_std','padmin');
        $t->tableView->title='Lista wpisów';
        $t->showPagesExacly=true;
        $t->showSearchPanel=true;
        $t->showEditBtn=false;
        $t->showDelBtn=true;
        $t->showShowBtn=true;

        $t->addField('id','ID','center','int','60px');
        $t->addField('ptitle','Tytuł','center','varchar','');
        $t->addField('pmodrewrite','Modrewrite','center','','');
        $t->addField('idsite','Strona','center','select','150px',$this->sitesList);

        $t->options['showViewData']=array(
            'title'=>'Wpis',
            'subtitle'=>'{ptitle}',
            'groups'=>array(
                array(
                    'title'=>'Informacje',
                    'rows'=>array(
                        array(
                            array('name'=>'pmodrewrite','caption'=>'Modrewrite'),
                            array('name'=>'pstatus','caption'=>'Status','type'=>'select','data'=>$this->statusList),
                        ),
                        array(
                            array('name'=>'pauthor','caption'=>'Autor'),
                            array('name'=>'idsite','caption'=>'Strona','type'=>'select','data'=>$this->sitesList),
                        ),
                    ),
                ),
                array(
                    'title'=>'Inne',
                    'rows'=>array(
                        array(
                            'options'=>array('class'=>'col-md-12'),
                            array('name'=>'pbody','caption'=>'Treść'),
                        ),
                    ),
                ),
            ),
        );

        echo $t->render();
    }

}