<?php defined('SYSPATH') or die('No direct script access.');

require APPPATH.'classes/class.BinMemCached'.EXT;
require APPPATH.'classes/class.BinImages'.EXT;


class Model_BSX_Core
{
    public static $bsx_config=false;            //pełny plik konfiguracyjny (obiekt)
    public static $bsx_cfg   =false;            //podpięta gałąź z pliku konfiguracyjnego opisująca  daną konkretną stronę WWW
    public static $redirectToControler = false; //czy było już przekierowanie w poszukiwaniu kontrolera
    public static $variables =array();          //zmienne globalne
    public static $subdomain ='';               //subdomena
    public static $db;                          //uchwyt do bazy danych
    public static $_TPL_;
    public static $skinOptions=array();        //opcje związane z aktualną skrótką (nadawane indywidualnie dla danej strony przez skórkę)
    public static $_MONTHS=array('','Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec','Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień');

    public static $support_imagick=false;

    private static $_init   = false;            //znacznik, czy strona została zainicjowana, czy nie

    public static $cache = false;

    public static function reconnectDB() {
        $host=$_SERVER['HTTP_HOST'];
        //jak jest w sesji bsxcloud - znaczy jest informacja, z której bazy danych mamy korzystać
        if (!empty($_SESSION['bsxcloud']['fkey']))
        { //
            try {
                Model_BSX_Core::$db = Database::instance('bsxcloud', array('type'=>'PDO','connection' => array(
                    'dsn' => 'mysql:host=' . $_SESSION['bsxcloud']['nhost'] . ';dbname=' . $_SESSION['bsxcloud']['ndatabase'],
                    'options' => array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'),
                    'hostname' => $_SESSION['bsxcloud']['nhost'],
                    'database' => $_SESSION['bsxcloud']['ndatabase'],
                    'username' => $_SESSION['bsxcloud']['nlogin'],
                    'password' => $_SESSION['bsxcloud']['npass'],
                    'persistent' => FALSE,
                ), 'table_prefix' => '', 'charset' => 'utf8', 'caching' => FALSE,));
                if (isset($_SESSION['bsxcloud']['orgConfig']))
                {
                    Model_BSX_Core::$bsx_config['sites']=Arr::merge(array($_SESSION['bsxcloud']['orgConfig']['phost']=>$_SESSION['bsxcloud']['orgConfig']),Model_BSX_Core::$bsx_config['sites']);
                }
            } catch (Exception $e) {
                if (isset($_SESSION['bsxcloud'])) unset($_SESSION['bsxcloud']);
            }
        } else {

            try {
                Model_BSX_Core::$db = Database::instance($host);
            } catch (Exception $e) {
                Model_BSX_Core::$db = Database::instance();
            }

        }
    }

    public static function initSite() {
        //wykrywamy aktywną stronę WWW
        $host=$_SERVER['HTTP_HOST'];

        //łączymy się do bazy danych
        Model_BSX_Core::reconnectDB();

        //doczytujemy do konfiguracji ustawienia stron z bazy danych
        foreach (sql_rows('SELECT * FROM bsc_sites') as $site) {
            //jak znajdziemy w bazie konfigurację, którą mamy opisaną w pliku, to "scalamy" je, a jak nie - dodajemy nową konfigurację
            $sites=explode(';',$site['phost']);
            foreach ($sites as $ssite) {
                $site['phost']=$ssite;
                if (!empty($ssite)) {
                    if (isset(Model_BSX_Core::$bsx_config['sites'][$ssite])) {
                        Model_BSX_Core::$bsx_config['sites'][$ssite] = array_merge(Model_BSX_Core::$bsx_config['sites'][$ssite], $site);
                    } else {
                        Model_BSX_Core::$bsx_config['sites'][$ssite] = $site;
                    }
                }
            }
        }

        //subdomenki
        Model_BSX_Core::$subdomain='';
        foreach (Model_BSX_Core::$bsx_config['sites'] as $name=>$site)
        {
            if (!isset($site['phost']))
            {
                $site['phost']=$name;
                Model_BSX_Core::$bsx_config['sites'][$name]['phost']=$site['phost'];
            }
            if ($site['phost']==$host) {
                Model_BSX_Core::$bsx_cfg=$site;
                break;
            } else {
                if ($site['phost'] == substr($host, strlen($host) - strlen($site['phost']), strlen($site['phost']))) {
                    $subdomain = substr($host, 0, strlen($host) - strlen($site['phost']) - 1);
                    Model_BSX_Core::$bsx_cfg = $site;
                    Model_BSX_Core::$subdomain = $subdomain;
                    break;
                }
            }
        }
    }

