<?php
/* ************************************
/* BinUtils v2016-01-26              **
/* ************************************/

global $echo_buff;

class BinUtils {
  private static $buff;

  public static function buffer_start()
  {
      global $echo_buff;
      BinUtils::$buff=ob_get_contents();
      ob_end_clean();
      ob_start();
  }

  public static function buffer_end()
  {
      $bf=ob_get_contents();
      ob_end_clean();
      ob_start();
      echo BinUtils::$buff;
      return $bf;
  }

  public static function correctPrice($s)
  {
      $s=str_replace(' ','',str_replace(',','.',$s));
      return (double)$s;
  }

  public static function CorrectS2D($value)
  {
      return number_format((double)$value, 2, '.', '');
  }

  public static function CorrectD2D($value)
  {
        return number_format((double)$value, 2, '.', '');
  }

  public static function CorrectDD2S($value)
  {
        return number_format((double)$value, 4, '.', '');
  }
    
  public static function price($value,$w='')
  {
      if ($w!='') $w='&nbsp;'.$w;
      return number_format((double)$value, 2, '.', ' ').$w;
  }

  public static function doubleValue($value,$w=3)
  {
        return number_format((double)$value, $w, '.', ' ');
  }

    public static function trimPL($s) {
        $a = array( 'Ę', 'Ó', 'Ą', 'Ś', 'Ł', 'Ż', 'Ź', 'Ć', 'Ń', 'ę', 'ó', 'ą', 'ś', 'ł', 'ż', 'ź', 'ć', 'ń' );
        $b = array( 'E', 'O', 'A', 'S', 'L', 'Z', 'Z', 'C', 'N', 'e', 'o', 'a', 's', 'l', 'z', 'z', 'c', 'n' );
        $s = str_replace( $a, $b, $s );
        return $s;
    }

  public static function explodeMail($email, &$adres, &$nazwa)
  {
       $adres='';
       $nazwa=NULL;
       $k=strpos($email,'<');
       if ($k!==false)
       {
            $nazwa=trim(substr($email,0,$k));
            $adres=trim(substr($email,$k+1,-1));
            if ($nazwa=='') $nazwa=NULL;
       } else $adres=$email;
  }

    public static function cleanField($s,$t) {
        $s=trim($s);
        $t=strtolower($t);
        if ($t=='nip') {
            $s = str_replace('-','',$s);
            $s = str_replace(' ','',$s);
            $s=strtoupper($s);
        } else if ($t=='iban') {
            $s = str_replace('-','',$s);
            $s = str_replace(' ','',$s);
            $s=strtoupper($s);
        } else if ($t=='phone') {
            $s = str_replace('-','',$s);
            $s = str_replace(' ','',$s);
            $s = str_replace('.','',$s);
            $s = str_replace('(','',$s);
            $s = str_replace(')','',$s);
            $s=strtoupper($s);
          }
      return trim($s);
    }

    public static function extractFilePath($file,$lower=false)
    {
        $path=substr($file,0,-strlen(BinUtils::extractFileName($file)));
        if ($lower) return strtolower($path); else return $path;
    }


    public static function extractFileExt($file,$lower=true)
    {
        $t=explode('.', $file);
        $extension = '.'.end($t);
        if ($lower) return strtolower($extension); else return $extension;
    }

    public static function extractFileName($file,$lower=false)
    {
        $t=explode('/', $file);
        $name = end($t);
        if ($lower) return strtolower($name); else return $name;
    }

    public static function extractFileNameNoExt($file,$lower=false)
    {
        $file=BinUtils::extractFileName($file);
        $name=substr($file,0,strlen($file)-strlen(BinUtils::extractFileExt($file)));
        if ($lower) return strtolower($name); else return $name;
    }

    public static function genUniqueFileName($path,$start,$ext='@')
    {
        if ($ext=='@') {
            $ext=BinUtils::extractFileExt($start);
            $start=BinUtils::extractFileNameNoExt($start);
        }
        $start=$start.time().rand(1,100);
        $i=0;
        {
            if ($i==0) $file=$path.'/'.$start.$ext;
            else $file=$path.'/'.$start.'-'.$i.$ext;
            $i++;
        } while (BinUtils::is_file($file));
        return $file;
    }

    public static function mkdir($folder)
    {
        if (!is_dir($folder))
        {
            mkdir($folder);
        }
    }

