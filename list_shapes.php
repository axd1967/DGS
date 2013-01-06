<?php
/*
Dragon Go Server
Copyright (C) 2001-2013  Erik Ouchterlony, Jens-Uwe Gaspar

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

$TranslateGroups[] = "Games";

require_once 'include/std_functions.php';
require_once 'include/std_classes.php';
require_once 'include/table_columns.php';
require_once 'include/filter.php';
require_once 'include/classlib_profile.php';
require_once 'include/shape_control.php';

$GLOBALS['ThePage'] = new Page('ShapeList');


{
   connect2mysql();

   $logged_in = who_is_logged( $player_row);
   if( !$logged_in )
      error('login_if_not_logged_in', 'list_shapes');
   $my_id = $player_row['ID'];

   $page = "list_shapes.php";

/* Actual REQUEST calls used:
     ''                       : show shapes
*/

   // init search profile
   $search_profile = new SearchProfile( $my_id, PROFTYPE_FILTER_SHAPES );
   $sfilter = new SearchFilter( '', $search_profile );
   $search_profile->register_regex_save_args( 'user|pub' ); // named-filters FC_FNAME
   $table = new Table( 'shapes', $page, null, '', TABLE_ROWS_NAVI );
   $table->set_profile_handler( $search_profile );
   $search_profile->handle_action();

   // table filters
   $sfilter->add_filter( 3, 'Text', 'SHPP.Handle', true,
         array( FC_FNAME => 'user' ));
   $sfilter->add_filter( 4, 'Text', "SHP.Name #OP #VAL", true,
         array( FC_SIZE => 15, FC_SUBSTRING => 1, FC_START_WILD => 3, FC_SQL_TEMPLATE => 1 ));
   $sfilter->add_filter( 5, 'Numeric', 'SHP.Size', true );
   $sfilter->add_filter( 6, 'Boolean', 'SHP.Flags & '.SHAPE_FLAG_PUBLIC, true,
         array( FC_FNAME => 'pub', FC_LABEL => ' '.T_('Public#shape'), FC_DEFAULT => 1 ));
   $sfilter->add_filter( 7, 'RelativeDate', 'SHP.Created', true,
         array( FC_TIME_UNITS => FRDTU_ALL_ABS, FC_SIZE => 6 ) );
   $sfilter->add_filter( 8, 'RelativeDate', 'SHP.Lastchanged', true,
         array( FC_TIME_UNITS => FRDTU_ALL_ABS, FC_SIZE => 6 ) );
   $sfilter->init();

   $filter_text =& $sfilter->get_filter(4);
   $rx_term = implode('|', $filter_text->get_rx_terms() );

   // init table
   $table->register_filter( $sfilter );
   $table->add_or_del_column();

   // add_tablehead($nr, $descr, $attbs=null, $mode=TABLE_NO_HIDE|TABLE_NO_SORT, $sortx='')
   $table->add_tablehead( 1, T_('ID#header'), 'Button', TABLE_NO_HIDE, 'ID-');
   $table->add_tablehead( 2, new TableHead( T_('Shape Information'), 'images/shape.gif'), 'ImagesLeft', TABLE_NO_HIDE);
   $table->add_tablehead( 3, T_('Author#header'), 'User', 0, 'SHPP_Handle+');
   $table->add_tablehead( 4, T_('Name#header'), 'Text', TABLE_NO_HIDE, 'Name+');
   $table->add_tablehead( 5, T_('Size#header'), 'Number', TABLE_NO_HIDE, 'Size+');
   $table->add_tablehead( 6, T_('Flags#header'), 'Enum', TABLE_NO_HIDE|TABLE_NO_SORT, 'Flags+');
   $table->add_tablehead( 7, T_('Created#header'), 'Date', 0, 'Created-');
   $table->add_tablehead( 8, T_('Updated#header'), 'Date', 0, 'Lastchanged-');

   $table->set_default_sort( 4, 1 ); //on Name, ID

   $iterator = new ListIterator( 'Shape.list',
         $table->get_query(),
         $table->current_order_string(),
         $table->current_limit_string() );
   $iterator = Shape::load_shapes( $iterator );

   $show_rows = $table->compute_show_rows( $iterator->ResultRows );
   $table->set_found_rows( mysql_found_rows('Shape.list.found_rows') );


   $title = T_('Shapes');
   start_page($title, true, $logged_in, $player_row, button_style($player_row['Button']) );

   section('Shape', $title );

   while( ($show_rows-- > 0) && list(,$arr_item) = $iterator->getListIterator() )
   {
      list( $shape, $orow ) = $arr_item;
      $shape_id = $shape->ID;
      $uid = $shape->uid;
      $row_str = array();

      if( @$table->Is_Column_Displayed[ 1] )
         $row_str[ 1] = button_TD_anchor( "view_shape.php?shape=$shape_id", $shape_id);
      if( @$table->Is_Column_Displayed[ 2] )
      {
         $links = echo_image_shapeinfo($shape_id, $shape->Size, $shape->Snapshot, /*edit-goban*/true );
         if( $my_id == $shape->uid )
            $links .= ' ' . anchor( "edit_shape.php?shape=$shape_id",
               image( 'images/edit.gif', 'E', '', 'class="Action InTextImage"' ), T_('Edit Shape'));
         $row_str[ 2] = $links;
      }
      if( @$table->Is_Column_Displayed[ 3] )
         $row_str[ 3] = user_reference( REF_LINK, 1, '', $uid, $shape->User->Handle, '');
      if( @$table->Is_Column_Displayed[ 4] )
      {
         $str = make_html_safe( $shape->Name, false, $rx_term );
         if( strlen($shape->Notes) )
            $str .= echo_image_note( $shape->Notes );
         $row_str[ 4] = $str;
      }
      if( @$table->Is_Column_Displayed[ 5] )
         $row_str[ 5] = $shape->Size;
      if( @$table->Is_Column_Displayed[ 6] )
         $row_str[ 6] = ShapeControl::formatFlags($shape->Flags);
      if( @$table->Is_Column_Displayed[ 7] )
         $row_str[ 7] = formatDate($shape->Created);
      if( @$table->Is_Column_Displayed[ 8] )
         $row_str[ 8] = formatDate($shape->Lastchanged);

      $table->add_row( $row_str );
   }

   // print table
   $table->echo_table();


   //$no_prof = URI_AMP . $search_profile->get_request_params()->get_url_parts(); // add if not to load def-profile
   $menu_array = array();
   $menu_array[T_('Shapes')] = "list_shapes.php";
   $menu_array[T_('All shapes')] = "list_shapes.php?pub=0".SPURI_ARGS.'pub';
   $menu_array[T_('My shapes')] = "list_shapes.php?user=".urlencode($player_row['Handle']).URI_AMP.'pub=0'.SPURI_ARGS.'user,pub';
   $menu_array[T_('New Shape (Goban Editor)')] = "goban_editor.php";

   end_page(@$menu_array);
}

?>
