<?php

/*
Copyright © 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/

  interface ICache {

    public static function set( &$output, $expiration, $id, $directory );
    public static function get( $id, $directory );
    public static function clear( $id, $directory );
    public static function flush();

  }

?>
