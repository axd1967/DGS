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

$TranslateGroups[] = "Users";

require_once( "include/std_functions.php" );
require_once( "include/form_functions.php" );
require_once( "include/message_functions.php" );


function make_folder_form_row(&$form, $name, $nr,
                              $bgred, $bggreen, $bgblue, $bgalpha, $fgred, $fggreen, $fgblue,
                              $onstatuspage)
{

   $fcol = RGBA($fgred, $fggreen, $fgblue);

   $name_cel = '<td bgcolor="#' . blend_alpha($bgred, $bggreen, $bgblue, $bgalpha) . '">';
   if( empty($name) )
      $name_cel.= "<font color=\"#$fcol\">" . T_('Folder name') . '</font></td>';
   else
      $name_cel.= "<a style=\"color:'#$fcol'\" href=\"list_messages.php?folder=$nr\">" .
                     make_html_safe($name) . '</a></td>';


   $array = array( 'OWNHTML', $name_cel,
                   'TEXTINPUT', "folder$nr", 32, 32, $name,
                   'DESCRIPTION', T_('Background'),
                   'DESCRIPTION', T_('Red'),
                   'TEXTINPUT', "bgred$nr", 3, 3, "$bgred",
                   'DESCRIPTION', T_('Green'),
                   'TEXTINPUT', "bggreen$nr", 3, 3, "$bggreen",
                   'DESCRIPTION', T_('Blue'),
                   'TEXTINPUT', "bgblue$nr", 3, 3, "$bgblue",
                   'DESCRIPTION', T_('Alpha'),
                   'TEXTINPUT', "bgalpha$nr", 3, 3, "$bgalpha" );

   $form->add_row( $array );

   if( $nr < USER_FOLDERS )
      $array = array( 'OWNHTML', '<td colspan=2></td>' );
   else
      $array = array( 'TAB',
                      'CHECKBOX', "onstatuspage$nr", 't',
                      T_('Show on status page'), $onstatuspage );

   array_push( $array, 'DESCRIPTION', T_('Foreground'),
               'DESCRIPTION', T_('Red'),
               'TEXTINPUT', "fgred$nr", 3, 3, "$fgred",
               'DESCRIPTION', T_('Green'),
               'TEXTINPUT', "fggreen$nr", 3, 3, "$fggreen",
               'DESCRIPTION', T_('Blue'),
               'TEXTINPUT', "fgblue$nr", 3, 3, "$fgblue");

   $form->add_row( $array );
   $form->add_row( array('SPACE'));
}

