<?php


class Model_BSX_Order {
    public static $status=array(0=>'W trakcie edycji',1=>'W trakcie realizacji',2=>'Częściowo zrealizowane',3=>'Zrealizowane',4=>'Anulowane');
    public $order=array();
    public $items=array();
    private $system=null;
    public $autoAddContractor=true;

    public function __construct()
    {
        $this->system=Model_BSX_System::init();


        $this->order['add_id_user']=$this->system->user['id'];
        $this->order['add_time']=date('Y-m-d H:i:s');
        $this->order['modyf_id_user']=$this->order['add_id_user'];
        $this->order['modyf_time']=$this->order['add_time'];
        $this->order['idcompany']=$this->system->company['id'];
        $this->order['idbranch']=$this->system->branche['id'];
        $this->order['idowner']=$this->system->user['id'];
        $this->order['nstatus']=1;  //w trakcie realizacji
        $this->order['ntype']=0; //zamówienie przychodzące

        $this->order['cms_idsite']=Model_BSX_Core::$bsx_cfg['id'];

        $nodoc=$this->system->getNoDoc('orders_in#ntype=0');

        $this->order['nnodoc']=$nodoc['nnodoc'];
        $this->order['nvnodoc']=$nodoc['nvnodoc'];
        $this->order['idnodoc']=$nodoc['idnodoc'];

        $this->order['md5']=md5(time().'!'.$this->order['nvnodoc']);


        $this->order['npaymentform']='Płatność on-line';
        $this->order['npaymentdate']='7 dni';
        $this->order['ndpaymentdate']=date('Y-m-d',time()+7*24*3600);
        $this->order['ndeliverytype']='';
        $this->order['nperson1']=$this->system->user['username'];
        $this->order['nnote']='';
        $this->order['ncalculation']=$this->system->getCompanyConfig('dcalculationsell','1');
        $this->order['ncurrency']=Model_BSX_Shop::$currency;
        $this->order['ncurrencyrate']='1.0';
        if (isset($this->system->company['bank'])) $this->order['nbank']=$this->system->company['bank'];
        $this->order['nstotal_n']=0;
        $this->order['nstotal_v']=0;
        $this->order['nstotal_b']=0;
        $this->order['ndate_issue']=date('Y-m-d');

        $this->order['pidcontractor']=0;
        $this->order['psymbol']='';
        $this->order['pname']='';
        $this->order['pstreet']='';
        $this->order['pstreet_n1']='';
        $this->order['ppostcode']='';
        $this->order['ppost']='';
        $this->order['pcity']='';
        $this->order['pprovince']='';
        $this->order['pdistrict']='';
        $this->order['pcountry']='';
        $this->order['pemail']='';
        $this->order['pnip']='';
        $this->order['pphone1']='';
        $this->order['kname']='';
        $this->order['kstreet']='';
        $this->order['kstreet_n1']='';
        $this->order['kpostcode']='';
        $this->order['kpost']='';
        $this->order['kcity']='';
        $this->order['kprovince']='';
        $this->order['kdistrict']='';
        $this->order['kcountry']='';
        $this->order['sname']=$this->system->company['pname'];
        $this->order['sstreet']=$this->system->company['pstreet'];
        $this->order['sstreet_n1']=$this->system->company['pstreet_n1'];
        $this->order['spostcode']=$this->system->company['ppostcode'];
        $this->order['spost']=$this->system->company['ppost'];
        $this->order['scity']=$this->system->company['pcity'];
        $this->order['sprovince']=$this->system->company['pprovince'];
        $this->order['sdistrict']=$this->system->company['pdistrict'];
        $this->order['scountry']=$this->system->company['pcountry'];
        $this->order['semail']=$this->system->company['pemail'];
        $this->order['snip']=$this->system->company['pnip'];
        $this->order['sphone1']=$this->system->company['pphone1'];
    }

    public function getOrderArr() {
        return $this->order;
    }

    public function getItemsArr() {
        return $this->items;
    }