    public static function txt_cut($text, $cut)
    {
        $text = strip_tags($text);
        $calosc = strlen($text);
        if ($cut >= $calosc) { return $text; }
        else {
            $poz = strpos($text.' ', ' ', $cut-4);
            if ($poz<$cut+5) return substr($text, 0, $poz).'...';
            else return substr($text, 0, $cut).'...';
        }
    }

    public static function pr($d, $exit=false)
    {
        echo '<pre>';
        if (is_array($d)) print_r($d);
        else if (is_object($d) || is_bool($d)) var_dump($d);
        else echo $d;
        echo '</pre>';
        if ($exit) exit();
    }

    public static function trackDebug($show,$full,$file) {
        return '';
    }


    public static function fillInt($s,$cnt,$char='0') {
        while (strlen($s)<$cnt) $s=$char.$s;
        return $s;
    }

    public static function MSL($l,$a='minuta',$c='minuty',$b='minut')
    {
        $l=abs($l);
        $r=$l%100;
        if ($r==0) return($b);
        else if ($l>100 && $r==1) return($b);
        else if ($r==1) return($a);
        else if ($r>=2 && $r<=4) return($c);
        elseif ($r>=5 && $r<=20) return($b);
        else {
            $m=$r%10;
            if ($m<=1) return($b);
            elseif ($m<=4) return($c);
            else return($b);
        }
    }

    public static function DATA_M($d)
    {
        if (date('d.m.Y')==date('d.m.Y',$d)) return 'dzisiaj';
        else if (date('d.m.Y',mktime(12,00,00,date("n"),date("j")+2,date("Y")))==date('d.m.Y',$d)) return 'pojutrze';
        else if (date('d.m.Y',mktime(12,00,00,date("n"),date("j")+1,date("Y")))==date('d.m.Y',$d)) return 'jutro';
        else if (date('d.m.Y',mktime(12,00,00,date("n"),date("j")-1,date("Y")))==date('d.m.Y',$d)) return 'wczoraj';
        else if (date('d.m.Y',mktime(12,00,00,date("n"),date("j")-2,date("Y")))==date('d.m.Y',$d)) return 'przedwczoraj';
        else return date('d.m.Y',$d).' r.';
    }

    public static function sizetostr($s)
    {
        $s=(int)$s;
        if ($s<1024) return $s.' B';
        else if ($s<1048576) return sprintf('%.2f',$s/1024).' KB';
        else return sprintf('%.2f',$s/1048576).' MB';
    }

    public static function stoper_start()
    {
        list($usec, $sec) = explode(' ',microtime());
        return ((float)$usec + (float)$sec);
    }

    public static function stoper_stop($licznik)
    {
        list($usec, $sec) = explode(' ',microtime());
        $czas=((float)$usec + (float)$sec)-$licznik;
        return sprintf('%.4f',$czas);
    }


}

function getSecurity($s)
{
    return strip_tags($s);
}

function getSecurityLike($s)
{
  $s=str_replace('%','',$s);
  $s=str_replace('"','',$s);
  $s=str_replace("'",'',$s);
  $s=str_replace("\0",'',$s);  
  $s=strip_tags($s);
  return trim($s);
}

function getSecuritySQL($s)
{
  while (strpos($s,'-')!==false) $s=str_replace('-','',$s);
  while (strpos($s,';')!==false) $s=str_replace(';','',$s);
  while (strpos($s,' ')!==false) $s=str_replace(' ','',$s);
  return trim($s);
}

function getSecurityHTML($s,$exc=NULL)
{
  if ($exc==NULL) $exc='<b><i><u><p><strong>';
  return strip_tags($s,$exc);
}


function getPost($n, $d='')
{
     return Arr::get($_POST, $n, $d);
}

function getGet($n, $d='')
{
     return Arr::get($_GET, $n, $d);
}

function getGetPost($n, $d='')
{
  $d=Arr::get($_GET, $n, $d);
  return Arr::get($_POST, $n, $d);
}

function getGetPostInt($n, $d=0)
{
  $d=(int)Arr::get($_GET, $n, $d);
  return (int)Arr::get($_POST, $n, $d);
}


function getGetPostLike($n,$def='')
{
     $d=getGetPost($n,$def);
     return getSecurityLike($d);
}

function getGetPostSQL($n,$def='')
{
     $d=getGetPost($n,$def);
     return getSecuritySQL($d);
}

function getPostInt($n,$min=0, $max=100000) {
    $d=(int)getPost($n,$min);
    if($d < $min) $d=$min;
    else if ($d > $max) $d=$max;
    return $d;
}

function getPostArray(&$f) {
  foreach($f as $key=>$val) $f[$key]=getPost($key);
}


//-------- SQL Database ------------------

