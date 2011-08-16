<?php

/*
Copyright © 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/

  class roles {

    public static function query_get_user_information( $user_id ) {
      if( $user_id == action::get( "user/id" ) ) {
        $total_titles = action::total( "user/titles/title" );
        for( $i = 0; $i < $total_titles; $i++ ) {
          action::add( "is_" . action::get( "user/titles/title/name", $i ), 1 );
        }
        if( !( (int) action::get( "user/logged_in" ) ) ) {
          action::add( "is_" . action::get( "settings/roles/unregistered_role" ), 1 );
        } else {
          action::add( "is_" . action::get( "settings/roles/registered_role" ), 1 );
        }
      } else {
        db::open( TABLE_USER_ROLE_ASSIGNMENTS );
          db::where( "user_id", $user_id );
          db::open( TABLE_USER_ROLES );
            db::link( "user_role_id" );
        while( $row = db::result() ) {
          action::add( "is_" . $row['user_role_name'], 1 );
        }
      }
    }

    public static function query_get_multi_user_information( $user_ids, $path ) {
      db::open( TABLE_USER_ROLE_ASSIGNMENTS );
        db::where_in( "user_id", $user_ids );
        db::open( TABLE_USER_ROLES );
          db::link( "user_role_id" );
      while( $row = db::result() ) {
        $user_path = str_replace( "%1", $row['user_id'], $path );
        action::resume( $user_path );
          action::add( "is_" . $row['user_role_name'], 1 );
        action::end();
      }
    }

    public static function hook_account_initialized() {
      $user_id = action::get( "user/user_id" ) ? action::get( "user/user_id" ) : ANONYMOUS;
      db::open( TABLE_USER_ROLE_ASSIGNMENTS );
        db::where( "user_id", $user_id );
        db::open( TABLE_USER_ROLES );
          db::link( "user_role_id" );
          db::open( TABLE_USER_TITLES );
            db::link( "user_title_id" );
      action::resume( "user/titles" );
        while( $row = db::result() ) {
          action::start( "title" );
            action::add( "id", $row['user_title_id'] );
            action::add( "name", $row['user_role_name'] );
            action::add( "title", $row['user_title'] );
          action::end();
        }
        $title = NULL;
        db::open( TABLE_USER_ROLES );
          if( !( (int) action::get( "user/logged_in" ) ) ) {
            db::where( "user_role_name", action::get( "settings/roles/unregistered_role" ) );
          } else {
            db::where( "user_role_name", action::get( "settings/roles/registered_role" ) );
          }
          db::open( TABLE_USER_TITLES );
            db::link( "user_title_id" );
        $title = db::result();
        db::clear_result();
        if( $title ) {
          action::start( "title" );
            action::add( "id", $title['user_title_id'] );
            action::add( "name", $title['user_title_name'] );
            action::add( "title", $title['user_title'] );
          action::end();
        }
      action::end();
    }

    public static function hook_get_permission_tiers() {
      $user_id = action::get( "user/id" ) ? action::get( "user/id" ) : ANONYMOUS;
      db::open( TABLE_PERMISSION_TIERS );
        db::where( "permission_tier_name", "roles" );
        db::group( "permission_tier_name" );
        db::open( TABLE_USER_ROLE_ASSIGNMENTS, LEFT );
          db::where( "user_id", $user_id );
          db::open( TABLE_USER_ROLE_PERMISSIONS );
            db::link( "user_role_id" );
          db::close();
        db::close();
        db::open( TABLE_USER_ROLES, LEFT );
          db::select_as( "default_role" );
          db::select( "user_role_id" );
          if( (int)action::get( "user/logged_in" ) ) {
            db::where( "user_role_name", action::get( "settings/roles/registered_role" ) );
          } else {
            db::where( "user_role_name", action::get( "settings/roles/unregistered_role" ) );
          }
          db::open( TABLE_USER_ROLE_PERMISSIONS, LEFT );
            db::select_as( "default_group" );
            db::select( "permission_group_id" );
            db::link( "user_role_id" );
          db::close();
        db::close();
      $targets = array();
      $total_targets = 0;
      while( $row = db::result() ) {
        $targets[] = $row;
        $total_targets++;
      }
      if( $total_targets ) {
        action::resume( "authentication/tier_list" );
          action::start( "tier" );
            action::add( "title", lang::phrase( "roles/title" ) );
            action::add( "name", "roles" );
            action::add( "permission_table", TABLE_USER_ROLE_PERMISSIONS );
            action::add( "item_table", TABLE_USER_ROLES );
            action::add( "id_column", "user_role_id" );
            action::add( "name_column", "user_role_title" );
            action::add( "extension", "roles" );
            action::add( "level", $targets[0]['permission_tier_level'] );
            action::start( "target_list" );
              for( $i = 0; $i < $total_targets; $i++ ) {
                if( $targets[$i]['user_role_id'] ) {
                  action::add( "target", $targets[$i]['user_role_id'] );
                }
              }
              if( $targets[0]['default_group'] ) {
                action::add( "target", $targets[0]['default_role'] );
              }
            action::end();
          action::end();
        action::end();
      }
		}

    public static function query_get_permission_tiers() {
      action::resume( "authentication/master_tier_list" );
        action::start( "tier" );
          action::add( "title", lang::phrase( "roles/title" ) );
          action::add( "name", "roles" );
          action::add( "permission_table", TABLE_USER_ROLE_PERMISSIONS );
          action::add( "item_table", TABLE_USER_ROLES );
          action::add( "id_column", "user_role_id" );
          action::add( "name_column", "user_role_title" );
          action::add( "extension", "roles" );
        action::end();
      action::end();
		}

    public static function list_roles() {
      
    }
    
  }

?>
