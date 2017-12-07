<?php defined('SYSPATH') or die('No direct script access.');


class Model_BSX_B2B
{
    private static $salt='B2B_Application_By_Binsoft';
    private static $count_max=100;//max liczba pobieranych elementow na raz
    private static $user=null;//zalogowany uzytkownik
    private static $admin=false;//prawa admina
    private static $admin_br=false;//prawa kierownika oddzialu
    private static $_user=false;//czy prawa uzytkownika
    private static $uid=0;//id zalogowanego uzytkownika
    private static $pid=0;//id rodzica zalogowanego uzytkownika
    private static $aid=0;//id admina - tzn dla admina to jego id, dla pozostalych to id ich rodzica
    private static $inited=false;


    private static function init() {
        if(self::$inited) return;
        self::$inited=true;

        if(!isset($_SESSION['user_b2b'])) return;
        self::$user=$_SESSION['user_b2b'];
        self::$uid=(int)self::$user->id;
        self::$admin=self::testPermission('admin', self::$uid);
        self::$admin_br=self::testPermission('admin_br', self::$uid);
        self::$_user=self::testPermission('user', self::$uid);
        self::$pid=(int)self::$user->idparent;
        if(self::$admin) self::$aid=self::$uid;
        else self::$aid=self::$pid;
    }

    public static function createUser($fields) {
        //kierownik moze dodawac tylko do wlasnego oddzialu, admin tylko do wlasnych oddzialow
        if(!self::testOnBranchActionPermission($fields['b2b_branch'])) return false;
        $user=$_SESSION['user_b2b'];
        if(self::testPermission('user', $user->id)) return false;

        $system = Model_BSX_System::init();
        $c = array();

        $c['add_id_user'] = $system->user['id'];
        $c['add_time'] = date('Y-m-d H:i:s');
        $c['modyf_id_user'] = $c['add_id_user'];
        $c['modyf_time'] = $c['add_time'];
        $c['idcompany'] = $system->company['id'];
        $c['idowner'] = $system->user['id'];
        $c['idbranch'] = $system->branche['id'];
        $c['cms_status'] = 2;
        $c['idparent'] = (isset($user->idparent) && $user->idparent > 0)? $user->idparent : $user->id;
        $c['cms_idsite'] = Model_BSX_Core::$bsx_cfg['id'];
        $c['cms_datereg'] = date('Y-m-d H:i:s');
        $c['cms_email'] = $fields['pemail'];
        $c['cms_pass'] = sha1($fields['ppass1']);
        $c['cms_md5'] = md5(time() . $fields['pemail']);
        if (!empty(Model_BSX_Core::$bsx_cfg['ptitle'])) $c['pfrom'] = Model_BSX_Core::$bsx_cfg['ptitle'];

        foreach ($fields as $name=>$value) $c[$name]=$value;
        unset($c['id']);
        unset($c['setpass']);
        unset($c['ppass1']);
        unset($c['ppass2']);
        
        $u = sql_insert('bs_contractors', $c);

        return true;
    }

    //update moze zrobic admin dla swojego uzytkownika lub admin odzialu dla oddzialu w jakim jest
    public static function updateUser($id, $f, $setpass=0) { 
        if(!self::testOnUserActionPermission($id)) return false;//czy modyfikowany uzytkownik nalezy do zalogowango uzytkownika
        if(!self::testOnBranchActionPermission($f['b2b_branch'])) return false;//czy modyfikowany uzytkownik bedzie nalezal nadal do odzialu zalogowanego uzytkownika
        $user=$_SESSION['user_b2b'];
        $uid=$user->id;
        if($id == $uid) {//jesli dowolny uzytkownik modyfikuje siebie, to nie moze sobie zmienic pewnych pol 
            $f['b2b_status']=$user->b2b_status;
            $f['b2b_branch']=$user->b2b_branch;
        } 

        if($setpass>0) {
              $f['cms_pass'] = sha1($f['ppass1']);
        } 
        $f['modyf_time'] = date('Y-m-d H:i:s');
        $f['cms_email'] = $f['pemail'];
        unset($f['id']);      
        unset($f['setpass']);
        unset($f['ppass1']);
        unset($f['ppass2']);
        $row=sql_update('bs_contractors',$f,$id);
        return true;
    }

    public static function createBranch($fields) {
        $user=$_SESSION['user_b2b'];
        $system = Model_BSX_System::init();
        $c = array();
        $c['add_id_user'] = $system->user['id'];
        $c['add_time'] = date('Y-m-d H:i:s');
        $c['modyf_id_user'] = $c['add_id_user'];
        $c['modyf_time'] = $c['add_time'];
        $c['idcompany'] = $system->company['id'];
        $c['idowner'] = $system->user['id'];
        $c['cms_status'] = 0;
        $c['cms_idsite'] = Model_BSX_Core::$bsx_cfg['id'];
        $c['cms_datereg'] = date('Y-m-d H:i:s');
        $c['cms_email'] = '';
        $c['b2b_status'] = 9;//9 - to oznaczenie dla oddzialu
        $c['idparentbr']  = $user->id;
        $c['cms_md5'] = md5(time() . $fields['pname']);
        if (!empty(Model_BSX_Core::$bsx_cfg['ptitle'])) $c['pfrom'] = Model_BSX_Core::$bsx_cfg['ptitle'];

        foreach ($fields as $name=>$value) $c[$name]=$value;
        unset($c['id']);
        
        $u = sql_insert('bs_contractors', $c);
        return true;
    }

    public static function updateBranch($id, $f) {   
        if(!self::testOnBranchActionPermission($id)) return false; 
        unset($f['id']);      
        unset($f['idparent']);      
        $f['modyf_time'] = date('Y-m-d H:i:s');
        $row=sql_update('bs_contractors',$f,$id);
        return true;
    }

