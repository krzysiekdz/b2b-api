<?php defined('SYSPATH') or die('No direct script access.');

class Model_BSX_Reseller
{

    public static function test_login($controller)
    {
       //jak jest włączona chmura i nie ma jej wybranej - to do wyboru chmury
       if (isset(Model_BSX_Core::$bsx_cfg['options']['option_bsxcloudselect']) && (Model_BSX_Core::$bsx_cfg['options']['option_bsxcloudselect']==true) && empty($_SESSION['bsxcloud']['nhost']))
       {
           header('Location: /'.Model_BSX_Core::$bsx_cfg['preseller_prefix'].'/core/cloud');
           exit;
       }
       //jak użytkownik nie zalogowany - to logujemy
       if (empty($_SESSION['reseller_user']['id']) || $_SESSION['reseller_user']['id']<=0)
       {
            header('Location: /'.Model_BSX_Core::$bsx_cfg['preseller_prefix'].'/core/login');
            exit;
       }
       //jak blokada ekranu, to do blokady
       if (isset($_SESSION['reseller_user']['lock']) && $_SESSION['reseller_user']['lock']==true)
       {
           header('Location: /'.Model_BSX_Core::$bsx_cfg['preseller_prefix'].'/core/lock');
           exit;
       }
       return true;
    }

    public static function login($username, $password, $remember=false)
    {
        if ($username=='' || $password=='') return -1;
        $row=sql_row('SELECT * FROM bs_users WHERE pdel = 0  AND (plogin = :user OR pemail = :user) AND (ppass = :password)',array(':user' => $username,':password' => sha1($password)));

        if ($row) {
             if ($row['pstatus']==10) return -5;
             else if ($row['pstatus']!=7 && $row['pstatus']!=8 && $row['pstatus']!=9) return -6;//reseller/spedycja/ZOWA
             $_SESSION['reseller_user']=$row;

            //jak opcja zapamiętania, to zapamiętujemy w cookies
            if ($remember)
            {
                $m=time().$row['id'];
                $seed=sha1($m).'bsx'.md5($m);
                $enc = new Encrypt($seed,MCRYPT_MODE_ECB,MCRYPT_BLOWFISH);
                $c1=$enc->encode($username);
                $c2=$enc->encode($password);
                Cookie::set('bsxrl',$c1,Date::MONTH);
                Cookie::set('bsxrp',$c2,Date::MONTH);
                Cookie::set('bsxrs',$m,Date::MONTH);
            }

             return 1;
        } else {
             return -2;
        }
    }

}