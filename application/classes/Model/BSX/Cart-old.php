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

    public $country='Polska';
    public $delivery=null;
    public $payment=null;

    public $paymentsList=array();
    public $deliveryList=array();
    public $countryList=array('Polska'=>'Polska', 'Niemcy'=>'Niemcy', 'Belgia'=>'Belgia');
    public $discountRow=false;

    public static function init() {
        if (Model_BSX_Cart::$_init) return Model_BSX_Cart::$_init;

        if (isset($_SESSION['cart'])) {
            Model_BSX_Cart::$_init=unserialize($_SESSION['cart']);
        }
        else
        {
            Model_BSX_Cart::$_init=new Model_BSX_Cart();
            Model_BSX_Cart::$_init->paymentsList=sql_rows('SELECT * FROM bs_paymentsform WHERE cms_active=1 ORDER BY pname');
            Model_BSX_Cart::$_init->clear();
            Model_BSX_Cart::$_init->afterCartChange();
            Model_BSX_Cart::$_init->save();
        }
        return Model_BSX_Cart::$_init;
    }


    public function clear() {
        $this->items=array();
        $this->country='Polska';
        $this->delivery=null;
        $this->payment=null;
        $this->setDelivery('ELEKTRONICZNA');
        $this->setPayment('DOTPAY');

        foreach ($this->paymentsList as $payment)
            if (isset($payment['cms_default']) && $payment['cms_default'] == 1) {
                    $this->setPayment($payment['cms_idn']);
                    break;
            }

        foreach ($this->deliveryList as $delivery)
            if (isset($delivery['cms_default']) && $delivery['cms_default'] == 1) {
                    $this->setDelivery($delivery['cms_idn']);
                    break;
            }

    }


    public function add($item, $quantity=1) {
        // if the list of available countries is empty, do not add
        if (empty($this->countryList)) {
            $this->err = 'Problem z wysyłką. Błąd 0x021. Skontaktuj się z właścicielem sklepu!';
            return false;
        }

        // do not allow adding more items than the highest delivery threshold
        $allowed = false;
        $availableDeliveries = $this->getAvailableDeliveryTypes($item['id']);
        if (!empty($availableDeliveries)) {
            $highestThreshold = $this->getHighestQuantityThreshold($availableDeliveries);
            if ($this->count + $quantity <= $highestThreshold) $allowed = true;

            if ($item['pweight'] > 0) {
                $highestWeightThreshold = $this->getHighestWeightThreshold($availableDeliveries);
                if ($this->weight + $item['pweight'] > $highestWeightThreshold) $allowed = false;
            }
        }

        if (!$allowed) {
            $this->err = 'Nie można dodać podanego przedmiotu do koszyka! Utwórz oddzielne zamówienie!';
            return false;
        }

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

        $item['pquantity']=$quantity;
        $vat=1+($item['psrate_v']/100);
        if (Model_BSX_Shop::$calculationMethod==0) {
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


        $this->items[]=$item;

        $this->recalc();
        $this->afterCartChange();
        $this->save();
    }

    public function remove($lp) {
         if (isset($this->items[$lp])) unset($this->items[$lp]);
         $this->recalc();
        $this->afterCartChange();
         $this->save();

    }

    public function changeQuantity($lp,$newQuantity)
    {
         $newQuantity=(int)$newQuantity;
         if ($newQuantity<0) $newQuantity=0;
         if (isset($this->items[$lp]))
         {
             if ($newQuantity<=0) unset($this->items[$lp]);
             else $this->items[$lp]['pquantity']=$newQuantity;
         }
         $this->recalc();
        $this->afterCartChange();
         $this->save();
    }
    
    public function changePriceBrutto($lp,$newPrice)
    {
         if (isset($this->items[$lp]))
         {
             $this->items[$lp]['pbrutto']=$newPrice;
         }
         $this->recalc();
         $this->save();
    }

    public function setPayment($p)
    {
        foreach ($this->paymentsList as $payment)
        {
            if ($payment['cms_idn']==$p)
            {
                $this->payment=$payment;
                $this->recalc();
                $this->save();
                return true;
            }
        }
        return false;
    }

    public function setDelivery($p)
    {
        foreach ($this->deliveryList as $delivery)
        {
            if ($delivery['cms_idn']==$p)
            {
                $this->delivery=$delivery;
                $this->recalc();
                $this->save();
                return true;
            }
        }
        return false;
    }

    public function setCountry($p)
    {
        if (!isset($this->countryList[$p])) return false;
        $this->country=$p;
        $this->initializeDeliveryList();
        $this->save();
        return true;
    }

    public function setDiscountCode($code) {
        if ($code=='') return 0;
        $w=sql_row('SELECT * FROM bsc_codes WHERE ncode=:code AND nstatus=1',array(':code'=>$code));
        if (!$w) return -1;
        if ($w['nlimit']>0 && $w['nused']>=$w['nlimit']) return -2;
        if ($w['ndate1']!='' && $w['ndate1']!='1970-01-01' && strtotime($w['ndate1'])>time()) return -3;
        if ($w['ndate2']!='' && $w['ndate2']!='1970-01-01' && strtotime($w['ndate2'])<time()) return -4;
        $this->discountRow=$w;
        $this->recalc();
        $this->save();
        return 1;
    }

    public function recalc() {
        $this->sumNetto=0;
        $this->sumBrutto=0;
        $this->sumVat=0;
        $this->count=0;
        $this->weight=0;
        foreach ($this->items as &$item)
        {
             $vat=1+($item['psrate_v']/100);

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

            if (Model_BSX_Shop::$calculationMethod==0) { //metoda od netto
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
            $this->sumNetto+=$item['pstotal_n'];
            $this->sumVat+=$item['pstotal_v'];
            $this->sumBrutto+=$item['pstotal_b'];
            $this->count+=$item['pquantity'];
            $this->weight+=$item['pweight'];
         }
    }

    public function save() {
         $_SESSION['cart']=serialize($this);
    }


    public function count() {
         return $this->count;
    }

    public function err() {
        return $this->err;
    }

    public function items() {
         return $this->items;
    }

    public function getActiveDelivery($type, $netto)
    {

        if ($type=='price') {
            if ($this->delivery==null) $v=0;
            else if ($netto=='netto') $v=$this->delivery['cms_price'];
            else $v=$this->delivery['cms_price']*(1+$this->delivery['cms_ratev']/100);
            $v+= $this->addDeliveryExtraCharges();
            if ($v<=0) return 'Za darmo'; else return BinUtils::price($v,Model_BSX_Shop::$currency);
        }
    }

    public function totalBrutto()
    {
        $v=$this->sumBrutto;
        if (isset($this->delivery['cms_price'])) {
            $v+=($this->delivery['cms_price']*(1+$this->delivery['cms_ratev']/100));
            $v+= $this->addDeliveryExtraCharges();
        }
        return $v;
    }

    public function productInCartBySymbol($symbol) {
        foreach ($this->items as $item)
            if (!empty($item['psymbol']) && $item['psymbol']==$symbol) return $item;
        return false;
    }



    public function getDeliveryThresholds($weight=false) {
        if (!isset($this->delivery) || empty($this->delivery)) return false;
        $returnArray = sql_rows('SELECT cms_quantity, cms_price FROM bs_deliverytypes_pr WHERE iddoc=:id ORDER BY cms_quantity ASC', array(':id'=>$this->delivery['id']));
        return $returnArray;
    }


    public function addDeliveryExtraCharges() {
        $thresholds = $this->getDeliveryThresholds();
        if (empty($thresholds)) return 0;
        if ($this->delivery['cms_weight_on'] == 1) {
            foreach ($thresholds as $threshold) {
                if ($this->weight <= $threshold['cms_quantity']) {
                    return $threshold['cms_price'];
                    break;
                }
            }
        }
        else if ($this->delivery['cms_weight_on'] == 0) {
            foreach ($thresholds as $threshold) {
                if ($this->count <= $threshold['cms_quantity']) {
                    return $threshold['cms_price'];
                    break;
                }
            }
        }
        return 0;
    }


    public function restrictAvailableDeliveryTypes($idString,$threshold=NULL,$weightThreshold=false) {
        if (isset($threshold) &&  $weightThreshold) {
            foreach ($this->deliveryList as  $index=>$delivery) {
                // if adding the item exceeds a threshold, disable the appropriate delivery method
                if (isset($delivery['highestWeightThreshold']) && !empty($delivery['highestWeightThreshold']) && $this->weight + $threshold > $delivery['highestWeightThreshold']) {
                    unset($this->deliveryList[$index]);
                }
            }
        }
        else if (isset($threshold)) {
            foreach ($this->deliveryList as  $index=>$delivery) {
                // if adding the item exceeds a threshold, disable the appropriate delivery method
                if (isset($delivery['highestQuantityThreshold']) && !empty($delivery['highestQuantityThreshold']) && $this->count + $threshold > $delivery['highestQuantityThreshold']) {
                    unset($this->deliveryList[$index]);
                }
            }
        }
        else {
            $idArray = explode(',', $idString);
            foreach ($this->deliveryList as $index=>$delivery) {
                if (!in_array($delivery['id'], $idArray)) {
                    unset($this->deliveryList[$index]);
                }
            }
        }

        if (!in_array($this->delivery, $this->deliveryList)) $this->setActiveDelivery();
    }


    public function getAvailableDeliveryTypes($idProduct) {
        if(!isset($idString) || empty($idString)) return $this->deliveryList;
        $rows=sql_rows('SELECT iddev FROM bs_stockdev WHERE idproduct=:id',array(':id'=>$idProduct));

        $deliveryListCopy = $this->deliveryList;
        foreach ($deliveryListCopy as $index=>$delivery) {
            if (!in_array($delivery['id'], $rows)) {
                unset($deliveryListCopy[$index]);
            }
        }
        return $deliveryListCopy;
    }


    public function setActiveDelivery() {
        $this->delivery = reset($this->deliveryList);
    }

    public function setActiveCountry() {
        foreach ($this->countryList as $index=>$country) {
            $this->country = $country;
            break;
        }
    }


    public function getHighestQuantityThreshold($deliveryList) {
        $highestQuantityThreshold = 0;
        foreach($deliveryList as $index=>$delivery) {
            if (isset($delivery['highestQuantityThreshold']) && !empty($delivery['highestQuantityThreshold']) && (float) $delivery['highestQuantityThreshold'] > $highestQuantityThreshold) {
                $highestQuantityThreshold = $delivery['highestQuantityThreshold'];
            }
            else if (empty($delivery['highestQuantityThreshold'])) {
                $highestQuantityThreshold = 999999999;
                break;
            }
        }
        return $highestQuantityThreshold;
    }


    public function getHighestWeightThreshold($deliveryList) {
        $highestWeightThreshold = 0;
        foreach($deliveryList as $index=>$delivery) {
            if (isset($delivery['highestWeightThreshold']) && !empty($delivery['highestWeightThreshold']) && (float) $delivery['highestWeightThreshold'] > $highestWeightThreshold) {
                $highestWeightThreshold = (float) $delivery['highestWeightThreshold'];
            }
            else if (empty($delivery['highestWeightThreshold'])) {
                $highestWeightThreshold = 999999999;
                break;
            }
        }
        return $highestWeightThreshold;
    }


    public function initializeDeliveryList($restrictToSelectedCountry=true) {
        Model_BSX_Cart::$_init->deliveryList=sql_rows('SELECT * FROM bs_deliverytypes WHERE cms_active=1 ORDER BY cms_default DESC, pname ASC');
        foreach (Model_BSX_Cart::$_init->deliveryList as $index=>$delivery) {
            if ($delivery['cms_weight_on']==0) {
                Model_BSX_Cart::$_init->deliveryList[$index]['highestQuantityThreshold'] = sql_row('SELECT cms_quantity FROM bs_deliverytypes_pr WHERE iddoc=:iddoc ORDER BY cms_quantity DESC', array(':iddoc'=>$delivery['id']))['cms_quantity'];
            }
            else if ($delivery['cms_weight_on']==1) {
                Model_BSX_Cart::$_init->deliveryList[$index]['highestWeightThreshold'] = sql_row('SELECT cms_quantity FROM bs_deliverytypes_pr WHERE iddoc=:iddoc ORDER BY cms_quantity DESC', array(':iddoc'=>$delivery['id']))['cms_quantity'];
            }
        }
        //foreach ($this->items as $item) {
        //    if(isset($item['pdelivery_ids']) && !empty($item['pdelivery_ids'])) $this->restrictAvailableDeliveryTypes($item['pdelivery_ids']);
       // }

        if ($restrictToSelectedCountry) {
            foreach (Model_BSX_Cart::$_init->deliveryList as $index=>$delivery) {
                if (isset($delivery['pcountry']) && !empty($delivery['pcountry'])) {
                    if ($this->country != $delivery['pcountry']) {
                        unset(Model_BSX_Cart::$_init->deliveryList[$index]);
                        unset($this->deliveryList[$index]);
                    }
                }
            }
        }

        Model_BSX_Cart::$_init->restrictAvailableDeliveryTypes('',0);
        Model_BSX_Cart::$_init->restrictAvailableDeliveryTypes('',0,true);

        if (!in_array($this->delivery, $this->deliveryList)) $this->setActiveDelivery();
    }


    public function restrictAvailableCountries() {

        // update delivery list
        Model_BSX_Cart::$_init->initializeDeliveryList(false);

        $availableCountries = [];
        $allCountries = false;
        foreach ($this->deliveryList as $index=>$delivery) {
            if (!isset($delivery['pcountry']) || empty($delivery['pcountry'])) {
                $allCountries = true;
                break;
            }
            if (!in_array($delivery['pcountry'], $availableCountries)) {
                $availableCountries[] = $delivery['pcountry'];
            }
        }
        if (!$allCountries) {
            foreach ($this->countryList as $index=>$country) {
                if (!in_array($index, $availableCountries)) {
                    unset($this->countryList[$index]);
                }
            }
        }

        // update delivery list
        if (!in_array($this->country, $this->countryList)) $this->setActiveCountry();
        Model_BSX_Cart::$_init->initializeDeliveryList();
    }


    public function afterCartChange() {
        Model_BSX_Cart::$_init->restrictAvailableCountries();
    }

      //------------------------------------

    public function getDeliveries() {
        $d=sql_rows('SELECT id, pname, pcountry, cms_price, cms_idn, cms_default, cms_weight_on from bs_deliverytypes WHERE cms_active=1 ORDER BY cms_default DESC, pname ASC;');
        $this->deliveryList=$d;
    }

}
