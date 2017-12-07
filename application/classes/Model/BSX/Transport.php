<?php defined('SYSPATH') or die('No direct script access.');

class Model_BSX_Transport
{
    private static $_init = false;


    public static function init()
    {
        if (Model_BSX_Transport::$_init) return Model_BSX_Transport::$_init;

        Model_BSX_Transport::$_init = new Model_BSX_Transport();
        return Model_BSX_Transport::$_init;
    }

    //aktualizacja informacji pomiędzy powiązanymi zleceniami transportowymi i zamówieniami paliwowymi
    /**
     * @param $lID
     * @return bool
     */
    public static function transCorrectRelations($lID)
    {
        $T = sql_row('SELECT id, ideuroorder, idtrans, idopz, idregister, idclo, idbase, idkierowca, idauto, idtransport, idnaczepa, ndate_zal, ndate_term, crcdistance, mapdistance, ndistance, ndistance_plus, tname, tdowod, tphone1, trej, tnaczepa, nbraki, naddresses, pcorrected, pcorrected_type FROM bs_trans WHERE id=:id',$lID);
        if (!$T) return false;

        $lista=array();
        //jak mamy powiązane zamówienie
        if ($T['ideuroorder'] > 0) $lista[]=array($T['ideuroorder'],'eurofast_orders');
        $lRel=sql_getvalue('SELECT idrelord FROM eurofast_orders WHERE id=:id', $T['ideuroorder']);
        while ($lRel > 0) {
            $lista[] = array($lRel, 'eurofast_orders');
            $lRel = sql_getvalue('SELECT idrelord FROM eurofast_orders WHERE id=:id', $lRel);
        }
        $lRel=sql_getvalue('SELECT id FROM eurofast_orders WHERE idrelord=:id', $T['ideuroorder']);
        while ($lRel > 0) {
            $lista[] = array($lRel, 'eurofast_orders');
            $lRel = sql_getvalue('SELECT id FROM eurofast_orders WHERE idrelord=:id', $lRel);
        }

        //a czy mamy powiązane inne zlecenie
        $lRel=$T['idtrans'];
        while ($lRel > 0) {
            $lista[] = array($lRel, 'bs_trans');
            $lRel = sql_getvalue('SELECT idtrans FROM bs_trans WHERE id=:id', $lRel);
        }
        $lRel = sql_getvalue('SELECT id FROM bs_trans WHERE idtrans=:id', $lID);
        while ($lRel > 0) {
            $lista[] = array($lRel, 'bs_trans');
            $lRel = sql_getvalue('SELECT id FROM bs_trans WHERE idtrans=:id', $lRel);
        }

        foreach ($lista as $item) {
            $e = array();
            $e['idopz'] = z2n($T['idopz']);
            $e['idregister'] = z2n($T['idregister']);
            $e['idclo'] = z2n($T['idclo']);
            $e['idbase'] = z2n($T['idbase']);
            $e['idkierowca'] = z2n($T['idkierowca']);
            $e['idauto'] = z2n($T['idauto']);
            $e['idtransport'] = z2n($T['idtransport']);
            $e['idnaczepa'] = z2n($T['idnaczepa']);
            $e['ndate_zal'] = $T['ndate_zal'];
            $e['ndate_term'] = $T['ndate_term'];
            $e['crcdistance'] = $T['crcdistance'];
            $e['mapdistance'] = $T['mapdistance'];
            $e['ndistance'] = $T['ndistance'];
            $e['ndistance_plus'] = $T['ndistance_plus'];
            $e['tname'] = $T['tname'];
            $e['tdowod'] = $T['tdowod'];
            $e['tphone1'] = $T['tphone1'];
            $e['trej'] = $T['trej'];
            $e['tnaczepa'] = $T['tnaczepa'];
            $e['nbraki'] = $T['nbraki'];
            $e['naddresses'] = $T['naddresses'];
            $e['pcorrected'] = $T['pcorrected'];
            $e['pcorrected_type'] = $T['pcorrected_type'];

            sql_update($item[1], $e, $item[0]);
        }
        return true;
    }

