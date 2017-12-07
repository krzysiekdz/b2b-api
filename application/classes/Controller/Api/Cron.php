<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Api_Cron extends Controller {

    public function before() {
        parent::before();
    }

    public function after() {
           parent::after();
    }


    public function action_index()
    {

           echo 'No handle';

    }

    //automatyczna realizacja zamówień
    public function action_finalizeOrders()
    {
        //$x=Model_BSX_Invoices::createInvoiceForOrder(683,0);
        //Model_BSX_Invoices::generateInvoicePDF($x);
         $lp=0;
         //realizujemy zamówienia: w trakcie realizacji i częściowo zrealizowane, opłacone i gdzie cms_status=0, czyli jeszcze nie były analizowane przez CMS
         $rows=sql_rows('SELECT * FROM bs_orders WHERE (nstatus=1 OR nstatus=2) AND nprice IS NOT NULL AND nprice!="1970-01-01" AND cms_status=0');
         foreach ($rows as $order) {
              $fin=new Model_BSX_FinalizeOrder($order);
              if ($fin->finalize()) $lp++;
              unset($fin);
         }
         echo date('Y-m-d H:i:s').' - Zrealizowano: '.$lp." zamowien\n";
    }

}