    //-- inicjalizacja strony WWW - rozpoznanie strony, ustawienie basehref, itp.
    /**
     * @throws Kohana_Exception
     */
    public static function init() {
        if (Model_BSX_Core::$_init) return;

        //rozpoznajemy rozszerzenia
        Model_BSX_Core::$support_imagick=extension_loaded('imagick');

        //jak jest "ostatnia konfiguracja" w sesji, to ją pobieramy
        $sessKey='bsxcfg100';
        if (isset($_SESSION[$sessKey])) {
            //bieżąca konfiguracja
            Model_BSX_Core::$bsx_cfg = $_SESSION[$sessKey];
            //konfiguracja wszystkich stron
            Model_BSX_Core::$bsx_config = $_SESSION[$sessKey.'_all'];
            //połączenie do "powiązanej" bazy danych
            Model_BSX_Core::reconnectDB();
        } else {
            //wczytujemy konfigurację (opisane są tam strony WWW)
            Model_BSX_Core::$bsx_config = Kohana::$config->load('bsx');

            //nawiązujemy połączenie do wybranej bazy danych
            Model_BSX_Core::initSite();

            //jak jest podany bsxcloudkey w ustawieniach BSX.php, znaczy że dana strona
            //ma być pobierana z określonej chmury
            if (!empty(Model_BSX_Core::$bsx_cfg['bsxcloudkey']) && !isset($_SESSION['bsxcloud'])) {
                $_SESSION['bsxcloud'] = sql_row('SELECT * FROM bsw_mpclouds WHERE fkey=:key', array(':key' => Model_BSX_Core::$bsx_cfg['bsxcloudkey']));
                if ($_SESSION['bsxcloud']) {
                    //informacje o hostach mogą być enumerowane, a hasła szyfrowane
                    if (isset($_SESSION['bsxcloud']['nhost'])) {
                        if ($_SESSION['bsxcloud']['nhost']=='') $_SESSION['bsxcloud']['nhost']='bsxcloud.pl';
                        else if (strtolower($_SESSION['bsxcloud']['nhost'])=='h1') $_SESSION['bsxcloud']['nhost']='bsxcloud.pl';
                        else if (strtolower($_SESSION['bsxcloud']['nhost'])=='h2') $_SESSION['bsxcloud']['nhost']='bsxsystem.pl';
                        else if (strtolower($_SESSION['bsxcloud']['nhost'])=='h3') $_SESSION['bsxcloud']['nhost']='binsoft.pl';
                        else if (strtolower($_SESSION['bsxcloud']['nhost'])=='h4') $_SESSION['bsxcloud']['nhost']='mpcore.pl';
                        else if (strtolower($_SESSION['bsxcloud']['nhost'])=='h5') $_SESSION['bsxcloud']['nhost']='bsxsystem.com';
                        else if (strtolower($_SESSION['bsxcloud']['nhost'])=='h6') $_SESSION['bsxcloud']['nhost']='tradergroups.pl';
                    }
                    if (isset($_SESSION['bsxcloud']['nftphost'])) {
                        if ($_SESSION['bsxcloud']['nftphost']=='') $_SESSION['bsxcloud']['nftphost']='bsxcloud.pl';
                        else if (strtolower($_SESSION['bsxcloud']['nftphost'])=='h1') $_SESSION['bsxcloud']['nftphost']='bsxcloud.pl';
                        else if (strtolower($_SESSION['bsxcloud']['nftphost'])=='h2') $_SESSION['bsxcloud']['nftphost']='bsxsystem.pl';
                        else if (strtolower($_SESSION['bsxcloud']['nftphost'])=='h3') $_SESSION['bsxcloud']['nftphost']='binsoft.pl';
                        else if (strtolower($_SESSION['bsxcloud']['nftphost'])=='h4') $_SESSION['bsxcloud']['nftphost']='mpcore.pl';
                        else if (strtolower($_SESSION['bsxcloud']['nftphost'])=='h5') $_SESSION['bsxcloud']['nftphost']='bsxsystem.com';
                        else if (strtolower($_SESSION['bsxcloud']['nftphost'])=='h6') $_SESSION['bsxcloud']['nftphost']='tradergroups.pl';
                    }
                    if (isset($_SESSION['bsxcloud']['npass']) && substr($_SESSION['bsxcloud']['npass'],0,2)=='**') {
                        $p=substr($_SESSION['bsxcloud']['npass'],2);
                        $w='';
                        while (strlen($p)>0) {
                            $a=substr($p,0,2);
                            $p=substr($p,2);
                            $w.=chr(hexdec($a)^15);
                        }
                        $_SESSION['bsxcloud']['npass']=$w;
                    }
                    if (isset($_SESSION['bsxcloud']['nftppass']) && substr($_SESSION['bsxcloud']['nftppass'],0,2)=='**') {
                        $p=substr($_SESSION['bsxcloud']['nftppass'],2);
                        $w='';
                        while (strlen($p)>0) {
                            $a=substr($p,0,2);
                            $p=substr($p,2);
                            $w.=chr(hexdec($a)^15);
                        }
                        $_SESSION['bsxcloud']['nftppass']=$w;
                    }
                    //łączymy się do tej bazy i wczytujemy konfiguracje stron WWW w tej bazie
                    Model_BSX_Core::initSite();
                }
            }
            //strony WWW powiazane z chmurą, czerpią główne menu ze strony głównej
            if (!empty(Model_BSX_Core::$bsx_cfg['bsxcloudkey'])) {
                if (isset(Model_BSX_Core::$bsx_config['sites']['www.bsxcloud.pl'])) $dm = 'www.bsxcloud.pl';
                else if (isset(Model_BSX_Core::$bsx_config['sites']['bsxcloud.localhost'])) $dm = 'bsxcloud.localhost';
                else $dm = '';
                if ($dm != '') {
                    if (isset(Model_BSX_Core::$bsx_config['sites'][$dm]['padmin_navigation'])) Model_BSX_Core::$bsx_cfg['padmin_navigation'] = Model_BSX_Core::$bsx_config['sites'][$dm]['padmin_navigation'];
                    if (isset(Model_BSX_Core::$bsx_config['sites'][$dm]['preseller_navigation'])) Model_BSX_Core::$bsx_cfg['preseller_navigation'] = Model_BSX_Core::$bsx_config['sites'][$dm]['preseller_navigation'];
                }
            }

            if (!Model_BSX_Core::$bsx_cfg || (!isset(Model_BSX_Core::$bsx_cfg['psite']) && !isset(Model_BSX_Core::$bsx_cfg['puser']) && !isset(Model_BSX_Core::$bsx_cfg['preseller']) && !isset(Model_BSX_Core::$bsx_cfg['padmin']) && !isset(Model_BSX_Core::$bsx_cfg['id']))) {
                if (!empty(Model_BSX_Core::$bsx_cfg['bsxcloudkey'])) {
                    die('Host nie został poprawnie rozpoznany.');
                } else {
                    Header('Location: http://www.bsxcloud.pl');
                    exit;
                }
            }
            //uzupełniamy brakujące pola konfiguracyjne -----
            if (!isset(Model_BSX_Core::$bsx_cfg['id'])) Model_BSX_Core::$bsx_cfg['id'] = '0';
            if (!isset(Model_BSX_Core::$bsx_cfg['psite_skin'])) Model_BSX_Core::$bsx_cfg['psite_skin'] = 'standard';
            if (!isset(Model_BSX_Core::$bsx_cfg['psite_folder'])) Model_BSX_Core::$bsx_cfg['psite_folder'] = '/';
            if (!isset(Model_BSX_Core::$bsx_cfg['psite_class'])) Model_BSX_Core::$bsx_cfg['psite_class'] = str_replace(' ', '', ucwords(str_replace('_', ' ', Model_BSX_Core::$bsx_cfg['psite_skin'])));
            if (!isset(Model_BSX_Core::$bsx_cfg['psite_static'])) Model_BSX_Core::$bsx_cfg['psite_static'] = 'assets/' . Model_BSX_Core::$bsx_cfg['psite_skin'] . '/';

            if (!isset(Model_BSX_Core::$bsx_cfg['padmin_prefix'])) Model_BSX_Core::$bsx_cfg['padmin_prefix'] = 'admin';
            if (!isset(Model_BSX_Core::$bsx_cfg['padmin_skin'])) Model_BSX_Core::$bsx_cfg['padmin_skin'] = 'admin_cms';
            if (!isset(Model_BSX_Core::$bsx_cfg['padmin_static'])) Model_BSX_Core::$bsx_cfg['padmin_static'] = 'assets/' . Model_BSX_Core::$bsx_cfg['padmin_skin'] . '/';

            if (!isset(Model_BSX_Core::$bsx_cfg['preseller_prefix'])) Model_BSX_Core::$bsx_cfg['preseller_prefix'] = 'reseller';
            if (!isset(Model_BSX_Core::$bsx_cfg['preseller_skin'])) Model_BSX_Core::$bsx_cfg['preseller_skin'] = 'admin_cms';
            if (!isset(Model_BSX_Core::$bsx_cfg['preseller_static'])) Model_BSX_Core::$bsx_cfg['preseller_static'] = 'assets/' . Model_BSX_Core::$bsx_cfg['preseller_skin'] . '/';

            if (!isset(Model_BSX_Core::$bsx_cfg['puser_prefix'])) Model_BSX_Core::$bsx_cfg['puser_prefix'] = 'user';
            if (!isset(Model_BSX_Core::$bsx_cfg['puser_skin'])) Model_BSX_Core::$bsx_cfg['puser_skin'] = 'admin_cms';
            if (!isset(Model_BSX_Core::$bsx_cfg['puser_static'])) Model_BSX_Core::$bsx_cfg['puser_static'] = 'assets/' . Model_BSX_Core::$bsx_cfg['puser_skin'] . '/';

            if (!isset(Model_BSX_Core::$bsx_cfg['pshop_prefix'])) Model_BSX_Core::$bsx_cfg['pshop_prefix'] = 'sklep';
            if (!isset(Model_BSX_Core::$bsx_cfg['pshop_skin'])) Model_BSX_Core::$bsx_cfg['pshop_skin'] = Model_BSX_Core::$bsx_cfg['psite_skin'];
            if (!isset(Model_BSX_Core::$bsx_cfg['pshop_folder'])) Model_BSX_Core::$bsx_cfg['pshop_folder'] = Model_BSX_Core::$bsx_cfg['psite_folder'];
            if (!isset(Model_BSX_Core::$bsx_cfg['pshop_class'])) Model_BSX_Core::$bsx_cfg['pshop_class'] = Model_BSX_Core::$bsx_cfg['psite_class'];
            if (!isset(Model_BSX_Core::$bsx_cfg['pshop_static'])) Model_BSX_Core::$bsx_cfg['pshop_static'] = 'assets/' . Model_BSX_Core::$bsx_cfg['pshop_skin'] . '/';

            if (!isset(Model_BSX_Core::$bsx_cfg['pmlang'])) Model_BSX_Core::$bsx_cfg['pmlang'] = 'pl';
            if (!isset(Model_BSX_Core::$bsx_cfg['pmtitle'])) Model_BSX_Core::$bsx_cfg['pmtitle'] = '';
            if (!isset(Model_BSX_Core::$bsx_cfg['pmdesc'])) Model_BSX_Core::$bsx_cfg['pmdesc'] = '';
            if (!isset(Model_BSX_Core::$bsx_cfg['pmkeys'])) Model_BSX_Core::$bsx_cfg['pmkeys'] = '';
            if (!isset(Model_BSX_Core::$bsx_cfg['pmauthor'])) Model_BSX_Core::$bsx_cfg['pmauthor'] = '';
            if (!isset(Model_BSX_Core::$bsx_cfg['passets'])) Model_BSX_Core::$bsx_cfg['passets'] = 'assets';

            if (!isset(Model_BSX_Core::$bsx_cfg['idprice'])) Model_BSX_Core::$bsx_cfg['idprice'] = '0';
            if (!isset(Model_BSX_Core::$bsx_cfg['purl'])) Model_BSX_Core::$bsx_cfg['purl']='';

            //jak strona jest wewnątrz katalogu, odpowiednie poprawnie
            $s = rtrim(Model_BSX_Core::$bsx_cfg['psite_folder'], '/');
            if ($s != '') {
                Kohana::$base_url = '/' . $s . '/';
            }

            //ustawiamy odpowiedni basehref
            if (!isset(Model_BSX_Core::$bsx_cfg['pbasehref'])) {
                if ($s != '') $s = Kohana::$base_url; else $s = '/';
                Model_BSX_Core::$bsx_cfg['pbasehref'] = $s;
            }

            $_SESSION[$sessKey] = Model_BSX_Core::$bsx_cfg;
            $_SESSION[$sessKey.'_all']=Model_BSX_Core::$bsx_config;
        }

        //doczytujemy inne potrzebne dane z bazy danych
        $w = sql_row('SELECT pcurrency FROM bs_pricing WHERE id=:id', array(':id' => Model_BSX_Core::$bsx_cfg['idprice']));
        if ($w) Model_BSX_Shop::$currency = $w['pcurrency'];

        $w = sql_row('SELECT cfgvalue FROM bs_settings WHERE cfgname=:name', array(':name' => 'datt'));
        if ($w) Model_BSX_Shop::$attachmentPlace = $w['cfgvalue'];

        $w = sql_row('SELECT cfgvalue FROM bs_settings WHERE cfgname=:name', array(':name' => 'datt_folder'));
        if ($w) Model_BSX_Shop::$attachmentFolder = $w['cfgvalue'];

        if (!empty($_SESSION['login_user']['idprice']) && $_SESSION['login_user']['idprice']!=Model_BSX_Core::$bsx_cfg['idprice']) {
            Model_BSX_Core::$bsx_cfg['idprice']=$_SESSION['login_user']['idprice'];
            $w = sql_row('SELECT pcurrency FROM bs_pricing WHERE id=:id', array(':id' => Model_BSX_Core::$bsx_cfg['idprice']));
            if ($w) Model_BSX_Shop::$currency = $w['pcurrency'];
        }

        //jak strona ma podpięty własny MODEL - to go inicjujemy
        if (!empty(Model_BSX_Core::$bsx_cfg['model']) && is_callable(array(Model_BSX_Core::$bsx_cfg['model'], 'init'),true))
        {
            //forward_static_call(array(Model_BSX_Core::$bsx_cfg['model'],'init'));
        }

        //echo '<pre>';print_r(Model_BSX_Core::$bsx_cfg);exit;
        //jak jakieś strony mają indywidualne kontrolery, ustawiamy route by na nie wskazywały
        //$pages = sql_rows('SELECT id,idsite, pctrl, pmodrewrite FROM bsc_articles WHERE pctrl!="" AND pmodrewrite!="" AND  (idsite=0 OR idsite=:idsite)',array(':idsite'=>Model_BSX_Core::$bsx_cfg['id']));
        //foreach ($pages as $page) {
        //     Route::set('site_'.$page['id'].'_1', $page['pmodrewrite'].'(/<action>(/<id>(/<p1>(/<p2>(/<p3>)))))')->defaults(array('controller' => $page['pctrl'],'action'=>'index',));
        //     //Route::set('site_'.$page['id'].'_2', $page['pmodrewrite'])->defaults(array('controller' => $page['pctrl'],'action'=>'index',));
        // }

        //połączenie z memcache
        $s='site-';
        if (!empty(Model_BSX_Core::$bsx_cfg['bsxcloudkey'])) $s.='-'.Model_BSX_Core::$bsx_cfg['bsxcloudkey'];
        if (!empty(Model_BSX_Core::$bsx_cfg['id'])) $s.='-'.Model_BSX_Core::$bsx_cfg['id'];
        Model_BSX_Core::$cache=new BinMemCached('','',BinMemCached::M_MEM,$s.'!');
        Model_BSX_Core::$cache->connect();
        //Model_BSX_Core::$cache->clearAll();


        //detekcja struktury plików (by na poziomie bazy mieć opisaną strukturę folderów szablonów)
        //Model_BSX_Core::detect_structure();


        Model_BSX_Core::global_variable('ajax',(getGetPost('ajax')!='') || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'));

        //strona zainicjowana
        Model_BSX_Core::$_init=true;
    }

    //-- analiza struktury plików - wykrycie plików szablonu, dodanie ich do bazy itp.
    public static function detect_structure() {
        if ( !empty(Model_BSX_Core::$bsx_cfg['passets']) && !is_dir(Model_BSX_Core::$bsx_cfg['passets'].DIRECTORY_SEPARATOR.'img'))
        {
            mkdir(Model_BSX_Core::$bsx_cfg['passets'].DIRECTORY_SEPARATOR.'img', 0755, TRUE);
            chmod(Model_BSX_Core::$bsx_cfg['passets'].DIRECTORY_SEPARATOR.'img', 0755);
        }
        //wczytujemy aktualne skórki w bazie danych
        $skins = sql_rows('SELECT * FROM bsc_tpls');

        $files=array();


        /*if (!is_dir(APPPATH.'/classes/Controller'.Model_BSX_Core::$bsx_cfg['psite_class']))
        {

          $dir = new DirectoryIterator(APPPATH.'/classes/Controller');
          foreach ($dir as $file)
          {
               if (strtolower(Model_BSX_Core::$bsx_cfg['psite_class'])==strtolower($file))
               {
                    Model_BSX_Core::$bsx_cfg['psite_class']=$file;
                    break;
               }
          }

        }*/


        //wykrywamy skórki w katalogach
        $p=APPPATH.'views';
        $dir = new DirectoryIterator($p);
        foreach ($dir as $file)
        {
            $filename = $file->getFilename();

            if ($filename[0] === '.' OR $filename[strlen($filename)-1] === '~')
            {
                continue;
            }

            if (is_file($p.DIRECTORY_SEPARATOR.$filename.DIRECTORY_SEPARATOR.'init.php'))
            {
                $f=include($p.DIRECTORY_SEPARATOR.$filename.DIRECTORY_SEPARATOR.'init.php');
            } else
            {
                $f=array('title'=>$filename);
            }

            if (!empty($f['title']))
            {
                //przeglądamy pliki szablonów
                $dir_f = new DirectoryIterator($p.DIRECTORY_SEPARATOR.$filename);
                foreach ($dir_f as $file_f) {
                    $fl = $file_f->getFilename();
                    if ($fl[0] === '.' OR $fl[strlen($fl)-1] === '~') continue;
                    if (substr($fl,0,5)!='page_' && substr($fl,0,6)!='index_') continue;
                    $files[$filename.DIRECTORY_SEPARATOR.$fl]=filemtime($p.DIRECTORY_SEPARATOR.$filename.DIRECTORY_SEPARATOR.$fl);
                }

                $ok=false;
                foreach ($skins as $id=>$w)
                    if ($skins[$id]['pname']==$filename)
                    {
                        $skins[$id]['exists']=1;
                        $skins[$id]['title']=$f['title'];
                        $ok=true;
                        break;
                    }
                if (!$ok) {
                    $skins[]=array('pname'=>$filename,'ptitle'=>$f['title'],'exists'=>2);
                }
            }
        }

        //aktualizujemy wpisy w bazie danych
        foreach ($skins as $skin) {
            if (!isset($skin['exists'])) sql_query('DELETE FROM bsc_tpls WHERE id=:id',array(':id'=>$skin['id']));
            else if ($skin['exists']==2) sql_insert('bsc_tpls',array('ptitle'=>$skin['ptitle'],'pname'=>$skin['pname']));
        }
        arsort($files);
        $dt=$files[key($files)];
        $w=sql_row('SELECT modyf_time FROM bsc_tpls_fl ORDER BY modyf_time DESC LIMIT 1');
        if ($w) $w=$w['modyf_time']; else $w=date('Y-m-d H:i:s');
        $last_dt = strtotime($w);
        if ($dt>$last_dt) {
            sql_query('DELETE FROM bsc_tpls_fl');
            foreach ($files as $fname=>$fdate)
            {
                $insert = sql_insert('bsc_tpls_fl',array('modyf_time'=>date('Y-m-d H:i:s',$fdate),'pname'=>$fname));
            }
        }
    }

    //sprawdzenie czy jest włączona strona WWW/admina/usera itp.
    public static function testPermission($site)
    {
        if ($site=='site' && (!isset(Model_BSX_Core::$bsx_cfg['psite']) || Model_BSX_Core::$bsx_cfg['psite']==0))
        {
            if (isset(Model_BSX_Core::$bsx_cfg['pshop']) && Model_BSX_Core::$bsx_cfg['pshop']==1) Header('Location: /'.Model_BSX_Core::$bsx_cfg['pshop_prefix']);
            else if (isset(Model_BSX_Core::$bsx_cfg['paccount']) && Model_BSX_Core::$bsx_cfg['paccount']==1) Header('Location: /'.Model_BSX_Core::$bsx_cfg['paccount_prefix']);
            else if (isset(Model_BSX_Core::$bsx_cfg['puser']) && Model_BSX_Core::$bsx_cfg['puser']==1) Header('Location: /'.Model_BSX_Core::$bsx_cfg['puser_prefix']);
            else if (isset(Model_BSX_Core::$bsx_cfg['preseller']) && Model_BSX_Core::$bsx_cfg['preseller']==1) Header('Location: /'.Model_BSX_Core::$bsx_cfg['preseller_prefix']);
            else if (isset(Model_BSX_Core::$bsx_cfg['padmin']) && Model_BSX_Core::$bsx_cfg['padmin']==1) Header('Location: /'.Model_BSX_Core::$bsx_cfg['padmin_prefix']);
            else die('Brak strony!');
            exit;
        }
        if ($site=='admin' && (!isset(Model_BSX_Core::$bsx_cfg['padmin']) || Model_BSX_Core::$bsx_cfg['padmin']==0))
        {
            Header('Location: /');
            exit;
        }
        if ($site=='reseller' && (!isset(Model_BSX_Core::$bsx_cfg['preseller']) || Model_BSX_Core::$bsx_cfg['preseller']==0))
        {
            Header('Location: /');
            exit;
        }
        if ($site=='user' && (!isset(Model_BSX_Core::$bsx_cfg['puser']) || Model_BSX_Core::$bsx_cfg['puser']==0))
        {
            Header('Location: /');
            exit;
        }
        if ($site=='account' && (!isset(Model_BSX_Core::$bsx_cfg['paccount']) || Model_BSX_Core::$bsx_cfg['paccount']==0))
        {
            Header('Location: /');
            exit;
        }
        return true;
    }

    public static function asset($filename)
    {
        return 'assets/data/BinSoft/'.$filename;
    }

    public static function include_view($controller, $tpl, $prefix='psite', $vars=null)
    {
        if ($vars) foreach ($vars as $key => $val) if ($key!='prefix' && $key!='vars' && $key!='tpl' && $key!='controller'){ $$key=$val; }

        //plik z widokiem
        if (!empty(Model_BSX_Core::$bsx_cfg['viewFolder'])) {
            $f=Model_BSX_Core::$bsx_cfg[$prefix.'_skin'].DIRECTORY_SEPARATOR.Model_BSX_Core::$bsx_cfg['viewFolder'].DIRECTORY_SEPARATOR.$tpl;
            if (!is_file(APPPATH.'views'.DIRECTORY_SEPARATOR.$f.'.php')) $f=Model_BSX_Core::$bsx_cfg[$prefix.'_skin'].'/'.$tpl;
        } else {
            $f=Model_BSX_Core::$bsx_cfg[$prefix.'_skin'].DIRECTORY_SEPARATOR.$tpl;
        }
        if (!is_file(APPPATH.'views'.DIRECTORY_SEPARATOR.$f.'.php')) return false;

        $_TPL=Model_BSX_Core::$_TPL_;
        if (isset($controller->request)) $selfurl=$controller->request->url();
        include_once(APPPATH.'views'.DIRECTORY_SEPARATOR.$f.'.php');
    }

    //-- wyświetlenie widoku
    public static function create_view($controller, $tpl, $prefix='psite',$vars=array())
    {
        //określenie miejsca z grafikami statycznymi
        Model_BSX_Core::$_TPL_=Model_BSX_Core::$bsx_cfg[$prefix.'_static'];

        //załadowanie opcji
        $optionsFile=APPPATH.'views'.DIRECTORY_SEPARATOR.Model_BSX_Core::$bsx_cfg[$prefix.'_skin'].DIRECTORY_SEPARATOR.'default.php';
        if (is_file($optionsFile)) include_once($optionsFile);
        if (!empty(Model_BSX_Core::$bsx_cfg['viewFolder'])) {
            $optionsFile=APPPATH.'views'.DIRECTORY_SEPARATOR.Model_BSX_Core::$bsx_cfg[$prefix.'_skin'].DIRECTORY_SEPARATOR.Model_BSX_Core::$bsx_cfg['viewFolder'].DIRECTORY_SEPARATOR.'options.php';
            if (is_file($optionsFile)) include_once($optionsFile);
        }

        //plik z widokiem
        if (!empty(Model_BSX_Core::$bsx_cfg['viewFolder'])) {
            $f=Model_BSX_Core::$bsx_cfg[$prefix.'_skin'].DIRECTORY_SEPARATOR.Model_BSX_Core::$bsx_cfg['viewFolder'].DIRECTORY_SEPARATOR.$tpl;
            if (!is_file(APPPATH.'views'.DIRECTORY_SEPARATOR.$f.'.php')) $f=Model_BSX_Core::$bsx_cfg[$prefix.'_skin'].'/'.$tpl;
        } else {
            $f=Model_BSX_Core::$bsx_cfg[$prefix.'_skin'].DIRECTORY_SEPARATOR.$tpl;
        }
        if (!is_file(APPPATH.'views'.DIRECTORY_SEPARATOR.$f.'.php')) return false;


        //ładujemy widok
        $view=View::factory($f);

        //ustawiamy zmienne widoku
        $view->set('_TPL',Model_BSX_Core::$_TPL_);
        $view->set('_PREFIX',$prefix);
        $view->set('controller',$controller);
        $view->set('basehref',Model_BSX_Core::$bsx_cfg['pbasehref']);
        $view->set('admin_prefix',Model_BSX_Core::$bsx_cfg['padmin_prefix']);
        $view->set('reseller_prefix',Model_BSX_Core::$bsx_cfg['preseller_prefix']);
        $view->set('user_prefix',Model_BSX_Core::$bsx_cfg['puser_prefix']);
        $view->set('shop_prefix',Model_BSX_Core::$bsx_cfg['pshop_prefix']);
        if ($prefix=='admin' || $prefix=='padmin') $prefix2=Model_BSX_Core::$bsx_cfg['padmin_prefix'];
        else if ($prefix=='reseller' || $prefix=='preseller') $prefix2=Model_BSX_Core::$bsx_cfg['preseller_prefix'];
        else if ($prefix=='user' || $prefix=='puser') $prefix2=Model_BSX_Core::$bsx_cfg['puser_prefix'];
        else if ($prefix=='shop' || $prefix=='pshop') $prefix2=Model_BSX_Core::$bsx_cfg['pshop_prefix'];
        else $prefix2=URL::site();
        $view->set('prefix',$prefix2);
        if (isset($controller->request)) $view->set('selfurl',$controller->request->url());

        if (isset(Model_BSX_Core::$bsx_cfg[$prefix.'_navigation'])) $view->set('navigation',Model_BSX_Core::$bsx_cfg[$prefix.'_navigation']);
        else $view->set('navigation',array());

        foreach ($vars as $a=>$b) $view->set($a,$b);

        //dodatkowe parametry
        $params=array();
        $params['lang']=Model_BSX_Core::$bsx_cfg['pmlang'];
        $params['title']=Model_BSX_Core::$bsx_cfg['pmtitle'];
        $params['description']=Model_BSX_Core::$bsx_cfg['pmdesc'];
        $params['keywords']=Model_BSX_Core::$bsx_cfg['pmkeys'];
        $params['author']=Model_BSX_Core::$bsx_cfg['pmauthor'];
        if (isset($controller->params)) $params = array_merge($params, $controller->params);

        $view->path=array(
            array('caption'=>'Home','url'=>URL::site()),
        );

        $view->set('p',$params);
        $view->set('skin',Model_BSX_Core::$skinOptions);

        return $view;
    }



    public static function isOption($option, $default=false)
    {
        if (!isset(Model_BSX_Core::$bsx_cfg['options'][$option])) return $default;
        return Model_BSX_Core::$bsx_cfg['options'][$option];
    }

    //szablon wadomości e-mail
    public static function mail_view($tpl, $title='', $skin='', $url='')
    {
        if ($title=='') $title='Wiadomość ze strony';
        if ($skin=='') $skin=Model_BSX_Core::$bsx_cfg['psite_skin'];
        if ($url=='') $url=Model_BSX_Core::$bsx_cfg['purl'];
        $f=$skin.'/mails/'.$tpl;
        if (!is_file(APPPATH.'views'.DIRECTORY_SEPARATOR.$f.'.php')) return false;
        $view=View::factory($skin.'/mails/'.$tpl);
        $view->set('_TPL',Model_BSX_Core::$bsx_cfg['psite_static']);
        $view->set('_URL',$url);
        $view->set('title',$title);
        return $view;
    }


    //-- ustawienie tytułu strony WWW
    public static function meta_page($cmd,$txt=null,$opc=0)
    {
        if ($txt===null) {
            if ($cmd=='title') return Model_BSX_Core::$bsx_cfg['pmtitle'];
            if ($cmd=='description') return Model_BSX_Core::$bsx_cfg['pmdesc'];
            if ($cmd=='keywords') return Model_BSX_Core::$bsx_cfg['pmkeys'];
            if ($cmd=='author') return Model_BSX_Core::$bsx_cfg['pmauthor'];
        } else {
            if ($cmd=='title')
            {
                if ($opc==0) Model_BSX_Core::$bsx_cfg['pmtitle']=$txt;
                else if ($opc==1) Model_BSX_Core::$bsx_cfg['pmtitle']=$txt.' - '.Model_BSX_Core::$bsx_cfg['pmtitle'];
            }
            if ($cmd=='description') Model_BSX_Core::$bsx_cfg['pmdesc']=$txt;
            if ($cmd=='keywords') Model_BSX_Core::$bsx_cfg['pmkeys']=$txt;
            if ($cmd=='author') Model_BSX_Core::$bsx_cfg['pmauthor']=$txt;
        }
    }

    public static function global_variable($name,$val=null)
    {
        if ($val!==null) Model_BSX_Core::$variables[$name]=$val;
        else if (isset(Model_BSX_Core::$variables[$name])) return Model_BSX_Core::$variables[$name];
    }

    public static function create_sidebar($controller, $view)
    {
        //$view->sidebar_search='SZ';
        if (isset(Model_BSX_Core::$bsx_cfg['padmin_sidebar'])) $view->sidebar=Model_BSX_Core::$bsx_cfg['padmin_sidebar'];
        else $view->sidebar=array();
    }

    public static function get_menu($idr=0,$site=0)
    {
        if ($site==0) $site=Model_BSX_Core::$bsx_cfg['id'];
        $menu = sql_rows('SELECT * FROM bsc_menu WHERE idsite=:idsite AND idr=:idr AND pvisible=1 ORDER BY plp, ptitle',array(':idsite'=>$site,':idr'=>$idr));
        foreach ($menu as $id=>$item) {
            $menu[$id]['items']=Model_BSX_Core::get_menu($item['id'],$site);
        }
        return $menu;
    }

    public static function get_cats($idr=0,$recursive=true)
    {
        $menu = sql_rows('SELECT * FROM bsc_category WHERE idr=:idr ORDER BY plp, ptitle',array(':idr'=>$idr));
        foreach ($menu as $id=>$item) {
            if ($recursive) $menu[$id]['items']=Model_BSX_Core::get_cats($item['id'],$recursive);
        }
        return $menu;
    }

    public static function getSlider($idn)
    {
        $slider=sql_row('SELECT * FROM bsc_sliders WHERE pident=:pident',array(':pident'=>$idn));
        if ($slider) {
            $slider['items']=array();
            $slider['items'][]=sql_row('SELECT id, pid, pname as apname, ptable FROM bs_attachments WHERE ptable="bsc_sliders" AND pid=:pid AND pstatus=2 AND prating=100 ORDER BY RAND()',array(':pid'=>$slider['id']));
            $slider['items']=array_merge($slider['items'],sql_rows('SELECT id, pid, pname as apname, ptable FROM bs_attachments WHERE ptable="bsc_sliders" AND pid=:pid AND pstatus=2 AND prating<>100',array(':pid'=>$slider['id'])));
        }
        return $slider;
    }

    public static function getNSlider($idn)
    {
        $slider=sql_row('SELECT * FROM bsn_sliders WHERE pident=:pident AND pstatus=1',array(':pident'=>$idn));
        if ($slider) {
            $slider['slides']=sql_rows('SELECT * FROM bsn_sliders_pr WHERE idslider=:idslider',array(':idslider'=>$slider['id']));
            foreach ($slider['slides'] as $id=>$slide) {
                $slider['slides'][$id]['items']=array();
                $slider['slides'][$id]['txt']=array();
                $slider['slides'][$id]['items']=sql_rows('SELECT id, pid, pname as apname, ptable FROM bs_attachments WHERE ptable="bsn_sliders_pr" AND pid=:pid AND pstatus=2 AND prating=100 ORDER BY RAND()',array(':pid'=>$slider['slides'][$id]['id']));
                $slider['slides'][$id]['items']=array_merge($slider['slides'][$id]['items'],sql_rows('SELECT id, pid, pname as apname, ptable FROM bs_attachments WHERE ptable="bsn_sliders_pr" AND pid=:pid AND pstatus=2 AND prating<>100',array(':pid'=>$slider['slides'][$id]['id'])));
                $slider['slides'][$id]['txt']=sql_rows('SELECT * FROM bsn_sliders_txt WHERE idslide=:slide',array(':slide'=>$slide['id']));
            }
        }
        return $slider;
    }

    public static function buffer_start()
    {
        ob_start();
    }

    public static function buffer_end($filename=false)
    {
        $buffer=ob_get_contents();
        ob_end_clean();
        if ($filename!==false) file_put_contents($filename,$buffer);
        return $buffer;
    }


    public static function cache_start($name,$stime=0)
    {
        $fn=APPPATH.'cache/'.$name.'.htm';
        if ($stime>0 && is_file($fn)) $ok=time()-filemtime($fn)<=$stime; else $ok=is_file($fn);
        if ($ok)
        {
            readfile($fn);
            return true;
        } else
        {
            ob_start();
            return false;
        }
    }

    public static function cache_save($name,$buffer=null)
    {
        if ($buffer==null) $buffer=ob_get_contents();
        $fn=APPPATH.'cache/'.$name.'.htm';
        file_put_contents($fn,$buffer);
        ob_end_clean();
        echo $buffer;
    }

    public static function include_cache_view($name,$path=null)
    {
        if ($path==null)
        {
            $r=debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT,1);
            $path=realpath(dirname($r[0]['file']));
        }
        if (!Model_BSX_Core::cache_start($name))
        {
            include_once($path.DIRECTORY_SEPARATOR.$name);
            Model_BSX_Core::cache_save($name);
        }
    }

