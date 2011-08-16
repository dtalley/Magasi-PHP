<?php

/*
Copyright © 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/

    class podcasts {
		
      public static function hook_account_initialized() {
        $podcast_action = sys::input( "podcast_action", false, SKIP_GET );
        if( $podcast_action ) {
          switch( $podcast_action ) {
            case "edit_podcast":
              self::edit_podcast();
              break;
          }
        }
      }

      public static function query_get_extension_object( $target, $type ) {
        if( $type == "podcast" ) {
          echo "<!-- Getting podcast " . $target . " -->\n";
          action::start( "podcast" );
            db::open( TABLE_PODCASTS );
              db::where( "podcast_id", $target );
            $podcast = db::result();
            db::clear_result();
            action::add( "id", $podcast['podcast_id'] );
            action::add( "title", $podcast['podcast_title'] );
            action::add( "name", $podcast['podcast_name'] );
          action::end();
        }
      }

      private static function edit_podcast() {
        sys::check_return_page();
        $podcast_id = sys::input( "podcast_id", 0 );
        if( $podcast_id ) {
          db::open( TABLE_PODCASTS );
            db::select( "user_id" );
            db::where( "podcast_id", $podcast_id );
          $podcast = db::result();
          db::clear_result();
          if( $podcast['user_id'] == action::get( "user/user_id" ) ) {
            if( !auth::test( "podcasts", "edit_own_podcasts" ) ) {
              sys::message( AUTHENTICATION_ERROR, lang::phrase( "authentication/denied" ), lang::phrase( "authentication/podcasts/edit_own_podcasts/denied" ) );
            }
          } else {
            if( !auth::test( "podcasts", "edit_podcasts" ) ) {
              sys::message( AUTHENTICATION_ERROR, lang::phrase( "authentication/denied" ), lang::phrase( "authentication/podcasts/edit_podcasts/denied" ) );
            }
          }
        } else {
          if( !auth::test( "podcasts", "add_podcasts" ) ) {
            sys::message( AUTHENTICATION_ERROR, lang::phrase( "authentication/denied" ), lang::phrase( "authentication/podcasts/add_podcasts/denied" ) );
          }
        }

        $podcast_title = sys::input( "podcast_title", "" );
        $podcast_title = preg_replace( "/[^\w\d\s<>\/\-_&%\$#@\[\]\(\)\?\+\.\^\\\"'{}=,;:|]/si", "", $podcast_title );
        $auto_podcast_name = str_replace( " ", "-", strtolower( $podcast_title ) );
        $podcast_name = sys::input( "podcast_name", false ) ? sys::input( "podcast_name", "" ) : $auto_podcast_name;
        $podcast_name = preg_replace( "/([^a-zA-Z0-9_\-]*?)/", "", $podcast_name );
        $podcast_name = preg_replace( "/(-+?)/", "-", $podcast_name );
        $podcast_description = sys::input( "podcast_description", "" );
        $podcast_description = preg_replace( "/[^\w\d\s<>\/\-_&%\$#@\[\]\(\)\?\+\.\^\\\"'{}=,;:|]/si", "", $podcast_description );
        $podcast_date = sys::input( "podcast_date", "" );
        $podcast_category_name = sys::input( "podcast_category_name", "" );
        $user_id = sys::input( "user_id", "" );

        db::open( TABLE_PODCASTS );
          db::set( "podcast_title", $podcast_title );
          db::set( "podcast_name", $podcast_name );
          db::set( "podcast_description", $podcast_description );
          db::set( "podcast_category_name", $podcast_category_name );

        $podcast_created = false;
        if( $podcast_id ) {
          if( $podcast_date ) {
            db::set( "podcast_date", $podcast_date );
          }
          if( $user_id ) {
            db::set( "user_id", $user_id );
          }
          db::where( "podcast_id", $podcast_id );
          if( !db::update() ) {
            sys::message( "SYSTEM_ERROR", lang::phrase( "error/podcasts/edit_podcast/title" ), lang::phrase( "error/blog/edit_podcast/body", db::error() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
          }
        } else {
          if( !$podcast_date ) {
            db::set( "podcast_date", gmdate( "Y/m/d H:i:s", time() ) );
          }
          if( !$user_id ) {
            db::set( "user_id", action::get( "user/id" ) );
          }
          if( !db::insert() ) {
            sys::message( "SYSTEM_ERROR", lang::phrase( "error/podcasts/add_podcast/title" ), lang::phrase( "error/podcasts/add_podcast/body", db::error() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
          }
          $podcast_id = db::id();
          $podcast_created = true;
        }

        tpl::update_dependency( "podcast_list" );
        tpl::update_dependency( "podcast_" . $podcast_id );

        action::resume( "podcasts/actions" );
          action::start( "action" );
            action::add( "name", "edit_podcast" );
            action::add( "title", lang::phrase( "podcasts/actions/edit_podcast/title" ) );
            action::add( "success", 1 );
            action::add( "message", lang::phrase( "podcasts/actions/edit_podcast/success/body" ) );
          action::end();
        action::end();

        if( action::get( "request/return_page" ) ) {
          if( $podcast_created ) {
            $return_page = action::get( "request/return_page" );
            $return_page = str_replace( "[podcast_id]", $podcast_id, $return_page );
            sys::replace_return_page( $return_page );
          }
          sys::message(
            USER_MESSAGE,
            lang::phrase( "podcasts/actions/edit_podcast/success/title" ),
            lang::phrase( "podcasts/actions/edit_podcast/success/body" )
          );
        }
      }

      public static function list_podcasts() {
        $page = 1;
        $author_filter = sys::input( "podcast_author", "" );
        $category_filter = sys::input( "podcast_category", "" );
        $page = sys::input( "page", 1 );
        $per_page = sys::input( "per_page", 12 );
        db::open( TABLE_PODCASTS );
          db::select_as( "total_podcasts" );
          db::select_count( "podcast_id" );
          if( $category_filter ) {
            db::where( "podcast_category_name", $category_filter );
          }
          db::open( TABLE_USERS );
            if( $author_filter ) {
              db::where( "user_name", $author_filter );
            }
            db::link( "user_id" );
          db::close();
        $count = db::result();
        db::clear_result();
        $total_podcasts = $count['total_podcasts'];
        $podcast_id = sys::input( "podcast_id", 0 );

        action::resume( "podcasts" );
          if( $category_filter ) {
            action::add( "category", $category_filter );
          }
          action::add( "per_page", $per_page );
          action::add( "total_podcasts", $total_podcasts );
          action::add( "total_pages", ceil( $total_podcasts / $per_page ) );
          action::add( "page", $page );
          action::start( "podcast_list" );
            db::open( TABLE_PODCASTS );
              if( $podcast_id ) {
                db::where( "podcast_id", $podcast_id );
              }
              if( $category_filter ) {
                db::where( "podcast_category_name", $category_filter );
              }
              db::order( "podcast_date", "DESC" );
              db::group( "podcast_id" );
              db::limit( $per_page*($page-1), $per_page );
              db::open( TABLE_USERS );
                if( $author_filter ) {
                  db::where( "user_name", $author_filter );
                }
                db::link( "user_id" );
              db::close();
              db::open( TABLE_PODCAST_CATEGORIES );
                db::link( "podcast_category_name" );
              db::close();
            
            while( $row = db::result() ) {
              action::start( "podcast" );
                $row['podcast_timestamp'] = strtotime( $row['podcast_date'] );
                $row['podcast_timestamp'] += ( 60 * 60 ) * sys::timezone();
                action::add( "id", $row['podcast_id'] );
                action::add( "datetime", sys::create_datetime( $row['podcast_timestamp'] ) );
                action::add( "time", gmdate( "g:i A", $row['podcast_timestamp'] ) );
                action::add( "long_date", gmdate( "F jS, Y", $row['podcast_timestamp'] ) );
                action::add( "short_date", gmdate( "n/j/y", $row['podcast_timestamp'] ) );
                action::add( "title", $row['podcast_title'] );
                action::add( "name", $row['podcast_name'] );
                action::add( "description", $row['podcast_description'] );
                action::add( "author_id", $row['user_id'] );
                action::add( "author_username", $row['user_name'] );
                action::add( "author_email", $row['user_email'] );
                action::add( "category_name", $row['podcast_category_name'] );
                action::add( "category_title", $row['podcast_category_title'] );
                if( file_exists( "uploads/podcasts/" . $row['podcast_name'] . ".mp3" ) ) {
                  $filesize = filesize( "uploads/podcasts/" . $row['podcast_name'] . ".mp3" );
                  action::add( "filesize", $filesize );
                }
              action::end();
            }
          action::end();
        action::end();
      }

      public static function get_podcast() {
        $category_filter = sys::input( "podcast_category", "" );
        $podcast_id = sys::input( "podcast_id", 0 );
        $podcast_name = sys::input( "podcast_name", "" );
        $latest_podcast = sys::input( "latest_podcast", 0 );
        if( !$podcast_id && !$podcast_name && !$latest_podcast ) {
          $podcast_id = sys::input( "podcast_id", 0 );
        }
        if( !$podcast_id && !$podcast_name && !$latest_podcast ) {
          sys::message( USER_ERROR, lang::phrase( "error/podcasts/get_podcast/invalid_identifier/title" ), lang::phrase( "error/podcasts/get_podcast/invalid_identifier/title" ) );
        }
        if( $podcast_id.'' == "new" ) {
          action::resume( "podcasts/podcast" );
            $timestamp = time() + ( 60 * 60 ) * sys::timezone();
            action::add( "datetime", gmdate( "Y-m-d g:i:s", time() ) );
            action::add( "time", gmdate( "g:i A", $timestamp ) );
            action::add( "long_date", gmdate( "F jS, Y", $timestamp ) );
            action::add( "title", "Untitled" );
            action::add( "author_username", action::get( "user/user_name" ) );
            action::add( "author_id", action::get( "user/user_id" ) );
            action::add( "new", 1 );
          action::end();
        } else {
          action::resume( "podcasts" );
            action::start( "podcast" );
              db::open( TABLE_PODCASTS );
                if( $category_filter ) {
                  db::where( "podcast_category_name", $category_filter );
                }
                if( $podcast_id ) {
                  db::where( "podcast_id", $podcast_id );
                } else if( $podcast_name ) {
                  db::where( "podcast_name", $podcast_name );
                } else if( $latest_podcast ) {
                  db::order( "podcast_date", "DESC" );
                  db::limit( 0, 1 );
                }
                db::open( TABLE_USERS );
                  db::link( "user_id" );
                db::close();
                db::open( TABLE_PODCAST_CATEGORIES );
                  db::link( "podcast_category_name" );
                db::close();
              $podcast = db::result();
              db::clear_result();
              if( !$podcast ) {
                sys::message(
                  NOTFOUND_ERROR,
                  lang::phrase( "error/podcasts/get_podcast/not_found/title" ),
                  lang::phrase( "error/podcasts/get_podcast/not_found/body" )
                );
              }
              $podcast['podcast_timestamp'] = strtotime( $podcast['podcast_date'] );
              $podcast['podcast_timestamp'] += ( 60 * 60 ) * sys::timezone();
              action::add( "id", $podcast['podcast_id'] );
              action::add( "datetime", $podcast['podcast_date'] );
              action::add( "time", gmdate( "g:i A", $podcast['podcast_timestamp'] ) );
              action::add( "long_date", gmdate( "F jS, Y", $podcast['podcast_timestamp'] ) );
              action::add( "short_date", gmdate( "n/j/y", $podcast['podcast_timestamp'] ) );
              action::add( "title", $podcast['podcast_title'] );
              action::add( "name", $podcast['podcast_name'] );
              action::add( "description", $podcast['podcast_description'] );
              action::add( "formatted_description", self::parse_description( $podcast['podcast_description'] ) );
              action::add( "author_id", $podcast['user_id'] );
              action::add( "author_username", $podcast['user_name'] );
              action::add( "author_email", $podcast['user_email'] );
              action::add( "category_name", $podcast['podcast_category_name'] );
              action::add( "category_title", $podcast['podcast_category_title'] );
            action::end();
          action::end();
        }
      }

      private static function get_podcast_info( $id ) {
        db::open( TABLE_PODCASTS );
          db::where( "podcast_id", $id );
          db::open( TABLE_USERS );
            db::link( "user_id" );
          db::close();
          db::open( TABLE_PODCAST_CATEGORIES );
            db::link( "podcast_category_name" );
          db::close();
        $podcast = db::result();
        db::clear_result();
        return $podcast;
      }

      public static function list_categories() {
        action::resume( "podcasts" );
          action::start( "podcast_category_list" );
            db::open( TABLE_PODCAST_CATEGORIES );
              db::order( "podcast_category_name", "ASC" );
            while( $row = db::result() ) {
              action::start( "category" );
                action::add( "title", $row['podcast_category_title'] );
                action::add( "name", $row['podcast_category_name'] );
              action::end();
            }
          action::end();
        action::end();
      }

      public static function parse_description( $text ) {
        return format::process( EXTENSIONS_DIR . "/blog", "post_formatting", $text );
      }
		
    }
?>