    //admin: idparent:NULL, idparentbr:NULL, b2b_status:1, b2b_branch:id
    //b2b_status, b2b_branch,  b2b_prefix
    //SELECT id, pname, idparent, idparentbr, pemail FROM bs_contractors;
    //UPDATE bs_contractors SET idparent=2166 WHERE idparent=988
    public static function login($username, $password, $remember=false)
    {
        $res=array('id'=>0);//domyslnie - podano zly login lub haslo
        if ($username=='' || $password=='') $res['id']=-1;//nie podano danych
        else {
            $row=sql_row('SELECT c.id, c.pname, c.pemail, c.cms_status, c.b2b_status, c.b2b_branch, c.idparent, c2.pname as branch_name, c.pcity, c.pnip, c.pstreet, c.ppostcode, c.pcountry, c.pphone1, c.pstreet_n1 FROM bs_contractors c LEFT JOIN bs_contractors c2 ON c2.id=c.b2b_branch WHERE (c.pemail = :user OR c.cms_email = :user) AND (c.cms_pass = :password) and (c.idparentbr is null) ',array(':user' => $username,':password' => sha1($password)));
            if($row) {
                if($row['cms_status']==0) {$res['id']=-2; return $res;} //nieaktywne
                if($row['cms_status']==1) {$res['id']=-3; return $res;} //zablokowane
                if($row['cms_status']!=2) {$res['id']=-4; return $res;}//inny bład

                $res['id']=$row['id'];

                //inicjalizacja admina
                if($row['b2b_status']==1 &&  $row['b2b_branch']!=$row['id'] ) {
                    sql_update('bs_contractors', array('b2b_branch'=>$row['id']), $row['id']);
                }//tzn admin ma oddzielny oddzial dla samego siebie-taki abstrakcyjny

                $res['row']=$row;
                $res['auth']=self::generateToken($row['pname'], $row['pemail']);
                self::createSession($res['auth'], $row, $remember);
            }

        }
        return $res;
    }

    
    public static function getUser($id) {
        if(!self::testOnUserActionPermission($id)) return false;
        $row=sql_row('SELECT c.id, c.pname, c.pemail, c.b2b_status, c.b2b_branch, c.idparent, c2.pname as branch_name, c.pcity, c.pnip, c.pstreet, c.ppostcode, c.pcountry, c.pphone1, c.pstreet_n1 FROM bs_contractors c LEFT JOIN bs_contractors c2 ON (c.b2b_branch=c2.id)  WHERE (c.idparentbr is NULL) and (c.id=:cid) ',array(':cid'=>$id));
        return $row;
    }

    public static function getBranch($id) {
        if(!self::testOnBranchActionPermission($id)) return false; 
        $row=sql_row('SELECT id, pname, pstreet, ppostcode, pcity, pcountry, pphone1, pdistrict, pstreet_n1, ppost, pprovince FROM bs_contractors WHERE idparentbr is not NULL and id=:cid ',array(':cid'=>$id));
        return $row; 
    }

    public static function getUsersByBranch($bid, $start, $count, $search='',$orderby='', $orderbydesc='1') {
        if(!self::testOnBranchActionPermission($bid)) return false; 
        if($bid <=0 ) return false;

        self::paginationBefore($start, $count, $this_page);
        $uid=$_SESSION['user_b2b']->id;
        $rows=sql_rows_ext('c.id, c.pname, c.pemail, c.b2b_status, c2.pname as branch_name',
                'bs_contractors c, bs_contractors c2 WHERE (c.b2b_branch=:bid) and (c.idparentbr is NULL) and (c.b2b_branch=c2.id) and (c.id!=:uid) '.sql_buildwhere(array('c.pname', 'c2.pname','c.pemail','c.b2b_status'),$search).sql_buildorder(array('c.pname','c.pemail','c.b2b_status','c2.pname'),'c.id',$orderby, $orderbydesc),
                array(':bid' => $bid,':start'=>$start-1,':count'=>$count, ':uid'=>$uid),
                $len
                );
        return array('rows'=>$rows, 'pagination'=> self::paginationAfter($start, $count, $this_page, $len, $rows));
    }

    public static function getBranches($start, $count, $search='',$orderby='', $orderbydesc='1') {
        self::paginationBefore($start, $count, $this_page);
        $uid=$_SESSION['user_b2b']->id;
        $rows=sql_rows_ext('id, pname, pstreet, ppostcode, pcity, pcountry, pphone1, pdistrict, pstreet_n1, ppost, pprovince',
            'bs_contractors WHERE idparentbr=:pid '.sql_buildwhere(array('pname', 'pcity', 'ppostcode', 'pstreet','pphone1', 'pstreet_n1'),$search).sql_buildorder(array('pname','pcity',),'id',$orderby, $orderbydesc),
            array(':pid' => $uid,':start'=>$start-1,':count'=>$count),
            $len
        );
        return array('rows'=>$rows, 'pagination'=>self::paginationAfter($start, $count, $this_page, $len, $rows));
    }

    public static function getUsersByParent($start, $count, $search='',$orderby='', $orderbydesc='1') {
        self::paginationBefore($start, $count, $this_page);
        $uid=(int)$_SESSION['user_b2b']->id;    
        $rows=sql_rows_ext('c.id, c.pname, c.pemail, c.b2b_status, c2.pname as branch_name',
               'bs_contractors c LEFT JOIN bs_contractors c2 ON (c.b2b_branch=c2.id) WHERE (c.idparent=:uid)  '.sql_buildwhere(array('c.pname', 'c2.pname', 'c.pemail'),$search).sql_buildorder(array('c.pname','c.pemail','c.b2b_status','c2.pname'),'c.id',$orderby, $orderbydesc),
               array(':uid' => $uid,':start'=>$start-1,':count'=>$count),
               $len
        );
        return array('rows'=>$rows, 'pagination'=> self::paginationAfter($start, $count, $this_page, $len, $rows));
    }

    
    public static function removeUser($id) {
        if(!self::testOnUserActionPermission($id)) return false; //admin moze tylko swoich, admin oddzialu tylko tych z oddzialu gdzie jest
        $uid=(int)$_SESSION['user_b2b']->id;    
        if($uid==$id) return false; //nie mozna usunac siebie
        sql_query('DELETE FROM bs_contractors WHERE id=:cid ',array(':cid' => $id));
        return true;
    }

