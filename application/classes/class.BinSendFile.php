<?php
/*****************************************************
/** class.BinSendFile.php  Data:2013-04-25          **
/**                                                 **
/** Karol Wierzchołowski                            **
/** karol@wierzcholowski.pl                         **
/**                                                 **
/*****************************************************/

class TBinSendFile
{
      public $doStream=false;
      public $maxSpeed=0;
      public $encrypt=false;
      public $key=0;

      private $fileName='';
      private $fileLocation='';
      private $contentType='';
      private $contentDisposition='';


      function TBinSendFile($fileLocation='',$fileName='',$doStream=false)
      {
        $this->fileLocation=$fileLocation;
        $this->fileName=$fileName;
        $this->doStream=$doStream;
        $this->prepareFile();
      }

      function encryptContent($s)
      {
           if (!$this->encrypt) return $s;

           for ($i=0;$i<strlen($s);$i++)
           {
                $s[$i]=chr(ord($s[$i])^$this->key);
                $this->key=($this->key+1)%221;
           }
           return $s;
      }

      function set_range($range, $filesize, &$first, &$last){
        /*
        Sets the first and last bytes of a range, given a range expressed as a string
        and the size of the file.

        If the end of the range is not specified, or the end of the range is greater
        than the length of the file, $last is set as the end of the file.

        If the begining of the range is not specified, the meaning of the value after
        the dash is "get the last n bytes of the file".

        If $first is greater than $last, the range is not satisfiable, and we should
        return a response with a status of 416 (Requested range not satisfiable).

        Examples:
        $range='0-499', $filesize=1000 => $first=0, $last=499 .
        $range='500-', $filesize=1000 => $first=500, $last=999 .
        $range='500-1200', $filesize=1000 => $first=500, $last=999 .
        $range='-200', $filesize=1000 => $first=800, $last=999 .

        */
        $dash=strpos($range,'-');
        $first=trim(substr($range,0,$dash));
        $last=trim(substr($range,$dash+1));
        if ($first=='') {
          //suffix byte range: gets last n bytes
          $suffix=$last;
          $last=$filesize-1;
          $first=$filesize-$suffix;
          if($first<0) $first=0;
        } else {
          if ($last=='' || $last>$filesize-1) $last=$filesize-1;
        }
        if($first>$last){
          //unsatisfiable range
          header("Status: 416 Requested range not satisfiable");
          header("Content-Range: */$filesize");
          exit;
        }
      }

      function buffered_read($file, $bytes){
        /*
        Outputs up to $bytes from the file $file to standard output, $buffer_size bytes at a time.
        */
        if ($this->maxSpeed>0) $buffer_size=$this->maxSpeed*1024; else $buffer_size=8*1024;
        $bytes_left=$bytes;
        while($bytes_left>0 && !feof($file) && (connection_status()==0))
        {
          set_time_limit(0);
          if($bytes_left>$buffer_size) $bytes_to_read=$buffer_size;
                                  else $bytes_to_read=$bytes_left;
          $bytes_left-=$bytes_to_read;
          $contents=fread($file, $bytes_to_read);
          $contents=$this->encryptContent($contents);
          echo $contents;
          flush();
          ob_flush();
          if ($this->maxSpeed>0) sleep(1);
        }
        return connection_status();
      }

      function byteserve($filename){
        /*
        Byteserves the file $filename.

        When there is a request for a single range, the content is transmitted
        with a Content-Range header, and a Content-Length header showing the number
        of bytes actually transferred.

        When there is a request for multiple ranges, these are transmitted as a
        multipart message. The multipart media type used for this purpose is
        "multipart/byteranges".
        */
        $result=-1;

        $filesize=filesize($filename);
        $file=fopen($filename,"rb");

        $ranges=NULL;
        if ($_SERVER['REQUEST_METHOD']=='GET' && isset($_SERVER['HTTP_RANGE']) && $range=stristr(trim($_SERVER['HTTP_RANGE']),'bytes='))
        {
          $range=substr($range,6);
          $boundary='g45d64dfa6bmdx4sd6h4shf5';//set a random boundary
          $ranges=explode(',',$range);
        }

        if($ranges && count($ranges))
        {
          header("HTTP/1.1 206 Partial content");
          header("Accept-Ranges: bytes");
          if(count($ranges)>1)
          {
            /*
            More than one range is requested.
            */

            //compute content length
            $content_length=0;
            foreach ($ranges as $range)
            {
              $this->set_range($range, $filesize, $first, $last);
              $content_length+=strlen("\r\n--$boundary\r\n");
              $content_length+=strlen("Content-range: bytes $first-$last/$filesize\r\n\r\n");
              $content_length+=$last-$first+1;
            }
            $content_length+=strlen("\r\n--$boundary--\r\n");

            //output headers
            header("Content-Length: $content_length");
            //see http://httpd.apache.org/docs/misc/known_client_problems.html for an discussion of x-byteranges vs. byteranges
            header("Content-Type: multipart/x-byteranges; boundary=$boundary");

            //output the content
            foreach ($ranges as $range)
            {
              $this->set_range($range, $filesize, $first, $last);
              echo "\r\n--$boundary\r\n";
              echo "Content-type: ".$this->contentType."\r\n";
              echo "Content-range: bytes $first-$last/$filesize\r\n\r\n";
              fseek($file,$first);
              $this->key=($this->key+$first)%221;
              $result=$this->buffered_read ($file, $last-$first+1);
            }
            echo "\r\n--$boundary--\r\n";
          }
          else
          {
            /*
            A single range is requested.
            */
            $range=$ranges[0];
            $this->set_range($range, $filesize, $first, $last);
            header("Content-Length: ".($last-$first+1) );
            header("Content-Range: bytes $first-$last/$filesize");
            header('Content-Type: '.$this->contentType);
            fseek($file,$first);
            $this->key=($this->key+$first)%221;
            $result=$this->buffered_read($file, $last-$first+1);
          }
        } else{
          //no byteserving
          header("Accept-Ranges: bytes");
          header("Content-Length: $filesize");
          header('Content-Type: '.$this->contentType);
          $result=$this->simplesend($file,$filesize);
          //$this->buffered_read($file,$filesize);
        }
        fclose($file);
        return $result;
      }