    public function setBuyer($arr) {
        foreach ($arr as $a=>$b) $this->order[$a]=$b;
    }

    public function getMD5() {
        return $this->order['md5'];
    }

    public function getEmail() {
        return $this->order['pemail'];
    }

    public function getOrderNumber() {
        return $this->order['nnodoc'];
    }

    public function importProducts($cart) {
        if ($cart->delivery['pname']!='') $this->order['ndeliverytype']=$cart->delivery['pname'];
        if ($cart->payment['pname']!='') $this->order['npaymentform']=$cart->payment['pname'];
        foreach ($cart->items() as $item)
        {
            if (!empty($item['pattributes_string'])) $item['pname'].=' ('.$item['pattributes_string'].')';
            $this->addItem($item);
        }
    }


    public function addItem($arr) {
        $item=array();
        $item['add_id_user']=$this->order['add_id_user'];
        $item['add_time']=date('Y-m-d H:i:s');
        $item['modyf_id_user']=$item['add_id_user'];
        $item['modyf_time']=$item['add_time'];
        $item['idcompany']=$this->order['idcompany'];
        $item['idbranch']=$this->order['idbranch'];
        $item['idowner']=$this->order['idowner'];
        $item['iddoc']=0;
        $item['fselcolor']=0;
        $item['fsingroup']=0;
        $item['ntype']=$this->order['ntype'];
        $item['pscalculation']=$this->order['ncalculation'];
        $item['idproduct']=$arr['id'];
        $item['ptype']=0;
        $item['psymbol']='';
        $item['pname']='';
        $item['pquantity']=1;
        $item['ppkwiu']='';
        $item['punit']='szt.';
        $item['psprice_n']=0;
        $item['psprice_v']=0;
        $item['psprice_b']=0;
        $item['psrate_v']='';
        $item['pstotal_n']=0;
        $item['pstotal_v']=0;
        $item['pstotal_b']=0;
        $item['psdiscount']=0;
        $item['pnrcat']='';
        $item['pother1']='';
        $item['nnote']='';
        foreach ($arr as $a=>$b) if (isset($item[$a])) $item[$a]=$b;
        if ($item['psrate_v']==='') $item['psrate_v']=23;
        $item['pname']=strip_tags($item['pname']);
        $stawka=(int)$item['psrate_v'];
        $nstawka=1+($stawka/100);
        $metoda=(int)$item['pscalculation'];
        if ($metoda==0) { //od netto
            $netto=(double)$item['psprice_n'];
            $sztuk=(double)$item['pquantity'];
            $item['psprice_b']=round($netto*($nstawka),2);
            $item['psprice_v']=round($item['psprice_b']-$netto,2);
            $item['pstotal_n']=round($netto*$sztuk,2);
            $item['pstotal_b']=round($netto*($nstawka)*$sztuk,2);
            $item['pstotal_v']=round($item['pstotal_b']-$item['pstotal_n'],2);
        } else { //od brutto
            $brutto=(double)$item['psprice_b'];
            $sztuk=(double)$item['pquantity'];
            $item['psprice_n']=round($brutto/($nstawka),2);
            $item['psprice_v']=round($brutto-(double)$item['psprice_n'],2);
            $item['pstotal_n']=round(($brutto/($nstawka))*$sztuk,2);
            $item['pstotal_b']=round($brutto*$sztuk,2);
            $item['pstotal_v']=round($item['pstotal_b']-$item['pstotal_n'],2);
        }
        $this->items[]=$item;
        $this->resum();
    }

