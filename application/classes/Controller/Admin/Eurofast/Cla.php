<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  Zarządzanie bazami celnymi
************************************************************************************/

class Controller_Admin_Eurofast_Cla extends Controller {
    private $view;
    public $messageStr;
    public $messageClass = 'alert-success';

    public function before() {
        if (!Model_BSX_Core::testPermission('admin')) return;
        if (!Model_BSX_Admin::test_login($this)) return;
        parent::before();

        $this->view=Model_BSX_Core::create_view($this,'index_standard','padmin');

        $this->root=Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/';
        $this->link=$this->root.'eurofast/cla';
        $this->view->sidebar_active='home';
        $this->view->sidebar_active_menu='dane';
        $this->table='eurofast_cla';


        $this->view->path=array(
            array('caption'=>'Home','url'=>$this->root),
            array('caption'=>'Bazy celne','url'=>$this->link),
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
        $this->view->title='Bazy celne';

        $t=new Model_BSX_Table($this->table,'tbl',$this);
        $t->formURL=$this->link.'/list';
        $t->tableView=Model_BSX_Core::create_view($this,'part_table_std','padmin');
        $t->showView=Model_BSX_Core::create_view($this,'part_table_show_std','padmin');
        $t->tableView->title='Bazy celne';
        $t->showPagesExacly=true;
        $t->showSearchPanel=true;
        $t->showEditBtn=true;
        $t->showDelBtn=true;
        $t->showShowBtn=false;
        $t->linkEdit = $this->link . '/edit/{id}';
        $t->options['toolbarVisible'] = true;
        $t->options['optionColumnWidth']=100;
        $t->options['toolButtons'] = array(array('caption' => 'Nowa baza', 'href' => '/admin/eurofast/cla/edit'));

        $t->addField('pname','Nazwa bazy','left');
        $t->addField('pstreet', 'Adres', 'left', 'varchar', '', null, '{pstreet}, {ppostcode} {pcity}|{pcountry}');
        echo $t->render();
    }


    public function action_edit()
    {
        $id = (int)$this->request->param('cmd');
        if ($id<=0) {
            $this->view->path[] = array('caption' => 'Nowa baza', 'url' => $this->link . '/edit');
            $this->view->title = 'Nowa baza celna';
        } else {
            $this->view->path[] = array('caption' => 'Baza celna', 'url' => $this->link . '/edit/'.$id);
            $this->view->title = 'Baza celna';
        }

        $system = Model_BSX_System::init();

        $fields = array('pname','pstreet','ppostcode','pcity','pcountry','pemail','klat','klong');
        $save = (getGetPost('save') != '');

        $f = array();

        if ($id > 0) {
            //istniejące
            if ($save) {//aktualizacja
                $dane = array();
                foreach ($_POST as $name => $value) if (in_array($name, $fields)) $dane[$name] = $value;
                if ($dane['klat']=='' || $dane['klong']=='') {
                    $r = Model_BSX_Google::getCoordinates($dane['pcountry'] . ', ' . $dane['pcity'] . ', ' . $dane['pstreet']);
                    if ($r) {
                        $dane['klat'] = $r['lat'];
                        $dane['klong'] = $r['long'];
                    }
                }
                sql_update($this->table, $dane, $id);

                $f = sql_row('SELECT * FROM '.$this->table.' WHERE id=:id', array(':id' => $id));

                $this->messageStr = 'Informacje zostały zaktualizowane!';
            }

            $f = sql_row('SELECT * FROM '.$this->table.' WHERE id=:id', array(':id' => $id));
        } else {
            //nowe
            $f['add_time'] = date('Y-m-d H:i:s');
            $f['modyf_time'] = $f['add_time'];
            $f['add_id_user'] = $_SESSION['admin_user']['id'];
            $f['modyf_id_user'] = $f['add_id_user'];

            $f['pname'] = $f['pstreet'] = $f['ppostcode'] = $f['pcity'] = '';
            $f['pcountry']='Polska';
            $f['klat']='';
            $f['klong']='';
            $f['pemail']='';

            if ($save) {
                foreach ($_POST as $name => $value) if (in_array($name, $fields)) $f[$name] = $value;
                if ($f['klat']=='' || $f['klong']=='') {
                    $r = Model_BSX_Google::getCoordinates($f['pcountry'] . ', ' . $f['pcity'] . ', ' . $f['pstreet']);
                    if ($r) {
                        $f['klat'] = $r['lat'];
                        $f['klong'] = $r['long'];
                    }
                }
                $id = sql_insert($this->table, $f);
                $f = sql_row('SELECT * FROM '.$this->table.' WHERE id=:id', array(':id' => $id));



                $this->messageStr = 'Nowa baza celna została zapisana.';
            }
        }

        $view = Model_BSX_Core::create_view($this, 'Eurofast/part_form_admin_cla_edit', 'padmin');
        $view->set('f', $f);
        $view->set('id', $id);

        $g=new Model_BSX_Attachments($this->table,$id,'attachments_'.$this->table,$this,Model_BSX_Core::create_view($this,'Eurofast/part_attachments_std','padmin'),'Eurofast/part_attachments_std_item','padmin');
        $g->type=1;
        $g->where='idclo='.$id;
        $g->url=$this->link.'edit/'.$id;
        $g->assetsURL='/assets/admin_cms/';
        $view->set('attachments',$g->render());


        echo $view;

    }

}