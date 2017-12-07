<?php defined('SYSPATH') or die('No direct script access.');
/* **********************************************************************************
  Kontrolery podstawowe - obsługujące różne funkcje panelu sklepu, m.in.:
************************************************************************************/

class Controller_Shop_Core extends BSXController {

    public function before() {
        if (!Model_BSX_Core::testPermission('shop'))
        parent::before();

        $this->view=Model_BSX_Core::create_view($this,'index_shop','pshop');
        if (!$this->view) die('Nie załadowano szablonu index_shop!');

        $this->root=Model_BSX_Core::$bsx_cfg['pshop_prefix'].'/';
        $this->link=$this->root;

        $this->view->path=array(
            array('caption'=>'Home','url'=>'/'),
            array('caption'=>'Sklep','url'=>$this->root),
        );
    }


    //---strona główna sklepu---
    public function action_index()
    {
        return $this->showHome(0);
    }

    public function action_detect()
    {
        $m = $this->request->param('modrewrite');
        $w=explode('/',$m);
        while (count($w)<=5) $w[]='';
        if (!empty(Model_BSX_Core::$bsx_cfg['model']) && ($w[0]==Model_BSX_Core::$bsx_cfg['model'])) $this->showFromModel($w);
        else if ($w[0]=='new') return $this->showListProducts((int)$w[1]);
        else if ($w[0]=='home') return $this->showHome((int)$w[1]);
		else if ($w[0]=='beforeCart') return $this->showBeforeCart($w, $m);
        else if ($w[0]=='cart') return $this->showCart($w);
        else if ($w[0]=='checkout') return $this->showCheckout($w);
        else if ($w[0]=='order') return $this->showOrder($w);
        else if ($w[0]=='search') return $this->showListBySearch(getGet('s'),(int)$w[1]);
        else if ($w[0]=='category') return $this->showCategory($w[1],(int)$w[2]);
        else if ($w[0]=='recalculate') return $this->recalculatePrice();
        else {
            if (is_numeric($w[0]) && !empty($w[1])) $p = sql_row('SELECT id FROM bs_stockindex WHERE id=:slug', array(':slug' => $w[0]));
            else $p = sql_row('SELECT id FROM bs_stockindex WHERE wslug=:slug', array(':slug' => $w[0]));
            if ($p) return $this->showProduct($p['id']);

            $p = sql_row('SELECT id, wslug, pname FROM bs_stocktags WHERE wslug=:slug', array(':slug' => $w[0]));
            if ($p) return $this->showListByTag($p,(int)$w[1]);

            $p = sql_row('SELECT * FROM bsc_articles WHERE (idsite=:idsite OR idsite=0) AND pmodrewrite=:modrewrite AND pstatus=1',array(':idsite'=>Model_BSX_Core::$bsx_cfg['id'],':modrewrite'=>$w[0]));
            if ($p) return $this->showArticle($p);

            $p = sql_row('SELECT id, wslug, pname FROM bs_groups WHERE wslug=:slug AND tblname=\'bs_stockindex\'', array(':slug' => $w[0]));
            if ($p) return $this->showListByCat($p,(int)$w[1]);

            throw new HTTP_Exception_404(':file does not exist!', array(':file' => $m));
        }
    }

    private function showHome($start)
    {
        $this->view->title='Oprogramowanie dla firm';
        $this->view->set('isHome',true);
        Model_BSX_Page::$active_tab='shop';

        $sklep=Model_BSX_Shop::init();
        $results=$sklep->getProductsListFromCategory('all',0,Model_BSX_Core::$bsx_cfg['pshop_prefix'].'/home/{start}',$start,100,true,'t.wlp DESC, t.pname');

        $page=Model_BSX_Core::create_view($this,'part_shop_home1');
        $page->set('results',$results);
        $page->set('sklep',$sklep);

        echo $page;
    }

