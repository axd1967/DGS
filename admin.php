<?php

/*
Dragon Go Server
Copyright (C) 2001-2002  Erik Ouchterlony

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

/* The code in this file is written by Ragnar Ouchterlony */

require( "include/std_functions.php" );
require( "include/form_functions.php" );

{
  connect2mysql();

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

  if( !$logged_in )
    error("not_logged_in");

  if( $player_row['Adminlevel'] < 2 )
    error("adminlevel_too_low");

  start_page(T_('Admin'), true, $logged_in, $player_row);

  echo "<center>\n";
  echo form_start( 'adminform', 'do_admin.php', 'POST' );

  /* Add language for translation */
  echo form_insert_row( 'HEADER', T_('Add language for translation') );
  echo form_insert_row( 'DESCRIPTION', T_('Two-letter language code'),
                        'TEXTINPUT', 'twoletter', 30, 10, '' );
  echo form_insert_row( 'DESCRIPTION', T_('Language name (i.e. English)'),
                        'TEXTINPUT', 'langname', 30, 50, '' );
  echo form_insert_row( 'DESCRIPTION', T_("Character encoding (i.e. 'iso-8859-1')"),
                        'TEXTINPUT', 'charenc', 30, 50, '' );
  echo form_insert_row( 'SUBMITBUTTON', 'addlanguage', T_('Add language') );

  /* Set translator privileges for user */
  echo form_insert_row( 'HEADER', T_('Set translator privileges for user') );
  echo form_insert_row( 'DESCRIPTION', T_('User to set privileges for (use the userid)'),
                        'TEXTINPUT', 'transluser', 30, 80, '' );
  echo form_insert_row( 'DESCRIPTION', T_('Select language to make user translator for that language.'),
                        'SELECTBOX', 'transladdlang', 1,
                        get_known_languages_with_full_names(), array(), false,
                        'SUBMITBUTTON', 'transladd', T_('Add language for translator') );
  echo form_insert_row( 'DESCRIPTION', T_('Select the languages the user should be allowed to translate'),
                        'SELECTBOX', 'transllang[]', 7,
                        get_known_languages_with_full_names(), array(), true,
                        'SUBMITBUTTON', 'translpriv', T_('Set user privileges') );

  echo form_end();

  end_page();
}