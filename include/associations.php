<?php

/*
Copyright © 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/


	class assoc {

    private static $called = array();

    public static function hook_account_initialized() {
      $associations_action = sys::input( "associations_action", false, SKIP_GET );
      $actions = array(
        "initiate_association",
        "complete_association",
        "delete_association"
      );
      if( in_array( $associations_action, $actions ) ) {
        $evaluate = "self::$associations_action();";
        eval( $evaluate );
      }
    }

    private static function initiate_association() {
      sys::check_return_page();
      $association_primary_type = sys::input( "association_primary_type", false );
      $association_secondary_type = sys::input( "association_secondary_type", false );
      $association_primary_target = sys::input( "association_primary_target", 0 );
      $association_secondary_target = sys::input( "association_secondary_target", 0 );
      if( !$association_primary_type || !$association_secondary_type ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/associations/actions/initiate_association/missing_type/title" ),
          lang::phrase( "error/associations/actions/initiate_association/missing_type/body" )
        );
      }
      if( !$association_primary_target && !$association_secondary_target ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/associations/actions/initiate_association/missing_target/title" ),
          lang::phrase( "error/associations/actions/initiate_association/missing_target/body" )
        );
      }
      db::open( TABLE_ASSOCIATIONS );
        db::where( "association_primary_type", $association_primary_type );
        db::where( "association_secondary_type", $association_secondary_type );
        if( $association_primary_target ) {
          db::where( "association_primary_target", $association_primary_target );
        }
        if( $association_secondary_target ) {
          db::where( "association_secondary_target", $association_secondary_target );
        }
        db::where( "association_pending", 1 );
      $association = db::result();
      db::clear_result();
      if( !$association ) {
        db::open( TABLE_ASSOCIATIONS );
          db::set( "association_primary_type", $association_primary_type );
          db::set( "association_secondary_type", $association_secondary_type );
          if( $association_primary_target ) {
            db::set( "association_primary_target", $association_primary_target );
          }
          if( $association_secondary_target ) {
            db::set( "association_secondary_target", $association_secondary_target );
          }
          db::set( "association_pending", 1 );
        if( !db::insert() ) {
          echo db::error();
          exit();
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/associations/actions/initiate_association/could_not_create_association/title" ),
            lang::phrase( "error/associations/actions/initiate_association/could_not_create_association/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      }
      action::resume( "associations/actions" );
        action::start( "action" );
          action::add( "name", "initiate_association" );
          action::add( "title", lang::phrase( "associations/actions/initiate_association/title" ) );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "associations/actions/initiate_association/success/body" ) );
        action::end();
      action::end();
      if( action::get( "request/return_page" ) ) {
        sys::message(
          USER_MESSAGE,
          lang::phrase( "associations/actions/initiate_association/success/title" ),
          lang::phrase( "associations/actions/initiate_association/success/body" )
        );
      }
    }

    private static function complete_association() {
      sys::check_return_page();
      $association_ids = sys::input( "association_id", array() );
      if( $association_ids && !is_array( $association_ids ) ) {
        $association_ids = array( $association_ids );
      }
      $association_primary_target = sys::input( "association_primary_target", 0 );
      $association_secondary_target = sys::input( "association_secondary_target", 0 );
      $association_type = sys::input( "association_type", "primary" );
      if( $association_type == "primary" && !$association_primary_target ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/associations/actions/complete_association/missing_primary_target/title" ),
          lang::phrase( "error/associations/actions/complete_association/missing_primary_target/body" )
        );
      } else if( $association_type == "secondary" && !$association_secondary_target ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/associations/actions/complete_association/missing_secondary_target/title" ),
          lang::phrase( "error/associations/actions/complete_association/missing_secondary_target/body" )
        );
      }
      foreach( $association_ids as $association_id ) {
        db::open( TABLE_ASSOCIATIONS );
          db::where( "association_id", $association_id );
          db::where( "association_pending", 1 );
        $association = db::result();
        db::clear_result();
        if( !$association ) {
          sys::message(
            USER_MESSAGE,
            lang::phrase( "error/associations/actions/complete_association/invalid_association/title" ),
            lang::phrase( "error/associations/actions/complete_association/invalid_association/body" )
          );
        }
        db::open( TABLE_ASSOCIATIONS );
          db::where( "association_id", $association_id );
          if( $association_type == "primary" ) {
            db::set( "association_primary_target", $association_primary_target );
          } else {
            db::set( "association_secondary_target", $association_secondary_target );
          }
          db::set( "association_pending", 0 );
        if( !db::update() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/associations/actions/complete_association/could_not_finalize_association/title" ),
            lang::phrase( "error/associations/actions/complete_association/could_not_finalize_association/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      }
      action::resume( "associations/actions" );
        action::start( "action" );
          action::add( "name", "complete_association" );
          action::add( "title", lang::phrase( "associations/actions/complete_association/title" ) );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "associations/actions/complete_association/success/body" ) );
        action::end();
      action::end();
      if( action::get( "request/return_page" ) ) {
        sys::message(
          USER_MESSAGE,
          lang::phrase( "associations/actions/complete_association/success/title" ),
          lang::phrase( "associations/actions/complete_association/success/body" )
        );
      }
    }

    private static function delete_association() {
      sys::check_return_page();
      $association_ids = sys::input( "association_id", array() );
      if( $association_ids && !is_array( $association_ids ) ) {
        $association_ids = array( $association_ids );
      }
      foreach( $association_ids as $association_id ) {
        db::open( TABLE_ASSOCIATIONS );
          db::where( "association_id", $association_id );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/associations/actions/delete_association/could_not_delete_association/title" ),
            lang::phrase( "error/associations/actions/delete_association/could_not_delete_association/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      }
      action::resume( "associations/actions" );
        action::start( "action" );
          action::add( "name", "delete_association" );
          action::add( "title", lang::phrase( "associations/actions/delete_association/title" ) );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "associations/actions/delete_association/success/body" ) );
        action::end();
      action::end();
      if( action::get( "request/return_page" ) ) {
        sys::message(
          USER_MESSAGE,
          lang::phrase( "associations/actions/delete_association/success/title" ),
          lang::phrase( "associations/actions/delete_association/success/body" )
        );
      }
    }

    public static function clear_associations( $type, $target ) {
      if( $type && $target ) {
        db::open( TABLE_ASSOCIATIONS );
          db::where( "association_primary_type", $type );
          db::where( "association_primary_target", $target );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/associations/clear_associations/could_not_clear_primary_associations/title" ),
            lang::phrase( "error/associations/clear_associations/could_not_clear_primary_associations/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        db::open( TABLE_ASSOCIATIONS );
          db::where( "association_secondary_type", $type );
          db::where( "association_secondary_target", $target );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/associations/clear_associations/could_not_clear_secondary_associations/title" ),
            lang::phrase( "error/associations/clear_associations/could_not_clear_secondary_associations/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      }
    }

    public static function append_associations( $primary, $secondary, $primary_path, $secondary_path, $primary_target = 0, $secondary_target = 0 ) {
      $using_primary = $primary_path ? true : false;
      $total_items = action::total( $using_primary ? $primary_path : $secondary_path );
      $primary_targets = array();
      $secondary_targets = array();
      if( $primary_path && $primary_target ) {
        $primary_targets[] = $primary_target;
      } else if( $secondary_path && $secondary_target ) {
        $secondary_targets[] = $secondary_target;
      } else {
        for( $i = 0; $i < $total_items; $i++ ) {
          if( $using_primary ) {
            $primary_targets[] = action::get( $primary_path . "/id", $i );
          } else {
            $secondary_targets[] = action::get( $secondary_path . "/id", $i );
          }
        }
      }
      action::resume( "associations" );
        foreach( ( $using_primary ? $primary_targets : $secondary_targets ) as $association_target ) {
          db::open( TABLE_ASSOCIATIONS );
            if( $using_primary ) {
              db::where( "association_primary_target", $association_target );
            } else {
              db::where( "association_secondary_target", $association_target );
            }
            if( $primary ) {
              db::where( "association_primary_type", $primary );
            }
            if( $secondary ) {
              db::where( "association_secondary_type", $secondary );
            }
          while( $row = db::result() ) {
            action::start( "association" );
              action::add( "id", $row['association_id'] );
              action::start( "primary" );
                action::add( "type", $row['association_primary_type'] );
                action::add( "id", $row['association_primary_target'] );
              action::end();
              action::start( "secondary" );
                action::add( "type", $row['association_secondary_type'] );
                action::add( "id", $row['association_secondary_target'] );
              action::end();
              $target_key = $using_primary ? "association_secondary_target" : "association_primary_target";
              $target_type = $using_primary ? $row['association_secondary_type'] : $row['association_primary_type'];
              if( isset( $row[$target_key] ) && !isset( self::$called[$row[$target_key].".".$target_type] ) ) {
                sys::query( "get_extension_object", $row[$target_key], $target_type );
                self::$called[$row[$target_key].".".$target_type] = true;
              }
            action::end();
          }
        }
      action::end();
    }

    public static function list_pending_associations() {
      $association_primary_type = sys::input( "association_primary_type", false );
      $association_secondary_type = sys::input( "association_secondary_type", false );
      $association_primary_target = sys::input( "association_primary_target", 0 );
      $association_secondary_target = sys::input( "association_secondary_target", 0 );
      if( !$association_primary_type && !$association_secondary_type ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/associations/list_pending_associations/missing_data/title" ),
          lang::phrase( "error/associations/list_pending_associations/missing_data/body" )
        );
      }
      $use_string = "";
      action::resume( "associations/pending_association_list" );
        action::start( "primary" );
          if( $association_primary_type ) {
            action::add( "type", $association_primary_type );
          }
          if( $association_primary_target ) {
            action::add( "target", $association_primary_target );
          }
        action::end();
        action::start( "secondary" );
          if( $association_secondary_type ) {
            action::add( "type", $association_secondary_type );
          }
          if( $association_secondary_target ) {
            action::add( "target", $association_secondary_target );
          }
        action::end();
        db::open( TABLE_ASSOCIATIONS );
          if( $association_primary_type ) {
            $use_string = "secondary";
            db::where( "association_primary_type", $association_primary_type );
          }
          if( $association_secondary_type ) {
            $use_string = "primary";
            db::where( "association_secondary_type", $association_secondary_type );
          }
          db::where( "association_pending", 1 );
          db::order( "association_id", "DESC" );
        while( $row = db::result() ) {
          action::start( "association" );
            action::add( "id", $row['association_id'] );
            action::start( "primary" );
              action::add( "type", $row['association_primary_type'] );
              action::add( "target", $row['association_primary_target'] );
            action::end();
            action::start( "secondary" );
              action::add( "type", $row['association_secondary_type'] );
              action::add( "target", $row['association_secondary_target'] );
            action::end();
            sys::query( "get_extension_object", $row['association_'.$use_string.'_target'], $row['association_'.$use_string.'_type'] );
          action::end();
        }
      action::end();      
    }

    public static function list_current_associations() {
      
    }
	}

?>