    public static function removeBranch($id) {
        if(!self::testOnBranchActionPermission($id)) return false;
        sql_query('DELETE FROM bs_contractors WHERE id=:cid ',array(':cid' => $id));
        return true;
    }

    //=================== metody do generowania kluczy

    private static $licenses=array( 
        'mpfirma'       =>array(
            'pseria'=>600, 'pid'=>5,'caption'=>'mpFirma', 
            'prices'=>array(1=>249,2=>419,3=>569,4=>699,5=>809,6=>929,7=>999,8=>1059,9=>1119,10=>1245,11=>1361,12=>1471,13=>1571,14=>1671,15=>1771,16=>1871,17=>1971,18=>2071,19=>2171,20=>2271),
            ),
        'mperp'         =>array(
            'pseria'=>600,'pid'=>5, 'caption'=>'mpFirma ERP', 
            'prices'=>array(1=>399,2=>669,3=>909,4=>1119,5=>1299,6=>1489,7=>1599,8=>1699,9=>1799,10=>1999),
            'options'=>array('visible'=>false)
            ),
        'mpgabinet'     =>array(
            'pseria'=>600,'pid'=>11, 'caption'=>'mpGabinet Lekarski',
            'prices'=>array(1=>249,2=>419,3=>569,4=>699,5=>809,6=>929,7=>999,8=>1059,9=>1119,10=>1245,11=>1361,12=>1471,13=>1571,14=>1671,15=>1771,16=>1871,17=>1971,18=>2071,19=>2171,20=>2271),
             ),
        'mpstomatolog'  =>array(
            'pseria'=>600,'pid'=>12, 'caption'=>'mpStomatolog',
            'prices'=>array(1=>249,2=>419,3=>569,4=>699,5=>809,6=>929,7=>999,8=>1059,9=>1119,10=>1245,11=>1361,12=>1471,13=>1571,14=>1671,15=>1771,16=>1871,17=>1971,18=>2071,19=>2171,20=>2271), 
            ),
        'mpfaktura'     =>array(
            'pseria'=>600,'pid'=>1,'caption'=>'mpFaktura',
            'prices'=>array(1=>149,2=>249,3=>339,4=>419,5=>489,6=>549,7=>599,8=>649,9=>699,10=>749,11=>799,12=>849,13=>899,14=>949,15=>999,16=>1049,17=>1099,18=>1149,19=>1199,20=>1249), 
            ),
        'mpcrm'         =>array(
            'pseria'=>600,'pid'=>3,'caption'=>'mpCRM',
            'prices'=>array(1=>149,2=>249,3=>339,4=>419,5=>489,6=>549,7=>599,8=>649,9=>699,10=>749,11=>799,12=>849,13=>899,14=>949,15=>999,16=>1049,17=>1099,18=>1149,19=>1199,20=>1249), 
            ),
        'mpsekretariat' =>array(
            'pseria'=>600,'pid'=>8,'caption'=>'mpSekretariat',
            'prices'=>array(1=>149,2=>249,3=>339,4=>419,5=>489,6=>549,7=>599,8=>649,9=>699,10=>749,11=>799,12=>849,13=>899,14=>949,15=>999,16=>1049,17=>1099,18=>1149,19=>1199,20=>1249), 
            ),
        'mppos'         =>array(
            'pseria'=>600,'pid'=>15,'caption'=>'mpPOS',
            'prices'=>array(1=>99,2=>149,3=>199,4=>249,5=>299,6=>349,7=>399,8=>449,9=>499,10=>549,11=>599,12=>649,13=>699,14=>749,15=>799,16=>849,17=>899,18=>949,19=>999,20=>1049), 
            ),
        'abcfaktury'    =>array(
            'pseria'=>600,'pid'=>101,'caption'=>'abcFaktury',
            'prices'=>array(1=>49), 
            'days'=>array(365),
            'options'=>array('maxitems'=>1)
            ),
        'abcmagazynu'   =>array(
            'pseria'=>600,'pid'=>102,'caption'=>'abcMagazynu',
            'prices'=>array(1=>49), 
            'days'=>array(365),
            ),
        'abcubezpieczen'=>array(
            'pseria'=>600,'pid'=>103,'caption'=>'abcUbezpieczeń',
            'prices'=>array(1=>49), 
            'days'=>array(365),
            ),
        'abcserwisu'    =>array(
            'pseria'=>600,'pid'=>104,'caption'=>'abcSerwisu i Reklamacji',
            'prices'=>array(1=>49),
            'days'=>array(365),
            ),
        'abcgabinetu'   =>array(
            'pseria'=>600,'pid'=>105,'caption'=>'abcGabinetu',
            'prices'=>array(1=>99), 
            'days'=>array(365),
            ),
        'bsxprinter'    =>array(
            'pseria'=>220,'pid'=>999,'caption'=>'BSX Printer',
            'prices'=>array(1=>149,2=>249,3=>339,4=>419,5=>489,6=>549,7=>599,8=>649,9=>699,10=>749,11=>799,12=>849,13=>899,14=>949,15=>999,16=>1049,17=>1099,18=>1149,19=>1199,20=>1249), 
            ),
        'bsxprinter2'   =>array(
            'pseria'=>600,'pid'=>200,'caption'=>'BSX Printer 2',
            'prices'=>array(500=>149,2500=>249,10000=>349,999999=>449),
            'options'=>array('maxitems'=>1,'limitparagonow'=>true, 'visible'=>true)
            ),
        'bsxcloud'      =>array(
            'pseria'=>600,'pid'=>50,'caption'=>'BSX Cloud',
            'prices'=>array('256 MB'=>159,'512 MB'=>199,'1 GB'=>249,'2 GB'=>299,'3 GB'=>349,'5 GB'=>399),
            'options'=>array('visible'=>false)
            ),
    );  


