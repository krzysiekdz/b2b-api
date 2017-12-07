<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  Zarządzanie zamówieniami
************************************************************************************/

class Controller_Admin_Orders extends Controller {
    private $view;

    public function before() {
        if (!Model_BSX_Core::testPermission('admin')) return;
        if (!Model_BSX_Admin::test_login($this)) return;
        parent::before();

        $this->view=Model_BSX_Core::create_view($this,'index_standard','padmin');

        $this->root=Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/';
        $this->link=$this->root.'orders';
        $this->view->sidebar_active='modules';
        $this->view->sidebar_active_menu='orders';
        $this->table='bs_orders';
        $this->statusList=array(
            0=>'W trakcie edycji',
            1=>'W trakcie realizacji',
            11=>'{#class=label-info#}Oczekiwanie na dostawę',
            12=>'{#class=label-info#}W trakcie kompletowania',
            13=>'{#class=label-info#}Oczekiwanie na płatność',
            14=>'{#class=label-info#}Gotowe do wysłania',
            2=>'{#class=label-info#}Częściowo zrealizowane',
            3=>'Zrealizowane',
            4=>'{#background=gray#}Anulowane',
            41=>'{#background=gray#}Odrzucone',
            42=>'{#background=gray#}Zwrócone',
            43=>'{#background=gray#}Reklamowane',
            );

        $this->view->path=array(
            array('caption'=>'Home','url'=>$this->root),
            array('caption'=>'Zamówienia','url'=>$this->root.'orders'),
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
        return $this->action_in(0);
    }

    public function action_in()
    {
        return $this->action_inout(0);
    }

    public function action_out()
    {
        return $this->action_inout(1);
    }
    public function action_inout($type)
    {
        $t=new Model_BSX_Table($this->table,'tbl',$this);

        if ($type==0) {
            $this->view->path[] = array('caption' => 'Przychodzące', 'url' => $this->link . '/in');
            $this->view->title = 'Zamówienia przychodzące';
            $t->formURL=$this->link.'/in';
        } else {
            $this->view->path[] = array('caption' => 'Wychodzące', 'url' => $this->link . '/out');
            $this->view->title = 'Zamówienia wchodzące';
            $t->formURL=$this->link.'/out';
        }

        $t->tableView=Model_BSX_Core::create_view($this,'part_table_std','padmin');
        $t->showView=Model_BSX_Core::create_view($this,'part_table_show_std','padmin');
        $t->tableView->title='Lista zamówień';
        $t->showPagesExacly=true;
        $t->showSearchPanel=true;
        $t->showEditBtn=false;
        $t->showDelBtn=true;
        $t->showShowBtn=true;
        $t->where='ntype='.$type;
        //$t->addField('id','ID','center','int','60px');
        $t->addField('nnodoc','Numer','center','varchar','');
        $t->addField('ndate_issue','Data wystawienia','center','date','');
        $t->addField('nstatus','Status','center','select','150px',$this->statusList);
        $t->addField('pname','Kontrahent','left','varchar','',null,'<b>{pname}</b>|{pstreet} {pstreet_n1}, {ppostcode} {ppost}');
        $t->addField('nstotal_n','Wartości','right','price','',null,'{nstotal_n|price} {ncurrency}|{nstotal_b|price} {ncurrency}|{nstotal_v|price} {ncurrency}');
        $t->addField('nprice','Opłacono','center','date','');

        if ($t->cmd=='show')
        {
            $t2=new Model_BSX_Table($this->table.'_pr','tbl2',$this);
            $t2->tableView=Model_BSX_Core::create_view($this,'part_table_std','padmin');
            $t2->tableView->title='Lista produktów';
            $t2->showPagesExacly=true;
            $t2->showSearchPanel=false;
            $t2->showEditBtn=false;
            $t2->showDelBtn=false;
            $t2->showShowBtn=false;
            $t2->where='iddoc='.$t->row['id'];
            $t2->options['rootClass']='box red';
            $t2->options['searchItem']=false;
            $t2->options['paginationVisible']=false;
            $t2->addField('pname','Numer','left','varchar','');
            $t2->addField('psprice_n','Cena netto','right','price','');
            $t2->addField('psprice_b','Cena brutto','right','price','');
            $t2->addField('pquantity','Ilość','right','quantity','');
            $t2->addField('pstotal_n','Wartość netto','right','price','');
            $t2->addField('pstotal_v','Wartość vat','right','price','');
            $t2->addField('pstotal_b','Wartość brutto','right','price','');
            $t->row['%tbl2']=$t2->render();

        }

        $t->options['showViewData']=array(
            'title'=>'Zamówienie',
            'subtitle'=>'Zamówienie nr {nnodoc}',
            'groups'=>array(
                array(
                    'title'=>'Informacje',
                    'rows'=>array(
                        array(
                            array('name'=>'nnodoc','caption'=>'Numer dokumentu'),
                            array('name'=>'nstatus','caption'=>'Status','type'=>'select','data'=>$this->statusList),
                        ),
                        array(
                            array('name'=>'ndate_issue','caption'=>'Data wystawienia'),
                            array('name'=>'npaymentform','caption'=>'Forma płatności'),
                        ),
                        array(
                            array('name'=>'ndate_final','caption'=>'Data realizacji', 'type'=>'datecheck'),
                            array('name'=>'npaymentdate','caption'=>'Termin płatność'),
                        ),
                        array(
                            array('name'=>'ncurrency','caption'=>'Waluta'),
                            array('name'=>'nprice','caption'=>'Status płatności', 'type'=>'datecheck'),
                        ),
                    ),
                ),
                array(
                    'title'=>'Zamawiający',
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
                array(
                    'title'=>'Produkty',
                    'rows'=>array(
                      array(
                          'options'=>array('class'=>'col-md-4'),
                          array('name'=>'nstotal_n','caption'=>'Wartość Netto', 'type'=>'price','sufix'=>' {ncurrency}'),
                          array('name'=>'nstotal_v','caption'=>'Wartość Vat', 'type'=>'price','sufix'=>' {ncurrency}'),
                          array('name'=>'nstotal_b','caption'=>'Wartość Brutto', 'type'=>'price','sufix'=>' {ncurrency}'),
                      ),
                      array(
                          'options'=>array('class'=>'col-md-12'),
                          array('name'=>'%tbl2'),
                      ),
                    ),
                ),
                array(
                    'title'=>'Inne',
                    'rows'=>array(
                        array(
                            array('name'=>'nnote','caption'=>'Uwagi'),
                        ),
                    ),
                ),
            ),
        );
        echo $t->render();
    }


}