<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  Zarządzanie oknem powitalnym
************************************************************************************/

class Controller_Admin_Fuelgroup_Orders extends Controller
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
        $this->link = $this->root . 'fuelgroup/orders';
        $this->view->sidebar_active = 'fuel';
        $this->view->sidebar_active_menu = 'orders';

        $this->table = 'eurofast_orders';
        $this->statusList = array(
            0 => 'W trakcie edycji (U)',
            1 => 'Zlecone (U)',
            2 => 'Zaakceptowane (R)',
            3 => 'Do realizacji (A)',
            4 => 'Wysłano awizacje',
            5 => 'Zrealizowane',
            10 => 'Anulowane',
        );
        //$this->baseList=Arr::merge(array(0=>'Brak'),sql_rows_asselect('SELECT id, pname FROM eurofast_bazy','{pname}'));
        //$this->cloList=Arr::merge(array(0=>'Brak'),sql_rows_asselect('SELECT id, pname FROM eurofast_cla','{pname}'));
        //$this->spedycjaList=Arr::merge(array(0=>'Brak'),sql_rows_asselect('SELECT id, pname FROM bs_users WHERE pstatus=8','{pname}'));


        $this->view->path = array(
            array('caption' => 'Home', 'url' => $this->root),
            array('caption' => 'Zamówienia', 'url' => $this->link),
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
        return $this->action_orders();
    }

    public function action_list()
    {
        return $this->action_orders();
    }

    function fnc_OrderCell($table,$row,$name,$value)
    {
        if ($name=='nstatus')
        {
            $v=$value;
            $value=$this->statusList[$value];
            if ($v==2) {
                $value='<span style="color:red;"> Czeka na realizację </span>';
                if ($row['idbase']<=0) {
                    $value='<span style="color:red;"> Wybierz<br>bazę dostawczą </span>';
                } else if ($row['idclo']<=0) {
                    $value='<span style="color:red;"> Wybierz bazę celną </span>';
                } else if ($row['idspedycja']<=0) {
                    $value='<span style="color:red;"> Wybierz przewoźnika </span>';
                } else if ($row['trname']=='' || $row['tname']=='')
                {
                    $value='<span style="color:red;"> Czeka na spedytora </span>';
                }
            } else if ($v==3)
            {
                $value='<span class="label label-sm label-danger"> DO REALIZACJI </span>';
            } else if ($v==4)
            {
                $value='<span class="label label-sm label-info"> '.$value.' </span>';
            } else if ($v==5)
            {
                $value='<span class="label label-sm label-success"> '.$value.' </span>';
            } else if ($v==10)
            {
                $value='<span class="label label-sm label-warning"> '.$value.' </span>';
            }
            if ($row['pcorrected']!='' && $v<5)
            {
                if ($row['pcorrected_type']==0) $value.='<br><span style="color:red;">ZMIANA ADRESU<br>'.$row['pcorrected'].'</span>';
                if ($row['pcorrected_type']==1) $value.='<br><span style="color:red;">ZMIANA KIEROWCY<br>'.$row['pcorrected'].'</span>';
                if ($row['pcorrected_type']==2) $value.='<br><span style="color:red;">ZMIANA AUTA<br>'.$row['pcorrected'].'</span>';
                if ($row['pcorrected_type']==3) $value.='<br><span style="color:red;">ZMIANA BAZY CELNEJ<br>'.$row['pcorrected'].'</span>';
                if ($row['pcorrected_type']==4) $value.='<br><span style="color:red;">ZMIANA BAZY DOSTAWCZEJ<br>'.$row['pcorrected'].'</span>';
                if ($row['pcorrected_type']==5) $value.='<br><span style="color:red;">ZMIANA NACZEPY<br>'.$row['pcorrected'].'</span>';

            }
        } else if ($name=='bpname' && $value=='') {
            $value = '<select class="form-control" style="width:120px;" name="idbase" data-id="'.$row['id'].'">';
            foreach ($this->baseList as $id => $v) {
                if ($v==$id) $sel=' selected'; else $sel='';
                $value.='<option value="'.$id.'" '.$sel.'>'.$v.'</option>';
            }
            $value.='</select>';
        } else if ($name=='cpname' && $value=='') {
            $value = '<select class="form-control" style="width:120px;" name="idclo" data-id="'.$row['id'].'">';
            foreach ($this->cloList as $id => $v) {
                if ($v==$id) $sel=' selected'; else $sel='';
                $value.='<option value="'.$id.'" '.$sel.'>'.$v.'</option>';
            }
            $value.='</select>';
        } else if ($name=='upname' && $value=='') {
                $value = '<select class="form-control" style="width:120px;" name="idspedycja" data-id="'.$row['id'].'">';
                foreach ($this->spedycjaList as $id => $v) {
                    if ($v==$id) $sel=' selected'; else $sel='';
                    $value.='<option value="'.$id.'" '.$sel.'>'.$v.'</option>';
                }
                $value.='</select>';
        } else if ($name=='%address') {
                $value='';
                $rr=sql_rows('SELECT id, pname, pstreet, ppostcode, pcity, pcountry FROM eurofast_orders_pr WHERE iddoc=:id',array(':id'=>$row['id']));
                foreach ($rr as $rrr) {
                     $value.='<b>'.$rrr['pname'].'</b><br>'.$rrr['pstreet'].', '.$rrr['ppostcode'].' '.$rrr['pcity'].', '.$rrr['pcountry'].'<br>';
                }

        } else if ($name=='ndistance') {
            if ($value=='') $value='---';
            else $value='<b>'.($value+(double)$row['ndistance_plus']).'</b> km';
        }

        return $value;
    }

    public function action_ajax()
    {
        $res=array();
        $cmd=getGetPost('cmd');
        $id=getGetPost('id');
        $value=getGetPost('value');
        $name=getGetPost('name');
        $items=getGetPost('items');
        if ($cmd=='setvalue')
        {
            $r=sql_row('SELECT iduser FROM eurofast_orders WHERE id=:id',array(':id'=>$id));
            if ($r && in_array($name,array('idbase','idclo','idspedycja'))) {

                $d = array();
                $d[$name] = $value;
                if ($name=='idspedycja') {
                    $m=sql_row('SELECT * FROM bs_users WHERE id=:id',array(':id'=>$value));
                    if ($m)
                    {
                        $d['trname']=$m['pname'];
                        $d['trcode']=$m['pcode'];
                        $d['trnip']=$m['pnip'];
                        $d['traddress']=$m['ppostcode'].' '.$m['pcity'].', '.$m['pstreet'].', '.$m['pcountry'];
                    }
                }
                sql_update('eurofast_orders', $d, $id);
                Model_Eurofast_Core::changeDistance($id);
            } else {
                $res[]['@run']='alert("Brak uprawnień")';
            }
        } else
        if ($cmd=='del')
        {
            $items=explode(";",$items);
            $fail=false;
            foreach ($items as $id) {
                if ($id<=0) continue;
                $r = sql_row('SELECT id, nstatus FROM eurofast_orders WHERE id=:id', array(':id' => $id));
                if ($r) {
                        sql_query('DELETE FROM eurofast_orders WHERE id=:id', array(':id' => $id));
                } else {
                    $fail=true;
                    $res[]['@run'] = 'alert("Nie znaleziono zamówienia!")';
                }
            }
            if (!$fail) $res[]['@run'] = 'location.reload()';
        } else
        if ($cmd=='accept')
        {
            $items=explode(";",$items);
            $fail=false;
            foreach ($items as $id) {
                if ($id<=0) continue;
                $r = sql_row('SELECT id, nstatus FROM eurofast_orders WHERE id=:id', array(':id' => $id));
                if ($r) {
                    if ($r['nstatus'] == 2) {
                        $err=Model_Eurofast_Core::setStatus($id,3);
                        if (is_string($err))
                        {
                            $fail=true;
                            $res[]['@run'] = 'alert("'.strip_tags($err).'!")';
                        }
                    } else {
                        $fail=true;
                        $res[]['@run'] = 'alert("Nie można przygotować do realizacji tego zamówienia!")';
                    }
                } else {
                    $fail=true;
                    $res[]['@run'] = 'alert("Nie znaleziono zamówienia!")';
                }
            }
            if (!$fail) $res[]['@run'] = 'location.reload()';
        }
        echo json_encode($res);
        exit;
    }

    public function action_orders()
    {
        $this->view->path[] = array('caption' => 'Lista', 'url' => $this->link . '/list');
        $this->view->title = 'Zamówienia przychodzące';


        $t = new Model_BSX_Table($this->table, 'tbl', $this);
        $t->formURL = $this->link . '/list';
        $t->tableView = Model_BSX_Core::create_view($this, 'part_table_std', 'padmin');
        $t->showView = Model_BSX_Core::create_view($this, 'part_table_show_std', 'padmin');
        $t->tableView->title = 'Lista zamówień';
        $t->showPagesExacly = true;
        $t->showSearchPanel = false;
        $t->showEditBtn = false;
        $t->showDelBtn = false;
        $t->showShowBtn = false;
        $t->linkEdit = $this->link . '/edit/{id}';
        $t->linkDel = $this->link . '/delete/{id}';
        $t->linkShow = $this->link . '/show/{id}';
        $t->onCellOptions = 'fnc_opcje';
        $t->onCell='fnc_OrderCell';
        $t->where = 'nstatus>1 AND iduser IS NULL';
        $t->limit=25;
        $t->leftJoin=array('bs_contractors AS b1#b1.id=eurofast_orders.idbase#b1.pname AS bpname',
                           'bs_contractors AS b2#b2.id=eurofast_orders.idclo#b2.pname AS cpname',
                           'bs_company#bs_company.id=eurofast_orders.idspedycja#bs_company.pname AS upname',
            );
        $t->options['toolbarVisible'] = true;
        $t->options['tableClass']='nowrap table-bordered table-striped table-hover';
        $t->options['toolButtons'] = array(array('caption' => 'Realizuj zamówienia', 'href' => '/admin/eurofast/orders/finalize'));
        $t->addField('%checkbox','Z','center','int','20px',null,'<input type="checkbox" name="selR" class="checkSelR" data-id="{id}">');
        $t->addField('nnodoc', 'Numer', 'center', 'varchar', '');
        $t->addField('ndate_issue', 'Data<br>wystawienia', 'center', 'date');
        $t->addField('ndate_term', 'Termin<br>rozładunku', 'center', 'date');
        $t->addField('nstatus', 'Status', 'center', 'select', '');
        $t->addField('nskrot', 'Opis', 'left', 'varchar', '');
        $t->addField('pname', 'Odbiorca', 'left', 'varchar', '', null, '<b>{pname}</b>');
        $t->addField('%address', 'Adresy dostaw', 'left', 'varchar', '');
        $t->addField('bpname', 'Baza<br>dostawcza', 'center', 'varchar', '150px');
        $t->addField('cpname', 'Baza<br>celna', 'center', 'varchar', '150px');
        $t->addField('ndistance', 'Odległość', 'center', '');
        $t->addField('upname', 'Przewoźnik', 'center', 'varchar', '150px');
        $t->addField('nstotal_n', 'Wartości<br>netto', 'right', 'price');
        $t->footerHTML='<a id="selAll">Zaznacz wszystko</a> | <a id="unselAll">Odznacz wszystko</a> | <a id="delAll">Usuń zaznaczone</a> | <a id="accAll">Do realizacji zaznaczone</a>';
        echo $t->render();

        Model_BSX_CoreJS::addJSInit('
          $("select[name=idbase],select[name=idclo],select[name=idspedycja]").change(function(){
              bsxForm("/admin/eurofast/orders/ajax","cmd=setvalue&name="+$(this).attr("name")+"&id="+$(this).attr("data-id")+"&value="+$(this).val());
          });
          $("#selAll").click(function(event){
           $(".checkSelR").each(function() {$(this).parent().addClass("checked");$(this).prop("checked",true); });
          });
          $("#unselAll").click(function(event){
           $(".checkSelR").each(function() {$(this).parent().removeClass("checked");$(this).prop("checked",false); });
          });
          $("#delAll").click(function(event){
           if (!confirm(\'Czy na pewno usunąć wybrane zamówienia? \')) return;
           var ids="";
           $(".checkSelR").each(function() {
             if ($(this).parent().hasClass("checked")) ids=ids+$(this).attr("data-id")+";";
           });
           bsxForm("/admin/eurofast/orders/ajax","cmd=del&items="+ids);
          });
          $("#accAll").click(function(event){
           if (!confirm(\'Czy na pewno zaakceptować wybrane zamówienia? \')) return;
           var ids="";
           $(".checkSelR").each(function() {
             if ($(this).parent().hasClass("checked")) ids=ids+$(this).attr("data-id")+";";
           });
           bsxForm("/admin/eurofast/orders/ajax","cmd=accept&items="+ids);
          });
          $(".checkSelR").each(function() {$(this).parent().removeClass("checked");$(this).prop("checked",false); });
        ');
    }

    public function action_finalize()
    {

        if (Model_Eurofast_Core::createNotifications())
        {
            $this->messageStr='Zamówienia gotowe do realizacji zostały zrealizowane a dokumenty wygenerowane!';
        } else
        {
            $this->messageClass = 'alert-danger';
            $this->messageStr='Wystąpiły problemy przy realizacji zamówień!';
        }
        return $this->action_orders();
    }

    public function fnc_opcje($view, $row)
    {
            $link = $view->linkEdit;
            foreach ($row as $a => $b) if ($a[0]!='%') $link = str_replace('{' . $a . '}', $b, $link);
            echo '<a href="' . $link . '" class="btn btn-success btn-sm btn-block">Edycja</a>';
            $link = $view->linkDel;
            foreach ($row as $a => $b) if ($a[0]!='%') $link = str_replace('{' . $a . '}', $b, $link);
            echo '<a href="' . $link . '" onclick="return confirm(\'Czy na pewno usunąć wybrany rekord? \');" class="btn btn-danger btn-sm btn-block">Usuń</a>';

            $lFileName='assets/uploads/eurofast/eurofast_orders/'.$row['id'].'/Order-'.$row['id'].'-'.strtotime($row['add_time']).'.pdf';
            if ($lFileName!='' && is_file($lFileName))
            {
                echo '<a href="' . $lFileName . '" class="btn btn-info btn-sm btn-block">Pobierz zamówienie</a>';
            }
    }

    public function action_edit()
    {
        $id = (int)$this->request->param('cmd');
        if ($id<=0)
        {
            header('Location: /admin/eurofast/orders');
            exit;
        }
        $f = sql_row('SELECT id, nstatus,nnodoc,idspedycja,idclo,idbase,idorder FROM '.$this->table.' WHERE id=:id', array(':id' => $id));
        if (!$f) die('Brak zamówienia!!');
        $products = sql_rows('SELECT * FROM '.$this->table.'_pr WHERE iddoc=:id', array(':id' => $id));

        $this->view->path[] = array('caption' => 'Zamówienie '.$f['nnodoc'], 'url' => $this->link . '/edit/'.$id);
        $this->view->title = 'Zamówienie '.$f['nnodoc'];

        $system = Model_BSX_System::init();

        $fields = array('pname', 'pstreet', 'pcountry', 'ppostcode', 'pcity', 'pnip', 'pemail', 'pphone1','nstatus','ndate_zal','ndistance_plus',
            'narc','idbase','idclo','idspedycja','trname','trnip','trcode','traddress','tname','tdowod','trej','tnaczepa','tphone1','ndate_term');
        $fields2=array('ndate_term','nstatus');

        $save = (getGetPost('save') != '');

        //istniejące
        if ($save) {
                $beforeF=$f;
                $dane = array();
                foreach ($_POST as $name => $value) if (in_array($name, $fields)) $dane[$name] = $value;
                sql_update($this->table, $dane, $id);


                $f = sql_row('SELECT * FROM '.$this->table.' WHERE id=:id', array(':id' => $id));
                $up = array('nstotal_n' => 0, 'nstotal_b' => 0, 'nstotal_v' => 0);
                $corrected=false;
                foreach ($products as $product) {
                    if (isset($_POST['pquantity' . $product['id']])) {
                        $product['pquantity'] = BinUtils::correctPrice(getPost('pquantity' . $product['id']));
                    }
                    if (isset($_POST['rquantity' . $product['id']])) {
                        $product['rquantity'] = BinUtils::correctPrice(getPost('rquantity' . $product['id']));
                    }
                    if (isset($_POST['kquantity' . $product['id']])) {
                        $product['kquantity'] = BinUtils::correctPrice(getPost('kquantity' . $product['id']));
                    }
                    if (isset($_POST['ptemperatura' . $product['id']])) {
                        $product['ptemperatura'] = BinUtils::correctPrice(getPost('ptemperatura' . $product['id']));
                    }
                    if (isset($_POST['pgestosc' . $product['id']])) {
                        $product['pgestosc'] = BinUtils::correctPrice(getPost('pgestosc' . $product['id']));
                    }

                    if (isset($_POST['psprice_n' . $product['id']])) {
                        $product['psprice_n'] = BinUtils::correctPrice(getPost('psprice_n' . $product['id']));
                    }

                    $q=$product['pquantity'];
                    if ($product['rquantity']>0) $q=$product['rquantity'];

                    $stawka = 1 + ($product['psrate_v'] / 100);

                    $product['psprice_b'] = $product['psprice_n'] * $stawka;
                    $product['psprice_v'] = $product['psprice_b'] - $product['psprice_n'];

                    $product['pstotal_n'] = $product['psprice_n'] * $q;
                    $product['pstotal_b'] = $product['psprice_n'] * $stawka * $q;
                    $product['pstotal_v'] = $product['pstotal_b'] - $product['pstotal_n'];

                    $up['nstotal_n'] += $product['pstotal_n'];
                    $up['nstotal_v'] += $product['pstotal_v'];
                    $up['nstotal_b'] += $product['pstotal_b'];

                    sql_update($this->table.'_pr', $product, $product['id']);
                }
                if ($beforeF['idspedycja']<=0 && $f['idspedycja']>0)
                {
                    $m=sql_row('SELECT * FROM bs_users WHERE id=:id',array(':id'=>$f['idspedycja']));
                    if ($m)
                    {
                        $up['trname']=$m['pname'];
                        $up['trcode']=$m['pcode'];
                        $up['trnip']=$m['pnip'];
                        $up['traddress']=$m['ppostcode'].' '.$m['pcity'].', '.$m['pstreet'].', '.$m['pcountry'];
                    }
                }

                if ($beforeF['idclo']!=$f['idclo'] && $f['nstatus']>=4)
                {
                    $up['nstatus']=3;
                    $up['corrected']=date('Y-m-d H:i:s');
                    $up['corrected_type']=3;
                    $corrected=true;
                    sql_query('UPDATE eurofast_orders SET nstatus=3 WHERE id=:id',array(':id'=>$f['idorder']));
                }
                if ($beforeF['idbase']!=$f['idbase'] && $f['nstatus']>=4)
                {
                    $up['nstatus']=3;
                    $up['corrected']=date('Y-m-d H:i:s');
                    $up['corrected_type']=4;
                    $corrected=true;
                    sql_query('UPDATE eurofast_orders SET nstatus=3 WHERE id=:id',array(':id'=>$f['idorder']));
                }
                sql_update($this->table, $up, $id);

                if ($corrected) {
                    Model_Eurofast_Core::changeDistance($id);
                }

                $this->messageStr = 'Zamówienie zostało zaktualizowane!';

                //generujemy zamówienia za każdym razem (admin może je zmieniać)
                Model_Eurofast_Core::generateOrderPDF($id); //dla klienta
                if ($f['idorder']>0)
                {
                    $d=array();
                    foreach ($_POST as $name => $value) if (in_array($name, $fields2)) $d[$name] = $value;
                    sql_update($this->table, $d, $f['idorder']);
                    Model_Eurofast_Core::generateOrderPDF($f['idorder']);
                }

                Model_Eurofast_Core::updateAttachmentsForOrder($id);


                if (getPost('nstatus') == '3' && $beforeF['nstatus']!=3) {
                    //realizacja zamówienia
                    $err = '';

                    if ($err != '') {
                        $this->messageStr = $err;
                        $this->messageClass = 'alert-danger';
                    } else {
                        $this->messageStr = 'Zamówienie zostało przygotowanie do realizacji!';

                        Model_Eurofast_Core::setStatus($id,3);
                    }
                } else if (getPost('nstatus') == '4' && $beforeF['nstatus']!=4) {
                        //realizacja zamówienia
                        $err = '';

                        if ($err != '') {
                            $this->messageStr = $err;
                            $this->messageClass = 'alert-danger';
                        } else {
                            $this->messageStr = 'Zamówienie zostało przygotowane do realizacji!';

                            Model_Eurofast_Core::setStatus($id,4);
                        }
                } else if (getPost('nstatus') == '5' && $beforeF['nstatus']!=5) {
                    //realizacja zamówienia
                    $err = '';

                    if ($err != '') {
                        $this->messageStr = $err;
                        $this->messageClass = 'alert-danger';
                    } else {
                        $this->messageStr = 'Zamówienie zostało zrealizowane!';

                        Model_Eurofast_Core::setStatus($id,5);
                    }
                }
        }

        $products = sql_rows('SELECT * FROM '.$this->table.'_pr WHERE iddoc=:id', array(':id' => $id));
        $f = sql_row('SELECT * FROM '.$this->table.' WHERE id=:id', array(':id' => $id));



        $view = Model_BSX_Core::create_view($this, 'Eurofast/part_form_admin_order_edit', 'padmin');
        $view->set('f', $f);
        $view->set('r', $products);
        $view->set('id', $id);

        $g=new Model_BSX_Attachments($this->table,$id,'attachments_'.$this->table,$this,Model_BSX_Core::create_view($this,'Eurofast/part_attachments_std','padmin'),'Eurofast/part_attachments_std_item','padmin');
        $g->type=1;
        $g->where='idorder='.$id;
        $g->url=$this->link.'edit/'.$id;
        $g->assetsURL='/assets/admin_cms/';
        $g->assetFolder='uploads'.DIRECTORY_SEPARATOR.'eurofast';
        $g->addDataArray=array('idorder'=>$id);
        $view->set('attachments',$g->render());

        echo $view;

    }

    public function action_delete()
    {
        $id = (int)$this->request->param('cmd');
        sql_query('DELETE FROM eurofast_orders WHERE id=:id', array(':id' => $id));
        sql_query('DELETE FROM eurofast_orders_pr WHERE iddoc=:id', array(':id' => $id));
        return $this->action_orders();
    }

    public function action_show()
    {
        $id = (int)$this->request->param('cmd');
        $f = sql_row('SELECT * FROM eurofast_orders WHERE id=:id', array(':id' => $id));
        if (!$f) die('Brak uprawnień!');

        $products = sql_rows('SELECT * FROM eurofast_orders_pr WHERE iddoc=:id', array(':id' => $id));

        $this->view->path[] = array('caption' => 'Zamówienie', 'url' => '/admin/eurofast/orders/show/'.$id);
        $this->view->title = 'Zamówienie';

        $view = Model_BSX_Core::create_view($this, 'Eurofast/part_form_admin_order', 'padmin');
        $view->set('f', $f);
        $view->set('r', $products);
        $view->set('id', $id);
        echo $view;
    }

}