<?php defined('SYSPATH') or die('No direct script access.');

class Model_BSX_Invoices
{
    private static $_init = false;


    public static function init()
    {
        if (Model_BSX_Invoices::$_init) return Model_BSX_Invoices::$_init;

        Model_BSX_Invoices::$_init = new Model_BSX_Invoices();
        return Model_BSX_Invoices::$_init;
    }

    //poprawienie faktury zakupowej wystawionej do faktury sprzedaży, gdy ta pierwsza została zmieniona
    public static function correctIncomingInvoiceForDoc($fromID, $toID)
    {
        $t1=sql_row('SELECT * FROM bs_invoices WHERE id=',$fromID);
        $t2=sql_row('SELECT * FROM bs_invoices WHERE id=',$toID);
        if (!$t1 || !$t2) return false;

        sql_query('DELETE FROM bs_invoices_pr WHERE iddoc=:iddoc',$toID);
        $items=sql_rows('SELECT id FROM bs_invoices_pr WHERE iddoc=:id',$fromID);
        foreach ($items as $row) {
            $wNID2 = sql_duplicate_row('bs_invoices_pr', $row['id']);
            sql_query('UPDATE bs_invoices_pr SET iddoc=:iddoc, idcompany=:idcompany, idbranch=NULL, idowner=NULL, ntype=:ntype, nsubtype=:nsubtype WHERE id=:id',array(':iddoc'=>$t2['id'],':idcompany'=>$t2['idcompany'],':ntype'=>$t2['ntype'],':nsubtype'=>$t2['nsubtype'],':id'=>$wNID2));
        }

        $fields=array('nnodoc','nvnodoc','npaymentform','npaymentdate','ndpaymentdate','ncurrency','ncurrencydate','ncurrencytable','ncurrencyrate','nstotal_n','nstotal_b','nstotal_v','ndate_sell','ndate_issue','nplace','nnote','ncom1','ncom2');
        $e=array();
        foreach ($fields as $field) $e[$field]=$t1[$field];
        sql_update('bs_invoices',$e,$toID);

        //jak jest "Globalny Identyfikator Zamówienia
        if (!empty($t2['refid']) && $t2['idcompany']>0) {
            //szukamy zamówienia przychodzącego
            $row_z=sql_row('SELECT id, ninvoice, idinvoice FROM eurofast_orders WHERE refid=:refid AND idcompany=:idcompany',array(':refid'=>$t2['refid'],':idcompany'=>$t2['idcompany']));
            if ($row_z && $row_z['idinvoice']!='' && $row_z['idinvoice']>0) {
                //mamy fakturę do tego zamówienia, trzeba ją poprawić
                $items1=sql_rows('SELECT id, pquantity, psymbol FROM bs_invoices_pr WHERE iddoc=:id ORDER BY id ASC',$fromID);
                $items2=sql_rows('SELECT * FROM bs_invoices_pr WHERE iddoc=:id ORDER BY id ASC',$row_z['idinvoice']);
                $fail=false;
                $w=array('nstotal_n'=>0,'nstotal_v'=>0,'nstotal_b'=>0);
                if (count($items1)==count($items2)) {
                    foreach ($items2 as $lp=>$item)
                      if ($item['psymbol']==$items1[$lp]['psymbol']) {
                          $e=array();
                          $v=$item['psrate_v']/100;
                          $e['pquantity']=$items1[$lp]['pquantity'];
                          $e['pstotal_n']=BinUtils::correctPrice($item['psprice_n']*$e['pquantity']);
                          $e['pstotal_v']=BinUtils::correctPrice($e['pstotal_n']*$v);
                          $e['pstotal_b']=BinUtils::correctPrice($e['pstotal_n']+$e['pstotal_v']);

                          sql_update('bs_invoices_pr',$e,$item['id']);
                          $w['nstotal_n']=BinUtils::correctPrice($w['nstotal_n']+$e['pstotal_n']);
                          $w['nstotal_v']=BinUtils::correctPrice($w['nstotal_v']+$e['pstotal_v']);
                          $w['nstotal_b']=BinUtils::correctPrice($w['nstotal_b']+$e['pstotal_b']);
                      } else {
                          $fail=true;
                          break;
                      }

                    if (!$fail) {
                        $fields=array('npaymentform','npaymentdate','ndpaymentdate','ncurrency','ncurrencydate','ncurrencytable','ncurrencyrate','ndate_sell','ndate_issue','nplace','nnote','ncom1','ncom2');
                        foreach ($fields as $field) $w[$field]=$t1[$field];
                        $w['cltest']=0;
                        sql_update('bs_invoices',$w,$row_z['idinvoice']);
                    }
                }

            }
        }

        return true;
    }