    //aktualizacja pozycji na powiązanych dokumentach transportowych i zamówieniach paliwowych
    public static function transCorrectRelationsPR($lID)
    {

        $T=sql_row('SELECT * FROM bs_trans_pr WHERE id=',$lID);
        if (!$T) return false;
        $lista=array();

        $lRel=sql_getvalue('SELECT idrelord FROM bs_trans_pr WHERE id=',$lID, 0);
        while ($lRel > 0) {
            $lista[] = array($lRel, 'bs_trans_pr');
            $lRel = sql_getvalue('SELECT idrelord FROM bs_trans_pr WHERE id=', $lRel, 0);
        }
        $lRel=sql_getvalue('SELECT id FROM bs_trans_pr WHERE idrelord=',$lID, 0);
        while ($lRel > 0) {
            $lista[] = array($lRel, 'bs_trans_pr');
            $lRel = sql_getvalue('SELECT id FROM bs_trans_pr WHERE idrelord=', $lRel, 0);
        }

        foreach ($lista as $item) {
            $e = array();
            $e['rquantity'] = $T['rquantity'];
            $e['wquantity'] = $T['wquantity'];
            $e['xquantity'] = $T['xquantity'];
            sql_update($item[1], $e, $item[0]);
        }


        if ($T['ideuroorderpr'] > 0) {
            $e = array();
            $e['rquantity'] = $T['rquantity'];
            $e['wquantity'] = $T['wquantity'];
            $e['xquantity'] = $T['xquantity'];
            sql_update('eurofast_orders_pr', $e, $T['ideuroorderpr']);

            $lista = array();
            $lRel = sql_getvalue('SELECT idrelord FROM eurofast_orders_pr WHERE id=', $T['ideuroorderpr'], 0);
            while ($lRel > 0) {
                $lista[] = array($lRel, 'eurofast_orders_pr');
                $lRel = sql_getvalue('SELECT idrelord FROM bs_trans_pr WHERE id=', $lRel, 0);
            }
            $lRel = sql_getvalue('SELECT id FROM eurofast_orders_pr WHERE idrelord=', $lID, 0);
            while ($lRel > 0) {
                $lista[] = array($lRel, 'eurofast_orders_pr');
                $lRel = sql_getvalue('SELECT id FROM eurofast_orders_pr WHERE idrelord=', $lRel, 0);
            }

            foreach ($lista as $item) {
                $e = array();
                $e['rquantity'] = $T['rquantity'];
                $e['wquantity'] = $T['wquantity'];
                $e['xquantity'] = $T['xquantity'];
                sql_update($item[1], $e, $item[0]);
            }
        }


        $lID=sql_getvalue('SELECT iddoc FROM bs_trans_pr WHERE id=', $lID, 0);
        Model_BSX_Transport::transRefreshStatDoc($lID);
        Model_BSX_Transport::transCorrectRelations($lID);

        return true;
    }

    //aktualizacja informacji o zawartości zlecenia transportowego
    public static function transRefreshStatDoc($lID) {
        if ($lID<=0) return false;
        $S2='';
        $lBraki=0;
        $rows=sql_rows('SELECT pname, pstreet, pstreet_n1, ppost, pcity, pcountry, xquantity FROM bs_trans_pr WHERE iddoc=',$lID);
        foreach ($rows as $T) {
            $S2 .= $T['pname'] . ', ' . $T['pstreet'] . ' ' . $T['pstreet_n1'] . ', ' . $T['pcity'] . ', ' . $T['pcountry'] . "\n\r";
            $lBraki += $lBraki + (double)$T['xquantity'];
        }
        $e=array();
        $e['naddresses']=$S2;
        $e['nbraki']=$lBraki;
        sql_update('bs_trans',$e,$lID);
        return true;
    }