function sql_insert($tbl, $data, $db=NULL)
{
  if ($db==NULL) $db=Model_BSX_Core::$db;
  $row=DB::insert($tbl,array_keys($data))->values($data)->execute($db);
  if (is_array($row)) return $row[0]; else return $row;
}

function sql_update($tbl, $data, $id, $fieldid='id', $db=NULL)
{
  if ($db==NULL) $db=Model_BSX_Core::$db;
  $row=DB::update($tbl)->set($data)->where($fieldid,'=',$id)->execute($db);
  if (is_array($row)) return $row[0]; else return $row;;
}

function sql_query($query, $data=false, $db=NULL)
{
  if ($db==NULL) $db=Model_BSX_Core::$db;
  if (strtolower(substr($query,0,6))=='select' || strtolower(substr($query,0,8))=='describe')
  {
    return sql_rows($query,$data,$db);
  } else
  {
      $query=trim($query);
      if ($data && !is_array($data)) {
          if ($query[strlen($query)-1]=='=') {
              $query.=':record';
              $data=array(':record'=>$data);
          } else {
              $p=strrpos($query,':');
              if ($p!==FALSE) {
                  $v='';
                  for ($i=$p+1;$i<strlen($query);$i++) {
                      if ($query[$i]==' ' || $query[$i]==',') {
                          $v=substr($query,$p,$i-$p);
                          break;
                      }
                  }
                  if ($v=='') $v=substr($query,$p);
                  $data=array($v=>$data);
              } else {
                  $data=array(':id'=>$data);
              }
          }
      }

      $row = DB::query(Database::UPDATE, $query);
      if ($data && is_array($data)) $row = $row->parameters($data);
      $row = $row->execute($db);
      if (is_array($row)) return $row[0]; else return $row;
  }
}

function sql_add_standard_fields(&$e,$userId=0, $db=NULL)
{
    $e['add_time']=date('Y-m-d H:i:s');
    $e['modyf_time']=$e['add_time'];
    if ($userId>0) {
        $e['add_id_user']=$userId;
        $e['modyf_id_user']=$e['add_id_user'];
    }
}

function sql_loadfromquery(&$e, $query, $data=false, $list='', $db=NULL)
{
    $r=sql_row($query, $data, $db);
    if (!$r) return;
    $list=explode(';',$list);
    foreach ($list as $item) {
        $m=explode('=',$item);
        $a=$m[0];
        if (empty($m[1])) $b=$a; else $b=$m[1];
        if (isset($r[$b])) $e[$a]=$r[$b];
    }
}

function sql_rows($query, $data=false, $db=NULL)
{
    if ($db==NULL) $db=Model_BSX_Core::$db;
    if ($data && !is_array($data)) {
        if ($query[strlen($query)-1]=='=') {
            $query.=':record';
            $data=array(':record'=>$data);
        } else {
            $p=strrpos($query,':');
            if ($p!==FALSE) {
                $v='';
                for ($i=$p+1;$i<strlen($query);$i++) {
                    if ($query[$i]==' ' || $query[$i]==',') {
                        $v=substr($query,$p,$i-$p);
                        break;
                    }
                }
                if ($v=='') $v=substr($query,$p);
                $data=array($v=>$data);
            } else {
                $data=array(':id'=>$data);
            }
        }
    }
    $row = DB::query(Database::SELECT, $query);
    if ($data && is_array($data)) $row = $row->parameters($data);
    $row = $row->execute($db);
    $row = $row->as_array();
    return $row;
}

function sql_rows_id($query, $data=false, $col='id', $db=NULL)
{
     $rel=array();
     $rows=sql_rows($query,$data, $db);
     foreach ($rows as $row) $rel[$row[$col]]=$row;
     return $rel;
}

function sql_rows_group($query, $data=false, $col=false, $db=NULL)
{
    $rows=sql_rows($query,$data, $db);
    if ($col===false) return $rows;
    $rel=array();
    foreach ($rows as $row) $rel[$row[$col]][]=$row;
    return $rel;
}

function sql_rows_asselect($query,  $format, $data=null, $id='id')
{
     $res=array();
     $rows=sql_rows($query,$data);
     foreach ($rows as $row)
     {
          $v=$format;
          foreach ($row as $a=>$b) $v=str_replace('{'.$a.'}',$b,$v);
          $res[$row[$id]]=$v;
     }
     return $res;
}

