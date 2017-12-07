<?php defined('SYSPATH') or die('No direct script access.');

//require_once('Markdown.php');

class Model_BSX_Shop
{
    private static $_init   = false;
    public static $currency = 'PLN';
    public static $calculationMethod=0;
    public static $countryList=array('Polska','Niemcy');
    public static $attachmentPlace=-1;
    public static $attachmentFolder='';

    public static function init() {
        if (Model_BSX_Shop::$_init) return Model_BSX_Shop::$_init;

        Model_BSX_Shop::$_init=new Model_BSX_Shop();
        return Model_BSX_Shop::$_init;
    }

    private function genPaginations($start,$limit,$count,$link)
    {
        $wstecz=$start-$limit;
        if ($wstecz<0) $wstecz=0;

        $pagination=array();

        //$link='javascript:bsxTable('.($this->ajax?'1':'0').',\''.$this->formID.'\',\'refresh\',{start})';

        if ($start>0)
        {
            $pagination['first']=str_replace('{start}',0,$link);
            $pagination['first_caption']='Start';

            $pagination['previous']=str_replace('{start}',$wstecz,$link);
            $pagination['previous_caption']='Wstecz';
        }

        if ($count>0)
        {
            $akt=(int)(($start)/$limit)+1;
            $od=$akt-5;
            $do=$akt+5;
            if ($od<1) $od=1;

            if ($do*$limit>$count)
            {
                $do=floor($count/$limit);
                if ($do*$limit<$count) $do++;
            }
            if ($do<=0) $do=$akt;

            for ($t=$od;$t<=$do;$t++)
            {
                $p=(($t-1)*$limit);
                $page=array('caption'=>$t,'url'=>str_replace('{start}',$p,$link));
                if ($t==$akt)
                {
                    $page['active']=true;
                }
                $pagination['pages'][]=$page;
            }
        }
        if ($start+$limit<$count)
        {
            $p=$start+$limit;

            $pagination['end']=str_replace('{start}','last',$link);
            $pagination['end_caption']='Ostatnia';

            $pagination['next']=str_replace('{start}',$p,$link);;
            $pagination['next_caption']='Dalej';
        }

        return $pagination;
    }

    private function correctProduct(&$row, $full=true, $recalculatePrice=true)
    {
        if (!empty($row['wslug'])) $row['url']=Model_BSX_Core::$bsx_cfg['pshop_prefix'].'/'.$row['wslug'];
        else {
            //$row['url']=Model_BSX_Core::$bsx_cfg['pshop_prefix'].'/'.Model_BSX_Core::create_friendly_name($row['pname']);
            $row['url']=Model_BSX_Core::$bsx_cfg['pshop_prefix'].'/'.$row['id'].'/'.Model_BSX_Core::create_friendly_name($row['pname']);
        }

       if (!empty($row['wtags']) && $row['wtags'][strlen($row['wtags'])-1]==';') $row['wtags']=substr($row['wtags'],0,-1);

        $row['group_slugs']='';
        if ($full) {
            $rr = sql_rows('SELECT g.wslug FROM bs_groups_pr p LEFT JOIN bs_groups g ON g.id=p.idg WHERE p.idp=:idp AND p.ptable=:table', array(':idp' => $row['id'], ':table' => 'bs_stockindex'));
            foreach ($rr as $r) $row['group_slugs'] .= $r['wslug'] . ' ';
        }

        $this->addProductAttributes($row);

        if (empty($row['pnetto']) && empty($row['pbrutto'])) {
            $row['price'] = '';
            $row['isPrice']=false;
        } else
        if (!empty($row['pnetto']) && $row['pnetto']<=0)
        {
            $row['price'] = '';
            $row['isPrice']=false;
        } else
        if (!empty($row['pbrutto']) && $row['pbrutto']<=0)
        {
                $row['price'] = '';
                $row['isPrice']=false;
        } else
        if (isset($row['pbrutto']))
        {
            $row['isPrice']=true;

            if (isset($row['attributes'])) {
                if ($recalculatePrice) {
                    $this->recalculatePrice($row, $this->getSelectedAttributesArray($row));
                }
            }

            if (Model_BSX_Core::$bsx_cfg['pricenb'] == 0) {
                $row['price'] = BinUtils::price($row['pbrutto'], Model_BSX_Shop::$currency);
            } else {
                $row['price'] = BinUtils::price($row['pnetto'], Model_BSX_Shop::$currency);
            }

        }
        if (isset($row['woldpriceb'])) $row['woldprice'] = BinUtils::price($row['woldpriceb'], Model_BSX_Shop::$currency);
        if (isset($row['wopc_price']) && $row['wopc_price']==1) $row['hidePrice']=true;
        if (isset($row['wopc_from']) && $row['wopc_from']==1) $row['price']='od '.$row['price'];


        if (!empty($row['pdesc'])) $row['pdesc']=Model_BSX_Core::parseText($row['pdesc'],true);
        if (!empty($row['wdesc'])) $row['wdesc']=Model_BSX_Core::parseText($row['wdesc'],true);

        $row['url_addCart']=Model_BSX_Core::$bsx_cfg['pshop_prefix'].'/cart/add/'.$row['id'];

        $row['currency'] = Model_BSX_Shop::$currency;

    }

