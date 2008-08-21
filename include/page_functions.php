<?php
/*
Dragon Go Server
Copyright (C) 2001-2007  Erik Ouchterlony, Rod Ival

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

$TranslateGroups[] = "Common";


/*!
 * \file page_functions.php
 *
 * \brief Functions for creating Dragon pages.
 */

/*!
 * \class HTMLPage
 *
 * \brief Class to ease the creation of simpler Dragon pages.
 */

class HTMLPage
{
   /*! \privatesection */

   /*! \brief Id used for CSS differentiation. */
   var $Id;

   /*! \brief Script of the page relative to the root.
    * e.g: 'forum/index.php'
    */
   var $BaseName;

   /*! \publicsection */

   /*! \brief Constructor. Create a new page and initialize it. */
   function HTMLPage( $_pageid=false)
   {
      $this->BaseName = substr( @$_SERVER['PHP_SELF'], strlen(SUB_PATH));

      if( !is_string($_pageid) )
         $_pageid = substr( $this->BaseName, 0, strrpos($this->BaseName,'.'));
      //make it CSS compatible, just allowing the space (see getCSSclass())
      $this->Id = preg_replace('%[^ a-zA-Z0-9]+%', '-', $_pageid);

      /*
       * a soon bufferization seems to prevent a possible E_WARNING message
       * to disturb later header() functions
       * else we should have to set output_buffering and output_handler
       * in the php.ini file (and adjust our INSTALL file accordingly)
       */
      ob_start('ob_gzhandler');
   }

   /*! \brief retrieve the CSS class.
    * may be multiple, i.e. 'Games Running'
    */
   function getCSSclass( )
   {
      return $this->Id;
   }

} //class HTMLPage


/*!
 * \class Page
 *
 * \brief Class to ease the creation of complete Dragon pages.
 */

class Page extends HTMLPage
{
   function Page( $_pageid=false )
   {
      parent::HTMLPage( $_pageid );
   }
} //class Page

?>
