<?php
	
/*
Copyright © 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/

  
	class account {
		
		public static function list_users() {			
			$page = 1;
			$letter = "";
			$first_var = action::get( "url_variables/var", 0 );
			if( $first_var == "page" ) {
				$page = action::get( "url_variables/var", 1 );
			} else if( $first_var == "letter" ) {
				$letter = action::get( "url_variables/var", 1 );
			}
			$second_var = action::get( "url_variables/var", 2 );
		  if( $second_var == "page" ) {
        $page = action::get( "url_variables/var", 3 );
      } else if( $second_var == "letter" ) {
        $letter = action::get( "url_variables/var", 3 );
      }
      if( !$page ) {
      	$page = (int) sys::input( "page", 1 );;
      }
      if( !$letter ) {
        $letter = sys::input( "letter", "" );
      }
			$per_page = sys::input( "per_page", 12 );
			db::open( TABLE_USERS );
				db::select_as( "total_users" );
				db::select_count( "user_id" );
				if( $letter ) {
					db::start_block();
            db::where_or();
            if( strlen( $letter ) > 3 ) {
              db::where_like( "user_name", "%$letter%" );
              db::where_like( "user_name", "%" . strtoupper($letter) . "%" );
              db::where_like( "user_name", "%" . strtolower($letter) . "%" );
            } else {
              db::where_like( "user_name", "$letter%" );
              db::where_like( "user_name", strtoupper($letter) . "%" );
              db::where_like( "user_name", strtolower($letter) . "%" );
            }
						db::where_and();
					db::end_block();
				}
			$count = db::result();
      db::clear_result();
			$total_users = $count['total_users'];

      $manage_accounts = auth::test( "account", "manage_accounts" );
			action::start( "account" );
				action::add( "users_per_page", $per_page );
				action::add( "total_users", $total_users );
        if( $per_page > 0 ) {
          action::add( "total_pages", ceil( $total_users / $per_page ) );
        }
				action::add( "page", $page );			
				action::start( "user_list" );				
					db::open( TABLE_USERS );
						if( $letter ) {
							db::start_block();
								db::where_or();
                if( strlen( $letter ) > 3 ) {
                  db::where_like( "user_name", "%$letter%" );
                  db::where_like( "user_name", "%" . strtoupper($letter) . "%" );
                  db::where_like( "user_name", "%" . strtolower($letter) . "%" );
                } else {
                  db::where_like( "user_name", "$letter%" );
                  db::where_like( "user_name", strtoupper($letter) . "%" );
                  db::where_like( "user_name", strtolower($letter) . "%" );
                }
								db::where_and();
							db::end_block();
						}
						db::order( "user_name", "ASC" );
						db::group( "user_id" );
            if( $per_page > 0 ) {
              db::limit( $per_page*($page-1), $per_page );
            }
					while( $row = db::result() ) {
						action::start( "user" );
							$row['user_created_timestamp'] = strtotime( $row['user_created'] );
							$row['user_created_timestamp'] += ( 60 * 60 ) * sys::setting( "global", "default_timezone" );
							action::add( "id", $row['user_id'] );
							action::add( "created_datetime", $row['user_created'] );
							action::add( "created_time", gmdate( "g:i A", $row['user_created_timestamp'] ) );
							action::add( "created_long_date", gmdate( "F jS, Y", $row['user_created_timestamp'] ) );
							action::add( "created_short_date", gmdate( "n/j/y", $row['user_created_timestamp'] ) );
							action::add( "name", $row['user_name'] );
              if( $manage_accounts ) {
                action::add( "email", $row['user_email'] );
              }
							action::add( "active", $row['user_active'] );              
						action::end();
					}				
				action::end();
				if( $letter ) {
					action::add( "letter", $letter );
				}
				action::start( "letter_list" );
					for( $i = 0; $i < 26; $i++ ) {
						action::start( "letter" );
							action::add( "character", chr(97+$i) );
						action::end();
					}
				action::end();
			action::end();
		}

    public static function list_online_users() {
      $only_current_page = sys::input( "only_current_page", 0 );
      $search_by_page = sys::input( "search_by_page", 0 );
      $online_time = gmdate( "Y/m/d H:i:s", time() - ( 60 * 1 ) );
      action::resume( "account" );
        action::start( "online_user_list" );
          db::open( TABLE_USERS );
            db::where( "user_activity", $online_time, ">" );
            if( $only_current_page ) {
              db::where( "user_page", action::get( "request/page" ) );
            }
            if( $search_by_page) {
              db::where( "user_page", "%" . action::get( "request/page" ) . "%", "LIKE" );
            }
          $total_users = 0;
          while( $row = db::result() ) {
            action::start( "online_user" );
              action::add( "id", $row['user_id'] );
              //action::add( "name", $row['user_name'] );
            action::end();
            $total_users++;
          }
        action::end();
        action::add( "users_online", $total_users );
        db::open( TABLE_SESSIONS );
          db::select_as( "guest_count" );
          db::select_count( "session_id" );
          db::where( "user_id", ANONYMOUS );
          db::where( "session_update", $online_time, ">" );
          if( $only_current_page ) {
            db::where( "session_page", action::get( "request/page" ) );
          }
          if( $search_by_page) {
            db::where( "session_page", "%" . action::get( "request/page" ) . "%", "LIKE" );
          }
        $count = db::result();
        db::clear_result();
        action::add( "guests_online", $count['guest_count'] );
        action::add( "total_online", $count['guest_count'] + $total_users );
      action::end();
    }

		public static function hook_get_permission_tiers() {
			$user_id = action::get( "user/user_id" ) ? action::get( "user/user_id" ) : ANONYMOUS;
      db::open( TABLE_PERMISSION_TIERS );
        db::where( "permission_tier_name", "account" );
        db::open( TABLE_USER_PERMISSIONS );
          db::where( "user_id", $user_id );
        db::close();
        db::limit( 0, 1 );
      $tier = db::result();
      db::clear_result();
      if( $tier ) {
        action::resume( "authentication/tier_list" );
          action::start( "tier" );
            action::add( "title", lang::phrase( "account/title" ) );
            action::add( "name", "account" );
            action::add( "permission_table", TABLE_USER_PERMISSIONS );
            action::add( "item_table", TABLE_USERS );
            action::add( "id_column", "user_id" );
            action::add( "name_column", "user_name" );
            action::add( "extension", "account" );
            action::add( "level", $tier['permission_tier_level'] );
            action::start( "target_list" );
              action::add( "target", $user_id );
            action::end();
          action::end();
        action::end();
      }
		}

    public static function hook_terminating_message() {
      if( action::get( "user/logged_in" ) && !defined( "USER_INFORMATION_RETRIEVED" ) ) {
        define( "USER_INFORMATION_RETRIEVED", true );
        action::resume( "user" );
          sys::query( "get_user_information", action::get( "user/id" ) );
        action::end();
      }
    }

    public static function query_get_permission_tiers() {
			action::resume( "authentication/master_tier_list" );
        action::start( "tier" );
          action::add( "title", lang::phrase( "account/title" ) );
          action::add( "name", "account" );
          action::add( "permission_table", TABLE_USER_PERMISSIONS );
          action::add( "item_table", TABLE_USERS );
          action::add( "id_column", "user_id" );
          action::add( "name_column", "user_name" );
          action::add( "extension", "account" );
        action::end();
      action::end();
		}

    public static function hook_get_preference_tables() {
      $user_id = action::get( "user/user_id" ) ? action::get( "user/user_id" ) : ANONYMOUS;
      action::resume( "preferences/table_list" );
        action::start( "table" );
          action::add( "name", "account" );
          action::add( "preference_table", TABLE_USER_PREFERENCES );
          action::add( "item_table", TABLE_USERS );
          action::add( "id_column", "user_id" );
          action::add( "value_column", "user_preference_value" );
          action::add( "extension", "account" );
          action::add( "target", $user_id );
        action::end();
      action::end();
    }

    public static function query_get_user_information( $user_id ) {
      $user = null;
      if( $user_id != action::get( "user/id" ) ) {
        db::open( TABLE_USERS );
          db::where( "user_id", $user_id );
        $user = db::result();
        db::clear_result();
      }
      if( $user ) {
        action::add( "id", $user['user_id'] );
        action::add( "name", $user['user_name'] );
        action::add( "created", $user['user_created'] );
      } else if( $user_id == action::get( "user/id" ) ) {
        action::add( "id", action::get( "user/id" ) );
        action::add( "name", action::get( "user/name" ) );
        action::add( "created", action::get( "user/created" ) );
      }
      if( $user || $user_id == action::get( "user/id" ) ) {
        action::add( "show_email", preferences::get( "global", "show_email", "account", $user_id ) ? 1 : 0 );
        if( $user_id == (int)action::get( "user/id" ) || preferences::get( "global", "show_email", "account", $user_id ) ) {
          action::add( "email", $user['user_email'] );
        }
        action::add( "show_birthdate", preferences::get( "global", "show_birthdate", "account", $user_id ) ? 1 : 0 );
        if( $user_id == (int)action::get( "user/id" ) || preferences::get( "global", "show_birthdate", "account", $user_id ) ) {
          action::add( "birthdate", $user['user_birthdate'] );
        }
      }
    }

    public static function query_get_multi_user_information( $user_ids, $path ) {
      db::open( TABLE_USERS );
        db::where_in( "user_id", $user_ids );
      while( $row = db::result() ) {
        $user_path = str_replace( "%1", $row['user_id'], $path );
        action::resume( $user_path );
          action::add( "id", $row['user_id'] );
          action::add( "name", $row['user_name'] );
          action::add( "created", $row['user_created'] );
          action::add( "show_email", preferences::get( "global", "show_email", "account", $row['user_id'] ) ? 1 : 0 );
          if( $row['user_id'] == (int)action::get( "user/id" ) || preferences::get( "global", "show_email", "account", $row['user_id'] ) ) {
            action::add( "email", $row['user_email'] );
          }
          action::add( "show_birthdate", preferences::get( "global", "show_birthdate", "account", $row['user_id'] ) ? 1 : 0 );
          if( $row['user_id'] == (int)action::get( "user/id" ) || preferences::get( "global", "show_birthdate", "account", $row['user_id'] ) ) {
            action::add( "birthdate", $row['user_birthdate'] );
          }
        action::end();
      }
    }
		
		/*********
		 ** 
		 **  Everything below handles sessions and account access
		 **  
		 *********/

    public static function logged_in() {
      $account_action = sys::input( "account_action", "" );
      if( $account_action == "login" || $account_action == "logout" ) {
        return true;
      }
      $cookie_name = sys::setting( "global", "cookie_name" );
      $session_id = sys::cookie( $cookie_name . "_sid" );
      if( !$session_id ) {
        $session_id = sys::input( "session_id", "" );
      }
      if( !$session_id ) {
        return false;
      }
      $guest = sys::cookie( $cookie_name . "_guest" );
      if( $guest ) {
        return false;
      }
      return true;
    }
		
		public static function initialize( $call_hooks = true ) {
      $cookie_name = sys::setting( "global", "cookie_name" );
			$session_id = sys::cookie( $cookie_name . "_sid" );
			$account_action = sys::input( "account_action", "" );
      if( $account_action == "logout" ) {
        self::logout( $session_id );
      } else if( $account_action == "login" ) {
        self::login();
      } else {
        if( $session_id ) {
          self::retrieve( $session_id );
        } else {
          $session_id = sys::input( "session_id", "" );
          if( $session_id ) {
            self::retrieve( $session_id );
          } else {
            self::begin( ANONYMOUS );
          }
        }
        if( $account_action ) {
          switch( $account_action ) {
            case 'register':
              self::register();
              break;
            case 'reset':
              self::reset();
              break;
            case 'edit':
              self::edit();
              break;
            case 'complete':
              self::complete();
              break;
            case 'disable':
              self::disable( $session_id );
              break;
          }
        }
      }

      if( $call_hooks ) {
        sys::hook( "account_initialized" );
        if( action::get( "user/logged_in" ) && !defined( "USER_INFORMATION_RETRIEVED" ) ) {
          define( "USER_INFORMATION_RETRIEVED", true );
          action::resume( "user" );
            sys::query( "get_user_information", action::get( "user/id" ) );
          action::end();
        }
      }
		}
		
		private static function retrieve( $session_id ) {
			db::open( TABLE_SESSIONS );
				db::where( "session_id", $session_id );
				db::open( TABLE_USERS );
					db::link( "user_id" );
			$session_data = db::result();
      db::clear_result();
			action::resume( "user" );
			if( !$session_data || ( !$session_data['user_active'] && $session_data['user_id'] != ANONYMOUS ) ) {
				self::begin( ANONYMOUS, $session_id );
			} else if( $session_data['user_id'] != ANONYMOUS ) {
				$ip_security = sys::setting( "account", "ip_security" );
        $ip_security = action::get( "settings/ip_security" );
				if( isset( $_SERVER['HTTPS'] ) ) {
          $secure_expiration = time() - $session_data['session_secure_update'];
					$secure_session_length = sys::setting( "account", "secure_session_length" );
				}
				if( $ip_security && $session_data['session_ip'] != $_SERVER['REMOTE_ADDR'] ) {
					action::add( "logged_in", 0 );
					db::open( TABLE_SESSIONS );
            db::where( "session_id", $session_id );
          if( !db::delete() ) {
            sys::message( SYSTEM_ERROR, lang::phrase( "account/main/delete_session_error/title" ), lang::phrase( "account/main/delete_session_error/body", db::sql_error(), db::delete_sql() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
          }
				} else if( isset( $_SERVER['HTTPS'] ) && ( !$session_data['session_secure'] || ( $session_data['session_secure'] && $secure_expiration > $secure_session_length ) ) ) {
					action::add( "logged_in", 0 );
          action::add( "user_id", ANONYMOUS );
          action::add( "id", ANONYMOUS );
				} else {
					action::add( "logged_in", 1 );
					action::add( "user_name", $session_data['user_name'] );
					action::add( "user_id", $session_data['user_id'] );
					action::add( "user_email", $session_data['user_email'] );
          action::add( "name", $session_data['user_name'] );
					action::add( "id", $session_data['user_id'] );
					action::add( "email", $session_data['user_email'] );
          action::add( "created", $session_data['user_created'] );
          action::add( "terms_agreed", $session_data['user_terms_agree'] );
          action::add( "privacy_agreed", $session_data['user_privacy_agree'] );
          action::add( "community_agreed", $session_data['user_community_agree'] );
          action::add( "registration_complete", $session_data['user_registration_complete'] );          
					self::update( $session_id, $session_data['user_id'] );
				}
			} else {
				action::add( "logged_in", 0 );
        action::add( "user_id", ANONYMOUS );
        action::add( "id", ANONYMOUS );
				self::update( $session_id );
			}
			action::end();
		}
		
		private static function update( $session_id, $user_id = 0 ) {
			db::open( TABLE_SESSIONS );
        db::where( "session_id", $session_id );
        db::set( "session_update", "UTC_TIMESTAMP()", false );
        db::set( "session_page", action::get( "request/page" ) );
        db::set( "session_extension", action::get( "request/extension" ) );
        db::set( "session_action", action::get( "request/action" ) );
        if( isset( $_SERVER['HTTPS'] ) ) {
          db::set( "session_secure_update", "UTC_TIMESTAMP()", false );
        }
			if( !db::update() ) {
				sys::message( SYSTEM_ERROR, lang::phrase( "account/main/update_session_error/title" ), lang::phrase( "account/main/update_session_error/body", db::sql_error(), db::sql() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
			}
      if( $user_id ) {
        $current_date = gmdate( "Y/m/d H:i:s", time() );
        db::open( TABLE_USERS );
          db::where( "user_id", $user_id );
          db::set( "user_activity", $current_date );
          if( action::get( "request/page" ) ) {
            db::set( "user_page", action::get( "request/page" ) );
          }
        if( !db::update() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/account/update_user_visited/title" ),
            lang::phrase( "error/account/update_user_visited/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      }
			setcookie( sys::setting( "global", "cookie_name" )."_sid", $session_id, time()+sys::setting( "account", "unsecure_session_length" ), sys::setting( "global", "cookie_path" ), sys::setting( "global", "cookie_domain" ) );
      if( $user_id == 0 ) {
        setcookie( sys::setting( "global", "cookie_name" )."_guest", 1, time()+sys::setting( "account", "unsecure_session_length" ), sys::setting( "global", "cookie_path" ), sys::setting( "global", "cookie_domain" ) );
      } else {
        setcookie( sys::setting( "global", "cookie_name" )."_guest", 0, time()-8000, sys::setting( "global", "cookie_path" ), sys::setting( "global", "cookie_domain" ) );
      }
		}
		
		private static function begin( $user_id ) {
			$session_id = sys::random_chars( 32 );
			$session_ip = $_SERVER['REMOTE_ADDR'];
			$session_secure = isset( $_SERVER['HTTPS'] ) ? 1 : 0;
			
			//If the user is not a guest, delete all of their expired sessions
			if( $user_id != ANONYMOUS ) {
				db::open( TABLE_SESSIONS );
					db::where( "user_id", $user_id );
				
				if( !db::delete() ) {
					sys::message( SYSTEM_ERROR, lang::phrase( "account/main/delete_session_error/title" ), lang::phrase( "account/main/delete_session_error/body", db::sql_error(), $sql ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
				}
			}

      $session_length = sys::setting( "account", "unsecure_session_length" );
      $user_session = time() - $session_length;
      $guest_session = time() - ( 60 * 60 * 12 );
      $user_date = gmdate( "Y/m/d H:i:s", $user_session );
      $guest_date = gmdate( "Y/m/d H:i:s", $guest_session );
      db::open( TABLE_SESSIONS );
        db::start_block();
          db::where( "user_id", ANONYMOUS, "!=" );
          db::where( "session_update", $user_date, "<" );
          db::where_or();
          db::start_block();
            db::where( "user_id", ANONYMOUS );
            db::where_and();
            db::where( "session_update", $guest_date, "<" );
          db::end_block();
        db::end_block();
      if( !db::delete() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/account/delete_old_sessions/title" ),
          lang::phrase( "error/account/delete_old_sessions/body" ),
          __FILE__, __LINE__, __FUNCTION__, __CLASS__
        );
      }
			
			db::open( TABLE_SESSIONS );
				db::set( "session_id", $session_id );
				db::set( "user_id", $user_id );
				db::set( "session_start", "UTC_TIMESTAMP()", false );
				db::set( "session_update", "UTC_TIMESTAMP()", false );
				if( $session_secure ) {
					db::set( "session_secure_update", "UTC_TIMESTAMP()", false );
				}
				db::set( "session_page", "" );
				db::set( "session_extension", "" );
				db::set( "session_action", "" );
				db::set( "session_ip", $session_ip );
				db::set( "session_secure", $session_secure );
			if( !db::insert() ) {
				sys::message( SYSTEM_ERROR, lang::phrase( "account/main/create_session_error/title" ), lang::phrase( "account/main/create_session_error/body", db::sql_error() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
			}
			self::retrieve( $session_id );
		}
		
		private static function get_user_by_user_id( $user_id ) {
			db::open( TABLE_USERS );
				db::where( "user_id", $user_id );
			$user = db::result(true);
			return $user;
		}
		
		private static function get_user_by_user_name( $user_name ) {
			db::open( TABLE_USERS );
				db::where( "user_name", $user_name );
			$user = db::result();
      db::clear_result();
			return $user;
		}
		
		private static function get_user_by_user_email( $user_email ) {
			db::open( TABLE_USERS );
				db::where( "user_email", $user_email );
			$user = db::result();
      db::clear_result();
			return $user;
		}
		
		private static function login() {
			$user_name = sys::input( "user_name", "" );
			$user_password = sys::input( "user_password", "" );
			$user_remember = sys::input( "user_remember", false );
			$return_page = sys::input( "return_page", RELATIVE_DIR );
			$success = true;
			action::start( "account" );
                        $message = "";
			if( !$user_name || !$user_password ) {
				action::add( "account_message", lang::phrase( "account/login/login_incomplete" ) );
				if( !$user_name ) {
					action::add( "user_name_message", lang::phrase( "account/login/user_name_required" ) );
                                        $message = lang::phrase( "account/login/user_name_required" );
				}
				if( !$user_password ) {
					action::add( "user_password_message", lang::phrase( "account/login/user_password_required" ) );
                                        $message = lang::phrase( "account/login/user_password_required" );
				}
				$success = false;
			} else {
				$user = self::get_user_by_user_name( $user_name );
				if( !$user ) {
					action::add( "account_message", lang::phrase( "account/main/unknown_user" ) );
                                        $message = lang::phrase( "account/main/unknown_user" );
					$success = false;
				} else if( !phpass::check( $user_password, $user['user_password'] ) ) {
					action::add( "account_message", lang::phrase( "account/login/wrong_password" ) );
                                        $message = lang::phrase( "account/login/wrong_password" );
					$success = false;
				}
			}			
			if( !$success ) {
				action::add( "login_success", 0 );
				action::add( "user_name", $user_name );
			}
			action::end();
			if( $success ) {
				self::begin( $user['user_id'] );
				action::resume( "request" );
					action::add( "return_page", $return_page );
					action::add( "return_text", lang::phrase( "main/return_previous" ) );
				action::end();
				sys::message( USER_MESSAGE, lang::phrase( "account/main/logged_in/title" ), lang::phrase( "account/main/logged_in/body" ) );
			} else {
                          sys::message( USER_MESSAGE, lang::phrase( "account/login/login_failed" ), $message );
                        }
		}
		
		private static function logout( $session_id ) {
			$return_page = sys::input( "return_page", RELATIVE_DIR . "/" );
			self::begin( ANONYMOUS, $session_id );
			action::resume( "request" );
        action::add( "return_page", $return_page );
        action::add( "return_text", lang::phrase( "main/return_main" ) );
      action::end();
      sys::message( USER_MESSAGE, lang::phrase( "account/log_out/success/title" ), lang::phrase( "account/log_out/success/body" ) );
		}
		
		private static function reset() {
			$user_email = sys::input( "user_email", "" );
      $user_name = sys::input( "user_name", "" );
			$success = true;
			action::resume( "account" );
        action::start( "account_action" );
          action::add( "action", "reset" );
          if( !$user_email && !$user_name ) {
            action::add( "message", lang::phrase( "error/account/reset/reset_incomplete" ) );
            $success = false;
          } else {
            db::open( TABLE_USERS );
              if( $user_email ) {
                db::where( "user_email", $user_email );
              }
              if( $user_name ) {
                db::where( "user_name", $user_name );
              }
            $user = db::result();
            db::clear_result();
            if( !$user ) {
              action::add( "message", lang::phrase( "error/account/reset/unknown_user" ) );
              $success = false;
            }
            $characters = array();
            for( $i = 48; $i < 58; $i++ ) {
              $characters[] = chr( $i );
            }
            for( $i = 65; $i < 91; $i++ ) {
              $characters[] = chr( $i );
            }
            for( $i = 97; $i < 123; $i++ ) {
              $characters[] = chr( $i );
            }
            $minlength = sys::setting( "account", "password_minlength" );
            $new_password = "";
            while( strlen( $new_password ) < $minlength ) {
              $index = rand( 0, count( $characters ) );
              $new_password .= $characters[$index];
            }
            db::open( TABLE_USERS );
              db::where( "user_id", $user['user_id'] );
              db::set( "user_password", phpass::hash( $new_password ) );
            if( !db::update() ) {
              sys::message(
                SYSTEM_ERROR,
                lang::phrase( "error/account/reset/title" ),
                lang::phrase( "error/account/reset/body" ),
                __FILE__, __LINE__, __FUNCTION__, __CLASS__
              );
            }
          }
          if( !$success ) {
            action::add( "success", 0 );
          }
          if( $success ) {
            action::add( "success", 1 );
            action::add( "message", lang::phrase( "account/reset/success/body" ) );
            action::add( "password", $new_password );
            action::add( "user_name", $user['user_name'] );
            email::send( "user_reset_password", "html", $user['user_email'], sys::setting( "global", "admin_email" ), DEFAULT_LANG, lang::phrase( "account/reset/success/title" ) );
          }
        action::end();
      action::end();
		}
		
		private static function register() {
      $user_birthdate = sys::input( "user_birthdate", "" );
			$user_name = sys::input( "user_name", "" );
			$user_email = sys::input( "user_email", "" );
			$user_confirm_email = sys::input( "user_confirm_email", "" );
			$user_password = sys::input( "user_password", "" );
			$user_confirm_password = sys::input( "user_confirm_password", "" );
			$user_terms_agree = sys::input( "user_terms_agree", false );
      $user_privacy_agree = sys::input( "user_privacy_agree", false );
      $user_community_agree = sys::input( "user_community_agree", false );
			$success = true;
			action::resume( "account/account_action" );
			if( !$user_name || !$user_password || !$user_email || !$user_birthdate ) {
				action::add( "message", lang::phrase( "account/register/register_incomplete" ) );
				if( !$user_name ) {
					action::add( "user_name_message", lang::phrase( "account/register/no_user_name" ) );
				}
				if( !$user_password ) {
					action::add( "user_password_message", lang::phrase( "account/register/no_user_password" ) );
				}
				if( !$user_email ) {
					action::add( "user_email_message", lang::phrase( "account/register/no_email" ) );
				}
        if( !$user_birthdate ) {
          action::add( "user_birthdate_message", lang::phrase( "account/register/no_birthdate" ) );
        }
				$success = false;
			} else if( preg_match( "/([^a-zA-Z0-9_\-])/s", $user_name ) ) {
				action::add( "message", lang::phrase( "account/register/user_name_format_nospecial" ) );
				action::add( "user_name_message", lang::phrase( "account/register/user_name_format_nospecial" ) );
				$success = false;
			} else if( preg_match( "/([^a-zA-Z0-9_\-])/s", $user_password ) ) {
				action::add( "message", lang::phrase( "account/register/user_password_format_nospecial" ) );
				action::add( "user_password_message", lang::phrase( "account/register/user_password_format_nospecial" ) );
				$success = false;
			} else if( !preg_match( "/([\w\d!#\$%&'\*\+\-\/=?\^_`{\|}~].+)@([\w\d_\-].+)\.([\w\d_\-\.].+)/s", $user_email ) ) {
				action::add( "message", lang::phrase( "account/register/user_email_format_email" ) );
				action::add( "user_email_message", lang::phrase( "account/register/user_email_format_email" ) );
				$success = false;
			} else if( $user_email != $user_confirm_email ) {
				action::add( "message", lang::phrase( "account/register/user_confirm_email_confirm" ) );
				action::add( "user_confirm_email_message", lang::phrase( "account/register/user_confirm_email_confirm" ) );
				$success = false;
			} else if( $user_password != $user_confirm_password ) {
				action::add( "message", lang::phrase( "account/register/user_confirm_password_confirm" ) );
				action::add( "user_confirm_password_message", lang::phrase( "account/register/user_confirm_password_confirm" ) );
				$success = false;
			} else if( !$user_terms_agree ) {
				action::add( "message", lang::phrase( "account/register/user_terms_agree_required" ) );
				action::add( "user_terms_agree_message", lang::phrase( "account/register/user_terms_agree_required" ) );
				$success = false;
			} else if( !$user_privacy_agree ) {
				action::add( "message", lang::phrase( "account/register/user_privacy_agree_required" ) );
				action::add( "user_privacy_agree_message", lang::phrase( "account/register/user_privacy_agree_required" ) );
				$success = false;
			} else if( !$user_community_agree ) {
				action::add( "message", lang::phrase( "account/register/user_community_agree_required" ) );
				action::add( "user_community_agree_message", lang::phrase( "account/register/user_community_agree_required" ) );
				$success = false;
			} else if( strlen( $user_name ) < sys::setting( "account", "name_minlength" ) ) {
				action::add( "message", lang::phrase( "account/register/user_name_minlength", sys::setting( "account", "name_minlength" ) ) );
				action::add( "user_name_message", lang::phrase( "account/register/user_name_minlength", sys::setting( "account", "name_minlength" ) ) );
				$success = false;
			} else if( strlen( $user_password ) < sys::setting( "account", "password_minlength" ) ) {
				action::add( "message", lang::phrase( "account/register/user_password_minlength" ), sys::setting( "account", "password_minlength" ) );
				action::add( "user_password_message", lang::phrase( "account/register/user_password_minlength", sys::setting( "account", "password_minlength" ) ) );
				$success = false;
			} else if( strlen( $user_name ) > 25 ) {
				action::add( "message", lang::phrase( "account/register/user_name_maxlength" ), 25 );
				action::add( "user_name_message", lang::phrase( "account/register/user_name_maxlength", 25 ) );
				$success = false;
			} else if( strlen( $user_email ) > 255 ) {
				action::add( "message", lang::phrase( "account/register/user_email_maxlength" ), 255 );
				action::add( "user_name_message", lang::phrase( "account/register/user_email_maxlength", 255 ) );
				$success = false;
			} else if( self::check_name( $user_name ) ) {
				action::add( "message", lang::phrase( "account/register/user_name_exists", $user_name ) );
				action::add( "user_name_message", lang::phrase( "account/register/user_name_exists", $user_name ) );
				$success = false;
			} else if( self::check_email( $user_email ) ) {
				action::add( "message", lang::phrase( "account/register/user_email_exists" ) );
				action::add( "user_email_message", lang::phrase( "account/register/user_email_exists" ) );
				$success = false;
			}

      $birthdate = explode( "/", $user_birthdate );
      $target_time = time() - ( 60 * 60 * 24 * 365 * 18 );
      $target_year = gmdate( "Y", $target_time );
      $target_month = gmdate( "n", $target_time );
      $target_day = gmdate( "j", $target_time );
      if( strlen( $user_birthdate ) != 10 || count( $birthdate ) != 3 ) {
        action::add( "message", lang::phrase( "account/register/user_birthdate_wrong_format" ) );
        action::add( "user_birthdate_message", lang::phrase( "account/register/user_birthdate_wrong_format" ) );
        $success = false;
      } else {
        $birth_year = (int) $birthdate[2];
        $birth_day = (int) $birthdate[1];
        $birth_month = (int) $birthdate[0];
        if( $birth_year > $target_year ||
            ( $birth_year == $target_year && $birth_month > $target_month ) ||
            ( $birth_year == $target_year && $birth_month == $target_month && $birth_day > $target_day ) ) {
          action::add( "message", lang::phrase( "account/register/user_too_young" ) );
          action::add( "user_birthdate_message", lang::phrase( "account/register/user_too_young" ) );
          $success = false;
        }
        $user_birthdate_final = $birthdate[2] . "-" . $birthdate[0] . "-" . $birthdate[1];
      }

      sys::hook( "account_registration" );
      if( (int) action::get( "account/account_action/extension_failed" ) == 1 ) {
        $success = false;
      }

			if( !$success ) {
        action::add( "success", 0 );
        action::add( "user_birthdate", $user_birthdate );
				action::add( "user_name", $user_name );
				action::add( "user_email", $user_email );
				action::add( "user_confirm_email", $user_confirm_email );
        if( $user_terms_agree ) {
          action::add( "user_terms_agree", "1" );
        }
        if( $user_privacy_agree ) {
          action::add( "user_privacy_agree", "1" );
        }
        if( $user_community_agree ) {
          action::add( "user_community_agree", "1" );
        }
			} else {
        action::add( "success", 1 );
        action::add( "message", lang::phrase( "action/register/success" ) );
      }
			action::end();
			if( $success ) {
				$user_active = 1;
				$user_activation_code = '';
				if( (int)sys::setting( "account", "activation_required" ) ) {
					$user_active = 0;
					$user_activation_code = sys::random_chars( 16 );
				}
        $current_date = gmdate( "Y/m/d H:i:s", time() );
				db::open( TABLE_USERS );
					db::set( "user_name", $user_name );
					db::set( "user_password", phpass::hash( $user_password ) );
					db::set( "user_email", $user_email );
					db::set( "user_activation_code", $user_activation_code );
					db::set( "user_active", $user_active );
          db::set( "user_birthdate", $user_birthdate_final );
          db::set( "user_created", $current_date );
          db::set( "user_activity", $current_date );
          db::set( "user_terms_agree", 1 );
          db::set( "user_privacy_agree", 1 );
          db::set( "user_community_agree", 1 );
          db::set( "user_registration_complete", 1 );
				if( !db::insert() ) {
					sys::message( APPLICATION_ERROR, lang::phrase( "account/main/create_account_error/title" ), lang::phrase( "account/main/create_account_error/body", db::sql_error(), $sql ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
				}

        sys::hook( "account_registration_successful", db::id() );
				
				action::start( "registration" );
					action::add( "user_name", $user_name );
					action::add( "user_email", $user_email );
				action::end();
				email::send( "user_registered", "html", $user_email, sys::setting( "global", "admin_email" ), DEFAULT_LANG, lang::phrase( "account/register/registration_successful", action::get( "settings/site_name" ) ) );

        $return_page = sys::input( "return_page", RELATIVE_DIR );
				if( !$user_active ) {
					action::resume( "request" );
            action::add( "return_page", RELATIVE_DIR );
            action::add( "return_text", lang::phrase( "main/return_main" ) );
					action::end();
					sys::message( USER_MESSAGE, lang::phrase( "account/main/activation_required/title" ), lang::phrase( "account/main/activation_required/body", $user_email ) );
				} else {
					action::resume( "request" );
						action::add( "return_page", $return_page );
						action::add( "return_text", lang::phrase( "account/main/return_login" ) );
					action::end();
					sys::message( USER_MESSAGE, lang::phrase( "account/main/account_created/title" ), lang::phrase( "account/main/account_created/body", $user_name, $user_email ) );
				}
			}
		}

    private static function edit() {
      if( !action::get( "user/logged_in" ) ) {
        sys::message( USER_ERROR, lang::phrase( "error/account/must_be_logged_in/title" ), lang::phrase( "error/account/must_be_logged_in/body" ) );
      }
      $user_email = sys::input( "user_email", "" );
      $user_new_password = sys::input( "user_new_password", "" );
      $user_new_password_confirm = sys::input( "user_new_password_confirm", "" );
      $user_old_password = sys::input( "user_old_password", "" );
      $user_id = action::get( "user/id" );

      action::resume( "account/account_action" );
      db::open( TABLE_USERS );
        db::where( "user_id", $user_id );
      $user = db::result();
      db::clear_result();
      if( !phpass::check( $user_old_password, $user['user_password'] ) ) {
        action::add( "success", 0 );
        action::add( "message", "error/account/edit/old_password_incorrect" );
      } else {
        $success = true;
        if( !$user_email ) {
          action::add( "message", "error/account/edit/missing_email" );
          $success = false;
        } else if( !preg_match( "/([\w\d!#\$%&'\*\+\-\/=?\^_`{\|}~].+)@([\w\d_\-].+)\.([\w\d_\-\.].+)/s", $user_email ) ) {
          action::add( "message", lang::phrase( "account/register/user_email_format_email" ) );
          $success = false;
        }
        if( $user_new_password && $user_new_password != $user_new_password_confirm ) {
          action::add( "message", lang::phrase( "error/account/edit/new_password_mismatch" ) );
          $success = false;
        } else if( $user_new_password && strlen( $user_new_password ) < sys::setting( "account", "password_minlength" ) ) {
          action::add( "message", lang::phrase( "account/register/user_password_minlength" ) );
          $success = false;
        }
        if( !$success ) {
          action::add( "success", 0 );
        } else {
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "account/edit/success" ) );

          db::open( TABLE_USERS );
            db::where( "user_id", $user_id );
            db::set( "user_email", $user_email );
            if( $user_new_password ) {
              db::set( "user_password", phpass::hash( $user_new_password ) );
            }
          if( !db::update() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/account/edit/title" ),
              lang::phrase( "error/account/edit/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
        }
      }
      action::end();
    }

    private static function complete() {
      $user_id = action::get( "user/id" );
      $user_birthdate = sys::input( "user_birthdate", "" );
			$user_terms_agree = sys::input( "user_terms_agree", false );
      $user_privacy_agree = sys::input( "user_privacy_agree", false );
      $user_community_agree = sys::input( "user_community_agree", false );
			$success = true;
			action::resume( "account/account_action" );
        action::add( "action", "complete" );
        if( !$user_birthdate ) {
          action::add( "message", lang::phrase( "account/register/register_incomplete" ) );
          if( !$user_birthdate ) {
            action::add( "user_birthdate_message", lang::phrase( "account/register/no_birthdate" ) );
          }
          $success = false;
        } else if( !$user_terms_agree ) {
          action::add( "message", lang::phrase( "account/register/user_terms_agree_required" ) );
          action::add( "user_terms_agree_message", lang::phrase( "account/register/user_terms_agree_required" ) );
          $success = false;
        } else if( !$user_privacy_agree ) {
          action::add( "message", lang::phrase( "account/register/user_privacy_agree_required" ) );
          action::add( "user_privacy_agree_message", lang::phrase( "account/register/user_privacy_agree_required" ) );
          $success = false;
        } else if( !$user_community_agree ) {
          action::add( "message", lang::phrase( "account/register/user_community_agree_required" ) );
          action::add( "user_community_agree_message", lang::phrase( "account/register/user_community_agree_required" ) );
          $success = false;
        }

        $birthdate = explode( "/", $user_birthdate );
        $target_time = time() - ( 60 * 60 * 24 * 365 * 18 );
        $target_year = gmdate( "Y", $target_time );
        $target_month = gmdate( "n", $target_time );
        $target_day = gmdate( "j", $target_time );
        if( strlen( $user_birthdate ) != 10 || count( $birthdate ) != 3 ) {
          action::add( "message", lang::phrase( "account/register/user_birthdate_wrong_format" ) );
          action::add( "user_birthdate_message", lang::phrase( "account/register/user_birthdate_wrong_format" ) );
          $success = false;
        } else {
          $birth_year = (int) $birthdate[2];
          $birth_day = (int) $birthdate[1];
          $birth_month = (int) $birthdate[0];
          if( $birth_year > $target_year ||
              ( $birth_year == $target_year && $birth_month > $target_month ) ||
              ( $birth_year == $target_year && $birth_month == $target_month && $birth_day > $target_day ) ) {
            action::add( "message", lang::phrase( "account/register/user_too_young" ) );
            action::add( "user_birthdate_message", lang::phrase( "account/register/user_too_young" ) );
            $success = false;
          }
          $user_birthdate_final = $birthdate[2] . "-" . $birthdate[0] . "-" . $birthdate[1];
        }

        sys::hook( "account_complete" );
        if( (int) action::get( "account/account_action/extension_failed" ) == 1 ) {
          $success = false;
        }

        if( !$success ) {
          action::add( "success", 0 );
          action::add( "user_birthdate", $user_birthdate );
          if( $user_terms_agree ) {
            action::add( "user_terms_agree", "1" );
          }
          if( $user_privacy_agree ) {
            action::add( "user_privacy_agree", "1" );
          }
          if( $user_community_agree ) {
            action::add( "user_community_agree", "1" );
          }
        } else {
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "account/complete_registration/success" ) );
        }
			action::end();
			if( $success ) {
        $current_date = gmdate( "Y/m/d H:i:s", time() );
				db::open( TABLE_USERS );
          db::where( "user_id", $user_id );
          db::set( "user_birthdate", $user_birthdate_final );
          db::set( "user_activity", $current_date );
          db::set( "user_terms_agree", 1 );
          db::set( "user_privacy_agree", 1 );
          db::set( "user_community_agree", 1 );
          db::set( "user_registration_complete", 1 );
				if( !db::update() ) {
					sys::message( APPLICATION_ERROR, lang::phrase( "error/account/complete_registration/title" ), lang::phrase( "error/account/complete_registration/body", db::sql_error(), $sql ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
				}
        
        sys::hook( "account_complete_successful", $user_id );
        
        $return_page = sys::input( "return_page", RELATIVE_DIR );
        action::resume( "request" );
          action::add( "return_page", $return_page );
          action::add( "return_text", lang::phrase( "account/registration_completed/success/return" ) );
        action::end();
        sys::message( USER_MESSAGE, lang::phrase( "account/registration_completed/success/title" ), lang::phrase( "account/registration_completed/success/body", $user_name, $user_email ) );
			}
    }

    private static function disable( $session_id ) {
      if( !action::get( "user/logged_in" ) ) {
        sys::message( USER_ERROR, lang::phrase( "error/account/must_be_logged_in/title" ), lang::phrase( "error/account/must_be_logged_in/body" ) );
      }
      $user_id = action::get( "user/id" );
      db::open( TABLE_USERS );
        db::where( "user_id", $user_id );
        db::set( "user_active", 0 );
        db::set( "user_registration_complete", 0 );
      if( !db::update() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/account/disable/title" ),
          lang::phrase( "error/account/disable/body" ),
          __FILE__, __LINE__, __FUNCTION__, __CLASS__
        );
      }
      action::resume( "account/account_action" );
        action::add( "action", "disable" );
        action::add( "success", 1 );
        action::add( "message", lang::phrase( "account/disable/success/body" ) );
      action::end();
      action::resume( "request" );
        action::add( "return_page", RELATIVE_DIR . "/" );
        action::add( "return_text", lang::phrase( "account/disable/success/return" ) );
      action::end();
      self::begin( ANONYMOUS, $session_id );
      sys::message( USER_MESSAGE, lang::phrase( "account/disable/success/title" ), lang::phrase( "account/disable/success/body" ) );
    }
		
		public static function check_user_name() {
			$value = sys::input( 'value', '' );
			self::check_name( $value );
		}
		
		private static function check_name( $user_name ) {
			$check_name = self::get_user_by_user_name( $user_name );
			$name_exists = false;
			if( !$user_name ) {
				$name_exists = true;
				action::add( "check_element", 1 );
				action::add( "check_message", lang::phrase( "account/register/user_name_required", $user_name ) );
			} else if( $check_name ) {
				$name_exists = true;
				action::add( "check_element", 1 );
				action::add( "check_message", lang::phrase( "account/register/user_name_exists", $user_name ) );
			} else {
				action::add( "check_element", 0 );
				action::add( "check_message", lang::phrase( "account/register/user_name_available", $user_name ) );
			}
			return $name_exists;
		}
		
		public static function check_user_email() {
			$value = sys::input( 'value', '' );
			self::check_email( $value );
		}
		
		private static function check_email( $user_email ) {
			$check_email = self::get_user_by_user_email( $user_email );
			$email_exists = false;
			if( $check_email ) {
				$email_exists = true;
				action::add( "check_element", 1 );
				action::add( "check_message", lang::phrase( "account/register/user_email_exists" ) );
			} else {
				action::add( "check_element", 0 );
				action::add( "check_message", lang::phrase( "account/register/user_email_available" ) );
			}
			return $email_exists;
		}

    public static function get_user() {
      if( action::total( "url_variables/var" ) == 1 ) {
        $user_id = action::get( "url_variables/var", 0 );
      }
      if( !$user_id ) {
        $user_id = sys::input( "user_id", 0 );
      }
      $manage_accounts = auth::test( "account", "manage_accounts" );
      action::resume( "account/user" );
        db::open( TABLE_USERS );
          db::where( "user_id", $user_id );
        $user = db::result();
        db::clear_result();
        if( $manage_accounts ) {
          action::add( "email", $user['user_email'] );
          action::add( "birthdate", $user['user_birthdate'] );
        }
        action::add( "active", $user['user_active'] );
        action::add( "last_activity", $user['user_activity'] );
        sys::query( "get_user_information", $user['user_id'] );
      action::end();
    }
		
	}

?>