    public static function getKeys($start, $count, $search='', $orderby='', $orderbydesc='1', $filters=array()) {
        self::init();
        self::paginationBefore($start, $count, $this_page);  
        if(self::$admin) $aid=self::$uid;
        else $aid=self::$pid;
        $filter_where='';
        foreach ($filters as $obj) {
            if (isset($obj['idprogram'])) { 
                $symbol=$obj['idprogram'];
                if(isset(self::$licenses[$symbol])) {
                    $licobj=self::$licenses[$symbol];
                    $filter_where.=' AND s.fpid='.$licobj['pid'].' AND s.fseria='.$licobj['pseria'].' ';
                }

                // foreach (self::$licenses as $licname=>$licobj) {
                //     if ($licname==$obj['idprogram']) {
                //         $filter_where.=' AND s.fpid='.$licobj['pid'].' AND s.fseria='.$licobj['pseria'].' ';
                //         break;
                //     }
                // }
            } else
            if (isset($obj['prepaid']) && $obj['prepaid']==1) {
                $filter_where.=' AND frozdate IS NOT NULL ';
            } else
            if (isset($obj['enddate']) && $obj['enddate']==1) {
                $filter_where.=' AND fdate IS NOT NULL AND  fdate<"'.date('Y-m-d',time()+31*24*3600).'"';
            }
            
        }


        // return array('rows'=>$orderby.'!'.$orderbydesc, 'pagination'=>array());

        $rows=sql_rows_ext('s.id, s.fserial, s.fpid, s.ntrial, s.fdate, s.fitems, s.fstart, s.fdays, s.pname, s.pnip, s.pcity, s.ppostcode, s.pstreet, s.pstreet_n1, s.pcountry, s.b2b_wid, s.wname, c.pname as b2b_wname, s.fprice_n, s.frozdate ',
               'bsw_mpserialsonline s LEFT JOIN  bs_contractors c ON (s.b2b_wid=c.id)  WHERE  (s.widcontractor=:aid) '.$filter_where.sql_buildwhere(array('s.pname','s.pnip', 's.fserial', 's.pcity', 's.pstreet','s.ppostcode'),$search,false).sql_buildorder(array('s.fdate','s.pname','s.frozdate','s.fstart', 's.id'),'s.add_id_user',$orderby, $orderbydesc),
               array(':aid' => $aid,':start'=>$start-1,':count'=>$count),
               $len
        ); // order by s.id DES

        foreach ($rows as &$row) {
            $row['expiration']=0;
            if (empty($row['fdate'])) {
                switch ($row['fdays']) {
                    case 7: $row['sdate']='tydzień'; break;
                    case 31: $row['sdate']='miesiąc'; break;
                    case 62: $row['sdate']='2 miesiące'; break;
                    case 188: $row['sdate']='pół roku'; break;
                    case 365: $row['sdate']='rok'; break;
                    default: $row['sdate']=$row['fdays'].' dni';
                }
            } else {
                $d=strtotime($row['fdate'])-time();
                if ($d<0) $row['expiration']=2;
                else if ($d<14*24*3600) $row['expiration']=1;
                 
                $row['sdate']=$row['fdate'];
            }

            $lic=self::findLicById((int)$row['fpid'], self::$licenses);
            if($lic) $row['caption']=$lic['caption'];
        }

        return array('rows'=>$rows, 'pagination'=> self::paginationAfter($start, $count, $this_page, $len, $rows));
    }

    public static function getKeyById($id) {
        self::init();
        $row=sql_row('SELECT s.id, s.pname, s.pcity, s.ppostcode, s.pcountry, s.pstreet, s.pstreet_n1, s.pemail, s.pnip, s.pphone1, s.fserial, s.ntrial, s.fpid, s.fdate, s.fitems, s.fstart, s.fdays, c.pname as b2b_wname, s.wname, s.fprice_n, s.frozdate, s.pparagonow FROM bsw_mpserialsonline s LEFT JOIN bs_contractors c ON (s.b2b_wid=c.id) WHERE (s.widcontractor=:aid) and (s.id=:id) ', array(':aid' => self::$aid,':id'=>$id));
        if(!$row) return false;

        //pobranie aktualnej nazwy admina
        $a=sql_row('SELECT pname FROM bs_contractors WHERE id=:id', array(':id'=>self::$aid));
        if($a && isset($a['pname'])) $row['wname']=$a['pname'];

        $lic=self::findLicById((int)$row['fpid'], self::$licenses);
        if($lic) $row['caption']=$lic['caption'];

        $row['expiration']=0;
        if (empty($row['fdate'])) {
            switch ($row['fdays']) {
                case 7: $row['sdate']='tydzień'; break;
                case 31: $row['sdate']='miesiąc'; break;
                case 62: $row['sdate']='2 miesiące'; break;
                case 365: $row['sdate']='rok'; break;
                default: $row['sdate']=$row['fdays'].' dni';
            }
        } else {
            $d=strtotime($row['fdate'])-time();
            if ($d<0) $row['expiration']=2;//klucz po terminie
            else if ($d<14*24*3600) $row['expiration']=1;//klucz konczy sie w czasie nie dluzszym niz 2 tyg
             
            $row['sdate']=$row['fdate'];
        }


        return array('row'=>$row);
    }