    //wystawienie faktury zakupowej do faktury sprzedaży
    public static function createIncomingInvoiceForDoc($nid)
    {
        if ($nid<=0) return false;
        //oznaczamy, że została przetworzona
        sql_query('UPDATE bs_invoices SET cltest=1 WHERE id=',$nid);

        //jak jest już taka faktura, to ją "poprawiamy"
        $lresID=sql_getvalue('SELECT id FROM bs_invoices WHERE idinvinc=',$nid,0);
        if ($lresID>0) {
            Model_BSX_Invoices::correctIncomingInvoiceForDoc($nid,$lresID);
            return true;
        }

        //pobieramy podstawowe informacje o fakturze
        $invoice=sql_row('SELECT * FROM bs_invoices WHERE id=:id',array(':id'=>$nid));
        if (!$invoice) return false;

        //jak na fakturze nie ma NIP-u odbiorcy, to nic nie zrobimy
        if (empty($invoice['pnip'])) return false;

        //szukamy firmy o danym NIP-ie, jak nie ma - nie ma do kogo wysłać faktury
        $newCompanyID=(int)sql_getvalue('SELECT id FROM bs_company WHERE pnip=:nip AND id!=:idcompany',array(':nip'=>$invoice['pnip'],':idcompany'=>$invoice['idcompany']));
        if ($newCompanyID<=0) return false;

        //no i robimy odpowiednią fakturę zakupową: zwykłą, korektę, pro-formę itd.
        $lType=1; //zakupowa
        $lSubType=0; //zwykła
        if ($invoice['nsubtype']==0) $lSubType=3; //proforma

        $wNID=sql_duplicate_row('bs_invoices',$nid);
        $t2=sql_rows('SELECT id FROM bs_invoices_pr WHERE iddoc=:id',array(':id'=>$nid));
        foreach ($t2 as $row) {
            $wNID2 = sql_duplicate_row('bs_invoices_pr', $row['id']);
            sql_query('UPDATE bs_invoices_pr SET iddoc=:iddoc, idcompany=:idcompany, idbranch=NULL, idowner=NULL, ntype=:ntype, nsubtype=:nsubtype WHERE id=:id',array(':iddoc'=>$wNID,':idcompany'=>$newCompanyID,':ntype'=>$lType,':nsubtype'=>$lSubType,':id'=>$wNID2));
        }

        $e=array();
        $e['idcompany']=z2n($newCompanyID);
        $e['idbranch']=z2n(0);
        $e['idowner']=z2n(0);
        $e['idnodoc']=z2n(0);
        $e['ntype']=$lType;
        $e['nsubtype']=$lSubType;
        $e['idinvinc']=z2n($nid);
        $e['clauto']=1; //oznaczamy, że faktura utworzona "automatycznie"
        sql_loadfromquery($e,'SELECT pname, pstreet, pstreet_n1, ppostcode, ppost, pprovince, pdistrict, pcountry, pnip, pphone1, pemail, sname, sstreet, sstreet_n1, spostcode, spost, sprovince, sdistrict, scountry, snip, sphone1, semail FROM bs_invoices WHERE id=:id',array(':id'=>$invoice['id']),'sname=pname;sstreet=pstreet;sstreet_n1=pstreet_n1;scountry=pcountry;spostcode=ppostcode;sprovince=pprovince;sdistrict=pdistrict;scity=pcity;spost=ppost;snip=pnip;semail=pemail;sphone1=pphone1;pname=sname;pstreet=sstreet;pstreet_n1=sstreet_n1;pcountry=scountry;ppostcode=spostcode;pprovince=sprovince;pdistrict=sdistrict;pcity=scity;ppost=spost;pnip=snip;pemail=semail;pphone1=sphone1');
        $e['kname']='';
        $e['kstreet']='';
        $e['kstreet_n1']='';
        $e['kpostcode']='';
        $e['kpost']='';
        $e['kprovince']='';
        $e['kdistrict']='';
        $e['kcountry']='';
        $e['kphone1']='';

        //jak to jest korekta, szukamy fakturę której ona dotyczy
        if ($invoice['idcorrection']>0) {
            $e['idcorrection']=z2n((int)sql_getvalue('SELECT id FROM bs_invoices WHERE idinvinc=',$invoice['idcorrection'],0));
        }

        //jak w firmie docelowej nie ma kontrahenta o danym NIP-ie, to go dodajemy
        $wNID2=(int)sql_getvalue('SELECT id FROM bs_contractors WHERE pnip<>"" AND pnip=:nip AND idcompany=:idcompany',array(':nip'=>$e['pnip'],':idcompany'=>$newCompanyID),0);
        if ($wNID2<=0) {
            $K2 = (int)sql_getvalue('SELECT id FROM bs_contrtypes WHERE ptype=5');//dostawca
            $e2=array();
            sql_add_standard_fields($e2,0);
            $e2['idtype']=z2n($K2);
            $e2['idcompany']=z2n($newCompanyID);
            sql_loadfromquery($e2,'SELECT pname, pstreet, pstreet_n1, ppostcode, ppost, pprovince, pdistrict, pcountry, pnip, pphone1, pemail FROM bs_company WHERE id=:id',array(':id'=>$invoice['idcompany']), 'pname=pname;pstreet=pstreet;pstreet_n1=pstreet_n1;pcountry=pcountry;ppostcode=ppostcode;pprovince=pprovince;pdistrict=pdistrict;pcity=pcity;ppost=ppost;pnip=pnip;pemail=pemail;pphone1=pphone1;');
            $wNID2 = sql_insert('bs_contractors',$e2);
        }
        $e['pidcontractor']=z2n($wNID2);
        sql_update('bs_invoices',$e,$wNID);

        //================ EUROFAST ================
        if (!empty($invoice['refid'])) {
            //robimy fakturę zakupową, to jej numer musimy dać do zamówienia wychodzącego
            $d=(int)sql_getvalue('SELECT id FROM eurofast_orders WHERE idcompany=:idcompany AND refid=:refid AND ntype=0',array(':refid'=>$invoice['refid'],':idcompany'=>$newCompanyID),0);
             if ($d>0) {
                sql_query('UPDATE eurofast_orders SET ninvoice=:invoice WHERE id=:id',array(':id'=>$d,':invoice'=>$invoice['nnodoc']));
            }
        }
        //==========================================

        return true;
    }