    //---- wystawienie zlecenia przychodzącego do zlecenia wychodzącego (w powiązanej firmie w tej samej bazie danych)
    public static function transIncomingTransForDoc($nid)
    {
        sql_query('UPDATE bs_trans SET cltest=1 WHERE id=',$nid);
        if (sql_rowexists('bs_trans', 'idtrans=',$nid)) return false;
        $T=sql_row('SELECT id, pnip, snip, idcompany, ntype, idtransfrom, ideuroorder, nstatus FROM bs_trans WHERE id=',$nid);
        if (!$T) return false;

        if ($T['pnip'] == '' || $T['ntype'] != 1 || $T['nstatus']!=1) return false;

        $newCompanyID=(int)sql_getvalue('SELECT id FROM bs_company WHERE pnip=:nip AND id!=:idcompany',array(':nip'=>$T['pnip'],':idcompany'=>$T['idcompany']),0);
        if ($newCompanyID <= 0) return false;

        $wNID=sql_duplicate_row('bs_trans', $nid);
        $rows=sql_rows('SELECT id FROM bs_trans_pr WHERE iddoc=',$nid);
        foreach ($rows as $T2) {
             $wNID2 = sql_duplicate_row('bs_trans_pr', $T2['id']);
             sql_query('UPDATE bs_trans_pr SET iddoc=:iddoc, idrelord=:relid WHERE id=:id',array(':iddoc'=>$wNID,':relid'=>$T2['id'],':id'=>$wNID2));
        }

        $e=array();
        $e['idcompany']=z2n($newCompanyID);
        $e['idbranch']=z2n(0);
        $e['idowner']=z2n(0);
        $e['idnodoc']=z2n(0);
        $e['ntype']=0;
        $e['idfrom']=z2n($T['idcompany']);
        $e['idtrans']=z2n($nid);
        sql_loadfromquery($e,'SELECT pname, pstreet, pstreet_n1, ppostcode, ppost, pprovince, pdistrict, pcountry, pnip, pphone1, pemail, sname, sstreet, sstreet_n1, spostcode, spost, sprovince, sdistrict, scountry, snip, sphone1, semail FROM bs_trans WHERE id=',$T['id'], 'sname=pname;sstreet=pstreet;sstreet_n1=pstreet_n1;scountry=pcountry;spostcode=ppostcode;sprovince=pprovince;sdistrict=pdistrict;scity=pcity;spost=ppost;snip=pnip;semail=pemail;sphone1=pphone1;pname=sname;pstreet=sstreet;pstreet_n1=sstreet_n1;pcountry=scountry;ppostcode=spostcode;pprovince=sprovince;pdistrict=sdistrict;pcity=scity;ppost=spost;pnip=snip;pemail=semail;pphone1=sphone1');
        $e['kname']='';
        $e['kstreet']='';
        $e['kstreet_n1']='';
        $e['kpostcode']='';
        $e['kpost']='';
        $e['kprovince']='';
        $e['kdistrict']='';
        $e['kcountry']='';
        $e['kphone1']='';

        $system = Model_BSX_System::init();
        $dd=sql_row('SELECT id FROM bs_symbols WHERE pidn=:idn AND idcompany=:idu',array(':idn'=>'trans_in',':idu'=>$newCompanyID));
        $nodoc=$system->getNoDoc($dd['id'],true, false, 'bs_trans#ntype=0');

        $e['nnodoc']=$nodoc['nnodoc'];
        $e['nvnodoc']=$nodoc['nvnodoc'];
        $e['idnodoc']=z2n($nodoc['idnodoc']);

        $wNID2=sql_getvalue('SELECT id FROM bs_contractors WHERE pnip!="" AND pnip=:nip AND idcompany=:idcompany',array(':nip'=>$e['pnip'],':idcompany'=>$newCompanyID),0);
        if ($wNID2 <= 0) {
            $K2 = sql_getvalue('SELECT id FROM bs_contrtypes WHERE ptype=4');//odbiorca
            $E2 = array();
            sql_add_standard_fields($E2, 0);
            $E2['idtype'] = $K2;
            $E2['idcompany'] = z2n($newCompanyID);
            sql_loadfromquery($E2,'SELECT * FROM bs_company WHERE id=', $T['idcompany'], 'pname=pname;pstreet=pstreet;pstreet_n1=pstreet_n1;pcountry=pcountry;ppostcode=ppostcode;pprovince=pprovince;pdistrict=pdistrict;pcity=pcity;ppost=ppost;pnip=pnip;pemail=pemail;pphone1=pphone1;');
            $wNID2 = sql_insert('bs_contractors', $E2);
            Model_BSX_System::addToReport('bs_contractors',$wNID2,0,'Kontrahent dodany automatycznie. REL:11, TINID:'.$nid,'Automatycznie dodanie kontrahenta.');
        }

        $e['pidcontractor']=$wNID2;

        $T3=sql_row('SELECT * FROM bs_contractors WHERE id=',$wNID2);
        if ($T3) {
            $S = $T3['iswdcurrency'];
            if ($S == '') $S = 'PLN';
            $e['npricepkm'] = $T3['iswdpricekm'];
            $e['ncurrency'] = $S;
            $e['npricetp']=$T3['iswdpricetp'];
        }
        $rule=Model_BSX_Transport::getRule($T,$newCompanyID);
        if ($rule) {
            $rr=array();
            if ($rule['idopz']>0) {$e['idopz']=z2n($rule['idopz']);$rr[]='idopz|0|IDOPZ|'.$rule['idopz'];}
            if ($rule['idregister']>0) {$e['idregister']=z2n($rule['idregister']);$rr[]='idregister|0|Zarejestrowany odbiorca|'.$rule['idregister'];}
            if ($rule['idbase']>0) {$e['idbase']=z2n($rule['idbase']);$rr[]='idbase|0|Baza dostawcza|'.$rule['idbase'];}
            if ($rule['idclo']>0) {$e['idclo']=z2n($rule['idclo']);$rr[]='idclo|0|Baza celna|'.$rule['idclo'];}
            if ($rule['idtransport']>0) {$e['idtransport']=z2n($rule['idtransport']);$rr[]='idtransport|0|Przewoźnik|'.$rule['idtransport'];}

            if (count($rr)>0) Model_BSX_System::addToReport('bs_trans',$T['id'],0,'Reguła online ID:'.$rule['id'].' (Modyfikacja)',$rr);
        }
        sql_update('bs_trans',$e,$wNID);

        Model_BSX_Transport::transCorrectRelations($wNID);

        if ($rule && $rule['idtransport']>0) {
            Model_BSX_Transport::transCreateZlecenie($wNID);
        }
        return true;

    }


