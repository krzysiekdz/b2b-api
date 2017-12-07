<?php defined('SYSPATH') or die('No direct script access.');

class Model_BSX_FinalizeOrder
{
    private $order=array();
    private $shop=false;


    public function __construct($order) {
        $this->order=$order;
        $this->shop=sql_row('SELECT * FROM bsc_sites WHERE id=:id',array(':id'=>$this->order['cms_idsite']));
        if (!empty(Model_BSX_Core::$bsx_cfg['model'])&&is_callable(array(Model_BSX_Core::$bsx_cfg['model'], 'getDefaultShop'),true))
        {
            $this->shop=forward_static_call(array(Model_BSX_Core::$bsx_cfg['model'], 'getDefaultShop'));
        }
    }

  public function finalize() {
      //jak nie ma sklepu, nie realizujemy
      if (!$this->shop) return false;

      //w trakcie realizacji
      sql_query('UPDATE bs_orders SET cms_status=1 WHERE id=:id',array(':id'=>$this->order['id']));

      //jak nie ma MD5 - to tworzymy
      if (empty($this->order['md5']))
      {
            $this->order['md5']=md5(time().'!'.$this->order['nvnodoc']);
            sql_query('UPDATE bs_orders SET md5=:md5 WHERE id=:id',array(':md5'=>$this->order['md5'],':id'=>$this->order['id']));
      }

       //realizujemy poszczególne pozycje
       $cnt=$cancel=$no=$ok=0;
       $rows=sql_rows('SELECT * FROM bs_orders_pr WHERE iddoc=:iddoc',array(':iddoc'=>$this->order['id']));
       foreach ($rows as $row)
       {
           $cnt++;
           $this->finalizeItem($row);
           if ($row['nstatus']==0 || $row['nstatus']==1 || $row['nstatus']==11) $no++;
           if ($row['nstatus']==3) $ok++;
           if ($row['nstatus']==4 || $row['nstatus']==41) $cancel++;
       }

      //jak wszystko jest zrealizowane lub anulowane
       if ($no==0) {

           if ($ok>0) {
               //zrealizowane
               $d=array();
               $d['nstatus']=2; //częściowo zrealizowane - bo jeszcze faktura
               $d['cms_status']=3; //wewnętrznie ZREALIZOWANE
               sql_update('bs_orders',$d,$this->order['id']);

               //jak jest e-mail - wysyłamy wiadomość
               if (!empty($this->order['pemail'])) {
                   $mail = Model_BSX_Core::mail_view('realizacja', 'Zamówienie ' . $this->order['nnodoc'] . ' zostało zrealizowane.', Model_BSX_Core::$bsx_cfg['psite_skin'], Model_BSX_Core::$bsx_cfg['purl']);
                   $mail->set('order', $this->order);
                   $mail->set('shop', Model_BSX_Core::$bsx_cfg);
                   $vmail = $mail->render();
                   $em=$nz='';
                   BinUtils::explodeMail($this->shop['pemail'], $em, $nz);
                   $email = Email::factory()->subject($mail->title)->to($this->order['pemail'])->from($em, $nz)->message($vmail)->send();
               }

               if (!empty(Model_BSX_Core::$bsx_cfg['model']) && is_callable(array(Model_BSX_Core::$bsx_cfg['model'], 'finalizeOrder'),true))
               {
                   forward_static_call(array(Model_BSX_Core::$bsx_cfg['model'], 'finalizeOrder'),$this,$this->order['id'],3);
               }

           } else {
               //anulowane
               $d=array();
               $d['nstatus']=4; //zamówienie anulowane
               $d['cms_status']=3; //wewnętrznie ANULOWANE
               sql_update('bs_orders',$d,$this->order['id']);

               //jak jest e-mail - wysyłamy wiadomość
               if (!empty($this->order['pemail'])) {
                   $mail = Model_BSX_Core::mail_view('anulowanie', 'Zamówienie ' . $this->order['nnodoc'] . ' zostało anulowane.', Model_BSX_Core::$bsx_cfg['psite_skin'], Model_BSX_Core::$bsx_cfg['purl']);
                   $mail->set('order', $this->order);
                   $mail->set('shop', Model_BSX_Core::$bsx_cfg);
                   $vmail = $mail->render();
                   $em=$nz='';
                   BinUtils::explodeMail($this->shop['pemail'], $em, $nz);
                   $email = Email::factory()->subject($mail->title)->to($this->order['pemail'])->from($em, $nz)->message($vmail)->send();
               }
           }

           return true;

       } else {
           //nie wszystko zrealizowane - sprawdzimy jeszcze raz
           sql_query('UPDATE bs_orders SET cms_status=0 WHERE id=:id',array(':id'=>$this->order['id']));
           return false;
       }
  }


  private function finalizeItem(&$item) {
      if ((int)$item['idproduct']<=0) return false;

      $m=sql_row('SELECT m.pidn FROM bs_stockindex i LEFT JOIN ms_procedures m ON m.id=i.idproc WHERE i.id=:id',array(':id'=>$item['idproduct']));

      if (!$m || empty($m['pidn'])) return false;

      if ($m['pidn']=='MP')
      {
          return Model_Binsoft_Core::finalizeItem($this->shop, $this->order, $item);
      } else return false;
  }

}
