<?php defined('SYSPATH') or die('No direct script access.');

class Model_BSX_Admin
{

    public static function test_login($controller)
    {
       //jak jest włączona chmura i nie ma jej wybranej - to do wyboru chmury
       if (isset(Model_BSX_Core::$bsx_cfg['options']['option_bsxcloudselect']) && (Model_BSX_Core::$bsx_cfg['options']['option_bsxcloudselect']==true) && empty($_SESSION['bsxcloud']['nhost']))
       {
           Header('Location: /'.Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/core/cloud');
           exit;
       }
       //jak użytkownik nie zalogowany - to logujemy
       if (empty($_SESSION['admin_user']['id']) || $_SESSION['admin_user']['id']<=0)
       {
            Header('Location: /'.Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/core/login');
            exit;
       }
       //jak blokada ekranu, to do blokady
       if (isset($_SESSION['admin_user']['lock']) && $_SESSION['admin_user']['lock']==true)
       {
           Header('Location: /'.Model_BSX_Core::$bsx_cfg['padmin_prefix'].'/core/lock');
           exit;
       }
       return true;
    }

    public static function login($username, $password, $remember=false)
    {
        if ($username=='' || $password=='') return -1;
        $row=sql_row('SELECT * FROM bs_users WHERE pdel = 0 AND (plogin = :user OR pemail = :user) AND (ppass = :password)',array(':user' => $username,':password' => sha1($password)));

        if ($row) {
            if ($row['pstatus']==10) return -5;
            else if ($row['pstatus']>2) return -6;
            $_SESSION['admin_user']=$row;

            //jak opcja zapamiętania, to zapamiętujemy w cookies
            if ($remember)
            {
                $m=time().$row['id'];
                $seed=sha1($m).'bsx'.md5($m);
                $enc = new Encrypt($seed,MCRYPT_MODE_ECB,MCRYPT_BLOWFISH);
                $c1=$enc->encode($username);
                $c2=$enc->encode($password);
                Cookie::set('bsxal',$c1,Date::MONTH);
                Cookie::set('bsxap',$c2,Date::MONTH);
                Cookie::set('bsxas',$m,Date::MONTH);
            }

            return 1;
        } else {
             return -2;
        }
    }

    public static function cloudSelect($key, $remember=false)
    {
        if ($key=='') return -1;

        $row=sql_row('SELECT * FROM bsw_mpclouds WHERE (fkey = :key)',array(':key' => $key));

        if ($row) {
            $_SESSION['bsxcloud']=$row;
            $_SESSION['bsxcloud']['orgConfig']=Model_BSX_Core::$bsx_cfg;
            //sprawdzenie połączenia z bazą danych
            try {
                Model_BSX_Core::$db = Database::instance('bsxcloud', array('type' => 'PDO', 'connection' => array(
                    'dsn' => 'mysql:host=' . $_SESSION['bsxcloud']['nhost'] . ';dbname=' . $_SESSION['bsxcloud']['ndatabase'],
                    'options' => array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'),
                    'hostname' => $_SESSION['bsxcloud']['nhost'],
                    'database' => $_SESSION['bsxcloud']['ndatabase'],
                    'username' => $_SESSION['bsxcloud']['nlogin'],
                    'password' => $_SESSION['bsxcloud']['npass'],
                    'persistent' => FALSE,
                ), 'table_prefix' => '', 'charset' => 'utf8', 'caching' => FALSE,));

                //jak opcja zapamiętania, to zapamiętujemy w cookies
                if ($remember)
                {
                    $m=time().$row['id'];
                    $seed=sha1($m).'bsx'.md5($m);
                    $enc = new Encrypt($seed,MCRYPT_MODE_ECB,MCRYPT_BLOWFISH);
                    $c=$enc->encode($key);
                    Cookie::set('bsxcloudkey',$c,Date::MONTH);
                    Cookie::set('bsxcloudseed',$m,Date::MONTH);
                }

            } catch (Exception $e) {
                if (isset($_SESSION['bsxcloud'])) unset($_SESSION['bsxcloud']);
                return -3;
            }
            return 1;
        } else {
            return -2;
        }
    }
}