    public static function getLicenses() {
        $rows=array();
        foreach(self::$licenses as $symbol=>&$lic) {
            if(!isset($lic['options'])) $lic['options']=array();
            if(!isset($lic['options']['visible'])) $lic['options']['visible']=true;
            if(!isset($lic['options']['default_days'])) $lic['options']['default_days']=365;
            if(!isset($lic['options']['default_days_trial'])) $lic['options']['default_days_trial']=188;
            if(!isset($lic['options']['maxitems'])) $lic['options']['maxitems']=999999;
            if(!isset($lic['options']['limitparagonow'])) $lic['options']['limitparagonow']=false;
            if(!isset($lic['days'])) $lic['days']=array(31, 62, 188, 365, 2*365, 5*365);
            if(!isset($lic['options']['max_trial_days'])) $lic['options']['max_trial_days']=188;
            if(!isset($lic['options']['max_days'])) $lic['options']['max_days']=5*365;
            if(!isset($lic['options']['min_trial_days'])) $lic['options']['min_trial_days']=31;
            if(!isset($lic['options']['min_days'])) $lic['options']['min_days']=31;
            if(!isset($lic['days_trial'])) $lic['days_trial']=array(31, 62, 188);
            $lic['symbol']=$symbol;
            
            if($lic['options']['visible']) $rows[]=$lic;
        }
        return array('rows'=>$rows);
    }

    public static function getCredit() {
        self::init();
        $row1=sql_row('SELECT ncredit, ndiscount from bs_contractors where id=:aid', array(':aid'=>self::$aid));
        $row2=sql_row('SELECT SUM(fprice_n) as price_sum FROM bsw_mpserialsonline WHERE widcontractor=:aid AND ntrial!=1 AND frozdate IS NULL', array(':aid'=>self::$aid));
        return array('row'=>array('ncredit'=>$row1['ncredit'], 'ndiscount'=>$row1['ndiscount'], 'price_sum'=>$row2['price_sum']));
    }

    public static function getArticle($w) {
        self::init();
        
         $cms=Model_BSX_CMS::init();
         $row=$cms->getArticleByModrewrite($w[1],'',NULL);
         //if (!$p) throw new HTTP_Exception_404(':file does not exist!', array(':file' => $w[0]));

        //$row=sql_row('SELECT id, pbody, ptitle, pauthor FROM bsc_articles WHERE pmodrewrite=:modrewrite', array(':modrewrite'=>$w[1]));
        //$row['pbody']=Model_BSX_Core::parseText($row['pbody']);
        return $row;
    }

    public static function getTemplates() {
        self::init();
        $rows=sql_rows('SELECT id, pbody, ptitle, ptype FROM b2b_templates WHERE widcontractor=:wid', array(':wid'=>self::$aid));

        if(count($rows)==0) {
            //inicjalizacja szablonów, jesli nie ma zadnych
            $a=array('pbody'=>"Dzień dobry, \n\nProgram: {name} \nKlucz: {fserial}\nWażność: {expiry}\n\nWiadomość wysłana automatycznie.", 'ptitle'=>'Klucz do  programu {name}', 'ptype'=>0, 'widcontractor'=>self::$aid);//ptype==0 - szablon email
            $a['id']=sql_insert('b2b_templates', $a);
            $b=array('ptitle'=>'Klucz do  programu {name}', 'pbody'=>"<div align=\"center\">\n\n<h1>{name}</h1>\n\n<h2>Klucz: <b >{fserial}</b></h2>\n<h2>Ważność: <b>{expiry}</b></h2>\n\n</div>", 'ptype'=>1, 'widcontractor'=>self::$aid);//ptype==0 - szablon email
            $b['id']=sql_insert('b2b_templates', $b);
            $rows=array($a,$b);
        }
        return $rows;
    }

     public static function getTemplateById($id) {
        self::init();
        $row=sql_row('SELECT id, pbody, ptitle, ptype FROM b2b_templates WHERE (widcontractor=:wid) and id=:id', array(':wid'=>self::$aid, ':id'=>$id));
        return $row;
    }

    public static function updateTemplate($id, $f) {
        self::init();
        sql_update('b2b_templates', $f, $id);
        return true;
    }

     public static function sendKey($id) {
        self::init();
        $row=sql_row('SELECT * FROM bsw_mpserialsonline WHERE (widcontractor=:wid) and id=:id', array(':wid'=>self::$aid, ':id'=>$id));
        if(!$row) return -1;
        $tmpl=sql_row('SELECT * FROM b2b_templates WHERE ptype=0 and (widcontractor=:wid)',  array(':wid'=>self::$aid));
        if(!$tmpl) return -2;
        
        $msg=$tmpl['pbody'];
        $title=$tmpl['ptitle'];
        $email=$row['pemail'];

        if (empty($email)) return -3;

        foreach($row as $name=>$value) {
            $title=str_ireplace('{'.$name.'}', $value, $title);
            $msg=str_ireplace('{'.$name.'}', $value, $msg);
        }
        foreach(self::$licenses as $value) {
            if (isset($value['pseria']) && isset($value['pid']) && $value['pseria']==$row['fseria'] && $value['pid']==$row['fpid']) {
                $title=str_ireplace('{name}', $value['caption'], $title);
                $msg=str_ireplace('{name}', $value['caption'], $msg);               
            }
        }

        if (!empty($row['fdate'])) $expiry=$row['fdate'];
        else $expiry=$row['fdays'].' dni';

        $title=str_ireplace('{expiry}', $expiry, $title);
        $msg=str_ireplace('{expiry}', $expiry, $msg);    

        $m=sql_row('SELECT pemail FROM bs_contractors WHERE id=:id',array(':id'=>self::$aid));
        if ($m) $mailfrom=$m['pemail']; else $mailfrom='sprzedaz@binsoft.pl';

        $r = Email::factory()
                        ->subject($title)
                        ->to($email)
                        ->from($mailfrom)
                        ->message($msg)
                        ->send();

        return 0;
    }


