<?php
/*
 * Copyright (c) 2013 Baptiste Lepers
 * Released under MIT License
 *
 * Options - Entry point
 */

class Pages_Sitemap_Index {
   public static $description = "sitemap";
   public static $isOptional = true;
   public static $activatedByDefault = true;
   public static $showOnMenu = false;

   public static $isContentPlugin = true; // necessary to be on the $plugins array and have writeJSON called
         // this is not a "user content" plugin - it does not have $userContentName set.

   public static function getOptions() {
      global $sitemap_activated;
      if(!$sitemap_activated)
         return array();

      $robot = new File('..', 'robots.txt');
      $sitemap = new File('..', 'sitemap.xml');
      $rights = array(
         '../robots.txt'      => $robot->isWritable(true),
         '../sitemap.xml'   => $sitemap->isWritable(true),
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

   public static function writeJSON($args) {
      global $picpath;

      $currentdir = &$args['dir'];
      if($currentdir->isUpdated) // we only care about the parent dir
         return;

      // Convert the json into a list that we can put in the sitemap
      $new_json = &$args['json'];
      $new_dirs = array('LOC' => array(), 'LASTMOD' => array(), 'COMPLETEPATH' => array());
      $new_paths = array();
      if(!isset($new_json['dirs']) || $new_json['dirs'] == null) {
         // no directory
      } else {
         foreach($new_json['dirs'] as $d) {
            $realdir = new IndexDir($currentdir->completePath, $d['url']);
            $new_dirs['DIR'][] = $realdir;
            $new_dirs['LOC'][] = File_JSON::forceUTF8($realdir->getURL());
            $new_dirs['LASTMOD'][] = date('Y-m-d');
            $new_paths[$realdir->completePath] = count($new_dirs['LASTMOD']) - 1;
         }
      }

      // Read the previous sitemap, and keep unseen directories
      $sitemap = new File('..', 'sitemap.xml');
      if(!$sitemap->isWritable(true))
         die("Cannot write ../sitemap.xml file. Disable sitemap in the options.\n");

      $content = $sitemap->getContent();
      $xml = simplexml_load_string($content);
      if(isset($xml->url)) {
         foreach($xml->url as $url) {
            if(isset($url->loc)) {
               $path_array = explode("#!", $url->loc);
               $completepath = end($path_array);

               $xmldir = new IndexDir($picpath, $completepath);
               if(!$xmldir->exists()) {
                  $completepath = @utf8_decode($completepath);
                  $xmldir = new IndexDir($picpath, $completepath);
                  if(!$xmldir->exists())
                     continue;
               }
               if($xmldir->path !== $currentdir->completePath) {
                  $new_dirs['LOC'][] = $url->loc;
                  $new_dirs['LASTMOD'][] = $url->lastmod;
                  continue;
               }
               if($xmldir->isUpdated)
                  continue;
               $index = $new_paths[$xmldir->completePath];
               $new_dirs['LASTMOD'][$index] = $url->lastmod;
            }
         }
      }

      $template = new liteTemplate();
      $template->file('pages/sitemap/tpl/sitemap.xml');
      $template->assignTag('BALISE', '1', array(
         'LOC' => $new_dirs['LOC'],
         'LASTMOD' => $new_dirs['LASTMOD'],
      ));
      $sitemap->writeContent($template->returnTpl());


      $robot = new File('..', 'robots.txt');
      if(!$robot->isWritable(true))
         die("Cannot write ../robots.txt file. Disable sitemap in the options.\n");
      $content = $robot->getContent();
      if(strstr($content, 'Sitemap:') !== FALSE)
         return;

      $https= isset($_SERVER['HTTPS']) && (strcasecmp('off', $_SERVER['HTTPS']) !== 0);
      $hostname = $_SERVER['SERVER_ADDR'];
      $port = $_SERVER['SERVER_PORT'];
      $path_only = implode("/", (explode('/', $_SERVER["REQUEST_URI"], -1)));
      $sitemapurl = 'Sitemap: http'.($https?'s':'').'://'.$_SERVER['SERVER_NAME'].($port!=80?':'.$port:'').str_replace("admin", "", File::simplifyPath($path_only)).'sitemap.xml'."\n";
      $content .= $sitemapurl;
      $robot->writeContent($content);
   }
};