    public static function cache_img($table,$id,$name,$width=NULL,$height=NULL,$tryb=Image::AUTO,$subFolder='uploads')
    {
        if ($table!='' && $id!='' && $name!='') {
            $img = Model_BSX_Core::$bsx_cfg['passets'] . DIRECTORY_SEPARATOR . $subFolder . DIRECTORY_SEPARATOR . $table . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . $name;
            $url='';
            if (!empty(Model_BSX_Core::$bsx_cfg['nftpurl'])) $url=Model_BSX_Core::$bsx_cfg['nftpurl'];
            if (!empty($_SESSION['bsxcloud']['nftpurl'])) $url=$_SESSION['bsxcloud']['nftpurl'];
            if ($url!='')
            {
                $img=$url.$table . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . $name;
            }
        } else if ($table=='' && $id=='' && (substr($name,0,4)=='http' || $name[0]=='/')) {
            $img=$name;
        } else
        {
            $img=Model_BSX_Core::$bsx_cfg['passets'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.'empty.jpg';
        }

        return Model_BSX_Core::cache_img_url($img,$width,$height,$tryb);
    }


    public static function cache_img_url($img,$width=NULL,$height=NULL,$tryb=Image::AUTO,$useCache=true)
    {
        if (substr($img,0,4)=='http')
        {
            $remote=true;
            $r=BinUtils::extractFileExt($img);

            $nimg=Model_BSX_Core::$bsx_cfg['passets'].DIRECTORY_SEPARATOR.'imgdata';
            if (!is_dir($nimg)) {mkdir($nimg,0775);}
            $file=sha1($img).$r;
            $nimg.=DIRECTORY_SEPARATOR.substr($file,0,2);
            if (!is_dir($nimg)) {mkdir($nimg,0775);}
            $nimg.=DIRECTORY_SEPARATOR.$file;

            if (!is_file($nimg)) {
                $data = @file_get_contents($img);
                if (!$data) return Model_BSX_Core::$bsx_cfg['passets'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.'empty.jpg';
                if (strpos($data,'<html')!==false) return Model_BSX_Core::$bsx_cfg['passets'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.'empty.jpg';
                @file_put_contents($nimg, $data);
            }
            $img=$nimg;
        } else {
            $remote=false;
            if ($img!='' && $img[0]!='/' && $img[1]!=':') {
                if ($width<=0 && $height<=0) {
                    $nimg = $img;
                } else {
                    $nimg = DOCROOT . $img;
                }
            }
            else $nimg=$img;  
            if (!is_file($nimg)) $nimg= DOCROOT . $img;
        }

        if (is_file($nimg)) $tm=filemtime($nimg);
        else {
            $tm=0;


        }

        if ($tryb==200) return $nimg;

        $r=BinUtils::extractFileExt($img);
        $p=Model_BSX_Core::$bsx_cfg['passets'].DIRECTORY_SEPARATOR.'imgcache';
        if (!is_dir($p)) {mkdir($p,0775);}
        $file=sha1($img.'x'.$width.'x'.$height.'x'.$tryb.'x'.$tm);
        $p.=DIRECTORY_SEPARATOR.substr($file,0,2);
        if (!is_dir($p)) {mkdir($p,0775);}
        $p.=DIRECTORY_SEPARATOR.$file.$r;

        if ($r!='.png')
        {
            $np=substr($p,0,-strlen($r)).'.png';
            if (is_file($np) && $useCache) return str_replace('\\','/',$np);
        }
        if (is_file($p) && $useCache) return str_replace('\\','/',$p);


        if ($tm==0)
        {
            $f=Model_BSX_Core::$bsx_cfg['passets'].DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.'empty.jpg';
            $p=Model_BSX_Core::$bsx_cfg['passets'].DIRECTORY_SEPARATOR.'imgcache'.DIRECTORY_SEPARATOR.sha1($f.'x'.$width.'x'.$height.'x'.$tryb.'x'.$tm).'.jpg';
            if (is_file($p)) return str_replace('\\','/',$p);
            $nimg=$f;
        }

        if ($width<=0 && $height<=0) return $nimg;


        if (Model_BSX_Core::$support_imagick)
        { 
	        if ($nimg!='' && $nimg[0]!='/' && $nimg[1]!=':') $nimg = DOCROOT . $nimg; //musi być ścieżka bezwględna!		
            $img = new Imagick();
            $img->readImage($nimg);
            $img->setImageCompression(Imagick::COMPRESSION_JPEG);
            $img->setImageCompressionQuality(100);
            if ($tryb==102)
            {
                $img->resizeImage($width,$height,Imagick::FILTER_LANCZOS,1, true);

            } else
                if ($tryb==101)
                {
                    $oldSize=$img->getImageGeometry();
                    if ($oldSize['width']>$oldSize['height'] && $width>0) $img->resizeImage($width,0,Imagick::FILTER_LANCZOS,1);
                    if ($height>0) $img->resizeImage(0,$height,Imagick::FILTER_LANCZOS,1);

                } else
                    if ($tryb==100)
                    {
                        $oldSize=$img->getImageGeometry();
                        if ($oldSize['width']>$oldSize['height'] && $width>0) $img->resizeImage($width,0,Imagick::FILTER_LANCZOS,1);
                        else if ($height>0) $img->resizeImage(0,$height,Imagick::FILTER_LANCZOS,1);
                        $oldSize=$img->getImageGeometry();
                        $img->cropImage($width,$height,$oldSize['width']/2-$width/2,$oldSize['height']/2-$height/2);
                    } else //AUTO
                    {
                        $oldSize=$img->getImageGeometry();
                        //print_r($oldSize);echo '!'.$width.'!'.$height.'!';exit;
                        if ($width>0 && $height>0)
                        {
                            if ($r=='.jpg' || $r=='.jpeg')
                            {
                                $img->destroy();

                                $foto=new Imagick($nimg);
                                $oldSize=$foto->getImageGeometry();
                                if ($oldSize['width']>$width) $foto->resizeImage($width,0,Imagick::FILTER_LANCZOS,1);
                                $oldSize=$foto->getImageGeometry();
                                if ($oldSize['height']>$height) $foto->resizeImage(0,$height,Imagick::FILTER_LANCZOS,1);
                                $oldSize=$foto->getImageGeometry();
                                $x=$width/2-$oldSize['width']/2;
                                $y=$height/2-$oldSize['height']/2;

                                $img = new Imagick();
                                $img->newImage($width, $height, new ImagickPixel('transparent'));
                                $img->setImageCompressionQuality(100);
                                $img->setImageFormat('png');
                                $img->setImageBackgroundColor( new ImagickPixel( 'transparent' ) );
                                //$img->setResolution($width, $height);
                                $img->compositeImage($foto,Imagick::COMPOSITE_DEFAULT, $x, $y);

                                $foto->destroy();

                                $p=$np;
                            } else
                            {
                                $img->setImageBackgroundColor( new ImagickPixel( 'transparent' ) );
                                $img->thumbnailImage($width,$height,true,true);
                            }
                        }
                        else if ($width>0 && $height<=0) $img->resizeImage($width,0,Imagick::FILTER_LANCZOS,1);
                        else if ($height>0 && $width<=0) $img->resizeImage(0,$height,Imagick::FILTER_LANCZOS,1);
                        else if ($oldSize['width']>$oldSize['height'] && $width>0) $img->resizeImage($width,0,Imagick::FILTER_LANCZOS,1);
                        else if ($height>0) $img->resizeImage(0,$height,Imagick::FILTER_LANCZOS,1);

                    } 
		

            if ($p!='' && $p[0]!='/' && $p[1]!=':') $p = DOCROOT . $p; //musi być ścieżka bezwględna!		
            $img->writeImage($p);
            $img->destroy();

        } else
        {
            $image = Image::factory($nimg);
            if ($tryb==105) {
                $image->resize($width, $height, ($image->width > $image->height ? Image::WIDTH : Image::HEIGHT));
            } else if ($tryb==100)
            {
                if ($image->width>$image->height) $image->resize($width,$height,Image::HEIGHT); else $image->resize($width,$height,Image::WIDTH);
                $image->crop($width, $height);
            } else {
                $image->resize($width,$height,$tryb);
            }
            $image->save($p);
        }
        return $p;
    }

    public static function create_friendly_name($s)
    {
        // $s='HTH Spa Czyszczący linie wody 1l'; //po znaku ą jest jakis niewyswietlany znak - tu  tkwil blad, bo w spa taki ciag znakow byl wysylany

        // $s='HTH Spa Czyszczący linie wody 1l';
        // echo 'before:';
        // print_r($s);
        // echo '<br>###';

        $rf = array("\r","\n","\r\n","\n\r",'а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я','');
        $rt = array('','','','','a','b','v','g','d','e','jo','zh','z','i','j','k','l','m','n','o','p','r','s','t','u','f','h','c','ch','sh','w','','y','','je','ju','ja','');
        $txt = str_replace(array('^',"'",'"','`','~'),'',iconv('UTF-8','ASCII//TRANSLIT',str_replace($rf,$rt,mb_strtolower($s,'UTF-8'))));
        while (strpos($txt,'  ')!==false) $txt = str_replace('  ',' ',$txt);
        $txt=str_replace(' ','-',preg_replace('/[^a-zA-Z0-9\s]/','',trim(str_replace(array('_','.',',','(',')','{','}','[',']','/',':',';','"','\'','-','+','=','!','@','#','$','%','^','&','?','*'),' ', $txt ))));
        // echo 'after:';
        // print($txt);
        // echo '<br>---';
        return $txt;
    }

    public static function showDebugger() {
        if (isset($_GET['debugger']) && $_GET['debugger']=='yes')
        {
            $_SESSION['debugger']=true;
        }
        if (isset($_SESSION['debugger'])) {
            echo '<div class="bsxdebugger">';
            echo '<div class="params">';
            echo '<h4>GET:</h4>';echo '<pre>';print_r($_GET);echo '</pre>';
            echo '<h4>POST:</h4>';echo '<pre>';print_r($_POST);echo '</pre>';
            echo '<h4>COOKIE:</h4>';echo '<pre>';print_r($_COOKIE);echo '</pre>';
            echo '</div>';
            echo View::factory('profiler/stats');
            if (isset($_GET['showsession'])) {
                echo '<div class="params">';
                echo '<h4>SESSION:</h4>';
                echo '<pre>';
                print_r($_SESSION);
                echo '</pre>';
                echo '</div>';
            }
            echo '</div>';
        }
    }


    public static function parseText($s,$markdown=true,$controller=null) {
        $tags=array('col','panel','button','youtube','img','row','html','gallery','template');
        //szukamy pierwszego tag-u
        $min=-1;$foundTag=false;
        foreach ($tags as $tag) {
            $x=strpos($s,'['.$tag);
            if ($x!==false && ($x<$min || $min==-1)) {
                $min=$x;
                $foundTag=$tag;
            }
        }
        if ($foundTag) {
            //znaleziony tag
            $len = strlen($foundTag);
            $a = substr($s, 0, $min); //przed znacznikiem
            $c = ''; //po znaczniku
            $data = ''; //treść w znaczniku
            $pz = strpos($s, ']', $min);
            if ($pz !== false) {
                $prm = substr($s, $min + $len + 1, $pz - $min - $len - 1);
                if ($prm!='') $prm=substr($prm,1);
                $s = substr($s, $pz + 1);
            } else {
                $prm = '';
            }

            //szukamy koniec znacznika
            $i = 0;
            $deep = 1;
            while ($i < strlen($s)) {
                if (substr($s, $i, $len + 1) == '[' . $foundTag) $deep++;
                if (substr($s, $i, $len + 3) == '[/' . $foundTag . ']') {
                    $deep--;
                    if ($deep == 0) {
                        $c = substr($s, $i + $len + 3);
                        $data = substr($s, 0, $i);
                        break;
                    }
                }
                $i++;
            }


            $opcje=explode(':',$prm);

            //parsujemy to co jest przed znacznikiem
            $a=Model_BSX_Core::parseText($a,$markdown);
            //parsujemy co jest po znaczniku
            $c=Model_BSX_Core::parseText($c,$markdown);

            //--------------------------------------------------------------------------
            if ($foundTag=='row') {
                $data='<div class="row">'.Model_BSX_Core::parseText($data,$markdown).'</div>';
            } else
                //--------------------------------------------------------------------------
                //[row][col:6:6:6]XXX[/col][/row]
                if ($foundTag=='col') {
                    if (!empty($opcje[0])) $class='col-md-'.$opcje[0]; else $class='col-md-12';
                    if (!empty($opcje[1])) $class.=' col-sm-'.$opcje[1];
                    if (!empty($opcje[2])) $class.=' col-xs-'.$opcje[2];
                    $data='<div class="'.$class.'">'.Model_BSX_Core::parseText($data,$markdown).'</div>';
                } else
                    //--------------------------------------------------------------------------
                    //[panel:title:class:footer]Tresc[/panel]
                    if ($foundTag=='panel') {
                        if (!empty($opcje[0])) $title = $opcje[0]; else $title = '';
                        if (!empty($opcje[1])) $class = $opcje[1]; else $class = 'panel-default';
                        if (!empty($opcje[2])) $footer = $opcje[2]; else $footer = '';

                        $kod = '<div class="panel ' . $class . '">' . "\n";
                        if ($title != '') $kod .= '<div class="panel-heading">' . $title . '</div>' . "\n";
                        $kod .= '<div class="panel-body">' . Model_BSX_Core::parseText($data,$markdown) . '</div>' . "\n";
                        if ($footer != '') $kod .= '<div class="panel-footer">' . $footer . '</div>' . "\n";
                        $kod .= '</div>' . "\n";
                        $data='<div class="'.$class.'">'.$kod.'</div>';
                    } else
                        //--------------------------------------------------------------------------
                        //[button=http://www.onet.pl|btn-success|width:200px;]Test[/button]
                        if ($foundTag=='button') {
                            $opcje=explode('|',$prm);
                            $link=$opcje[0];
                            if (!empty($opcje[1])) $class=$opcje[1]; else $class='btn-success';
                            if (!empty($opcje[2])) $style=' style="'.$opcje[2].'"'; else $style='';
                            $data='<a href="'.$link.'" class="btn '.$class.'"'.$style.'>'.$data.'</a>';
                        } else
                            //--------------------------------------------------------------------------
                            //[youtube:WIDTHxHEIGHT:align:controls]ADRES[/youtube]
                            if ($foundTag=='youtube') {
                                $width  = 425;
                                $height = 325;
                                $controls=1;
                                $align='left';
                                $attr='';
                                if (count($opcje)>0)
                                {
                                    if (!empty($opcje[0]))
                                    {
                                        $k=strpos($opcje[0],'x');
                                        if ($k!==false)
                                        {
                                            $width=substr($opcje[0],0,$k);
                                            $height=substr($opcje[0],$k+1);
                                        } else {
                                            $width=$opcje[0];
                                            $height='';
                                        }
                                        $attr.=' width="'.$width.'"';
                                        if ($height!='') $attr.=' height="'.$height.'"';
                                    }
                                    if (!empty($opcje[1])) $align=$opcje[1];
                                    if (!empty($opcje[2])) $controls=$opcje[2];
                                }
                                $pp=strpos($data,'v=');
                                if ($pp>0) {$data=substr($data,$pp+2);$pz=strpos($data,'&');if ($pz>0) $data=substr($data,0,$pz);}
                                $style='';
                                if ($align=='left' || $align=='right' || $align=='center') $style.='text-align:'.$align.';';
                                else if ($align=='float-left') $style.='float:left; margin-right:10px;';
                                else if ($align=='float-right') $style.='float:right; margin-left:10px;';
                                $kod='<div style="'.$style.'"><iframe '.$attr.' src="http://www.youtube.com/embed/'.$data.'?autoplay=0&controls='.$controls.'" frameborder="0" allowfullscreen></iframe></div>';
                                $data=$kod;
                            } else
                                //--------------------------------------------------------------------------
                                //[gallery:identyfikator]Test[/gallery]
                                if ($foundTag=='gallery') {
                                    $template=$data;
                                    $data='';
                                    if (!empty($opcje[1])) $w=(int)$opcje[1]; else $w=200;
                                    if (!empty($opcje[2])) $h=(int)$opcje[2]; else $h=200;
                                    if (!empty($opcje[3])) $t=(int)$opcje[3]; else $t=105;
                                    $gallery=sql_row('SELECT * FROM bsc_galleries WHERE pident=:ident',array(':ident'=>$opcje[0]));
                                    $images=sql_rows('SELECT a.ptable, a.pid, a.pname AS apname FROM bs_attachments a WHERE pstatus=2 AND ptable="bsc_galleries" AND pid=:pid',array(':pid'=>$gallery['id']));
                                    foreach ($images as $image) {
                                        $item=$template;
                                        $item=str_replace('{image.src}',Model_BSX_Core::cache_img($image['ptable'],$image['pid'],$image['apname'],0,0,200),$item);
                                        $item=str_replace('{image.minsrc}',Model_BSX_Core::cache_img($image['ptable'],$image['pid'],$image['apname'],$w,$h,$t),$item);
                                        $data .= $item;
                                    }
                                } else
                                    //--------------------------------------------------------------------------
                                    //[template:szablon:identyfikator]Test[/template]
                                    if ($foundTag=='template') {
                                        if (empty($opcje[0])) $data='Brak szablonu!';
                                        else {
                                            $view = Model_BSX_Core::create_view($controller, $opcje[0]);
                                            if (!$view) $data='Nie odnaleziono szablonu: '.$opcje[0];
                                            else {
                                                $view->params=$opcje;
                                                $data = $view->render();
                                            }
                                        }
                                    } else
                                        //--------------------------------------------------------------------------
                                        //[html]Test[/html]
                                        if ($foundTag=='html') {

                                        } else
                                            //--------------------------------------------------------------------------
                                            //[img:klasa:tytul]URL:WIDTHxHEIGHTxMODE:ALT:CLASS:STYLE[/img]
                                            if ($foundTag=='img') {
                                                if (substr($data, 0, 6) == 'https:') $data[5] = '|';
                                                else if (substr($data, 0, 5) == 'http:') $data[4] = '|';

                                                $t = explode(':', $data);

                                                $adres = $t[0];
                                                if (substr($adres, 0, 6) == 'https|') $adres[5] = ':';
                                                else if (substr($adres, 0, 5) == 'http|') $adres[4] = ':';


                                                if (empty($t[1])) $size = ''; else $size = $t[1];
                                                if (empty($t[2])) $alt = ''; else $alt = ' alt="' . $t[2] . '"';
                                                if (empty($t[3])) $class = ''; else $class = ' class="' . $t[3] . '"';
                                                if (empty($t[4])) $style = ''; else $style = ' style="' . str_replace('@', ':', $t[4]) . '"';

                                                $width = 0;
                                                $height = 0;
                                                $mode = Image::AUTO;
                                                if ($size != '') {
                                                    $size = explode('x', $size);
                                                    $width = $size[0];
                                                    if (isset($size[1])) $height = $size[1];
                                                    if (isset($size[2])) $mode = $size[2];
                                                }

                                                if ($adres[0] == '@') {
                                                    $adres = substr($adres, 1);
                                                    $returnAddress = true;
                                                } else {
                                                    $returnAddress = false;
                                                }

                                                if ($adres != '') {
                                                    if (substr($adres, 0, 4) != 'http' && $adres[0]!='/') {
                                                        $w = sql_row('SELECT id, ptitle, ptable, pid, pname FROM bs_attachments WHERE (id=:v OR ptitle=:v OR pname=:v) AND pstatus=2', array(':v' => $adres));
                                                        $adres = Model_BSX_Core::cache_img($w['ptable'], $w['pid'], $w['pname'], $width, $height, $mode);
                                                    } else {
                                                        if ($width > 0 || $height > 0) {
                                                            $adres = Model_BSX_Core::cache_img('', 0, $adres, $width, $height, $mode);
                                                        }
                                                    }
                                                    if ($returnAddress) $kod = $adres; else $kod = '<img src="' . $adres . '"' . $alt . $class . $style . '>';

                                                    $data=$kod;
                                                }
                                            }

            return $a.$data.$c;

        } else {
            //nie ma znacznika
            if ($markdown) {
                $m = Markdown::instance();
                $s = $m->transform($s, true);
            }

            $bbcode= array (
                "/\[br\]/si",
                "/\[clear\]/si",
                "/\[color=(.+?)\](.+?)\[\/color\]/si",
                "/\[size=(.+?)\](.+?)\[\/size\]/si",
                "/\[url\](.+?)\[\/url\]/si",
                "/\[url=(.+?)\](.+?)\[\/url\]/si",
                "/\[quote\](.+?)\[\/quote\]/si",
                "/\[div:(.+?)\](.+?)\[\/div\]/si",
                "/\{pathtpl\}/si",
            );
            $htmlcode= array (
                "<br />",
                "<div style=\"clear:both;\"></div>",
                "<span style=\"color:$1\">$2</span>",
                "<span style=\"font-size:$1\">$2</span>",
                "<a href=\"$1\">$1</a>",
                "<a href=\"$1\">$2</a>",
                "<blockquote>$1</blockquote>",
                "<div $1>$2</div>",
                Model_BSX_Core::$bsx_cfg['psite_static'],
            );
            $s=preg_replace($bbcode,$htmlcode,$s);
            $s=preg_replace($bbcode,$htmlcode,$s);
        }

        return $s;
    }
}