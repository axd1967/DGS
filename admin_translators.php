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

/* The code in this file is written by Ragnar Ouchterlony */

$TranslateGroups[] = "Admin";

require( "include/std_functions.php" );
require( "include/form_functions.php" );

{
  connect2mysql();

  $logged_in = is_logged_in($handle, $sessioncode, $player_row);

  if( !$logged_in )
    error("not_logged_in");

  if( !($player_row['admin_level'] & ADMIN_TRANSLATORS) )
    error("adminlevel_too_low");

  start_page(T_('Translator admin'), true, $logged_in, $player_row);

  echo "<center>\n";

  if( !empty($_GET['msg']) )
    echo "<p><b><font color=\"green\">" . $_GET['msg'] . "</font></b><hr>\n";

  $translator_form = new Form( 'translatorform', 'admin_do_translators.php', FORM_POST );

  /* Add language for translation */
  $translator_form->add_row( array( 'HEADER', T_('Add language for translation') ) );
  $translator_form->add_row( array( 'DESCRIPTION', T_('Two-letter language code'),
                                    'TEXTINPUT', 'twoletter', 30, 10, '' ) );
  $translator_form->add_row( array( 'DESCRIPTION', T_('Language name (i.e. English)'),
                                    'TEXTINPUT', 'langname', 30, 50, '' ) );
  $translator_form->add_row( array( 'DESCRIPTION', T_("Character encoding (i.e. 'iso-8859-1')"),
                                    'TEXTINPUT', 'charenc', 30, 50, '' ) );
  $translator_form->add_row( array( 'SUBMITBUTTON', 'addlanguage', T_('Add language') ) );

  /* Set translator privileges for user */
  $translator_form->add_row( array( 'HEADER', T_('Set translator privileges for user') ) );
  $translator_form->add_row( array( 'DESCRIPTION', T_('User to set privileges for (use the userid)'),
                                    'TEXTINPUT', 'transluser', 30, 80, '' ) );
  $translator_form->add_row(
     array( 'DESCRIPTION', T_('Select language to make user translator for that language.'),
            'SELECTBOX', 'transladdlang', 1,
            get_language_descriptions_translated(), array(), false,
            'SUBMITBUTTON', 'transladd', T_('Add language for translator') ) );
  $translator_form->add_row(
     array( 'DESCRIPTION', T_('Select the languages the user should be allowed to translate'),
            'SELECTBOX', 'transllang[]', 7,
            get_language_descriptions_translated(), array(), true,
            'SUBMITBUTTON', 'translpriv', T_('Set user privileges') ) );

  $translator_form->echo_string();

  end_page();
}