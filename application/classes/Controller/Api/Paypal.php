<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Api_Paypal extends Controller {

    public function before() {
        parent::before();
    }

    public function after() {
           parent::after();
    }

    public function action_result()
    {
         require_once (MODPATH.'paypalfunctions.php');

         $g=getGetPost('result');
         $t=getGetPost('TOKEN');
         $p=getGetPost('PayerID');
         if (isset($_SESSION['PAYPAL_PAYMENTAMOUNT'])) $v=$_SESSION['PAYPAL_PAYMENTAMOUNT']; else $v=0;
        // print_r($_SESSION);
         $c=(int)$_SESSION['PAYPAL_CONTROL'];
         $order=sql_row('SELECT * FROM bs_orders WHERE id=:id',array(':id'=>$c));
         if (!$c)
         {
               header('Location: '.$_SESSION['PAYPAL_BACKFAIL']);
               exit;

         }

         if ($g=='ok')
         {
              $_SESSION['payer_id']=$p;
              $w=ConfirmPayment($v);


              $pl=array();
              $pl['add_time']=date('Y-m-d H:i:s');
              $pl['fsettled']=0;
              $pl['ftype']=1;
              $pl['fido']=$order['id'];
              $pl['famount']=$v;
              $pl['femail']=$order['pemail'];
              $pl['fdesc']=$_SESSION['PAYPAL_DESC'];
              $pl['fnodoc']=0;
              $pl['riddotpay']=0;
              $pl['fstatus']=2;
              $pl['id']=sql_insert('bs_pay_online',$pl);


              $s='Paypal:'.$pl['fdesc'];
              $pl2=array();
              $pl2['add_time']=date('Y-m-d H:i:s');
              $pl2['modyf_time']=date('Y-m-d H:i:s');
              $pl2['add_id_user']=0;
              $pl2['modyf_id_user']=0;
              $pl2['pidz']=$order['id'];
              $pl2['pidf']=0;
              $pl2['pidc']=$order['pidcontractor'];
              $pl2['pdate']=$order['add_time'];
              $pl2['pprice_b']=$v;
              $pl2['rdesc']=$s;
              $pl2['ridkp']=0;
              $pl2['ridwb']=0;
              $pl2['ridon']=$pl['id'];
              sql_insert('bs_payments',$pl2);


              sql_query('UPDATE bs_orders SET nprice=:data WHERE id=:id',array(':data'=>$pl2['pdate'],':id'=>$order['id']));
              sql_query('UPDATE bs_orders SET nstatus=2 WHERE id=:id AND nstatus<2',array(':id'=>$order['id']));
              sql_query('UPDATE bs_pay_online SET fsettled=1 WHERE id=:id',array(':id'=>$pl['id']));

              Header('Location: '.$_SESSION['PAYPAL_BACKOK']);
         } else
         {
              header('Location: '.$_SESSION['PAYPAL_BACKFAIL']);
         }
         exit;
    }

    //--- strona główna serwisu WWW ---
    public function action_index()
    {
        require_once (MODPATH.'paypalfunctions.php');

        $id=(int)getPost('control');
        $order=sql_row('SELECT * FROM bs_orders WHERE id=:id',array(':id'=>$id));
        if (!$order) die('Nieprawidłowe wywołanie');

        $paymentAmount = (double)$order['nstotal_b'];
        $paymentAmount=str_replace(',','.',$paymentAmount);
        $paymentDesc=getPost('description');
        $currencyCodeType = "PLN";
        $paymentType = "Sale";
        $_SESSION['PAYPAL_BACKOK']=getPost('URL');
        $_SESSION['PAYPAL_BACKFAIL']=getPost('URLFAIL');
        $_SESSION['PAYPAL_CONTROL']=$order['id'];
        $_SESSION['PAYPAL_PAYMENTAMOUNT']=$paymentAmount;
        $_SESSION['PAYPAL_DESC']=$paymentDesc;

        $returnURL = Model_BSX_Core::$bsx_cfg['purl'].'/api/paypal/result?result=ok';
        $cancelURL =Model_BSX_Core::$bsx_cfg['purl'].'/api/paypal/result?result=fail' ;


        $resArray = CallShortcutExpressCheckout ($paymentAmount, $paymentDesc,$currencyCodeType, $paymentType, $returnURL, $cancelURL);
        $ack = strtoupper($resArray["ACK"]);
        if($ack=="SUCCESS" || $ack=="SUCCESSWITHWARNING")
        {
            RedirectToPayPal ( $resArray["TOKEN"] );
            exit;
        }
        else
        {
            //Display a user friendly Error on the page using any of the following error information returned by PayPal
            $ErrorCode = urldecode($resArray["L_ERRORCODE0"]);
            $ErrorShortMsg = urldecode($resArray["L_SHORTMESSAGE0"]);
            $ErrorLongMsg = urldecode($resArray["L_LONGMESSAGE0"]);
            $ErrorSeverityCode = urldecode($resArray["L_SEVERITYCODE0"]);

            echo "SetExpressCheckout API call failed. ";
            echo "Detailed Error Message: " . $ErrorLongMsg;
            echo "Short Error Message: " . $ErrorShortMsg;
            echo "Error Code: " . $ErrorCode;
            echo "Error Severity Code: " . $ErrorSeverityCode;
        }


    }

}