    private function showProduct($id)
    {
        $sklep=Model_BSX_Shop::init();
        Model_BSX_Page::$active_tab='shop';
        $product=$sklep->getProduct($id);
        if (!$product) throw new HTTP_Exception_404('Product does not exist!');

        $this->view->title=$product['pname'];
        $this->view->path[]=array('caption'=>$product['pname'],'url'=>$this->link.'/'.$product['wslug']);


        Model_BSX_Core::$bsx_cfg['pmtitle']=$product['pname'];
        if (!empty($product['wseo_desc'])) {
            Model_BSX_Core::$bsx_cfg['pmdesc']=$product['wseo_desc'];
        }
        if (!empty($product['wseo_title'])) {
            Model_BSX_Core::meta_page('title',$product['wseo_title'],0);
        } else {
            Model_BSX_Core::meta_page('title',$this->view->title,1);
        }
        if (!empty($product['wseo_keyw'])) Model_BSX_Core::$bsx_cfg['pmkeys']=$product['wseo_keyw'];

        if (Model_BSX_Core::global_variable('ajax'))
        {
            if ($product!==null) {
                $page = Model_BSX_Core::create_view($this, 'part_shop_product1_preview');//nie istnieje widok-zwracany false i jest blad
                $page->set('product', $product);
                $page->set('title', $product['pname']);
                $page->set('sklep', $sklep);
                $this->ajaxResult[]['#productModal'] = $page->render();
                $this->ajaxResult[]['@run'] = '$(\'#productModal\').modal(\'show\')';
            } else {
                $this->ajaxResult[]='';
            }
        } else {
            $page = Model_BSX_Core::create_view($this, 'part_shop_product1');
            $page->set('product', $product);
            $page->set('title', $product['pname']);
            $page->set('sklep', $sklep);
            echo $page;
        }
    }

    private function showListProducts($start=0)
    {
        $this->view->title='Sklep';
        Model_BSX_Page::$active_tab='shop';

        //$this->view->path[]=array('caption'=>'Tag: '.$tag['pname'],'url'=>$this->link.'/'.$tag['wslug']);

        $sklep=Model_BSX_Shop::init();
        $results=$sklep->getProductsListFromCategory('all',0,Model_BSX_Core::$bsx_cfg['pshop_prefix'].'/all/{start}',$start);

        $page=Model_BSX_Core::create_view($this,'part_shop_category1');
        $page->set('results',$results);
        $page->set('sklep',$sklep);
        echo $page;
    }

    private function showListByTag($tag,$start=0)
    {
        $this->view->title='Tag: '.$tag['pname'];
        $this->view->path[]=array('caption'=>'Tag: '.$tag['pname'],'url'=>$this->link.'/'.$tag['wslug']);
        Model_BSX_Page::$active_tab='shop';
        Model_BSX_Core::meta_page('title',$this->view->title,1);

        $sklep=Model_BSX_Shop::init();
        $results=$sklep->getProductsListFromCategory('tag',$tag,Model_BSX_Core::$bsx_cfg['pshop_prefix'].'/'.$tag['wslug'].'/{start}',$start);

        $page=Model_BSX_Core::create_view($this,'part_shop_category1');
        $page->set('results',$results);
        $page->set('sklep',$sklep);
        echo $page;
    }

    private function showCategory($catSlug,$start=0) {//$catSlug-nazwa kategorii, $start-indeks od ktorego pobierac
        if (empty($catSlug)) {
            Model_BSX_Page::$active_tab='shopcat';
            $this->view->title='Kategorie';
            $this->view->path[]=array('caption'=>'Kategorie','url'=>$this->link.'category');
            Model_BSX_Core::meta_page('title',$this->view->title,1);

            $sklep=Model_BSX_Shop::init();
            $page=Model_BSX_Core::create_view($this,'part_shop_category_list1');
            $page->set('sklep',$sklep);
            $page->title=$this->view->title;
            $page->path=$this->view->path;
            echo $page;
        } else {
            $p = sql_row('SELECT id, wslug, pname FROM bs_groups WHERE wslug=:slug AND tblname=\'bs_stockindex\'', array(':slug' => $catSlug));
            if (!$p) throw new HTTP_Exception_404(':file does not exist!', array(':file' => $catSlug));
            return $this->showListByCat($p, $start);
        }
    }

