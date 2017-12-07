<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  Zarządzanie kupującymi
************************************************************************************/

class Controller_Admin_Eurofast_Buyers extends Controller {
    private $view;
    public $messageStr;
    public $messageClass = 'alert-success';

    public function before() {
        if (!Model_BSX_Core::testPermission('admin')) return;
        if (!Model_BSX_Admin::test_login($this)) return;
        parent::before();

        $this->view=Model_BSX_Core::create_view($this,'index_standard','padmin');

        $this->root=Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/';
        $this->link=$this->root.'eurofast/buyers';
        $this->view->sidebar_active='home';
        $this->view->sidebar_active_menu='buyers';
        $this->table='eurofast_buyers';


        $this->view->path=array(
            array('caption'=>'Home','url'=>$this->root),
            array('caption'=>'Buyers Clients','url'=>$this->link),
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
        $this->view->title='Buyer Client';

        $t=new Model_BSX_Table($this->table,'tbl',$this);
        $t->formURL=$this->link.'/list';
        $t->tableView=Model_BSX_Core::create_view($this,'part_table_std','padmin');
        $t->showView=Model_BSX_Core::create_view($this,'part_table_show_std','padmin');
        $t->tableView->title='Buyer Client';
        $t->showPagesExacly=true;
        $t->showSearchPanel=true;
        $t->showEditBtn=true;
        $t->showDelBtn=true;
        $t->showShowBtn=false;
        $t->linkEdit = $this->link . '/edit/{id}';
        $t->options['toolbarVisible'] = true;
        $t->options['optionColumnWidth']=100;
        $t->options['toolButtons'] = array(array('caption' => 'Nowa baza', 'href' => '/admin/eurofast/buyers/edit'));

        $t->addField('pname','Nazwa','left');
        $t->addField('pcode', 'Kod', 'left', 'varchar');
        echo $t->render();
    }


    public function action_edit()
    {
        $id = (int)$this->request->param('cmd');
        if ($id<=0) {
            $this->view->path[] = array('caption' => 'Nowa klient', 'url' => $this->link . '/edit');
            $this->view->title = 'Nowy klient';
        } else {
            $this->view->path[] = array('caption' => 'Klient', 'url' => $this->link . '/edit/'.$id);
            $this->view->title = 'Klient';
        }

        $system = Model_BSX_System::init();

        $fields = array('pname','pcode','pnip','paddress');
        $save = (getGetPost('save') != '');

        $f = array();

        if ($id > 0) {
            //istniejące
            if ($save) {//aktualizacja
                $dane = array();
                foreach ($_POST as $name => $value) if (in_array($name, $fields)) $dane[$name] = $value;
                sql_update($this->table, $dane, $id);

                $f = sql_row('SELECT * FROM '.$this->table.' WHERE id=:id', array(':id' => $id));

                $this->messageStr = 'Informacje zostały zaktualizowane!';
            }

            $f = sql_row('SELECT * FROM '.$this->table.' WHERE id=:id', array(':id' => $id));
        } else {
            //nowe
            $f['pname'] = $f['pcode'] = $f['pnip'] = $f['paddress'] = '';

            if ($save) {
                foreach ($_POST as $name => $value) if (in_array($name, $fields)) $f[$name] = $value;
                $id = sql_insert($this->table, $f);
                $f = sql_row('SELECT * FROM '.$this->table.' WHERE id=:id', array(':id' => $id));



                $this->messageStr = 'Nowy klient został zapisany.';
            }
        }

        $view = Model_BSX_Core::create_view($this, 'Eurofast/part_form_admin_buyers_edit', 'padmin');
        $view->set('f', $f);
        $view->set('id', $id);
        echo $view;

    }

}