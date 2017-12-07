<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  Zarządzanie oknem faktur
************************************************************************************/

class Controller_Admin_Eurofast_Invoices extends Controller
{
    private $view;
    public $messageStr;
    public $messageClass = 'alert-success';

    public function before()
    {
        if (!Model_BSX_Core::testPermission('admin')) return;
        if (!Model_BSX_Admin::test_login($this)) return;
        parent::before();

        $this->view = Model_BSX_Core::create_view($this, 'index_standard', 'padmin');

        $this->root = Model_BSX_Core::$bsx_cfg['padmin_prefix'] . '/';
        $this->link = $this->root . 'eurofast/invoices';
        $this->view->sidebar_active = 'home';
        $this->view->sidebar_active_menu = 'invoices';

        $this->table = 'bs_invoices';
        $this->statusList = array(
            0 => 'W trakcie edycji',
            2 => 'Zatwierdzona',
        );


        $this->view->path = array(
            array('caption' => 'Home', 'url' => $this->root),
            array('caption' => 'Faktury', 'url' => $this->link),
        );

        BinUtils::buffer_start();
    }

    public function after()
    {
        $this->view->content = BinUtils::buffer_end();
        Model_BSX_Core::create_sidebar($this, $this->view);  //menu panelu administracyjnego
        $this->response->body($this->view);
        parent::after();
    }


    public function action_index()
    {
        return $this->action_vat();
    }

    public function action_proforma()
    {
        return $this->list_invoices(1);
    }

    public function action_vat()
    {
        return $this->list_invoices(0);
    }

    public function list_invoices($type)
    {
        $t = new Model_BSX_Table($this->table, 'tbl', $this);
        $this->viewType=$type;
        if ($type==0) {
            $this->view->path[] = array('caption' => 'Lista', 'url' => $this->link . '/vat');
            $this->view->title = 'Faktury VAT';
            $t->formURL = $this->link . '/vat';
            $t->where = 'ntype=0 AND nsubtype>0 AND idreseller>0 AND iduser=0';
        } else {
            $this->view->path[] = array('caption' => 'Lista', 'url' => $this->link . '/proforma');
            $this->view->title = 'Faktury proforma';
            $t->formURL = $this->link . '/proforma';
            $t->where = 'ntype=0 AND nsubtype=0 AND idreseller>0 AND iduser=0';
        }


        $t->tableView = Model_BSX_Core::create_view($this, 'part_table_std', 'padmin');
        $t->showView = Model_BSX_Core::create_view($this, 'part_table_show_std', 'padmin');
        $t->tableView->title = $this->view->title;
        $t->showPagesExacly = true;
        $t->showSearchPanel = true;
        $t->showEditBtn = false;
        $t->showDelBtn = false;
        $t->showShowBtn = false;
        $t->linkEdit = $this->link . '/edit/{id}';
        $t->linkDel = $this->link . '/delete/{id}';
        $t->linkShow = $this->link . '/show/{id}';
        $t->onCellOptions = 'fnc_opcje';
        $t->options['toolbarVisible'] = true;
        $t->addField('nnodoc', 'Numer', 'center', 'varchar', '');
        $t->addField('ndate_issue', 'Data wystawienia', 'center', 'date', '');
        $t->addField('nstatus', 'Status', 'center', 'select', '150px', $this->statusList);
        $t->addField('pname', 'Odbiorca', 'left', 'varchar', '', null, '<b>{pname}</b>|{pstreet}, {ppostcode} {pcity}');
        $t->addField('nstotal_n', 'Wartości netto', 'right', 'price', '', null, '{nstotal_n|price} {ncurrency}');

        echo $t->render();
    }

    public function fnc_opcje($view, $row)
    {
        $link = $view->linkShow;
        foreach ($row as $a => $b) if ($a[0]!='%') $link = str_replace('{' . $a . '}', $b, $link);
        echo '<a href="' . $link . '" class="btn btn-info btn-sm btn-block">Pokaż</a>';

        if ($this->viewType==0) $lFileName='assets/uploads/eurofast/eurofast_orders/'.$row['idorder'].'/Faktura-'.$row['id'].'-'.strtotime($row['add_time']).'.pdf';
        else if ($this->viewType==1) $lFileName='assets/uploads/eurofast/eurofast_orders/'.$row['idorder'].'/Proforma-'.$row['id'].'-'.strtotime($row['add_time']).'.pdf';
        if (is_file($lFileName))
        {
            echo '<a href="' . $lFileName . '" class="btn btn-success btn-sm btn-block">Pobierz</a>';
        }
    }



    public function action_show()
    {
        $id = (int)$this->request->param('cmd');
        $f = sql_row('SELECT * FROM bs_invoices WHERE id=:id', array(':id' => $id));
        if (!$f) die('Brak uprawnień!');


        $products = sql_rows('SELECT * FROM bs_invoices_pr WHERE iddoc=:id', array(':id' => $id));

        if ($f['nsubtype']==0) {
            $this->view->path[] = array('caption' => 'Faktura pro-forma', 'url' => '/admin/eurofast/invoices/show/' . $id);
            $this->view->title = 'Faktura pro-forma';
        } else {
            $this->view->path[] = array('caption' => 'Faktura', 'url' => '/admin/eurofast/invoices/show/' . $id);
            $this->view->title = 'Faktura';
        }

        $view = Model_BSX_Core::create_view($this, 'Eurofast/part_form_invoice', 'padmin');
        $view->set('f', $f);
        $view->set('r', $products);
        $view->set('id', $id);
        echo $view;
    }

}