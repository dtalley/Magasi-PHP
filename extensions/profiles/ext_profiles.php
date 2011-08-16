<?php

/*
Copyright © 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/

  class profiles {

    public static function hook_account_registration() {
      self::check_registration();
    }

    public static function hook_account_registration_successful( $user_id ) {
      self::complete_registration( $user_id );
    }

    public static function hook_account_complete() {
      self::check_registration();
    }

    public static function hook_account_complete_successful( $user_id ) {
      self::complete_registration( $user_id );
    }

    private static function check_registration() {
      $user_postal = sys::input( "user_postal", "" );
      $user_country = sys::input( "user_country", "" );
      $user_gender = sys::input( "user_gender", "" );
      $success = true;
      if( !$user_postal || !$user_country ) {
        action::add( "extension_failed", 1 );
        action::add( "message", lang::phrase( "account/register/register_incomplete" ) );
        if( !$user_postal ) {
          action::add( "user_postal_message", lang::phrase( "error/profiles/missing_postal_code" ) );
        }
        if( !$user_country ) {
          action::add( "user_country_message", lang::phrase( "error/profiles/missing_country" ) );
        }
        if( !$user_gender ) {
          action::add( "user_gender_message", lang::phrase( "error/profiles/missing_gender" ) );
        }
        $success = false;
      }
      $location = googlemaps::return_location( $user_country, $user_postal );
      if( !$location ) {
        action::add( "extension_failed", 1 );
        action::add( "message", lang::phrase( "account/register/invalid_postal_code" ) );
        action::add( "user_postal_message", lang::phrase( "account/register/invalid_postal_code" ) );
        $success = false;
      }
      action::add( "user_postal", $user_postal );
      action::add( "user_country", $user_country );
      action::add( "user_gender", $user_gender );
    }

    private static function complete_registration( $user_id ) {
      $user_postal = sys::input( "user_postal", "" );
      $user_country_code = sys::input( "user_country", "" );
      $user_gender = sys::input( "user_gender", "" );
      $profile = self::get_user_profile( $user_id );

      $location = googlemaps::return_location( $user_country_code, $user_postal );
      if( !$location ) {
        db::open( TABLE_USERS );
          db::where( "user_id", $user_id );
          db::set( "user_registration_complete", 0 );
        if( !db::update() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/profiles/deactivate_registration/title" ),
            lang::phrase( "error/profiles/deactivate_registration/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      } else {
        $user_country = $location['country'];
        $user_city = $location['city'];
        $user_state = $location['state'];
        $user_postal = $location['postal'] != '' ? $location['postal'] : $user_postal;
        db::open( TABLE_USER_PROFILES );
          db::where( "user_id", $user_id );
          db::set( "user_postal", $user_postal );
          db::set( "user_country", $user_country );
          db::set( "user_country_code", $user_country_code );
          db::set( "user_state", $user_state );
          db::set( "user_city", $user_city );
          db::set( "user_gender", $user_gender );
        if( !db::update() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/profiles/complete_registration/title" ),
            lang::phrase( "error/profiles/complete_registration/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      }  
    }

    public static function query_get_user_information( $user_id ) {
      $profile = self::get_user_profile( $user_id );
      if( $profile ) {
        $simple_signatures = preferences::get( "profiles", "simple_signatures", "account" ) ? true : false;
        self::populate_profile( $profile, $simple_signatures );
      }
    }

    public static function query_get_multi_user_information( $user_ids, $path ) {
      $simple_signatures = preferences::get( "profiles", "simple_signatures", "account" ) ? true : false;
      db::open( TABLE_USER_PROFILES );
        db::where_in( "user_id", $user_ids );
        db::open( TABLE_USERS );
          db::link( "user_id" );
        db::close();
        db::open( TABLE_USER_TITLES, LEFT );
          db::select( "user_title", "user_title_name" );
          db::link( "user_title_id" );
      while( $row = db::result() ) {
        $user_path = str_replace( "%1", $row['user_id'], $path );
        action::resume( $user_path );
          self::populate_profile( $row, $simple_signatures );
        action::end();
      }
    }
    
    public static function hook_account_initialized() {
      $user_id = action::get( "user/id" ) ? action::get( "user/id" ) : ANONYMOUS;
      action::resume( "user/titles" );
        db::open( TABLE_USER_TITLE_ASSIGNMENTS );
          db::where( "user_id", $user_id );
          db::open( TABLE_USER_TITLES );
            db::link( "user_title_id" );
          db::close();
        while( $row = db::result() ) {
          action::start( "title" );
            action::add( "id", $row['user_title_id'] );
            action::add( "name", $row['user_title_name'] );
            action::add( "title", $row['user_title'] );
          action::end();
        }
      action::end();

      $profile_action = sys::input( "profile_action", false, SKIP_GET );
      $actions = array(
        "update_profile",
        "update_avatar",
        "add_link",
        "delete_link"
      );
      if( in_array( $profile_action, $actions ) ) {
        $evaluate = "self::$profile_action();";
        eval( $evaluate );
      }
    }

    private static function update_profile() {
      if( !action::get( "user/logged_in" ) ) {
        sys::message( USER_ERROR, lang::phrase( "error/account/must_be_logged_in/title" ), lang::phrase( "error/account/must_be_logged_in/body" ) );
      }
      $user_id = 0;
      if( !$user_id ) {
        $user_id = (int) sys::input( "user_id", 0 );
      }
      if( !$user_id ) {
        $user_id = (int) action::get( "user/id" );
      }
      $permissions = auth::test( "profiles", "" );
      if( $user_id == action::get( "user/id" ) && !$permissions["edit_own_profile"] ) {
        auth::deny( "profiles", "edit_own_profile" );
      } else if( $user_id != (int) action::get( "user/id" ) && !$permissions["edit_profiles"] ) {
        auth::deny( "profiles", "edit_profiles" );
      }
      $profile = self::get_user_profile( $user_id );
      $user_first_name = sys::input( "user_first_name", "" );
      $user_last_name = sys::input( "user_last_name", "" );
      $user_postal = sys::input( "user_postal", "" );
      $user_country_code = sys::input( "user_country", "" );
      $user_country = "";
      if( $user_country_code ) {
        $user_country = self::get_country_from_code( $user_country_code );
      }
      $user_biography = sys::input( "user_biography", "" );
      $user_biography = preg_replace( "/[^\w\d\s<>\/\-_&%\$#@\[\]\(\)\?\+\.\^\\\"'{}=,;:|]/si", "", $user_biography );
      $user_signature = sys::input( "user_signature", "" );
      $user_signature = preg_replace( "/[^\w\d\s<>\/\-_&%\$#@\[\]\(\)\?\+\.\^\\\"'{}=,;:|]/si", "", $user_signature );
      $user_signature = preg_replace( "/&([^&\s]*?);/", "", $user_signature );
      $user_website = sys::input( "user_website", "" );
      $user_title_id = sys::input( "user_title_id", "" );
      $user_gender = sys::input( "user_gender", "" );

      $user_guild_title = sys::input( "user_guild_title", "" );
      $user_guild_link = sys::input( "user_guild_link", "" );
      $user_primary_class = sys::input( "user_primary_class", "" );
      $user_secondary_class = sys::input( "user_secondary_class", "" );
      $user_primary_play_style = sys::input( "user_primary_play_style", "" );
      $user_secondary_play_style = sys::input( "user_secondary_play_style", "" );
      $user_group_size = sys::input( "user_group_size", "" );
      $user_mmos_played = sys::input( "user_mmos_played", "" );
      $user_mmo_most_played = sys::input( "user_mmo_most_played", "" );

      $user_gamerdna_explorer = sys::input( "user_gamerdna_explorer", "" );
      $user_gamerdna_killer = sys::input( "user_gamerdna_killer", "" );
      $user_gamerdna_achiever = sys::input( "user_gamerdna_achiever", "" );
      $user_gamerdna_socializer = sys::input( "user_gamerdna_socializer", "" );

      if( !$user_postal ) {
        action::resume( "profiles/profile_action" );
          action::add( "action", "update_profile" );
          action::add( "success", 0 );
          action::add( "message", lang::phrase( "error/profiles/missing_postal_code" ) );
        action::end();
        return;
      }
      if( !$user_country_code ) {
        action::resume( "profiles/profile_action" );
          action::add( "action", "update_profile" );
          action::add( "success", 0 );
          action::add( "message", lang::phrase( "error/profiles/missing_country" ) );
        action::end();
        return;
      }
      if( !$user_gender ) {
        action::resume( "profiles/profile_action" );
          action::add( "action", "update_profile" );
          action::add( "success", 0 );
          action::add( "message", lang::phrase( "error/profiles/missing_gender" ) );
        action::end();
        return;
      }
      $user_city = "";
      $user_state = "";
      if( $user_postal && $user_country_code && ( $user_postal != $profile['user_postal'] || $user_country_code != $profile['user_country_code'] ) ) {
        $location = googlemaps::return_location( $user_country_code, $user_postal );
        $user_city = $location['city'];
        $user_state = $location['state'];
      }

      $profile = self::get_user_profile( $user_id );
      db::open( TABLE_USER_PROFILES );
        db::where( "user_id", $user_id );
        db::set( "user_first_name", $user_first_name );
        db::set( "user_last_name", $user_last_name );
        db::set( "user_postal", $user_postal );
        if( $user_city ) {
          db::set( "user_city", $user_city );
        }
        if( $user_state ) {
          db::set( "user_state", $user_state );
        }
        db::set( "user_country", $user_country );
        db::set( "user_country_code", $user_country_code );
        db::set( "user_biography", $user_biography );
        if( $permissions['edit_signature'] ) {
          db::set( "user_signature", $user_signature );
        }
        db::set( "user_website", $user_website );
        db::set( "user_gender", $user_gender );
        db::set( "user_title_id", $user_title_id );
        db::set( "user_guild_title", $user_guild_title );
        db::set( "user_guild_link", $user_guild_link );
        db::set( "user_primary_class", $user_primary_class );
        db::set( "user_secondary_class", $user_secondary_class );
        db::set( "user_primary_play_style", $user_primary_play_style );
        db::set( "user_secondary_play_style", $user_secondary_play_style );
        db::set( "user_group_size", $user_group_size );
        db::set( "user_mmos_played", $user_mmos_played );
        db::set( "user_mmo_most_played", $user_mmo_most_played );
        db::set( "user_gamerdna_explorer", $user_gamerdna_explorer );
        db::set( "user_gamerdna_killer", $user_gamerdna_killer );
        db::set( "user_gamerdna_achiever", $user_gamerdna_achiever );
        db::set( "user_gamerdna_socializer", $user_gamerdna_socializer );
      if( !db::update() ) {
        echo "<!-- " . db::error() . " -->\n";
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/profiles/update_profile/title" ),
          lang::phrase( "error/profiles/update_profile/body" ),
          __FILE__, __LINE__, __FUNCTION__, __CLASS__
        );
      }
      action::resume( "profiles/profile_action" );
        action::add( "action", "update_profile" );
        action::add( "success", 1 );
        action::add( "message", lang::phrase( "profiles/update_profile/success" ) );
      action::end();
    }

    private static function update_avatar() {
      if( !action::get( "user/logged_in" ) ) {
        sys::message( USER_ERROR, lang::phrase( "error/account/must_be_logged_in/title" ), lang::phrase( "error/account/must_be_logged_in/body" ) );
      }
      $user_id = sys::input( "user_id", 0 );
      if( !$user_id && action::get( "user/logged_in" ) ) {
        $user_id = action::get( "user/id" );
      }
      if( !$user_id ) {
        action::resume( "profiles/profile_action" );
          action::add( "action", "update_avatar" );
          action::add( "success", 0 );
          action::add( "message", lang::phrase( "profiles/update_avatar/missing_user_id" ) );
        action::end();
        return;
      }
      if( $user_id == action::get( "user/id" ) && !auth::test( "profiles", "edit_avatar" ) ) {
        auth::deny( "profiles", "edit_avatar" );
      } else if( $user_id != action::get( "user/id" ) && !auth::test( "profiles", "edit_profiles" ) ) {
        auth::deny( "profiles", "edit_profiles" );
      }
      $user_avatar_file = sys::file( "user_avatar_file" );
      $file_uploaded = false;
      $avatar_filename = sys::input( "user_avatar_url", "" );
      if( $user_avatar_file && $user_avatar_file['tmp_name'] ) {
        $avatar_filename = $user_avatar_file['tmp_name'];
        $file_uploaded = true;
      }
      if( $avatar_filename ) {
        $save_avatar = true;
        $message = "";
        $info = getimagesize( $avatar_filename );
        $width = $info[0];
        $height = $info[1];
        $type = $info[2];
        if( $file_uploaded ) {
          $size = filesize( $avatar_filename ) / 1024;
        } else {
          $ch = curl_init( $avatar_filename );
          curl_setopt($ch, CURLOPT_NOBODY, true);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_HEADER, true);
          curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
          $data = curl_exec($ch);
          curl_close($ch);
          if( !$data ) {
            $save_avatar = false;
            $message = lang::phrase( "profiles/update_avatar/remote_connection_failed" );
          }
          $size = 0;
          if (preg_match('/Content-Length: (\d+)/', $data, $matches)) {
            $size = (int)$matches[1];
            $size /= 1024;
          }
          if( $save_avatar && !$size ) {
            $save_avatar = false;
            $message = lang::phrase( "profiles/update_avatar/invalid_remote_file" );
          }
        }
        $max_width = sys::setting( "profiles", "avatar_width" );
        $max_height = sys::setting( "profiles", "avatar_height" );
        $max_size = sys::setting( "profiles", "avatar_size" );
        if( $save_avatar && $width != $max_width ) {
          $save_avatar = false;
          $message = lang::phrase( "profiles/update_avatar/wrong_width", $max_width, $width );
        } else if( $save_avatar && $height != $max_height ) {
          $save_avatar = false;
          $message = lang::phrase( "profiles/update_avatar/wrong_height", $max_height, $height );
        } else if( $save_avatar && $size > $max_size ) {
          $save_avatar = false;
          $message = lang::phrase( "profiles/update_avatar/file_too_big", $max_size, $size );
        } else if( $save_avatar && $type != IMAGETYPE_JPEG  && $type != IMAGETYPE_GIF && $type != IMAGETYPE_PNG ) {
          $save_avatar = false;
          $message = lang::phrase( "profiles/update_avatar/wrong_type" );
        }
        action::resume( "profiles/profile_action" );
          action::add( "action", "update_avatar" );
          $user_id = action::get( "user/id" );
          if( $save_avatar ) {
            db::open( TABLE_USER_AVATARS );
              db::set( "user_id", $user_id );
              db::set( "user_avatar_date", "NOW()", false );
            if( !db::insert() ) {
              sys::message(
                SYSTEM_ERROR,
                lang::phrase( "error/profiles/create_avatar/title" ),
                lang::phrase( "error/profiles/create_avatar/body" ),
                __FILE__, __LINE__, __FUNCTION__, __CLASS__
              );
            }
            $user_avatar_id = db::id();
            if( !file_exists( "uploads/avatars/" . $user_id ) ) {
              mkdir( "uploads/avatars/" . $user_id, 0777 );
            }
            if( $type == IMAGETYPE_JPEG ) {
              $source_image = imagecreatefromjpeg( $avatar_filename );
            } else if( $type == IMAGETYPE_PNG ) {
              $source_image = imagecreatefrompng( $avatar_filename );
            } else if( $type == IMAGETYPE_GIF ) {
              $source_image = imagecreatefromgif( $avatar_filename );
            }
            $new_image = imagecreatetruecolor( $width, $height );
            imagecopyresampled( $new_image, $source_image, 0, 0, 0, 0, $width, $height, $width, $height );
            imagejpeg( $new_image, "uploads/avatars/" . $user_id . "/avatar_" . $user_avatar_id . ".jpg", 90 );
            $profile = self::get_user_profile( $user_id );
            db::open( TABLE_USER_PROFILES );
              db::where( "user_id", $user_id );
              db::set( "user_avatar_id", $user_avatar_id );
            if( !db::update() ) {
              sys::message(
                SYSTEM_ERROR,
                lang::phrase( "error/profiles/update_avatar/title" ),
                lang::phrase( "error/profiles/update_avatar/body" ),
                __FILE__, __LINE__, __FUNCTION__, __CLASS__
              );
            }
            action::add( "success", 1 );
            action::add( "message", lang::phrase( "profiles/update_avatar/success" ) );
          } else {
            action::add( "success", 0 );
            action::add( "message", $message );
          }
        action::end();
      } else {
        action::resume( "profiles/profile_action" );
          action::add( "action", "update_avatar" );
          action::add( "success", 0 );
          action::add( "message", lang::phrase( "profiles/update_avatar/missing_file" ) );
        action::end();
      }
    }

    private static function add_link() {
      if( !action::get( "user/logged_in" ) ) {
        sys::message( USER_ERROR, lang::phrase( "error/account/must_be_logged_in/title" ), lang::phrase( "error/account/must_be_logged_in/body" ) );
      }
      $user_id = (int) sys::input( "user_id", 0 );
      if( !$user_id && action::get( "user/logged_in" ) ) {
        $user_id = action::get( "user/id" );
      }
      if( !$user_id ) {
        action::resume( "profiles/profile_action" );
          action::add( "action", "add_link" );
          action::add( "success", 0 );
          action::add( "message", lang::phrase( "errors/profiles/missing_user_id" ) );
        action::end();
        return;
      }
      if( $user_id == action::get( "user/id" ) && !auth::test( "profiles", "edit_own_profile" ) ) {
        auth::deny( "profiles", "edit_own_profile" );
      } else if( $user_id != action::get( "user/id" ) && !auth::test( "profiles", "edit_profiles" ) ) {
        auth::deny( "profiles", "edit_profiles" );
      }
      $user_link_type = sys::input( "user_link_type", "" );
      $user_link_tag = sys::input( "user_link_tag", "" );
      db::open( TABLE_USER_LINKS );
        db::select_as( "link_count" );
        db::select_count( "user_link_id" );
        db::where( "user_id", $user_id );
        db::group( "user_id" );
      $link_count = db::result();
      db::clear_result();
      if( $link_count && $link_count['link_count'] > sys::setting( "profiles", "maximum_links" ) ) {
        action::resume( "profiles/profile_action" );
          action::add( "action", "add_link" );
          action::add( "success", 0 );
          action::add( "message", lang::phrase( "profiles/add_link/too_many" ) );
        action::end();
        return;
      }
        
      db::open( TABLE_USER_LINKS );
        db::set( "user_id", $user_id );
        db::set( "user_link_type", $user_link_type );
        db::set( "user_link_tag", $user_link_tag );
      if( !db::insert() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/profiles/add_link/title" ),
          lang::phrase( "error/profiles/add_link/body", db::error() ),
          __FILE__, __LINE__, __FUNCTION__, __CLASS__
        );
      }
      action::resume( "profiles/profile_action" );
        action::add( "action", "add_link" );
        action::add( "success", 1 );
        action::add( "message", lang::phrase( "profiles/add_link/success" ) );
      action::end();
    }

    private static function delete_link() {
      if( !action::get( "user/logged_in" ) ) {
        sys::message( USER_ERROR, lang::phrase( "error/account/must_be_logged_in/title" ), lang::phrase( "error/account/must_be_logged_in/body" ) );
      }
      $user_id = (int) sys::input( "user_id", 0 );
      if( !$user_id && action::get( "user/logged_in" ) ) {
        $user_id = action::get( "user/id" );
      }
      if( !$user_id ) {
        action::resume( "profiles/profile_action" );
          action::add( "action", "delete_link" );
          action::add( "success", 0 );
          action::add( "message", lang::phrase( "errors/profiles/missing_user_id" ) );
        action::end();
        return;
      }
      if( $user_id == action::get( "user/id" ) && !auth::test( "profiles", "edit_own_profile" ) ) {
        auth::deny( "profiles", "edit_own_profile" );
      } else if( $user_id != action::get( "user/id" ) && !auth::test( "profiles", "edit_profiles" ) ) {
        auth::deny( "profiles", "edit_profiles" );
      }
      $user_link_id = sys::input( "user_link_id", 0 );
      db::open( TABLE_USER_LINKS );
        db::where( "user_link_id", $user_link_id );
        db::where( "user_id", $user_id );
      if( !db::delete() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/profiles/delete_link/title" ),
          lang::phrase( "error/profiles/delete_link/body" ),
          __FILE__, __LINE__, __FUNCTION__, __CLASS__
        );
      }
      action::resume( "profiles/profile_action" );
        action::add( "action", "delete_link" );
        action::add( "success", 1 );
        action::add( "message", lang::phrase( "profiles/delete_link/success" ) );
      action::end();
    }

    public static function list_links() {
      $user_id = 0;
      $user_name = sys::input( "user_name", "" );
      if( $user_name ) {
        db::open( TABLE_USERS );
          db::where( "user_name", $user_name );
        $user = db::result();
        db::clear_result();
        if( $user ) {
          $user_id = $user['user_id'];
        }
      }
      if( !$user_id ) {
        $user_id = sys::input( "user_id", 0 );
      }
      if( !$user_id && (int) action::get( "user/logged_in" ) ) {
        $user_id = action::get( "user/id" );
      }
      if( $user_id ) {
        action::resume( "profiles/link_list" );
          db::open( TABLE_USER_LINKS );
            db::where( "user_id", $user_id );
          while( $row = db::result() ) {
            action::start( "link" );
              action::add( "id", $row['user_link_id'] );
              action::add( "type", $row['user_link_type'] );
              action::add( "tag", $row['user_link_tag'] );
              action::add( "title", lang::phrase( "profiles/link_types/" . $row['user_link_type'] ) );
            action::end();
          }
        action::end();
      }
    }

    public static function list_countries() {
      action::resume( "profiles" );
        action::start( "country_list" );
          $countries = simplexml_load_file( EXTENSIONS_DIR . "/profiles/countries.xml" );
          foreach( $countries as $country ) {
            action::start( "country" );
              $name = ucwords( strtolower( $country->name ) );
              action::add( "name", $name );
              action::add( "code2", $country->alpha_2_code );
            action::end();
          }
        action::end();
      action::end();
    }

    public static function loop_profiles( $path, $targets ) {
      if( count( $targets ) == 0 ) {
        $total_items = action::total( $path );
        for( $i = 0; $i < $total_items; $i++ ) {
          $targets[] = action::get( $path, $i );
        }
      }

      $simple_signatures = preferences::get( "profiles", "simple_signatures", "account" ) ? true : false;
      action::resume( "profiles" );
        action::start( "profile_list" );
          db::open( TABLE_USER_PROFILES );
            db::where_in( "user_id", $targets );
            db::open( TABLE_USERS );
              db::select( "user_name" );
              db::link( "user_id" );
            db::close();
            db::open( TABLE_USER_TITLES, LEFT );
              db::link( "user_title_id" );
            db::close();
            db::open( TABLE_USER_AVATARS, LEFT );
              db::select( "user_avatar_id", "user_avatar_date", "user_avatar_public", "user_avatar_cdn_enabled" );
              db::link( "user_avatar_id" );
            db::close();
          while( $row = db::result() ) {
            action::start( "profile" );
              self::populate_profile( $row, $simple_signatures );
            action::end();
          }
        action::end();
      action::end();
    }

    private static function populate_profile( $profile, $simple_signatures ) {      
      action::add( "id", $profile['user_id'] );
      action::add( "name", $profile['user_name'] );
      action::add( "signature", sys::clean_xml( $profile['user_signature'] ) );
      action::add( "formatted_signature", self::parse_signature( sys::clean_xml( $profile['user_signature'] ), $simple_signatures ) );
      action::add( "biography", $profile['user_biography'] );
      action::add( "formatted_biography", self::parse_biography( $profile['user_biography'] ) );
      action::add( "avatar", $profile['user_avatar_id'] );
      action::add( "website", $profile['user_website'] );
      action::start( "guild" );
        action::add( "title", $profile['user_guild_title'] );
        action::add( "link", $profile['user_guild_link'] );
      action::end();
      action::start( "class" );
        action::add( "primary", $profile['user_primary_class'] );
        action::add( "secondary", $profile['user_secondary_class'] );
      action::end();
      action::start( "play_style" );
        action::add( "primary", $profile['user_primary_play_style'] );
        action::add( "secondary", $profile['user_secondary_play_style'] );
      action::end();
      action::add( "group_size", $profile['user_group_size'] );
      action::add( "mmos_played", $profile['user_mmos_played'] );
      action::add( "mmo_most_played", $profile['user_mmo_most_played'] );
      action::start( "gamerdna" );
        action::add( "explorer", $profile['user_gamerdna_explorer'] );
        action::add( "killer", $profile['user_gamerdna_killer'] );
        action::add( "achiever", $profile['user_gamerdna_achiever'] );
        action::add( "socializer", $profile['user_gamerdna_socializer'] );
      action::end();
      if( (int)$profile['user_id'] == (int)action::get( "user/id" ) ) {
        action::add( "disable_avatars", preferences::get( "profiles", "disable_avatars", "account", $profile['user_id'] ) ? 1 : 0 );
      }
      action::add( "show_name", preferences::get( "profiles", "show_name", "account", $profile['user_id'] ) ? 1 : 0 );
      if( (int)$profile['user_id'] == (int)action::get( "user/id" ) || preferences::get( "profiles", "show_name", "account", $profile['user_id'] ) ) {
        action::add( "first_name", $profile['user_first_name'] );
        action::add( "last_name", $profile['user_last_name'] );
      }
      action::add( "show_gender", preferences::get( "profiles", "show_gender", "account", $profile['user_id'] ) ? 1 : 0 );
      if( (int)$profile['user_id'] == (int)action::get( "user/id" ) || preferences::get( "profiles", "show_gender", "account", $profile['user_id'] ) ) {
        action::start( "gender" );
          action::add( "code", $profile['user_gender'] );
          action::add( "title", lang::phrase( "profiles/gender/code_" . strtolower( $profile['user_gender'] ) ) );
        action::end();
      }
      action::add( "show_location", preferences::get( "profiles", "show_location", "account", $profile['user_id'] ) ? 1 : 0 );
      if( (int)$profile['user_id'] == (int)action::get( "user/id" ) || preferences::get( "profiles", "show_location", "account", $profile['user_id'] ) ) {
        action::add( "city", $profile['user_city'] );
        action::add( "state", $profile['user_state'] );
        action::add( "postal", $profile['user_postal'] );
        action::start( "country" );
          action::add( "code", $profile['user_country_code'] );
          action::add( "name", $profile['user_country'] );
        action::end();
      }
      if( isset( $profile['user_title_id'] ) && $profile['user_title_id'] ) {
        action::start( "title" );
          action::add( "name", $profile['user_title_name'] );
          action::add( "title", $profile['user_title'] );
          action::add( "id", $profile['user_title_id'] );
        action::end();
      }
    }

    private static function get_country_from_code( $code ) {
      $countries = simplexml_load_file( EXTENSIONS_DIR . "/profiles/countries.xml" );
      foreach( $countries as $country ) {
        if( $country->alpha_2_code == $code ) {
          $name = ucwords( strtolower( $country->name ) );
          return $name;
        }
      }
      return "Not found";
    }

    public static function get_profile() {
      if( !auth::test( "profiles", "view_profiles" ) ) {
        auth::deny( "profiles", "view_profiles" );
      }
      $user_id = 0;
      $user_name = sys::input( "user_name", "" );
      if( $user_name ) {
        db::open( TABLE_USERS );
          db::where( "user_name", $user_name );
        $user = db::result();
        db::clear_result();
        if( !$user ) {
          sys::message(
            NOTFOUND_ERROR,
            lang::phrase( "error/profiles/get_profile/not_found/title" ),
            lang::phrase( "error/profiles/get_profile/not_found/body" )
          );
        }
        $user_id = $user['user_id'];
      }
      if( !$user_id ) {
        $user_id = sys::input( "user_id", 0 );
      }
      if( !$user_id && (int)action::get( "user/logged_in" ) == 1 ) {
        $user_id = action::get( "user/id" );
      }
      if( !$user_id ) {
        sys::message( USER_ERROR, lang::phrase( "error/profile/must_be_logged_in/title" ), lang::phrase( "error/profile/must_be_logged_in/body" ) );
      }
      $profile = self::get_user_profile( $user_id );
      $permissions = auth::test( "profiles", "" );
      action::resume( "profiles" );
        action::start( "permissions" );
          foreach( $permissions as $key => $val ) {
            action::add( $key, $val );
          }
        action::end();
        action::start( "profile" );
          sys::query( "get_user_information", $user_id );
        action::end();
      action::end();
    }

    public static function get_avatar( $output = true ) {
      $max_width = (int)sys::setting( "profiles", "avatar_width" );
      $max_height = (int)sys::setting( "profiles", "avatar_height" );
      $user_avatar_id = sys::input( "user_avatar_id", 0 );
      db::open( TABLE_USER_AVATARS );
        db::where( "user_avatar_id", $user_avatar_id );
      $avatar = db::result();
      db::clear_result();
      $width = sys::input( "width", $max_width );
      $height = sys::input( "height", $max_height );
      $filename = "uploads/avatars/" . $avatar['user_id'] . "/avatar_" . $avatar['user_avatar_id'] . ".jpg";
      $new_filename = "uploads/avatars/" . $avatar['user_id'] . "/avatar_";
      if( $width != $max_width || $height != $max_height ) {
        $new_filename .= $width . "x" . $height . "_";
      }
      $new_filename .= $avatar['user_avatar_id'] . ".jpg";
      $create_new = false;
      if( !$output ) {
        $output = sys::input( "output", false );
      }
      if( !file_exists( $new_filename ) ) {
        $create_new = true;
      }
      if( $create_new ) {
        $source_image = imagecreatefromjpeg( $filename );
        $new_image = imagecreatetruecolor( $width, $height );					
        imagecopyresampled( $new_image, $source_image, 0, 0, 0, 0, $width, $height, $max_width, $max_height );
        imagejpeg( $new_image, $new_filename, 90 );
        if( $output ) {
          header('Content-type: image/jpeg');
          imagejpeg( $new_image, '', 90 );
        }
        imagedestroy( $new_image );
        imagedestroy( $source_image );
      } else if( $output ) {
        header('Content-type: image/jpeg');
        $source_image = @fopen( $new_filename, 'r' );
				echo @fread( $source_image, filesize( $new_filename ) );
				@fclose( $source_image );
      }
      if( $output ) {
        exit();
      }
    }

    public static function get_user_profile( $user_id ) {
      db::open( TABLE_USER_PROFILES );
        db::where( "user_id", $user_id );
        db::open( TABLE_USERS );
          db::link( "user_id" );
        db::close();
        db::open( TABLE_USER_TITLES, LEFT );
          db::link( "user_title_id" );
        db::close();
        db::open( TABLE_USERS, LEFT );
            db::link( "user_id" );
      $profile = db::result();
      db::clear_result();
      if( !$profile ) {
        db::open( TABLE_USER_PROFILES );
          db::set( "user_id", $user_id );
        if( !db::insert() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/profiles/create_user_profile/title" ),
            lang::phrase( "error/profiles/create_user_profile/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        db::open( TABLE_USER_PROFILES );
          db::where( "user_id", $user_id );
          db::open( TABLE_USERS );
            db::link( "user_id" );
        $profile = db::result();
        db::clear_result();
      }
      return $profile;
    }

    public static function parse_signature( $text, $simple = false ) {
      if( $simple ) {
        return format::process( EXTENSIONS_DIR . "/profiles", "simple_formatting", $text );
      } else {
        $text = format::process( EXTENSIONS_DIR . "/profiles", "signature_formatting", $text );
        $text = preg_replace( "/([^=\"\'>])((https?|ftp|gopher|telnet|file|notes|ms-help):((\/\/)|(\\\\))+[^<>\"\s]+)/", "$1<a rel=\"nofollow\" target=\"_blank\" href=\"$2\">$2</a>", ' ' . $text );
        $text = substr( $text, 1 );
        return $text;
      }
    }

    public static function parse_biography( $text ) {
      return format::process( EXTENSIONS_DIR . "/profiles", "biography_formatting", $text );
    }

  }

?>
