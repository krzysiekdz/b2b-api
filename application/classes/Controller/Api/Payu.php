<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Api_Payu extends Controller {
    private $payu=array();

    public function before() {
        $this->payu=Model_BSX_Core::$bsx_cfg['payments']['payu'];
        parent::before();
    }

    public function after() {
           parent::after();
    }


    function get_status($parts){
          if ($parts[1] != $this->payu['id']) return array('code' => false,'message' => 'błędny numer POS');  //--- bledny numer POS

          $sig = md5($parts[1].$parts[2].$parts[3].$parts[5].$parts[4].$parts[6].$parts[7].$this->payu['key2']);
          if ($parts[8] != $sig) return array('code' => false,'message' => 'błędny podpis');  //--- bledny podpis
          switch ($parts[5]) {
              case 1: return array('code' => $parts[5], 'message' => 'nowa'); break;
              case 2: return array('code' => $parts[5], 'message' => 'anulowana'); break;
              case 3: return array('code' => $parts[5], 'message' => 'odrzucona'); break;
              case 4: return array('code' => $parts[5], 'message' => 'rozpoczęta'); break;
              case 5: return array('code' => $parts[5], 'message' => 'oczekuje na odbiór'); break;
              case 6: return array('code' => $parts[5], 'message' => 'autoryzacja odmowna'); break;
              case 7: return array('code' => $parts[5], 'message' => 'płatność odrzucona'); break;
              case 99: return array('code' => $parts[5], 'message' => 'płatność odebrana - zakończona'); break;
              case 888: return array('code' => $parts[5], 'message' => 'błędny status'); break;
              default: return array('code' => false, 'message' => 'brak statusu'); break;
      }
    }


    public function action_result()
    {
         echo 'OK';
/*
$_POST['session_id']='3-1394967160';
$_POST['pos_id']=165480;
$_POST['ts']='1394967299058';
$_POST['sig']='a017552c011c1b507c17951cfb115c37';
*/
         $id=getGetPost('session_id');
         $gposid=getGetPost('pos_id');
         if ($id=='') return;
/*
         $plik=fopen('payu.txt','a');
         fputs($plik,"session_id=$id\n");
         fputs($plik,"pos_id=$gposid\n");
         fputs($plik,"ts=".getGetPost('ts')."\n");
         fputs($plik,"sig=".getGetPost('sig')."\n-----\n");
         fclose($plik);*/

         //zmienił się status zamówienia  trzeba sprawdzić

         $server = 'www.platnosci.pl';
         $server_script = '/paygw/UTF/Payment/get';

         $ts = time();
         $sig = md5($this->payu['id'].$id.$ts.$this->payu['key1']);
         $parameters = "pos_id=" . $this->payu['id'] . "&session_id=" . $id . "&ts=" . $ts . "&sig=" . $sig;

         $ch = curl_init();
         curl_setopt($ch, CURLOPT_URL, "https://" . $server . $server_script);
         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
         curl_setopt($ch, CURLOPT_HEADER, 0);
         curl_setopt($ch, CURLOPT_TIMEOUT, 20);
         curl_setopt($ch, CURLOPT_POST, 1);
         curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
         $platnosci_response = curl_exec($ch);
         curl_close($ch);

         $part=array();
         if (preg_match("/<pos_id>([0-9]*)<\/pos_id>/", $platnosci_response, $parts)) $part[1]=strip_tags($parts[0]);
         if (preg_match("/<session_id>(.*)<\/session_id>/", $platnosci_response, $parts)) $part[2]=strip_tags($parts[0]);
         if (preg_match("/<order_id>([0-9]*)<\/order_id>/", $platnosci_response, $parts)) $part[3]=strip_tags($parts[0]);
         if (preg_match("/<amount>([0-9]*)<\/amount>/", $platnosci_response, $parts)) $part[4]=strip_tags($parts[0]);
         if (preg_match("/<status>([0-9]*)<\/status>/", $platnosci_response, $parts)) $part[5]=strip_tags($parts[0]);
         if (preg_match("/<desc>(.*)<\/desc>/", $platnosci_response, $parts)) $part[6]=strip_tags($parts[0]);
         if (preg_match("/<ts>(.*)<\/ts>/", $platnosci_response, $parts)) $part[7]=strip_tags($parts[0]);
         if (preg_match("/<sig>(.*)<\/sig>/", $platnosci_response, $parts)) $part[8]=strip_tags($parts[0]);

         $parts=$part;
         $result = $this->get_status($parts);

         if ( isset($result['code']) &&  $result['code']) {  //--- rozpoznany status transakcji

              $pos_id = $parts[1];
              $session_id = $parts[2];
              $order_id = $parts[3];
              $amount = $parts[4];  //-- w groszach
              $status = $parts[5];
              $desc = $parts[6];
              $ts = $parts[7];
              $sig = $parts[8];

              $order=sql_row('SELECT * FROM bs_orders WHERE id=:id',array(':id'=>$order_id));
              if (!$order) $order=array('pemail'=>'','pidcontractor'=>0,'add_time'=>date('Y-m-d H:i:s'));


              $log=array();
              $log['add_time']=date('Y-m-d H:i:s');
              $log['fposid']=$pos_id;
              $log['fcontrol']=$order_id;
              $log['famount']=$amount/100;
              $log['fstatus_s']=$result['message'];
              $log['fstatus']=$status;
              $log['fdesc']=$desc;
              $log['id']=sql_insert('bsc_log_payu',$log);

              if ($status==1 || $status==4 || $status==5) $status=1;//nowa
              else if ($status==99) $status=2;//wykonana
              else if ($status==3 || $status==6 || $status==7) $status=3;//odmowa
              else if ($status==2) $status=4;//anulowana

              $d=sql_row('SELECT id, fstatus FROM bs_pay_online WHERE fido='.$log['fcontrol']);
              if ($d) $ido=$d['id']; else $ido=0;

              if (!$d || $d['fstatus']!=2)
              {
                  $pl=array();
                  $pl['add_time']=date('Y-m-d H:i:s');
                  $pl['fsettled']=0;
                  $pl['ftype']=3;
                  $pl['fido']=$log['fcontrol'];
                  $pl['famount']=$log['famount'];
                  $pl['femail']=$order['pemail'];
                  $pl['fdesc']=$log['fdesc'];
                  $pl['fnodoc']='';
                  $pl['ridpayu']=$log['id'];
                  $pl['fstatus']=$status;
                  if ($ido<=0) $pl['id']=sql_insert('bs_pay_online',$pl);
                  else
                  {
                       sql_update('bs_pay_online',$pl,$ido);
                       $pl['id']=$ido;
                  }

                  if ($status==2)
                  {
                          $s='PayU:'.$pl['fdesc'];

                          $pl2=array();
                          $pl2['add_time']=date('Y-m-d H:i:s');
                          $pl2['modyf_time']=date('Y-m-d H:i:s');
                          $pl2['add_id_user']=0;
                          $pl2['modyf_id_user']=0;
                          $pl2['pidz']=$log['fcontrol'];
                          $pl2['pidf']=0;
                          $pl2['pidc']=$order['pidcontractor'];
                          $pl2['pdate']=$order['add_time'];
                          $pl2['pprice_b']=$log['famount'];
                          $pl2['rdesc']=$s;
                          $pl2['ridkp']=0;
                          $pl2['ridwb']=0;
                          $pl2['ridon']=$pl['id'];
                          sql_insert('bs_payments',$pl2);

                          sql_query('UPDATE bs_orders SET nprice=:data WHERE id=:id',array(':data'=>$pl2['pdate'],':id'=>$order['id']));
                          sql_query('UPDATE bs_orders SET nstatus=2 WHERE id=:id AND nstatus<2',array(':id'=>$order['id']));
                          sql_query('UPDATE bs_pay_online SET fsettled=1 WHERE id=:id',array(':id'=>$pl['id']));
                  }
              }



          } else {

              $log=array();
              $log['add_time']=date('Y-m-d H:i:s');
              $log['fposid']=$gposid;
              $log['fcontrol']=0;
              $log['famount']=0;
              $log['fstatus_s']=$result['message'];
              $log['fstatus']=-1;
              $log['fdesc']=$platnosci_response;
              $log['id']=sql_insert('bsc_log_payu',$log);

          }



    }

    //--- strona główna serwisu WWW ---
    public function action_index()
    {
        $id=(int)getGetPost('control');
        $status=(int)getGetPost('status');
        $order=sql_row('SELECT * FROM bs_orders WHERE id=:id',array(':id'=>$id));
        if (!$order) die('Nieprawidłowe wywołanie');

        if ($status==1) $url = Model_BSX_Core::$bsx_cfg['purl'].'/orders/order/'.$order['md5'].'?return=ok';
        else if ($status==0) $url =Model_BSX_Core::$bsx_cfg['purl'].'/orders/order/'.$order['md5'].'?return=fail' ;
        Header('Location: '.$url);
        exit;
    }

}