     public static function printKey($id, $format='') {
        self::init();
        $row=sql_row('SELECT * FROM bsw_mpserialsonline WHERE (widcontractor=:wid) and id=:id', array(':wid'=>self::$aid, ':id'=>$id));
        if(!$row) return -1;
        $tmpl=sql_row('SELECT * FROM b2b_templates WHERE ptype=1 and (widcontractor=:wid)',  array(':wid'=>self::$aid));
        if(!$tmpl) return -2;
        
        $msg=$tmpl['pbody'];
        $title=$tmpl['ptitle'];

        foreach($row as $name=>$value) {
            $title=str_ireplace('{'.$name.'}', $value, $title);
            $msg=str_ireplace('{'.$name.'}', $value, $msg);
        }
        foreach(self::$licenses as $value) {
            if (isset($value['pseria']) && isset($value['pid']) && $value['pseria']==$row['fseria'] && $value['pid']==$row['fpid']) {
                $title=str_ireplace('{name}', $value['caption'], $title);
                $msg=str_ireplace('{name}', $value['caption'], $msg);  
                break;             
            }
        }

        if (!empty($row['fdate'])) $expiry=$row['fdate'];
        else $expiry=$row['fdays'].' dni';

        $title=str_ireplace('{expiry}', $expiry, $title);
        $msg=str_ireplace('{expiry}', $expiry, $msg);    

        if ($format=='html') {
            echo '<html><head><title>'.$title.'</title><style type="text/css">body {font-family:arial;font-size:12px;}</style></head><body>'.$msg.' <script>window.print();</script></body></html>';
            exit;
        }


        return array('title'=>$title,'body'=>$msg );
    }

   
    public static function createKey($f) {
        self::init();
        $lic=self::getLicenses(); 
        $lic=$lic['rows'];
        $this_lic=self::findLic($f['symbol'], $lic);

        if (!$this_lic) return false;
        if(!isset($this_lic['prices'][$f['fitems']])) return false;

        $system = Model_BSX_System::init();
        $c = array();
        foreach ($f as $name=>$value) $c[$name]=$value;

        $c['add_id_user'] = $system->user['id'];
        $c['add_time'] = date('Y-m-d H:i:s');
        $c['modyf_id_user'] = $c['add_id_user'];
        $c['modyf_time'] = $c['add_time'];

        $c['fserial']=self::generateKey();
        $c['fpid']=$this_lic['pid'];
        $c['fseria']=$this_lic['pseria'];
        $c['ftype']=2;
        $c['fstatus']=0;
        $c['widcontractor']=self::$aid;
        $c['b2b_wid']=self::$uid;
        if ($this_lic['options']['limitparagonow']) {
            $c['pparagonow']=$c['fitems'];
            $c['fitems']=1;
            $base_price=$this_lic['prices'][$c['pparagonow']];//w else jest to samo  tylko odnosnie innej zmiennej, ale ma to przypominac ze jest takie rozróżnienie
        } else {
            $base_price=$this_lic['prices'][$c['fitems']];
        }
        //walidacja liczby dni
        if($c['ntrial']!=0) { //wersja testowa
            if($c['fdays'] > $this_lic['options']['max_trial_days']) $c['fdays']=$this_lic['options']['max_trial_days'];
            else if($c['fdays'] < $this_lic['options']['min_trial_days']) $c['fdays']=$this_lic['options']['min_trial_days'];
        } else { //wersja płatna
            if($c['fdays'] > $this_lic['options']['max_days']) $c['fdays']=$this_lic['options']['max_days'];
            else if($c['fdays'] < $this_lic['options']['min_days']) $c['fdays']=$this_lic['options']['min_days'];
        }
        //pobranie danych firmy
        sql_loadfromquery($c,'SELECT * FROM bs_contractors WHERE id=:id',array(':id'=>$c['widcontractor']),'wname=pname;wstreet=pstreet;wstreet_n1=pstreet_n1;wcity=pcity;wpost=ppost;wcountry=pcountry;wprovince=pprovince;wdistrict=pdistrict;wemail=pemail;wphone1=pphone1;ndiscount;ncredit;');
        //wyliczanie ceny
        $price=self::calcPrice($base_price, $c['ndiscount'], $c['fdays'], $c['ncredit'], $c['ntrial']);
        if($price < 0) return false; //przekroczono kredyt kupiecki - nie mozna utworzyc klucza
        $c['fprice_n']=$price;

        unset($c['id']);
        unset($c['symbol']);
        unset($c['ndiscount']);
        unset($c['ncredit']);
        sql_insert('bsw_mpserialsonline', $c);
        return true;
    }


