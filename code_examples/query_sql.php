<?php

/*
 * Code example using QuerySQL-class.
 *
 * Usage: php code_examples/query_sql.php
 */

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
   $q2->add_part( SQLP_FROM, 'join Forums ON F.ID=G.ID' );
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

}
?>