    private function showListByCat($cat,$start=0)
    {
        // echo 'kategoria:';
        // print_r($cat);
        $this->view->title='Kategoria: '.$cat['pname'];
        Model_BSX_Page::$active_tab='shopcat';
        $this->view->path[]=array('caption'=>'Kategorie','url'=>$this->link.'category');
        $this->view->path[]=array('caption'=>'Kategoria: '.$cat['pname'],'url'=>$this->link.'/category/'.$cat['wslug']);
        Model_BSX_Core::meta_page('title',$cat['pname'],1);

        $sklep=Model_BSX_Shop::init();
        $limit=getGet('plimit', Model_BSX_Core::$skinOptions['categories_list.default']);
        if($limit !== Model_BSX_Core::$skinOptions['categories_list.default'])
            Model_BSX_Core::$skinOptions['categories_list.default']=$limit;
        $this_url='sklep/category/'.$cat['wslug'].'/'.$start;

        $results=$sklep->getProductsListFromCategory('category',$cat,Model_BSX_Core::$bsx_cfg['pshop_prefix'].'/category/'.$cat['wslug'].'/{start}',$start,$limit);

        $ajax=getGet('ajax');
        if($ajax==1) {
            //generowanie linkow do obrazkow (w przypadku żadania nie ajax, robi sie to w widoku)
            foreach($results['rows'] as &$product) {
                $product['img_src']=Model_BSX_Core::cache_img($product['ptable'],$product['pid'],$product['apname'],600,600,105);
            }
            $this->ajaxResult=$results;
            return;
        }

        $page=Model_BSX_Core::create_view($this,'part_shop_category1');
        $page->set('results',$results);
        $page->set('sklep',$sklep);
        $page->set('this_url', $this_url);
        $page->title=$this->view->title;
        $page->path=$this->view->path;
        echo $page;
    }

    private function showListBySearch($s,$start=0)
    {
        $this->view->title='Szukaj: '.$s;
        $this->view->path[]=array('caption'=>'Wyszukiwarka','url'=>$this->link.'/search');
        Model_BSX_Core::meta_page('title',$this->view->title,1);

        $sklep=Model_BSX_Shop::init();
        $results=$sklep->getProductsListFromCategory('search',$s,Model_BSX_Core::$bsx_cfg['pshop_prefix'].'/search/{start}?s='.$s,$start);

        $page=Model_BSX_Core::create_view($this,'part_shop_category1');
        $page->set('results',$results);
        $page->set('sklep',$sklep);
        echo $page;
    }

    private function showArticle($p)
    {
        $this->view->path[]=array('caption'=>$p['ptitle'],'url'=>$this->link.$p['pmodrewrite']);
        $page=new Model_BSX_Page($p);
        Model_BSX_Core::meta_page('title',$page->title,1);
        $view=$page->show_article(false);
        echo $view;
    }
	
	private function showBeforeCart($w, $m) {
		$sklep=Model_BSX_Shop::init();
		$results=$sklep->getRelatedProducts($w[2]);
		if (empty($results)) {
			//echo $m; exit;
			$m = 'sklep/'.$m;
			$m = str_replace('beforeCart/', 'cart/', $m);
			header('Location: /'.$m);
            exit;
			//$w[0] = 'cart';
			//$this->showCart($w);
		}
		else {
			$page=Model_BSX_Core::create_view($this,'part_shop_beforeCart');
			$page->set('results',$results);
			$page->set('sklep',$sklep);
			$page->set('productID', $w[2]);
			$nextLink = '/sklep/';
			foreach ($w as $piece) {
				if ($piece=='beforeCart') $nextLink.= 'cart/';
				else if ($piece!='') $nextLink.= $piece.'/';
			}
			$page->set('nextLink', $nextLink);
			echo $page;
		}
	}

