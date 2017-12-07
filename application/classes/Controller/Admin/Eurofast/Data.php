<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  Zarządzanie Użytkownikami systemu
************************************************************************************/

class Controller_Admin_Eurofast_Data extends Controller {
    private $view;

    public function before() {
        parent::before();

        $this->view=Model_BSX_Core::create_view($this,'index_standard','padmin');

        $this->root=Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/';
        $this->link=$this->root.'eurofast/data';
        $this->view->sidebar_active='home';
        $this->view->sidebar_active_menu='dane';
        $this->table='eurofast_orlen';


        $this->view->path=array(
            array('caption'=>'Home','url'=>$this->root),
            array('caption'=>'Dane źródłowe','url'=>$this->link),
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
        if (!Model_BSX_Core::testPermission('admin')) return;
        if (!Model_BSX_Admin::test_login($this)) return;

        $this->view->title='Ceny paliw';

        $t=new Model_BSX_Table($this->table,'tbl',$this);
        $t->formURL=$this->link.'/list';
        $t->tableView=Model_BSX_Core::create_view($this,'part_table_std','padmin');
        $t->showView=Model_BSX_Core::create_view($this,'part_table_show_std','padmin');
        $t->tableView->title='Dane źródłowe';
        $t->showPagesExacly=true;
        $t->showSearchPanel=true;
        $t->showEditBtn=false;
        $t->showDelBtn=true;
        $t->showShowBtn=false;
        $t->options['toolbarVisible']=true;
        $t->options['toolButtons']=array(array('caption'=>'Importuj ceny','href'=>'/admin/eurofast/data/import'));

        $t->addField('pdate','Data','center','datetime');
        $t->addField('peurosuper','Eurosuper 95','right','price');
        $t->addField('psuperplus','Super Plus 98','right','price');
        $t->addField('pekodiesel','Ekodiesel','right','price');
        echo $t->render();
    }

    public function action_import()
    {
        $this->orlen_import();
        return $this->action_list();
    }

    public function orlen_import()
    {
        $d=file_get_contents('http://www.orlen.pl/PL/DlaBiznesu/HurtoweCenyPaliw/Strony/default.aspx');
        $x1=strpos($d,'<table class="tableLight" width="100%" cellpadding="0" cellspacing="0">');
        $x2=strpos($d,'</table>',$x1+1);

        if ($x1==false || $x2==false) exit;
        $d=trim(substr($d,$x1+71,$x2-$x1-71));
        $d=str_replace('</td>','|',$d);
        $d=str_replace('</tr>','+',$d);
        $d=str_replace("&nbsp;",' ',$d);
        $d=str_replace("\r",'',$d);
        $d=str_replace("\n",'',$d);
        $d=trim(strip_tags($d));
        while(strpos($d,'  ')!==false) $d=str_replace('  ',' ',$d);
        $d=explode('+',$d);
        $dane=array('pdate'=>date('Y-m-d H:i:s'));
        foreach ($d as $item)
        {
            $w=explode('|',$item);
            if (!isset($w[1])) continue;
            $w[0]=trim($w[0]);
            $w[1]=trim($w[1]);
            $w[0]=iconv("UTF-8", "Windows-1250", $w[0]);
            $w[1]=iconv("UTF-8", "Windows-1250", $w[1]);
            $w[1]=str_replace(',','.',$w[1]);

            $wname=$w[0];
            $wprice=$w[1];

            $wprice=str_replace(chr(160),'',$wprice);
            $wprice=str_replace(chr(179),'',$wprice);
            $wprice=str_replace(' ','',$wprice);
            $k=strpos($wprice,'z');if ($k!==false) $wprice=substr($wprice,0,$k);
            //echo $wname.'='.$wprice.'<br>';

            if (strpos($wname,'Eurosuper')!==false) $wname='peurosuper';
            else if (strpos($wname,'Super Plus')!==false) $wname='psuperplus';
            else if (strpos($wname,'Ekodiesel')!==false) $wname='pekodiesel';
            else if (strpos($wname,'Arktyczny')!==false) $wname='parktyczny';
            else if (strpos($wname,'BIO 100')!==false) $wname='pbio100';
            else if (strpos($wname,'Ekoterm Plus')!==false) $wname='pekoterm';
            else $wname='';

            if ($wname!='' && $wprice>0) $dane[$wname]=$wprice;

        }
        sql_insert($this->table,$dane);
    }

    public function action_cron()
    {
        //CRON ustawić na Godzina:55
        echo 'Cron v1.0 - '.date('Y-m-d H:i:s')."\n";

        //import cennika
        $w=sql_row('SELECT pdate FROM eurofast_orlen ORDER BY pdate DESC');
        if ($w) $t=strtotime($w['pdate']);
        if (!$w || (date('h',$t)>7 &&  date('Y-m-d')!=date('Y-m-d',$t)))
        {
            echo "Import cennika...";
            $this->orlen_import();
            echo "OK!\n";
        }

        Model_Eurofast_Core::createNotifications();


        //akceptacja za resellera - zamówienia na "dzisiaj"
        $rows=sql_rows('SELECT id FROM eurofast_orders WHERE nstatus=1 AND ndate_term=:data',array(':data'=>date('Y-m-d')));
        foreach ($rows as $row)
        {
            echo "Zmieniam status zamówienia $row[id] na ZAAKCEPTOWANY...";
            Model_Eurofast_Core::setStatus($row['id'],2);
            echo "OK!\n";
        }

        //po wypełnieniu przez spedytora danych - zmiana statusu na "do wysłania"
        $rows=sql_rows('SELECT id FROM eurofast_orders WHERE nstatus=2 AND idspedycja>0 AND idkierowca>0 AND idauto>0 AND trname!="" AND ndate_term=:data',array(':data'=>date('Y-m-d')));
        foreach ($rows as $row)
        {
            echo "Zmieniam status zamówienia $row[id] na DO REALIZACJI...";
            Model_Eurofast_Core::setStatus($row['id'],3);
            echo "OK!\n";
        }

        //zmieniamy na zrealizowane
        $rows=sql_rows('SELECT id FROM eurofast_orders WHERE nstatus=4 AND idspedycja>0 AND idkierowca>0 AND idauto>0 AND trname!="" AND narc!=""',array(':data'=>date('Y-m-d')));
        foreach ($rows as $row)
        {
            echo "Zmieniam status zamówienia $row[id] na ZREALIZOWANE...";
            Model_Eurofast_Core::setStatus($row['id'],5);
            echo "OK!\n";
        }

        exit;
    }


}