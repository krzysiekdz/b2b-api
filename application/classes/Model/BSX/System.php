<?php defined('SYSPATH') or die('No direct script access.');

class Model_BSX_System
{
    private static $_init   = false;

    public $company=array();
    public $branche=array();
    public $user=array();

    public static function init() {
         if (Model_BSX_System::$_init) return Model_BSX_System::$_init;

        if (isset($_SESSION['bsxsystem'])) {
            Model_BSX_System::$_init=$_SESSION['bsxsystem'];
            return Model_BSX_System::$_init;
        }

         Model_BSX_System::$_init=new Model_BSX_System();
         Model_BSX_System::$_init->loadData();
         $_SESSION['bsxsystem']=Model_BSX_System::$_init;
         return Model_BSX_System::$_init;
    }

    public function loadData() {
         if (isset(Model_BSX_Core::$bsx_cfg['idcompany'])) $this->company = sql_row('SELECT * FROM bs_company WHERE id=:id',array(':id'=>Model_BSX_Core::$bsx_cfg['idcompany']));
         if (isset(Model_BSX_Core::$bsx_cfg['idbranch'])) $this->branche = sql_row('SELECT * FROM bs_branches WHERE id=:id',array(':id'=>Model_BSX_Core::$bsx_cfg['idbranch']));
         if (isset(Model_BSX_Core::$bsx_cfg['idowner'])) $this->user = sql_row('SELECT * FROM bs_users WHERE id=:id',array(':id'=>Model_BSX_Core::$bsx_cfg['idowner']));
         if (!$this->user) die('Sklep nie posiada przypisanego uzytkownika!');
         if (!$this->branche) die('Sklep nie posiada przypisanego oddzialu!');
         if (!$this->company) die('Sklep nie posiada przypisanej firmy!');

         $this->user['username']=$this->user['pfirstname'].' '.$this->user['plastname'];
         $this->branche['branchename']=$this->branche['pname'];

         $row = sql_row('SELECT * FROM bs_banks WHERE idcompany=:idcompany ORDER BY pdefault',array(':idcompany'=>$this->company['id']));
         if ($row) $this->company['bank']=$row['paccount'];


    }

    public function getDBConfig($s,$def,$idcompany=0,$idbranch=0,$iduser=0) {
      if ($idcompany<0) $idcompany=$this->company['id'];
      if ($idbranch<0) $idbranch=$this->branche['id'];
      if ($iduser<0) $iduser=$this->user['id'];
      $row = sql_row('SELECT cfgvalue,id FROM bs_settings WHERE cfgname=:cfgname AND idcompany=:idcompany AND idbranch=:idbranch AND iduser=:iduser',array(':idcompany'=>$idcompany,':idbranch'=>$idbranch,':iduser'=>$iduser,':cfgname'=>$s));
      if ($row) return $row['cfgvalue'];
      return $def;
    }


    public function getCompanyConfig($s,$def) {
         return $this->getDBConfig($s,$def,-1);
    }

    private function FillS($s,$k) {
              while (strlen($s)<$k) $s='0'.$s;
              return $s;
    }

    public static function getPaymentsForm() {
         return sql_rows_id('SELECT * FROM bs_paymentsform WHERE cms_active=1');
    }

    public static function getDeliveryTypes() {
         return sql_rows_id('SELECT * FROM bs_deliverytypes WHERE cms_active=1');
    }

