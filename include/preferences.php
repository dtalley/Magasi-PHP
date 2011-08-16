<?php

/*
Copyright © 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/


  class preferences {

    public static function hook_account_initialized() {
      if( !defined( "PREFS_ACCOUNT_INITIALIZED" ) && !(int)action::get( "request/feed" ) ) {
        define( "PREFS_ACCOUNT_INITIALIZED", true );
        $cache_id = "uid" . action::get( "user/id" );
        action::resume( "preferences" );
        action::end();
        if( !CACHE_ENABLED || !$preference_tables = cache::get( $cache_id, "preferences/get_preference_tables" ) ) {
          sys::hook( "get_preference_tables" );
          $preference_tables = action::xpath( "preferences/table_list" );
          if( CACHE_ENABLED && $preference_tables ) {
            cache::set( $preference_tables->ownerDocument->saveXML(), -1, $cache_id, "preferences/get_preference_tables" );
          }
        } else {
          action::merge( simplexml_load_string( $preference_tables ), "preferences" );
        }
        if( !CACHE_ENABLED || !$preference_list = cache::get( $cache_id, "preferences/get_preferences" ) ) {
          self::calculate_preferences();
          $preference_list = action::xpath( "preferences/preferences" );
          if( CACHE_ENABLED && $preference_list ) {
            cache::set( $preference_list->ownerDocument->saveXML($preference_list), -1, $cache_id, "preferences/get_preferences" );
          }
        } else {
          action::merge( simplexml_load_string( $preference_list ), "preferences" );
        }
        $preference_action = sys::input( "preference_action", false, SKIP_GET );
        $actions = array(
          "update_preferences"
        );
        if( in_array( $preference_action, $actions ) ) {
          $evaluate = "self::$preference_action();";
          eval( $evaluate );
        }
      }
    }

    private static function calculate_preferences() {
      $preference_groups = self::calculate_user_preferences( action::get( "user/id" ) );
      self::save_user_preferences( "preferences", $preference_groups );
    }

    private static function calculate_user_preferences( $user_id ) {
      db::open( TABLE_PREFERENCES );
        db::open( TABLE_PREFERENCE_GROUPS );
          db::link( "preference_group_id" );
        db::close();
        db::open( TABLE_USER_PREFERENCES, LEFT );
          db::link( "preference_id" );
          db::where( "user_id", $user_id );
        db::close();
      $preference_groups = array();
      while( $row = db::result() ) {
        if( !isset( $preference_groups[$row['preference_group_type']] ) ) {
          $preference_groups[$row['preference_group_type']] = array();
        }
        if( !isset( $preference_groups[$row['preference_group_type']][$row['preference_group_name']] ) ) {
          $preference_groups[$row['preference_group_type']][$row['preference_group_name']] = array();
        }
        if( isset( $row['user_preference_value'] ) ) {
          $preference_groups[$row['preference_group_type']][$row['preference_group_name']][$row['preference_name']] = $row['user_preference_value'];
        }
      }
      return $preference_groups;
    }

    private static function save_user_preferences( $path, $preference_groups ) {
      foreach( $preference_groups as $type => $groups ) {
        foreach( $groups as $group => $preferences ) {
          action::resume( "preferences/" . $path . "/" . $type . "/" . $group );
            foreach( $preferences as $preference => $value ) {
              action::add( $preference, $value );
            }
          action::end();
        }
      }
    }

    private static function update_preferences() {
      if( !action::get( "user/logged_in" ) ) {
        sys::message( USER_ERROR, lang::phrase( "error/account/must_be_logged_in/title" ), lang::phrase( "error/account/must_be_logged_in/body" ) );
      }
      $total_groups = sys::input( "total_groups", 0 );
      $user_id = action::get( "user/id" );
      for( $i = 0; $i < $total_groups; $i++ ) {
        $preference_group_id = sys::input( "group_" . ($i+1) . "_id", 0 );
        db::open( TABLE_PREFERENCE_GROUPS );
          db::where( "preference_group_id", $preference_group_id );
          db::open( TABLE_PREFERENCES );
            db::link( "preference_group_id" );
        while( $row = db::result() ) {
          $total_tables = action::total( "preferences/table_list/table" );
          for( $j = 0; $j < $total_tables; $j++ ) {
            if( action::get( "preferences/table_list/table/name", $j ) == $row['preference_group_type'] ) {
              $preference_table = action::get( "preferences/table_list/table/preference_table", $j );
              $preference_target = action::get( "preferences/table_list/table/target", $j );
              $preference_id = action::get( "preferences/table_list/table/id_column", $j );
              $preference_value = action::get( "preferences/table_list/table/value_column", $j );
              //echo $preference_table . " / " . $preference_target . " / " . $preference_id . " / " . $preference_value . "group_" . ($i+1) . "_preference_" . $row['preference_id'] . "<br />";
              db::open( $preference_table );
                db::where( $preference_id, $preference_target );
                db::where( "preference_id", $row['preference_id'] );
              $preference = db::result();
              db::clear_result();
              db::open( $preference_table );
                db::where( $preference_id, $preference_target );
                db::set( $preference_value, sys::input( "group_" . ($i+1) . "_preference_" . $row['preference_id'], "" ) );
              if( $preference ) {
                db::where( "user_id", $user_id );
                db::where( "preference_id", $row['preference_id'] );
                if( !db::update() ) {
                  sys::message(
                    SYSTEM_ERROR,
                    lang::phrase( "error/preferences/update_preference/title" ),
                    lang::phrase( "error/preferences/update_preference/body" ),
                    __FILE__, __LINE__, __FUNCTION__, __CLASS__
                  );
                }
              } else {
                db::set( "preference_group_id", $preference_group_id );
                db::set( "preference_id", $row['preference_id'] );
                db::set( "user_id", $user_id );
                if( !db::insert() ) {
                  sys::message(
                    SYSTEM_ERROR,
                    lang::phrase( "error/preferences/create_preference/title" ),
                    lang::phrase( "error/preferences/create_preference/body" ),
                    __FILE__, __LINE__, __FUNCTION__, __CLASS__
                  );
                }
              }
            }
          }
        }
      }

      //cache::clear( "uid" . $user_id, "preferences/get_preferences" );
      cache::flush();

      action::resume( "preferences/preference_action" );
        action::add( "action", "update_preferences" );
        action::add( "success", 1 );
        action::add( "message", lang::phrase( "preferences/update_preferences/success" ) );
      action::end();
    }

    public static function list_groups() {
      $preference_group_type = sys::input( "preference_group_type", "" );
      db::open( TABLE_PREFERENCE_GROUPS );
        db::where( "preference_group_type", $preference_group_type );
        db::order( "preference_group_name", "ASC" );
      action::resume( "preferences" );
        action::start( "group_list" );
          while( $row = db::result() ) {
            action::start( "group" );
              action::add( "id", $row['preference_group_id'] );
              action::add( "name", $row['preference_group_name'] );
              action::add( "title", lang::phrase( "preferences/" . $preference_group_type . "/" . $row['preference_group_name'] . "/title" ) );
            action::end();
          }
        action::end();
      action::end();
    }

    public static function list_preferences() {
      $preference_group_type = sys::input( "preference_group_type", "" );
      $preference_group_name = sys::input( "preference_group_name", "" );
      $group = null;
      if( $preference_group_type && $preference_group_name ) {
        db::open( TABLE_PREFERENCE_GROUPS );
          db::where( "preference_group_name", $preference_group_name );
          db::where( "preference_group_type", $preference_group_type );
        $group = db::result();
        action::resume( "preferences" );
          action::start( "preference_group" );
            action::add( "id", $group['preference_group_id'] );
            action::add( "title", lang::phrase( "preferences/" . $preference_group_type . "/" . $preference_group_name . "/title" ) );
            action::add( "name", $group['preference_group_name'] );
          action::end();
        action::end();
      }
      $preference_table = "";
      $preference_target = "";
      $preference_id = "";
      $preference_value = "";
      $target_requested = false;
      $total_tables = action::total( "preferences/table_list/table" );
      for( $i = 0; $i < $total_tables; $i++ ) {
        if( action::get( "preferences/table_list/table/name", $i ) == $preference_group_type ) {
          $preference_table = action::get( "preferences/table_list/table/preference_table", $i );
          $preference_target = action::get( "preferences/table_list/table/target", $i );
          $preference_id = action::get( "preferences/table_list/table/id_column", $i );
          $preference_value = action::get( "preferences/table_list/table/value_column", $i );
          $target_requested = true;
        }
      }
      db::open( TABLE_PREFERENCES );
        db::order( "preference_id", "ASC" );
        if( $group ) {
          db::where( "preference_group_id", $group['preference_group_id'] );
        }
        if( $target_requested ) {
          db::open( $preference_table, LEFT );
            db::select( $preference_value );
            db::link( "preference_id" );
            db::where( $preference_id, $preference_target );
          db::close();
        }
        db::open( TABLE_PREFERENCE_GROUPS );
          db::link( "preference_group_id" );
          db::where( "preference_group_type", $preference_group_type );
          if( $preference_group_name ) {
            db::where( "preference_group_name", $preference_group_name );
          }
        db::close();
      $preferences = array();
      $total_preferences = 0;
      while( $row = db::result() ) {
        $preferences[] = $row;
        $total_preferences++;
      }
      db::clear_result();
      action::resume( "preferences" ); {
        action::start( "preference_list" ); {
          for( $i = 0; $i < $total_preferences; $i++ ) {
            action::start( "preference" );
              action::add( "id", $preferences[$i]['preference_id'] );
              action::add( "title", lang::phrase( "preferences/" . $preference_group_type . "/" . $preferences[$i]['preference_group_name'] . "/" . $preferences[$i]['preference_name'] . "/title" ) );
              action::add( "description", lang::phrase( "preferences/" . $preference_group_type . "/" . $preferences[$i]['preference_group_name'] . "/" . $preferences[$i]['preference_name'] . "/description" ) );
              action::add( "name", $preferences[$i]['preference_name'] );
              action::add( "type", $preferences[$i]['preference_type'] );
              action::add( "select", $preferences[$i]['preference_select'] );
              action::start( "group" );
                action::add( "id", $preferences[$i]['preference_group_id'] );
              action::end();
              if( $target_requested ) {
                action::add( "value", $preferences[$i][$preference_value.''] );
              }
              if( $preferences[$i]['preference_select'] ) {
                action::start( "option_list" );
                  action::start( "option" );
                    action::add( "id", 0 );
                    action::add( "value", "" );
                    action::add( "title", "Default" );
                  action::end();
                  db::open( TABLE_PREFERENCE_VALUES );
                    db::where( "preference_id", $preferences[$i]['preference_id'] );
                    db::order( "preference_value_id", "ASC" );
                  while( $row = db::result() ) {
                    action::start( "option" );
                      action::add( "id", $row['preference_value_id'] );
                      action::add( "value", $row['preference_value'] );
                      if( $preferences[$i]['preference_type'] == "int" ) {
                        action::add( "title", $row['preference_value'] );
                      } else {
                        action::add( "title", lang::phrase( "preferences/" . $preference_group_type . "/" . $preference_group_name . "/" . $preferences[$i]['preference_name'] . "/options/" . $row['preference_value'] ) );
                      }
                    action::end();
                  }
                  db::clear_result();
                action::end();
              }
            action::end();
          }
        } action::end();
      } action::end();
    }

    public static function get( $group_name, $preference_name, $group_type, $user_id = 0 ) {
      if( !$user_id ) {
        $user_id = action::get( "user/id" );
      }
      if( $user_id == (int)action::get( "user/id" ) ) {
        $preference = action::get( "preferences/preferences/" . $group_type . "/" . $group_name . "/" . $preference_name );
        if( is_bool( $preference ) && $preference == false ) {
          return NULL;
        }
        return $preference;
      } else {
        if( !action::get( "preferences/user_" . $user_id . "/preferences" ) ) {
          if( !CACHE_ENABLED || !$preference_list = cache::get( "uid" . $user_id, "preferences/get_preferences" ) ) {
            $preference_groups = self::calculate_user_preferences( $user_id );
            self::save_user_preferences( "user_" . $user_id . "/preferences", $preference_groups );
            $preference_list = action::xpath( "preferences/user_" . $user_id . "/preferences" );
            if( CACHE_ENABLED && $preference_list ) {
              cache::set( $preference_list->ownerDocument->saveXML($preference_list), -1, "uid" . $user_id, "preferences/get_preferences" );
            }
          } else {
            action::merge( simplexml_load_string( $preference_list ), "preferences/user_" . $user_id );
          }
        }
        return action::get( "preferences/user_" . $user_id . "/preferences/" . $group_type . "/" . $group_name . "/" . $preference_name );
      }
    }

  }

?>
