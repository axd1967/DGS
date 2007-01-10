<?php
/*
Dragon Go Server
Copyright (C) 2001-2006  Erik Ouchterlony, Rod Ival

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation,
Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );
require_once( 'include/table_infos.php' );

define('USER_BIO_ADDENTRIES', 3);


function find_category_box_text($cat)
{
   global $categories;

   if( in_array($cat, array_keys($categories)) )
      return $cat;
   else
      return '';
}

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");

   $editorder = isset($_REQUEST['editorder']);

   $result = mysql_query("SELECT * FROM Bio where uid=" . $player_row["ID"]
               . " order by SortOrder, ID")
      or error('mysql_query_failed', 'edit_bio.find_bios');


   $categories = array( '' => T_('Other:'),
                        'Country' => T_('Country'),
                        'City' => T_('City'),
                        'State' => T_('State'),
                        'Club' => T_('Club'),
                        'Homepage' => T_('Homepage'),
                        'Email' => T_('Email'),
                        'ICQ-number' => T_('ICQ-number'),
                        'Game preferences' => T_('Game preferences'),
                        'Hobbies' => T_('Hobbies'),
                        'Occupation' => T_('Occupation'),
                        'Native Language' => T_('Native Language'),
                        'Language Competence' => T_('Language Competence'),
                        );



   $page = "edit_bio.php";
   $title = T_("Edit biographical info");
   $othertitle = T_("Edit biographical order");
   if ( $editorder ) {
      $str = $title;
      $title = $othertitle;
      $othertitle = $str;
   }

   start_page( $title, true, $logged_in, $player_row );
   echo "<h3 class=header>$title</h3>\n";

   echo "<CENTER>\n";

   {
      $cat_max = 40;
      $cat_width = 20;
      $text_width = 60;
      $text_height = 4;

      $moveurl = 'change_bio.php';
      if ( !$editorder )
      {
         $bio_form = new Form( 'bioform', $moveurl, FORM_POST );
      }
      else
      {
         $bio_table= new Table_info('biomove');
      }
      $moveurl.= '?';

      while( $row = mysql_fetch_array( $result ) )
      {
         $cat = find_category_box_text($row["Category"]);

         if ( !$editorder )
         {
            $bio_row = array(
               'CELL', 0, 'class=header',
               'SELECTBOX', "category".$row["ID"], 1, $categories, $cat, false,
               'BR',
               'TEXTINPUT', "other".$row["ID"], $cat_width, $cat_max
                          , ($cat == '' ? $row["Category"] : '' ),
               'CELL', 0, 'class=info',
               'TEXTAREA', "text".$row["ID"], $text_width, $text_height, $row["Text"]
            );

            $bio_form->add_row( $bio_row );
         }
         else
         {
            $bio_table->add_row( array(
               'header' => '<div class=bold>'
                     . make_html_safe($cat == ''
                                 ? $row["Category"] : $categories[$cat] )
                     . "\n</div><div class=center>\n"
                     . anchor($moveurl.'move'.$row['ID'].'=1'
                           , image( 'images/down.png', 'down')
                           , T_("Move down"))
                     . "\n"
                     . anchor($moveurl.'move'.$row['ID'].'=-1'
                           , image( 'images/up.png', 'up')
                           , T_("Move up"))
                     . "\n</div>",
               'info' => make_html_safe($row["Text"],true),
            ) );
         }
      } //while($row)

      if ( !$editorder )
      {
         // And now some empty ones:
         for($i=1; $i <= USER_BIO_ADDENTRIES; $i++)
         {
            $bio_form->add_row( array(
                  'CELL', 0, 'class=newheader',
                  'SELECTBOX', "newcategory" . $i, 1, $categories, '', false,

                  'BR',
                  'TEXTINPUT', "newother" . $i, $cat_width, $cat_max, "",

                  'CELL', 0, 'class=newinfo',
                  'TEXTAREA', "newtext" . $i, $text_width, $text_height, "",
               ) );
         }

         $bio_form->add_row( array(
                  'HIDDEN', 'newcnt', USER_BIO_ADDENTRIES,
                  'SUBMITBUTTON', 'action', T_('Change bio')
               ) );
         $bio_form->echo_string(1);
      }
      else
      {
         $bio_table->echo_table();
      }
   }

   echo "</CENTER><BR>\n";


   if ( $editorder ) {
      $page = $page;
   } else {
      $page = make_url($page, array( 'editorder' => '1') );
   }
   $menu_array = array(
         $othertitle  => $page,
         T_('My user info') => 'userinfo.php',
      );

   end_page(@$menu_array);
}
?>