    private function showCart($w)
    {
		//BinUtils::pr($w); exit;
        Model_BSX_Page::$active_tab='shop';
        $this->view->title='Zawartość koszyka';
        $this->view->path[]=array('caption'=>'Koszyk','url'=>$this->link.'/cart');
        Model_BSX_Core::meta_page('title',$this->view->title,1);

        $sklep=Model_BSX_Shop::init();
        $cart=Model_BSX_Cart::init();
        $info='';

        if ($w[1]=='add') {
            $product=$sklep->getProduct($w[2],false);
            if ($product) {

                // save attributes that the buyer chose
                if (isset($w[3])) $explodedString = explode(',', $w[3]);
                foreach ($explodedString as $attribute) {
                    if (is_numeric($attribute)) {
                        if (!isset($product['selectedAttributes'])) $product['selectedAttributes'] = [];
                        $product['selectedAttributes'][] = (int) $attribute;
                    }
                }
                if (!isset($product['selectedAttributes'])) $product['selectedAttributes'] = $sklep->getSelectedAttributesArray($product);
                // end save attributes

                if (!empty(Model_BSX_Core::$bsx_cfg['model'])&&is_callable(array(Model_BSX_Core::$bsx_cfg['model'], 'addProductToCart'),true)&&method_exists(Model_BSX_Core::$bsx_cfg['model'],'addProductToCart'))
                {
                    $product=forward_static_call(array(Model_BSX_Core::$bsx_cfg['model'], 'addProductToCart'),$this,$product,$w);
                    if (is_array($product) && !empty($product['error'])) {
                        $info=$product['error'];
                        $product=null;
                    }
                }

                if ($product!==null) {
                    if (!$cart->add($product, (int)getGetPost('quantity', '1'))) {
                        if ($info!='') $info='<div class="alert alert-danger">'.$cart->err.'</div>';
                    }
                }
            }
            if (Model_BSX_Core::global_variable('ajax'))
            {
                if ($product!==null) {
                    $this->ajaxResult[]['#cartModalLabel']='Zawartość koszyka';
                    $this->ajaxResult[]['#cartModalGo']='Przejdź do koszyka';
                    $this->ajaxResult[]['%headerShop'] = Model_BSX_Core::create_view($this, 'part_shop_menu_cart', 'psite', array('cart' => $cart))->render();
                    $this->ajaxResult[]['#cartModal .modal-body'] = Model_BSX_Core::create_view($this, 'part_shop_modal_cart', 'psite', array('cart' => $cart))->render();
                    $this->ajaxResult[]['@run'] = '$(\'#cartModal\').modal(\'show\')';
                } else {
                    $this->ajaxResult[]='';
                }
            }
        } else if ($w[1]=='del') {
            $cart->remove($w[2]);
            if (Model_BSX_Core::global_variable('ajax'))
            {
                $page=Model_BSX_Core::create_view($this,'part_shop_menu_cart');
                $page->set('cart',$cart);
                $this->ajaxResult[]['%headerShop']=$page->render();
            }
        } else if ($w[1]=='update') {
            if (isset($_POST['quantity']) && is_array($_POST['quantity']))//$_POST['quantity'] - to tablica z wartosciami ilosci
            {
                foreach ($_POST['quantity'] as $lp=>$value) $cart->changeQuantity($lp,$value);//$lp-indeks w tablicy - w koszyku; $value-nowa wartosc ilosci - zawsze dla wszystkich produktow robi
            }

            if (isset($_POST['ndelivery'])) $cart->setDelivery($_POST['ndelivery']);
            if (isset($_POST['ncountry'])) $cart->setCountry($_POST['ncountry']);

            $r=$cart->setDiscountCode(getGetPost('code'));
            if ($r>0) $info='<div class="alert alert-success">Rabat został nadany!</div>';
            else if ($r==-1) $info='<div class="alert alert-danger">Podano nieprawidłowy kod rabatowy!</div>';
            else if ($r<0) $info='<div class="alert alert-danger">Z tego kodu rabatowego nie można już skorzystać!</div>';
        }
        $page=Model_BSX_Core::create_view($this,'part_shop_cart1');
        $page->set('title',$this->view->title);
        $page->set('path',$this->view->path);
        $page->set('info',$info);
        if (!empty(Model_BSX_Core::$bsx_cfg['model'])&&is_callable(array(Model_BSX_Core::$bsx_cfg['model'], 'eventShowCart'),true)&&method_exists(Model_BSX_Core::$bsx_cfg['model'],'eventShowCart'))
        {
            $page=forward_static_call(array(Model_BSX_Core::$bsx_cfg['model'], 'eventShowCart'),$this,$page);
        }
        $cart->recalc();
        $cart->save();
        echo $page;
    }

