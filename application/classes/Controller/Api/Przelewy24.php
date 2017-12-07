<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Api_Przelewy24 extends Controller {

    public function before() {
        parent::before();
    }

    public function after() {
        parent::after();
    }


    //--- strona główna serwisu WWW ---
    public function action_index()
    {
        //foreach ($_GET as $n=>$v) $_POST[$n]=$v;
        $d='';
        foreach ($_POST as $n=>$v) $d.="$n=$v\n";
        file_put_contents('11.txt',$d);

        if (empty($_POST['p24_merchant_id']) || empty($_POST['p24_pos_id']) || empty($_POST['p24_session_id']) || empty($_POST['p24_amount']) ) {
            echo 'FAIL!';
            exit;
        }

        $pay=new Model_BSX_Payments();
        if (!empty(Model_BSX_Core::$bsx_cfg['model'])&&is_callable(array(Model_BSX_Core::$bsx_cfg['model'], 'getSuppliers'),true))
        {
            $pay->suppliers=forward_static_call(array(Model_BSX_Core::$bsx_cfg['model'], 'getSuppliers'));
        }

        include_once(APPPATH.'vendor/class_przelewy24.php');
        $P24 = new Przelewy24($pay->suppliers['przelewy24']['id'], $pay->suppliers['przelewy24']['id'], $pay->suppliers['przelewy24']['key'], false);
        $P24->addValue('p24_session_id',$_POST['p24_session_id']);
        $P24->addValue('p24_amount',$_POST['p24_amount']);
        $P24->addValue('p24_order_id',$_POST['p24_order_id']);
        $P24->addValue('p24_currency',$_POST['p24_currency']);
        $res = $P24->trnVerify();
        if(isset($res["error"]) and $res["error"] === '0')
        {
            $msg = 'Transakcja została zweryfikowana poprawnie';
        }
        else{
            $msg = 'Błędna weryfikacja transakcji';
        }
        echo $msg;

        //najważniejszy wpis w logach
        $log=array();
        $log['add_time']=date('Y-m-d H:i:s');
        $log['fid']=(int)getPost('p24_merchant_id',0); //identyfikator  użytkownika DotPay
        $log['fstatus_s']='OK'; //status OK, FAIL
        $log['fcontrol']=(int)getPost('p24_session_id',0);
        $log['ftid']=getPost('p24_order_id',''); //identyfikator transakcji DotPay
        $log['famount']=((double)getPost('p24_amount',0))/100; //kwota
        $log['fstatus']=2;//status transakcji:1-nowa, 2-wykonana, 3-odmowa, 4-anulowana, 5-reklamacja
        $log['fdesc']=getPost('p24_statement','');//opis
        $log['id']=sql_insert('bsc_log_dotpay',$log);


        //stworzenie płatności on-line
        $pl=array();
        $pl['add_time']=date('Y-m-d H:i:s');
        $pl['fsettled']=0;
        $pl['ftype']=1;
        $pl['fido']=$log['fcontrol'];
        $pl['famount']=$log['famount'];
        $pl['femail']='';
        $pl['fdesc']=$log['fdesc'];
        $pl['fnodoc']=$log['ftid'];
        $pl['riddotpay']=$log['id'];
        $pl['fstatus']=$log['fstatus'];
        $pl['id']=sql_insert('bs_pay_online',$pl);

        //podpięcie płatności pod odpowiednie zamówienie
        $r=sql_row('SELECT * FROM bs_orders WHERE id=:id',array(':id'=>$pl['fido']));
        if ($r && $log['fstatus']==2) {
            $s='Przelewy24:'.$pl['fnodoc'].':'.$pl['fdesc'];

            $pl2=array();
            $pl2['add_time']=date('Y-m-d H:i:s');
            $pl2['modyf_time']=date('Y-m-d H:i:s');
            $pl2['add_id_user']=0;
            $pl2['modyf_id_user']=0;
            $pl2['pidz']=$log['fcontrol'];
            $pl2['pidf']=0;
            $pl2['pidc']=$r['pidcontractor'];
            $pl2['pdate']=$r['add_time'];
            $pl2['pprice_b']=$log['famount'];
            $pl2['rdesc']=$s;
            $pl2['ridkp']=0;
            $pl2['ridwb']=0;
            $pl2['ridon']=$pl['id'];
            sql_insert('bs_payments',$pl2);

            sql_query('UPDATE bs_orders SET nprice=:data WHERE id=:id',array(':data'=>$pl2['pdate'],':id'=>$r['id']));
            sql_query('UPDATE bs_orders SET nstatus=2 WHERE id=:id AND nstatus<2',array(':id'=>$r['id']));
            sql_query('UPDATE bs_pay_online SET fsettled=1 WHERE id=:id',array(':id'=>$pl['id']));
        }


        echo "OK";
        return;

    }

}