{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);

   if( !$logged_in )
      error("not_logged_in");
   init_standard_folders();

   $my_id = $player_row['ID'];

   $folders = get_folders($my_id);
   $max_folder = array_reduce(array_keys($folders), "max", USER_FOLDERS-1);
   $status_page_folders = empty($player_row['StatusFolders']) ? array() :
      explode( ',', $player_row['StatusFolders'] );
   $old_statusfolders = $status_page_folders;

   // Update folders

   foreach( $_POST as $key => $val )
   {
      if( !preg_match("/^folder(\d+)$/", $key, $matches) )
         continue;

      $nr = $matches[1];

      $bgred = limit(@$_POST["bgred$nr"], 0, 255, 0);
      $bggreen = limit(@$_POST["bggreen$nr"], 0, 255, 0);
      $bgblue = limit(@$_POST["bgblue$nr"], 0, 255, 0);
      $bgalpha = limit(@$_POST["bgalpha$nr"], 0, 255, 255);
      $fgred = limit(@$_POST["fgred$nr"], 0, 255, 0);
      $fggreen = limit(@$_POST["fggreen$nr"], 0, 255, 0);
      $fgblue = limit(@$_POST["fgblue$nr"], 0, 255, 0);

      $bgcolor = RGBA( $bgred, $bggreen, $bgblue, $bgalpha);
      $fgcolor = RGBA( $fgred, $fggreen, $fgblue);
      $name = trim(@$_POST["folder$nr"]);

      $onstatuspage = ( @$_POST["onstatuspage$nr"] == 't' );

      if( $nr >= USER_FOLDERS and ( in_array($nr, $status_page_folders) xor $onstatuspage ) )
      {
         if( $onstatuspage )
            array_push($status_page_folders, $nr);
         else
         {
            $i=array_search( $nr, $status_page_folders);
            if($i!==false)
               unset($status_page_folders[$i]);
         }
      }

      if( empty($name) && $nr > $max_folder )
         continue;

      $newfolder = array($name, $bgcolor, $fgcolor);
      if( !isset($folders[$nr]) )
      {
         $delete = false;
         $update = false;
         //else insert $newfolder
      }
      else
      {
         if( $folders[$nr] === $newfolder )
            continue;
         if( $nr >= USER_FOLDERS )
         {
            if( !empty($name) )
               $delete = false;
            elseif( !folder_is_empty($nr, $my_id) )
               continue;
            else
               $delete = true;
            $update = true;
         }
         else
         {
            $delete = ( empty($name) or $STANDARD_FOLDERS[$nr] === $newfolder );
            $update = ( $STANDARD_FOLDERS[$nr] !== $folders[$nr] );
         }
      }


      if( $delete )
      {
         $query = "DELETE FROM Folders WHERE uid='$my_id' AND Folder_nr=$nr LIMIT 1";
      }
      else if( $update )
      {
         list($oldname, $oldbgcolor, $oldfgcolor) = $folders[$nr];

         $query = "UPDATE Folders SET ";
         $updates = array();
         if( $name !== $oldname ) array_push($updates, "Name='$name'");
         if( $bgcolor !== $oldbgcolor ) array_push($updates, "BGColor='$bgcolor'");
         if( $fgcolor !== $oldfgcolor ) array_push($updates, "FGColor='$fgcolor'");

         if( !(count($updates) > 0) )
            continue;

         $query .= implode(",", $updates);
         $query .= " WHERE uid='$my_id' AND Folder_nr=$nr LIMIT 1";
      }
      else
      {
         $query = "INSERT INTO Folders SET " .
            "uid='$my_id', " .
            "Folder_nr=$nr, " .
            "Name='$name', " .
            "BGColor='$bgcolor', " .
            "FGColor='$fgcolor' ";
      }

      mysql_query($query) or die(mysql_error());
   }


   if( $status_page_folders != $old_statusfolders )
   {
      mysql_query("UPDATE Players SET StatusFolders='" .
                  implode(',',$status_page_folders) . "' WHERE ID=$my_id LIMIT 1")
         or die(mysql_error());
   }

   $folders = get_folders($my_id);
   $max_folder = array_reduce(array_keys($folders), "max", USER_FOLDERS-1);




   start_page(T_("Edit message folders"), true, $logged_in, $player_row );

   echo "<center>\n";

   $form = new Form( 'folderform', 'edit_folders.php', FORM_POST );

   $form->add_row( array( 'HEADER', T_('Edit message folders') ) );

   foreach( $folders as $nr => $fld )
   {
      list($name, $bgcolor, $fgcolor) = $fld;
      list($bgred,$bggreen,$bgblue,$bgalpha)= split_RGBA($bgcolor, 0);
      list($fgred,$fggreen,$fgblue,$dummy)= split_RGBA($fgcolor);

      make_folder_form_row($form, $name, $nr,
                           $bgred, $bggreen, $bgblue, $bgalpha, $fgred, $fggreen, $fgblue,
                           in_array($nr, $status_page_folders));
   }



// And now three empty ones:

   for($i=$max_folder+1; $i<=$max_folder+3; $i++)
   {
      make_folder_form_row($form, '', $i, 247, 245, 227, 255, 0, 0, 0, false);
   }



   $form->add_row( array( 
//                          'SUBMITBUTTON', 'action_preview', T_('Preview'),
                          'SUBMITBUTTON', 'action', T_('Update')) );

   $form->echo_string();

   echo "</center>\n";

   $menu_array = array(
         T_('Message list') => 'list_messages.php',
         T_('Show/edit userinfo') => 'userinfo.php',
      );

   end_page(@$menu_array);
}
?>
