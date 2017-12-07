<?php
/*****************************************************
/** class.BinMemCached.php  Data:2016-03-06         **
/**                                                 **
/** Karol Wierzchołowski                            **
/** karol@wierzcholowski.pl                         **
/**                                                 **
/*****************************************************/


  class BinMemCached  {
      const M_OFF   =0;
      const M_MEM   =1;
      const M_FILE  =2;
      const M_INLINE=3;

      const TIME_FULL=0;
      const TIME_HOUR=3600;
      const TIME_1H=3600;
      const TIME_2H=7200;
      const TIME_8H=32400;
      const TIME_DAY=86400;
      const TIME_1D=86400;
      const TIME_2D=172800;
      const TIME_7D=604800;
      const TIME_1M=604800;

      private $db_host;
      private $db_port;

      public $memcache = NULL;
      public $varlist=array();
      private $varlistChanges=false;
      public $servers=0;
      public $saveVarlistDestruct=true;            //czy listę varlist zapisywać przy kończeniu skryptu (true), czy po kazdej zmianie (false)
      public $varlistLimit=10000;                  //maksymalna liczba wpisów w varlist

      public $debug=false;
      public $debugToSystem=false;
      public $logfile='application/logs/memcache.txt';   //plik z logami błędów

      public $persistentConnection=false;             //czy nawiązywać połączenie stałe

      private $sessionPrefix='';                  //prefix wpisów sesyjnych
      private $sessionLifetime=0;                 //czas trzymania sesji
      public $enabledSession=false;               //czy funkcja jest włączona

      private $lastBufferName='';                 //nazwa ostatnio generowanego bufora (isBuffer -> setBuffer)

      public $path='application/cache/';
      public $mode=BinMemCached::M_MEM; //0=brak cache, 1 - memcache (class), 2 - file, 3 - memcache (inline)

      public $allowMemcached=false;
      public $allowMemcache=false;

      public $prefix='';



       public function __construct($db_host='', $db_port='', $mode=BinMemCached::M_MEM, $prefix='') {
           $this->mode=$mode;
           $this->prefix=$prefix;
           if ($db_host!='' && $db_port!='') $this->connect($db_host,$db_port);
       }

       function __destruct() {
              if ($this->saveVarlistDestruct) $this->saveVarList(true);
       }

       public function connect($db_host='localhost', $db_port='11211') {
              $this->db_host=$db_host;
              $this->db_port=$db_port;

              $this->allowMemcached=extension_loaded('memcached');
              $this->allowMemcache=extension_loaded('memcache');


              //dodać mechanizm sprawdzania ile jest aktywnych połączeń,
              //jak zbyt dużo - robimy zwykłe connect
              if ($this->mode==BinMemCached::M_MEM)
              {
                  if ($this->persistentConnection)
                  {
                      if ($this->allowMemcached) {
                          if ($this->memcache === NULL) $this->memcache = new Memcached('bsxmemcache');
                          $this->addServer($db_host,$db_port);
                      } else if ($this->allowMemcache) {
                          if ($this->memcache===NULL) $this->memcache=new Memcache();
                          if (!$r=@$this->memcache->pconnect($db_host,$db_port))
                          {
                              sleep(1);
                              $this->log('[ERROR]Problem z polaczeniem (pconnect) z sererem MemCache! (1)',false,true);
                              $r=@$this->memcache->connect($db_host,$db_port);
                              if (!$r) {
                                  $this->log('[ERROR]Problem z polaczeniem (connect) z sererem MemCache! (2)',false,true);
                                  $this->mode=$this->mode==BinMemCached::M_FILE;
                              }
                          }
                      } else $this->mode=BinMemCached::M_FILE;
                  } else
                  {
                      if ($this->allowMemcached) {
                          if ($this->memcache===NULL) $this->memcache=new Memcached();
                          $this->addServer($db_host,$db_port);
                      } else if ($this->allowMemcache) {
                          if ($this->memcache===NULL) $this->memcache=new Memcache();
                          if (!$r=@$this->memcache->connect($db_host,$db_port))
                          {
                              sleep(1);
                              $this->log('[ERROR]Problem z polaczeniem (connect) z sererem MemCache! (1)',false,true);
                              $r=@$this->memcache->connect($db_host,$db_port);
                              if (!$r) {
                                  $this->log('[ERROR]Problem z polaczeniem (connect) z sererem MemCache! (2)',false,true);
                                  $this->mode=BinMemCached::M_FILE;
                              }
                          }
                      } else $this->mode=BinMemCached::M_FILE;
                  }
                  if ($this->memcache===NULL) {
                      $this->log('[ERROR]Problem z inicjacją klasy MemCached! (1)',false,true);
                      $this->mode=BinMemCached::M_FILE;
                      return false;
                  }
                  if ($this->allowMemcached) {
                      $stat = $this->memcache->getStats();
                      $n = current($stat);
                      if ($n['version'] == '' || $n['pid'] == -1) {
                          unset($this->memcached);
                          $this->log('[ERROR]Problem z polaczeniem z serwerem MemCached! (2)', false, true);
                          $this->mode = BinMemCached::M_FILE;
                          return false;
                      }
                  }
              }

              $this->initVarList();
           return true;
       }

       public function addServer($db_host, $db_port) {
            $result=$this->memcache->addServer($db_host,$db_port);
            if ($result) $this->servers++;
            if ($this->servers==1) $this->initVarList();
            return $result;
       }

       public function log($txt,$echo=true,$save=false) {
            $s='[BinMemCache]'.date('Y-m-d H:i:s').' - '.$txt;
            //if (!empty($_SERVER['REMOTE_ADDR'])) $s.=' - '.$_SERVER['REMOTE_ADDR'];
            //if (!empty($_SERVER['REMOTE_ADDR'])) $s.=' - '.gethostbyaddr($_SERVER['REMOTE_ADDR']);
            $s.="\r\n";
            if ($save)
            {
                $handle = @fopen($this->logfile, 'a');
                if ($handle) {
                  flock($handle,LOCK_EX);
                  fwrite($handle,$s);
                  flock($handle,LOCK_UN);
                  fclose($handle);
                }
            }
            if ($this->debugToSystem)
            {
                 if (stripos($s,'[ERROR]')!==false) $s='<b style="color:red;">'.$s.'</b>';
                 return;
            }
            if ($echo) echo $s.'<br />';
       }

       private function initVarList() {
            $this->varlist=array();
            if ($this->servers>0 && $this->varlistLimit>=0)
            {
                 if ($this->mode==BinMemCached::M_MEM)
                 {
                    $this->varlist=$this->getValue('@varlist@',$this->varlist);
                 } else if ($this->mode==BinMemCached::M_FILE)
                 {
                    $file=$this->path.'varlist.txt';
                    if (is_file($file)) $file=unserialize(file_get_contents($file));
                 }
            }
       }




       private function saveVarList($always=false) {
            if ($this->varlistLimit<0 || !$this->varlistChanges) return;
            if ($this->mode==BinMemCached::M_OFF) return; //wyłączony tryb

            if ($always || !$this->saveVarlistDestruct)
            {
                 if ($this->mode==BinMemCached::M_MEM)
                 {
                      if ($this->servers>0)
                      {
                         $this->memcache->set($this->prefix.'@varlist@',$this->varlist);
                      }
                 } else if ($this->mode==BinMemCached::M_FILE)
                 {
                      $file=$this->path.$this->prefix.'varlist.txt';
                      file_put_contents($file,serialize($this->varlist));
                 }
            }
       }

       public function getVersion() {
              if ($this->mode==BinMemCached::M_OFF) return 'Server Disabled';
              else if ($this->mode==BinMemCached::M_MEM) return $this->memcache->getVersion() or die('[BinMemCacheError] No MemCache Found!');
              else if ($this->mode==BinMemCached::M_FILE) return 'FileServer';
              else return 'Unknown';
       }

       public function setValue($name, $value, $flag=false, $expire=0) {
              if ($this->mode==BinMemCached::M_OFF) return;
              if ($this->mode==BinMemCached::M_MEM)
              {
                 $this->memcache->set($this->prefix.$name,$value,$flag,$expire);
              } else if ($this->mode==BinMemCached::M_FILE)
              {
                 $file=$this->path.$this->prefix.sha1($name).'.txt';
                 if ($expire<=0) $expire=31*24*3600;
                 file_put_contents($file,serialize(array('date'=>time(),'expire'=>$expire,'value'=>$value)));
              }
              if ($this->debug) $this->log('setValue:'.$name);
       }

       public function set($name, $value, $flag=false, $expire=0) {
              $this->setValue($name,$value,$flag,$expire);
       }


       public function setMemValue($name, $value, $flag=false, $expire=0) {
              $this->setValue($name,$value,$flag,$expire);
              if ($this->varlistLimit<0) return;
              $this->varlist[$name]=time();
              $this->varlistChanges=true;
              if ($this->varlistLimit>0 && count($this->varlist)>$this->varlistLimit) {
                   $k=key($this->varlist);
                   if ($k) $this->delValue($k,false);
              }
              $this->saveVarList();
       }

       public function getValue($name, $def=FALSE) {
            if ($this->mode==BinMemCached::M_OFF) return $def;
            if ($this->mode==BinMemCached::M_MEM)
            {
               $data=$this->memcache->get($this->prefix.$name);
            } else if ($this->mode==BinMemCached::M_FILE)
            {
               $file=$this->path.$this->prefix.sha1($name).'.txt';
               if (is_file($file))
               {
                    $data=unserialize(file_get_contents($file));
                    if ($data['date']+$data['expire']<time())
                    {
                         $data=false;
                         unlink($file);
                    } else $data=$data['value'];
               } else $data=false;
            }
            if ($data===false) $data=$def;
            if ($this->debug)
            {
                 if ($data===false) $this->log('getValue:'.$name.' NO EXISTS');
                 else $this->log('getValue:'.$name.' FOUND!');
            }
            return $data;
       }

       public function get($name, $def=FALSE) {
            return $this->getValue($name,$def);
       }


       public function delValue($name,$updateVarList=true) {
            if ($this->mode==BinMemCached::M_OFF) return;
            if ($this->mode==BinMemCached::M_MEM)
            {
                 $result=$this->memcache->delete($this->prefix.$name);
            } else if ($this->mode==BinMemCached::M_FILE)
            {
                 $file=$this->path.$this->prefix.sha1($name).'.txt';
                 if (is_file($file)) unlink($file);
            }
            if ($this->debug) $this->log('delValue:'.$name);
            if (isset($this->varlist[$name]))
            {
                      unset($this->varlist[$name]);
                      $this->varlistChanges=true;
                      if ($updateVarList) $this->saveVarList();
            }
       }

       public function delete($name) {
            $this->delValue($name);
       }

       public function clearAll() {
            if ($this->mode==BinMemCached::M_OFF) return;
            else if ($this->mode==BinMemCached::M_MEM)
            {
                  $this->memcache->flush();
            } else if ($this->mode==BinMemCached::M_FILE)
            {
                  $files = glob($this->path.'*.txt');
                  foreach($files as $file){
                    if(is_file($file))
                      unlink($file);
                  }
            }
            $this->varlist=array();
            $this->varlistChanges=true;
            $this->saveVarList();
            if ($this->debug) $this->log('Clear All (VARLIST)');
       }

       public function delMemValueLike($like) {
            if ($this->debug) $this->log('delValues like:'.$like);
            $this->delValue($like);
            foreach ($this->varlist as $a=>$b)
            {
                 if (stripos($a,$like)!==FALSE) $this->delValue($a,false);
            }
            $this->saveVarList();
       }

       public function getStats() {
            if ($this->mode==BinMemCached::M_MEM) return $this->memcache->getStats(); else return array();
       }

       public function getExtendedStats() {
            if ($this->mode==BinMemCached::M_MEM) return $this->memcache->getExtendedStats(); else return array();
       }
      //==================== dowolne buforowanie danych ================

       public function isBuffer($name,$ret=true)
       {
                 $this->lastBufferName=$name;
                 $data=$this->getValue($name);
                 if ($data===FALSE)
                 {
                      if (!$ret) ob_start();
                      return false;
                 }
                 if ($ret || is_array($data)) return $data;
                 else echo $data;
                 return true;
       }

       public function setBuffer($name='',$buffer=false,$mtime=0,$ret=true)
       {
           if ($name=='') $name=$this->lastBufferName;
           if ($buffer===false)
           {
                $buffer=ob_get_contents();
                ob_end_clean();
           }

           $this->setValue($name,$buffer,false,$mtime);

           if ($ret || is_array($buffer)) return $buffer;
           else echo $buffer;
           return true;
       }

       public function delBuffer($name)
       {
            $this->delValue($name);
       }



      //==================== obsługa sesji w memcached -----------


       public function enableSession($prefix,$lifeTime=-1)
       {
              $this->sessionPrefix=$prefix;
              if ($prefix=='') return false;

              $this->sessionLifetime = get_cfg_var("session.gc_maxlifetime");
              if ($lifeTime>=0) $this->sessionLifetime=$lifeTime;

              session_set_save_handler(
              array($this, 'sessao_open'),
              array($this, 'sessao_close'),
              array($this, 'sessao_read'),
              array($this, 'sessao_write'),
              array($this, 'sessao_destroy'),
              array($this, 'sessao_gc')
              );

              // This line prevents unexpected effects when using objects as save handlers.
              register_shutdown_function('session_write_close');

              $this->enabledSession=true;

              return true;
       }

       function sessao_open($aSavaPath, $aSessionName)
       {
                return true;
       }

       function sessao_close()
       {
               return true;
       }

       function sessao_read( $aKey )
       {
               $userAgent='';
               $ip=BinMemCache::getUserIP();
               if (!empty($_SERVER['HTTP_USER_AGENT'])) $userAgent.=$_SERVER['HTTP_USER_AGENT'];
               if ($ip!='') $userAgent.=' '.$ip;
               $sessionID=$this->sessionPrefix.$aKey.':'.sha1($userAgent);

               $row=$this->getValue($sessionID);
               if($row!==false) return $row;
               else
               {
                     $this->setValue($sessionID,'',false,$this->sessionLifetime);
                     return '';
               }
       }

       function sessao_write( $aKey, $aVal )
       {
               $userAgent='';
               $ip=BinMemCache::getUserIP();
               if (!empty($_SERVER['HTTP_USER_AGENT'])) $userAgent.=$_SERVER['HTTP_USER_AGENT'];
               if ($ip!='') $userAgent.=' '.$ip;
               $sessionID=$this->sessionPrefix.$aKey.':'.sha1($userAgent);
               $this->setValue($sessionID,$aVal,false,$this->sessionLifetime);
               return true;
       }

       function sessao_destroy( $aKey )
       {
               $userAgent='';
               $ip=BinMemCache::getUserIP();
               if (!empty($_SERVER['HTTP_USER_AGENT'])) $userAgent.=$_SERVER['HTTP_USER_AGENT'];
               if ($ip!='') $userAgent.=' '.$ip;
               $sessionID=$this->sessionPrefix.$aKey.':'.sha1($userAgent);
               $this->delValue($sessionID);
               return true;
       }

       function sessao_gc( $aMaxLifeTime )
       {
               return true;
       }

       public static function getUserIP()
       {
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
            else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
            else if (!empty($_SERVER['REMOTE_ADDR']) ) return $_SERVER['REMOTE_ADDR'];
            else return '';
       }

  }

?>