    private function showCheckout($w)
    {
        Model_BSX_Page::$active_tab='shop';
        $this->view->title='Realizacja zamówienia';
        $this->view->path[]=array('caption'=>'Realizacja zamówienia','url'=>$this->link.'/checkout');
        Model_BSX_Core::meta_page('title',$this->view->title,1);

        $cart=Model_BSX_Cart::init();
        if ($cart->count()<=0)
        {
            header('Location: /'.Model_BSX_Core::$bsx_cfg['pshop_prefix'].'/cart');
            exit;
        }
        $fields=array('pname','pnip','pstreet','pstreet_n1','ppostcode','ppost','pcity','pcountry','kname','kstreet','kstreet_n1','kpostcode','kpost','kcity','kcountry','pemail','pphone1', 'npayment', 'buyerType', 'differentAddress',
						'acceptRules', 'nagree_proc', 'nagree_mar');
        $f=array();
        if ($_SESSION['login_user']['id']>0) {
            $u=sql_row('SELECT * FROM bs_contractors WHERE id=:id',array(':id'=>$_SESSION['login_user']['id']));
            if ($u) {
                foreach ($fields as $name) if (isset($u[$name])) $f[$name] = $u[$name];
            }
        }
        foreach ($fields as $name) {
            if (isset($f[$name])) $v=$f[$name]; else $v='';
            $f[$name]=getPost($name,$v);
			if (in_array($name, ['acceptRules', 'nagree_proc', 'nagree_mar'])) {
				if (getPost($name, NULL)!==NULL) $f[$name] = 1;
			}
        }
        if ($f['pcountry']=='') $f['pcountry']='Polska';
        if ($f['kcountry']=='') $f['kcountry']='Polska';


        $cart->setPayment($f['npayment']);
        $err='';
        if (getPost('save')==1) {
            if($f['buyerType']!=='consumer' && $f['buyerType']!=='business') $err='Nie określono czy jesteś osobą prywatną czy firmą';
			else if ($f['pname']=='' && $f['buyerType']=='consumer') $err = 'Należy podać imię i nazwisko';
            else if ($f['pname']=='' && $f['buyerType']=='business') $err = 'Należy podać nazwę firmy';
            else if ($f['pstreet'] == '') $err = 'Należy podać adres';
            else if ($f['ppostcode'] == '') $err = 'Należy podać kod pocztowy';
            else if ($f['pcity'] == '') $err = 'Należy podać miejscowość';
            else if ($f['pcountry'] == '') $err = 'Należy podać kraj';
            else if ($f['pemail'] == '') $err = 'Należy podać adres e-mail';
            else if (!filter_var($f['pemail'], FILTER_VALIDATE_EMAIL)) $err = 'Podany adres e-mail jest niepoprawny';
            else if ($f['pphone1'] == '') $err = 'Należy podać numer telefonu';            
            else if ($f['npayment'] == '') $err = 'Należy podać sposób platności';
            else if (getPost('acceptRules', NULL)===NULL) $err = 'Należy zaakceptować regulamin';

            if ($err == '') {
                $order=new Model_BSX_Order();
                $order->importProducts($cart);
                $order->setBuyer(array(
                    'pname'=>$f['pname'],
                    'pnip'=>$f['pnip'],
                    'pstreet'=>$f['pstreet'],
                    'pstreet_n1'=>trim($f['pstreet_n1']),
                    'ppostcode'=>$f['ppostcode'],
                    'pcity'=>$f['pcity'],
                    'pcountry'=>$f['pcountry'],
                    'pphone1'=>$f['pphone1'],
                    'pemail'=>$f['pemail'],
                    'kname'=>$f['kname'],
                    'kstreet'=>$f['kstreet'],
                    'kstreet_n1'=>trim($f['kstreet_n1']),
                    'kpostcode'=>$f['kpostcode'],
                    'kcity'=>$f['kcity'],
                    'kcountry'=>$f['kcountry'],
                ));
                if ($cart->discountRow) {
                    $order->order['nnote']='Zastosowano rabat: '.$cart->discountRow['pname'];
                    sql_query('UPDATE bsc_codes SET nused=nused+1 WHERE id=:id',array(':id'=>$cart->discountRow['id']));
                }
                $order->order['pfromdoc']=Model_BSX_Core::$bsx_cfg['ptitle'];
                $order->order['pfrom']=$order->order['pfromdoc'];
                $order->analyse();

                $order->execute();

                $mail=Model_BSX_Core::mail_view('zamowienie','Potwierdzenie przyjęcia zamówienia');
                $mail->set('order',$order->order);
                $mail->set('shop',Model_BSX_Core::$bsx_cfg);
                $vmail=$mail->render();

                BinUtils::explodeMail(Model_BSX_Core::$bsx_cfg['pemail'],$em,$nz);
                Email::factory()->subject($mail->title)->to($f['pemail'])->from($em,$nz)->message($vmail)->send();

                $cart->clear();
				
				// set user permissions (information processing, sending offers)
				$e1 = [];
				if (getPost('nagree_proc', NULL)!==NULL) $e1['nagree_proc'] = 1;
				else $e1['nagree_proc'] = 0;
				
				if (getPost('nagree_mar', NULL)!==NULL) $e1['nagree_mar'] = 1;
				else $e1['nagree_mar'] = 0;
				sql_update('bs_contractors',$e1,$_SESSION['login_user']['id']);
				
                Header('Location: /'.Model_BSX_Core::$bsx_cfg['pshop_prefix'].'/order/'.$order->getMD5());
                exit;
            }
        }

        $page=Model_BSX_Core::create_view($this,'part_shop_checkout1');
        $page->set('title',$this->view->title);
        $page->set('path',$this->view->path);
        $page->set('f',$f);
        $page->set('err',$err);
        echo $page;
    }