    public function getNoDoc($cm, $inc=false, $dt=false, $addwhere='', $data=array()) {
         $ret=array('nnodoc'=>'','nvnodoc'=>'','idnodoc'=>0);
         $table='';
         if (!is_numeric($cm)) { 
             $k = strpos($cm, '#');
             if ($k !== false) {
                 $addwhere = substr($cm, $k + 1);
                 $cm = substr($cm, 0, $k);
             }
             $row = sql_row('SELECT * FROM bs_symbols WHERE pidn=:pidn', array(':pidn' => $cm));
         } else $row = sql_row('SELECT * FROM bs_symbols WHERE id=:id', array(':id' => $cm));
         if (!$row){$row=array('pformat'=>'#L#/#M#/#Y#','pnumber'=>1,'id'=>0);}

         if ($dt==false || $dt==null) $dt=time();
         else if (!is_numeric($dt)) $dt=strtotime($dt);

         if (!is_array($data))
         {
            $d=explode(';',$data);
            $data=array();
            foreach ($d as $line) {
                $k=strpos($line,'=');
                if ($k!==false) $data[substr($line,0,$k)]=substr($line,$k+1);
            }
         }

         $k=strpos($addwhere,'#');
         if ($k!==false) {
            $data['mtable']=substr($addwhere,0,$k);
            $addwhere=substr($addwhere,$k+1);
         }

         $ret['idnodoc']=$row['id'];


         $lc=(int)$row['pnumber'];

         $VM = date('Ym', $dt);

         //jak podano wszystkie dane, to wyznaczamy ostatni numer, a nie bierzemy tego z ustawień
         if (isset($data['mtable']) && isset($data['msymbol']) && isset($data['mvsymbol']) && isset($data['idcompany'])) {
             if ($addwhere!='') $addwhere=' AND '.$addwhere;
             $d=sql_row('SELECT '.$data['mvsymbol'].' FROM '.$data['mtable'].' WHERE '.$data['mvsymbol'].' LIKE "'.$VM.'%" AND idcompany='.$data['idcompany'].$addwhere.' ORDER BY '.$data['mvsymbol'].' DESC');
             if ($d) {
                 $lc=(int)substr($d[$data['mvsymbol']],6)+1;
             } else {
                 $lc=1;
             }
         }

         do {
             $cm=$row['pformat'];
             $VSymbol = $VM . $this->FillS($lc, 8);

             $ret['nvnodoc'] = $VSymbol;

             $cm = str_replace('#L#', $lc, $cm);
             for ($i = 1; $i <= 10; $i++) $cm = str_replace('#' . $i . 'L#', $this->FillS($lc, $i), $cm);
             $cm = str_replace('#Y#', date('Y', $dt), $cm);
             $cm = str_replace('#y#', date('y', $dt), $cm);
             $cm = str_replace('#M#', date('n', $dt), $cm);
             $cm = str_replace('#m#', date('m', $dt), $cm);
             $cm = str_replace('#D#', date('d', $dt), $cm);
             $cm = str_replace('#d#', date('j', $dt), $cm);
             $cm = str_replace('#H#', date('h', $dt), $cm);
             $cm = str_replace('#I#', date('i', $dt), $cm);
             $cm = str_replace('#S#', date('s', $dt), $cm);
             $cm = str_replace('#UN#', $this->user['username'], $cm);
             $cm = str_replace('#OF#', $this->branche['branchename'], $cm);

             $ok=false;

             //jak podano wszystkie dane, to sprawdzamy czy taki numer już istnieje, jak tak, lecimy na kolejny
             if (isset($data['mtable']) && isset($data['msymbol']) && isset($data['mvsymbol']) && isset($data['idcompany'])) {
               if (sql_row('SELECT id FROM '.$data['mtable'].' WHERE '.$data['msymbol'].'=:number AND idcompany=:idcompany'.$addwhere,array(':number'=>$cm,':idcompany'=>$data['idcompany']))) {
                   $lc++;
                   $ok=true;
               }
             }
         } while ($ok);



         $ret['nnodoc']=$cm;

         if ($inc && $ret['idnodoc']>0) {
             sql_query('UPDATE bs_symbols SET pnumber=pnumber+1 WHERE id=:id',array(':id'=>$ret['idnodoc']));
         }

         return $ret;
    }

    public static function addToReport($table, $idr, $type, $title, $desc, $username='Online',$idcompany=0, $idbranch=0) {
        if (is_array($desc)) {
            $desc_s='';
            foreach ($desc as $line) {
                $desc_s.="$line\n";
            }
        } else $desc_s=$desc;
        $r=array();
        $r['add_time']=date('Y-m-d H:i:s');
        $r['modyf_time']=$r['add_time'];
        $r['ptable']=$table;
        $r['idr']=$idr;
        $r['ptype']=$type;
        $r['ptitle']=$title;
        $r['pdesc']=$desc_s;
        $r['pusername']=$username;
        sql_insert('bs_logs',$r);
    }

}



