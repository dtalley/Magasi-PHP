<?php

/*
Copyright © 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/

  require_once( CACHE_DIR . "/cache.php" );

  class cache implements ICache {

    public static function set( &$output, $expiration = -1, $id = "", $directory = "" ) {
      return false;
    }

    public static function get( $id = "", $directory = "" ) {
      return false;
    }

    public static function clear( $id = "", $directory = "" ) {
      return false;
    }

  }

?>