    public function resum() {
        $_Vat=0;
        $_Netto=0;
        $_Brutto=0;
        $_Cnt=0;
        $iStawek=0;
        $Stawki=array();
        foreach ($this->items as $item)
        {
            $_N=$item['pstotal_n'];
            $_B=$item['pstotal_b'];
            $_V=$item['pstotal_v'];


            $S=$item['psrate_v'];
            $j=-1;
            for ($k=0;$k<$iStawek;$k++) if ($Stawki[$k]['rate']==$S) {$j=$k; break; }
            if ($j==-1)
            {
                $Stawki[$iStawek]['rate']=$S;
                $Stawki[$iStawek]['netto']=$_N;
                $Stawki[$iStawek]['vat']=$_V;
                $Stawki[$iStawek]['brutto']=$_B;
                $iStawek++;
            } else
            {
                $Stawki[$j]['netto']+=$_N;
                $Stawki[$j]['vat']+=$_V;
                $Stawki[$j]['brutto']+=$_B;
            }

            $_Cnt++;
        }
        //Mamy tabelkę podsumowującą VAT-y, to z niej wyliczamy podsumowanie całego dokumentu
        //ale aby tego dokonać, musi być ona poprawna. Zwykła suma po pozycjach nie jest poprawna.
        //W metodzie liczenia od NETTO do tabelki robimy zwykłą sumę ale tylko kolumn NETTO, a pozostałe obliczamy.
        //Analogicznie w metodzie od BRUTTO
        //metoda obliczeń na dokumencie
        for ($j=0;$j<$iStawek;$j++)
        {
            $_S=1+($Stawki[$j]['rate']/100);
            if ($this->order['ncalculation']==0)
            { //netto
                $_N=$Stawki[$j]['netto'];
                $_B=($_N*$_S);
                $_V=$_B-$_N;
                $Stawki[$j]['vat']=$_V;
                $Stawki[$j]['brutto']=$_B;
            } else
            { //brutto
                $_B=$Stawki[$j]['brutto'];
                $_N=($_B/$_S);
                $_V=$_B-$_N;
                $Stawki[$j]['vat']=$_V;
                $Stawki[$j]['netto']=$_N;
            }
            $_Netto+=$Stawki[$j]['netto'];
            $_Vat+=$Stawki[$j]['vat'];
            $_Brutto+=$Stawki[$j]['brutto'];
        }
        $this->order['nstotal_n']=round($_Netto,2);
        $this->order['nstotal_v']=round($_Vat,2);
        $this->order['nstotal_b']=round($_Brutto,2);
    }

    public function analyse() {
        $this->resum();
        if ($this->order['pcountry']=='') $this->order['pcountry']='Polska';
        if ($this->order['ppostcode']!='' && $this->order['ppost']=='' && sql_tableexists('bsx_postcode'))
        {
            $row = sql_row('SELECT * FROM bsx_postcode WHERE ppostcode=:ppostcode',array(':ppostcode'=>$this->order['ppostcode']));
            if ($row) {
                $this->order['ppost']=$row['pcity'];
                $this->order['pprovince']=$row['pprovince'];
                $this->order['pdistrict']=$row['pdistrict'];
            }

        }

    }
    public function execute() {
        $system=Model_BSX_System::init();

        $this->order['pnip']=BinUtils::cleanField($this->order['pnip'],'nip');
        $this->order['pphone1']=BinUtils::cleanField($this->order['pphone1'],'phone');

        $w=false;
        if (!empty($this->order['pnip'])) $w=sql_row('SELECT id FROM bs_contractors WHERE pnip=:nip',array(':nip'=>$this->order['pnip']));
        if (!empty($this->order['pemail']) && !$w) $w=sql_row('SELECT id FROM bs_contractors WHERE pemail=:email',array(':email'=>$this->order['pemail']));
        if ($w) $this->order['pidcontractor']=$w['id'];
        if ($this->autoAddContractor && $this->order['pidcontractor']<=0)
        {
            $c=new Model_BSX_Contractor();
            $c->importDataFromOrder($this);
            $this->order['pidcontractor']=$c->execute();
        }

        $this->order['id']=sql_insert('bs_orders',$this->order);
        foreach ($this->items as $item)
        {
            $item['iddoc']=$this->order['id'];
            sql_insert('bs_orders_pr',$item);
        }
        $this->system->getNoDoc('orders_in#ntype=0',true);

    }


}