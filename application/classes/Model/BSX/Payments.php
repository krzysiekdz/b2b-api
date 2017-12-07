<?php defined('SYSPATH') or die('No direct script access.');

class Model_BSX_Payments
{
     public $suppliers=array();
     public $lang='pl';
     public $backButtonTXT='Wróć do sklepu';
     public $urlOK='';
     public $urlFAIL='';
     public $price=0;
     public $currency='PLN';
     public $desc='';
     public $urlConfirm='';
     public $orderID=0;
     public $buyer=array('name'=>'','street'=>'','city'=>'','postcode'=>'','country'=>'','email'=>'');
     public $buttonPriceTXT='Zapłać za zamówienie';

     public function __construct() {
          $this->suppliers=array();
          if (isset(Model_BSX_Core::$bsx_cfg['payments']))
           foreach (Model_BSX_Core::$bsx_cfg['payments'] as $n=>$pay) $this->suppliers[$n]=$pay;
     }

     public function generateForm($dostawca,$urlConfirm=null,$buttonText=null) {
       if ($urlConfirm==null) $urlConfirm=$this->urlConfirm;
       if ($buttonText==null) $buttonText=$this->buttonPriceTXT;
       if (empty($urlConfirm) && isset($this->suppliers['dotpay']) && isset($this->suppliers['dotpay']['confirmURL'])) $urlConfirm=$this->suppliers['dotpay']['confirmURL'];

       if ($dostawca=='dotpay' && !empty($this->suppliers['dotpay']['id'])) {
                  echo '<form action="https://ssl.dotpay.pl" method="post" name="form_payment_dotpay">';
                  echo '<input type="submit" style="position: absolute; left: -9999px; width: 1px; height: 1px;"/>';
                  echo '<input type="hidden" name="lang" value="'.$this->lang.'" />';
                  echo '<input type="hidden" name="id" value="'.$this->suppliers['dotpay']['id'].'" />';
                  echo '<input type="hidden" name="buttontext" value="'.$this->backButtonTXT.'" />';
                  echo '<input type="hidden" name="URL" value="'.$this->urlOK.'" />';
                  echo '<input type="hidden" name="amount" value="'.BinUtils::price($this->price).'" />';
                  echo '<input type="hidden" name="currency" value="'.$this->currency.'" />';
                  echo '<input type="hidden" name="description" value="'.$this->desc.'" />';
                  echo '<input type="hidden" name="type" value="3" />';
                  echo '<input type="hidden" name="URLC" value="'.$urlConfirm.'" />';
                  echo '<input type="hidden" name="control" value="'.$this->orderID.'" />';
                  echo '<input type="hidden" name="forename" value="" />';
                  echo '<input type="hidden" name="surname" value="'.$this->buyer['name'].'" />';
                  echo '<input type="hidden" name="street" value="'.$this->buyer['street'].'" />';
                  echo '<input type="hidden" name="city" value="'.$this->buyer['city'].'" />';
                  echo '<input type="hidden" name="postcode" value="'.$this->buyer['postcode'].'" />';
                  echo '<input type="hidden" name="country" value="'.$this->buyer['country'].'" />';
                  echo '<input type="hidden" name="email" value="'.$this->buyer['email'].'" />';
                  echo '<div class="payment_dotpay"><a href="javascript:document.form_payment_dotpay.submit();" class="btn btn-success">'.$buttonText.'</a></div>';
                  echo '</form>';
       } else
       if ($dostawca=='paypal') {
                  echo '<form action="/api/Paypal" METHOD="POST">';
                  echo '<input type="hidden" name="lang" value="'.$this->lang.'" />';
                  echo '<input type="hidden" name="URL" value="'.$this->urlOK.'" />';
                  echo '<input type="hidden" name="URLFAIL" value="'.$this->urlFAIL.'" />';
                  echo '<input type="hidden" name="amount" value="'.str_replace(',','.',BinUtils::price($this->price)).'" />';
                  echo '<input type="hidden" name="currency" value="'.$this->currency.'" />';
                  echo '<input type="hidden" name="description" value="'.$this->desc.'" />';
                  echo '<input type="hidden" name="control" value="'.$this->orderID.'" />';
                  echo '<input type="hidden" name="forename" value="" />';
                  echo '<input type="hidden" name="surname" value="'.$this->buyer['name'].'" />';
                  echo '<input type="hidden" name="street" value="'.$this->buyer['street'].'" />';
                  echo '<input type="hidden" name="city" value="'.$this->buyer['city'].'" />';
                  echo '<input type="hidden" name="postcode" value="'.$this->buyer['postcode'].'" />';
                  echo '<input type="hidden" name="country" value="'.$this->buyer['country'].'" />';
                  echo '<input type="hidden" name="email" value="'.$this->buyer['email'].'" />';

                  if (stripos($buttonText,'<')!==false && stripos($buttonText,'>')!==false) echo $buttonText;
                  else echo '<input type="image" name="submit" src="https://www.paypal.com/pl_PL/i/btn/btn_xpressCheckout.gif" border="0" align="top" alt="Zapłać poprzez PayPal" />';
                  echo '</form>';
       } else
       if ($dostawca=='payu') {

                  if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                      $ip = $_SERVER['HTTP_CLIENT_IP'];
                  } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                      $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                  } else {
                      $ip = $_SERVER['REMOTE_ADDR'];
                  }

