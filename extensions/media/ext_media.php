<?php

/*
Copyright © 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/

  define( "MEDIA_TAG", "media" );

  define( "MEDIA_QUEUE_ASSIGNMENT", 1 );
  define( "MEDIA_QUEUE_FLAG", 2 );

  class media {

    public static function hook_account_initialized() {
      $media_action = sys::input( "media_action", false, SKIP_GET );
      $actions = array(
        "add_tag",
        "delete_tag",
        "add_media",
        "edit_media",
        "delete_media",
        "edit_gallery",
        "set_featured",
        "delete_gallery",
        "initiate_attach",
        "complete_attach",
        "complete_detach",
        "detach_media",
        "delete_queue",
        "generate_thumbnail"
      );
      if( in_array( $media_action, $actions ) ) {
        $evaluate = "self::$media_action();";
        eval( $evaluate );
      }
    }

    public static function query_get_extension_object( $target, $type ) {
      if( $type == "media_gallery" ) {
        db::open( TABLE_MEDIA_GALLERIES );
          db::select(
            "media_gallery_id",
            "media_gallery_title",
            "media_gallery_name",
            "media_gallery_updated",
            "media_gallery_updated_by",
            "media_gallery_created",
            "media_gallery_description",
            "media_gallery_count",
            "media_gallery_private"
          );
          db::where( "media_gallery_id", $target );
          db::open( TABLE_MEDIA, LEFT );
            db::link( "media_id" );
          db::close();
        $media_gallery = db::result();
        db::clear_result();
        if( $media_gallery ) {
          action::start( "media_gallery" );
            action::add( "id", $media_gallery['media_gallery_id'] );
            action::add( "name", $media_gallery['media_gallery_name'] );
            action::add( "title", $media_gallery['media_gallery_title'] );
            action::add( "description", $media_gallery['media_gallery_description'] );
            action::add( "media_count", $media_gallery['media_gallery_count'] );
            action::add( "private", $media_gallery['media_gallery_private'] );
            action::start( "created" );
              action::add( "datetime", $media_gallery['media_gallery_created'] );
              $timestamp = strtotime( $media_gallery['media_gallery_created'] );
              action::add( "period", sys::create_duration( $timestamp, time() ) );
              $timestamp += ( 60 * 60 ) * sys::timezone();
              action::add( "altered_datetime", sys::create_datetime( $timestamp ) );
            action::end();
            action::start( "updated" );
              action::add( "datetime", $media_gallery['media_gallery_updated'] );
              $timestamp = strtotime( $media_gallery['media_gallery_updated'] );
              action::add( "period", sys::create_duration( $timestamp, time() ) );
              $timestamp += ( 60 * 60 ) * sys::timezone();
              action::add( "altered_datetime", sys::create_datetime( $timestamp ) );
            action::end();
            if( $media_gallery['media_id'] ) {
              action::start( "featured" );
                action::add( "id", $media_gallery['media_id'] );
                action::add( "cdn_enabled", $media_gallery['media_cdn_enabled'] );
                action::add( "width", $media_gallery['media_width'] );
                action::add( "height", $media_gallery['media_height'] );
                action::start( "created" );
                  $timestamp = strtotime( $media_gallery['media_created'] );
                  action::add( "datetime", sys::create_datetime( $timestamp ) );
                action::end();
                action::start( "children" );
                  self::get_media_children( $media_gallery['media_id'] );
                action::end();
              action::end();
            }
          action::end();
        }
      }
    }

    private static function add_tag() {
      sys::check_return_page();
      $media_id = sys::input( "media_id" );
      db::open( TABLE_MEDIA );
        db::where( "media_id", $media_id );
      $media = db::result();
      db::clear_result();
      if( !auth::test( "media", "add_tags" ) ) {
        if( $media['user_id'] != action::get( "user/id" ) || !auth::test( "media", "add_tags_to_own" ) ) {
          auth::deny( "media", "add_tags" );
        }
      }
      $tag_title = sys::input( "tag_title", "" );
      if( !$tag_title ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/media/actions/add_tag/invalid_tag_title/title" ),
          lang::phrase( "error/media/actions/add_tag/invalid_tag_title/body" )
        );
      }
      $tag_name = sys::create_tag( $tag_title );      
      db::open( TABLE_TAGS );
        db::where( "tag_type", MEDIA_TAG );
        db::where( "tag_target", $media_id );
        db::where( "tag_name", $tag_name );
      $tag = db::result();
      db::clear_result();
      if( $tag ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/media/actions/add_tag/tag_already_exists/title" ),
          lang::phrase( "error/media/actions/add_tag/tag_already_exists/body" )
        );
      }
      db::open( TABLE_TAGS );
        db::set( "tag_type", MEDIA_TAG );
        db::set( "tag_title", $tag_title );
        db::set( "tag_name", $tag_name );
        db::set( "tag_target", $media_id );
      if( !db::insert() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/media/actions/add_tag/could_not_add/title" ),
          lang::phrase( "error/media/actions/add_tag/could_not_add/body" ),
          __FILE__, __LINE__, __FUNCTION__, __CLASS__
        );
      }
      action::resume( "media/actions" );
        action::start( "action" );
          action::add( "title", lang::phrase( "media/actions/add_tag/title" ) );
          action::add( "name", "add_tag" );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "media/actions/add_tag/success/body" ) );
        action::end();
      action::end();
      if( action::get( "request/return_page" ) ) {
        sys::message(
          USER_MESSAGE,
          lang::phrase( "media/actions/add_tag/success/title" ),
          lang::phrase( "media/actions/add_tag/success/body" )
        );
      }
    }

    private static function delete_tag() {
      sys::check_return_page();
      $media_id = sys::input( "media_id" );
      db::open( TABLE_MEDIA );
        db::where( "media_id", $media_id );
      $media = db::result();
      db::clear_result();
      if( !auth::test( "media", "delete_tags" ) ) {
        if( $media['user_id'] != action::get( "user/id" ) || !auth::test( "media", "delete_tags_from_own" ) ) {
          auth::deny( "media", "delete_tags" );
        }
      }
      $tag_name = sys::input( "tag_name", false );
      if( !$tag_name ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/media/actions/delete_tag/invalid_tag_name/title" ),
          lang::phrase( "error/media/actions/delete_tag/invalid_tag_name/body" )
        );
      }
      db::open( TABLE_TAGS );
        db::where( "tag_type", MEDIA_TAG );
        db::where( "tag_name", $tag_name );
        db::where( "tag_target", $media_id );
      if( !db::delete() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/media/actions/delete_tag/could_not_delete/title" ),
          lang::phrase( "error/media/actions/delete_tag/could_not_delete/body" ),
          __FILE__, __LINE__, __FUNCTION__, __CLASS__
        );
      }
      action::resume( "media/actions" );
        action::start( "action" );
          action::add( "title", lang::phrase( "media/actions/delete_tag/title" ) );
          action::add( "name", "delete_tag" );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "media/actions/delete_tag/success/body" ) );
        action::end();
      action::end();
      if( action::get( "request/return_page" ) ) {
        sys::message(
          USER_MESSAGE,
          lang::phrase( "media/actions/delete_tag/success/title" ),
          lang::phrase( "media/actions/delete_tag/success/body" )
        );
      }
    }

    private static function add_media() {
      if( !auth::test( "media", "add_media" ) ) {
        auth::deny( "media", "add_media" );
      }
      sys::check_return_page();
      $media_gallery_id = sys::input( "media_gallery_id", 0 );
      if( $media_gallery_id ) {
        db::open( TABLE_MEDIA_GALLERIES );
          db::where( "media_gallery_id", $media_gallery_id );
          db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS, LEFT );
            db::select( "media_order" );
            db::link( "media_gallery_id" );
            db::order( "media_order", "DESC" );
        $media_gallery = db::result();
        db::clear_result();
        if( !$media_gallery ) {
          sys::message(
            USER_ERROR,
            lang::phrase( "error/media/actions/edit_media/invalid_media_gallery_id/title" ),
            lang::phrase( "error/media/actions/edit_media/invalid_media_gallery_id/body" )
          );
        }
      }
      $total_media = sys::input( "total_media", 0 );
      $current_date = gmdate( "Y/m/d H:i:s" );
      $media_order = 0;
      for( $i = 0; $i < $total_media; $i++ ) {
        $media_file = sys::file( "media_file_" . ( $i + 1 ) );
        if( $media_file && isset( $media_file['name'] ) && $media_file['name'] ) {
          $media_title = sys::input( "media_title_" . ( $i + 1 ), $media_file['name'] );
          $media_description = sys::input( "media_description_" . ( $i + 1 ), "" );
          $media_private = sys::input( "media_private_" . ( $i + 1 ), 0 );
          $media_type = sys::input( "media_type_" . ( $i + 1 ), "image" );
          $media_comments_enabled = sys::input( "media_comments_enabled_" . ( $i + 1 ), 0 );
          db::open( TABLE_MEDIA );
            db::set( "user_id", action::get( "user/id" ) );
            db::set( "media_title", $media_title );
            db::set( "media_name", sys::create_tag( $media_title ) );
            db::set( "media_description", $media_description );
            db::set( "media_type", $media_type );
            db::set( "media_created", $current_date );
            db::set( "media_updated", $current_date );
            db::set( "media_updated_by", action::get( "user/id" ) );
            db::set( "media_verified", 0 );
            db::set( "media_comments_enabled", $media_comments_enabled );
          if( !db::insert() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/media/actions/add_media/could_not_create_media/title" ),
              lang::phrase( "error/media/actions/add_media/could_not_create_media/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
          $media_id = db::id();
          $new_filename = sys::random_chars(12) . "." . $media_file['name'];
          sys::copy_file( "media_file_" . ( $i + 1 ), "uploads/media/temp/", $new_filename );
          self::upload_media( $media_id, $media_id, $media_type, "uploads/media/temp/" . $new_filename, $media_file['name'] );
          if( $media_gallery_id ) {
            db::open( TABLE_MEDIA_GALLERIES );
              db::where( "media_gallery_id", $media_gallery_id );
            $media_gallery = db::result();
            if( !auth::test( "media", "attach_media" ) ) {
              if( $media_gallery['user_id'] != action::get( "user/id" ) || !auth::test( "media", "attach_own_media_to_own" ) ) {
                auth::deny( "media", "attach_media" );
              }
            }
            if( !$media_order ) {
              if( !$media_gallery['media_order'] ) {
                $media_order = 1;
              } else {
                $media_order = $media_gallery['media_order'] + 1;
              }
            }
            db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS );
              db::set( "media_gallery_id", $media_gallery_id );
              db::set( "media_id", $media_id );
              db::set( "media_added", $current_date );
              db::set( "media_order", $media_order );
            if( !db::insert() ) {
              sys::message(
                SYSTEM_ERROR,
                lang::phrase( "error/media/actions/add_media/add_to_gallery/title" ),
                lang::phrase( "error/media/actions/add_media/add_to_gallery/body" ),
                __FILE__, __LINE__, __FUNCTION__, __CLASS__
              );
            }
            $media_order++;
            if( !$media_gallery['media_id'] ) {
              db::open( TABLE_MEDIA_GALLERIES );
                db::where( "media_gallery_id", $media_gallery_id );
                db::set( "media_id", $media_id );
              if( !db::update() ) {
                sys::message(
                  SYSTEM_ERROR,
                  lang::phrase( "error/media/actions/add_media/update_gallery_featured/title" ),
                  lang::phrase( "error/media/actions/add_media/update_gallery_featured/body" ),
                  __FILE__, __LINE__, __FUNCTION__, __CLASS__
                );
              }
              $media_gallery['media_id'] = $media_id;
            }
          }
        }
      }
      if( $media_gallery_id ) {
        self::update_media_gallery( $media_gallery_id );
      }
      action::resume( "media/actions" );
        action::start( "action" );
          action::add( "title", lang::phrase( "media/actions/add_media/title" ) );
          action::add( "name", "add_media" );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "media/actions/add_media/success/body" ) );
        action::end();
      action::end();
      if( action::get( "request/return_page" ) ) {
        sys::message(
          USER_MESSAGE,
          lang::phrase( "media/actions/add_media/success/title" ),
          lang::phrase( "media/actions/add_media/success/body" )
        );
      }
    }

    private static function edit_media() {
      sys::check_return_page();
      $media_id = sys::input( "media_id", 0 );
      $media = null;
      if( $media_id ) {
        db::open( TABLE_MEDIA );
          db::where( "media_id", $media_id );
        $media = db::result();
        db::clear_result();
        if( !$media ) {
          sys::message(
            USER_ERROR,
            lang::phrase( "error/media/actions/edit_media/invalid_media_id/title" ),
            lang::phrase( "error/media/actions/edit_media/invalid_media_id/body" )
          );
        }
        if( !auth::test( "media", "edit_media" ) ) {
          if( $media_gallery['user_id'] != action::get( "user/id" ) || !auth::test( "media", "edit_own_media" ) ) {
            sys::message(
              USER_ERROR,
              lang::phrase( "authentication/media/edit_media/denied/title" ),
              lang::phrase( "authentication/media/edit_media/denied/body" )
            );
          }
        }
      } else {
        if( !auth::test( "media", "add_media" ) ) {
          sys::message(
            USER_ERROR,
            lang::phrase( "authentication/media/add_media/denied/title" ),
            lang::phrase( "authentication/media/add_media/denied/body" )
          );
        }
      }
      $media_gallery_id = sys::input( "media_gallery_id", 0 );
      $media_gallery = null;
      if( $media_gallery_id ) {
        db::open( TABLE_MEDIA_GALLERIES );
          db::where( "media_gallery_id", $media_gallery_id );
        $media_gallery = db::result();
        db::clear_result();
        if( !$media_gallery ) {
          sys::message(
            USER_ERROR,
            lang::phrase( "error/media/actions/edit_media/invalid_media_gallery_id/title" ),
            lang::phrase( "error/media/actions/edit_media/invalid_media_gallery_id/body" )
          );
        }
        if( !auth::test( "media", "edit_galleries" ) ) {
          if( $media_gallery['user_id'] == action::get( "user/id" ) && !auth::test( "media", "edit_own_galleries" ) ) {
            sys::message(
              USER_ERROR,
              lang::phrase( "authentication/media/edit_galleries/denied/title" ),
              lang::phrase( "authentication/media/edit_galleries/denied/body" )
            );
          }
        }
      }
      $media_title = sys::input( "media_title", "" );
      $media_name = sys::input( "media_name", "" );
      if( !$media_name ) {
        $media_name = sys::create_tag( $media_title );
      }
      $media_description = sys::input( "media_description", "" );
      $media_description = preg_replace( "/[^\w\d\s<>\/\-_&%\$#@\[\]\(\)\?\+\.\^\\\"'{}=,;:|]/si", "", $media_description );
      $media_type = sys::input( "media_type", "" );
      $current_date = gmdate( "Y/m/d H:i:s" );
      $media_file = sys::file( "media_file" );
      $media_parent = sys::input( "media_parent", -1 );
      $media_comments_enabled = sys::input( "media_comments_enabled", false ) ? 1 : 0;
      $media_private = sys::input( "media_private", 0 );
      if( !$media_id && !$media_file ) {
        sys::message( 
          USER_ERROR,
          lang::phrase( "error/media/actions/edit_media/invalid_file/title" ),
          lang::phrase( "error/media/actions/edit_media/invalid_file/body" )
        );
      }
      $media_created = false;
      db::open( TABLE_MEDIA );
        if( $media_file ) {
          db::set( "media_verified", 0 );
        }
        db::set( "media_title", $media_title );
        db::set( "media_name", $media_name );
        db::set( "media_description", $media_description );
        db::set( "media_type", $media_type );
        db::set( "media_comments_enabled", $media_comments_enabled );
        if( $media_parent >= 0 ) {
          db::set( "media_parent", $media_parent );
        }
        db::set( "media_updated", $current_date );
        db::set( "media_private", $media_private );
      if( !$media_id ) {
        db::set( "media_created", $current_date );
        db::set( "user_id", action::get( "user/id" ) );
        if( !db::insert() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/media/actions/edit_media/add_new_media/title" ),
            lang::phrase( "error/media/actions/edit_media/add_new_media/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        $media_id = db::id();
        $media_created = true;
      } else {
        db::where( "media_id", $media_id );
        if( !db::update() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/media/actions/edit_media/update_media_info/title" ),
            lang::phrase( "error/media/actions/edit_media/update_media_info/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }        
      }
      if( $media_file && isset( $media_file['name'] ) && $media_file['name'] ) {
        if( !$media_created ) {
          db::open( TABLE_MEDIA );
            db::where( "media_id", $media_id );
            db::set( "media_verified", 0 );
          if( !db::update() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/media/actions/edit_media/unverify_media/title" ),
              lang::phrase( "error/media/actions/edit_media/unverify_media/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
          self::delete_media_file( $media['media_id'], $media['media_filename'], $media['media_cdn_enabled'] );
          self::delete_media_children( $media['media_id'] );
        }
        $new_filename = sys::random_chars(12) . "." . $media_file['name'];
        sys::copy_file( "media_file", "uploads/media/temp/", $new_filename );
        self::upload_media( $media_id, $media_id, $media_type, "uploads/media/temp/" . $new_filename, $media_file['name'], true );
      }
      if( $media_created && $media_gallery_id ) {
        db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS );
          db::set( "media_gallery_id", $media_gallery_id );
          db::set( "media_id", $media_id );
          db::set( "media_added", $current_date );
        if( !db::insert() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/media/actions/edit_media/add_to_gallery/title" ),
            lang::phrase( "error/media/actions/edit_media/add_to_gallery/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        self::update_media_gallery( $media_gallery_id );
      }
      action::resume( "media/actions" );
        action::start( "action" );
          action::add( "title", lang::phrase( "media/actions/edit_media/title" ) );
          action::add( "name", "edit_media" );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "media/actions/edit_media/success/body" ) );
        action::end();
      action::end();
      if( action::get( "request/return_page" ) ) {
        if( $media_created ) {
          $return_page = action::get( "request/return_page" );
          $return_page = str_replace( "[media_id]", $media_id, $return_page );
          sys::replace_return_page( $return_page );
        }
        sys::message(
          USER_MESSAGE,
          lang::phrase( "media/actions/edit_media/success/title" ),
          lang::phrase( "media/actions/edit_media/success/body" )
        );
      }
    }

    private static function upload_media( $media_id, $verify_id, $media_type, $old_file, $new_file, $include_id = true ) {
      if( !file_exists( $old_file ) ) {
        sys::message( 
          USER_ERROR,
          lang::phrase( "error/media/upload_media/invalid_file/title" ),
          lang::phrase( "error/media/upload_media/invalid_file/body" )
        );
      }
      db::open( TABLE_MEDIA );
        db::where( "media_id", $media_id );
      $media = db::result();
      db::clear_result();
      $media_verified = false;

      $year = gmdate( "Y", strtotime( $media['media_created'] ) );
      $month = gmdate( "m", strtotime( $media['media_created'] ) );
      if( !file_exists( "uploads/media/" . $year ) ) {
        mkdir( "uploads/media/" . $year, 0777 );
      }
      if( !file_exists( "uploads/media/" . $year . "/" . $month ) ) {
        mkdir( "uploads/media/" . $year . "/" . $month, 0777 );
      }
      if( !file_exists( "uploads/media/" . $year . "/" . $month . "/" . $media_id ) ) {
        mkdir( "uploads/media/" . $year . "/" . $month . "/" . $media_id, 0777 );
      }
      if( copy( $old_file, "uploads/media/" . $year . "/" . $month . "/" . $media_id . "/" . $new_file ) ) {
        $media_verified = true;
      }
      
      $media_filesize = filesize( $old_file );
      $name_split = explode( ".", $new_file );
      $media_extension = $name_split[count($name_split)-1];
      if( $media_type == "image" ) {
        $info = getimagesize( $old_file );
        $media_width = $info[0];
        $media_height = $info[1];
        $media_mimetype = $info['mime'];
      }
      unlink( $old_file );
      if( $media_verified ) {
        db::open( TABLE_MEDIA );
          db::where( "media_id", $verify_id );
          db::set( "media_verified", 1 );
          db::set( "media_filename", $new_file );
          db::set( "media_filesize", $media_filesize );
          db::set( "media_extension", $media_extension );
          db::set( "media_mimetype", $media_mimetype );
          db::set( "media_width", $media_width );
          db::set( "media_height", $media_height );
        if( !db::update() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/media/upload_media/verify_uploaded_media/title" ),
            lang::phrase( "error/media/upload_media/verify_uploaded_media/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      }
    }

    private static function delete_media() {
      sys::check_return_page();
      $media_ids = sys::input( "media_ids", array() );
      if( !is_array( $media_ids ) ) {
        $media_ids = array( $media_ids );
      }
      foreach( $media_ids as $media_id ) {
        echo "<!-- Deleting media " . $media_id . " -->\n";
        self::process_delete_media( (int)$media_id );
      }
      action::resume( "media/actions" );
        action::start( "action" );
          action::add( "title", lang::phrase( "media/actions/delete_media/title" ) );
          action::add( "name", "delete_media" );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "media/actions/delete_media/success/body" ) );
        action::end();
      action::end();
      if( action::get( "request/return_page" ) ) {
        sys::message( 
          USER_MESSAGE,
          lang::phrase( "media/actions/delete_media/success/title" ),
          lang::phrase( "media/actions/delete_media/success/body" )
        );
      }
    }

    private static function process_delete_media( $media_id ) {
      $media = null;
      if( $media_id ) {
        db::open( TABLE_MEDIA );
          db::where( "media_id", $media_id );
        $media = db::result();
        db::clear_result();
      }
      if( !auth::test( "media", "delete_media" ) ) {
        if( $media['user_id'] != action::get( "user/id" ) || !auth::test( "media", "delete_own_media" ) ) {
          sys::message( 
            USER_ERROR,
            lang::phrase( "authentication/media/delete_media/denied/title" ),
            lang::phrase( "authentication/media/delete_media/denied/body" )
          );
        }
      }
      if( $media ) {
        db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS );
          db::where( "media_id", $media_id );
        $media_gallery_assignments = array();
        while( $row = db::result() ) {
          $media_gallery_assignments[] = $row;
        }
        db::open( TABLE_MEDIA );
          db::where( "media_id", $media_id );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/media/process_delete_media/could_not_delete/title" ),
            lang::phrase( "error/media/process_delete_media/could_not_delete/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        db::open( TABLE_MEDIA_QUEUE );
          db::where( "media_id", $media_id );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/media/process_delete_media/could_not_delete_from_queue/title" ),
            lang::phrase( "error/media/process_delete_media/could_not_delete_from_queue/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS );
          db::where( "media_id", $media_id );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/media/process_delete_media/could_not_remove_from_gallery/title" ),
            lang::phrase( "error/media/process_delete_media/could_not_remove_from_gallery/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        $total_assignments = count( $media_gallery_assignments );
        for( $i = 0; $i < $total_assignments; $i++ ) {
          self::update_media_gallery( $media_gallery_assignments[$i]['media_gallery_id'] );
        }
        self::delete_media_file( $media['media_parent'] ? $media['media_parent'] : $media['media_id'], $media['media_filename'], $media['media_cdn_enabled'] );
        self::delete_media_children( $media_id );
      }
    }

    private static function delete_media_file( $media_id, $media_filename, $media_cdn_enabled ) {
      db::open( TABLE_MEDIA );
        db::where( "media_id", $media_id );
      $media = db::result();
      db::clear_result();
      if( $media_cdn_enabled ) {
        cdn::delete_object( "media", $media_filename );
      }
      $year = gmdate( "Y", strtotime( $media['media_created'] ) );
      $month = gmdate( "m", strtotime( $media['media_created'] ) );
      if( file_exists( "uploads/media/" . $year . "/" . $month . "/" . $media_id . "/" . $media_filename ) ) {
        unlink( "uploads/media/" . $year . "/" . $month . "/" . $media_id . "/" . $media_filename );
      }
    }

    private static function delete_media_children( $media_id ) {
      db::open( TABLE_MEDIA );
        db::where( "media_parent", $media_id );
      while( $row = db::result() ) {
        self::process_delete_media( $row['media_id'] );
      }
    }

    private static function edit_gallery() {
      sys::check_return_page();
      $media_gallery_id = (int)sys::input( "media_gallery_id", 0 );
      if( $media_gallery_id ) {
        db::open( TABLE_MEDIA_GALLERIES );
          db::where( "media_gallery_id", $media_gallery_id );
        $media_gallery = db::result();
        db::clear_result();
        if( !$media_gallery ) {
          sys::message( 
            USER_ERROR,
            lang::phrase( "error/media/actions/edit_gallery/invalid_media_gallery_id/title" ),
            lang::phrase( "error/media/actions/edit_gallery/invalid_media_gallery_id/body" )
          );
        }
        if( !auth::test( "media", "edit_galleries" ) ) {
          if( $media_gallery['user_id'] != action::get( "user/id" ) || !auth::test( "media", "edit_own_galleries" ) ) {
            auth::deny( "media", "edit_galleries" );
          }
        }
      } else {
        if( !auth::test( "media", "add_galleries" ) ) {
          auth::deny( "media", "add_galleries" );
        }
      }
      $media_gallery_title = sys::input( "media_gallery_title", "" );
      $media_gallery_name = sys::input( "media_gallery_name", "" );
      if( !$media_gallery_name ) {
        $media_gallery_name = $media_gallery_title;
      }
      $media_gallery_name = sys::create_tag( $media_gallery_name );
      $media_gallery_description = sys::input( "media_gallery_description", "" );
      $media_gallery_private = sys::input( "media_gallery_private", 0 );
      $current_date = gmdate( "Y-m-d H:i:s", time() );
      $gallery_created = false;
      db::open( TABLE_MEDIA_GALLERIES );
        db::set( "media_gallery_title", $media_gallery_title );
        db::set( "media_gallery_name", $media_gallery_name );
        db::set( "media_gallery_description", $media_gallery_description );
        db::set( "media_gallery_updated", $current_date );
        db::set( "media_gallery_private", $media_gallery_private );
      if( $media_gallery_id ) {
        db::set( "media_gallery_updated_by", action::get( "user/id" ) );
        db::where( "media_gallery_id", $media_gallery_id );
        if( !db::update() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/media/actions/edit_gallery/could_not_update/title" ),
            lang::phrase( "error/media/actions/edit_gallery/could_not_update/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      } else {
        db::set( "media_gallery_created", $current_date );
        db::set( "user_id", action::get( "user/id" ) );
        if( !db::insert() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/media/actions/edit_gallery/could_not_create/title" ),
            lang::phrase( "error/media/actions/edit_gallery/could_not_create/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        $gallery_created = true;
        $media_gallery_id = db::id();
      }
      action::resume( "media/actions" );
        action::start( "action" );
          action::add( "title", lang::phrase( "media/actions/edit_gallery/title" ) );
          action::add( "name", "edit_gallery" );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "media/actions/edit_gallery/success/body" ) );
        action::end();
      action::end();
      if( action::get( "request/return_page" ) ) {
        if( $gallery_created ) {
          $return_page = action::get( "request/return_page" );
          $return_page = str_replace( "[media_gallery_id]", $media_gallery_id, $return_page );
          sys::replace_return_page( $return_page );
        }
        sys::message(
          USER_MESSAGE,
          lang::phrase( "media/actions/edit_gallery/success/title" ),
          lang::phrase( "media/actions/edit_gallery/success/body" )
        );
      }
    }

    private static function set_featured() {
      sys::check_return_page();
      $media_gallery_id = sys::input( "media_gallery_id", 0 );
      $media_gallery = null;
      if( $media_gallery_id ) {
        db::open( TABLE_MEDIA_GALLERIES );
          db::where( "media_gallery_id", $media_gallery_id );
        $media_gallery = db::result();
        db::clear_result();
      }
      if( !$media_gallery_id || !$media_gallery ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/media/actions/set_featured/invalid_media_gallery_id/title" ),
          lang::phrase( "error/media/actions/set_featured/invalid_media_gallery_id/body" )
        );
      }
      if( !auth::test( "media", "edit_galleries" ) ) {
        if( $media_gallery['user_id'] != action::get( "user/id" ) || !auth::test( "media", "edit_own_galleries" ) ) {
          auth::deny( "media", "edit_galleries" );
        }
      }
      $media_id = sys::input( "media_id", 0 );
      $media = null;
      if( $media_id ) {
        db::open( TABLE_MEDIA );
          db::where( "media_id", $media_id );
        $media = db::result();
        db::clear_result();
      }
      if( !$media_id || !$media ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/media/actions/set_featured/invalid_media_id/title" ),
          lang::phrase( "error/media/actions/set_featured/invalid_media_id/body" )
        );
      }
      db::open( TABLE_MEDIA_GALLERIES );
        db::set( "media_id", $media_id );
        db::where( "media_gallery_id", $media_gallery_id );
      if( !db::update() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/media/actions/set_featured/could_not_update_gallery/title" ),
          lang::phrase( "error/media/actions/set_featured/could_not_update_gallery/body" ),
          __FILE__, __LINE__, __FUNCTION__, __CLASS__
        );
      }
      action::resume( "media/actions" );
        action::start( "action" );
          action::add( "title", lang::phrase( "media/actions/set_featured/title" ) );
          action::add( "name", "set_featured" );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "media/actions/set_featured/success/body" ) );
        action::end();
      action::end();
      if( action::get( "request/return_page" ) ) {
        sys::message(
          USER_MESSAGE,
          lang::phrase( "media/actions/set_featured/success/title" ),
          lang::phrase( "media/actions/set_featured/success/body" )
        );
      }
    }

    private static function delete_gallery() {
      sys::check_return_page();
      $media_gallery_ids = sys::input( "media_gallery_id", array() );
      if( !is_array( $media_gallery_ids ) ) {
        $media_gallery_ids = array( $media_gallery_ids );
      }
      foreach( $media_gallery_ids as $media_gallery_id ) {
        self::process_delete_gallery( (int)$media_gallery_id );
      }
      action::resume( "media/actions" );
        action::start( "action" );
          action::add( "title", lang::phrase( "media/actions/delete_gallery/title" ) );
          action::add( "name", "delete_gallery" );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "media/actions/delete_gallery/success/body" ) );
        action::end();
      action::end();
      if( action::get( "request/return_page" ) ) {
        sys::message( 
          USER_MESSAGE,
          lang::phrase( "media/actions/delete_gallery/success/title" ),
          lang::phrase( "media/actions/delete_gallery/success/body" )
        );
      }
    }

    private static function process_delete_gallery( $media_gallery_id ) {
      $media_gallery = null;
      if( $media_gallery_id && is_int( $media_gallery_id ) ) {
        db::open( TABLE_MEDIA_GALLERIES );
          db::where( "media_gallery_id", $media_gallery_id );
        $media_gallery = db::result();
        db::clear_result();
      } else {
        sys::message( 
          USER_MESSAGE,
          lang::phrase( "error/media/process_delete_gallery/invalid_media_gallery_id/title" ),
          lang::phrase( "error/media/process_delete_gallery/invalid_media_gallery_id/body" )
        );
      }
      if( !auth::test( "media", "delete_galleries" ) ) {
        if( $media_gallery['user_id'] != action::get( "user/id" ) || !auth::test( "media", "delete_own_galleries" ) ) {
          auth::deny( "media", "delete_galleries" );
        }
      }
      if( $media_gallery ) {
        db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS );
          db::where( "media_gallery_id", $media_gallery_id );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/media/process_delete_gallery/could_not_delete_assignments/title" ),
            lang::phrase( "error/media/process_delete_gallery/could_not_delete_assignments/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        db::open( TABLE_MEDIA_GALLERIES );
          db::where( "media_gallery_id", $media_gallery_id );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/media/process_delete_gallery/could_not_delete/title" ),
            lang::phrase( "error/media/process_delete_gallery/could_not_delete/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        self::delete_gallery_children( $media_gallery_id );
      }
    }

    private static function delete_gallery_children( $media_gallery_id ) {
      db::open( TABLE_MEDIA_GALLERIES );
        db::where( "media_gallery_parent", $media_gallery_id );
      while( $row = db::result() ) {
        self::process_delete_gallery( $row['media_gallery_id'] );
      }
    }

    private static function initiate_attach() {
      sys::check_return_page();
      $media_ids = sys::input( "media_id", array() );
      $media_queue_description = sys::input( "media_queue_description", "" );
      if( !is_array( $media_ids ) ) {
        $media_ids = array( $media_ids );
      }
      foreach( $media_ids as $media_id ) {
        self::process_initiate_attach( (int)$media_id, $media_queue_description );
      }
      action::resume( "media/actions" );
        action::start( "action" );
          action::add( "title", lang::phrase( "media/actions/initiate_attach/title" ) );
          action::add( "name", "initiate_attach" );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "media/actions/initiate_attach/success/body" ) );
        action::end();
      action::end();
      if( action::get( "request/return_page" ) ) {
        sys::message( 
          USER_MESSAGE,
          lang::phrase( "media/actions/initiate_attach/success/title" ),
          lang::phrase( "media/actions/initiate_attach/success/body" )
        );
      }
    }

    private static function process_initiate_attach( $media_id, $media_queue_description ) {
      $media = null;
      if( $media_id ) {
        db::open( TABLE_MEDIA );
          db::where( "media_id", $media_id );
        $media = db::result();
        db::clear_result();
      }
      if( !$media_id || !$media ) {
        sys::message( 
          USER_ERROR,
          lang::phrase( "error/media/process_initiate_attach/invalid_media_id/title" ),
          lang::phrase( "error/media/process_initiate_attach/invalid_media_id/body" )
        );
      }
      if( !auth::test( "media", "attach_media" ) ) {
        if( $media['user_id'] != action::get( "user/id" ) || !auth::test( "media", "attach_own_media" ) ) {
          auth::deny( "media", "attach_media" );
        }
      }
      db::open( TABLE_MEDIA_QUEUE );
        db::where( "media_id", $media_id );
        db::where( "media_queue_type", MEDIA_QUEUE_ASSIGNMENT );
      $media_queue = db::result();
      db::clear_result();
      if( !$media_queue ) {
        $current_date = gmdate( "Y-m-d H:i:s" );
        db::open( TABLE_MEDIA_QUEUE );
          db::set( "media_id", $media_id );
          db::set( "media_queue_type", MEDIA_QUEUE_ASSIGNMENT );
          db::set( "media_queue_description", $media_queue_description );
          db::set( "media_queue_created", $current_date );
        if( !db::insert() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/media/process_initiate_attach/could_not_add_to_queue/title" ),
            lang::phrase( "error/media/process_initiate_attach/could_not_add_to_queue/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      }
    }

    private static function complete_attach() {
      sys::check_return_page();
      $media_ids = sys::input( "media_id", array() );
      if( !is_array( $media_ids ) ) {
        $media_ids = array( $media_ids );
      }
      $media_gallery_id = sys::input( "media_gallery_id", 0 );
      $media_gallery = null;
      if( $media_gallery_id ) {
        db::open( TABLE_MEDIA_GALLERIES );
          db::where( "media_gallery_id", $media_gallery_id );
          db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS, LEFT );
            db::select( "media_order" );
            db::link( "media_gallery_id" );
            db::order( "media_order", "DESC" );
        $media_gallery = db::result();
      }
      if( !$media_gallery ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/media/actions/complete_attach/invalid_media_gallery_id/title" ),
          lang::phrase( "error/media/actions/complete_attach/invalid_media_gallery_id/body" )
        );
      }
      $media_order = 0;
      if( !$media_gallery['media_order'] ) {
        $media_order = 1;
      } else {
        $media_order = $media_gallery['media_order'] + 1;
      }
      foreach( $media_ids as $media_id ) {
        self::process_complete_attach( (int)$media_id, $media_gallery, $media_order, $attach_media, $attach_media_to_own, $attach_own_media_to_own );
        $media_order++;
      }
      action::resume( "media/actions" );
        action::start( "action" );
          action::add( "title", lang::phrase( "media/actions/complete_attach/title" ) );
          action::add( "name", "complete_attach" );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "media/actions/complete_attach/success/body" ) );
        action::end();
      action::end();
      if( action::get( "request/return_page" ) ) {
        sys::message( 
          USER_MESSAGE,
          lang::phrase( "media/actions/complete_attach/success/title" ),
          lang::phrase( "media/actions/complete_attach/success/body" )
        );
      }
    }

    private static function process_complete_attach( $media_id, $media_gallery, $media_order ) {
      $media = null;
      if( $media_id ) {
        db::open( TABLE_MEDIA );
          db::where( "media_id", $media_id );
        $media = db::result();
        db::clear_result();
      }
      if( !$media_id || !$media ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/media/process_complete_attach/invalid_media_id/title" ),
          lang::phrase( "error/media/process_complete_attach/invalid_media_id/body" )
        );
      }
      $user_id = action::get( "user/id" );
      $attach_media = auth::test( "media", "attach_media" );
      $attach_media_to_own = auth::test( "media", "attach_media_to_own" );
      $attach_own_media_to_own = auth::test( "media", "attach_own_media_to_own" );
      if( !$attach_media ) {
        if( $media_gallery['user_id'] != $user_id || !$attach_media_to_own ) {
          if( $media_gallery['user_id'] != $user_id || $media['user_id'] != $user_id || !$attach_own_media_to_own ) ) {
            auth::deny( "media", "attach_media" );
          }
        }
      }
      $media_gallery_id = $media_gallery['media_gallery_id'];
      db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS );
        db::where( "media_id", $media_id );
        db::where( "media_gallery_id", $media_gallery_id );
      $media_gallery_assignment = db::result();
      db::clear_result();
      if( !$media_gallery_assignment ) {
        db::open( TABLE_MEDIA_QUEUE );
          db::where( "media_id", $media_id );
          db::where( "media_queue_type", MEDIA_QUEUE_ASSIGNMENT );
        $media_queue = db::result();
        db::clear_result();
        if( !$media_queue ) {
          sys::message(
            USER_ERROR,
            lang::phrase( "error/media/process_complete_attach/media_not_in_queue/title" ),
            lang::phrase( "error/media/process_complete_attach/media_not_in_queue/body" )
          );
        }
        $current_date = gmdate( "Y-m-d H:i:s" );
        db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS );
          db::set( "media_id", $media_id );
          db::set( "media_gallery_id", $media_gallery_id );
          db::set( "media_added", $current_date );
          db::set( "media_order", $media_order );
        if( !db::insert() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/media/process_complete_attach/could_not_attach/title" ),
            lang::phrase( "error/media/process_complete_attach/could_not_attach/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        db::open( TABLE_MEDIA_QUEUE );
          db::where( "media_id", $media_id );
          db::where( "media_queue_type", MEDIA_QUEUE_ASSIGNMENT );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/media/process_complete_attach/could_not_delete_from_queue/title" ),
            lang::phrase( "error/media/process_complete_attach/could_not_delete_from_queue/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        self::update_media_gallery( $media_gallery_id );
      }
    }

    private static function complete_detach() {
      sys::check_return_page();
      $media_ids = sys::input( "media_id", array() );
      if( !is_array( $media_ids ) ) {
        $media_ids = array( $media_ids );
      }
      $media_gallery_id = sys::input( "media_gallery_id", 0 );
      $media_gallery = null;
      if( $media_gallery_id ) {
        db::open( TABLE_MEDIA_GALLERIES );
          db::where( "media_gallery_id", $media_gallery_id );
          db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS, LEFT );
            db::select( "media_order" );
            db::link( "media_gallery_id" );
            db::order( "media_order", "DESC" );
        $media_gallery = db::result();
      }
      if( !$media_gallery ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/media/actions/complete_attach/invalid_media_gallery_id/title" ),
          lang::phrase( "error/media/actions/complete_attach/invalid_media_gallery_id/body" )
        );
      }
      foreach( $media_ids as $media_id ) {
        self::process_complete_detach( (int)$media_id, $media_gallery );
      }
      action::resume( "media/actions" );
        action::start( "action" );
          action::add( "title", lang::phrase( "media/actions/complete_detach/title" ) );
          action::add( "name", "complete_detach" );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "media/actions/complete_detach/success/body" ) );
        action::end();
      action::end();
      if( action::get( "request/return_page" ) ) {
        sys::message(
          USER_MESSAGE,
          lang::phrase( "media/actions/complete_detach/success/title" ),
          lang::phrase( "media/actions/complete_detach/success/body" )
        );
      }
    }

    private static function process_complete_detach( $media_id, $media_gallery ) {
      $media = null;
      if( $media_id ) {
        db::open( TABLE_MEDIA );
          db::where( "media_id", $media_id );
        $media = db::result();
        db::clear_result();
      }
      if( !$media_id || !$media ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/media/process_complete_detach/invalid_media_id/title" ),
          lang::phrase( "error/media/process_complete_detach/invalid_media_id/body" )
        );
      }
      $user_id = action::get( "user/id" );
      $detach_media = auth::test( "media", "detach_media" );
      $detach_media_from_own = auth::test( "media", "detach_media_from_own" );
      $detach_own_media_from_own = auth::test( "media", "detach_own_media_from_own" );
      if( !$detach_media ) {
        if( $media_gallery['user_id'] != $user_id || !$detach_media_from_own ) {
          if( $media_gallery['user_id'] != $user_id || $media['user_id'] != $user_id || !$detach_own_media_from_own ) {
            auth::deny( "media", "attach_media" );
          }
        }
      }
      $media_gallery_id = $media_gallery['media_gallery_id'];
      db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS );
        db::where( "media_id", $media_id );
        db::where( "media_gallery_id", $media_gallery_id );
      $media_gallery_assignment = db::result();
      db::clear_result();
      if( $media_gallery_assignment ) {
        db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS );
          db::where( "media_id", $media_id );
          db::where( "media_gallery_id", $media_gallery_id );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/media/process_complete_detach/could_not_detach/title" ),
            lang::phrase( "error/media/process_complete_detach/could_not_detach/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        self::update_media_gallery( $media_gallery_id );
      }
    }

    private static function delete_queue() {
      sys::check_return_page();
      $media_ids = sys::input( "media_id", array() );
      $media = null;
      if( $media_ids && !is_array( $media_ids ) ) {
        $media_ids = array( $media_ids );
      }
      foreach( $media_ids as $media_id ) {
        db::open( TABLE_MEDIA_QUEUE );
          db::where( "media_id", $media_id );
        $media = db::result();
        db::clear_result();
        if( !auth::test( "media", "attach_media" ) ) {
          if( $media['user_id'] != action::get( "user/id" ) || ( !auth::test( "media", "attach_own_media" ) && !auth::test( "media", "attach_own_media_to_own" ) ) ) {
            auth::deny( "media", "unqueue_media" );
          }
        }
        db::open( TABLE_MEDIA_QUEUE );
          db::where( "media_id", $media_id );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/media/actions/delete_queue/could_not_delete/title" ),
            lang::phrase( "error/media/actions/delete_queue/could_not_delete/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      }
      action::resume( "media/actions" );
        action::start( "action" );
          action::add( "title", lang::phrase( "media/actions/delete_queue/title" ) );
          action::add( "name", "delete_queue" );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "media/actions/delete_queue/success/body" ) );
        action::end();
      action::end();
      if( action::get( "request/return_page" ) ) {
        sys::message(
          USER_MESSAGE,
          lang::phrase( "media/actions/delete_queue/success/title" ),
          lang::phrase( "media/actions/delete_queue/success/body" )
        );
      }
    }

    private static function update_media_gallery( $media_gallery_id ) {
      db::open( TABLE_MEDIA_GALLERIES );
        db::where( "media_gallery_id", $media_gallery_id );
      $media_gallery = db::result();
      db::clear_result();
      db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS );
        db::where( "media_gallery_id", $media_gallery_id );
        db::order( "media_added", "DESC" );
        db::limit( 0, 1 );
      $last_media = db::result();
      db::clear_result();
      db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS );
        db::select_as( "media_gallery_count" );
        db::select_count_distinct( "media_id" );
        db::where( "media_gallery_id", $media_gallery_id );
      $count = db::result();
      db::clear_result();
      if(
        $last_media['media_id'] != $media_gallery['media_id']
        || $count['media_gallery_count'] != $media_gallery['media_gallery_count']
      ) {
        $current_date = gmdate( "Y-m-d H:i:s" );
        db::open( TABLE_MEDIA_GALLERIES );
          db::where( "media_gallery_id", $media_gallery_id );
          if( !$media_gallery['media_id'] ) {
            db::set( "media_id", $last_media['media_id'] );
          }
          db::set( "media_gallery_count", $count['media_gallery_count'] );
          db::set( "media_gallery_updated", $current_date );
        if( !db::update() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/media/update_media_gallery/could_not_update/title" ),
            lang::phrase( "error/media/update_media_gallery/could_not_update/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      }
    }

    private static function generate_thumbnail() {
      sys::check_return_page();
      $media_id = sys::input( "media_id", 0 );
      $fill_space = sys::input( "fill_space", false );
      $width = sys::input( "media_width", 0 );
      $height = sys::input( "media_height", 0 );
      self::output_media_image( $media_id, $fill_space, $width, $height, false );
      action::resume( "media/actions" );
        action::start( "action" );
          action::add( "title", lang::phrase( "media/actions/generate_thumbnail/title" ) );
          action::add( "name", "generate_thumbnail" );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "media/actions/generate_thumbnail/success/body" ) );
        action::end();
      action::end();
      if( action::get( "request/return_page" ) ) {
        sys::message(
          USER_MESSAGE,
          lang::phrase( "media/actions/generate_thumbnail/success/title" ),
          lang::phrase( "media/actions/generate_thumbnail/success/body" )
        );
      }
    }

    public static function list_tags() {
      $media_gallery_id = sys::input( "media_gallery_id", 0 );
      if( $media_gallery_id ) {
        db::open( TABLE_MEDIA_GALLERIES );
          db::where( "media_gallery_id", $media_gallery_id );
        $media_gallery = db::result();
        db::clear_result();
        if( !$media_gallery ) {
          sys::message(
            USER_ERROR,
            lang::phrase( "error/media/list_tags/invalid_media_gallery_id/title" ),
            lang::phrase( "error/media/list_tags/invalid_media_gallery_id/body" )
          );
        }
      }
      $media_id = sys::input( "media_id", 0 );
      if( $media_id ) {
        db::open( TABLE_MEDIA );
          db::where( "media_id", $media_id );
        $media = db::result();
        db::clear_result();
        if( !$media ) {
          sys::message(
            USER_ERROR,
            lang::phrase( "error/media/list_tags/invalid_media_id/title" ),
            lang::phrase( "error/media/list_tags/invalid_media_id/body" )
          );
        }
      }
      $tag_sort_mode = sys::input( "tag_sort_mode", "most-recent" );
      $page = sys::input( "page", 1 );
      $per_page = sys::input( "per_page", 20 );
      action::resume( "media" );
        action::start( "media_tag_list" );
          db::open( TABLE_TAGS );
            db::group( "tag_name" );
            db::where( "tag_type", MEDIA_TAG );
            if( $media_id ) {
              db::where( "tag_target", $media_id );
            }
            if( $tag_sort_mode == "alphabetical" ) {
              db::order( "tag_name", "ASC" );
            } else if( $tag_sort_mode == "reverse-alphabetical" ) {
              db::order( "tag_name", "DESC" );
            }
            db::open( TABLE_MEDIA, LEFT );
              db::select_as( "media_count" );
              db::select_count( "media_id" );
              db::select_as( "media_id" );
              db::select_max( "media_id" );
              db::select_as( "media_created" );
              db::select_max( "media_created" );
              db::loose_link( "media_id", "tag_target" );
              db::order( "media_created", "DESC" );
              if( $tag_sort_mode == "most-recent" ) {
                db::order( "recent_media_created", "DESC", false );
              } else if( $tag_sort_mode == "least-recent" ) {
                db::order( "recent_media_created", "ASC", false );
              } else if( $tag_sort_mode == "highest-count" ) {
                db::order( "media_count", "DESC", false );
              } else if( $tag_sort_mode == "lowest-count" ) {
                db::order( "media_count", "ASC", false );
              }
              if( $media_gallery_id ) {
                db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS );
                  db::link( "media_id" );
                  db::where( "media_gallery_id", $media_gallery_id );
                db::close();
              }
            db::close();
          while( $row = db::result() ) {
            action::start( "tag" );
              action::add( "name", $row['tag_name'] );
              action::add( "title", $row['tag_title'] );
              action::add( "count", $row['media_count'] );
              action::start( "media" );
                action::add( "id", $row['media_id'] );
                $timestamp = strtotime( $row['media_created'] );
                action::add( "period", sys::create_duration( $timestamp, time() ) );
                $timestamp += ( 60 * 60 ) * sys::timezone();
                action::add( "datetime", sys::create_datetime( $timestamp ) );
                action::start( "children" );
                  self::get_media_children( $row['media_id'] );
                action::end();
              action::end();
            action::end();
          }
        action::end();
      action::end();
    }

    public static function list_media() {
      $media_gallery_id = sys::input( "media_gallery_id", 0 );
      $media_gallery_name = sys::input( "media_gallery_name", "" );
      $media_gallery = NULL;
      if( $media_gallery_id || $media_gallery_name ) {
        db::open( TABLE_MEDIA_GALLERIES );
          if( $media_gallery_id ) {
            db::where( "media_gallery_id", $media_gallery_id );
          }
          if( $media_gallery_name ) {
            db::where( "media_gallery_name", $media_gallery_name );
          }
        $media_gallery = db::result();
        db::clear_result();
        if( !$media_gallery ) {
          sys::message(
            USER_ERROR,
            lang::phrase( "error/media/list_media/invalid_media_gallery_id/title" ),
            lang::phrase( "error/media/list_media/invalid_media_gallery_id/body" )
          );
        }
        $media_gallery_id = $media_gallery['media_gallery_id'];
      }
      $queued_media_only = sys::input( "queued_media_only", false );
      $media_sort_mode = sys::input( "media_sort_mode", "" );
      if( $media_gallery && !$media_sort_mode ) {
        $media_sort_mode = "oldest-added-first";
      } else if( !$media_sort_mode ) {
        $media_sort_mode = "newest-created-first";
      }
      $page = sys::input( "page", 1 );
      $per_page = sys::input( "per_page", 15 );
      $offset = sys::input( "offset", 0 );
      $thumbnail_width = sys::input( "thumbnail_width", 0 );
      $thumbnail_height = sys::input( "thumbnail_height", 0 );
      $media_id = sys::input( "media_id", 0 );
      $modify_offset = sys::input( "modify_offset", false );
      $media = NULL;
      if( $media_id ) {
        db::open( TABLE_MEDIA );
          db::where( "media_id", $media_id );
          db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS );
            db::link( "media_id" );
            db::where( "media_gallery_id", $media_gallery_id );
        $media = db::result();
        db::clear_result();
      }

      db::open( TABLE_MEDIA );
        db::select_as( "media_count" );
        db::select_count_distinct( "media_id" );
        db::where( "media_parent", 0 );
        if( $media_gallery_id && !$queued_media_only ) {
          db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS );
            db::where( "media_gallery_id", $media_gallery_id );
            db::link( "media_id" );
          db::close();
        } else if( $queued_media_only ) {
          db::open( TABLE_MEDIA_QUEUE );
            db::link( "media_id" );
          db::close();
        }
      $media_count = db::result();
      db::clear_result();
      $total_media = $media_count['media_count'];
      $total_pages = ceil( $total_media / $per_page );
      if( $total_pages == 0 ) {
        $total_pages = 1;
      }
      if( $page > $total_pages ) {
        $page = $total_pages;
      }
      if( $offset && $offset + $per_page >= $total_media ) {
        $offset = $total_media - $per_page;
      }

      if( $media && $media_gallery && $modify_offset && $total_media > $per_page ) {
        db::open( TABLE_MEDIA_GALLERIES );
          db::where( "media_gallery_id", $media_gallery_id );
          db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS );
            db::select_as( "media_count" );
            db::select_count( "media_id" );
            db::link( "media_gallery_id" );
            if( $media_sort_mode == "newest-added-first" ) {
              db::where( "media_order", $media['media_order'], ">" );
              db::order( "media_order", "ASC" );
            } else if( $media_sort_mode == "oldest-added-first" ) {
              db::where( "media_order", $media['media_order'], "<" );
              db::order( "media_order", "DESC" );
            }
            db::open( TABLE_MEDIA );
              db::link( "media_id" );
              if( $media_sort_mode == "newest-updated-first" ) {
                db::where( "media_updated", $media['media_updated'], ">" );
                db::order( "media_updated", "ASC" );
              } else if( $media_sort_mode == "oldest-updated-first" ) {
                db::where( "media_updated", $media['media_updated'], "<" );
                db::order( "media_updated", "DESC" );
              } else if( $media_sort_mode == "newest-created-first" ) {
                db::where( "media_created", $media['media_created'], ">" );
                db::order( "media_created", "ASC" );
              } else if( $media_sort_mode == "oldest-created-first" ) {
                db::where( "media_created", $media['media_created'], "<" );
                db::order( "media_created", "DESC" );
              }
            db::close();
          db::close();
          db::limit( 0, 1 );
        $media_count = db::result();
        db::clear_result();
        $row_number = $media_count['media_count'] + 1;
        if( $row_number >= $total_media - floor( $per_page / 2 ) ) {
          $offset = $total_media - $per_page;
        } else if( $row_number <= floor( $per_page / 2 ) ) {
          $offset = 0;
        } else {
          $offset = $row_number - floor( $per_page / 2 ) - 1;
        }
      }

      action::resume( "media" );
        action::start( "media_list" );
          action::add( "page", $page );
          action::add( "per_page", $per_page );
          action::add( "offset", $offset );
          action::add( "total_pages", $total_pages );
          action::add( "total_media", $total_media );
          action::add( "width", $thumbnail_width );
          action::add( "height", $thumbnail_height );
          if( $media ) {
            action::add( "target", $media_id );
          }
          if( $media_sort_mode ) {
            action::add( "sort", $media_sort_mode );
          }
          if( $media_gallery ) {
            action::start( "gallery" );
              action::add( "id", $media_gallery['media_gallery_id'] );
              action::add( "name", $media_gallery['media_gallery_name'] );
            action::end();
          }
          db::open( TABLE_MEDIA );
            db::where( "media_parent", 0 );
            if( $media_gallery_id && !$queued_media_only ) {
              db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS );
                db::where( "media_gallery_id", $media_gallery_id );
                db::link( "media_id" );
                if( $media_sort_mode == "newest-added-first" ) {
                  db::order( "media_order", "DESC" );
                } else if( $media_sort_mode == "oldest-added-first" ) {
                  db::order( "media_order", "ASC" );
                }
              db::close();
            }
            db::open( TABLE_USERS, LEFT );
              db::link( "user_id" );
            db::close();
            db::open( TABLE_MEDIA_QUEUE, $queued_media_only ? NULL : LEFT );
              db::select( "media_queue_created" );
              db::link( "media_id" );
            db::close();
            if( $media_sort_mode == "newest-updated-first" ) {
              db::order( "media_updated", "DESC" );
            } else if( $media_sort_mode == "oldest-updated-first" ) {
              db::order( "media_updated", "ASC" );
            } else if( $media_sort_mode == "newest-created-first" ) {
              db::order( "media_created", "DESC" );
            } else if( $media_sort_mode == "oldest-created-first" ) {
              db::order( "media_created", "ASC" );
            }
            if( $offset && $per_page ) {
              db::limit( $offset, $per_page );
            } else if( $per_page ) {
              db::limit( ($page-1)*$per_page, $per_page );
            }
          while( $row = db::result() ) {
            action::start( "media" );
              action::add( "id", $row['media_id'] );
              action::add( "title", $row['media_title'] );
              action::add( "name", $row['media_name'] );
              action::add( "description", $row['media_description'] );
              action::add( "type", $row['media_type'] );
              action::add( "filename", $row['media_filename'] );
              action::add( "extension", $row['media_extension'] );
              action::add( "filesize", $row['media_filesize'] );
              action::add( "mimetype", $row['media_mimetype'] );
              action::add( "width", $row['media_width'] );
              action::add( "height", $row['media_height'] );
              action::add( "cdn", $row['media_cdn_enabled'] );
              action::add( "queued", $row['media_queue_created'] ? 1 : 0 );
              action::add( "cdn_enabled", $row['media_cdn_enabled'] );
              if( $media_gallery_id && $row['media_id'] == $media_gallery['media_id'] ) {
                action::add( "featured", 1 );
              }
              action::start( "author" );
                action::add( "id", $row['user_id'] );
                action::add( "user_name", $row['user_name'] );
              action::end();
              action::start( "created" );
                $timestamp = strtotime( $row['media_created'] );
                action::add( "period", sys::create_duration( $timestamp, time() ) );
                action::add( "datetime", sys::create_datetime( $timestamp ) );
                $timestamp += ( 60 * 60 ) * sys::timezone();
                action::add( "altered_datetime", sys::create_datetime( $timestamp ) );
              action::end();
              action::start( "updated" );
                $timestamp = strtotime( $row['media_updated'] );
                action::add( "period", sys::create_duration( $timestamp, time() ) );
                action::add( "datetime", sys::create_datetime( $timestamp ) );
                $timestamp += ( 60 * 60 ) * sys::timezone();
                action::add( "altered_datetime", sys::create_datetime( $timestamp ) );
              action::end();
              action::start( "children" );
                self::get_media_children( $row['media_id'] );
              action::end();
            action::end();
          }
        action::end();
      action::end();
    }

    public static function list_galleries() {
      $media_id = sys::input( "media_id", 0 );
      $media = NULL;
      if( $media_id ) {
        db::open( TABLE_MEDIA );
          db::where( "media_id", $media_id );
        $media = db::result();
        db::clear_result();
        if( !$media ) {
          sys::message(
            USER_ERROR,
            lang::phrase( "error/media/list_media/invalid_media_id/title" ),
            lang::phrase( "error/media/list_media/invalid_media_id/body" )
          );
        }
      }
      $page = sys::input( "page", 1 );
      $per_page = sys::input( "per_page", 0 );
      $public_galleries_only = sys::input( "public_galleries_only", 0 );
      $gallery_sort_mode = sys::input( "gallery_sort_mode", "newest-created-first" );
      $skip_latest_gallery = sys::input( "skip_latest_gallery", false ) && $gallery_sort_mode == "newest-created-first" ? 1 : 0;
      db::open( TABLE_MEDIA_GALLERIES );
        db::select_as( "media_gallery_count" );
        db::select_count_distinct( "media_gallery_id" );
        if( $public_galleries_only ) {
          db::where( "media_gallery_private", 0 );
        }
        if( $media ) {
          db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS );
            db::link( "media_gallery_id" );
            db::where( "media_id", $media['media_id'] );
          db::close();
        }
      $media_gallery_count = db::result();
      db::clear_result();
      $total_media_galleries = $media_gallery_count['media_gallery_count'];
      $total_pages = $per_page ? ceil( $total_media_galleries / $per_page ) : 1;
      if( $total_pages == 0 ) {
        $total_pages = 1;
      }
      if( $page > $total_pages ) {
        $page = $total_pages;
      }
      action::resume( "media" );
        action::start( "gallery_list" );
          action::add( "page", $page );
          action::add( "total_pages", $total_pages );
          action::add( "total_galleries", $total_media_galleries );
          action::add( "per_page", $per_page );
          action::add( "sort", $gallery_sort_mode );
          if( $media ) {
            action::start( "media" );
              action::add( "id", $media['media_id'] );
              action::add( "name", $media['media_name'] );
              action::add( "title", $media['media_title'] );
            action::end();
          }
          db::open( TABLE_MEDIA_GALLERIES );
            db::select(
              "media_gallery_id",
              "media_gallery_title",
              "media_gallery_name",
              "media_gallery_updated",
              "media_gallery_updated_by",
              "media_gallery_created",
              "media_gallery_description",
              "media_gallery_count",
              "media_gallery_private"
            );
            if( $public_galleries_only ) {
              db::where( "media_gallery_private", 0 );
            }
            db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS, $media ? NONE : LEFT );
              db::select_as( "latest_media_id" );
              db::select_max( "media_id" );
              db::select_as( "latest_media_added" );
              db::select_max( "media_added" );
              db::link( "media_gallery_id" );
              if( $media ) {
                db::where( "media_id", $media_id );
              }
            db::close();
            db::open( TABLE_MEDIA, LEFT );
              db::link( "media_id" );
            db::close();
            db::open( TABLE_USERS, LEFT );
              db::link( "user_id" );
            db::close();
            db::group( "media_gallery_id" );
            if( $gallery_sort_mode == "newest-created-first" ) {
              db::order( "media_gallery_created", "DESC" );
            } else if( $gallery_sort_mode == "newest-updated-first" ) {
              db::order( "media_gallery_updated", "DESC" );
            } else if( $gallery_sort_mode == "file-count" ) {
              db::order( "media_gallery_count", "DESC" );
            } else if( $gallery_sort_mode == "alphabetical-a-z" ) {
              db::order( "media_gallery_name", "ASC" );
            }
            if( $per_page ) {
              db::limit( (($page-1)*$per_page)+$skip_latest_gallery, $per_page );
            }
          while( $row = db::result() ) {
            action::start( "gallery" );
              action::add( "id", $row['media_gallery_id'] );
              action::add( "name", $row['media_gallery_name'] );
              action::add( "title", $row['media_gallery_title'] );
              action::add( "description", $row['media_gallery_description'] );
              action::add( "media_count", $row['media_gallery_count'] );
              action::add( "private", $row['media_gallery_private'] );
              if( $row['media_id'] ) {
                action::start( "featured" );
                  action::add( "id", $row['media_id'] );
                  action::add( "cdn_enabled", $row['media_cdn_enabled'] );
                  action::add( "width", $row['media_width'] );
                  action::add( "height", $row['media_height'] );
                  action::start( "created" );
                    $timestamp = strtotime( $row['media_created'] );
                    action::add( "datetime", sys::create_datetime( $timestamp ) );
                  action::end();
                  action::start( "children" );
                    self::get_media_children( $row['media_id'] );
                  action::end();
                action::end();
              }
              if( $row['latest_media_id'] ) {
                action::start( "latest" );
                  action::add( "id", $row['latest_media_id'] );
                  action::start( "added" );
                    action::add( "datetime", $row['latest_media_added'] );
                    $timestamp = strtotime( $row['latest_media_added'] );
                    action::add( "period", sys::create_duration( $timestamp, time() ) );
                    $timestamp += ( 60 * 60 ) * sys::timezone();
                    action::add( "altered_datetime", sys::create_datetime( $timestamp ) );
                  action::end();
                  action::start( "children" );
                    self::get_media_children( $row['latest_media_id'] );
                  action::end();
                action::end();
              }
              action::start( "author" );
                action::add( "id", $row['user_id'] );
                action::add( "name", $row['user_name'] );
              action::end();
              action::start( "created" );
                action::add( "datetime", $row['media_gallery_created'] );
                $timestamp = strtotime( $row['media_gallery_created'] );
                action::add( "period", sys::create_duration( $timestamp, time() ) );
                $timestamp += ( 60 * 60 ) * sys::timezone();
                action::add( "altered_datetime", sys::create_datetime( $timestamp ) );
              action::end();
              action::start( "updated" );
                action::add( "datetime", $row['media_gallery_updated'] );
                $timestamp = strtotime( $row['media_gallery_updated'] );
                action::add( "period", sys::create_duration( $timestamp, time() ) );
                $timestamp += ( 60 * 60 ) * sys::timezone();
                action::add( "altered_datetime", sys::create_datetime( $timestamp ) );
              action::end();
            action::end();
          }
        action::end();
      action::end();
    }

    public static function get_media() {
      $media_id = sys::input( "media_id", 0 );
      $media_gallery_id = sys::input( "media_gallery_id", 0 );
      $media_gallery_name = sys::input( "media_gallery_name", "" );
      $get_latest_image = sys::input( "get_latest_image", false );
      $media_gallery = NULL;
      if( ( $media_gallery_id || $media_gallery_name ) && ( ( $media_id && $media_id != "new" ) || $get_latest_image ) ) {
        db::open( TABLE_MEDIA_GALLERIES );
          db::select( "media_gallery_id", "media_gallery_name", "media_gallery_title" );
          if( $media_gallery_id ) {
            db::where( "media_gallery_id", $media_gallery_id );
          }
          if( $media_gallery_name ) {
            db::where( "media_gallery_name", $media_gallery_name );
          }
          db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS );
            db::link( "media_gallery_id" );
          if( $media_id && $media_id != "new" ) {
            db::where( "media_id", $media_id );
          } else if( $get_latest_image ) {
            db::order( "media_order", "ASC" );
            db::limit( 0, 1 );
          }
        $media_gallery = db::result();
        db::clear_result();
        if( !$media_gallery ) {
          sys::message(
            USER_ERROR,
            lang::phrase( "error/media/get_media/invalid_media_gallery_id/title" ),
            lang::phrase( "error/media/get_media/invalid_media_gallery_id/body" )
          );
        }
        $media_gallery_id = $media_gallery['media_gallery_id'];
        if( !$media_id && $get_latest_image ) {
          $media_id = $media_gallery['media_id'];
        }
      }
      if( $media_id == "new" ) {
        $media = true;
      } else if( $media_id ) {
        db::open( TABLE_MEDIA );
          db::where( "media_id", $media_id );
        $media = db::result();
        db::clear_result();
      }
      if( !$media_id || !$media ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/media/get_media/invalid_media_id/title" ),
          lang::phrase( "error/media/get_media/invalid_media_id/body" )
        );
      }
      action::resume( "media" );
        action::start( "media" );
          if( $media_id == "new" ) {
            action::add( "id", "new" );
            action::add( "title", "Untitled" );
          } else {
            db::open( TABLE_MEDIA );
              db::where( "media_id", $media_id );
              db::open( TABLE_USERS );
                db::link( "user_id" );
              db::close();
              if( $media_gallery ) {
                db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS );
                  db::select( "media_order" );
                  db::link( "media_id" );
                  db::where( "media_gallery_id", $media_gallery_id );
                db::close();
              }
              db::limit( 0, 1 );
            while( $row = db::result() ) {
              action::add( "id", $row['media_id'] );
              action::add( "title", $row['media_title'] );
              action::add( "name", $row['media_name'] );
              action::add( "description", $row['media_description'] );
              action::add( "type", $row['media_type'] );
              action::add( "filename", $row['media_filename'] );
              action::add( "extension", $row['media_extension'] );
              action::add( "filesize", $row['media_filesize'] );
              action::add( "mimetype", $row['media_mimetype'] );
              action::add( "width", $row['media_width'] );
              action::add( "height", $row['media_height'] );
              action::add( "private", $row['media_private'] );
              action::start( "comments" );
                action::add( "enabled", $row['media_comments_enabled'] );
              action::end();
              action::add( "cdn", $row['media_cdn_enabled'] );
              action::start( "author" );
                action::add( "id", $row['user_id'] );
                action::add( "name", $row['user_name'] );
              action::end();
              action::start( "created" );
                action::add( "datetime", $row['media_created'] );
                $timestamp = strtotime( $row['media_created'] );
                action::add( "period", sys::create_duration( $timestamp, time() ) );
                $timestamp += ( 60 * 60 ) * sys::timezone();
                action::add( "altered_datetime", sys::create_datetime( $timestamp ) );
              action::end();
              action::start( "updated" );
                action::add( "datetime", $row['media_updated'] );
                $timestamp = strtotime( $row['media_updated'] );
                action::add( "period", sys::create_duration( $timestamp, time() ) );
                $timestamp += ( 60 * 60 ) * sys::timezone();
                action::add( "altered_datetime", sys::create_datetime( $timestamp ) );
              action::end();
              action::start( "children" );
                self::get_media_children( $row['media_id'] );
              action::end();
              if( $media_gallery ) {
                action::start( "gallery" );
                  action::add( "id", $media_gallery['media_gallery_id'] );
                  action::add( "name", $media_gallery['media_gallery_name'] );
                  action::add( "title", $media_gallery['media_gallery_title'] );
                action::end();
                db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS );
                  db::where( "media_gallery_id", $media_gallery_id );
                  db::where( "media_order", $row['media_order'], ">" );
                  db::where( "media_id", $row['media_id'], "!=" );
                  db::order( "media_order", "ASC" );
                  db::open( TABLE_MEDIA );
                    db::link( "media_id" );
                  db::close();
                  db::limit( 0, 1 );
                $next = db::result();
                db::clear_result();
                if( $next ) {
                  action::start( "next" );
                    action::add( "id", $next['media_id'] );
                    action::add( "name", $next['media_name'] );
                    action::add( "title", $next['media_title'] );
                  action::end();
                }
                db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS );
                  db::select( "media_order" );
                  db::where( "media_gallery_id", $media_gallery_id );
                  db::where( "media_order", $row['media_order'], "<" );
                  db::where( "media_id", $row['media_id'], "!=" );
                  db::order( "media_order", "DESC" );
                  db::open( TABLE_MEDIA );
                    db::link( "media_id" );
                  db::close();
                  db::limit( 0, 1 );
                echo "<!-- " . db::select_sql() . " -->\n";
                $previous = db::result();
                db::clear_result();
                if( $previous ) {
                  db::open( TABLE_MEDIA_GALLERY_ASSIGNMENTS, LEFT );
                    db::select_as( "sibling_count" );
                    db::select_count( "media_added" );
                    db::where( "media_gallery_id", $media_gallery_id );
                    db::where( "media_order", $row['media_order'], "<" );
                  db::close();
                  $sibling_count = db::result();
                  db::clear_result();
                  action::start( "previous" );
                    action::add( "id", $previous['media_id'] );
                    action::add( "name", $previous['media_name'] );
                    action::add( "title", $previous['media_title'] );
                    action::add( "count", $sibling_count['sibling_count'] );
                  action::end();
                }
              }
            }
          }
        action::end();
      action::end();
    }

    public static function get_gallery() {
      $media_gallery_id = (int)sys::input( "media_gallery_id", 0 );
      $media_gallery_name = sys::input( "media_gallery_name", "" );
      if( $media_gallery_id == "new" ) {
        $media_gallery = true;
      }
      $get_latest_gallery = sys::input( "get_latest_gallery", false );
      if( ( $media_gallery_id && is_int( $media_gallery_id ) ) || $media_gallery_name || $get_latest_gallery ) {
        db::open( TABLE_MEDIA_GALLERIES );
          if( $media_gallery_id ) {
            db::where( "media_gallery_id", $media_gallery_id );
          }
          if( $media_gallery_name ) {
            db::where( "media_gallery_name", $media_gallery_name );
          }
          if( $get_latest_gallery ) {
            db::where( "media_gallery_private", 0 );
            db::order( "media_gallery_created", "DESC" );
          }
          db::open( TABLE_MEDIA, LEFT );
            db::link( "media_id" );
          db::close();
          db::open( TABLE_USERS, LEFT );
            db::link( "user_id" );
          db::close();
          db::limit( 0, 1 );
        $media_gallery = db::result();
        db::clear_result();
        if( $media_gallery ) {
          $media_gallery_id = $media_gallery['media_gallery_id'];
        }
      }
      if( !$media_gallery_id || !$media_gallery ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/media/get_gallery/invalid_media_gallery_id/title" ),
          lang::phrase( "error/media/get_gallery/invalid_media_gallery_id/body" )
        );
      }
      $media_id = sys::input( "media_id", 0 );
      if( $media_id && $media_id > 0 ) {
        db::open( TABLE_MEDIA );
          db::where( "media_id", $media_id );
        $media = db::result();
        db::clear_result();
        if( !$media ) {
          sys::message(
            USER_ERROR,
            lang::phrase( "error/media/get_gallery/invalid_media_id/title" ),
            lang::phrase( "error/media/get_gallery/invalid_media_id/body" )
          );
        }
      }
      action::resume( "media" );
        action::start( "gallery" );
          if( $media_gallery_id == 'new' ) {
            action::add( "id", "new" );
            action::add( "title", "Untitled" );
          } else {
            action::add( "id", $media_gallery['media_gallery_id'] );
            action::add( "name", $media_gallery['media_gallery_name'] );
            action::add( "title", $media_gallery['media_gallery_title'] );
            action::add( "description", $media_gallery['media_gallery_description'] );
            action::add( "media_count", $media_gallery['media_gallery_count'] );
            action::add( "private", $media_gallery['media_gallery_private'] );
            action::start( "featured" );
              action::add( "id", $media_gallery['media_id'] );
              action::add( "cdn_enabled", $media_gallery['media_cdn_enabled'] );
              action::add( "width", $media_gallery['media_width'] );
              action::add( "height", $media_gallery['media_height'] );
              action::start( "created" );
                $timestamp = strtotime( $media_gallery['media_created'] );
                action::add( "datetime", sys::create_datetime( $timestamp ) );
              action::end();
              action::start( "children" );
                self::get_media_children( $media_gallery['media_id'] );
              action::end();
            action::end();
            action::start( "author" );
              action::add( "id", $media_gallery['user_id'] );
              action::add( "name", $media_gallery['user_name'] );
            action::end();
            action::start( "created" );
              action::add( "datetime", $media_gallery['media_gallery_created'] );
              $timestamp = strtotime( $media_gallery['media_gallery_created'] );
              action::add( "period", sys::create_duration( $timestamp, time() ) );
              $timestamp += ( 60 * 60 ) * sys::timezone();
              action::add( "altered_datetime", sys::create_datetime( $timestamp ) );
            action::end();
            action::start( "updated" );
              action::add( "datetime", $media_gallery['media_gallery_updated'] );
              $timestamp = strtotime( $media_gallery['media_gallery_updated'] );
              action::add( "period", sys::create_duration( $timestamp, time() ) );
              $timestamp += ( 60 * 60 ) * sys::timezone();
              action::add( "altered_datetime", sys::create_datetime( $timestamp ) );
            action::end();
          }
        action::end();
      action::end();
    }

    private static function get_media_children( $media_id ) {
      db::open( TABLE_MEDIA );
        db::where( "media_parent", $media_id );
      while( $row = db::result() ) {
        action::start( "child" );
          action::add( "id", $row['media_id'] );
          action::add( "width", $row['media_width'] );
          action::add( "height", $row['media_height'] );
          action::add( "target_width", $row['media_target_width'] );
          action::add( "target_height", $row['media_target_height'] );
          action::add( "filename", $row['media_filename'] );
          action::add( "cdn_enabled", $row['media_cdn_enabled'] );
          action::start( "created" );
            action::add( "datetime", $row['media_created'] );
            $timestamp = strtotime( $row['media_created'] );
            action::add( "period", sys::create_duration( $timestamp, time() ) );
            $timestamp += ( 60 * 60 ) * sys::timezone();
            action::add( "altered_datetime", sys::create_datetime( $timestamp ) );
          action::end();
        action::end();
      }
    }

    public static function get_media_image() {
      $media_id = sys::input( "media_id", 0 );
      $fill_space = sys::input( "fill_space", false );
      $width = sys::input( "width", 0 );
      $height = sys::input( "height", 0 );
      $output = sys::input( "output", false );
      self::output_media_image( $media_id, $fill_space, $width, $height, $output );
    }

    private static function output_media_image( $media_id, $fill_space, $width, $height, $output = false ) {
      $media = null;
      if( $media_id ) {
        db::open( TABLE_MEDIA );
          db::where( "media_id", $media_id );
        $media = db::result();
        db::clear_result();
      }
      if( !$media ) {
        sys::message(
          NOTFOUND_ERROR,
          lang::phrase( "error/media/output_media_image/not_found/title" ),
          lang::phrase( "error/media/output_media_image/not_found/body" )
        );
      }
      $media_width = $media['media_width'];
      $media_height = $media['media_height'];
      if( $width ) {
        $hscale = $width / $media_width;
      }
      if( $height ) {
        $vscale = $height / $media_height;
      }
      $scale = 1;
      if( $width && $height ) {
        $ratio1 = $width / $height;
        $ratio2 = $media_width / $media_height;
        if( $fill_space ) {
          if( $ratio1 > $ratio2 ) {
            $scale = $hscale;
          } else {
            $scale = $vscale;
          }
        } else {
          if( $ratio1 < $ratio2 ) {
            $scale = $hscale;
          } else {
            $scale = $vscale;
          }
        }
      } else if( $width ) {
        $scale = $hscale;
      } else if( $height ) {
        $scale = $vscale;
      }
      $new_width = round( $media_width * $scale );
      $new_height = round( $media_height * $scale );
      if( $media['media_cdn_enabled'] ) {
        $filename = action::get( "settings/media/cdn_media_dir" ) . "/" . $media['media_filename'];
      } else {
        $year = gmdate( "Y", strtotime( $media['media_created'] ) );
        $month = gmdate( "m", strtotime( $media['media_created'] ) );
        $filename = "uploads/media/" . $year . "/" . $month . "/" . $media['media_id'] . "/" . $media['media_filename'];
      }
      $temp_filename = "uploads/media/temp/" . sys::random_chars( 12 ) . "." . $media['media_filename'];
      $new_filename = "";
      if( $media['media_width'] > $width || $media['media_height'] > $height ) {        
        $new_filename .= ( $width ? $width : "default" ) . "x" . ( $height ? $height : "default" ) . ".";
      }
      $new_filename .= $media['media_filename'];
      $new_filename = str_replace( $media['media_id'] . "-", "", $new_filename );
      $new_filename = $media['media_id'] . "-" . $new_filename;
      $create_new = false;
      db::open( TABLE_MEDIA );
        db::where( "media_parent", $media_id );
        db::where( "media_target_width", $width );
        db::where( "media_target_height", $height );
      $existing_media = db::result();
      if( !$existing_media && $scale < 1 ) {
        $create_new = true;
      } else if( $existing_media ) {
        $filename = action::get( "settings/media/cdn_media_dir" ) . "/" . $existing_media['media_filename'];
        $media = $existing_media;
      }
      if( $media['media_mimetype'] == image_type_to_mime_type( IMAGETYPE_JPEG ) ) {
        $source_image = imagecreatefromjpeg( $filename );
      } else if( $media['media_mimetype'] == image_type_to_mime_type( IMAGETYPE_GIF ) ) {
        $source_image = imagecreatefromgif( $filename );
      } else if( $media['media_mimetype'] == image_type_to_mime_type( IMAGETYPE_PNG ) ) {
        $source_image = imagecreatefrompng( $filename );
      }
      if( $source_image ) {
        if( $create_new ) {
          $new_image = imagecreatetruecolor( $new_width, $new_height );
          imagecopyresampled( $new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $media_width, $media_height );
          imagejpeg( $new_image, $temp_filename, 90 );
          $current_date = gmdate( "Y/m/d H:i:s" );
          db::open( TABLE_MEDIA );
            db::set( "user_id", $media['user_id'] );
            db::set( "media_parent", $media_id );
            db::set( "media_title", $media['media_title'] );
            db::set( "media_name", $media['media_name'] );
            db::set( "media_description", $media['media_description'] );
            db::set( "media_type", "image" );
            db::set( "media_target_width", $width );
            db::set( "media_target_height", $height );
            db::set( "media_created", $current_date );
            db::set( "media_updated", $current_date );
          if( !db::insert() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/media/get_media_image/could_not_create_thumbnail/title" ),
              lang::phrase( "error/media/get_media_image/could_not_create_thumbnail/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
          $thumbnail_id = db::id();
          self::upload_media( $media_id, $thumbnail_id, "image", $temp_filename, $new_filename, false );
        }
        if( $output ) {
          header( "Content-type: image/jpeg" );
          if( $create_new ) {
            imagejpeg( $new_image, NULL, 90 );
          } else {
            imagejpeg( $source_image, NULL, 90 );
          }
        }
        imagedestroy( $new_image );
        imagedestroy( $source_image );
      } else {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/media/get_media_image/invalid_image/title" ),
          lang::phrase( "error/media/get_media_image/invalid_image/body" )
        );
      }
    }

  }

?>
