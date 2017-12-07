<?php defined('SYSPATH') or die('No direct script access.');


class Model_BSX_Gallery
{
    public static function getGallery($ident,$limit=999) {
        $gallery=sql_row('SELECT * FROM bsc_galleries WHERE pident=:ident',array(':ident'=>$ident));
        if (!$gallery) return false;
        $gallery['items']=sql_rows('SELECT ptable, pid, pname, ptitle FROM bs_attachments WHERE ptable=\'bsc_galleries\' AND pid=:pid AND pstatus=2 LIMIT '.$limit,array(':pid'=>$gallery['id']));
        foreach ($gallery['items'] as &$row) {

        }
        return $gallery;
    }

    public static function getGalleryByModrewrite($modrewrite,$prefix='galeria',$limit=999) {
        $gallery=sql_row('SELECT * FROM bsc_galleries WHERE pmodrewrite=:modrewrite',array(':modrewrite'=>$modrewrite));
        if (!$gallery) return false;
        $gallery['items']=sql_rows('SELECT ptable, pid, pname, ptitle FROM bs_attachments WHERE ptable=\'bsc_galleries\' AND pid=:pid AND pstatus=2 LIMIT '.$limit,array(':pid'=>$gallery['id']));
        $gallery['url']='/'.$prefix.'/'.$gallery['pmodrewrite'];
        foreach ($gallery['items'] as &$row) {

        }
        return $gallery;
    }

    public static function getLastGallery($count=10) {
        $params=array(':idsite'=>Model_BSX_Core::$bsx_cfg['id']);
        $gallery=sql_row('SELECT * FROM bsc_galleries WHERE (idsite=:idsite OR idsite=0 OR idsite IS NULL) ORDER BY id DESC LIMIT '.$count,$params);
        if (!$gallery) return false;
        $gallery['items']=sql_rows('SELECT ptable, pid, pname, ptitle FROM bs_attachments WHERE ptable=\'bsc_galleries\' AND pid=:pid AND pstatus=2',array(':pid'=>$gallery['id']));
        foreach ($gallery['items'] as &$row) {
        }
        return $gallery;
    }

    public static function getGalleries($limit=10) {
        $params=array(':idsite'=>Model_BSX_Core::$bsx_cfg['id']);
        $galleries=sql_rows('SELECT * FROM bsc_galleries WHERE (idsite=:idsite OR idsite=0 OR idsite IS NULL) LIMIT '.$limit,$params);
        foreach ($galleries as &$gallery) {
            $gallery['url']='/galerie/'.$gallery['pmodrewrite'];
            $gallery['items']=sql_rows('SELECT ptable, pid, pname, ptitle FROM bs_attachments WHERE ptable=\'bsc_galleries\' AND pid=:pid AND pstatus=2 LIMIT 1',array(':pid'=>$gallery['id']));
        }
        return $galleries;
    }
}