      private function simplesend($file,$size)
      {
           $range = 0;
           if(isset($_SERVER['HTTP_RANGE']))
           {
                  list($a, $range)=explode("=",$_SERVER['HTTP_RANGE']);
                  str_replace($range, "-", $range);
                  $size2=$size-1;
                  $new_length=$size-$range;
                  header("HTTP/1.1 206 Partial Content");
                  header("Content-Length: $new_length");
                  header("Content-Range: bytes $range$size2/$size");
           } else
           {
                  $size2=$size-1;
                  header("Content-Range: bytes 0-$size2/$size");
                  header("Content-Length: ".$size);
           }

           fseek($file,$range);
           $this->key=($this->key+$range)%221;
           if ($this->maxSpeed>0) $buffer_size=$this->maxSpeed*1024; else $buffer_size=8*1024;

           while(!feof($file) and (connection_status()==0))
           {
                  set_time_limit(0);
                  $contents=fread($file,$buffer_size);
                  $contents=$this->encryptContent($contents);
                  echo $contents;
                  flush();
                  ob_flush();
                  if ($this->maxSpeed>0) sleep(1);
           }
           return connection_status();
      }


      private function prepareFile()
      {
        if ($this->fileName=='' && $this->fileLocation!='') $this->fileName=$this->fileLocation;

        if ($this->fileName!='')
        {
          for($i=strlen($this->fileName)-1;$i>0;$i--) if ($this->fileName[$i]=='/') break;
          if ($this->fileName[$i]=='/') $i++;
          $this->fileName=substr($this->fileName,$i,strlen($this->fileName));
        }
        $v=explode('.',$this->fileName);
        $extension = strtolower(end($v));

        $fileTypes=array('swf'=>'application/x-shockwave-flash','pdf'=>'application/pdf','exe'=>'application/octet-stream','zip'=>'application/zip','7z'=>'application/7z','doc'=>'application/msword','xls'=>'application/vnd.ms-excel','ppt'=>'application/vnd.ms-powerpoint','gif'=>'image/gif','png'=>'image/png','jpeg'=> 'image/jpg','jpg'=>'image/jpg','rar'=>'application/rar','ra'=>'audio/x-pn-realaudio','ram'=>'audio/x-pn-realaudio','ogg'=>'audio/x-pn-realaudio','wav'=>'video/x-msvideo','wmv'=>'video/x-msvideo','avi'=>'video/x-msvideo','asf'=>'video/x-msvideo','divx'=>'video/x-msvideo','mp3'=>'audio/mpeg','mp4'=>'audio/mpeg','mpeg'=>'video/mpeg','mpg'=>'video/mpeg','mpe'=>'video/mpeg','mov'=>'video/quicktime','swf'=>'video/quicktime','3gp'=>'video/quicktime','m4a'=>'video/quicktime','aac'=>'video/quicktime','m3u'=>'video/quicktime');
        $array_listen = array('mp3','m3u','m4a','mid','ogg','ra','ram','wm','wav','wma','aac','3gp','avi','mov','mp4','mpeg','mpg','swf','wmv','divx','asf','jpg');

        if (!empty($fileTypes[$extension])) $this->contentType = $fileTypes[$extension]; else $this->contentType='application/octet-stream';

        $this->contentDisposition = 'attachment';
        if($this->doStream == true) {
            if(in_array($extension,$array_listen)){
                $this->contentDisposition = 'inline';
            }
        }


      }

      public function sendFile($fileLocation='',$fileName='',$cacheSupport=false)
      {
        if ($fileLocation!='') $this->fileLocation=$fileLocation;
        if ($fileName!='') $this->fileName=$fileName;
        if ($fileLocation!='' || $fileName!='') $this->prepareFile();

        if (strstr($_SERVER['HTTP_USER_AGENT'], "MSIE")) $this->fileName=preg_replace('/\./', '%2e', $this->fileName, substr_count($this->fileName,'.') - 1);

        //unset magic quotes; otherwise, file contents will be modified
        if(get_magic_quotes_runtime()) set_magic_quotes_runtime(false);
        ini_set('magic_quotes_runtime', 0);

        //do not send cache limiter header
        ini_set('session.cache_limiter','none');

        header('Cache-Control: public');

        if ($cacheSupport)
        {
          $hash = md5_file($this->fileLocation);
          $headers = getallheaders();
           // if Browser sent ID, we check if they match
             if (ereg($hash, $headers['If-None-Match']))
             {
               header('HTTP/1.1 304 Not Modified');
             }
             else
             {
               header('ETag: "{'.$hash.'}"');
             }
        }

        //header('Content-Transfer-Encoding: binary');
        header('Content-Disposition: '.$this->contentDisposition.';filename="'.$this->fileName.'"');
        $result=$this->byteserve($this->fileLocation); 
        return $result;
      }


}





?>