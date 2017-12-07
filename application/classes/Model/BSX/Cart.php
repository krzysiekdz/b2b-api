<?php defined('SYSPATH') or die('No direct script access.');

class Model_BSX_Cart
{
    private static $_init   = false;
    public $items=array();
    public $sumNetto=0;
    public $sumBrutto=0;
    public $sumVat=0;
    public $count=0;
    public $weight=0;
    public $err='';
    public $discountRow=false;

    public $country=null;
    public $delivery=null;
    public $payment=null;

    public $paymentsList=null;
    public $deliveryList=null;
    public $countryList=null;

    private $deliveryListAll=null;//pamieta wszystkie opcje dostawy, bo deliveryList zawiera tylko te aktywne dla wybranego kraju

    
    private function __construct() {}


    private function fetchDeliveries() {
        $this->deliveryListAll=sql_rows('SELECT id, pname, pcountry, cms_price, cms_idn, cms_default, cms_weight_on, cms_ratev from bs_deliverytypes WHERE cms_active=1 ORDER BY cms_default DESC, pname ASC;');
        foreach($this->deliveryListAll as &$delivery) {
            $delivery['values']=sql_rows('SELECT cms_quantity, cms_price FROM bs_deliverytypes_pr WHERE iddoc=:id ORDER BY cms_quantity ASC', array(':id'=>$delivery['id']));
        }
        $this->restrictDeliveries();
    }

    private function fetchPayments() {
        $this->paymentsList=sql_rows('SELECT id, cms_price, cms_ratev, cms_idn, cms_default, pname FROM bs_paymentsform WHERE cms_active=1 ORDER BY cms_default DESC, pname ASC;');
    }

    private function fetchCountries() {
        $this->countryList=array('Polska'=>'Polska', 'Niemcy'=>'Niemcy', 'Belgia'=>'Belgia');
        $this->country='Polska';
    }

    private function restrictDeliveries() {//wybranie przesylek dostepnych w danym kraju
        $this->deliveryList=array();
        foreach($this->deliveryListAll as $delivery) {
            if(empty($delivery['pcountry'])) $this->deliveryList[]=$delivery;
            else if($delivery['pcountry']==$this->country) $this->deliveryList[]=$delivery;
        }
        if(!in_array($this->delivery, $this->deliveryList)) $this->delivery=$this->deliveryList[0];//wybieramy domyslnie pierwsza dostepna opcje wysylki, jesli aktualnie ustawiona nie pasuje
    }

    // private function &findProduct($id) {
    //     foreach($this->items as $item) {
    //         if($item['id']==$id) return $item;//zwrocenie referencji
    //     }
    //     return array();
    // }

    //pobiera w zaleznosci od konkretnego progu (cenowego, wagowego) cene przesylki
    private function findDeliveryPayment($val, $def) {
        if (!isset($this->delivery['values']) ) return $def;
        $values=$this->delivery['values'] ;
        $res=$def;
        foreach($values as $v) {
            if($v['cms_quantity'] >= $val) break;
            else $res=$v['cms_price'];
        }
        return $res;
    }

    private function addAttributes(&$item) {
        // add selected attributes
        if (isset($item['selectedAttributes'])) {
            $sklep=Model_BSX_Shop::init();
            $attributeNames = $sklep->getAttributeNames($item['selectedAttributes']);
            if ($attributeNames) {
                $item['pattributes']='(';
                foreach ($attributeNames as $attributeName=>$attributeValue) {
                    $item['pattributes'].= $attributeName.': '.$attributeValue.', ';
                }
                $item['pattributes'] = substr($item['pattributes'], 0, -2);
                $item['pattributes'].= ')';
            }
            $sklep->recalculatePrice($item, $item['selectedAttributes']);
        }
    }

