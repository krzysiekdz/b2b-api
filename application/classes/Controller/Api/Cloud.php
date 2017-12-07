<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Api_Cloud extends Controller {

    public function before() {
        parent::before();
    }

    public function after() {
        parent::after();
    }


    public function action_index()
    {
        echo '<root><error>Błędne wywołanie!</error></root>';
    }

    //automatyczna realizacja zamówień
    public function action_cron()
    {
        //tworzymy faktury zakupowe do faktur sprzedażowych
        $lp=0;
        $rows=sql_rows('SELECT id FROM bs_invoices WHERE cltest=0 AND ntype=0 AND nstatus=2');
        foreach ($rows as $invoice) {
            Model_BSX_Invoices::createIncomingInvoiceForDoc($invoice['id']);
            $lp++;
        }
        echo date('Y-m-d H:i:s').' - Utworzono: '.$lp." faktur zakupowych.\n<br>";

        if (date('i')%5==0) {
            //usuwamy faktury zakupowe utworzone do faktur sprzedażowych, gdy te zostały usunięte
            $lp = 0;
            $rows = sql_rows('SELECT id FROM bs_invoices WHERE nstatus=2 AND ntype=1 AND idinvinc IS NULL AND clauto=1');
            foreach ($rows as $invoice) {
                sql_query('DELETE FROM bs_invoices WHERE id=:id', $invoice['id']);
                $lp++;
            }
            echo date('Y-m-d H:i:s') . ' - Usunięto: ' . $lp . " faktur zakupowych (usunięte odpowiedniki sprzedażowe).\n<br>";

            //usuwamy faktury zakupowe utworzone do faktur sprzedażowych, gdy tym zmieniono status
            $lp = 0;
            $rows = sql_rows('SELECT i.id FROM bs_invoices i LEFT JOIN bs_invoices a ON a.id=i.idinvinc WHERE i.nstatus=2 AND i.ntype=1 AND i.idinvinc>0 AND a.nstatus!=2');
            foreach ($rows as $invoice) {
                sql_query('DELETE FROM bs_invoices WHERE id=:id', $invoice['id']);
                $lp++;
            }
            echo date('Y-m-d H:i:s') . ' - Usunięto: ' . $lp . " faktur zakupowych (zmieniony status faktury sprzedaży).\n<br>";
        }


        //zlecenia transportowe
        $lp=0;
        $rows=sql_rows('SELECT id FROM bs_trans WHERE cltest=0 AND ntype=1 AND nstatus=1');
        foreach ($rows as $order) {
            Model_BSX_Transport::transIncomingTransForDoc($order['id']);
            $lp++;
        }
        echo date('Y-m-d H:i:s').' - Przetworzono: '.$lp." zleceń transportowych.\n<br>";

        //poprawienie numerów faktur na zleceniach transportowych
        $lp=0;
        $rows=sql_rows('SELECT t.id, t.ninvoice, i.nnodoc FROM bs_trans t LEFT JOIN bs_invoices i ON i.id=t.idinvoice WHERE idinvoice>0 AND t.ninvoice!=i.nnodoc');
        foreach ($rows as $order) {
            sql_query('UPDATE bs_trans SET ninvoice=:nnodoc WHERE id=:id',array(':id'=>$order['id'],':nnodoc'=>$order['nnodoc']));
            $lp++;
        }
        echo date('Y-m-d H:i:s').' - Poprawiono '.$lp." numerów faktur na zleceniach transportowych.\n<br>";

    }
    
}
