<?php

/*
Copyright © 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/

  class logs {

    public static function hook_account_initialized() {
      $logs_action = sys::input( "logs_action", false, SKIP_GET );
      $actions = array(        
      );
      if( in_array( $logs_action, $actions ) ) {
        $evaluate = "self::$logs_action();";
        eval( $evaluate );
      }
    }

    public static function record_log( $type, $action, $target, $description, $message ) {
      $current_date = gmdate( "Y/m/d H:i:s", time() );
      db::open( TABLE_LOGS );
        db::set( "log_action", $action );
        db::set( "log_type", $type );
        db::set( "log_target", $target );
        db::set( "log_message", $message );
        db::set( "log_date", $current_date );
        db::set( "log_description", $description );
      if( !db::insert() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/logs/record_log/title" ),
          lang::phrase( "error/logs/record_log/body" ),
          __FILE__, __LINE__, __FUNCTION__, __CLASS__
        );
      }
      sys::query( "log_recorded", $action, $type, $target );
    }

    public static function list_logs() {
      if( !auth::test( "logs", "view_logs" ) ) {
        auth::deny( "logs", "view_logs" );
      }
      $page = sys::input( "page", 1 );
      $per_page = sys::input( "per_page", 20 );
      $log_action = sys::input( "log_action", "" );

      db::open( TABLE_LOGS );
        db::select_as( "total_logs" );
        db::select_count( "log_id" );
        if( $log_action ) {
          db::where( "log_action", $log_action );
        }
      $count = db::result();
      db::clear_result();
      $total_logs = $count['total_logs'];

      action::resume( "logs" );
        action::add( "logs_per_page", $per_page );
        action::add( "total_logs", $total_logs );
        action::add( "total_pages", ceil( $total_logs / $per_page ) );
        action::add( "page", $page );
        action::start( "log_list" );
          db::open( TABLE_LOGS );
            if( $log_action ) {
              db::where( "log_action", $log_action );
            }
            db::order( "log_date", "DESC" );
            db::limit( $per_page * ( $page - 1 ), $per_page );
          while( $row = db::result() ) {
            action::start( "log" );
              action::add( "id", $row['log_id'] );
              action::add( "type", $row['log_type'] );
              action::add( "action", $row['log_action'] );
              action::add( "description", $row['log_description'] );
              action::add( "message", $row['log_message'] );
              $timestamp = strtotime( $row['log_date'] );
              action::add( "period", sys::create_duration( $timestamp, time() ) );
              $timestamp += ( 60 * 60 ) * sys::timezone();
              action::add( "datetime", sys::create_datetime( $timestamp ) );
              sys::query( "get_extension_object", $row['log_target'], $row['log_type'] );
            action::end();
          }
         action::end();
      action::end();
    }

  }

?>
