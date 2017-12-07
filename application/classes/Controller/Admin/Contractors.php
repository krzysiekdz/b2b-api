<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  Zarządzanie kontrahentami
************************************************************************************/

class Controller_Admin_Contractors extends Controller {
    private $view;

    public function before() {
        if (!Model_BSX_Core::testPermission('admin')) return;
        if (!Model_BSX_Admin::test_login($this)) return;
        parent::before();

        $this->view=Model_BSX_Core::create_view($this,'index_standard','padmin');

        $this->root=Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/';
        $this->link=$this->root.'contractors';
        $this->view->sidebar_active='modules';
        $this->view->sidebar_active_menu='contractors';
        $this->table='bs_contractors';
        $this->statusList=array(
            0=>'W trakcie edycji',
            1=>'Do akceptacji',
            2=>'Zatwierdzone',
        );
        $this->view->path=array(
            array('caption'=>'Home','url'=>$this->root),
            array('caption'=>'Kontrahenci','url'=>$this->root.'contractors'),
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
        $t=new Model_BSX_Table($this->table,'tbl',$this);
        $t->tableView=Model_BSX_Core::create_view($this,'part_table_std','padmin');
        $t->showView=Model_BSX_Core::create_view($this,'part_table_show_std','padmin');
        $this->view->path[] = array('caption' => 'Lista', 'url' => $this->link . '/list');
        $this->view->title = 'Kontrahenci';
        $t->formURL=$this->link.'/list';
        $t->tableView->title='Lista kontrahentów';

        $t->showPagesExacly=true;
        $t->showSearchPanel=true;
        $t->showEditBtn=false;
        $t->showDelBtn=true;
        $t->showShowBtn=true;

        //$t->addField('id','ID','center','int','60px');
        $t->addField('pname','Kontrahent','left','varchar','',null,'<b>{pname}</b>|{pstreet} {pstreet_n1}, {ppostcode} {ppost}');

        $t->options['showViewData']=array(
            'title'=>'Kontrahent',
            'subtitle'=>'{pname}',
            'groups'=>array(
                array(
                    'title'=>'Kontrahent',
                    'rows'=>array(
                        array(
                            array('name'=>'pname','caption'=>'Nazwa'),
                            array('name'=>'pnip','caption'=>'NIP'),
                        ),
                        array(
                            array('name'=>'pstreet','caption'=>'Adres główny','tpl'=>'{pstreet} {pstreet_n1}|{ppostcode} {ppost}|{pcountry}'),
                            array('name'=>'kstreet','caption'=>'Adres korespondencji','tpl'=>'{kstreet} {kstreet_n1}|{kpostcode} {kpost}|{kcountry}','empty'=>false),
                        ),
                        array(
                            array('name'=>'pphone','caption'=>'Dane kontaktowe','tpl'=>'{pphone1}|{pemail|email}'),
                        ),
                    ),
                ),
            ),
        );

        echo $t->render();

    }



}