    public static function updateKey($f) {
        $system = Model_BSX_System::init();
        $c = array();
        foreach ($f as $name=>$value) $c[$name]=$value;
        $c['modyf_id_user'] = $system->user['id'];
        $c['modyf_time'] = date('Y-m-d H:i:s');
        $id=$c['id'];
        unset($c['id']);
        unset($c['symbol']);
        unset($c['fitems']);
        unset($c['fdays']);
        unset($c['ntrial']);
        sql_update('bsw_mpserialsonline',$c,$id);
        return true;
    }

   
    public static function renewKey($f) {
        self::init();
        $k=sql_row('SELECT fpid, pparagonow, fitems, frozdate, fstart, fdate, ntrial FROM bsw_mpserialsonline where (widcontractor=:aid) and (id=:id)', array(':aid'=>self::$aid, ':id'=>$f['id']));
        $a=sql_row('SELECT ncredit, ndiscount FROM bs_contractors WHERE id=:aid', array(':aid'=>self::$aid));
        if($k['ntrial']==1) return -1;//nie mozna przdluzac kluczy w wersji testowej
        else if (is_null($k['frozdate'])) return -8; //nie mozna przedluzac nie opłaconych kluczy
        else if (is_null($k['fstart'])) return -3; //nie mozna przedluzac nie aktywowanych kluczy
        else if (is_null($k['fdate'])) return -9; //nie mozna przedluzac 
        
        $lic=self::getLicenses(); 
        $lic=$lic['rows'];
        $this_lic=self::findLicById($k['fpid'], $lic);
        if (!$this_lic) return -4;//błąd, jesli program nie jest w liscie dostepnych programów

        //limit paragonow
        if ($this_lic['options']['limitparagonow']) {
            if(!isset($this_lic['prices'][$k['pparagonow']])) return -5;//błąd, jesli wybrana liczba paragonow nie jest dostepna w akutalnej ofercie
            $base_price=$this_lic['prices'][$k['pparagonow']]; 
        }
        else { 
            if(!isset($this_lic['prices'][$k['fitems']])) return -6;//błąd, jesli wybrana liczba stanowisk nie jest dostepna w akutalnej ofercie
            $base_price=$this_lic['prices'][$k['fitems']];
        }

        //walidacja liczby dni
        if($f['fdays'] > $this_lic['options']['max_days']) $f['fdays']=$this_lic['options']['max_days'];
        else if($f['fdays'] < $this_lic['options']['min_days']) $f['fdays']=$this_lic['options']['min_days'];

        $price=self::calcPrice($base_price, $a['ndiscount'], $f['fdays'], $a['ncredit'], 0);
        if($price < 0) return -7; //przekroczono kredyt kupiecki - nie mozna utworzyc klucza

        $system = Model_BSX_System::init();
        $r=array();
        $r['fprice_n']=$price;
        $r['modyf_id_user'] = $system->user['id'];
        $r['modyf_time'] = date('Y-m-d H:i:s');
        $r['fdays']=$f['fdays'];
        $r['b2b_wid']=self::$uid;
        $r['fdate']=date('Y-m-d',strtotime($k['fdate'])+$f['fdays']*3600*24);

        sql_update('bsw_mpserialsonline', $r, $f['id']);
        sql_query('UPDATE bsw_mpserialsonline SET frozdate=NULL WHERE id=:id', array(':id'=>$f['id']));

        return 0;
    }

    public static function removeKey($id) {
        if(!sql_row('SELECT id FROM bsw_mpserialsonline WHERE (id=:id)  AND (fstart is NULL) AND (frozdate is NULL) ', array(':id'=>$id))) return false;
        sql_query('DELETE FROM bsw_mpserialsonline WHERE id=:id ',array(':id' => $id));
        return true;
    }




    
    //===================

    //wyliczanie ceny klucza
    private static function calcPrice($b, $p, $d, $c, $trial) {//cena bazowa, rabat, dni, kredyt kupiecki ; jesli zwraca ujemna liczbe to przekroczono kwote kredytu
        self::init();
        $sum=sql_row('SELECT SUM(fprice_n) as price_sum FROM bsw_mpserialsonline WHERE widcontractor=:aid AND ntrial!=1 AND frozdate IS NULL', array(':aid'=>self::$aid));
        $sum=$sum['price_sum'];

        if($trial==1) return 0; //klucz testowy

        $p=$p/100; $dd=$d/365;
        $price=($b-($b*$p))*$dd;
        if($d < 365) $price*= 1.1;
        else if ($d >= 2*365 && $d < 4*365 ) $price*=0.9;
        else if ($d >= 4*365) $price*=0.8;

        if(($price+$sum) > $c) return -1; //przekroczono kwote kredytu kupieckiego
        return $price;
        
    }

    private static function findLic($symbol, $lic) {    
        foreach($lic as $l) {
            if($l['symbol']==$symbol) return $l;
        }
        return false;
    }

    private static function findLicById($id, $lic) {    
        foreach($lic as $l) {
            if($l['pid']==$id) return $l;
        }
        return false;
    }

    private static function generateKey() {
        self::init();
        $template='0123456789ABCDEF';
        $prefix=sql_getvalue('SELECT b2b_prefix from bs_contractors where id=:aid ', array(':aid'=>self::$aid),'');
        if (empty($prefix)) $prefix='MP';
        
        do {
            $key=$prefix.'-';
            while (strlen($key)<11+strlen($prefix)) $key.=$template[rand(0,strlen($template)-1)];
        } while (sql_row('SELECT id FROM bsw_mpserialsonline WHERE fserial=:key',array(':key'=>$key)));

        return $key;
    }


    private static function paginationBefore(&$start, &$count, &$this_page) {
        if($start<1) $start=1;
        if($count<1) $count=10;
        if($count > self::$count_max) $count=self::$count_max;
        //nalezy przeprowadzic wyrownanie tak aby start bylo poczatkiem strony jesli rozmair strony to count (np: dla count=10 start to 1, 11, 21, 31 itd - potem)
        if(($start % $count) !== 1) {
            $fl=(int)(floor($start/$count));//floor zwraca float
            $start=(int)($fl*$count)+1;//profilaktyczne rzutowanie na int
        }
        $this_page= ($start%$count)==0? ($start/$count):(floor($start/$count)+1);
    }

    private static function paginationAfter($start, $count, $this_page, $len, $rows) {
        // if (isset($len['length'])) $len=$len['length'];
        $pages_num=($len%$count)==0? ($len/$count):(floor($len/$count)+1);
        return array('this_page'=>$this_page, 'this_length'=>count($rows), 'all_length'=>$len, 'pages_num'=>$pages_num, 'start'=>$start, 'count'=>$count);
    }

