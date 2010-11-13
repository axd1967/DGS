<?php
/*
Dragon Go Server
Copyright (C) 2001-2010  Erik Ouchterlony, Rod Ival, Jens-Uwe Gaspar

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

/*
 * Code example using QuerySQL-class.
 *
 * Usage: php code_examples/query_sql.php
 */

require_once( "include/quick_common.php" );
require_once( "include/std_classes.php" );

function error( $msg ) { echo "ERROR: $msg\n"; }

{
   // construct
   $q1 = new QuerySQL(
      SQLP_FIELDS, 'P.ID', 'P.Name', 'P.Handle',
      SQLP_FROM, "Players P",
      SQLP_HAVING, 'ID > 10'
   );
   $q2 = new QuerySQL();

   // add variaous sql-parts for Q1
   $q1->add_part( SQLP_OPTS, 'DISTINCT' );
   $q1->add_part( SQLP_WHERE, 'P.ID=123' );
   $q1->add_part( SQLP_WHERE, "P.Name LIKE 'Pete%'" );
   $q1->add_part( SQLP_ORDER, 'P.ID DESC' );
   $q1->add_part( SQLP_LIMIT, '3,7' );
   echo "-------------\nQuerySQL #1:\n" . $q1->get_select() . "\n\n";

   // add variaous sql-parts for Q2
   $q2->add_part( SQLP_FIELDS, 'G.*' );
   $q2->add_part( SQLP_FROM, 'Games G' );
   $q2->add_part( SQLP_FROM, 'INNER JOIN Forums F ON F.ID=G.ID' );
   $q2->add_part( SQLP_WHERE, 'G.Moves > 10' );
   $q2->add_part( SQLP_LIMIT, '3,7' );
   $q2->add_part( SQLP_HAVING, 'goodrating' );
   echo "-------------\nQuerySQL #2:\n" . $q2->get_select() . "\n\n";

   // merge Q1 and Q2 into Q3 using OR'ing
   $q3 = $q1->merge_or($q2);
   echo "-------------\nQuerySQL Merged #1 + #2 (using OR):\n" . $q3->get_select() . "\n\n";

   // merge Q1 and Q2 into Q1 using default AND'ing
   $q1->merge( $q2 );
   echo "-------------\nQuerySQL Merged #1 + #2:\n" . $q1->get_select() . "\n\n";

   // debug String-representation of Q1
   echo "-------------\nQuerySQL #1 to_string():\n" . $q1->to_string() . "\n\n";

   // construct
   $q4 = new QuerySQL(
      SQLP_FIELDS, 'G.*',
      SQLP_FROM,   'Games G', 'Players P',
      SQLP_UNION_WHERE,
            'G.White_ID=4711 AND P.ID=G.Black_ID',
            'G.Black_ID=4711 AND P.ID=G.White_ID',
      SQLP_WHERE,  'G.Status'.IS_RUNNING_GAME,
      SQLP_ORDER,  'Lastchanged DESC', 'ID'
   );

   // debug String-representation of Q4 using UNION
   echo "-------------\nQuerySQL #4 (using UNION):\n" . $q4->get_select() . "\n\n";

}
?>