    //wystawienie faktury sprzedaży do zamówienia
    public static function createInvoiceForOrder($lID,$lVType)
    {
        if ($lID<=0) return false;

        $T=sql_row('SELECT * FROM bs_orders WHERE id=',$lID);
        if (!$T) return false;

        if ($T['ntype']!=0) return false; //faktury tylko do przychodzących

        $lType=0;
        $lCompanyID=$T['idcompany'];
        $lBranchID=$T['idbranch'];
        if ($lVType==0) { //standard
            $lN = 'invoice_std';
            $lSubType = 1;
        } else {//proforma
            $lN = 'invoice_pro';
            $lSubType = 0;
        }

        $nDate=date('Y-m-d');

        $system = Model_BSX_System::init();
        $dd = sql_row('SELECT id FROM bs_symbols WHERE pidn=:idn AND idcompany=:idu', array(':idn' => $lN, ':idu' => $lCompanyID));
        if (!$dd) $dd = sql_row('SELECT id FROM bs_symbols WHERE pidn=:idn AND idcompany IS NULL', array(':idn' => $lN));
        $nodoc = $system->getNoDoc($dd['id'], true, $nDate, 'bs_invoices#ntype='.$lType.' AND nsubtype='.$lSubType.' AND (nstatus=2 OR nstatus=3)','mtable=bs_invoices;msymbol=nnodoc;mvsymbol=nvnodoc;mdate='.$nDate.';midsymbol=idnodoc;idcompany='.$lCompanyID);

        $e=array();
        sql_add_standard_fields($e,0);
        $e['idcompany']=z2n($lCompanyID);
        $e['idbranch']=z2n($lBranchID);
        $e['idowner']=z2n($T['idowner']);
        $e['ncalculation']=0;
        $e['ntype']=$lType;
        $e['nsubtype']=$lSubType;

        $e['nnodoc'] = $nodoc['nnodoc'];
        $e['nvnodoc'] = $nodoc['nvnodoc'];
        $e['idnodoc'] = z2n($nodoc['idnodoc']);

        $e['idinvreceipt']=z2n(0);
        $e['nreturned']=0;
        $e['idcorrection']=z2n(0);
        $e['ncorrectiondesc']='';
        $e['nstatus']=2;
        $e['nproforma']=0;
        $e['npaymentform']=$T['npaymentform'];
        $e['npaymentdate']=$T['npaymentdate'];
        $e['ndate_issue']=$nDate;
        $e['nsend']=$nDate;
        $e['nplace']='';
        $e['nperson1']='';
        $e['nperson2']='';
        $e['ncurrency']=$T['ncurrency'];
        $e['ncurrencyrate']=$T['ncurrencyrate'];
        $e['ncurrencydate']=$T['ncurrencydate'];
        $e['nnodocorder']=$T['nnodoc'];
        $e['nperson1']=$T['nperson1'];
        $e['nperson2']=$T['nperson2'];
        $e['nprice']=$T['nprice'];


        $T2=sql_row('SELECT paccount FROM bs_banks WHERE idcompany=:idcompany AND (pcurrency=:currency OR pcurrency is NULL OR pcurrency="") ORDER BY pdefault DESC, pcurrency DESC',array(':idcompany'=>$lCompanyID,':currency'=>$e['ncurrency']));
        if ($T2) {
            $e['nbank']=$T2['paccount'];
        }

        sql_loadfromquery($e,'SELECT pidcontractor, pname, pstreet, pstreet_n1, ppostcode, ppost, pprovince, pdistrict, pcountry, pnip, pphone1, pemail, sname, sstreet, sstreet_n1, spostcode, spost, sprovince, sdistrict, scountry, snip, sphone1, semail FROM bs_orders WHERE id=',$T['id'],'pidcontractor=pidcontractor;pname=pname;pstreet=pstreet;pstreet_n1=pstreet_n1;pcountry=pcountry;ppostcode=ppostcode;pprovince=pprovince;pdistrict=pdistrict;pcity=pcity;ppost=ppost;pnip=pnip;pemail=pemail;pphone1=pphone1;sname=sname;sstreet=sstreet;sstreet_n1=sstreet_n1;scountry=scountry;spostcode=spostcode;sprovince=sprovince;sdistrict=sdistrict;scity=scity;spost=spost;snip=snip;semail=semail;sphone1=sphone1');


        $e['ndpaymentdate']=date('Y-m-d',strtotime($nDate)+30*25*3600);
        $e['ndate_sell']=$nDate;


        if ($e['pcountry']==$e['scountry']) {
            $lRate = 23;
            $r['nsettmethod'] = 0;
        } else {
            $lRate = 0;
            $e['nsettmethod'] = 3;
        }


        $nid=sql_insert('bs_invoices',$e);

        $lSumN=$lSumV=$lSumB=$lRateV=0;
        $lSumQ=0;
        $rows=sql_rows('SELECT * FROM bs_orders_pr WHERE iddoc=',$T['id']);
        foreach ($rows as $T2) {
            $lQuantity = $T2['pquantity'];
            $lPriceN = $T2['psprice_n'];


            $lRateV = (BinUtils::CorrectS2D($lRate) / 100);
            $lPriceV = BinUtils::CorrectD2D($lPriceN * $lRateV);
            $lPriceB = BinUtils::CorrectD2D($lPriceN + $lPriceV);
            $lTotalN = BinUtils::CorrectD2D($lPriceN * $lQuantity);
            $lTotalV = BinUtils::CorrectD2D($lTotalN * $lRateV);
            $lTotalB = BinUtils::CorrectD2D($lTotalN + $lTotalV);

            $lSumN = (double)$lSumN+(double)$lTotalN;
            $lSumQ = (double)$lSumQ+(double)$lQuantity;

            $e = array();
            sql_add_standard_fields($e, 0);
            $e['idcompany'] = z2n($lCompanyID);
            $e['idbranch'] = z2n($lBranchID);
            $e['idowner'] = z2n($T['idowner']);
            $e['iddoc'] = z2n($nid);
            $e['ntype'] = $lType;
            $e['nsubtype'] = $lSubType;
            $e['pscalculation'] = 0;

            $e['pserials'] = $T2['pserials'];
            $e['idproduct'] = z2n($T2['idproduct']);
            $e['ptype'] = $T2['ptype'];
            $e['psymbol'] = $T2['psymbol'];
            $e['pname'] = $T2['pname'];
            $e['punit'] = $T2['punit'];
            $e['pquantity'] = $lQuantity;
            $e['psprice_n'] = $lPriceN;
            $e['psprice_v'] = $lPriceV;
            $e['psprice_b'] = $lPriceB;
            $e['psrate_v'] = $lRate;
            $e['pstotal_n'] = $lTotalN;
            $e['pstotal_v'] = $lTotalV;
            $e['pstotal_b'] = $lTotalB;
            sql_insert('bs_invoices_pr', $e);
        }

        if ($lSumQ<=0) {
            sql_query('DELETE FROM bs_invoices WHERE id=:id',array(':id'=>$nid));
            return false;
        }


        $lSumN=BinUtils::CorrectD2D($lSumN);
        $lSumV=BinUtils::CorrectD2D($lSumN*$lRateV);
        $lSumB=BinUtils::CorrectD2D($lSumN+$lSumV);

        $e=array();
        $e['nstotal_n']=$lSumN;
        $e['nstotal_v']=$lSumV;
        $e['nstotal_b']=$lSumB;
        if (!empty($T['nprice']) && $T['nprice']!='1970-01-01') {
            $e['npaytotal_b']=$e['nstotal_b'];
        }
        sql_update('bs_invoices',$e,$nid);


        return $nid;
    }


