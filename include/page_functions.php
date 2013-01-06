<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


/*!
 * \file page_functions.php
 *
 * \brief Functions for creating Dragon pages.
 */

define('ROBOTS_NO_INDEX_NOR_FOLLOW', 'noindex, nofollow'); // default
define('ROBOTS_NO_FOLLOW', 'nofollow');
define('ROBOTS_NO_INDEX', 'noindex');

/*!
 * \class HTMLPage
 *
 * \brief Class to ease the creation of simpler Dragon pages.
 */
class HTMLPage
{
   /*! \privatesection */

   /*! \brief Id used for CSS differentiation. */
   var $ClassCSS;

   /*! \brief Script of the page relative to the root, e.g: 'forum/index.php' */
   var $BaseName;

   // HTML-head <meta>-tags should be added for the crawler-indexed pages (see sitemap.xml or robots.txt)
   var $meta_description;
   var $meta_robots; // ROBO_...
   var $meta_keywords;


   /*! \publicsection */

   /*!
    * \brief Constructor. Create a new page and initialize it.
    * \param $meta_robots '' (use default from start_html()-func); otherwise ROBO_...
    * \param $meta_description description for page-meta-tag; should be in enlish (not translatable)
    */
   function HTMLPage( $_pageid=false, $meta_robots='', $meta_description='', $meta_keywords='' )
   {
      $this->BaseName = substr( @$_SERVER['PHP_SELF'], strlen(SUB_PATH));

      if( !is_string($_pageid) )
         $_pageid = substr( $this->BaseName, 0, strrpos($this->BaseName,'.'));
      //make it CSS compatible, just allowing the space (see getCSSclass())
      $this->ClassCSS = preg_replace('%[^ a-zA-Z0-9]+%', '-', $_pageid);

      $this->meta_description = $meta_description;
      $this->meta_robots = $meta_robots;
      $this->meta_keywords = $meta_keywords;

      /*
       * a soon bufferization seems to prevent a possible E_WARNING message
       * to disturb later header() functions
       * else we should have to set output_buffering and output_handler
       * in the php.ini file (and adjust our INSTALL file accordingly)
       *
       * NOTE:
       * Before "ob_start('ob_gzhandler');" you may use "ini_set('zlib.output_compression_level', 3);" on-the-fly.
       * Default-level for ZLib-compression is 6, but 3 gives less load on web-server.
       * Or may be set in php.ini-file -> see http://de2.php.net/manual/en/zlib.configuration.php#ini.zlib.output-compression
       */
      ob_start('ob_gzhandler');
   }

   /*!
    * \brief retrieve the CSS class.
    * \note may be multiple, i.e. 'Games Running'
    */
   function getClassCSS( )
   {
      return $this->ClassCSS;
   }

   // \param $meta_robots : ROBO_...
   function set_meta_robots( $meta_robots )
   {
      $this->meta_robots = $meta_robots;
   }

   function set_meta_keywords( $meta_keywords )
   {
      $this->meta_keywords = $meta_keywords;
   }

} //class HTMLPage


/*!
 * \class Page
 *
 * \brief Class to ease the creation of complete Dragon pages.
 */
class Page extends HTMLPage
{
   function Page( $_pageid=false, $meta_robots='', $meta_description='', $meta_keywords='' )
   {
      parent::HTMLPage( $_pageid, $meta_robots, $meta_description, $meta_keywords );
   }
} //class Page

?>