    private function showOrder($w)
    {
        Model_BSX_Page::$active_tab='shop';
        $sklep=Model_BSX_Shop::init();
        $order=$sklep->getOrderByMD5($w[1]);
        if (!$order) throw new HTTP_Exception_404('Zamówienie nie istnieje!');

        $this->view->title='Zamówienie '.$order['nnodoc'];
        $this->view->path[]=array('caption'=>'Zamówienie','url'=>$this->link.'order/'.$order['md5']);
        Model_BSX_Core::meta_page('title',$this->view->title,1);


        $pay=new Model_BSX_Payments();
        if (!empty(Model_BSX_Core::$bsx_cfg['model'])&&is_callable(array(Model_BSX_Core::$bsx_cfg['model'], 'getSuppliers'),true))
        {
            $pay->suppliers=forward_static_call(array(Model_BSX_Core::$bsx_cfg['model'], 'getSuppliers'));
        }

        $ret=getGetPost('return');
        if ($ret!='')
        {
             if ($ret=='fail') Model_BSX_Core::global_variable('errorStr','<div class="alert alert-danger">Nie udało się poprawnie opłacić zamówienia.</div>');
             else Model_BSX_Core::global_variable('errorStr','<div class="alert alert-info">Dziękujemy za dokonanie płatności. Natychmiast, jak tylko zostanie potwierdzona, Twoje zamówienie będzie zrealizowane.</div>');
        }


        $pay->price=$order['nstotal_b'];
        $pay->orderID=$order['id'];
        $pay->urlOK=Model_BSX_Core::$bsx_cfg['purl'].'/sklep/order/'.$order['md5'].'?return=true';
        $pay->urlFAIL=Model_BSX_Core::$bsx_cfg['purl'].'/sklep/order/'.$order['md5'].'?return=false';
        $pay->desc='Zamowienie '.$order['nnodoc'];
        $pay->buttonPriceTXT='Zapłać za zamówienie '.$order['nnodoc'].' - '.BinUtils::price($order['nstotal_b']).' PLN';
        $pay->buyer=array('name'=>$order['pname'],'street'=>$order['pstreet'].' '.$order['pstreet_n1'],'city'=>$order['pcity'],'postcode'=>$order['ppostcode'],'country'=>$order['pcountry'],'email'=>$order['pemail']);


        $page=Model_BSX_Core::create_view($this,'part_shop_order1');
        if ($order) {
            $page->set('title', 'Zamówienie '.$order['nnodoc']);
        } else {
            $page->set('title', 'Nie znaleziono zamówienia!');
        }
        $page->set('order',$order);
        $page->set('pay',$pay);
        echo $page;
    }

    private function showFromModel($w)
    {
        if ($w[1]!='' && is_callable(array(Model_BSX_Core::$bsx_cfg['model'], $w[1]),true))
        {
            forward_static_call(array(Model_BSX_Core::$bsx_cfg['model'], $w[1]),$this,$w[2],$w[3],$w[4],$w[5]);
        }
    }

    private function recalculatePrice() {
        $data = json_decode(stripslashes($_POST['data']));
        $id = array_pop($data);

        $sklep=Model_BSX_Shop::init();
        $product=$sklep->getProduct($id, false);
        $sklep->recalculatePrice($product, $data);

        if (Model_BSX_Core::$bsx_cfg['pricenb'] == 0) {
            $product['price'] = BinUtils::price($product['pbrutto'], Model_BSX_Shop::$currency);
        } else {
            $product['price'] = BinUtils::price($product['pnetto'], Model_BSX_Shop::$currency);
        }


        echo str_replace('&nbsp;',' ',$product['price']).'$'. microtime(true);
    }
}