                  $imie='';
                  $nazwisko=$this->buyer['name'];
                  $k=strpos($nazwisko,' ');
                  if ($k!==false) {$imie=substr($nazwisko,0,$k);$nazwisko=substr($nazwisko,$k+1);}

                  $amount=floor($this->price*100);
                  $ts=time();
                  $sessionid=$this->orderID.'-'.$ts;
                  $sig = md5 ($this->suppliers['payu']['id'].$sessionid.$this->suppliers['payu']['pos_auth_key'].$amount.$this->desc.$this->orderID.$imie.$nazwisko.$this->buyer['email'].$ip.$ts.$this->suppliers['payu']['key1']);
                  //sig = md5 ( pos_id + pay_type + session_id + pos_auth_key + amount   + desc + desc2 + trsDesc + order_id + first_name + last_name   + payback_login + street + street_hn + street_an + city   + post_code + country + email + phone + language   + client_ip + ts + key1 )
                  echo '<form action="https://secure.payu.com/paygw/UTF/NewPayment" method="post" name="form_payment_payu">';
                  echo '<input type="submit" style="position: absolute; left: -9999px; width: 1px; height: 1px;"/>';
                  echo '<input type="hidden" name="first_name" value="'.$imie.'" />';
                  echo '<input type="hidden" name="last_name" value="'.$nazwisko.'" />';
                  echo '<input type="hidden" name="email" value="'.$this->buyer['email'].'" />';

                  echo '<input type="hidden" name="pos_id" value="'.$this->suppliers['payu']['id'].'" />';
                  echo '<input type="hidden" name="pos_auth_key" value="'.$this->suppliers['payu']['pos_auth_key'].'" />';
                  echo '<input type="hidden" name="order_id" value="'.$this->orderID.'" />';
                  echo '<input type="hidden" name="session_id" value="'.$sessionid.'" />';
                  echo '<input type="hidden" name="amount" value="'.$amount.'" />';
                  echo '<input type="hidden" name="desc" value="'.$this->desc.'" />';
                  echo '<input type="hidden" name="client_ip" value="'.$ip.'" />';
                  echo '<input type="hidden" name="ts" value="'.$ts.'" />';
                  echo '<input type="hidden" name="sig" value="'.$sig.'" />';
                  echo '<input type="hidden" name="js" value="0" />';

                  echo '<div class="payment_payu">';
                  if (stripos($buttonText,'<')!==false && stripos($buttonText,'>')!==false) echo $buttonText;
                  else echo '<a href="javascript:document.form_payment_payu.submit();"><span>'.$buttonText.'</span></a>';
                  echo '</div>';
                  echo '<script type="text/javascript"> document.forms["form_payment_payu"].js.value=1; </script> ';
                  echo '</form>';
       } else
       if ($dostawca=='przelewy24') {
           if (isset($_GET['request']) && $_GET['request']=='true')
           {
               $countryCode=strtolower($this->buyer['country']);
               if ($countryCode=='polska' || $countryCode=='pl' || $countryCode=='') $countryCode='PL';
               $kwota=(int)(str_replace(' ','',str_replace(',','.',BinUtils::price($this->price)))*100);
               include_once(APPPATH.'vendor/class_przelewy24.php');
               $P24 = new Przelewy24($this->suppliers['przelewy24']['id'], $this->suppliers['przelewy24']['id'], $this->suppliers['przelewy24']['key'], false);
               $P24->addValue('p24_session_id',$this->orderID);
               $P24->addValue('p24_amount',$kwota);
               $P24->addValue('p24_currency','PLN');
               $P24->addValue('p24_description',$this->desc);
               $P24->addValue('p24_email',$this->buyer['email']);
               $P24->addValue('p24_client',$this->buyer['name']);
               $P24->addValue('p24_address',$this->buyer['street']);
               $P24->addValue('p24_zip',$this->buyer['postcode']);
               $P24->addValue('p24_city',$this->buyer['city']);
               $P24->addValue('p24_country',$countryCode);
               $P24->addValue('p24_url_return',$this->urlOK);
               $P24->addValue('p24_url_status',$this->suppliers['przelewy24']['confirmURL']);
               $P24->addValue('p24_encoding','UTF-8');

               $token=sql_getvalue('SELECT p24_token FROM bs_orders WHERE id=:id',$this->orderID,'');
               if ($token!='') {
                   Header('Location: ' . $P24->getHost() . "trnRequest/" . $token);
                   exit;
               } else {
                   $res = $P24->trnRegister(true);
                   if (isset($res["error"]) and $res["error"] > 0) {
                       echo '<div class="alert alert-error">' . $res['errorMessage'] . '</div>';
                   } else {
                       sql_query('UPDATE bs_orders SET p24_token=:token WHERE id=:id', array(':token' => $res['token'], ':id' => $this->orderID));
                       Header('Location: ' . $P24->getHost() . "trnRequest/" . $res["token"]);
                       exit;
                   }
               }
           }

           $r=$_SERVER['REQUEST_URI'];
           if (strpos($r,'?')!==false) $r.='&request=true'; else $r.='?request=true';
           echo '<div class="payment_dotpay"><a href="'.$r.'" class="btn btn-success">'.$buttonText.'</a></div>';

       }

     }
}