<?php defined('SYSPATH') or die('No direct script access.');

//require_once('Markdown.php');

class Model_BSX_CMS
{
    private static $_init = false;
    public static $currency = 'PLN';
    public static $calculationMethod = 0;
    public static $countryList = array('Polska', 'Niemcy');

    public static function init()
    {
        if (Model_BSX_CMS::$_init) return Model_BSX_CMS::$_init;

        Model_BSX_CMS::$_init = new Model_BSX_CMS();
        return Model_BSX_CMS::$_init;
    }

    private function genPaginations($start, $limit, $count, $link)
    {
        $wstecz = $start - $limit;
        if ($wstecz < 0) $wstecz = 0;

        $pagination = array();

        //$link='javascript:bsxTable('.($this->ajax?'1':'0').',\''.$this->formID.'\',\'refresh\',{start})';

        if ($start > 0) {
            $pagination['first'] = str_replace('{start}', 0, $link);
            $pagination['first_caption'] = 'Start';

            $pagination['previous'] = str_replace('{start}', $wstecz, $link);
            $pagination['previous_caption'] = 'Wstecz';
        }

        if ($count > 0) {
            $akt = (int)(($start) / $limit) + 1;
            $od = $akt - 5;
            $do = $akt + 5;
            if ($od < 1) $od = 1;

            if ($do * $limit > $count) {
                $do = floor($count / $limit);
                if ($do * $limit < $count) $do++;
            }
            if ($do <= 0) $do = $akt;

            for ($t = $od; $t <= $do; $t++) {
                $p = (($t - 1) * $limit);
                $page = array('caption' => $t, 'url' => str_replace('{start}', $p, $link));
                if ($t == $akt) {
                    $page['active'] = true;
                }
                $pagination['pages'][] = $page;
            }
        }
        if ($start + $limit < $count) {
            $p = $start + $limit;

            $pagination['end'] = str_replace('{start}', 'last', $link);
            $pagination['end_caption'] = 'Ostatnia';

            $pagination['next'] = str_replace('{start}', $p, $link);;
            $pagination['next_caption'] = 'Dalej';
        }

        return $pagination;
    }

    public static function getHtmlBlock($pident) {
        $params=array(':pident'=>$pident);
        $row=sql_row('SELECT * FROM bsc_blocks WHERE pstatus=1 AND pident=:pident',$params);

        if ($row) {
            $rowId = $row['id'];
            $elementRows = sql_rows('SELECT * FROM bsc_blocks_pr WHERE pstatus=1 AND iddoc=:iddoc', array(':iddoc'=>$rowId) );
            $row['photos']=sql_rows('SELECT id, pid, pname as apname, ptable FROM bs_attachments WHERE ptable="bsc_blocks" AND pid=:pid AND pstatus=2 AND prating=100 ORDER BY modyf_time DESC',array(':pid'=>$rowId));
            $row['photos']=array_merge($row['photos'],sql_rows('SELECT id, pid, pname as apname, ptable FROM bs_attachments WHERE ptable="bsc_blocks" AND pid=:pid AND pstatus=2 AND prating<>100 ORDER BY modyf_time DESC',array(':pid'=>$rowId)));

            foreach ($elementRows as $element) {
                $row['elements'][$element['pident']] = $element;
                $rowId = $element['id'];
                $row['elements'][$element['pident']]['photos']=sql_rows('SELECT id, pid, pname as apname, ptable FROM bs_attachments WHERE ptable="bsc_blocks_pr" AND pid=:pid AND pstatus=2 AND prating=100 ORDER BY modyf_time DESC',array(':pid'=>$rowId));
                $row['elements'][$element['pident']]['photos']=array_merge($row['elements'][$element['pident']]['photos'],sql_rows('SELECT id, pid, pname as apname, ptable FROM bs_attachments WHERE ptable="bsc_blocks_pr" AND pid=:pid AND pstatus=2 AND prating<>100 ORDER BY modyf_time DESC',array(':pid'=>$rowId)));
            }
        }

        return $row;
    }
    
    // ******************* NEWSY **************************

    private function correctNews(&$row, $prefix, $full=true,$controller=false)
    {
        $row['url']=$prefix.'/'.$row['pmodrewrite'];

        if (!empty($row['phead'])) $row['phead']=Model_BSX_Core::parseText($row['phead'],true,$controller);
        if (!empty($row['pbody'])) $row['pbody']=Model_BSX_Core::parseText($row['pbody'],true,$controller);
    }
    
    public function getNewsCategories($prefix='news') {
        $params=array(':idsite'=>Model_BSX_Core::$bsx_cfg['id']);
        $rows=sql_rows('SELECT pcat, count(*) as count FROM bsc_news WHERE pstatus=1 AND (idsite=:idsite OR idsite=0 OR idsite IS NULL) GROUP BY pcat',$params);
        foreach ($rows as &$row) {
            $row['url']=$prefix.'/'.$row['pcat'];
        }
        return $rows;
    }

