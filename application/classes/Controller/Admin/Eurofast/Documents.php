<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  Zarządzanie dokumentami
************************************************************************************/

class Controller_Admin_Eurofast_Documents extends Controller {
    private $view;
    public $messageStr;
    public $messageClass = 'alert-success';

    public function before() {
        if (!Model_BSX_Core::testPermission('admin')) return;
        if (!Model_BSX_Admin::test_login($this)) return;
        parent::before();

        $this->view=Model_BSX_Core::create_view($this,'index_standard','padmin');

        $this->root=Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/';
        $this->link=$this->root.'eurofast/documents';
        $this->view->sidebar_active='home';
        $this->view->sidebar_active_menu='documents';
        $this->table='eurofast_docs';


        $this->view->path=array(
            array('caption'=>'Home','url'=>$this->root),
            array('caption'=>'Bazy dostawcze','url'=>$this->link),
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

    function fnc_OrderCell($table,$row,$name,$value)
    {
        if ($name=='ndate')
        {
            return '<span style="font-size:24px;"><span><i class="fa fa-file-pdf-o"></i></span>  <a href="/admin/eurofast/documents/edit/'.$row['id'].'">'.$value.'</a></span>';

        }
        return $value;
    }

    public function action_list()
    {
        $this->view->title='Dokumenty';

        $t=new Model_BSX_Table($this->table,'tbl',$this);
        $t->onCell='fnc_OrderCell';
        $t->formURL=$this->link.'/list';
        $t->tableView=Model_BSX_Core::create_view($this,'part_table_std','padmin');
        $t->showView=Model_BSX_Core::create_view($this,'part_table_show_std','padmin');
        $t->tableView->title='Dokumenty';
        $t->showPagesExacly=true;
        $t->showSearchPanel=false;
        $t->showEditBtn=false;
        $t->showDelBtn=false;
        $t->showShowBtn=false;
        $t->linkEdit = $this->link . '/edit/{id}';
        $t->options['toolbarVisible'] = true;
        $t->options['optionColumnWidth']=100;
        $t->options['toolButtons'] = array(array('caption' => 'Nowe dokumenty', 'href' => '/admin/eurofast/documents/edit'));

        $t->addField('ndate','Data','left');
        echo $t->render();
    }


    public function action_edit()
    {
        $id = (int)$this->request->param('cmd');
        if ($id<=0) {
            $this->view->path[] = array('caption' => 'Nowe dokumenty', 'url' => $this->link . '/edit');
            $this->view->title = 'Nowe dokumenty';
        } else {
            $this->view->path[] = array('caption' => 'Dokumenty', 'url' => $this->link . '/edit/'.$id);
            $this->view->title = 'Dokumenty';
        }

        $system = Model_BSX_System::init();

        $fields = array('ndate');
        $save = (getGetPost('save') != '');

        $f = array();

        if ($id > 0) {
            //istniejące
            if ($save) {//aktualizacja
                $dane = array();
                foreach ($_POST as $name => $value) if (in_array($name, $fields)) $dane[$name] = $value;
                sql_update('eurofast_docs', $dane, $id);

                $f = sql_row('SELECT * FROM eurofast_docs WHERE id=:id', array(':id' => $id));

                $this->messageStr = 'Informacje zostały zaktualizowane!';
            }

            $f = sql_row('SELECT * FROM eurofast_docs WHERE id=:id', array(':id' => $id));
        } else {
            //nowe
            $f['ndate'] = date('Y-m-d');

            if ($save) {
                foreach ($_POST as $name => $value) if (in_array($name, $fields)) $f[$name] = $value;
                $id = sql_insert('eurofast_docs', $f);
                $f = sql_row('SELECT * FROM eurofast_docs WHERE id=:id', array(':id' => $id));



                $this->messageStr = 'Nowy dzień został utworzony.';
            }
        }

        $view = Model_BSX_Core::create_view($this, 'Eurofast/part_form_admin_docs_edit', 'padmin');
        $view->set('f', $f);
        $view->set('id', $id);

        if ($id>0) {
            $g = new Model_BSX_Attachments($this->table, $id, 'attachments_' . $this->table, $this, Model_BSX_Core::create_view($this, 'Eurofast/part_attachments_std', 'padmin'),'Eurofast/part_attachments_std_item_data','padmin');
            $g->type = 1;
            $g->url = $this->link . 'edit/' . $id;
            $g->assetsURL = '/assets/admin_cms/';
            $g->assetFolder='uploads'.DIRECTORY_SEPARATOR.'eurofast';
            $view->set('attachments', $g->render());
        }

        echo $view;

    }

}