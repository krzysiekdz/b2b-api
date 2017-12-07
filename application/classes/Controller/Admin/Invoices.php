<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  Zarządzanie fakturami
************************************************************************************/

class Controller_Admin_Invoices extends Controller {
    private $view;

    public function before() {
        if (!Model_BSX_Core::testPermission('admin')) return;
        if (!Model_BSX_Admin::test_login($this)) return;
        parent::before();

        $this->view=Model_BSX_Core::create_view($this,'index_standard','padmin');

        $this->root=Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/';
        $this->link=$this->root.'invoices';
        $this->view->sidebar_active='modules';
        $this->view->sidebar_active_menu='invoices';
        $this->table='bs_invoices';
        $this->statusList=array(
            0=>'W trakcie edycji',
            1=>'Do akceptacji',
            2=>'Zatwierdzone',
        );
        $this->view->path=array(
            array('caption'=>'Home','url'=>$this->root),
            array('caption'=>'Sprzedaż','url'=>$this->root.'invoices'),
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
        return $this->action_sell();
    }

    public function action_sell()
    {
        return $this->action_sellbuy('sell');
    }

    public function action_buy()
    {
        return $this->action_sellbuy('buy');
    }

    public function action_proforma()
    {
        return $this->action_sellbuy('proforma');
    }

    public function action_sellbuy($type)
    {
        $t=new Model_BSX_Table($this->table,'tbl',$this);
        $t->tableView=Model_BSX_Core::create_view($this,'part_table_std','padmin');
        $t->showView=Model_BSX_Core::create_view($this,'part_table_show_std','padmin');
        if ($type=='sell') {
            $this->view->path[] = array('caption' => 'Dokumenty sprzedaży', 'url' => $this->link . '/sell');
            $this->view->title = 'Dokumenty sprzedaży';
            $t->formURL=$this->link.'/sell';
            $t->tableView->title='Lista dokumentów sprzedaży';
            $t->where='ntype=0 AND nsubtype>0 AND nstatus<>10';
        } else if ($type=='buy') {
            $this->view->path[] = array('caption' => 'Dokumenty zakupu', 'url' => $this->link . '/buy');
            $this->view->title = 'Dokumenty zakupu';
            $t->formURL=$this->link.'/buy';
            $t->tableView->title='Lista dokumentów zakupu';
            $t->where='ntype=1 AND nstatus<>10';
        } else if ($type=='proforma') {
            $this->view->path[] = array('caption' => 'Dokumenty sprzedaży', 'url' => $this->link . '/sell');
            $this->view->title = 'Dokumenty proforma';
            $t->formURL = $this->link . '/proforma';
            $t->tableView->title = 'Lista dokumentów proforma';
            $t->where = 'ntype=0 AND nsubtype=0 AND nstatus<>10';
        }

        $t->showPagesExacly=true;
        $t->showSearchPanel=true;
        $t->showEditBtn=false;
        $t->showDelBtn=true;
        $t->showShowBtn=true;

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
            'title'=>'Dokument',
            'subtitle'=>'Faktura nr {nnodoc}',
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
                            array('name'=>'ndate_sell','caption'=>'Data sprzedaży', 'type'=>'datecheck'),
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

        if ($type=='sell') {
            $t->options['showViewData']['title']='Dokument sprzedaży';
            switch ($t->row['nsubtype'])
            {
                case 0: $t->options['showViewData']['subtitle']='Proforma nr {nnodoc}';break;
                case 1: $t->options['showViewData']['subtitle']='Faktura nr {nnodoc}';break;
                case 3: $t->options['showViewData']['subtitle']='Faktura eksportowa nr {nnodoc}';break;
                case 4: $t->options['showViewData']['subtitle']='Faktura marża nr {nnodoc}';break;
                case 5: $t->options['showViewData']['subtitle']='Faktura zaliczka nr {nnodoc}';break;
                case 6: $t->options['showViewData']['subtitle']='Faktura WDT nr {nnodoc}';break;
                case 7: $t->options['showViewData']['subtitle']='Paragon nr {nnodoc}';break;
                case 8: $t->options['showViewData']['subtitle']='Rachunek mr {nnodoc}';break;
                case 2: $t->options['showViewData']['subtitle']='Nota wewnętrzna mr {nnodoc}';break;
                case 9: $t->options['showViewData']['subtitle']='Nota księgowa mr {nnodoc}';break;
            }
        } else if ($type=='buy') {
            $t->options['showViewData']['title']='Dokument zakupu';
            switch ($t->row['nsubtype'])
            {
                case 0: $t->options['showViewData']['subtitle']='Faktura zakupowa {nnodoc}';break;
                case 1: $t->options['showViewData']['subtitle']='Faktura RR {nnodoc}';break;
                case 3: $t->options['showViewData']['subtitle']='Dowód zakupu rzeczy używanych nr {nnodoc}';break;
            }
        } else if ($type=='proforma') {
            $t->options['showViewData']['title']='Dokument sprzedaży';
            $t->options['showViewData']['subtitle']='Proforma nr {nnodoc}';
        }
        echo $t->render();

    }



}