    //--- utworzenie zlecenia transportowego do innego zlecenia transportowego (gdy jest wybrana firma transportowa)
    public static function transCreateZlecenie($lID)
    {
        $T=sql_row('SELECT * FROM bs_trans WHERE id=',$lID);
        if (!$T) Exit;

        if ($T['idtransport'] <= 0) return false;

        if (sql_rowexists('bs_trans', 'idtransfrom=' . $lID)) return false;

        $lNIP=sql_getvalue('SELECT pnip FROM bs_contractors WHERE id=', $T['idtransport'], '');

        $T2=sql_row('SELECT * FROM bs_company WHERE pnip!="" AND pnip=',$lNIP);
        if (!$T2) return false;

        //robimy zlecenie transportowe wychodzące
        $IdTrans=sql_duplicate_row('bs_trans', $lID);
        $rows=sql_rows('SELECT id FROM bs_trans_pr WHERE iddoc=',$lID);
        foreach ($rows as $T3) {
            $wNID2 = sql_duplicate_row('bs_trans_pr', $T3['id']);
            sql_query('UPDATE bs_trans_pr SET iddoc=:idtrans, idrelord=:idrel WHERE id=:id', array(':idtrans' => $IdTrans, ':idrel' => $T3['id'], ':id' => $wNID2));
        }

        $e=array();
        sql_add_standard_fields($e,0);

        $e['nstatus']=1;
        $e['ntype']=1;
        $e['npricepkm']=0;
        $e['idtrans']=z2n($T['id']);
        $e['idtransfrom']=z2n($T['id']);

        $system = Model_BSX_System::init();
        $dd=sql_row('SELECT id FROM bs_symbols WHERE pidn=:idn AND idcompany=:idu',array(':idn'=>'trans_out',':idu'=>$T['idcompany']));
        $nodoc=$system->getNoDoc($dd['id'],true, false, 'bs_trans#ntype=1');

        $e['nnodoc']=$nodoc['nnodoc'];
        $e['nvnodoc']=$nodoc['nvnodoc'];
        $e['idnodoc']=z2n($nodoc['idnodoc']);

        $e['pidcontractor']=z2n($T['idtransport']);
        $e['pname']=$T2['pname'];
        $e['pstreet']=$T2['pstreet'];
        $e['pstreet_n1']=$T2['pstreet_n1'];
        $e['ppostcode']=$T2['ppostcode'];
        $e['ppost']=$T2['ppost'];
        $e['pcity']=$T2['pcity'];
        $e['pprovince']=$T2['pprovince'];
        $e['pdistrict']=$T2['pdistrict'];
        $e['pcountry']=$T2['pcountry'];
        $e['pemail']=$T2['pemail'];
        $e['pnip']=$T2['pnip'];
        $e['pphone1']=$T2['pphone1'];

        sql_update('bs_trans',$e,$IdTrans);

        Model_BSX_Transport::transIncomingTransForDoc($IdTrans);

        return true;

    }