    public function getNewsCategory($modrewrite) {
        $params=array(':idsite'=>Model_BSX_Core::$bsx_cfg['id'], ':cat'=>$modrewrite);
        $row=sql_row('SELECT * FROM bsc_news WHERE pstatus=1 AND (idsite=:idsite OR idsite=0 OR idsite IS NULL) AND pcat=:cat',$params);
        return $row;
    }

    public function getNewsList($prefix,$link,$start=0,$limit=10,$showfull=false,$search='',$cat=false) {
        $system=Model_BSX_System::init();

        if ($start<0) $start=0;
        $addLeftJoin=$addLeftJoinWhere='';
        $params=array(':idsite'=>Model_BSX_Core::$bsx_cfg['id']);

        $addQ='';
        if ($search!='') {
            $params[':search']='%'.$search.'%';
            $addQ.=' AND ptitle LIKE :search';
        }
        if ($cat) {
            $params[':cat']=$cat['pcat'];
            $addQ.=' AND pcat=:cat';
        }

        $count=sql_row('SELECT count(*) FROM bsc_news i
                           '.$addLeftJoin.'
                           WHERE i.pstatus=1 AND (idsite=:idsite OR idsite=0 OR idsite IS NULL) '.$addLeftJoinWhere.$addQ,$params);
        if ($count) $count=$count['count(*)']; else $count=0;

        $cnt=0;
        $rows=sql_rows('SELECT t.ptitle, t.phead, t.pdate, t.pshows,t.pauthor,t.pmodrewrite, 
                               a.ptable, a.pid, a.pname AS apname
                        FROM
                          (SELECT i.id, i.ptitle, i.pimageid, i.phead, i.pdate, i.pshows, i.pauthor, i.pmodrewrite FROM bsc_news i
                           '.$addLeftJoin.'
                           WHERE i.pstatus=1 AND (idsite=:idsite OR idsite=0 OR idsite IS NULL) '.$addLeftJoinWhere.$addQ.'
                           ORDER BY i.pdate DESC, i.id DESC
                           LIMIT '.$start.','.$limit.'
                          ) AS t
                        LEFT JOIN bs_attachments a ON a.id=t.pimageid
                        ',$params);
        foreach ($rows as &$row)
        {
            $this->correctNews($row,$prefix,$showfull);
            $cnt++;
        }
        return array('search'=>$search,'count'=>$count,'start'=>$start+1,'end'=>$start+$cnt,'rows'=>$rows,'pagination'=>$this->genPaginations($start,$limit,$count,$link));
    }

    public function getNewsByModrewrite($modrewrite, $prefix='news') {
        $row = sql_row('SELECT * FROM bsc_news WHERE (idsite=:idsite OR idsite=0 OR idsite IS NULL) AND pmodrewrite=:modrewrite AND pstatus=1',array(':idsite'=>Model_BSX_Core::$bsx_cfg['id'],':modrewrite'=>$modrewrite));
        if (!$row) return false;
        $this->correctNews($row,$prefix,true);
        $row['images']=sql_rows('SELECT a.ptable, a.pid, a.pname AS apname FROM bs_attachments a WHERE pstatus=2 AND ptable="bsc_news" AND pid=:pid',array(':pid'=>$row['id']));
        return $row;
    }

    // ******************* NEWSY **************************

    private function correctBlog(&$row, $prefix, $full=true,$controller=false)
    {
        $row['url']=$prefix.'/'.$row['pmodrewrite'];

        if (!empty($row['phead'])) $row['phead']=Model_BSX_Core::parseText($row['phead'],true,$controller);
        if (!empty($row['pbody'])) $row['pbody']=Model_BSX_Core::parseText($row['pbody'],true,$controller);
    }

    public function getBlogCategories($prefix='news') {
        $params=array(':idsite'=>Model_BSX_Core::$bsx_cfg['id']);
        $rows=sql_rows('SELECT pcat, count(*) as count FROM bsc_blog WHERE pstatus=1 AND (idsite=:idsite OR idsite=0 OR idsite IS NULL) GROUP BY pcat',$params);
        foreach ($rows as &$row) {
            $row['url']=$prefix.'/'.$row['pcat'];
        }
        return $rows;
    }

    public function getBlogCategory($modrewrite) {
        $params=array(':idsite'=>Model_BSX_Core::$bsx_cfg['id'], ':cat'=>$modrewrite);
        $row=sql_row('SELECT * FROM bsc_blog WHERE pstatus=1 AND (idsite=:idsite OR idsite=0 OR idsite IS NULL) AND pcat=:cat',$params);
        return $row;
    }

    public function getBlogList($prefix,$link,$start=0,$limit=10,$showfull=false,$search='',$cat=false) {
        $system=Model_BSX_System::init();

        if ($start<0) $start=0;
        $addLeftJoin=$addLeftJoinWhere='';
        $params=array(':idsite'=>Model_BSX_Core::$bsx_cfg['id']);

        $addQ='';
        if ($search!='') {
            $params[':search']='%'.$search.'%';
            $addQ.=' AND ptitle LIKE :search';
        }
        if ($cat) {
            $params[':cat']=$cat['pcat'];
            $addQ.=' AND pcat=:cat';
        }

        $count=sql_row('SELECT count(*) FROM bsc_blog i
                           '.$addLeftJoin.'
                           WHERE i.pstatus=1 AND (idsite=:idsite OR idsite=0 OR idsite IS NULL) '.$addLeftJoinWhere.$addQ,$params);
        if ($count) $count=$count['count(*)']; else $count=0;

        $cnt=0;
        $rows=sql_rows('SELECT t.ptitle, t.phead, t.pdate, t.pshows,t.pauthor,t.pmodrewrite, 
                               a.ptable, a.pid, a.pname AS apname
                        FROM
                          (SELECT i.id, i.ptitle, i.pimageid, i.phead, i.pdate, i.pshows, i.pauthor, i.pmodrewrite FROM bsc_blog i
                           '.$addLeftJoin.'
                           WHERE i.pstatus=1 AND (idsite=:idsite OR idsite=0 OR idsite IS NULL) '.$addLeftJoinWhere.$addQ.'
                           ORDER BY i.pdate DESC, i.id DESC
                           LIMIT '.$start.','.$limit.'
                          ) AS t
                        LEFT JOIN bs_attachments a ON a.id=t.pimageid
                        ',$params);
        foreach ($rows as &$row)
        {
            $this->correctBlog($row,$prefix,$showfull);
            $cnt++;
        }
        return array('search'=>$search,'count'=>$count,'start'=>$start+1,'end'=>$start+$cnt,'rows'=>$rows,'pagination'=>$this->genPaginations($start,$limit,$count,$link));
    }

    public function getBlogByModrewrite($modrewrite, $prefix='news') {
        $row = sql_row('SELECT * FROM bsc_blog WHERE (idsite=:idsite OR idsite=0 OR idsite IS NULL) AND pmodrewrite=:modrewrite AND pstatus=1',array(':idsite'=>Model_BSX_Core::$bsx_cfg['id'],':modrewrite'=>$modrewrite));
        if (!$row) return false;
        $this->correctNews($row,$prefix,true);
        $row['images']=sql_rows('SELECT a.ptable, a.pid, a.pname AS apname FROM bs_attachments a WHERE pstatus=2 AND ptable="bsc_blog" AND pid=:pid',array(':pid'=>$row['id']));
        return $row;
    }
    // ******************* WYDARZENIA **************************

    private function correctEvent(&$row, $prefix, $full=true,$controller=false)
    {
        $row['url']=$prefix.'/'.$row['pmodrewrite'];

        if (!empty($row['phead'])) $row['phead']=Model_BSX_Core::parseText($row['phead'],true,$controller);
        if (!empty($row['pbody'])) $row['pbody']=Model_BSX_Core::parseText($row['pbody'],true,$controller);
    }

    public function getEventsCategory($modrewrite) {
        $params=array(':idsite'=>Model_BSX_Core::$bsx_cfg['id'], ':cat'=>$modrewrite);
        $row=sql_row('SELECT * FROM bsc_events WHERE pstatus=1 AND (idsite=:idsite OR idsite=0 OR idsite IS NULL) AND pcat=:cat',$params);
        return $row;
    }


    public function getEventsCategories($prefix='news') {
        $params=array(':idsite'=>Model_BSX_Core::$bsx_cfg['id']);
        $rows=sql_rows('SELECT pcat, count(*) as count FROM bsc_events WHERE pstatus=1 AND (idsite=:idsite OR idsite=0 OR idsite IS NULL) GROUP BY pcat',$params);
        foreach ($rows as &$row) {
            $row['url']=$prefix.'/'.$row['pcat'];
        }
        return $rows;
    }

    public function getEventsList($prefix,$link,$start=0,$limit=10,$showfull=false,$mode='all',$search='',$cat=false) {
        $system=Model_BSX_System::init();

        if ($start<0) $start=0;
        $addLeftJoin=$addLeftJoinWhere='';
        $params=array(':idsite'=>Model_BSX_Core::$bsx_cfg['id'],':time'=>date('Y-m-d H:i:s'));
        $addQ='';
        $sort='i.pdate DESC';
        if ($mode=='new') {
            $addQ.=' AND pdate>:time';
            $sort='i.pdate ASC';
        }

        if ($search!='') {
            $params[':search']='%'.$search.'%';
            $addQ.=' AND ptitle LIKE :search';
        }
        if ($cat) {
            $params[':cat']=$cat['pcat'];
            $addQ.=' AND pcat=:cat';
        }

        
        $count=sql_row('SELECT count(*) FROM bsc_events i
                           '.$addLeftJoin.'
                           WHERE i.pstatus=1 AND (idsite=:idsite OR idsite=0 OR idsite IS NULL) '.$addLeftJoinWhere.$addQ,$params);
        if ($count) $count=$count['count(*)']; else $count=0;

        $cnt=0;
        $rows=sql_rows('SELECT t.ptitle, t.phead, t.pdate, t.pshows,t.pauthor,t.pmodrewrite, t.pcat, t.pplace,
                               a.ptable, a.pid, a.pname AS apname
                        FROM
                          (SELECT i.id, i.ptitle, i.pimageid, i.phead, i.pdate, i.pshows, i.pauthor, i.pmodrewrite, i.pcat, i.pplace FROM bsc_events i
                           '.$addLeftJoin.'
                           WHERE i.pstatus=1 AND (idsite=:idsite OR idsite=0 OR idsite IS NULL) '.$addLeftJoinWhere.$addQ.'
                           ORDER BY '.$sort.', i.id DESC
                           LIMIT '.$start.','.$limit.'
                          ) AS t
                        LEFT JOIN bs_attachments a ON a.id=t.pimageid
                        ',$params);
        foreach ($rows as &$row)
        {
            $this->correctEvent($row,$prefix,$showfull);
            $cnt++;
        }
        return array('count'=>$count,'start'=>$start+1,'end'=>$start+$cnt,'rows'=>$rows,'pagination'=>$this->genPaginations($start,$limit,$count,$link));
    }

    public function getEventsByModrewrite($modrewrite, $prefix='news') {
        $row = sql_row('SELECT * FROM bsc_events WHERE (idsite=:idsite OR idsite=0 OR idsite IS NULL) AND pmodrewrite=:modrewrite AND pstatus=1',array(':idsite'=>Model_BSX_Core::$bsx_cfg['id'],':modrewrite'=>$modrewrite));
        if (!$row) return false;
        $this->correctNews($row,$prefix,true);
        $row['images']=sql_rows('SELECT a.ptable, a.pid, a.pname AS apname FROM bs_attachments a WHERE pstatus=2 AND ptable="bsc_events" AND pid=:pid',array(':pid'=>$row['id']));
        return $row;
    }

    // ******************* ARTYKUŁY **************************

    private function correctArticle(&$row, $prefix, $full=true, $controller=false)
    {
        $row['url']=$prefix.'/'.$row['pmodrewrite'];

        $markdown=true;
        if (isset($row['popc_mark']))
        {
            if ($row['popc_mark']==1) $markdown=true; else $markdown=false;
        }

        if (!empty($row['phead'])) $row['phead']=Model_BSX_Core::parseText($row['phead'],$markdown,$controller);
        if (!empty($row['pbody'])) $row['pbody']=Model_BSX_Core::parseText($row['pbody'],$markdown,$controller);

    }

    public function getArticleByModrewrite($modrewrite, $prefix='article') {
        if (is_numeric($modrewrite)) $row = sql_row('SELECT * FROM bsc_articles WHERE (idsite=:idsite OR idsite=0 OR idsite IS NULL) AND id=:modrewrite AND pstatus=1',array(':idsite'=>Model_BSX_Core::$bsx_cfg['id'],':modrewrite'=>$modrewrite));
                                else $row = sql_row('SELECT * FROM bsc_articles WHERE (idsite=:idsite OR idsite=0 OR idsite IS NULL) AND pmodrewrite=:modrewrite AND pstatus=1',array(':idsite'=>Model_BSX_Core::$bsx_cfg['id'],':modrewrite'=>$modrewrite));
        if (!$row) return false;
        $this->correctArticle($row,$prefix,true);
        $row['images']=sql_rows('SELECT a.ptable, a.pid, a.pname AS apname FROM bs_attachments a WHERE pstatus=2 AND ptable="bsc_articles" AND pid=:pid',array(':pid'=>$row['id']));
        return $row;
    }

    public function getArticles($prefix='article') {
        $results = sql_rows('SELECT id, ptitle, pmodrewrite FROM bsc_articles WHERE (idsite=:idsite OR idsite=0 OR idsite IS NULL) AND pstatus=1',array(':idsite'=>Model_BSX_Core::$bsx_cfg['id']));
        foreach ($results as &$row)
        {
            $row['url']=$prefix.'/'.$row['pmodrewrite'];
        }
        return $results;
    }
}