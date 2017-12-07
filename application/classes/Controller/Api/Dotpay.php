<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Api_Dotpay extends Controller {

    public function before() {
        parent::before();
    }

    public function after() {
        parent::after();
    }


    //--- strona główna serwisu WWW ---
    public function action_index()
    {

        /*
          $_POST['control']=23;
          $_POST['t_status']=2;
          $_POST['status']='OK';
          $_POST['amount']=99;
          $_POST['email']='karol@wp.pl';
          $_POST['description']='Za program';
        */
        //najważniejszy wpis w logach
        $log=array();
        $log['add_time']=date('Y-m-d H:i:s');
        $log['fid']=(int)getPost('id',0); //identyfikator  użytkownika DotPay
        $log['fstatus_s']=getPost('status',''); //status OK, FAIL
        $log['fcontrol']=(int)getPost('control',0);
        $log['ftid']=getPost('t_id',''); //identyfikator transakcji DotPay
        $log['famount']=(double)getPost('amount',0); //kwota
        $log['fchannel']=(int)getPost('channel',0);//kanał
        $log['femail']=getPost('email','');//email
        $log['fstatus']=(int)getPost('t_status',0);//status transakcji:1-nowa, 2-wykonana, 3-odmowa, 4-anulowana, 5-reklamacja
        $log['fdesc']=getPost('description','');//opis
        $log['id']=sql_insert('bsc_log_dotpay',$log);


        //stworzenie płatności on-line
        $pl=array();
        $pl['add_time']=date('Y-m-d H:i:s');
        $pl['fsettled']=0;
        $pl['ftype']=1;
        $pl['fido']=$log['fcontrol'];
        $pl['famount']=$log['famount'];
        $pl['femail']=$log['femail'];
        $pl['fdesc']=$log['fdesc'];
        $pl['fnodoc']=$log['ftid'];
        $pl['riddotpay']=$log['id'];
        $pl['fstatus']=$log['fstatus'];
        $pl['id']=sql_insert('bs_pay_online',$pl);

        //podpięcie płatności pod odpowiednie zamówienie
        $r=sql_row('SELECT * FROM bs_orders WHERE id=:id',array(':id'=>$pl['fido']));
        if ($r && $log['fstatus']==2) {
            $s='Dotpay:'.$pl['fnodoc'].':'.$pl['fdesc'];

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