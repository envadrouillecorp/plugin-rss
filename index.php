<?php
/*
 * Copyright (c) 2013 Baptiste Lepers
 * Released under MIT License
 *
 * Options - Entry point
 */

class Pages_Rss_Index {
   public static $description = "rss";
   public static $isOptional = true;
   public static $activatedByDefault = true;
   public static $showOnMenu = false;

   public static $isContentPlugin = true; // necessary to be on the $plugins array and have writeJSON called
         // this is not a "user content" plugin - it does not have $userContentName set.

   public static function getOptions() {
      global $rss_activated;
      if(!$rss_activated)
         return array();

      $rss = new File('..', 'rss.xml');
      $rights = array(
         '../rss.xml'   => $rss->isWritable(true),
      );
      $failure = false;
      foreach($rights as $file=>$r) {
         if(!$r) {
            $failure = true;
            break;
         }
      }
      if($failure)
         Controller::notifyUser('error', 'permission', $rights);

      return array();
   }

   static public function getUserFunctions() {
       return array(
           file_get_contents('./pages/rss/scripts/jgallery.rss.fun.js')
       );
   }

   public static function writeJSON($args) {
      global $picpath;

      $currentdir = &$args['dir'];
      if($currentdir->isUpdated) // we only care about the parent dir
         return;

      $new_json = &$args['json'];
      $new_dirs = array('LOC' => array(), 'LASTMOD' => array(), 'TITLE' => array(), 'GUID' => array());
      $new_paths = array();
      if(!isset($new_json['dirs']) || $new_json['dirs'] == null) {
         // no directory
      } else {
         foreach($new_json['dirs'] as $d) {
            $realdir = new IndexDir($currentdir->completePath, $d['url']);
            $new_dirs['DIR'][] = $realdir;
            //$rssurl = File_JSON::forceUTF8(htmlentities($realdir->getURL(),ENT_COMPAT,'utf-8'));
            $rssurl = File_JSON::forceUTF8($realdir->getURL());
            $rssurl = str_replace(" ", "%20", $rssurl);
            $new_dirs['LOC'][] = $rssurl;
            $new_dirs['GUID'][] = $rssurl;
            $new_dirs['TITLE'][] = File_JSON::forceUTF8($realdir->name);
            $new_dirs['LASTMOD'][] = date('D, d M Y H:i:s O');
            $new_paths[$realdir->completePath] = count($new_dirs['LASTMOD']) - 1;
         }
      }

      // Read the previous rss, and keep unseen directories
      $rss = new File('..', 'rss.xml');
      if(!$rss->isWritable(true))
         die("Cannot write ../rss.xml file. Disable rss in the options.\n");

      $content = $rss->getContent();
      $xml = simplexml_load_string($content);
      if(isset($xml->channel)) {
         foreach($xml->channel->item as $url) {
            if(isset($url->link)) {
               $path_array = explode("#!", ((string) $url->link));
               $completepath = end($path_array);
               $completepath = str_replace('%20', ' ', $completepath);

               $xmldir = new IndexDir($picpath, $completepath);
               if(!$xmldir->exists()) {
                  $completepath = @utf8_decode($completepath);
                  $xmldir = new IndexDir($picpath, $completepath);
                  if(!$xmldir->exists())
                     continue;
               }
               if($xmldir->path !== $currentdir->completePath) {
                  $new_dirs['LOC'][] = ((string) $url->link);
                  $new_dirs['TITLE'][] = ((string) $url->title);
                  $new_dirs['GUID'][] = ((string) $url->guid);
                  $new_dirs['LASTMOD'][] = ((string) $url->pubDate);
                  continue;
               }
               if($xmldir->isUpdated)
                  continue;
               $index = $new_paths[$xmldir->completePath];
               $new_dirs['LASTMOD'][$index] = ((string) $url->pubDate);
            }
         }
      }

      $https= isset($_SERVER['HTTPS']) && (strcasecmp('off', $_SERVER['HTTPS']) !== 0);
      $hostname = $_SERVER['SERVER_ADDR'];
      $port = $_SERVER['SERVER_PORT'];
      $path_only = implode("/", (explode('/', $_SERVER["REQUEST_URI"], -1)));
      $url = 'http'.($https?'s':'').'://'.$_SERVER['SERVER_NAME'].($port!=80?':'.$port:'').str_replace("admin", "", File::simplifyPath($path_only));

      $template = new liteTemplate();
      $template->file('pages/rss/tpl/rss.xml');
      $template->assignTag('BALISE', '1', array(
         'LOC' => $new_dirs['LOC'],
         'TITLE' => $new_dirs['TITLE'],
         'GUID' => $new_dirs['GUID'],
         'LASTMOD' => $new_dirs['LASTMOD'],
      ));
      $template->assign(array('URL' => $url));
      $rss->writeContent($template->returnTpl());
   }
};

