<?php
/*****************************************************
/** class.BinImages.php    Data:2013-04-26          **
/**                                                 **
/** Karol Wierzchołowski                            **
/** karol@wierzcholowski.pl                         **
/**                                                 **
/*****************************************************/

 global $dat, $cfg;

  class BinImages {


        public static function getImageSizeFile($path, $size, $backColor='@',$options='' )
        {
         if ($size=='' || substr($path,0,5)=='http:' || substr($path,0,6)=='https:') return $path;
         $t=explode('x',$size);
         if ($path!='' && $path[0]=='/') $path=substr($path,1);
         return BinImages::getImageInSize($path,$t[0],$t[1],$backColor,$options);
        }

        public static function getImageInSize($path,$x,$y,$backColor='@',$options='',$alphablend=true)
        {
          global $cfg;

          if ($path!='' && $path[0]=='/') $path=substr($path,1);
          if (!is_file($path)) return '/'.$path;

          $filesize=filesize($path);
          $filemtime=filemtime($path);

          $r=BinUtils::extractFileExt($path,true);
          $n=BinUtils::extractFileNameNoExt($path);


          if ($r=='.jpg' || $r=='.jpeg') $newpath=Model_BSX_Core::$bsx_cfg['passets'].'/img/'.$n.'-'.$filesize.'-'.$x.'-'.$y.substr($backColor,1).$options.'.jpg';
          else if ($r=='.png') $newpath=Model_BSX_Core::$bsx_cfg['passets'].'/img/'.$n.'-'.$filesize.'-'.$x.'-'.$y.substr($backColor,1).$options.'.png';
          else if ($r=='.gif') $newpath=Model_BSX_Core::$bsx_cfg['passets'].'/img/'.$n.'-'.$filesize.'-'.$x.'-'.$y.substr($backColor,1).$options.'.gif';
          else return '/'.$path;

          if (is_file($newpath) && filemtime($newpath)>$filemtime) return '/'.$newpath;


          BinImages::imageResize($path,$newpath,90,$x,$y,$backColor,$alphablend,$options);
          return '/'.$newpath;
        }

        public static function imageResize ($img_in, $img_out, $imgQuality=100, $img_x=0, $img_y=0, $backColor='',$alphablend=true,$options='')
        {
          global $cfg;
          if ($backColor!='') $backColor=substr($backColor,1);



          $img_in=trim($img_in);
          $img_out=trim($img_out);
          $img_in_typ = BinUtils::extractFileExt($img_in,true);
          $img_out_typ = BinUtils::extractFileExt($img_out,true);

          $k=strpos($img_in,'#');
          if ($k>0)
          {
              $img_in_typ=substr($img_in,$k+1);
              $img_in=substr($img_in,0,$k);
          }

          //---- jak plik źródłowy do ICO - to konwertujemy do PNG bo ICO nie obsługujemy
          if ($img_in_typ=='.ico')
          {
                  include_once('class/class.ico.php');
                  $ico=new Ico($img_in);

                  $img_in='buffers/temp/'.time().'-'.rand(0,999).rand(0,999).'.png';
                  $img_in_typ='.png';

                  $ilosc=$ico->TotalIcons();
                  $max_i=0;$max_c=0;$max_w=0;
                  for ($i=0;$i<$ilosc;$i++)
                  {
                     $w=$ico->GetIconInfo($i);
                     if ($w['ColorCount']>$max_c) $max_c=$w['ColorCount'];
                  }
                  for ($i=0;$i<$ilosc;$i++)
                  {
                     $w=$ico->GetIconInfo($i);
                     if ($w['Width']>$max_w && $w['ColorCount']==$max_c) {$max_i=$i;$max_w=$w['Width'];}
                  }
                  $ico->SetBackground(255,255,255);
                  $ico->SetBackgroundTransparent();
                  $img=$ico->GetIcon($max_i);
                  imagepng($img, $img_in);
                  $alphablend=false;
          }

          //-----------------------------------------------------------------------------

          if (!is_file($img_in)) return -1;

          if ($img_in_typ=='.jpg' || $img_in_typ=='.jpeg') { $img_tmp = ImageCreateFromJPEG($img_in); }
          elseif ($img_in_typ=='.gif') { $img_tmp = ImageCreateFromGIF($img_in); }
          elseif ($img_in_typ=='.png') { $img_tmp = ImageCreateFromPNG($img_in); }
          else return -2;

          //$img_x, $img_y                  //  - wymiary pudełka (oczekiwane)
          $img_size_x  = ImageSX($img_tmp) ;//  - wymiary rzeczywiste
          $img_size_y = ImageSY($img_tmp) ;
          $img_new_x=0;                     //  - nowe wymiary obrazka
          $img_new_y=0;

          if ($img_x==0 && $img_y==0)
          {
              if ($img_out_typ=='.jpg' || $img_out_typ=='.jpeg') { ImageJPEG($img_tmp, $img_out, $imgQuality); }
              elseif ($img_out_typ=='.gif') { ImageGIF($img_tmp, $img_out); }
              elseif ($img_out_typ=='.png') {
                   if ($img_in_typ=='.png') copy($img_in,$img_out);
                   else ImagePNG($img_tmp, $img_out);
              }
              return 1;
          }

          if ($img_x>0) //oczekiwany wymiar
          {
                    $img_new_x = $img_x;
                    $img_new_y = round(($img_new_x * $img_size_y) / $img_size_x);
                    if (($img_new_y>$img_y)&&($img_y>0)) { $img_new_y = $img_y; $img_new_x = round(($img_new_y * $img_size_x) / $img_size_y); }
          }
          if ($img_y>0)
          {
                   $img_new_y = $img_y;
                   $img_new_x = round(($img_new_y * $img_size_x) / $img_size_y);
                   if (($img_new_x>$img_x)&&($img_x>0)) { $img_new_x = $img_x; $img_new_y = round(($img_new_x * $img_size_y) / $img_size_x); }
          }



          if ($img_size_x<=$img_new_x  && $img_size_y<=$img_new_y)
          {
               $img_new_x = $img_size_x;
               $img_new_y = $img_size_y;
          }

          if ($backColor!='')
          {
               //echo $img_x.'!'.$img_y.'!';


                   $img_new = ImageCreateTrueColor($img_x,$img_y);
                   if ($backColor[0]!='t' && $backColor[0]!='f')
                   {
                     $color = imagecolorallocate($img_new, hexdec('0x' . $backColor{0} . $backColor{1}), hexdec('0x' . $backColor{2} . $backColor{3}), hexdec('0x' . $backColor{4} . $backColor{5}));
                     imagefill( $img_new , 1 , 1 , $color );
                   } else
                   {
                     if ($img_in_typ=='.png' && $backColor=='f')
                     {
                        $transparent = ImageColorsForIndex($img_tmp, ImageColorTransparent($img_tmp));
                     } else
                     {
                       $transparent = imagecolorexactalpha($img_new, 255, 255, 255, 0); //bylo 127
                     }
                     imagecolortransparent($img_new, $transparent);
                     imagefilledrectangle($img_new, 0, 0, $img_x, $img_y, $transparent);
                     if (strlen($backColor)>1)
                     {
                          $backColor='#'.substr($backColor,1);
                          $color = imagecolorallocate($img_new, hexdec('0x' . $backColor{0} . $backColor{1}), hexdec('0x' . $backColor{2} . $backColor{3}), hexdec('0x' . $backColor{4} . $backColor{5}));
                          imagefilledrectangle($img_new, $img_x/2-$img_new_x/2, $img_y/2-$img_new_y/2, $img_new_x, $img_new_y, $color);
                     }
                   }
                   ImageCopyResampled($img_new,$img_tmp,$img_x/2-$img_new_x/2,$img_y/2-$img_new_y/2,0,0,$img_new_x,$img_new_y,$img_size_x,$img_size_y);

          } else
          {
          //echo $img_new_x.'!'.$img_new_y.'@';
                   $img_new = ImageCreateTrueColor($img_new_x,$img_new_y);
                   imagealphablending($img_new, false);
                   if ($alphablend) imagesavealpha($img_new, true);//**


                   //$transparent=imagecolorallocatealpha($img_new, 255, 255, 255, 127);
                   $transparent = imagecolorexactalpha($img_new, 255, 255, 255, 0); //bylo 127
                   if ($transparent<0) imagecolorallocatealpha($img_new, 255, 255, 255, 0);
                   imagecolortransparent($img_new, $transparent);

                   imagefilledrectangle($img_new, 0, 0, $img_new_x, $img_new_y, $transparent);

                   ImageCopyResampled($img_new,$img_tmp,0,0,0,0,$img_new_x,$img_new_y,$img_size_x,$img_size_y);

          }
          $options=';'.$options.';';
          if (stripos($options,';hf;')!==false) $img_new=image_flip($img_new,'horiz');
          if (stripos($options,';vf;')!==false) $img_new=image_flip($img_new,'vert');
          if (stripos($options,';bf;')!==false) $img_new=image_flip($img_new,'both');

          if ($img_out_typ=='.jpg' || $img_out_typ=='.jpeg') { ImageJPEG($img_new, $img_out, $imgQuality); }
          elseif ($img_out_typ=='.gif') { ImageGIF($img_new, $img_out); }
          elseif ($img_out_typ=='.png') { ImagePNG($img_new, $img_out); }

          ImageDestroy($img_tmp);
          ImageDestroy($img_new);
        }



        //Błędy: 0 - nie udało się przełać pliku
        //      -1 - błąd
        //      -2 - złe rozszerzenie
        //      -3 - nierozpoznany typ pliku
        public static function uploadImage($file,$fileOut,$imgSize,$allowedExts=null,$imgQuality=90)
        {
             global $memcache;
             if (!is_array($file))
             {
               if (!isset($_FILES[$file])) return 0;
               $file=$_FILES[$file];
             }

             if ($file['error']==4) return 0;
             if ($file['error']>0) return -1;

             if ($allowedExts==null) $allowedExts = array('.jpg','.jpeg','.gif','.png','.ico');
             $extension = BinUtils::extractFileExt($file['name'],true);
             if (!in_array($extension,$allowedExts))
             {
                  @unlink($file['tmp_name']);
                  return -2;
             }


             $typ='';
             if ($file['type'] == 'image/gif') $typ='.gif';
             else if ($file['type'] == 'image/jpeg') $typ='.jpg';
             else if ($file['type'] == 'image/png') $typ='.png';
             else if ($file['type'] == 'image/x-icon') $typ='.ico';
             else $typ='';

             if ($typ=='.jpg' && !in_array('.jpg',$allowedExts)) $typ='';
             if ($typ=='.png' && !in_array('.png',$allowedExts)) $typ='';
             if ($typ=='.gif' && !in_array('.gif',$allowedExts)) $typ='';
             if ($typ=='.ico' && !in_array('.ico',$allowedExts)) $typ='';

             if ($typ=='')
             {
                  @unlink($file['tmp_name']);
                  return -3;
             }


             if ($imgSize>0)
             {
                  $x=$imgSize;
                  $y=0;
             } else
             {
                  $t=explode('x',$imgSize);
                  $x=$t[0];
                  $y=$t[1];
             }

             BinImages::imageResize ($file['tmp_name'].'#'.$typ, $fileOut, $imgQuality, $x,$y);
             @unlink($file['tmp_name']);

             return 1;
        }

        public static function image_flip($img, $type=''){
            $width  = imagesx($img);
            $height = imagesy($img);
            $dest   = imagecreatetruecolor($width, $height);
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
            switch($type){
                case '':
                    return $img;
                break;
                case 'vert':
                    for($i=0;$i<$height;$i++){
                        imagecopy($dest, $img, 0, ($height - $i - 1), 0, $i, $width, 1);
                    }
                break;
                case 'horiz':
                    for($i=0;$i<$width;$i++){
                        imagecopy($dest, $img, ($width - $i - 1), 0, $i, 0, 1, $height);
                    }
                break;
                case 'both':
                    for($i=0;$i<$width;$i++){
                        imagecopy($dest, $img, ($width - $i - 1), 0, $i, 0, 1, $height);

                    }
                    $buffer = imagecreatetruecolor($width, 1);
                    for($i=0;$i<($height/2);$i++){
                        imagecopy($buffer, $dest, 0, 0, 0, ($height - $i -1), $width, 1);
                        imagecopy($dest, $dest, 0, ($height - $i - 1), 0, $i, $width, 1);
                        imagecopy($dest, $buffer, 0, $i, 0, 0, $width, 1);
                    }
                    imagedestroy($buffer);
                break;
            }
            ImageDestroy($img);
            return $dest;
        }


  }

?>