    private static function testOnUserActionPermission($id) {
        $user=$_SESSION['user_b2b'];
        $uid=(int)$user->id;  
        if($uid==$id) return true;
        $b2b_branch=(int)$user->b2b_branch;
        $admin_br=self::testPermission('admin_br', $uid); 
        $admin=self::testPermission('admin', $uid); 
        $_user=self::testPermission('user', $uid);
        $user_modyf=sql_row('SELECT b2b_branch, idparent FROM bs_contractors WHERE idparentbr is NULL and id=:cid ',array(':cid'=>$id));
        if(!$user_modyf) return false;
        if($admin_br && $user_modyf['b2b_branch']!=$b2b_branch) return false; //kierownik oddzialu moze tylko we wlasnym oddziale
        else if($admin && $user_modyf['idparent']!=$uid) return false; //admin moze tylko swoich uzytkownikow
        else if($_user && $uid !== $id) return false; //uzytkownik moze tylko na sobie
        else return true;
    }

    private static function testOnBranchActionPermission($id) {
        $user=$_SESSION['user_b2b'];
        $uid=(int)$user->id;  
        $b2b_branch=(int)$user->b2b_branch;
        $admin=self::testPermission('admin', $uid); 
        if($admin && $id==$uid) return true; //jesli admin modyfkuje siebie, to $id oddzialu ma ustawione na swoje id
        $admin_br=self::testPermission('admin_br', $uid); 
        $_user=self::testPermission('user', $uid);
        $branch_modyf=sql_row('SELECT idparentbr FROM bs_contractors WHERE idparentbr is not NULL and id=:cid ',array(':cid'=>$id));
        if(!$branch_modyf) return false;
        if($admin && $branch_modyf['idparentbr']!=$uid) return false; //admin moze tylko swoje oddzialy
        else if($admin_br && $b2b_branch != $id) return false; //admin oddzialu moze tylko swoj oddzial tj tam gdzie zostal przypisany
        else if($_user && $b2b_branch != $id) return false; //uzytkownik moze tylko swoj oddzial przypisac
        else return true;
    }

    public static function testPermission($type, $id) {
        $row=sql_row('SELECT b2b_status FROM bs_contractors WHERE id=:id',array(':id' => $id));
        if($row) {
            if($type=='admin' && $row['b2b_status']==1) return true;
            else if($type=='admin_br' && $row['b2b_status']==10) return true;
            else if($type=='user' && $row['b2b_status']==20) return true;
            else if($type=='branch' && $row['b2b_status']==9) return true;
            else return false;
        } else {
            return false;
        }
    }

    private static $session_path='application/session/';

    private static function generateToken($pname, $pemail) {
        $token=sha1($pname.$pemail.self::$salt.time());
        return $token;
    }


    //=====================================

    private static function createSession($token, $user, $remember=false) {
        // $file=self::$session_path.$token.'.txt';
        // if(!file_exists(self::$session_path)) {
        //     mkdir(self::$session_path, 0755, true);
        // }
        // if(!file_exists($file)) {
        //     file_put_contents($file, json_encode(array('user'=>$user)));
        // }        
        $w=array();
        $w['b2b_auth']=$token;
        $w['b2b_idu']=$user['id'];
        if($remember) $w['b2b_expiry']=14*24*3600; //w sekundach
        else $w['b2b_expiry']=30*60; //w sekundach
        $w['b2b_data']=json_encode(array('user'=>$user));
        $id=sql_insert('b2b_sessions',$w);
        sql_query('UPDATE b2b_sessions SET b2b_last=UNIX_TIMESTAMP() WHERE b2b_auth=:token',array(':token'=>$token));

    }

    public static function deleteSession($token) {
        // $file=self::$session_path.$token.'.txt';
        // if(file_exists($file)) {
        //     unlink($file);
        //     return true;
        // }
        // return false;

        sql_query('DELETE FROM b2b_sessions WHERE b2b_auth=:token',array(':token'=>$token));
        return false;
    }

    public static function getSession($token) {
        // $file=self::$session_path.$token.'.txt';
        // if(file_exists($file)) {
        //     return json_decode(file_get_contents($file));
        // } else return false;
        sql_query('DELETE FROM b2b_sessions WHERE b2b_last<UNIX_TIMESTAMP()-b2b_expiry');

        $w=sql_row('SELECT b2b_data FROM b2b_sessions WHERE b2b_auth=:token',array(':token'=>$token));
        if ($w) {
            sql_query('UPDATE b2b_sessions SET b2b_last=UNIX_TIMESTAMP() WHERE b2b_auth=:token',array(':token'=>$token));
            return json_decode($w['b2b_data']);
        } else return false;
    }

    public static function getLoggedUser($token) {
        $is_logged=false;
        $u=array();

        $c=self::getSession($token);
        if ($c && isset($c->user)) {
            $u=$c->user;
            $is_logged=true;
         }
         $_SESSION['user_b2b']=$u;
         $_SESSION['b2b_logged']=$is_logged;
         return $c;

        // $file=self::$session_path.$token.'.txt';
        // $is_logged=false;
        // $u=array();
        // if($file!='.txt' && file_exists($file)) {
        //     $c=json_decode(file_get_contents($file));
        //     if(isset($c->user)) {
        //         $u=$c->user;
        //         $is_logged=true;
        //     }
        // } 
        // $_SESSION['user_b2b']=$u;
        // $_SESSION['b2b_logged']=$is_logged;

    }

    public static function writeSession($token, $obj) {
        sql_query('UPDATE b2b_sessions SET b2b_data=:data WHERE b2b_auth=:token',array(':data'=>json_encode($obj),':token'=>$token));
        return true;
        // $file=self::$session_path.$token.'.txt';
        // if(file_exists($file)) {
        //     file_put_contents($file, json_encode($obj));
        //     return true;
        // } 
        // return false;
    }

    


}
