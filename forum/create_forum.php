<?php
/*
Dragon Go Server
Copyright (C) 2001-2003  Erik Ouchterlony

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

/*****************
* >>Warning: This file is no more used (moved in forum/admin.php)
******************/

require_once( "forum_functions.php" );
//require_once( "../include/form_functions.php" );

{
   connect2mysql();


   $logged_in = is_logged_in($handle, $sessioncode, $player_row);

   if( ($reply > 0) and !$logged_in )
      error("not_logged_in");

   if( $player_row['Adminlevel'] < 2 )
      error("adminlevel_too_low");

   start_page(T_('Create new forum'), true, $logged_in, $player_row);

   sysmsg(@$_GET['msg']);

   if( $_POST['do_create'] == 'Y' )
   {
      $results = mysql_query('SELECT Name,SortVal FROM Forums ORDER BY SortVal');

      $sortval = -100;
      while( $row = mysql_fetch_array( $results ) and
             $row['Name'] !== $_POST['BeforeForum'])
      {
         $sortval = $row['SortVal'];
      }

      if( $row['Name'] === $_POST['BeforeForum'] )
         $sortval = ( $sortval + $row['SortVal'] ) / 2;
      else
         $sortval += 1;

      mysql_query('INSERT INTO Forums SET ' .
                  'Name="' . $_POST['Name'] . '", ' .
                  'Description="' . $_POST['Description'] . '", ' .
                  'Sortval=' . $sortval ) or die(mysql_error());

      $msg = urlencode(T_('Forum added!'));

      jump_to("forum/create_forum.php?msg=$msg");
   }

   $results = mysql_query('SELECT Name FROM Forums ORDER BY SortVal');

   while( $row = mysql_fetch_array( $results ) )
      $forum_array[$row['Name']] = $row['Name'];

   $forum_array['_??_'] = T_('None (set last)');


   $form = new Form( 'addforum', 'create_forum.php', FORM_POST );

   $form->add_row( array('DESCRIPTION', T_('Name'),
                         'TEXTINPUT', 'Name', 40, 40, "" ));
   $form->add_row( array('DESCRIPTION', T_('Description'),
                         'TEXTAREA', 'Description', 40, 4, ""));
   $form->add_row( array('DESCRIPTION', T_('List before forum'),
                         'SELECTBOX', 'BeforeForum', 1, $forum_array, '_??_', false));
   $form->add_row( array('HIDDEN', 'do_create', 'Y',
                         'SUBMITBUTTON', 'submit', T_('Add forum') ) );

   $form->echo_string();

   end_page();
}
?>