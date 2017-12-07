<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  Zarządzanie Użytkownikami systemu
************************************************************************************/

class Controller_Admin_Eurofast_Users extends Controller {
    public $messageStr;
    public $messageClass = 'alert-success';
    private $view;

    public function before() {
        if (!Model_BSX_Core::testPermission('admin')) return;
        if (!Model_BSX_Admin::test_login($this)) return;
        parent::before();

        $this->view=Model_BSX_Core::create_view($this,'index_standard','padmin');

        $this->root=Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/';
        $this->link=$this->root.'eurofast/users';
        $this->view->sidebar_active='home';
        $this->view->sidebar_active_menu='start';

        $this->table='bs_users';
        $this->statusList=array(0=>'Administrator',4=>'Kierowca',5=>'Klient',7=>'Reseller',8=>'Spedycja',9=>'ZOWA' ,10=>'{#background=gray#}Nieaktywny');
        $this->resellerList=Arr::merge(array(0=>'Brak'),sql_rows_asselect('SELECT id, pname FROM bs_users WHERE pstatus=7 AND pname!=""','{pname}'));

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

    public function fnc_opcje($view, $row)
    {
        $link = $view->linkEdit;
        foreach ($row as $a => $b) if ($a[0]!='%')  $link = str_replace('{' . $a . '}', $b, $link);
        echo '<a href="' . $link . '" class="btn btn-success btn-sm btn-block">Płatności</a>';

    }

    public function onUserCellStyle($table, $row, $name, $value)
    {
        if ($name=='nsaldo' && $value<0) return 'color:red;';
        else return null;
    }

    public function action_list()
    {
        $this->view->path[]=array('caption'=>'Lista użytkowników','url'=>$this->link.'/list');
        $this->view->title='Użytkownicy';

        $showInfo='';
        if (getGetPost('saveForm')!='')
        {
            $d=array();
            $d['pstatus']=(int)getPost('pstatus');
            if (getPost('idowner')>0) $d['idowner']=(int)getPost('idowner');
            $d['ndiscount']=(double)getPost('ndiscount');
            $d['mslug']=getPost('mslug');
            $d['pemail']=getPost('pemail');
            $p=getPost('ppass');
            if ($p!='') $d['ppass']=sha1($p);
            sql_update('bs_users',$d,getGetPost('tbl_p1'));
            $showInfo='Zmiany zostały zapisane!';
        }

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
        $t->where='(idowner=0 OR idowner IS NULL)';
        $t->linkEdit = $this->link . '/payments/{id}';
        $t->onCellOptions = 'fnc_opcje';
        $t->onCellStyle='onUserCellStyle';
        if ($showInfo!='') $t->showMessage($showInfo);

        $t->addField('id','ID','center','int','60px');
        $t->addField('plastname','Imię i nazwisko','left','varchar','',null,'{plastname} {pfirstname}');
        $t->addField('pemail','E-mail','left','varchar');
        $t->addField('nsaldo','Saldo','right','price');
        $t->addField('pstatus','Status','center','select','',$this->statusList);
        $t->addField('ndiscount','Rabat (/m<sup>3</sup>)','right','price');

        if ($t->cmd=='show')
        {
            $id=(int)getGetPost('tbl_p1');

            $t->formMethod="post";
            $view=Model_BSX_Core::create_view($this,'Eurofast/part_form_admin_users','padmin');
            $view->table=$t;
            $view->controller=$this;
            $t->row['%tbl2']=$view;

            $g=new Model_BSX_Attachments($this->table,$id,'attachments_'.$this->table,$this,Model_BSX_Core::create_view($this,'Eurofast/part_attachments_std','padmin'),'Eurofast/part_attachments_std_item','padmin');
            $g->type=1;
            $g->where='(idreseller='.$id.') OR (iduser='.$id.')';
            $g->url=$this->link.'edit/'.$id;
            $g->assetsURL='/assets/admin_cms/';
            $t->row['%tbl3']=$g->render();


        }

        $t->options['showViewData']=array(
            'title'=>'Użytkownik',
            'subtitle'=>'{plastname} {pfirstname} - {pname}',
            'groups'=>array(
                array(
                    'title'=>'Informacje',
                    'rows'=>array(
                        array(
                            array('name'=>'pfirstname','caption'=>'Imie', 'tpl'=>'{pfirstname} {plastname}'),
                            array('name'=>'pstatus','caption'=>'Status','type'=>'select','data'=>$this->statusList),
                        ),
                        array(
                            array('name'=>'pemail','caption'=>'E-mail'),
                            array('name'=>'ndiscount','caption'=>'Rabat','type'=>'price', 'sufix'=>" zł /m<sup>3</sup>"),
                        ),
                        array(
                            array('name'=>'nsaldo','caption'=>'Saldo','type'=>'price', 'sufix'=>'&nbsp; PLN'),
                        ),
                    ),
                ),
                array(
                    'title'=>'Edycja',
                    'rows'=>array(
                        array(
                            'options'=>array('class'=>'col-md-12'),
                            array('name'=>'%tbl2'),
                        ),
                        array(
                            'options'=>array('class'=>'col-md-12'),
                            array('name'=>'%tbl3'),
                        ),
                    ),
                ),
            ),
        );


        echo $t->render();
    }

    public function onCellStyle($table, $row, $name, $value)
    {
        if ($name=='pvalue' && $value<0) return 'color:red;';
        else return null;
    }

    public function action_payments()
    {
        $id = (int)$this->request->param('cmd');
        $this->view->path[] = array('caption' => 'Płatności', 'url' => $this->link . '/payments/' . $id);

        $user = sql_row('SELECT id, idowner, nsaldo, pfirstname, plastname FROM bs_users WHERE id=:id', array(':id' => $id));
        if (!$user) die('Brak uprawień!');

        $this->view->title = 'Historia płatności użytkownika: ' . $user['pfirstname'] . ' ' . $user['plastname'];

        $t = new Model_BSX_Table('eurofast_saldo', 'tbl', $this);
        $t->onCellStyle='onCellStyle';
        $t->formURL = $this->link . '/list';
        $t->tableView = Model_BSX_Core::create_view($this, 'part_table_std', 'preseller');
        $t->showView = Model_BSX_Core::create_view($this, 'part_table_show_std', 'preseller');
        $t->tableView->title = 'Historia płatności';
        $t->showPagesExacly = true;
        $t->showSearchPanel = true;
        $t->showEditBtn = true;
        $t->showDelBtn = true;
        $t->showShowBtn = false;
        $t->linkDel = $this->link . '/paydel/'.$id.'/{id}';
        $t->linkEdit = $this->link . '/pay/'.$id.'/{id}';
        $t->where = 'iduser=' . $id;
        if ($user['nsaldo']<0) $addstyle=' style="color:red;"'; else $addstyle='';
        $t->headerHTML = '<div class="note note-info">Saldo użytkownika: <strong'.$addstyle.'>' . BinUtils::price($user['nsaldo'], 'PLN') . '</strong>.</div>';
        $t->options['toolbarVisible'] = true;
        $t->options['toolButtons'] = array(array('caption' => 'Nowa wpłata/wypłata', 'href' => '/admin/eurofast/users/pay/' . $id));

        $t->addField('pdate', 'Data', 'center', 'datetime');
        $t->addField('ptitle', 'Opis', 'left');
        $t->addField('pvalue', 'Kwota', 'right', 'price');
        echo $t->render();
    }


    public function action_pay()
    {
        $id = (int)$this->request->param('cmd');
        $iditem = (int)$this->request->param('id');
        $this->view->path[] = array('caption' => 'Płatności', 'url' => $this->link . '/payments/' . $id);
        $this->view->path[] = array('caption' => 'Wpłata/Wypłata', 'url' => $this->link . '/pay/' . $id.'/'.$iditem);

        $this->view->title='Płatność';

        $user = sql_row('SELECT id, idowner, nsaldo, pfirstname, plastname FROM bs_users WHERE id=:id', array(':id' => $id));
        if (!$user) die('Brak uprawień!');

        $fields = array('ptitle','pvalue','pdate');
        $save = (getGetPost('save') != '');


        if ($iditem > 0) {
            $f = sql_row('SELECT u.idowner FROM eurofast_saldo s LEFT JOIN bs_users u ON u.id=s.iduser WHERE s.id=:id', array(':id' => $iditem));
            if (!$f) die('Brak zamówienia!');

            //istniejące
            if ($save) {//aktualizacja
                $dane = array();
                $dane['iduser']=$id;
                foreach ($_POST as $name => $value) if (in_array($name, $fields)) $dane[$name] = $value;
                sql_update('eurofast_saldo', $dane, $iditem);

                $ss=sql_row('SELECT sum(pvalue) as suma FROM eurofast_saldo WHERE iduser=:user',array(':user'=>$id));
                if ($ss) sql_query('UPDATE bs_users SET nsaldo=:saldo WHERE id=:user',array(':saldo'=>$ss['suma'],':user'=>$id));

                $f = sql_row('SELECT * FROM eurofast_saldo WHERE id=:id', array(':id' => $iditem));


                $this->messageStr = 'Płatność została zapisana!';

            }

            $f = sql_row('SELECT * FROM eurofast_saldo WHERE id=:id', array(':id' => $iditem));
        } else {
            //nowe
            $f['add_time'] = date('Y-m-d H:i:s');
            $f['modyf_time'] = $f['add_time'];
            $f['add_id_user'] = $_SESSION['user_user']['id'];
            $f['modyf_id_user'] = $f['add_id_user'];
            $f['ptitle']='Wpłata';
            $f['pvalue']=0;
            $f['pdate']=date('Y-m-d');
            $f['iduser']=$id;


            if ($save) {
                foreach ($_POST as $name => $value) if (in_array($name, $fields)) $f[$name] = $value;
                $iditem = sql_insert('eurofast_saldo', $f);
                $f = sql_row('SELECT * FROM eurofast_saldo WHERE id=:id', array(':id' => $iditem));
                $this->messageStr = 'Płatność została zapisana';

                $ss=sql_row('SELECT sum(pvalue) as suma FROM eurofast_saldo WHERE iduser=:user',array(':user'=>$id));
                if ($ss) sql_query('UPDATE bs_users SET nsaldo=:saldo WHERE id=:user',array(':saldo'=>$ss['suma'],':user'=>$id));

            }
        }

        $view = Model_BSX_Core::create_view($this, 'Eurofast/part_form_reseller_payments_edit', 'padmin');
        $view->set('f', $f);
        $view->set('id', $id);
        $view->set('subid',$iditem);
        echo $view;

    }

    public function action_paydel()
    {
        $idu = (int)$this->request->param('cmd');
        $idp = (int)$this->request->param('id');
        $w = sql_row('SELECT u.idowner FROM eurofast_saldo s LEFT JOIN bs_users u ON u.id=s.iduser WHERE s.id=:id', array(':id' => $idp));
        if ($w) {
                sql_query('DELETE FROM eurofast_saldo WHERE id=:id', array(':id' => $idp));

                $ss=sql_row('SELECT sum(pvalue) as suma FROM eurofast_saldo WHERE iduser=:user',array(':user'=>$idu));
                if ($ss) sql_query('UPDATE bs_users SET nsaldo=:saldo WHERE id=:user',array(':saldo'=>$ss['suma'],':user'=>$idu));
        }
        return $this->action_payments();
    }

}