    //----- utworzenie faktury pod zlecenie transportowe -----
    public static function transCreateInvoiceForZlec($lID)
    {
        $T = sql_row('SELECT * FROM bs_trans WHERE id=', $lID);
        if (!$T) return false;

        if ($T['idinvoice'] > 0) return false;
        if ($T['npricepkm'] <= 0) return false;

        $lRyczalt=$T['npricetp'];


        $lType = 0;
        $lSubType = 1;
        $lCompanyID = z2n($T['idcompany']);
        $lBranchID = z2n($T['idbranch']);
        $lN = 'invoice_std';

        $system = Model_BSX_System::init();
        $dd = sql_row('SELECT id FROM bs_symbols WHERE pidn=:idn AND idcompany=:idu', array(':idn' => $lN, ':idu' => $lCompanyID));
        $nodoc = $system->getNoDoc($dd['id'], true, false,  'bs_invoices#' . 'ntype=' . $lType . ' AND nsubtype=' . $lSubType . ' AND (nstatus=2 OR nstatus=3)');

        $e['nnodoc'] = $nodoc['nnodoc'];
        $e['nvnodoc'] = $nodoc['nvnodoc'];
        $e['idnodoc'] = z2n($nodoc['idnodoc']);

        $outNoDoc=$e['nnodoc'];

        $e = array();
        sql_add_standard_fields($e, 0);
        $e['idcompany'] = z2n($lCompanyID);
        $e['idbranch'] = z2n($lBranchID);
        $e['idowner'] = z2n(0);
        $e['ncalculation'] = 0;
        $e['ntype'] = $lType;
        $e['nsubtype'] = $lSubType;
        $e['idinvreceipt'] = z2n(0);
        $e['nreturned'] = 0;
        $e['idcorrection'] = z2n(0);
        $e['ncorrectiondesc'] = '';
        $e['nstatus'] = 2;
        $e['nproforma'] = 0;
        $e['npaymentform'] = 'Przelew';
        $e['npaymentdate'] = '30 dni';
        $e['ndpaymentdate'] = date('Y-m-d', time() + 30 * 24 * 3600);
        $e['ndate_issue'] = date('Y-m-d');
        $e['ndate_sell'] = date('Y-m-d');
        $e['nsend'] = date('Y-m-d');
        $e['nplace'] = '';
        $e['nperson1'] = '';
        $e['nperson2'] = '';
        $e['ncurrency'] = $T['ncurrency'];
        $e['ncurrencyrate'] = 1;
        $e['ncom1'] = 'Numer zlecenia: ' . $T['nnodoc'];
        $e['ncom2'] = '';

        $T2 = sql_row('SELECT paccount FROM bs_banks WHERE idcompany=:idcompany AND (pcurrency=:currency OR pcurrency is NULL OR pcurrency="") ORDER BY pdefault DESC, pcurrency DESC', array(':idcompany' => $lCompanyID, ':currency' => $e['ncurrency']));
        if ($T2) {
            $e['nbank'] = $T2['paccount'];
        }

        sql_loadfromquery($e, 'SELECT pidcontractor, pname, pstreet, pstreet_n1, ppostcode, ppost, pcity, pprovince, pdistrict, pcountry, pnip, pphone1, pemail, sname, sstreet, sstreet_n1, spostcode, spost, scity, sdistrict, sprovince, scountry, snip, sphone1, semail FROM bs_trans WHERE id=' . $T['id'], 'pidcontractor=pidcontractor;pname=pname;pstreet=pstreet;pstreet_n1=pstreet_n1;pcountry=pcountry;ppostcode=ppostcode;pprovince=pprovince;pdistrict=pdistrict;pcity=pcity;ppost=ppost;pnip=pnip;pemail=pemail;pphone1=pphone1;sname=sname;sstreet=sstreet;sstreet_n1=sstreet_n1;scountry=scountry;spostcode=spostcode;sprovince=sprovince;sdistrict=sdistrict;scity=scity;spost=spost;snip=snip;semail=semail;sphone1=sphone1;');

        if ($e['pcountry'] = $e['scountry']) {
            $lRate = '23';
            $e['nsettmethod'] = 0;
        } else {
            $lRate = '0';
            $e['nsettmethod'] = 3;
        }

        if ($lRyczalt<=0) {
               if ($T['ndistance_plus'] > 0) $lQuantity=($T['ndistance_plus']) * 2;
                                        else $lQuantity=($T['ndistance']) * 2;
        } else $lQuantity=1;


        //$lQuantity = ($T['ndistance'] * 2) + $T['ndistance_plus'];
        $lPriceN = $T['npricepkm'];
        $lRateV = 1 + ($lRate / 100);
        $lPriceB = BinUtils::CorrectD2D($lPriceN * $lRateV);
        $lPriceV = BinUtils::CorrectD2D($lPriceN * $lRateV - $lPriceN);
        $lTotalN = BinUtils::CorrectD2D($lPriceN * $lQuantity);
        $lTotalB = BinUtils::CorrectD2D($lPriceN * $lQuantity * $lRateV);
        $lTotalV = BinUtils::CorrectD2D($lPriceN * $lQuantity * $lRateV - $lPriceN * $lQuantity);

        $e['nstotal_n'] = $lTotalN;
        $e['nstotal_v'] = $lTotalV;
        $e['nstotal_b'] = $lTotalB;
        $nid = sql_insert('bs_invoices', $e);


        $e=array();
        sql_add_standard_fields($e,0);
        $e['idcompany']=z2n($lCompanyID);
        $e['idbranch']=z2n($lBranchID);
        $e['idowner']=z2n(0);
        $e['iddoc']=z2n($nid);
        $e['ntype']=$lType;
        $e['nsubtype']=$lSubType;
        $e['pscalculation']=0;

        $e['idproduct']=z2n(0);
        $e['ptype']=1;
        $e['pname']='Usługa transportowa';
        if ($lRyczalt<=0) $e['punit']='km'; else $e['punit']='szt.';
        //$e['punit']='km';
        $e['pquantity']=$lQuantity;
        $e['psprice_n']=$lPriceN;
        $e['psprice_v']=$lPriceV;
        $e['psprice_b']=$lPriceB;
        $e['psrate_v']=$lRate;
        $e['pstotal_n']=$lTotalN;
        $e['pstotal_v']=$lTotalV;
        $e['pstotal_b']=$lTotalB;
        sql_insert('bs_invoices_pr',$e);


        sql_query('UPDATE bs_trans SET idinvoice=:idinvoice, ninvoice=:nodoc WHERE id=:id',array(':idinvoice'=>$nid,':nodoc'=>$outNoDoc,':id'=>$lID));
        if ($T['idtrans'] > 0) sql_query('UPDATE bs_trans SET idinvoice=:idinvoice, ninvoice=:nodoc WHERE id=:id',array(':idinvoice'=>$nid,':nodoc'=>$outNoDoc,':id'=>$T['idtrans']));

        Model_BSX_Invoices::createIncomingInvoiceForDoc($nid);
        return true;
    }


