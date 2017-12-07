<?php defined('SYSPATH') or die('No direct script access.');

//----------------------- B2B zmiany

class Model_BSX_Account
{

    public static function test_login($controller)
    {
       //jak użytkownik nie zalogowany - to logujemy
       if (empty($_SESSION['login_user']['id']) || $_SESSION['login_user']['id']<=0)
       {
            Header('Location: /account/login');
            exit;
       }
       return true;
    }

    public static function login($username, $password, $remember=false)
    {
        if ($username=='' || $password=='') return -1;
        $row=sql_row('SELECT id, cms_status, pname, idprice, pemail, idparent, b2b_status, idbranch FROM bs_contractors WHERE (pemail = :user OR cms_email = :user OR pnip=:user) AND (cms_pass = :password)',array(':user' => $username,':password' => sha1($password)));

        if ($row) {

            //nie sprawdzamy czy konto bylo aktywowane -od razu logujemy
            // if ($row['cms_status']==0) return -5; 
            // else if ($row['cms_status']!=2) return -6;
            $_SESSION['login_user']=$row;

            //jak opcja zapamiętania, to zapamiętujemy w cookies
            if ($remember)
            {
                $m=time().$row['id'];
                $seed=sha1($m).'bsx'.md5($m);
                $enc = new Encrypt($seed,MCRYPT_MODE_ECB,MCRYPT_BLOWFISH);
                $c1=$enc->encode($username);
                $c2=$enc->encode($password);
                Cookie::set('tsxul',$c1,Date::MONTH);
                Cookie::set('tsxup',$c2,Date::MONTH);
                Cookie::set('tsxus',$m,Date::MONTH);
            }

             return 1;
        } else {
             return -2;
        }
    }

    public static function getCompanyEmployees($companyid) {
        $rows=sql_rows('SELECT id, pname, pemail, b2b_status FROM bs_contractors WHERE idbranch=:idb',array(':idb' => $companyid));
        return $rows; 
    }

    public static function delete($id) {
        sql_query('DELETE FROM bs_contractors WHERE id=:cid ',array(':cid' => $id));
    }

    public static function getUsers($start, $count) {
        if($start<1) $start=1;
        if($count<1) $count=10;
        //nalezy przeprowadzic wyrownanie tak aby start bylo poczatkiem strony jesli rozmair strony to count (np: dla count=10 start to 1, 11, 21, 31 itd - potem)
        if(($start % $count) !== 1) {
            $fl=(int)(floor($start/$count));//floor zwraca float
            $start=(int)($fl*$count)+1;//profilaktyczne rzutowanie na int
        }

        $rows=sql_rows('SELECT id, pname, pemail, b2b_status, idbranch FROM bs_contractors where idparentbr is NULL order by add_time DESC limit :start, :count ',
            array(':start'=>$start-1,':count'=>$count));
        $this_page= ($start%$count)==0? ($start/$count):(floor($start/$count)+1) ;
        $len=sql_row('SELECT count(*) as length FROM bs_contractors where idparentbr is NULL');
        $len=$len['length'];
        $pages_num=($len%$count)==0? ($len/$count):(floor($len/$count)+1);
        return array('users'=>$rows, '_this_page'=>$this_page, '_length'=>$len, '_pages_num'=>$pages_num, 'start'=>$start, 'count'=>$count);
    }

    public static function getCompanies() {
        $rows=sql_rows('SELECT id, pname, pstreet, ppostcode, pcity, pcountry, pphone1, pdistrict, pstreet_n1, ppost, pprovince FROM bs_contractors WHERE idparentbr is not NULL ',array());
        return $rows; 
    }

    public static function getCompany($id) {
        $row=sql_row('SELECT id, pname, pstreet, ppostcode, pcity, pcountry, pphone1, pdistrict, pstreet_n1, ppost, pprovince FROM bs_contractors WHERE idparentbr is not NULL and id=:cid ',array(':cid'=>$id));
        return $row; 
    }

    public static function getUser($id) {
        $row=sql_row('SELECT id, pname, pemail, b2b_status, idbranch FROM bs_contractors WHERE idparentbr is NULL and id=:cid ',array(':cid'=>$id));
        return $row; 
    }

     public static function createCompany($fields) {
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
        $c['idparentbr']  = $fields['idparent'];
        $c['cms_md5'] = md5(time() . $fields['pname']);
        if (!empty(Model_BSX_Core::$bsx_cfg['ptitle'])) $c['pfrom'] = Model_BSX_Core::$bsx_cfg['ptitle'];

        foreach ($fields as $name=>$value) $c[$name]=$value;
        unset($c['idparent']);
        
        $u = sql_insert('bs_contractors', $c);
    }

    public static function updateCompany($id, $f) {    
        unset($f['id']);      
        $row=sql_update('bs_contractors',$f,$id);
        return $row;
    }

    public static function createUser($fields) {
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
    }

    public static function updateUser($id, $f, $setpass=0) {    
        if($setpass>0) {
              $f['cms_pass'] = sha1($f['ppass1']);
        } 
        unset($f['id']);      
        unset($f['setpass']);
        unset($f['ppass1']);
        unset($f['ppass2']);
        $row=sql_update('bs_contractors',$f,$id);
        return $row;
    }

   
    public static function testPermission($type, $id) {
        $row=sql_row('SELECT b2b_status FROM bs_contractors WHERE id=:id',array(':id' => $id));
        if($row) {
            if($type=='admin' && $row['b2b_status']==1) return true;
            else if($type=='admin_br' && $row['b2b_status']==10) return true;
            else if($type=='user' && $row['b2b_status']==20) return true;
            else if($type=='branch' && $row['b2b_status']==30) return true;
            else return false;
        } else {
            return false;
        }
    }

    //łatki: zeby kierownik nie mogl stworrzyc admina

}