    private function calcProduct(&$item) {
        $vat=1+($item['psrate_v']/100);
        if (Model_BSX_Shop::$calculationMethod==0) {//metoda od netto
            $item['pbrutto']=$item['pnetto']*$vat;
            $item['psprice_n'] = $item['pnetto'];
            $item['psprice_b'] = $item['psprice_n'] * $vat;
            $item['psprice_v'] = $item['psprice_b'] - $item['psprice_n'];
            $item['pstotal_n'] = $item['psprice_n'] * $item['pquantity'];
            $item['pstotal_b'] = $item['psprice_n'] * $item['pquantity'] * $vat;
            $item['pstotal_v'] = $item['pstotal_b'] - $item['pstotal_n'];
        } else {
            if ($vat>0) $item['pnetto']=$item['pbrutto']/$vat; else $item['pnetto']=$item['pbrutto'];
            $item['psprice_b'] = $item['pbrutto'];
            if ($vat>0) $item['psprice_n'] = $item['psprice_b'] / $vat; else $item['psprice_n']=$item['psprice_b'];
            $item['psprice_v'] = $item['psprice_b'] - $item['psprice_n'];
            $item['pstotal_b'] = $item['psprice_b'] * $item['pquantity'];
            if ($vat>0) $item['pstotal_n'] = $item['psprice_b'] * $item['pquantity'] / $vat; else $item['pstotal_n']=$item['pstotal_b'];
            $item['pstotal_v'] = $item['pstotal_b'] - $item['pstotal_n'];
        }
    }

    private function recalcProduct(&$item) {//prawie to samo co calcProduct, ale bez zapiswania do pol pbrutto i pnetto
        $vat=1+($item['psrate_v']/100);
        if (Model_BSX_Shop::$calculationMethod==0) { 
             $item['psprice_b'] = $item['psprice_n'] * $vat;
             $item['psprice_v'] = $item['psprice_b'] - $item['psprice_n'];
             $item['pstotal_n'] = $item['psprice_n'] * $item['pquantity'];
             $item['pstotal_b'] = $item['psprice_n'] * $item['pquantity'] * $vat;
             $item['pstotal_v'] = $item['pstotal_b'] - $item['pstotal_n'];
         } else {
             if ($vat>0) $item['psprice_n'] = $item['psprice_b'] / $vat; else $item['psprice_n']=$item['psprice_b'];
             $item['psprice_v'] = $item['psprice_b'] - $item['psprice_n'];
             $item['pstotal_b'] = $item['psprice_b'] * $item['pquantity'];
             if ($vat>0) $item['pstotal_n'] = $item['psprice_b'] * $item['pquantity'] / $vat; else $item['pstotal_n']=$item['pstotal_b'];
             $item['pstotal_v'] = $item['pstotal_b'] - $item['pstotal_n'];
         }
    }

    private function calcDiscount(&$item) {//obliczanie cen po zastosowaniu kodu rabatowego
        if ($this->discountRow) {
             if (isset($item['old_psprice_n'])) {
                 $item['psprice_n']=$item['old_psprice_n'];
                 $item['psprice_b']=$item['old_psprice_b'];
             } else {
                 $item['old_psprice_n'] = $item['psprice_n'];
                 $item['old_psprice_b'] = $item['psprice_b'];
             }
             $item['psprice_n']=$item['psprice_n']-($this->discountRow['nvalue']/100)*$item['psprice_n'];
             $item['psprice_b']=$item['psprice_b']-($this->discountRow['nvalue']/100)*$item['psprice_b'];
         }
    }

    //--------------------- public API

    public static function init() {
        if (Model_BSX_Cart::$_init) return Model_BSX_Cart::$_init;

        if (isset($_SESSION['cart'])) {
            Model_BSX_Cart::$_init=unserialize($_SESSION['cart']);
        }
        else
        {
            // echo 'creating cart<br>';
            $cart=new Model_BSX_Cart;
            Model_BSX_Cart::$_init=$cart;
            $cart->fetchCountries();
            $cart->fetchPayments();
            $cart->fetchDeliveries();
            $cart->save();
        }
        return Model_BSX_Cart::$_init;
    }

   
    public function count() {
        return $this->count;
    }

    public function remove($lp) {
        if (isset($this->items[$lp])) unset($this->items[$lp]);
    }

    public function items() {
        return $this->items;
    }

