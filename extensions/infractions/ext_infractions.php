<?php

/*
Copyright © 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/

  class infractions {

    public static function hook_account_initialized() {
      $infractions_action = sys::input( "infractions_action", false, SKIP_GET );
      $actions = array(
        "apply_user_infraction",
        "delete_user_infractions",
        "toggle_user_infractions"
      );
      if( in_array( $infractions_action, $actions ) ) {
        $evaluate = "self::$infractions_action();";
        eval( $evaluate );
      }
    }

    public static function query_get_item_info( $type, $id ) {
      db::open( TABLE_USER_INFRACTIONS );
        db::where( "user_infraction_type", $type );
        db::where( "user_infraction_target", $id );
        db::open( TABLE_INFRACTIONS );
          db::link( "infraction_id" );
      $infraction = db::result();
      db::clear_result();
      if( $infraction ) {
        action::add( "infracted", 1 );
        action::start( "infraction" );
          action::add( "title", $infraction['infraction_title'] );
          action::add( "severity", $infraction['user_infraction_severity'] );
          action::add( "reason", $infraction['user_infraction_reason'] );
        action::end();
      }
    }

    private static function apply_user_infraction() {
      $user_infraction_judge = action::get( "user/user_id" ) ? action::get( "user/user_id" ) : ANONYMOUS;
      $user_id = sys::input( "user_id", 0 );
      $infraction_id = sys::input( "infraction_id", 0 );
      $user_infraction_severity = (int) sys::input( "user_infraction_severity", -1 );
      $user_infraction_expires = sys::input( "user_infraction_expires", 0 );
      $user_infraction_reason = sys::input( "user_infraction_reason", "" );
      $user_infraction_target = sys::input( "user_infraction_target", "" );
      $user_infraction_type = sys::input( "user_infraction_type", "" );
      $user_infraction_date = gmdate( "Y/m/d H:i:s", time() );
      $user_infraction_expiration = sys::input( "user_infraction_expiration", "" );
      $return_page = sys::input( "return_page", "" );
      if( $return_page ) {
        action::resume( "request" );
          action::add( "return_page", $return_page );
          action::add( "return_text", lang::phrase( "infractions/apply_user_infraction/return" ) );
        action::end();
      }

      if( !auth::test( "infractions", "apply_infractions" ) ) {
        auth::deny( "infractions", "apply_infractions" );
      }

      db::open( TABLE_USERS );
        db::where( "user_id", $user_id );
      $user = db::result();
      db::clear_result();

      /**
       * Determine if an infraction was already given for the
       * requested target and type.
       */
      db::open( TABLE_USER_INFRACTIONS );
        db::where( "user_id", $user_id );
        db::where( "user_infraction_target", $user_infraction_target );
      $past_infraction = db::result();
      db::clear_result();
      $apply_infraction = true;
      if( $past_infraction ) {
        action::resume( "infractions/infractions_action" );
          action::add( "action", "apply_user_infraction" );
          action::add( "success", 0 );
          action::add( "message", lang::phrase( "error/infractions/apply_user_infraction/already_infracted" ) );
        action::end();
        //Don't infract if an infraction already exists.
        $apply_infraction = false;
      }

      if( $apply_infraction ) {

        if( $infraction_id ) {

          db::open( TABLE_INFRACTIONS );
            db::select( "infraction_severity", "infraction_title", "infraction_name" );
            db::where( "infraction_id", $infraction_id );
          $infraction = db::result();
          db::clear_result();
          if( $user_infraction_severity < 0 ) {
            $user_infraction_severity = $infraction['infraction_severity'];
          }
          /**
           * Insert a new infraction into the DB with all
           * available information.
           */
          db::open( TABLE_USER_INFRACTIONS );
            db::set( "user_id", $user_id );
            db::set( "infraction_id", $infraction_id );
            db::set( "user_infraction_judge", $user_infraction_judge );
            db::set( "user_infraction_reason", $user_infraction_reason );
            db::set( "user_infraction_target", $user_infraction_target );
            db::set( "user_infraction_type", $user_infraction_type );
            db::set( "user_infraction_expires", $user_infraction_expires );
            $expiration = ( 60 * 60 * 24 * 7 * 4 );
            db::set( "user_infraction_severity", $user_infraction_severity );
            $expiration *= $user_infraction_severity;
            if( !$user_infraction_expiration ) {
              $user_infraction_expiration = gmdate( "Y/m/d H:i:s", time() + $expiration );
            }
            db::set( "user_infraction_expiration", $user_infraction_expiration );
            db::set( "user_infraction_date", $user_infraction_date );
          if( !db::insert() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/infractions/apply_user_infraction/title" ),
              lang::phrase( "error/infractions/apply_user_infraction/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }

          /**
           * Retrieve the user's new infraction point total to
           * determine applicable punishments.
           */
          db::open( TABLE_USER_INFRACTIONS );
            db::select_as( "infraction_total" );
            db::select_sum( "user_infraction_severity" );
            db::where( "user_id", $user_id );
            db::where( "user_infraction_reversed", 0 );
            db::start_block();
              db::where( "user_infraction_expiration", $user_infraction_date, ">" );
              db::where_or();
              db::where( "user_infraction_expires", 0 );
            db::end_block();
          echo "<!-- " . db::select_sql() . " -->\n";
          $total = db::result();
          db::clear_result();
          $infraction_total = $total['infraction_total'];

          /**
           * Retrieve the top punishment with a threshold below the user's
           * new infraction point total.
           */
          db::open( TABLE_PUNISHMENTS );
            db::where( "punishment_automatic", 1 );
            db::where( "punishment_threshold", $infraction_total, "<=", false );
            db::open( TABLE_USER_PUNISHMENTS, LEFT );
              db::select( "user_punishment_id" );
              db::link( "punishment_id" );
              db::where( "user_id", $user_id );
            db::close();
            db::order( "punishment_threshold", "DESC" );
            db::limit( 0, 1 );
          echo "<!-- " . db::select_sql() . " -->\n";
          $punishment = db::result();
          db::clear_result();
          if( $punishment ) {
            echo "<!-- " . $punishment['punishment_threshold'] . " // " . $infraction_total . " -->\n";
          }

          /**
           * Record this action with the logs extension.
           */
          logs::record_log(
            $user_infraction_type,
            "moderator",
            $user_infraction_target,
            "infract",
            lang::phrase(
              "infractions/" . $user_infraction_type . "/moderator_action",
              action::get( "user/name" ),
              $user['user_name'],
              $infraction['infraction_title'],
              $user_infraction_severity,
              $user_infraction_reason
            )
          );
        }

        /**
         * Retrieve any requested manual punishment if an automatic
         * punishment is not necessary.
         */
        $punishment_id = sys::input( "punishment_id", 0 );
        if( !$punishment && $punishment_id ) {
          db::open( TABLE_PUNISHMENTS );
            db::where( "punishment_id", $punishment_id );
            db::open( TABLE_USER_PUNISHMENTS, LEFT );
              db::select( "user_punishment_id" );
              db::link( "punishment_id" );
              db::where( "user_id", $user_id );
            db::close();
          $punishment = db::result();
          db::clear_result();
        }

        if( $punishment ) {
          $user_punishment_expiration = gmdate( "Y/m/d H:i:s", time() + $punishment['punishment_lifespan'] );
          db::open( TABLE_USER_PUNISHMENTS );
            if( $punishment['punishment_lifespan'] > 0 ) {
              db::set( "user_punishment_expiration", $user_punishment_expiration );
              db::set( "user_punishment_expires", 1 );
            } else {
              db::set( "user_punishment_expires", 0 );
            }
            
            /**
             * If this punishment is not already applied to the user,
             * add it into the DB, otherwise extend the existing
             * punishment's expiration date.
             */
            if( !$punishment['user_punishment_id'] ) {
              db::set( "punishment_id", $punishment['punishment_id'] );
              if( $punishment_id ) {
                db::set( "user_punishment_reason", $user_infraction_reason );
                db::set( "user_punishment_judge", action::get( "user/id" ) );
              } else {
                db::set( "user_punishment_automatic", 1 );
                db::set( "user_punishment_reason", lang::phrase( "infractions/punishment/automatic" ) );
              }
              db::set( "user_punishment_date", $user_infraction_date );
              db::set( "user_id", $user_id );
              if( !db::insert() ) {
                sys::message(
                  SYSTEM_ERROR,
                  lang::phrase( "error/infractions/apply_automatic_punishment/title" ),
                  lang::phrase( "error/infractions/apply_automatic_punishment/body" ),
                  __FILE__, __LINE__, __FUNCTION__, __CLASS__
                );
              }
            } else {
              db::where( "user_punishment_id", $punishment['user_punishment_id'] );
              if( !db::update() ) {
                sys::message(
                  SYSTEM_ERROR,
                  lang::phrase( "error/infractions/update_automatic_punishment/title" ),
                  lang::phrase( "error/infractions/update_automatic_punishment/body" ),
                  __FILE__, __LINE__, __FUNCTION__, __CLASS__
                );
              }
            }

          /**
           * Record this punishment with the logs extension
           */
          logs::record_log(
            "punishment",
            "moderator",
            $punishment['user_punishment_id'] ? $punishment['user_punishment_id'] : db::id(),
            "punish",
            lang::phrase(
              "infractions/punishment/moderator_action",
              action::get( "user/name" ),
              $user['user_name'],
              $punishment['punishment_title'],
              $user_infraction_reason
            )
          );
        }

        if( !$infraction_id && !$punishment_id ) {
          action::resume( "infractions/infractions_action" );
            action::add( "action", "apply_user_infraction" );
            action::add( "success", 0 );
            action::add( "message", lang::phrase( "error/infractions/missing_infraction_id/body" ) );
          action::end();
        } else {
          action::resume( "infractions/infractions_action" );
            action::add( "action", "apply_user_infraction" );
            action::add( "success", 1 );
            action::add( "message", lang::phrase( "infractions/apply_user_infraction/success/body" ) );
            action::start( "email" );
              action::start( "user" );
                action::add( "name", $user['user_name'] );
              action::end();
              if( $infraction ) {
                action::add( "infracted", 1 );
                action::start( "infraction" );
                  action::add( "title", $infraction['infraction_title'] );
                  action::add( "message", lang::phrase( "infractions/email/infractions/" . $infraction['infraction_name'] . "/body" ) );
                  action::add( "severity", $user_infraction_severity );
                action::end();
              }
              if( $punishment ) {
                action::add( "punished", 1 );
                action::start( "punishment" );
                  action::add( "title", $punishment['punishment_title'] );
                  action::add( "message", lang::phrase( "infractions/email/punishments/" . $punishment['punishment_name'] . "/body" ) );
                  action::add( "automatic", $punishment_id ? 0 : 1 );
                action::end();
              }
            action::end();
          action::end();
          if( $infraction || $punishment ) {
            email::send( "user_infracted", "html", $user['user_email'], sys::setting( "global", "admin_email" ), DEFAULT_LANG, lang::phrase( "infractions/apply_user_infraction/email/subject" ) );
          }
        }
      }

      if( $return_page ) {
        if( $apply_infraction ) {
          sys::message( USER_MESSAGE, lang::phrase( "infractions/apply_user_infraction/success/title" ), action::get( "infractions/infractions_action/message" ) );
        } else {
          sys::message( USER_MESSAGE, lang::phrase( "infractions/apply_user_infraction/failed/title" ), action::get( "infractions/infractions_action/message" ) );
        }
      }
    }

    private static function delete_user_infractions() {
      if( !auth::test( "infractions", "delete_infractions" ) ) {
        auth::deny( "infractions", "delete_infractions" );
      }
      $return_page = sys::input( "return_page", "" );
      if( $return_page ) {
        action::resume( "request" );
          action::add( "return_page", $return_page );
          action::add( "return_text", lang::phrase( "infractions/return_to_previous/body" ) );
        action::end();
      }
      $user_infraction_ids = sys::input( "user_infraction_id", array() );
      $user_infraction_delete_reason = sys::input( "user_infraction_delete_reason", "" );
      if( !is_array( $user_infraction_ids ) ) {
        $user_infraction_ids = array( $user_infraction_ids );
      }
      $total_infractions = count( $user_infraction_ids );
      for( $i = 0; $i < $total_infractions; $i++ ) {
        if( $user_infraction_ids[$i] > 0 ) {
          db::open( TABLE_USER_INFRACTIONS );
            db::where( "user_infraction_id", $user_infraction_ids[$i] );
            db::open( TABLE_USERS );
              db::link( "user_id" );
            db::close();
          $infraction = db::result();
          db::clear_result();
          if( !$infraction ) {
            sys::message( USER_MESSAGE, lang::phrase( "errors/infractions/invalid_user_infraction_id/title" ), lang::phrase( "errors/infractions/invalid_user_infraction_id/body" ) );
          }
          db::open( TABLE_USER_INFRACTIONS );
            db::where( "user_infraction_id", $user_infraction_ids[$i] );
          if( !db::delete() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/infractions/delete_user_infractions/title" ),
              lang::phrase( "error/infractions/delete_user_infractions/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
          logs::record_log(
            "infraction",
            "moderator",
            $infraction['user_infraction_id'],
            "delete",
            lang::phrase(
              "infractions/delete_user_infractions/moderator_action",
              action::get( "user/name" ),
              $infraction['user_name'],
              $user_infraction_delete_reason
            )
          );
        } else {
          sys::message( USER_MESSAGE, lang::phrase( "error/infractions/missing_user_infraction_id/title" ), lang::phrase( "error/infractions/missing_user_infraction_id/body" ) );
        }
      }
      action::resume( "infractions/infractions_action" );
        action::add( "action", "delete_user_infractions" );
        action::add( "success", 1 );
        action::add( "message", "infractions/delete_user_infractions/success/body" );
      action::end();
      if( $return_page ) {
        sys::message( USER_MESSAGE, lang::phrase( "infractions/delete_user_infractions/success/title" ), lang::phrase( "infractions/delete_user_infractions/success/body" ) );
      }
    }

    private static function toggle_user_infractions() {
      if( !auth::test( "infractions", "toggle_infractions" ) ) {
        auth::deny( "infractions", "toggle_infractions" );
      }
      $return_page = sys::input( "return_page", "" );
      if( $return_page ) {
        action::resume( "request" );
          action::add( "return_page", $return_page );
          action::add( "return_text", lang::phrase( "infractions/return_to_previous/body" ) );
        action::end();
      }
      $user_infraction_ids = sys::input( "user_infraction_id", array() );
      $user_infraction_toggle_reason = sys::input( "user_infraction_toggle_reason", "" );
      if( !is_array( $user_infraction_ids ) ) {
        $user_infraction_ids = array( $user_infraction_ids );
      }
      $total_infractions = count( $user_infraction_ids );
      for( $i = 0; $i < $total_infractions; $i++ ) {
        if( $user_infraction_ids[$i] > 0 ) {
          db::open( TABLE_USER_INFRACTIONS );
            db::where( "user_infraction_id", $user_infraction_ids[$i] );
            db::open( TABLE_USERS );
              db::link( "user_id" );
            db::close();
          $infraction = db::result();
          db::clear_result();
          if( !$infraction ) {
            sys::message( USER_MESSAGE, lang::phrase( "error/infractions/invalid_user_infraction_id/title" ), lang::phrase( "error/infractions/invalid_user_infraction_id/body" ) );
          }
          db::open( TABLE_USER_INFRACTIONS );
            db::where( "user_infraction_id", $user_infraction_ids[$i] );
            db::set( "user_infraction_reversed", $infraction['user_infraction_reversed'] ? 0 : 1 );
          if( !db::update() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/infractions/toggle_user_infractions/title" ),
              lang::phrase( "error/infractions/toggle_user_infractions/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
          $log_message = "infractions/reverse_user_infractions/moderator_action";
          $log_action = "disable";
          if( $infraction['user_infraction_reversed'] ) {
            $log_message = "infractions/reinstate_user_infractions/moderator_action";
            $log_action = "enable";
          }
          logs::record_log(
            "infraction",
            "moderator",
            $infraction['user_infraction_id'],
            $log_action,
            lang::phrase(
              $log_message,
              action::get( "user/name" ),
              $infraction['user_name'],
              $user_infraction_toggle_reason
            )
          );
        } else {
          sys::message( USER_MESSAGE, lang::phrase( "error/infractions/missing_user_infraction_id/title" ), lang::phrase( "error/infractions/missing_user_infraction_id/body" ) );
        }
      }
      action::resume( "infractions/infractions_action" );
        action::add( "action", "toggle_user_infractions" );
        action::add( "success", 1 );
        action::add( "message", "infractions/toggle_user_infractions/success/body" );
      action::end();
      if( $return_page ) {
        sys::message( USER_MESSAGE, lang::phrase( "infractions/toggle_user_infractions/success/title" ), lang::phrase( "infractions/toggle_user_infractions/success/body" ) );
      }
    }

    public static function hook_get_permission_tiers() {
      $user_id = action::get( "user/user_id" ) ? action::get( "user/user_id" ) : ANONYMOUS;
      db::open( TABLE_PERMISSION_TIERS );
        db::where( "permission_tier_name", "infractions" );
        db::open( TABLE_USER_PUNISHMENTS );
          db::where( "user_id", $user_id );
          $current_date = gmdate( "Y/m/d H:i:s", time() );
          db::start_block();
            db::where( "user_punishment_expiration", $current_date, ">" );
            db::where_or();
            db::where( "user_punishment_expires", 0 );
          db::end_block();
      $targets = array();
      $total_targets = 0;
      while( $row = db::result() ) {
        $targets[] = $row;
        $total_targets++;
      }
      if( $total_targets ) {
        action::resume( "authentication/tier_list" );
          action::start( "tier" );
            action::add( "title", lang::phrase( "infractions/title" ) );
            action::add( "name", "infractions" );
            action::add( "permission_table", TABLE_PUNISHMENT_PERMISSIONS );
            action::add( "item_table", TABLE_PUNISHMENTS );
            action::add( "id_column", "punishment_id" );
            action::add( "name_column", "punishment_title" );
            action::add( "extension", "infractions" );
            action::add( "level", $targets[0]['permission_tier_level'] );
            action::start( "target_list" );
              for( $i = 0; $i < $total_targets; $i++ ) {
                action::add( "target", $targets[$i]['punishment_id'] );
              }
            action::end();
          action::end();
        action::end();
      }
		}

    public static function query_get_permission_tiers() {
      action::resume( "authentication/master_tier_list" );
        action::start( "tier" );
          action::add( "title", lang::phrase( "infractions/title" ) );
          action::add( "name", "infractions" );
          action::add( "permission_table", TABLE_PUNISHMENT_PERMISSIONS );
          action::add( "item_table", TABLE_PUNISHMENTS );
          action::add( "id_column", "punishment_id" );
          action::add( "name_column", "punishment_title" );
          action::add( "extension", "infractions" );
        action::end();
      action::end();
		}

    public static function list_infractions() {
      action::resume( "infractions" );
        action::start( "infraction_list" );
          db::open( TABLE_INFRACTIONS );
            db::order( "infraction_name", "ASC" );
          while( $row = db::result() ) {
            action::start( "infraction" );
              action::add( "id", $row['infraction_id'] );
              action::add( "title", $row['infraction_title'] );
              action::add( "name", $row['infraction_name'] );
              action::add( "severity", $row['infraction_severity'] );
            action::end();
          }
        action::end();
      action::end();
    }

    public static function list_punishments() {
      action::resume( "infractions" );
        action::start( "punishment_list" );
          db::open( TABLE_PUNISHMENTS );
            db::order( "punishment_name", "ASC" );
          while( $row = db::result() ) {
            action::start( "punishment" );
              action::add( "id", $row['punishment_id'] );
              action::add( "title", $row['punishment_title'] );
              action::add( "name", $row['punishment_name'] );
              action::add( "automatic", $row['punishment_automatic'] );
              action::add( "threshold", $row['punishment_threshold'] );
            action::end();
          }
        action::end();
      action::end();
    }

    public static function loop_recent_infractions( $path, $targets ) {
      if( count( $targets ) == 0 ) {
        $total_items = action::total( $path );
        for( $i = 0; $i < $total_items; $i++ ) {
          $targets[] = action::get( $path, $i );
        }
      }
      $total_recent_infractions = sys::input( "total_recent_infractions", 5 );
      db::open( TABLE_USER_INFRACTIONS );
        db::select_as( "total_infractions" );
        db::select_count( "user_infraction_id" );
        db::where_in( "user_id", $targets );
        db::limit( 0, $total_recent_infractions );
      $count = db::result();
      db::clear_result();
      $total_infractions = $count['total_infractions'];
      action::resume( "infractions" );
        action::start( "user_infraction_list" );
          action::add( "total", $total_infractions );
          db::open( TABLE_USER_INFRACTIONS );
            db::where_in( "user_id", $targets );
            db::open( TABLE_USERS );
              db::link( "user_id" );
            db::close();
            db::open( TABLE_INFRACTIONS, LEFT );
              db::select( "infraction_title", "infraction_name" );
              db::link( "infraction_id" );
            db::close();
            db::order( "user_infraction_date", "DESC" );
            db::limit( 0, $total_recent_infractions );
          while( $row = db::result() ) {
            action::start( "user_infraction" );
              sys::query( "get_extension_object", $row['user_infraction_target'], $row['user_infraction_type'] );
              action::start( "infracted" );
                action::add( "id", $row['user_id'] );
                action::add( "name", $row['user_name'] );
              action::end();
              db::open( TABLE_USERS );
                db::select( "user_id", "user_name" );
                db::where( "user_id", $row['user_infraction_judge'] );
              $judge = db::result();
              db::clear_result();
              action::start( "judge" );
                action::add( "id", $judge['user_id'] );
                action::add( "name", $judge['user_name'] );
              action::end();
              action::add( "reason", $row['user_infraction_reason'] );
              action::add( "expires", $row['user_infraction_expires'] );
              $timestamp = strtotime( $row['user_infraction_date'] );
              action::add( "period", sys::create_duration( $timestamp, time() ) );
              $timestamp += ( 60 * 60 ) * action::get( "settings/default_timezone" );
              action::add( "datetime", sys::create_datetime( $timestamp ) );
              action::start( "information" );
                action::add( "severity", $row['user_infraction_severity'] );
                action::add( "type", $row['user_infraction_type'] );
                action::add( "title", $row['infraction_title'] );
              action::end();
            action::end();
          }
        action::end();
      action::end();
    }

    public static function get_user_infraction() {
      $user_infraction_id = sys::input( "user_infraction_id", 0 );
      if( !$user_infraction_id ) {
        sys::message( USER_ERROR, lang::phrase( "error/infractions/missing_user_infraction_id/title" ), lang::phrase( "error/infractions/missing_user_infraction_id/body" ) );
      }
      action::resume( "infractions" );
        action::start( "user_infraction" );
          db::open( TABLE_USER_INFRACTIONS );
            db::where( "user_infraction_id", $user_infraction_id );
            db::open( TABLE_USERS );
              db::link( "user_id" );
            db::close();
            db::open( TABLE_INFRACTIONS );
              db::link( "infraction_id" );
            db::close();
          $infraction = db::result();
          db::clear_result();
          action::add( "type", $infraction['user_infraction_type'] );
          action::add( "title", $infraction['infraction_title'] );
          action::start( "infracted" );
            action::add( "id", $infraction['user_id'] );
            action::add( "name", $infraction['user_name'] );
          action::end();
        action::end();
      action::end();
    }

    public static function get_user_punishment() {
      $user_punishment_id = sys::input( "user_punishment_id", 0 );
      if( !$user_punishment_id ) {
        sys::message( USER_ERROR, lang::phrase( "error/infractions/missing_user_punishment_id/title" ), lang::phrase( "error/infractions/missing_user_punishment_id/body" ) );
      }
      action::resume( "infractions" );
        action::start( "user_punishment" );
          db::open( TABLE_USER_PUNISHMENTS );
            db::where( "user_punishment_id", $user_punishment_id );
            db::open( TABLE_USERS );
              db::link( "user_id" );
            db::close();
            db::open( TABLE_PUNISHMENTS );
              db::link( "punishment_id" );
            db::close();
          $punishment = db::result();
          db::clear_result();
          action::add( "type", $punishment['punishment_type'] );
          action::add( "title", $punishment['punishment_title'] );
          action::start( "punished" );
            action::add( "id", $punishment['user_id'] );
            action::add( "name", $punishment['user_name'] );
          action::end();
        action::end();
      action::end();
    }

    public static function list_user_infractions() {
      $user_only = sys::input( "user_only", 0 );
      if( !$user_only ) {
        if( !auth::test( "infractions", "view_infractions" ) ) {
          auth::deny( "infractions", "view_infractions" );
        }
      }
      $page = sys::input( "page", 1 );
      $user_name = sys::input( "user_name", "" );
      $victim = null;
      $victim_name = null;
      if( $user_name ) {
        db::open( TABLE_USERS );
          db::where( "user_name", $user_name );
        $user = db::result();
        db::clear_result();
        if( !$user ) {
          $victim_name = $user_name;
          $victim = 0;
          action::resume( "infractions/infractions_action" );
            action::add( "action", "list_user_infractions" );
            action::add( "success", 0 );
            action::add( "message", lang::phrase( "error/infractions/list_user_infractions/invalid_victim_name" ) );
          action::end();
        } else {
          $victim = $user['user_id'];
          $victim_name = $user['user_name'];
        }
      }
      $user_infraction_judge = sys::input( "user_infraction_judge", "" );
      $judge = null;
      $judge_name = null;
      if( $user_infraction_judge ) {
        db::open( TABLE_USERS );
          db::where( "user_name", $user_infraction_judge );
        $user = db::result();
        db::clear_result();
        if( !$user ) {
          $judge_name = $user_infraction_judge;
          $judge = 0;
          action::resume( "infractions/infractions_action" );
            action::add( "action", "list_user_infractions" );
            action::add( "success", 0 );
            action::add( "message", lang::phrase( "error/infractions/list_user_infractions/invalid_judge_name" ) );
          action::end();
        } else {
          $judge = $user['user_id'];
          $judge_name = $user['user_name'];
        }
      }
      $infraction_name = sys::input( "infraction_name", "" );
      $user_infraction_date_start = sys::input( "user_infraction_date_start", "" );
      $start = null;
      if( $user_infraction_date_start ) {
        $start = str_replace( "-", "/", $user_infraction_date_start );
        $start = $start . " 00:00:00";
        $start_timestamp = strtotime( $start );
        $start = gmdate( "Y/m/d H:i:s", $start_timestamp - ( 60 * 60 ) * sys::timezone() );
      }
      $user_infraction_date_end = sys::input( "user_infraction_date_end", "" );
      $end = null;
      if( $user_infraction_date_end ) {
        $end = str_replace( "-", "/", $user_infraction_date_end );
        $end = $end . " 23:59:59";
        $end_timestamp = strtotime( $end );
        $end = gmdate( "Y/m/d H:i:s", $end_timestamp - ( 60 * 60 ) * sys::timezone() );
      }
      $user_infraction_date_influence = sys::input( "user_infraction_date_influence", "" );
      $user_infraction_status = sys::input( "user_infraction_status", "" );
      $per_page = sys::input( "per_page", 20 );
      db::open( TABLE_USER_INFRACTIONS );
        db::select_as( "total_infractions" );
        db::select_count( "user_infraction_id" );
        if( $user_only ) {
          db::where( "user_id", action::get( "user/id" ) );
        } else {
          if( $victim ) {
            db::where( "user_id", $victim );
          }
          if( $judge ) {
            db::where( "user_infraction_judge", $judge );
          }
          if( $start ) {
            if( $user_infraction_date_influence && $user_infraction_date_influence == 'expire' ) {
              db::where( "user_infraction_expiration", $start, ">" );
            } else {
              db::where( "user_infraction_date", $start, ">" );
            }
          }
          if( $end ) {
            if( $user_infraction_date_influence && $user_infraction_date_influence == 'expire' ) {
              db::where( "user_infraction_expiration", $end, "<" );
            } else {
              db::where( "user_infraction_date", $end, "<" );
            }
          }
          if( $user_infraction_status ) {
            $current_date = gmdate( "Y/m/d H:i:s" );
            if( $user_infraction_status == "expired" ) {
              db::where( "user_infraction_expiration", $current_date, "<" );
            } else if( $user_infraction_status == "reversed" ) {
              db::where( "user_infraction_reversed", 1 );
            } else if( $user_infraction_status == "active" ) {
              db::where( "user_infraction_expiration", $current_date, ">" );
              db::where( "user_infraction_reversed", 0 );
            }
          }
          if( $infraction_name ) {
            db::open( TABLE_INFRACTIONS );
              db::link( "infraction_id" );
              db::where( "infraction_name", $infraction_name );
            db::close();
          }
        }
      $count = db::result();
      db::clear_result();
      $total_infractions = $count['total_infractions'];
      action::resume( "infractions" );
        action::add( "page", $page );
        action::add( "per_page", $per_page );
        action::add( "total_infractions", $total_infractions );
        action::add( "total_pages", ceil( $total_infractions / $per_page ) );
        if( $victim_name ) {
          action::add( "victim", $victim_name );
        }
        if( $judge_name ) {
          action::add( "judge", $judge_name );
        }
        if( $start ) {
          action::start( "start" );
            action::add( "month", gmdate( "m", $start_timestamp ) );
            action::add( "day", gmdate( "d", $start_timestamp ) );
            action::add( "year", gmdate( "Y", $start_timestamp ) );
          action::end();
        }
        if( $end ) {
          action::start( "end" );
            action::add( "month", gmdate( "m", $end_timestamp ) );
            action::add( "day", gmdate( "d", $end_timestamp ) );
            action::add( "year", gmdate( "Y", $end_timestamp ) );
          action::end();
        }
        if( $user_infraction_date_influence ) {
          action::add( "date", $user_infraction_date_influence );
        }
        if( $infraction_name ) {
          action::add( "type", $infraction_name );
        }
        if( $user_infraction_status ) {
          action::add( "status", $user_infraction_status );
        }
        action::start( "user_infraction_list" );
          db::open( TABLE_USER_INFRACTIONS );
            if( $user_only ) {
              db::where( "user_id", action::get( "user/id" ) );
            } else {
              if( $victim ) {
                db::where( "user_id", $victim );
              }
              if( $judge ) {
                db::where( "user_infraction_judge", $judge );
              }
              if( $start ) {
                if( $user_infraction_date_influence && $user_infraction_date_influence == 'expire' ) {
                  db::where( "user_infraction_expiration", $start, ">" );
                } else {
                  db::where( "user_infraction_date", $start, ">" );
                }
              }
              if( $end ) {
                if( $user_infraction_date_influence && $user_infraction_date_influence == 'expire' ) {
                  db::where( "user_infraction_expiration", $end, "<" );
                } else {
                  db::where( "user_infraction_date", $end, "<" );
                }
              }
              if( $user_infraction_status ) {
                $current_date = gmdate( "Y/m/d H:i:s" );
                if( $user_infraction_status == "expired" ) {
                  db::where( "user_infraction_expiration", $current_date, "<" );
                } else if( $user_infraction_status == "reversed" ) {
                  db::where( "user_infraction_reversed", 1 );
                } else if( $user_infraction_status == "active" ) {
                  db::where( "user_infraction_expiration", $current_date, ">" );
                  db::where( "user_infraction_reversed", 0 );
                }
              }
            }
            db::open( TABLE_USERS );
              db::link( "user_id" );
            db::close();
            if( !$user_only && $infraction_name ) {
              db::open( TABLE_INFRACTIONS );
            } else {
              db::open( TABLE_INFRACTIONS, LEFT );
            }
              db::select( "infraction_title", "infraction_name" );
              db::link( "infraction_id" );
              if( !$user_only && $infraction_name ) {
                db::where( "infraction_name", $infraction_name );
              }
            db::close();
            db::order( "user_infraction_date", "DESC" );
            db::limit( $per_page*($page-1), $per_page );
          while( $row = db::result() ) {
            action::start( "user_infraction" );
              sys::query( "get_extension_object", $row['user_infraction_target'], $row['user_infraction_type'] );
              action::add( "id", $row['user_infraction_id'] );
              action::start( "infracted" );
                action::add( "id", $row['user_id'] );
                action::add( "name", $row['user_name'] );
              action::end();
              db::open( TABLE_USERS );
                db::select( "user_id", "user_name" );
                db::where( "user_id", $row['user_infraction_judge'] );
              $judge = db::result();
              db::clear_result();
              action::start( "judge" );
                action::add( "id", $judge['user_id'] );
                action::add( "name", $judge['user_name'] );
              action::end();
              action::add( "reason", $row['user_infraction_reason'] );
              action::add( "expires", $row['user_infraction_expires'] );
              $timestamp = strtotime( $row['user_infraction_date'] );
              action::add( "period", sys::create_duration( $timestamp, time() ) );
              $timestamp += ( 60 * 60 ) * action::get( "settings/default_timezone" );
              action::add( "datetime", sys::create_datetime( $timestamp ) );
              action::add( "severity", $row['user_infraction_severity'] );
              action::add( "type", $row['user_infraction_type'] );
              action::add( "title", $row['infraction_title'] );
              action::add( "reversed", $row['user_infraction_reversed'] );
              $timestamp = strtotime( $row['user_infraction_expiration'] );
              if( $timestamp < time() ) {
                action::add( "expired", 1 );
              } else {
                action::add( "expired", 0 );
              }
              action::start( "expiration" );
                action::add( "period", sys::create_duration( $timestamp, time() ) );
                $timestamp += ( 60 * 60 ) * action::get( "settings/default_timezone" );
                action::add( "datetime", sys::create_datetime( $timestamp ) );
              action::end();
            action::end();
          }
        action::end();
      action::end();
    }

    public static function list_user_punishments() {
      $user_only = sys::input( "user_only", 0 );
      if( !$user_only && !auth::test( "infractions", "view_punishments" ) ) {
        auth::deny( "infractions", "view_punishments" );
      }
      $victim = action::sequence( "url_variables/var", "victim", "" );
      $victim_name = "";
      if( $victim ) {
        db::open( TABLE_USERS );
          db::where( "user_name", $victim );
        $user = db::result();
        db::clear_result();
        if( !$user ) {
          $victim_name = $victim;
          $victim = 0;
          action::resume( "infractions/infractions_action" );
            action::add( "action", "list_user_infractions" );
            action::add( "success", 0 );
            action::add( "message", lang::phrase( "error/infractions/list_user_infractions/invalid_victim_name" ) );
          action::end();
        } else {
          $victim = $user['user_id'];
          $victim_name = $user['user_name'];
        }
      }
      $judge = action::sequence( "url_variables/var", "judge", "" );
      $judge_name = "";
      if( $judge ) {
        db::open( TABLE_USERS );
          db::where( "user_name", $judge );
        $user = db::result();
        db::clear_result();
        if( !$user ) {
          $judge_name = $judge;
          $judge = 0;
          action::resume( "infractions/infractions_action" );
            action::add( "action", "list_user_infractions" );
            action::add( "success", 0 );
            action::add( "message", lang::phrase( "error/infractions/list_user_infractions/invalid_judge_name" ) );
          action::end();
        } else {
          $judge = $user['user_id'];
          $judge_name = $user['user_name'];
        }
      }
      $type = action::sequence( "url_variables/var", "type", "" );
      $start = action::sequence( "url_variables/var", "start", "" );
      if( $start ) {
        $start = str_replace( "-", "/", $start );
        $start = $start . " 00:00:00";
        $start_timestamp = strtotime( $start );
        $start = gmdate( "Y/m/d H:i:s", $start_timestamp - ( 60 * 60 ) * sys::timezone() );
      }
      $end = action::sequence( "url_variables/var", "end", "" );
      if( $end ) {
        $end = str_replace( "-", "/", $end );
        $end = $end . " 23:59:59";
        $end_timestamp = strtotime( $end );
        $end = gmdate( "Y/m/d H:i:s", $end_timestamp - ( 60 * 60 ) * sys::timezone() );
      }
      $date = action::sequence( "url_variables/var", "date", "" );
      $status = action::sequence( "url_variables/var", "status", "" );
      $page = action::sequence( "url_variables/var", "page", 0 );
      if( !$page ) {
        $page = sys::input( "page", 1 );
      }
      $per_page = sys::input( "per_page", 20 );
      db::open( TABLE_USER_PUNISHMENTS );
        db::select_as( "total_punishments" );
        db::select_count( "user_punishment_id" );
        if( $user_only ) {
          db::where( "user_id", action::get( "user/id" ) );
        } else {
          if( $victim ) {
            db::where( "user_id", $victim );
          }
          if( $judge ) {
            db::where( "user_punishment_judge", $judge );
          }
          if( $start ) {
            if( $date && $date == 'expire' ) {
              db::where( "user_punishment_expiration", $start, ">" );
            } else {
              db::where( "user_punishment_date", $start, ">" );
            }
          }
          if( $end ) {
            if( $date && $date == 'expire' ) {
              db::where( "user_punishment_expiration", $end, "<" );
            } else {
              db::where( "user_punishment_date", $end, "<" );
            }
          }
          if( $status ) {
            $current_date = gmdate( "Y/m/d H:i:s" );
            if( $status == "expired" ) {
              db::where( "user_punishment_expiration", $current_date, "<" );
            } else if( $status == "active" ) {
              db::where( "user_punishment_expiration", $current_date, ">" );
            }
          }
          if( $type ) {
            db::open( TABLE_PUNISHMENTS );
              db::link( "punishment_id" );
              db::where( "punishment_name", $type );
            db::close();
          }
        }
      $count = db::result();
      db::clear_result();
      $total_punishments = $count['total_punishments'];
      action::resume( "infractions" );
        action::add( "page", $page );
        action::add( "per_page", $per_page );
        action::add( "total_punishments", $total_punishments );
        action::add( "total_pages", ceil( $total_punishments / $per_page ) );
        if( $victim_name ) {
          action::add( "victim", $victim_name );
        }
        if( $judge_name ) {
          action::add( "judge", $judge_name );
        }
        if( $start ) {
          action::start( "start" );
            action::add( "month", gmdate( "m", $start_timestamp ) );
            action::add( "day", gmdate( "d", $start_timestamp ) );
            action::add( "year", gmdate( "Y", $start_timestamp ) );
          action::end();
        }
        if( $end ) {
          action::start( "end" );
            action::add( "month", gmdate( "m", $end_timestamp ) );
            action::add( "day", gmdate( "d", $end_timestamp ) );
            action::add( "year", gmdate( "Y", $end_timestamp ) );
          action::end();
        }
        if( $date ) {
          action::add( "date", $date );
        }
        if( $type ) {
          action::add( "type", $type );
        }
        if( $status ) {
          action::add( "status", $status );
        }
        action::start( "user_punishment_list" );
          db::open( TABLE_USER_PUNISHMENTS );
            if( $user_only ) {
              db::where( "user_id", action::get( "user/id" ) );
            } else {
              if( $victim ) {
                db::where( "user_id", $victim );
              }
              if( $judge ) {
                db::where( "user_punishment_judge", $judge );
              }
              if( $start ) {
                if( $date && $date == 'expire' ) {
                  db::where( "user_punishment_expiration", $start, ">" );
                } else {
                  db::where( "user_punishment_date", $start, ">" );
                }
              }
              if( $end ) {
                if( $date && $date == 'expire' ) {
                  db::where( "user_punishment_expiration", $end, "<" );
                } else {
                  db::where( "user_punishment_date", $end, "<" );
                }
              }
              if( $status ) {
                $current_date = gmdate( "Y/m/d H:i:s" );
                if( $status == "expired" ) {
                  db::where( "user_punishment_expiration", $current_date, "<" );
                } else if( $status == "active" ) {
                  db::where( "user_punishment_expiration", $current_date, ">" );
                }
              }
            }
            db::open( TABLE_USERS );
              db::link( "user_id" );
            db::close();
            if( !$user_only && $type ) {
              db::open( TABLE_PUNISHMENTS );
            } else {
              db::open( TABLE_PUNISHMENTS, LEFT );
            }
              db::select( "punishment_title", "punishment_name", "punishment_type" );
              db::link( "punishment_id" );
              if( !$user_only && $type ) {
                db::where( "punishment_name", $type );
              }
            db::close();
            db::limit( $per_page*($page-1), $per_page );
          while( $row = db::result() ) {
            action::start( "user_punishment" );
              action::start( "punished" );
                action::add( "id", $row['user_id'] );
                action::add( "name", $row['user_name'] );
              action::end();
              db::open( TABLE_USERS );
                db::select( "user_id", "user_name" );
                db::where( "user_id", $row['user_punishment_judge'] );
              $judge = db::result();
              db::clear_result();
              action::start( "judge" );
                action::add( "id", $judge['user_id'] );
                action::add( "name", $judge['user_name'] );
              action::end();
              action::add( "reason", $row['user_punishment_reason'] );
              action::add( "expires", $row['user_punishment_expires'] );
              $timestamp = strtotime( $row['user_punishment_date'] );
              action::add( "period", sys::create_duration( $timestamp, time() ) );
              $timestamp += ( 60 * 60 ) * action::get( "settings/default_timezone" );
              action::add( "datetime", sys::create_datetime( $timestamp ) );
              action::add( "title", $row['punishment_title'] );
              action::add( "type", $row['punishment_type'] );
              action::start( "expiration" );
                $timestamp = strtotime( $row['user_punishment_expiration'] );
                action::add( "period", sys::create_duration( $timestamp, time() ) );
                $timestamp += ( 60 * 60 ) * action::get( "settings/default_timezone" );
                action::add( "datetime", sys::create_datetime( $timestamp ) );
              action::end();
            action::end();
          }
        action::end();
      action::end();
    }

  }

?>