function sql_row($query, $data=false, $db=NULL)
{
    if ($db==NULL) $db=Model_BSX_Core::$db;
    $query=trim($query);
    if ($data && !is_array($data)) {
        if ($query[strlen($query)-1]=='=') {
            $query.=':record';
            $data=array(':record'=>$data);
        } else {
            $p=strrpos($query,':');
            if ($p!==FALSE) {
                $v='';
                for ($i=$p+1;$i<strlen($query);$i++) {
                    if ($query[$i]==' ' || $query[$i]==',') {
                        $v=substr($query,$p,$i-$p);
                        break;
                    }
                }
                if ($v=='') $v=substr($query,$p);
                $data=array($v=>$data);
            } else {
                $data=array(':id'=>$data);
            }
        }
    }
    if (stripos($query,' limit ')===FALSE) $query.=' LIMIT 1';

    $row = DB::query(Database::SELECT, $query);
    if ($data && is_array($data)) $row = $row->parameters($data);
    $row = $row->execute($db);
    $row = $row->as_array();
    if (isset($row[0])) return $row[0]; else return false;
}

function sql_rowexists($table, $query, $data=false, $db=NULL)
{
    if (is_numeric($query)) $query=' id='.$query;
    $r=sql_row('SELECT id FROM '.$table.' WHERE '.$query,$data,$db);
    if ($r) return true; else return false;
}

function sql_duplicate_row($table, $where, $modyfArray=NULL, $db=NULL)
{
    if ($db==NULL) $db=Model_BSX_Core::$db;
    if (is_numeric($where)) $r=sql_row('SELECT * FROM '.$table.' WHERE id=:id',array(':id'=>$where),$db);
    else $r=sql_row('SELECT * FROM '.$table.' WHERE '.$where,$db);
    unset($r['id']);
    if ($modyfArray!=NULL && is_array($modyfArray)) {
        foreach ($modyfArray as $a=>$b) $r[$a]=$b;
    }
    return sql_insert($table,$r,$db);
}

function sql_getvalue($query, $data=false, $default=null, $db=NULL)
{
    if ($db==NULL) $db=Model_BSX_Core::$db;
    $w=sql_row($query,$data, $db);
    if (!$w) return $default;
    return reset($w);
}

function sql_count($query, $data=false, $db=NULL)
{
  $r=sql_row($query,$data,$db);
  if (!$r) return 0;
  return (int)current($r);
}

function sql_start($db=NULL)
{
  if ($db==NULL) $db=Model_BSX_Core::$db;
  $db->begin();
}

function sql_commit($db=NULL)
{
  if ($db==NULL) $db=Model_BSX_Core::$db;
  $db->commit();
}

function sql_lock($table, $tryb='write',$db=NULL)
{
    sql_query('LOCK TABLES '.$table.' '.$tryb);
}

function sql_unlockall($db=NULL)
{
    sql_query('UNLOCK TABLES');
}

function sql_tableexists($table, $db=NULL)
{
    if ($db==NULL) $db=Model_BSX_Core::$db;
    $row = DB::query(Database::SELECT, 'SHOW TABLES LIKE "'.$table.'"');
    $row = $row->execute($db);
    $row = $row->as_array();
    if (isset($row[0])) return true; else return false;
}

function z2n($d) {
    $d=(int)$d;
    if ($d<=0) return null; else return $d;
}

function sql_rows_ext($fields, $subQuery, $params, &$count) { 
    $rows=sql_rows('SELECT '.$fields.' FROM '.$subQuery.' LIMIT :start, :count', $params);
    $len=sql_row('SELECT count(*) FROM '.$subQuery,$params);
    if ($len) $count=$len['count(*)']; else $count=0;
    // echo 'SELECT '.$fields.' FROM '.$subQuery.' LIMIT :start, :count';
    // exit;
    return $rows;
}

function sql_buildwhere($params, $search,$addWhere=false, $ommit=false) {
    if($ommit) return '';
    $search=getSecurityLike($search);
    // if (strlen($search)<=1) return '';
    if (strlen($search)<=0) return '';

    if (is_string($params)) $params=array($params);
    $q='( ';
    foreach ($params as $param) {
        $q.='('.$param.' LIKE "%'.$search.'%") OR ';
    }
    $q=substr($q,0,-3);
    $q.=' ) ';
    if ($addWhere) return ' WHERE '.$q;
    else return ' AND '.$q;
}

function sql_buildorder($arr, $default, $param, $desc='1') {
  if ($desc=='1') $desc=' DESC'; else $desc=' ASC';
  if (!in_array($param,$arr)) return ' ORDER BY '.$default.$desc;
  else return ' ORDER BY '.$param.$desc;
}