    public static function getRule($T,$idcompany) {
        $nip=array($T['snip'],$T['pnip']);
        $IdRel=$T['idtransfrom'];

        $d=sql_rows('SELECT DISTINCT pproductsymbol FROM bs_trans_pr WHERE iddoc=:iddoc',array(':iddoc'=>$T['id']));
        if (count($d)==1 && $d[0]['pproductsymbol']=='pekodiesel') $type=2;
        else if (count($d)==1 && $d[0]['pproductsymbol']=='psuperplus') $type=1;
        else if (count($d)==1 && $d[0]['pproductsymbol']=='peurosuper') $type=1;
        else if (count($d)>1) $type=3;
        else $type=0;

        while ($IdRel>0) {
            $r=sql_row('SELECT idtransfrom, pnip, snip FROM bs_trans WHERE id=',$IdRel);
            if ($r) {
                $IdRel=(int)$r['idtransfrom'];
                if ($r['snip']!='' && !in_array($r['snip'],$nip)) $nip[]=$r['snip'];
                if ($r['pnip']!='' && !in_array($r['pnip'],$nip)) $nip[]=$r['pnip'];
            } else $IdRel=0;
        }
        $IdRel=$T['ideuroorder'];

        while ($IdRel>0) {
            $r=sql_row('SELECT idrelord, pnip, snip FROM eurofast_orders WHERE id=',$IdRel);
            if ($r) {
                $IdRel=(int)$r['idrelord'];
                if ($r['snip']!='' && !in_array($r['snip'],$nip)) $nip[]=$r['snip'];
                if ($r['pnip']!='' && !in_array($r['pnip'],$nip)) $nip[]=$r['pnip'];
            } else $IdRel=0;
        }

        $rules=sql_rows('SELECT * FROM bs_trans_rl WHERE pstatus=1 AND idcompany=:idc',$idcompany);
        foreach ($rules as $rule) {
            if ($rule['m1']<=0) $rule['m1']=$type;
            
            if ($rule['p1']==0 && $rule['v1']==$T['snip'] && $rule['m1']==$type) return $rule;
            if ($rule['p1']==1 && in_array($rule['v1'], $nip) && $rule['m1']==$type) return $rule;
            if ($rule['p1']==2 && $rule['m1']==$type && sql_row('SELECT id FROM bs_trans_pr WHERE iddoc=:iddoc AND pidcontractor=:pid',array(':iddoc'=>$T['id'],':pid'=>$rule['a1'])))  return $rule;
        }

        return false;
    }

}