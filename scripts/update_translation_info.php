<?php
{
  $update_script = true;

  if( $argc >= 1 )
    include $argv[1];
  else
    $translation_info = array();

  $string = '';

  include "php://stdin";

  $stderr = fopen( "php://stderr", 'w' );

  $new_translation_info = array();
  foreach( $all_translations as $string => $sinfo )
    {
      $string = $sinfo['CString'];
      $place = $sinfo['File'];
      if( !empty($string) )
        {
          $group_array = array();
          if( array_key_exists( $string, $translation_info ) )
            {
              $group_array = $translation_info[$string]['Groups'];
            }

          if( !array_key_exists( $string, $new_translation_info ) )
            {
              $new_translation_info[$string] = array( 'Groups' => $group_array,
                                                      'Found in' => array() );
            }

          array_push( $new_translation_info[$string]['Found in'], $place );
      }
    }

  uksort( $new_translation_info, "strnatcasecmp" );

  $first = true;
  foreach( $new_translation_info as $string => $info )
    {
      $place_str = '';
      if( !empty( $info['Found in'] ) )
        {
          $sorted_array = array_unique( $info['Found in'] );
          sort( $sorted_array );
          foreach( $sorted_array as $cplace )
            $place_str .= $cplace . ', ';
          $place_str = substr( $place_str, 0, -2 );
        }

      $group_str = '';
      if( !empty( $info['Groups'] ) )
        {
          $sorted_array = array_unique( $info['Groups'] );
          sort( $sorted_array );
          foreach( $sorted_array as $cgroup )
            {
              $group_str .= " '$cgroup',";
              if( !in_array($cgroup, $translation_groups) )
                fwrite( $stderr, "Warning: No such group '$cgroup' for string '$string'.\n", 120 );
            }
          $group_str = substr( $group_str, 0, -1 ) . ' ';
        }
      else
        {
          fwrite( $stderr, "Warning: No group for string '$string'.\n", 120 );
        }

      $beginning = ",\n\n";
      if( $first )
        $beginning = "";

      $r_string = str_replace( "'", "\'", $string );

      echo $beginning;
      echo "      /* Found in these files: $place_str */\n";
      echo "      '$r_string' =>\n";
      echo "      array( 'Groups' => array($group_str) )";

      if( $first )
        $first = false;
    }

  echo "\n";
}

?>
