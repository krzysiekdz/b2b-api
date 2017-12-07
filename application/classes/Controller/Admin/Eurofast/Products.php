<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  Zarządzanie produktami
************************************************************************************/

class Controller_Admin_Eurofast_Products extends Controller {
    private $view;
    public $messageStr;
    public $messageClass = 'alert-success';

    public function before() {
        if (!Model_BSX_Core::testPermission('admin')) return;
        if (!Model_BSX_Admin::test_login($this)) return;
        parent::before();

        $this->view=Model_BSX_Core::create_view($this,'index_standard','padmin');

        $this->root=Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/';
        $this->link=$this->root.'eurofast/products';
        $this->view->sidebar_active='home';
        $this->view->sidebar_active_menu='products';
        $this->table='bs_stockindex';

        $this->templateList=array(0=>'Szablon 1',1=>'Szablon 2');
        $this->buyersList=Arr::merge(array(0=>'Dowolna'),sql_rows_asselect('SELECT id, pname FROM eurofast_buyers','{pname}'));
        $this->registersList=Arr::merge(array(0=>'Dowolna'),sql_rows_asselect('SELECT id, pname FROM eurofast_registers','{pname}'));


        $this->view->path=array(
            array('caption'=>'Home','url'=>$this->root),
            array('caption'=>'Produkty','url'=>$this->link),
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
        $this->view->title='Indeks produktów';

        $t=new Model_BSX_Table($this->table,'tbl',$this);
        $t->formURL=$this->link.'/list';
        $t->tableView=Model_BSX_Core::create_view($this,'part_table_std','padmin');
        $t->showView=Model_BSX_Core::create_view($this,'part_table_show_std','padmin');
        $t->tableView->title='Bazy dostawcze';
        $t->showPagesExacly=true;
        $t->showSearchPanel=false;
        $t->showEditBtn=true;
        $t->showDelBtn=true;
        $t->showShowBtn=false;
        $t->linkEdit = $this->link . '/edit/{id}';
        $t->options['toolbarVisible'] = true;
        $t->options['optionColumnWidth']=100;
        $t->options['toolButtons'] = array(array('caption' => 'Nowy produkt', 'href' => '/admin/eurofast/products/edit'));

        $t->addField('pname','Nazwa','left');
        $t->addField('pplace','Skrót','left');
        $t->addField('psymbol','Symbol','left');
        echo $t->render();
    }


    public function action_edit()
    {
        $id = (int)$this->request->param('cmd');
        if ($id<=0) {
            $this->view->path[] = array('caption' => 'Nowy produkt', 'url' => $this->link . '/edit');
            $this->view->title = 'Nowy produkt';
        } else {
            $this->view->path[] = array('caption' => 'Edycja produktu', 'url' => $this->link . '/edit/'.$id);
            $this->view->title = 'Edycja produktu';
        }


        $fields = array('pname','psymbol','pplace');
        $save = (getGetPost('save') != '');

        $f = array();

        if ($id > 0) {
            //istniejące
            if ($save) {//aktualizacja
                $dane = array();
                foreach ($_POST as $name => $value) if (in_array($name, $fields)) $dane[$name] = $value;
                sql_update('bs_stockindex', $dane, $id);
                $this->messageStr = 'Informacje o produkcie zostały zaktualizowane!';
            }

            $f = sql_row('SELECT * FROM bs_stockindex WHERE id=:id', array(':id' => $id));
        } else {
            //nowe
            $f['add_time'] = date('Y-m-d H:i:s');
            $f['modyf_time'] = $f['add_time'];
            $f['add_id_user'] = $_SESSION['admin_user']['id'];
            $f['modyf_id_user'] = $f['add_id_user'];

            $f['pname'] = $f['psymbol'] = $f['pplace'] = '';
            $f['ptype']=1;

            if ($save) {
                foreach ($_POST as $name => $value) if (in_array($name, $fields)) $f[$name] = $value;
                $id = sql_insert('bs_stockindex', $f);
                $f = sql_row('SELECT * FROM bs_stockindex WHERE id=:id', array(':id' => $id));
                $this->messageStr = 'Nowy produkt został zapisany.';
            }
        }

        $view = Model_BSX_Core::create_view($this, 'Eurofast/part_form_admin_products_edit', 'padmin');
        $view->set('f', $f);
        $view->set('id', $id);
        echo $view;
    }

}