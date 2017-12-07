<?php defined('SYSPATH') or die('No direct script access.');

// -- Environment setup --------------------------------------------------------

// Load the core Kohana class
require SYSPATH.'classes/Kohana/Core'.EXT;

if (is_file(APPPATH.'classes/Kohana'.EXT))
{
	// Application extends the core
	require APPPATH.'classes/Kohana'.EXT;
}
else
{
	// Load empty core extension
	require SYSPATH.'classes/Kohana'.EXT;
}

require APPPATH.'classes/BSX_functions'.EXT;

/**
 * Set the default time zone.
 *
 * @link http://kohanaframework.org/guide/using.configuration
 * @link http://www.php.net/manual/timezones
 */
date_default_timezone_set('Europe/Warsaw');

/**
 * Set the default locale.
 *
 * @link http://kohanaframework.org/guide/using.configuration
 * @link http://www.php.net/manual/function.setlocale
 */
setlocale(LC_ALL, 'pl_PL.utf-8');

/**
 * Enable the Kohana auto-loader.
 *
 * @link http://kohanaframework.org/guide/using.autoloading
 * @link http://www.php.net/manual/function.spl-autoload-register
 */
spl_autoload_register(array('Kohana', 'auto_load'));

/**
 * Optionally, you can enable a compatibility auto-loader for use with
 * older modules that have not been updated for PSR-0.
 *
 * It is recommended to not enable this unless absolutely necessary.
 */
//spl_autoload_register(array('Kohana', 'auto_load_lowercase'));

/**
 * Enable the Kohana auto-loader for unserialization.
 *
 * @link http://www.php.net/manual/function.spl-autoload-call
 * @link http://www.php.net/manual/var.configuration#unserialize-callback-func
 */
ini_set('unserialize_callback_func', 'spl_autoload_call');

/**
 * Set the mb_substitute_character to "none"
 *
 * @link http://www.php.net/manual/function.mb-substitute-character.php
 */
mb_substitute_character('none');

// -- Configuration and initialization -----------------------------------------

/**
 * Set the default language
 */
I18n::lang('pl');

if (isset($_SERVER['SERVER_PROTOCOL']))
{
	// Replace the default protocol.
	HTTP::$protocol = $_SERVER['SERVER_PROTOCOL'];
}

/**
 * Set Kohana::$environment if a 'KOHANA_ENV' environment variable has been supplied.
 *
 * Note: If you supply an invalid environment name, a PHP warning will be thrown
 * saying "Couldn't find constant Kohana::<INVALID_ENV_NAME>"
 */
if (isset($_SERVER['KOHANA_ENV']))
{
	Kohana::$environment = constant('Kohana::'.strtoupper($_SERVER['KOHANA_ENV']));
}

/**
 * Initialize Kohana, setting the default options.
 *
 * The following options are available:
 *
 * - string   base_url    path, and optionally domain, of your application   NULL
 * - string   index_file  name of your index file, usually "index.php"       index.php
 * - string   charset     internal character set used for input and output   utf-8
 * - string   cache_dir   set the internal cache directory                   APPPATH/cache
 * - integer  cache_life  lifetime, in seconds, of items cached              60
 * - boolean  errors      enable or disable error handling                   TRUE
 * - boolean  profile     enable or disable internal profiling               TRUE
 * - boolean  caching     enable or disable internal caching                 FALSE
 * - boolean  expose      set the X-Powered-By header                        FALSE
 */
Kohana::init(array(
	'base_url'   => '/',
	'index_file' => '',
	'errors'     => (Kohana::$environment == Kohana::DEVELOPMENT),
	'profile'    => (Kohana::$environment == Kohana::DEVELOPMENT),
	'caching'    => (Kohana::$environment == Kohana::PRODUCTION),
));

/**
 * Attach the file write to logging. Multiple writers are supported.
 */
Kohana::$log->attach(new Log_File(APPPATH.'logs'));

/**
 * Attach a file reader to config. Multiple readers are supported.
 */
Kohana::$config->attach(new Config_File);

/**
 * Enable modules. Modules are referenced by a relative or absolute path.
 */