    public static function generateInvoicePDF($idinvoice)
    {
        require_once APPPATH.'vendor/fpdf/fpdf'.EXT;
        require_once APPPATH.'vendor/fpdi/fpdi'.EXT;
        //require_once APPPATH.'vendor/barcode/barcode'.EXT;

        if(!function_exists("cv")) {
            function cv($s)
            {
                return iconv('UTF-8', 'windows-1250//TRANSLIT', $s);
            }
        }

        $invoice=sql_row('SELECT * FROM bs_invoices WHERE id=:id',array(':id'=>$idinvoice));
        if (!$invoice) die('Brak faktury!');

        if ($invoice['nsubtype']==0)
        {
            $subtitle='DOKUMENT PROFORMA';
            $title='Proforma '.$invoice['nnodoc'];
            $outFile='Proforma-'.$invoice['id'].'-'.strtotime($invoice['add_time']);
            $outFile.='.pdf';
        } else
        {
            $subtitle='FAKTURA';
            $title='Faktura '.$invoice['nnodoc'];
            $outFile='Faktura-'.$invoice['id'].'-'.strtotime($invoice['add_time']);
            $outFile.='.pdf';
        }

        $pdf = new FPDI();
        $pdf->SetCreator('BSX Generator');
        $pdf->SetAuthor('BinSoft');
        $pdf->SetTitle($title);
        $pdf->SetSubject($title);

        $pageCount = $pdf->setSourceFile('assets/uploads/binsoft/InvTemplate.pdf');
        $templateId = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($templateId);
        $pdf->AddPage('P', array($size['w'], $size['h']));
        $pdf->useTemplate($templateId);

        //$pdf->AddPage();
        $pdf->AddFont('arial_ce','','arial_ce.php');
        $pdf->AddFont('arial_ce','B','arial_ce_b.php');

        $pdf->SetFont('arial_ce', 'B', 10);
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetXY(80, 10);
        $pdf->MultiCell(50,9, cv($subtitle),0,'C',0);

        $pdf->image('assets/uploads/binsoft/logo-binsoft.png',12,13,60);

        $pdf -> SetFont('arial_ce', '', 10);

        $pdf->SetXY(80, 20);
        $pdf->MultiCell(50,8, cv($invoice['nnodoc']),0,'C',0);

        $pdf->SetXY(168, 14);
        $pdf->Write(0, CV($invoice['nnodoc']));

        $pdf -> SetFont('arial_ce', 'B', 10);
        $pdf->SetXY(137, 20);
        $pdf->Write(0, cv('Data wystawienia:'));
        $pdf -> SetFont('arial_ce', '', 10);
        $pdf->SetXY(168, 20);
        $pdf->Write(0, CV($invoice['ndate_issue']));

        $pdf -> SetFont('arial_ce', 'B', 10);
        $pdf->SetXY(137, 27);
        $pdf->Write(0, cv('Data sprzedaży:'));
        $pdf -> SetFont('arial_ce', '', 10);
        $pdf->SetXY(168, 27);
        $pdf->Write(0, CV($invoice['ndate_sell']));

        $pdf -> SetFont('arial_ce', 'B', 10);
        $pdf->SetXY(10, 35.5);
        $pdf->Write(0, cv('Termin płatności:'));
        $pdf -> SetFont('arial_ce', '', 10);
        $pdf->SetXY(42, 35.5);
        $pdf->Write(0, CV($invoice['npaymentdate']));

        $pdf -> SetFont('arial_ce', 'B', 10);
        $pdf->SetXY(70, 35.5);
        $pdf->Write(0, cv('Forma płatności:'));
        $pdf -> SetFont('arial_ce', '', 10);
        $pdf->SetXY(100, 35.5);
        $pdf->Write(0, CV($invoice['npaymentform']));

        $pdf -> SetFont('arial_ce', 'B', 10);
        $pdf->SetXY(70, 42.5);
        $pdf->Write(0, cv('Bank:'));
        $pdf -> SetFont('arial_ce', '', 10);
        $pdf->SetXY(100, 42.5);
        $pdf->Write(0, CV($invoice['nbank']));


        $pdf -> SetFont('arial_ce', 'B', 10);
        $pdf->SetXY(11, 59);
        $pdf->Write(0, cv('Sprzedawca'));
        $pdf->SetXY(108, 59);
        $pdf->Write(0, cv('Nabywca'));
        $pdf -> SetFont('arial_ce', '', 10);

        $sname=$invoice['sname']."\n".$invoice['sstreet'].' '.$invoice['sstreet_n1']."\n".$invoice['spostcode'].' '.$invoice['spost']."\nNIP: ".$invoice['snip'];
        $pname=$invoice['pname']."\n".$invoice['pstreet'].' '.$invoice['pstreet_n1']."\n".$invoice['ppostcode'].' '.$invoice['ppost']."\nNIP: ".$invoice['pnip'];

        $pdf->SetFont('arial_ce', '', 9);
        $pdf->SetXY(11, 63);
        $pdf->MultiCell(91,4.5, cv($sname),0,'L',0);
        $pdf->SetXY(108, 63);
        $pdf->MultiCell(91,4.5, cv($pname),0,'L',0);

        $columns=array(
            array("LP\n ",7,'lp','C'),
            array("Nazwa\n ",89,'pname','L'), //89
            array("Jm\n ",10,'punit','C'),
            array("Ilość\n ",10,'pquantity','R'),
            array("Cena\nnetto",15,'psprice_n','R'),
            array("Wartość\nnetto",15,'pstotal_n','R'),
            array("Stawka\nVAT",13,'psrate_v','C'),
            array("Wartość\nVAT",15,'pstotal_v','R'),
            array("Wartość\nbrutto",15,'pstotal_b','R'),
        );

        $ramka=1;
        $pdf -> SetFont('arial_ce', 'B', 8);
        $y = 99.5;
        $x=10.5;
        $height=5;//5
        $step=5.0;
        foreach ($columns as $column) {
            $pdf->SetXY($x, $y);
            $pdf->MultiCell($column[1], $height, cv($column[0]), $ramka, $column[3], 0);
            $x+=$column[1];
        }

        $pdf -> SetFont('arial_ce', '', 8);
        $y+=$step+5;
        $nlp=1;
        $rows=sql_rows('SELECT * FROM bs_invoices_pr WHERE iddoc=:id',array(':id'=>$invoice['id']));
        $up=array('nstotal_n'=>0,'nstotal_v'=>0,'nstotal_b'=>0);
        foreach ($rows as $lp=>$row) {
            $rate=1+($row['psrate_v']/100);
            $row['psprice_n']=$row['psprice_n'];
            $row['psprice_b']=$row['psprice_n']*$rate;
            $row['psprice_v']=$row['psprice_n']*$rate-$row['psprice_n'];

            $row['pstotal_n']=$row['psprice_n']*$row['pquantity'];
            $row['pstotal_b']=$row['psprice_n']*$row['pquantity']*$rate;
            $row['pstotal_v']=($row['psprice_n']*$row['pquantity']*$rate)-($row['psprice_n']*$row['pquantity']);

            $up['nstotal_n'] += $row['pstotal_n'];
            $up['nstotal_v'] += $row['pstotal_v'];
            $up['nstotal_b'] += $row['pstotal_b'];

            $row['lp']=$nlp++;
            $row['pquantity']=BinUtils::doubleValue($row['pquantity']);
            $row['psprice_n']=BinUtils::price($row['psprice_n']);
            $row['pstotal_n']=BinUtils::price($row['pstotal_n']);
            $row['psrate_v']=BinUtils::price($row['psrate_v']);
            $row['pstotal_v']=BinUtils::price($row['pstotal_v']);
            $row['pstotal_b']=BinUtils::price($row['pstotal_b']);

            if ($row['pserials']!='') {
                $s=explode(';',$row['pserials']);
                if (!empty($s[1])) $row['pname'] .= ' [' . $s[1] . ']';
            }

            $x=10.5;
            foreach ($columns as $column) {
                $pdf->SetXY($x, $y);
                $pdf->Rect($x,$y,$column[1],$height*2);
                $pdf->MultiCell($column[1], $height, cv($row[$column[2]]), 0, $column[3], 0);
                $x+=$column[1];
            }

            $y+=$step*2;
        }

        $invoice['nstotal_n']=$up['nstotal_n'];
        $invoice['nstotal_b']=$up['nstotal_b'];
        $invoice['nstotal_v']=$up['nstotal_v'];
        $invoice['nsratev']='23 %';

        //podsumowanie VAT
        $y=$y+9.7;
        $columns=array(
            array("Netto\n ",20,'nstotal_n','R'),
            array("Stawka\nVAT",15,'nsratev','C'),
            array("Wartość\nVAT",18,'nstotal_v','R'),
            array("Wartość\nbrutto",20,'nstotal_b','R'),
        );

        $invoice['nstotal_n']=BinUtils::price($invoice['nstotal_n']);
        $invoice['nstotal_v']=BinUtils::price($invoice['nstotal_v']);
        $invoice['nstotal_b']=BinUtils::price($invoice['nstotal_b']);


        $pdf -> SetFont('arial_ce', 'B', 8);
        $ramka=1;
        $x=125;
        $height=5;
        $step=5.0;
        foreach ($columns as $column) {
            $pdf->SetXY($x, $y);
            $pdf->MultiCell($column[1], $height, cv($column[0]), $ramka, $column[3], 0);
            $x+=$column[1];
        }
        $y+=$step+5;
        $pdf -> SetFont('arial_ce', '', 8);
        $x=125;
        foreach ($columns as $column) {
            $pdf->SetXY($x, $y);
            $pdf->MultiCell($column[1], $height, cv($invoice[$column[2]]), $ramka, $column[3], 0);
            $x+=$column[1];
        }
        $y+=$step;
        $pdf -> SetFont('arial_ce', 'B', 8);
        $x=125;
        foreach ($columns as $column) {
            $pdf->SetXY($x, $y);
            $pdf->MultiCell($column[1], $height, cv($invoice[$column[2]]), $ramka, $column[3], 0);
            $x+=$column[1];
        }


        //do zapłaty
        $ramka=0;
        $pdf -> SetFont('arial_ce', '', 14);
        $y+=10;
        $pdf->SetXY(11, $y);
        $pdf->MultiCell(80, 10, cv('DO ZAPŁATY: '.BinUtils::price($invoice['nstotal_b']).' zł'), $ramka, 'L', 0);

        if ($invoice['nprice']!='') {
            $pdf->SetFont('arial_ce', 'B', 14);
            $y += 10;
            $pdf->SetXY(11, $y);
            $pdf->MultiCell(80, 10, cv('ZAPŁACONO'), $ramka, 'L', 0);
        }

        $pdf -> SetFont('arial_ce', '', 12);
        $pdf->SetXY(11, 235);
        $pdf->MultiCell(91, 11, cv($invoice['nperson1']), 0, 'C', 0);


        $fld='assets'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'binsoft'.DIRECTORY_SEPARATOR.'invoices';
        if (!is_dir($fld))
        {
            mkdir($fld, 0755, TRUE);
            chmod($fld, 0755);
        }
        $pdf->Output($fld.DIRECTORY_SEPARATOR.$outFile);
        $pdf=null;
        return $fld.DIRECTORY_SEPARATOR.$outFile;

    }
}