    public function getProductsListFromCategory($type,$data,$link,$start=0,$limit=10,$showfull=false,$orderby='') {
        $system=Model_BSX_System::init();

        if ($start<0) $start=0;
        $addLeftJoin=$addLeftJoinWhere='';
        $params=array(':idstock'=>Model_BSX_Core::$bsx_cfg['idm'], ':idprice'=>Model_BSX_Core::$bsx_cfg['idprice'],);

        if ($type=='tag')
        { //tylko z danym tagiem
            $addLeftJoinWhere.=' AND i.wtags LIKE :tag';
            $params[':tag']='%'.$data['pname'].';%';
        } else if ($type=='search')
        {
            $addLeftJoinWhere.=' AND i.pname LIKE :search';
            $params[':search']='%'.$data.'%';
        } else if ($type=='category')
        {
            $ids=sql_row('SELECT idkids FROM bs_groups WHERE id=:id',array(':id'=>$data['id']));
            if ($ids) {
                $gr='';
                $w = explode(';', $ids['idkids']);
                foreach ($w as $ww) if ($ww!='') $gr.=$ww.',';
                if ($gr!='') $gr=substr($gr,0,-1);
                if ($gr!='') $gr=' IN ('.$gr.')';
                if ($gr!='') {
                    $addLeftJoin = 'LEFT JOIN bs_groups_pr gr ON gr.idp=i.id';
                    $addLeftJoinWhere .= ' AND gr.ptable=\'bs_stockindex\' AND gr.idg ' . $gr;
                }
            }
        } else if ($type=='last') {
            $orderby='t.id DESC';
        }

        if ($orderby=='') $orderby='t.wlp ASC, t.id DESC';

        if ($orderby!='') $orderby=' ORDER BY '.$orderby;

        if (isset(Model_BSX_Core::$bsx_cfg['opc_w1']) && Model_BSX_Core::$bsx_cfg['opc_w1']==1) $addLeftJoinWhere.=' AND p.pnetto>0'; //ukryj bez ceny
        if (isset(Model_BSX_Core::$bsx_cfg['opc_w2']) && Model_BSX_Core::$bsx_cfg['opc_w2']==1) $addLeftJoinWhere.=' AND r.pquantity>0'; //ukryj ze stanem zerowym

        $count=sql_row('SELECT count(*) FROM bs_stockindex i
                           LEFT JOIN bs_stockrel r ON r.idproduct=i.id
                           LEFT JOIN bs_stockprice p ON p.idproduct=i.id AND p.idprice=:idprice
                           '.$addLeftJoin.'
                           WHERE i.wopc_show=1 AND i.nstatus=2 AND r.idstock=:idstock '.$addLeftJoinWhere,$params);
        if ($count) $count=$count['count(*)']; else $count=0;

        $cnt=0;
        $rows=sql_rows('SELECT t.id, t.pname, t.pdesc, t.psymbol,t.wslug,t.woldpriceb,t.wopc_price,t.wopc_from, t.psrate_v,
                               t.pnetto,t.pbrutto, t.wlp,
                               a.ptable, a.pid, a.pname AS apname
                        FROM
                          (SELECT i.id, i.pname, i.mimageid, i.pdesc, i.prate_v as psrate_v, i.psymbol, i.wslug, p.pnetto, p.pbrutto, i.woldpriceb, i.wopc_price, i.wopc_from, i.wlp FROM bs_stockindex i
                           LEFT JOIN bs_stockrel r ON r.idproduct=i.id
                           LEFT JOIN bs_stockprice p ON p.idproduct=i.id AND p.idprice=:idprice
                           '.$addLeftJoin.'
                           WHERE i.wopc_show=1 AND i.nstatus=2 AND r.idstock=:idstock '.$addLeftJoinWhere.'
                           ORDER BY i.pname
                           LIMIT '.$start.','.$limit.'
                          ) AS t
                        LEFT JOIN bs_attachments a ON a.id=t.mimageid
                        '.$orderby.'
                        ',$params);
        foreach ($rows as &$row)
        {
            $this->correctProduct($row,$showfull);
            $cnt++;
        } 
        return array('count'=>$count,'start'=>$start+1,'end'=>$start+$cnt,'rows'=>$rows,'pagination'=>$this->genPaginations($start,$limit,$count,$link));
    }

    public function getTags()
    {
        $tags=Model_BSX_Core::$cache->getValue('tags',Array());
        if (count($tags)<=0) {
            $tags = sql_rows('SELECT id, pname, wslug FROM bs_stocktags LIMIT 10');
            foreach ($tags as &$tag) {
                if (!empty($tag['wslug'])) $tag['url'] = Model_BSX_Core::$bsx_cfg['pshop_prefix'] . '/' . $tag['wslug']; else $tag['url'] = Model_BSX_Core::$bsx_cfg['pshop_prefix'] . '/' . Model_BSX_Core::create_friendly_name($tag['pname']);
            }
            Model_BSX_Core::$cache->setValue('tags',$tags,BinMemCached::TIME_1H);
        }
        return $tags;
    }

    public function getManufactures()
    {
        return array();
    }

    public function getTopSells($limit)
    {
        $params=array(':idstock'=>Model_BSX_Core::$bsx_cfg['idm'], ':idprice'=>Model_BSX_Core::$bsx_cfg['idprice'],);
        $rows=sql_rows('SELECT t.id, t.pname, t.pdesc, t.psymbol,t.wslug,t.wnote,t.wlikes,t.woldpriceb,t.wopc_price,t.wopc_from,t.psrate_v.
                               p.pnetto,p.pbrutto,
                               a.ptable, a.pid, a.pname AS apname
                        FROM
                          (SELECT i.id, i.pname, i.mimageid, i.pdesc, i.psymbol, i.wslug, i.prate_v as psrate_v, i.wnote,i.wlikes,i.woldpriceb,i.wopc_price,i.wopc_from FROM
                           (SELECT idproduct, COUNT(*) AS ilosc FROM bs_invoices_pr WHERE idproduct>0 GROUP BY idproduct ORDER BY ilosc DESC LIMIT '.$limit.') AS u
                           LEFT JOIN bs_stockindex i ON i.id=u.idproduct
                           LEFT JOIN bs_stockrel r ON r.idproduct=i.id
                           WHERE i.wopc_show=1 AND i.nstatus=2 AND r.idstock=:idstock AND r.pquantity>0
                          ) AS t
                        LEFT JOIN bs_attachments a ON a.id=t.mimageid
                        LEFT JOIN bs_stockprice p ON p.idproduct=t.id AND p.idprice=:idprice
                        ',$params);
        foreach ($rows as &$row)
        {
            $this->correctProduct($row,false);
        }
        return $rows;
    }

    public function getFeatured($limit) //wyróżnione
    {
        $gr=sql_row('SELECT id FROM bs_groups WHERE wtype=:type AND idparent=:parent',array(':type'=>2,':parent'=>(int)Model_BSX_Core::$bsx_cfg['idcat']));
        if (!$gr) $gr=sql_row('SELECT id FROM bs_groups WHERE wtype=:type',array(':type'=>2,':parent'=>(int)Model_BSX_Core::$bsx_cfg['idcat']));
        if (!$gr) return array();


        $params=array(':idstock'=>Model_BSX_Core::$bsx_cfg['idm'], ':idprice'=>Model_BSX_Core::$bsx_cfg['idprice'],':idg'=>$gr['id']);

        $rows=sql_rows('SELECT t.id, t.pname, t.pdesc, t.psymbol,t.wslug,t.wnote,t.wlikes,t.woldpriceb,t.wopc_price,t.wopc_from,t.psrate_v,
                               p.pnetto,p.pbrutto,
                               a.ptable, a.pid, a.pname AS apname
                        FROM
                          (SELECT i.id, i.pname, i.mimageid, i.pdesc, i.psymbol, i.wslug, i.prate_v as psrate_v, i.wnote, i.wlikes, i.woldpriceb, i.wopc_price, i.wopc_from FROM bs_stockindex i
                           LEFT JOIN bs_stockrel r ON r.idproduct=i.id
                           LEFT JOIN bs_groups_pr gr ON gr.idp=i.id
                           WHERE i.wopc_show=1 AND i.nstatus=2 AND r.idstock=:idstock AND r.pquantity>0 AND gr.ptable=\'bs_stockindex\' AND gr.idg = :idg
                           ORDER BY i.pname
                           LIMIT 0,'.$limit.'
                          ) AS t
                        LEFT JOIN bs_attachments a ON a.id=t.mimageid
                        LEFT JOIN bs_stockprice p ON p.idproduct=t.id AND p.idprice=:idprice
                        ',$params);
        foreach ($rows as &$row)
        {
            $this->correctProduct($row,false);
        }
        return $rows;
    }

    public function getSpecial($limit) //specjalne
    {
        $gr=sql_row('SELECT id FROM bs_groups WHERE wtype=:type AND idparent=:parent',array(':type'=>3,':parent'=>(int)Model_BSX_Core::$bsx_cfg['idcat']));
        if (!$gr) $gr=sql_row('SELECT id FROM bs_groups WHERE wtype=:type',array(':type'=>3,':parent'=>(int)Model_BSX_Core::$bsx_cfg['idcat']));
        if (!$gr) return array();


        $params=array(':idstock'=>Model_BSX_Core::$bsx_cfg['idm'], ':idprice'=>Model_BSX_Core::$bsx_cfg['idprice'],':idg'=>$gr['id']);

        $rows=sql_rows('SELECT t.id, t.pname, t.pdesc, t.psymbol,t.wslug,t.wnote,t.wlikes, t.woldpriceb, t.wopc_price,t.wopc_from,t.psrate_v,
                               p.pnetto,p.pbrutto,
                               a.ptable, a.pid, a.pname AS apname
                        FROM
                          (SELECT i.id, i.pname, i.mimageid, i.pdesc, i.prate_v as psrate_v, i.psymbol, i.wslug, i.wnote, i.wlikes, i.woldpriceb, i.wopc_price, i.wopc_from FROM bs_stockindex i
                           LEFT JOIN bs_stockrel r ON r.idproduct=i.id
                           LEFT JOIN bs_groups_pr gr ON gr.idp=i.id
                           WHERE i.wopc_show=1 AND i.nstatus=2 AND r.idstock=:idstock AND r.pquantity>0 AND gr.ptable=\'bs_stockindex\' AND gr.idg = :idg
                           ORDER BY i.pname
                           LIMIT 0,'.$limit.'
                          ) AS t
                        LEFT JOIN bs_attachments a ON a.id=t.mimageid
                        LEFT JOIN bs_stockprice p ON p.idproduct=t.id AND p.idprice=:idprice
                        ',$params);
        foreach ($rows as &$row)
        {
            $this->correctProduct($row,false);
        }
        return $rows;
    }

    public function getRecomended($limit, $onStock=true, $orderby='') //specjalne
    {
        $gr=sql_row('SELECT id FROM bs_groups WHERE wtype=:type AND idparent=:parent',array(':type'=>1,':parent'=>(int)Model_BSX_Core::$bsx_cfg['idcat']));
        if (!$gr) $gr=sql_row('SELECT id FROM bs_groups WHERE wtype=:type',array(':type'=>1,':parent'=>(int)Model_BSX_Core::$bsx_cfg['idcat']));
        if (!$gr) return array();


        $params=array(':idstock'=>Model_BSX_Core::$bsx_cfg['idm'], ':idprice'=>Model_BSX_Core::$bsx_cfg['idprice'],':idg'=>$gr['id']);

        if ($onStock) $onStockAdd='AND r.pquantity>0'; else $onStockAdd='';

        if ($orderby=='') $orderby='t.wlp ASC, t.id DESC';
        if ($orderby!='') $orderby=' ORDER BY '.$orderby;

        $rows=sql_rows('SELECT t.id, t.pname, t.pdesc, t.psymbol,t.wslug,t.wnote,t.wlikes,t.woldpriceb,t.wopc_price,t.wopc_from,t.psrate_v,
                               p.pnetto,p.pbrutto,
                               a.ptable, a.pid, a.pname AS apname
                        FROM
                          (SELECT i.id, i.pname, i.mimageid, i.prate_v as psrate_v, i.pdesc, i.psymbol, i.wslug, i.wnote, i.wlikes, i.woldpriceb, i.wopc_price, i.wopc_from, i.wlp FROM bs_stockindex i
                           LEFT JOIN bs_stockrel r ON r.idproduct=i.id
                           LEFT JOIN bs_groups_pr gr ON gr.idp=i.id
                           WHERE i.wopc_show=1 AND i.nstatus=2 AND r.idstock=:idstock '.$onStockAdd.' AND gr.ptable=\'bs_stockindex\' AND gr.idg = :idg
                           ORDER BY i.pname
                           LIMIT 0,'.$limit.'
                          ) AS t
                        LEFT JOIN bs_attachments a ON a.id=t.mimageid
                        LEFT JOIN bs_stockprice p ON p.idproduct=t.id AND p.idprice=:idprice
                        '.$orderby,$params);
        foreach ($rows as &$row)
        {
            $this->correctProduct($row,false);
        }
        return $rows;
    }

    public function getProduct($id, $recalculatePrice=true) {
        $params=array(':idstock'=>Model_BSX_Core::$bsx_cfg['idm'], ':idprice'=>Model_BSX_Core::$bsx_cfg['idprice'],':id'=>$id);

        $product=sql_row('SELECT i.id, i.pname, i.pdesc, i.wdesc, i.ptype, i.prate_v as psrate_v, i.wtags, i.psymbol, i.pweight, i.wcechy, i.wnote, i.wlikes, i.wseo_desc, i.wseo_keyw,i.wseo_title,i.wslug,i.woldpriceb,i.wopc_price,i.wopc_from,
                                 p.pnetto, p.pbrutto,
                                 a.ptable, a.pid, a.pname AS apname
                          FROM bs_stockindex i
                          LEFT JOIN bs_attachments a ON a.id=i.mimageid
                          LEFT JOIN bs_stockprice p ON p.idproduct=i.id AND p.idprice=:idprice
                          WHERE i.id=:id AND i.wopc_show=1 AND i.nstatus=2',$params);
        if ($product)
        {
            $this->correctProduct($product,true,$recalculatePrice);
            $product['images']=sql_rows('SELECT a.ptable, a.pid, a.pname AS apname FROM bs_attachments a WHERE pstatus=2 AND ptable="bs_stockindex" AND pid=:pid',array(':pid'=>$product['id']));
            foreach ($product['images'] as &$image)
            {
                //$image['url_icon0']=Model_BSX_Core::cache_img($image['ptable'],$image['pid'],$image['apname'],262,262);
                //$image['url_icon1']=Model_BSX_Core::cache_img($image['ptable'],$image['pid'],$image['apname'],300,300);
                //$image['url_icon2']=Model_BSX_Core::cache_img($image['ptable'],$image['pid'],$image['apname'],800,800);
            }
        }
        return $product;
    }

    public function getProductBySymbol($symbol) {
        $r=sql_row('SELECT id FROM bs_stockindex WHERE psymbol=:symbol',array(':symbol'=>$symbol));
        if ($r) return $this->getProduct($r['id']);
        else return false;
    }

    public function getRelatedProducts($id) {
        $params=array(':idstock'=>Model_BSX_Core::$bsx_cfg['idm'], ':idprice'=>Model_BSX_Core::$bsx_cfg['idprice'],':idproduct'=>$id);

        $addLeftJoinWhere='';
        if (isset(Model_BSX_Core::$bsx_cfg['opc_w1']) && Model_BSX_Core::$bsx_cfg['opc_w1']==1) $addLeftJoinWhere.=' AND p.pnetto>0'; //ukryj bez ceny
        if (isset(Model_BSX_Core::$bsx_cfg['opc_w2']) && Model_BSX_Core::$bsx_cfg['opc_w2']==1) $addLeftJoinWhere.=' AND r.pquantity>0'; //ukryj ze stanem zerowym

        $rows=sql_rows('SELECT t.id, t.pname, t.pdesc, t.psymbol,t.woldpriceb,t.wopc_price,t.wopc_from, t.psrate_v,
                               t.pnetto,t.pbrutto,
                               a.ptable, a.pid, a.pname AS apname
                        FROM
                          (SELECT i.id, i.pname, i.mimageid, p.pnetto,p.pbrutto, i.pdesc, i.psymbol, i.woldpriceb, i.prate_v as psrate_v, i.wopc_price, i.wopc_from FROM bs_si_recom rr
                           LEFT JOIN bs_stockindex i ON i.id=rr.idrelproduct
                           LEFT JOIN bs_stockrel r ON r.idproduct=i.id
                           LEFT JOIN bs_stockprice p ON p.idproduct=i.id AND p.idprice=:idprice
                           WHERE rr.idproduct=:idproduct AND i.wopc_show=1 AND i.nstatus=2 AND r.idstock=:idstock '.$addLeftJoinWhere.'
                           ORDER BY i.pname
                           LIMIT 5
                          ) AS t
                        LEFT JOIN bs_attachments a ON a.id=t.mimageid
                        ',$params);
        foreach ($rows as &$row)
        {
            $this->correctProduct($row,false);
        }
        return $rows;
    }

    public function getOrderById($id) {
        $order=sql_row('SELECT * FROM bs_orders WHERE id=:id',array(':id'=>$id));
        if (!$order) return false;
        $order['products']=sql_rows('SELECT p.id, p.pname, p.pquantity, p.psprice_n, p.psprice_b, p.pstotal_n, p.pstotal_b,
                                    a.ptable, a.pid, a.pname AS apname
                                    FROM bs_orders_pr p
                                    LEFT JOIN bs_stockindex i ON i.id=p.idproduct
                                    LEFT JOIN bs_attachments a ON a.id=i.mimageid
                                    WHERE p.iddoc=:id',array(':id'=>$order['id']));
        foreach ($order['products'] as &$row)
        {
            $this->correctProduct($row,false);
        }
        return $order;
    }

    public function getOrderByMD5($md5) {
        $order=sql_row('SELECT id FROM bs_orders WHERE md5=:md5',array(':md5'=>$md5));
        if (!$order) return false;
        return $this->getOrderById($order['id']);
    }

    public function getCategories($idr=0) {
        $lista=array();
        //$lista=Model_BSX_Core::$cache->getValue('categories-'.$idr,Array());
        if (count($lista)<=0) {
            $rows = sql_rows('SELECT id, pname, wslug, idparent, wicon FROM bs_groups WHERE tblname=:t AND (wtype=0 OR wtype IS NULL) ORDER BY wspos, pname', array(':t' => 'bs_stockindex'));
            foreach ($rows as $row) {
                $lista[$row['idparent']][] = $row;
                //Model_BSX_Core::$cache->setValue('categories-'.$idr,$lista,BinMemCached::TIME_1H);
            }
			//BinUtils::pr($lista); exit;
        }
        return $lista;
    }



    private function addProductAttributes(&$product)
    {
        $rows=sql_rows('SELECT a.idattrpr, a.pdefault, b.id, b.idattr, b.mname, a.mprice 
							FROM bsc_stockattrs a LEFT JOIN bsc_attributes_pr b ON a.idattrpr=b.id
							WHERE a.idprod=:id ORDER BY b.idattr ASC, a.pdefault DESC, a.idattrpr ASC', array(':id'=>$product['id']));
        foreach ($rows as $row) {
            $product['attributes'][$row['idattr']][$row['id']] = array('mname'=>$row['mname'], 'mprice'=>$row['mprice'], 'pdefault'=>$row['pdefault']);
        }
    }

    public function recalculatePrice(&$product, $attArray=NULL) {
        // Handle price changing due to attribute selection
        if (!isset($attArray) || empty($attArray) || $product['pbrutto']<=0 || $product['pnetto']<=0) return false;

        $inClause = '(';
        foreach ($attArray as $item) {
            $inClause.= (string) $item;
            $inClause.= ',';
        }
        $inClause = substr($inClause, 0, -1);
        $inClause.= ')';

        $validationCount =  sql_row('SELECT COUNT(DISTINCT(idattr)) AS validationCount FROM bsc_stockattrs
									WHERE idattrpr IN '.$inClause)['validationCount'];
        if (count($attArray) != $validationCount) return $product['price'];

        $modAbsolutes = [];
        $modPercentages = [];
        foreach ($attArray as $idattrpr) {
            foreach ($product['attributes'] as $item) {
                if (isset($item[$idattrpr])) {
                    $priceMod = $item[$idattrpr]['mprice'];
                    // If price modificator is an absolute value
                    if (is_numeric($priceMod)) {
                        $modAbsolutes[] = $priceMod;
                    }
                    // if price modificator is a percentage
                    else if (substr($priceMod, -1) == '%' && is_numeric(substr($priceMod, 0, -1)) ) {
                        $modPercentages[] = substr($priceMod, 0, -1);
                    }

                    break;
                }
            }
        }

        $percentages = 0;
        foreach ($modPercentages as $percentage) {
            $percentages+= (float) $percentage;
        }
        if ($percentages!=0) $product['pbrutto'] += $product['pbrutto'] * ($percentages / 100);

        foreach ($modAbsolutes as $absolute) {
            $product['pbrutto']+= (float) $absolute;
        }

        if ($product['pbrutto'] < 0) $product['pbrutto'] = 0;
        $vat=1+($product['psrate_v']/100);
        if ($vat>0) $product['pnetto'] = $product['pbrutto'] / $vat; else $product['pnetto']=$product['pbrutto'];

        return true;
    }

    public function getSelectedAttributesArray($product) {
        if (!isset($product['attributes'])) return false;
        $returnArray = [];
        foreach ($product['attributes'] as $attributes) {
            foreach ($attributes as $attributeID=>$attributeValues) {
                $returnArray[] =  $attributeID;
                break;
            }
        }
        return $returnArray;
    }

    public function getAttributeNames($IDarray) {
        // validate
        if (empty($IDarray)) return false;
        $inClause = '(';
        foreach ($IDarray as $item) {
            $inClause.= (string) $item;
            $inClause.= ',';
        }
        $inClause = substr($inClause, 0, -1);
        $inClause.= ')';

        $validationCount =  sql_row('SELECT COUNT(DISTINCT(idattr)) AS validationCount FROM bsc_stockattrs
									WHERE idattrpr IN '.$inClause)['validationCount'];
        if (count($IDarray) != $validationCount) return false;

        //
        $rows =  sql_rows('SELECT b.mname as categoryName, a.mname as itemName FROM bsc_attributes_pr a 
							LEFT JOIN bsc_attributes b ON a.idattr=b.id
							WHERE a.id IN '.$inClause);

        foreach ($rows as $row) {
            if (!isset($resultArray)) $resultArray=[];
            $resultArray[$row['categoryName']] = $row['itemName'];
        }
        if (isset($resultArray)) return $resultArray;
        return false;
    }









    //======== OLD =========

    public static function get_attribute($product_id) {
       $attr=sql_rows('SELECT a.* FROM bsc_prod_attr p LEFT JOIN bsc_attributes a ON a.id=p.idattr WHERE p.idprod=:id ORDER BY a.plp ASC',array(':id'=>$product_id));
       $res=array();
       foreach ($attr as $a)
       {
            $res[$a['id']]=$a;
            if ($a['ptype']==0) {
              $w=sql_rows('SELECT a.*, t.pname as pimgname FROM bsc_attributes_pr a LEFT JOIN bs_attachments t ON t.id=a.pimageid WHERE a.pid=:id',array(':id'=>$a['id']));
              foreach ($w as $ww) $res[$a['id']]['items'][$ww['id']]=$ww;
            }

       }
       return $res;
    }

    public static function find_attribute($attributes,$name) {
       foreach ($attributes as $attr) if ($attr['pident']==$name) return $attr;
       return false;
    }

    public static function attribues_names($attributes, $selattr, $friendly=false)
    {//echo '<pre>X';print_r($selattr);print_r($attributes);exit;
         $res='';
         foreach ($selattr as $id=>$value)
         {
              $n=$attributes[$id]['pident'];
              if ($n=='') $attributes[$id]['pname'];
              $v=$value;
              if ($attributes[$id]['ptype']==0)
              {
                   $v=$attributes[$id]['items'][$value]['pname'];
              }
              if ($v!='')
              {
                   if ($friendly) $res.=''.$attributes[$id]['ptitle'].': <strong>'.$v.'</strong><br />';
                             else $res.=$n.'='.$v.', ';
              }
         }
         if ($res!='')
         {
              if ($friendly) $res=substr($res,0,-6);
              else $res=substr($res,0,-2);
         }
         return $res;
    }

    public static function calculate_price(&$product, $attributes=NULL, $selattr=NULL)
    {

         function testWarunek($w, &$attributes, &$selattr)
         {
              $w=str_replace('=','==',$w);
              $w=str_replace('>==','>=',$w);
              $w=str_replace('<==','<=',$w);
              $w=str_replace('===','==',$w);

            //  echo '<pre>';
              //print_r($attributes);
              foreach ($selattr as $id=>$value)
              {
                 $nazwa=$attributes[$id]['pident'];
                 $wartosc=$value;
                 if ($attributes[$id]['ptype']==0) $wartosc=$attributes[$id]['items'][$value]['pname'];
                 //echo $nazwa.'='.$wartosc.'<br>';
                 $w=str_ireplace($nazwa,$wartosc,$w);
              }

              $result=false;
              eval('$result=('.$w.');');

              return $result;
         }

//         echo '<pre>';
//         print_r($selattr);
        // print_r($attributes);
//         echo '</pre>';

         $price=$product['pprice'];

         //$selattr - opis wybranych atrybutów ID-atrybutu  => wybrana wartość
         foreach ($selattr as $id=>$value)
         {
              //pobieramy regułę dla atrybutu
              if (!isset($attributes[$id]['ppricemod'])) continue;
              $rel=$attributes[$id]['ppricemod'];

              if ($attributes[$id]['ptype']==0)
              {    //jak atrybut to lista wyboru, pobieramy regułę z tej listy
                   $rel=$attributes[$id]['items'][$value]['ppricemod'];
              }
              $rel=trim($rel);
              if ($rel=='') continue;

              //jak reguła w PHP, to wykonujemy
              if ($rel[0]=='!' || strpos($rel,'$price')!==false)
              {
                   if ($rel[0]=='!') $rel=substr($rel,1);
                   if ($rel[strlen($rel)-1]!=';') $rel.=';';

                   eval($rel);
              } else
              {
                //reguła w moim języku
                $e=explode(';',$rel);
                foreach ($e as $rel)
                {
                     $rel=trim($rel);
                     if ($rel=='') continue;
                     //echo $rel.'<br>';
                     if ($rel[0]=='[')
                     { //warunek if w nawiasach kwadratowych
                          $warunek=substr($rel,1);
                          $k=strpos($warunek,']');
                          $warunek=substr($warunek,0,$k);
                          if (testWarunek($warunek,$attributes,$selattr)) $rel=trim(substr($rel,$k+2)); else continue;
                     }
                     if ($rel[0]=='=')
                     {
                       $price=(double)substr($rel,1);
                     } else
                     if ($rel[0]=='+')
                     {
                          $price+=(double)substr($rel,1);
                     } else
                     if ($rel[0]=='-')
                     {
                          $price+=(double)substr($rel,1);
                     }
                   //echo $rel.'<br>';
                }



                //-----------------
              }

        //   echo $attributes[$id]['pname'].'='.$rel.'<br>';
         }

         //echo $price.'!';
         $product['pprice']=$price;
        // exit;
        // exit;



    }


}