    //sumowanie ceny produktow oraz kosztow wysylki
    public function totalBrutto() {
        return $this->sumBrutto + $this->getDeliveryPayment();
    }

    public function getDeliveryPayment() {
        $v=0;
        if(!isset($this->delivery)) return $v;
        $d=$this->delivery;

        $vat=1+(getValueInt($d,'cms_ratev')/100);
        $price=getValueInt($d,'cms_price');

        if(!$d['values']) $v=$price*$vat; //jesli nie ma ustawionych dodatkowych progow - bierzemy pod uwage tylko pole cms_price
        else if($d['cms_weight_on']==0) { //dostawca ktory liczy wysylke pod wzgledem ceny
            $v=$this->findDeliveryPayment($this->sumBrutto, $price) * $vat;
        } 
        else if ($d['cms_weight_on']==1)  {//dostawca ktory liczy wysylke pod wzgledem wagi
            $v=$this->findDeliveryPayment($this->weight, $price) * $vat;
        }

        return $v;
    }

    public  function add($item, $quantity) {
        $item['pquantity']=$quantity;
        $this->addAttributes($item);
        $this->calcProduct($item);
        $this->items[]=$item;
    }

    public function setDelivery($cms_idn) {
        if(isset($this->delivery) && $cms_idn==$this->delivery['cms_idn']) return;//jesli ustawiamy to samo co bylo, to return

        foreach($this->deliveryList as $delivery) {
            if($delivery['cms_idn']==$cms_idn) {
                $this->delivery=$delivery;
                break;
            }
        }
    }

    public function setPayment($cms_idn) {
        if(isset($this->payment) && $cms_idn==$this->payment['cms_idn']) return;//jesli ustawiamy to samo co bylo, to return

        foreach($this->paymentsList as $payment) {
            if($payment['cms_idn']==$cms_idn) {
                $this->payment=$payment;
                break;
            }
        }
    }

    public function setCountry($cname) {
        if(isset($this->country) && $cname==$this->country) return;//jesli ustawiamy to samo co bylo, to return

        foreach($this->countryList as $id=>$country) {
            if($id==$cname)  {
                $this->country=$country;
                break;
            }
        }
        $this->restrictDeliveries();
    }

    public function setDiscountCode($code) {
        if ($code=='') return 0;
        $w=sql_row('SELECT * FROM bsc_codes WHERE ncode=:code AND nstatus=1',array(':code'=>$code));
        if (!$w) return -1;
        if ($w['nlimit']>0 && $w['nused']>=$w['nlimit']) return -2;
        if ($w['ndate1']!='' && $w['ndate1']!='1970-01-01' && strtotime($w['ndate1'])>time()) return -3;
        if ($w['ndate2']!='' && $w['ndate2']!='1970-01-01' && strtotime($w['ndate2'])<time()) return -4;
        $this->discountRow=$w;
        return 1;
    }

    public function changeQuantity($lp, $quantity) {
        if($quantity<0) $quantity=0;
        $this->items[$lp]['pquantity']=$quantity;
    }

    public function show() {
        print_r($this->weight);
    }

    public function clear() {
        unset($_SESSION['cart']);
    }

    //recalc wywolac po ostatecznych zmianach na koszyku!
    public function recalc() {
        $this->sumNetto=0;
        $this->sumBrutto=0;
        $this->sumVat=0;
        $this->count=0;
        $this->weight=0;
        foreach ($this->items as &$item) 
        {
            $this->calcDiscount($item);
            $this->recalcProduct($item);
            $this->sumNetto+=$item['pstotal_n'];
            $this->sumVat+=$item['pstotal_v'];
            $this->sumBrutto+=$item['pstotal_b'];
            $this->count+=$item['pquantity'];
            $this->weight+=$item['pweight']*$item['pquantity'];
         }
    }

    //save wywolac po ostatecznych zmianach i wywolaniu recalc !
    public function save() {
        $_SESSION['cart']=serialize(Model_BSX_Cart::$_init);
    }


}

function getValueInt($arr, $value, $def=0) {
    if(!isset($arr[$value])) return $def;
    return (int)$arr[$value];
}