Kohana::modules(array(
	// 'auth'       => MODPATH.'auth',       // Basic authentication
	// 'cache'      => MODPATH.'cache',      // Caching with multiple backends
	// 'codebench'  => MODPATH.'codebench',  // Benchmarking tool
	 'database'   => MODPATH.'database',   // Database access
	 'image'      => MODPATH.'image',      // Image manipulation
	// 'minion'     => MODPATH.'minion',     // CLI Tasks
	// 'orm'        => MODPATH.'orm',        // Object Relationship Mapping
	// 'unittest'   => MODPATH.'unittest',   // Unit testing
	// 'userguide'  => MODPATH.'userguide',  // User guide and API documentation
	'markdown'   => MODPATH.'markdown',   // Markdown module
	'email'      => MODPATH.'email',      // Email module
	// 'recaptcha'      => MODPATH.'recaptcha',      // reCaptcha module
    // 'captcha'      => MODPATH.'captcha',      // Captcha module
	));



/**
 * Cookie Salt
 * @see  http://kohanaframework.org/3.3/guide/kohana/cookies
 * 
 * If you have not defined a cookie salt in your Cookie class then
 * uncomment the line below and define a preferrably long salt.
 */
Cookie::$salt = 'bsXSalt'; 
//Cookie::$domain=Model_BSX_Core::$bsx_cfg['phost'];
//Cookie::$expiration = Date::WEEK;
$session = Session::instance();
$_SESSION =& $session->as_array();
if (empty($_SESSION['login_user'])) $_SESSION['login_user']=array('id'=>0); //zalogowany w stronie głównej
if (empty($_SESSION['user_user'])) $_SESSION['user_user']=array('id'=>0); //odrębny panel usera
if (empty($_SESSION['reseller_user'])) $_SESSION['reseller_user']=array('id'=>0); //odrębny panel resellera
if (empty($_SESSION['admin_user'])) $_SESSION['admin_user']=array('id'=>0); //odrębny panel admina



/**
 * Konfiguracja BSX
 */
Model_BSX_Core::init();
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers:X-Requested-With,X-Prototype-Version,Content-Type,Cache-Control,Pragma,Origin,Set-Cookie');
header('Access-Control-Allow-Methods:GET,POST,OPTIONS');
// header('Access-Control-Allow-Origin: http://localhost:4200');
header('Access-Control-Allow-Origin: *');
// header('Access-Control-Max-Age:1728000');


// if (!empty(Model_BSX_Core::$bsx_cfg['padmin_prefix']))
// {
// 	Route::set('admin2', Model_BSX_Core::$bsx_cfg['padmin_prefix'].'(/<controller>(/<action>(/<cmd>(/<id>))))')->defaults(array('controller' => 'Core', 'action'=>'index', 'directory'=>'admin',));
// }

// if (!empty(Model_BSX_Core::$bsx_cfg['preseller_prefix']))
// {
// 	Route::set('reseller2', Model_BSX_Core::$bsx_cfg['preseller_prefix'].'(/<controller>(/<action>(/<cmd>(/<id>))))')->defaults(array('controller' => 'Core', 'action'=>'index', 'directory'=>'reseller',));
// }

// if (!empty(Model_BSX_Core::$bsx_cfg['puser_prefix']))
// {
// 	Route::set('user2', Model_BSX_Core::$bsx_cfg['puser_prefix'].'(/<controller>(/<action>(/<cmd>(/<id>))))')->defaults(array('controller' => 'Core', 'action'=>'index', 'directory'=>'user',));
// }

// Route::set('account1', 'account')->defaults(array('controller' => 'Core', 'action'=>'index', 'directory'=>'account',));
// Route::set('account2', 'account/<modrewrite>',array('modrewrite'=>'.*'))->defaults(array('controller' => 'Core','action' => 'detect','directory'=>'account',));

// Route::set('shop1', Model_BSX_Core::$bsx_cfg['pshop_prefix'])->defaults(array('controller' => 'Core', 'action'=>'index', 'directory'=>'shop',));
// Route::set('shop2', Model_BSX_Core::$bsx_cfg['pshop_prefix'].'/<modrewrite>',array('modrewrite'=>'.*'))->defaults(array('controller' => 'Core','action' => 'detect','directory'=>'shop',));

// Route::set('api', 'api(/<controller>(/<action>(/<id>(/<stuff>))))',array('stuff' => '.*'))->defaults(array('directory'=>'api','controller' => 'Start','action'=> 'index',));

Route::set('b2b', 'b2b/<modrewrite>',array('modrewrite'=>'.*'))->defaults(array('controller' => 'Core','action' => 'detect','directory'=>'B2B',));

//-- wszystko inne leci do detect
Route::set('all', '<modrewrite>',array('modrewrite'=>'.*'))->defaults(array('controller' => 'Detect','action' => 'detect',));
