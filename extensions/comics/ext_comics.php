<?php

/*
Copyright © 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/


    class comics {
		
      public static function hook_account_initialized() {
        $comics_action = sys::input( "comics_action", false, SKIP_GET );
        $actions = array(
          "edit_comic",
          "delete_comics",
          "toggle_publish"
        );
        if( in_array( $comics_action, $actions ) ) {
          call_user_func( "self::$comics_action" );
        }
      }

      public static function query_get_extension_object( $target, $type ) {
        if( $type == "comic" ) {
          action::start( "comic" );
            db::open( TABLE_COMICS );
              db::where( "comic_id", $target );
            $comic = db::result();
            db::clear_result();
            action::add( "id", $comic['comic_id'] );
            action::add( "title", $comic['comic_title'] );
            action::add( "name", $comic['comic_name'] );
          action::end();
        }
      }

      private static function edit_comic() {
        sys::check_return_page();
        $comic_id = sys::input( "comic_id", 0 );
        $comic = NULL;
        if( $comic_id ) {
          db::open( TABLE_COMICS );
            db::select( "user_id", "comic_id", "comic_filename", "comic_cdn_enabled" );
            db::where( "comic_id", $comic_id );
          $comic = db::result();
          db::clear_result();
          if( $comic['user_id'] == action::get( "user/user_id" ) ) {
            if( !auth::test( "comics", "edit_own_comics" ) ) {
              sys::message( AUTHENTICATION_ERROR, lang::phrase( "authentication/denied" ), lang::phrase( "authentication/comics/edit_own_comics/denied" ) );
            }
          } else {
            if( !auth::test( "comics", "edit_comics" ) ) {
              sys::message( AUTHENTICATION_ERROR, lang::phrase( "authentication/denied" ), lang::phrase( "authentication/comics/edit_comics/denied" ) );
            }
          }
        } else {
          if( !auth::test( "comics", "add_comics" ) ) {
            sys::message( AUTHENTICATION_ERROR, lang::phrase( "authentication/denied" ), lang::phrase( "authentication/comics/add_comics/denied" ) );
          }
        }

        $comic_title = sys::input( "comic_title", "" );
        $comic_title = preg_replace( "/[^\w\d\s<>\/\-_&%\$#@\[\]\(\)\?\+\.\^\\\"'{}=,;:|]/si", "", $comic_title );
        $comic_name = sys::input( "comic_name", false );
        if( !$comic_name ) {
          $comic_name = sys::create_tag( $comic_title );
        }
        $comic_description = sys::input( "comic_description", "" );
        $comic_description = preg_replace( "/[^\w\d\s<>\/\-_&%\$#@\[\]\(\)\?\+\.\^\\\"'{}=,;:|]/si", "", $comic_description );
        $comic_published = sys::input( "comic_published", false );
        $manual_publish_date = sys::input( "manual_publish_date", false );
        $publish_timezone_enabled = sys::input( "publish_timezone_enabled", false );
        $comic_category_id = sys::input( "comic_category_id", "" );
        $user_id = (int)sys::input( "user_id", action::get( "user/id" ) );
        if( !$user_id ) {
          $user_id = action::get( "user/id" );
        }
        $editor_id = action::get( "user/id" );
        $comic_file = sys::file( "comic_file" );
        $current_date = sys::create_datetime( time() );

        db::open( TABLE_COMICS );
          db::set( "comic_title", $comic_title );
          db::set( "comic_name", $comic_name );
          db::set( "comic_description", $comic_description );
          if( $manual_publish_date && $comic_published ) {
            if( $publish_timezone_enabled ) {
              $timestamp = strtotime( $comic_published );
              $timestamp -= ( 60 * 60 ) * sys::timezone();
              $comic_published = gmdate( "Y/m/d H:i:s", $timestamp );
            }
            db::set( "comic_published", $comic_published );
          }
          db::set( "comic_category_id", $comic_category_id );
          db::set( "user_id", $user_id );
          db::set( "comic_updated", $current_date );

        $comic_created = false;
        if( $comic_id ) {
          db::where( "comic_id", $comic_id );
          if( !db::update() ) {
            sys::message( "SYSTEM_ERROR", lang::phrase( "error/comics/edit_comic/title" ), lang::phrase( "error/comics/edit_comic/body", db::error() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
          }
        } else {
          db::set( "comic_created", $current_date );
          db::set( "comic_published", $current_date );
          db::set( "comic_status", "draft" );
          if( !db::insert() ) {
            sys::message( "SYSTEM_ERROR", lang::phrase( "error/comics/add_comic/title" ), lang::phrase( "error/comics/add_comic/body", db::error() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
          }
          $comic_id = db::id();
          $comic_created = true;
        }

        if( $comic_file && isset( $comic_file['name'] ) && $comic_file['name'] ) {
          if( !$comic_created ) {
            db::open( TABLE_COMICS );
              db::where( "comic_id", $comic_id );
              db::set( "comic_verified", 0 );
            if( !db::update() ) {
              sys::message(
                SYSTEM_ERROR,
                lang::phrase( "error/comics/actions/edit_comic/unverify_comic/title" ),
                lang::phrase( "error/comics/actions/edit_comic/unverify_comic/body" ),
                __FILE__, __LINE__, __FUNCTION__, __CLASS__
              );
            }
            self::delete_comic_file( $comic['comic_id'], $comic['comic_filename'], $comic['comic_cdn_enabled'] );
          }
          $new_filename = sys::random_chars(12) . "." . $comic_file['name'];
          sys::copy_file( "comic_file", "uploads/comics/temp", $new_filename );
          self::upload_comic( $comic_id, "uploads/comics/temp/" . $new_filename, $comic ? $comic['comic_filename'] : $comic_file['name'] );
        }

        tpl::update_dependency( "comic_list" );
        tpl::update_dependency( "comic_" . $comic_id );

        action::resume( "comics/actions" );
          action::start( "action" );
            action::add( "name", "edit_comic" );
            action::add( "title", lang::phrase( "comics/actions/edit_comic/title" ) );
            action::add( "success", 1 );
            action::add( "message", lang::phrase( "comics/actions/edit_comic/success/body" ) );
          action::end();
        action::end();

        if( action::get( "request/return_page" ) ) {
          if( $comic_created ) {
            $return_page = action::get( "request/return_page" );
            $return_page = str_replace( "[comic_id]", $comic_id, $return_page );
            sys::replace_return_page( $return_page );
          }
          sys::message(
            USER_MESSAGE,
            lang::phrase( "comics/actions/edit_comic/success/title" ),
            lang::phrase( "comics/actions/edit_comic/success/body" )
          );
        }
      }

      private static function toggle_publish() {
        $comic_id = sys::input( "comic_id", 0 );
        $comic = NULL;
        if( $comic_id ) {
          db::open( TABLE_COMICS );
            db::select( "user_id", "comic_status", "comic_published" );
            db::where( "comic_id", $comic_id );
          $comic = db::result();
        } else {
          sys::message( USER_MESSAGE, lang::phrase( "error/comics/actions/toggle_publish/missing_comic_id/title" ), lang::phrase( "error/comics/actions/toggle_publish/missing_comic_id/body" ) );
        }
        if( $comic ) {
          db::open( TABLE_COMICS );
            db::select( "user_id", "comic_status", "comic_published" );
            db::where( "comic_id", $comic_id );
          $comic = db::result();
          db::clear_result();
          if( $comic['comic_status'] == "published" ) {
            if( ( (int)$comic['user_id'] == (int)action::get( "user/id" ) &&
                !auth::test( "comics", "unpublish_own_comics" ) ) ||
                ( (int)$comic['user_id'] != (int)action::get( "user/id" ) &&
                !auth::test( "comics", "unpublish_comics" ) )
            ) {
              sys::message( AUTHENTICATION_ERROR, lang::phrase( "authentication/denied" ), lang::phrase( "authentication/comics/unpublish_comic/denied" ) );
            }
          } else {
            if( ( (int)$comic['user_id'] == (int)action::get( "user/id" ) &&
                !auth::test( "comics", "publish_own_comics" ) ) ||
                ( (int)$comic['user_id'] != (int)action::get( "user/id" ) &&
                !auth::test( "comics", "publish_comics" ) )
            ) {
              sys::message( AUTHENTICATION_ERROR, lang::phrase( "authentication/denied" ), lang::phrase( "authentication/comics/publish_comic/denied" ) );
            }
          }
        } else {
          sys::message( USER_MESSAGE, lang::phrase( "error/comics/actions/toggle_publish/invalid_comic/title" ), lang::phrase( "error/comics/actions/toggle_publish/invalid_comic/body" ) );
        }
        $current_date = gmdate( "Y-m-d H:i:s" );
        db::open( TABLE_COMICS );
          db::where( "comic_id", $comic_id );
          if( $comic['comic_status'] == "published" ) {
            db::set( "comic_status", "unpublished" );
          } else {
            db::set( "comic_status", "published" );
          }
          if( !isset( $post['comic_published'] ) || !strtotime( $post['comic_published'] ) ) {
            db::set( "comic_published", $current_date );
          }
        if( !db::update() ) {
          sys::message( APPLICATION_ERROR, lang::phrase( "error/comics/actions/toggle_publish/could_not_toggle_publish/title" ), lang::phrase( "error/comics/actions/toggle_publish/could_not_toggle_publish/body", db::error() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
        }
        
        tpl::update_dependency( "comic_list" );
        tpl::update_dependency( "comic_" . $comic_id );

        action::resume( "comics/actions" );
          action::start( "action" );
            action::add( "title", lang::phrase( "comics/actions/toggle_publish/title" ) );
            action::add( "name", "toggle_publish" );
            action::add( "success", 1 );
            action::add( "message", lang::phrase( "comics/actions/toggle_publish/success/body" ) );
          action::end();
        action::end();
      }

      private static function delete_comics() {
        sys::check_return_page();
        $comic_ids = sys::input( "comic_ids", array() );
        if( !is_array( $comic_ids ) ) {
          $comic_ids = array( $comic_ids );
        }
        foreach( $comic_ids as $comic_id ) {
          db::open( TABLE_COMICS );
            db::where( "comic_id", $comic_id );
          $comic = db::result();
          db::clear_result();
          if( $comic ) {
            db::open( TABLE_COMICS );
              db::where( "comic_id", $comic_id );
            if( !db::delete() ) {
              sys::message(
                SYSTEM_ERROR,
                lang::phrase( "error/comics/actions/delete_comics/could_not_delete_comic/title" ),
                lang::phrase( "error/comics/actions/delete_comics/could_not_delete_comic/body" ),
                __FILE__, __LINE__, __FUNCTION__, __CLASS__
              );
            }
            self::delete_comic_file( $comic_id, $comic['comic_filename'], $comic['comic_cdn_enabled'] );
            tpl::update_dependency( "comic_" . $comic_id );
          }
        }
        tpl::update_dependency( "comics_list" );
      }

      public static function list_comics() {
        $status_filter = sys::input( "comic_status", "" );
        $author_filter = sys::input( "comic_author", "" );
        $category_filter = sys::input( "comic_category", "" );
        $page = sys::input( "page", 1 );
        $per_page = sys::input( "per_page", 12 );
        db::open( TABLE_COMICS );
          db::select_as( "total_comics" );
          db::select_count( "comic_id" );
          if( $status_filter == "published" ) {
            db::where( "comic_published", sys::create_datetime( time() ), "<=" );
          } else if( !auth::test( "comics", "view_unpublished_comics" ) ) {
            if( auth::test( "comics", "view_own_unpublished_comics" ) ) {
              db::where( "user_id", action::get( "user/id" ) );
            } else {
              db::where( "comic_published", sys::create_datetime( time() ), "<=" );
            }
          }
          db::open( TABLE_COMIC_CATEGORIES, LEFT );
            db::link( "comic_category_id" );
            if( $category_filter ) {
              db::where( "comic_category_name", $category_filter );
            }
          db::close();
          db::open( TABLE_USERS );
            if( $author_filter ) {
              db::where( "user_name", $author_filter );
            }
            db::link( "user_id" );
          db::close();
        $count = db::result();
        db::clear_result();
        $total_comics = $count['total_comics'];

        action::resume( "comics" );
          if( $category_filter ) {
            action::add( "category", $category_filter );
          }
          if( $author_filter ) {
            action::add( "author", $author_filter );
          }
          action::add( "per_page", $per_page );
          action::add( "total_comics", $total_comics );
          action::add( "total_pages", ceil( $total_comics / $per_page ) );
          action::add( "page", $page );
          action::start( "comic_list" );
            db::open( TABLE_COMICS );
              if( $status_filter == "published" ) {
                db::where( "comic_published", sys::create_datetime( time() ), "<=" );
              } else if( !auth::test( "comics", "view_unpublished_comics" ) ) {
                if( auth::test( "comics", "view_own_unpublished_comics" ) ) {
                  db::where( "user_id", action::get( "user/id" ) );
                } else {
                  db::where( "comic_published", sys::create_datetime( time() ), "<=" );
                }
              }
              db::open( TABLE_COMIC_CATEGORIES, LEFT );
                db::link( "comic_category_id" );
                if( $category_filter ) {
                  db::where( "comic_category_name", $category_filter );
                }
              db::close();
              db::order( "comic_created", "DESC" );
              db::group( "comic_id" );
              db::limit( $per_page*($page-1), $per_page );
              db::open( TABLE_USERS );
                if( $author_filter ) {
                  db::where( "user_name", $author_filter );
                }
                db::link( "user_id" );
              db::close();
            while( $row = db::result() ) {
              action::start( "comic" );
                action::add( "id", $row['comic_id'] );
                action::add( "title", $row['comic_title'] );
                action::add( "name", $row['comic_name'] );
                action::add( "description", $row['comic_description'] );
                action::start( "created" );
                  $created = strtotime( $row['comic_created'] );
                  action::add( "timestamp", $created );
                  action::add( "period", sys::create_duration( time(), $created ) );
                  action::add( "datetime", sys::create_datetime( $created ) );
                  $created += sys::timezone() * 60 * 60;
                  action::add( "altered_timestamp", $created );
                  action::add( "altered_datetime", sys::create_datetime( $created ) );
                action::end();
                action::start( "published" );
                  action::add( "published", $row['comic_status'] == 'published' ? 1 : 0 );
                  $published = strtotime( $row['comic_published'] );
                  if( !$published ) {
                    $published = time();
                  } else {
                    action::add( "scheduled", $published > time() ? 1 : 0 );
                  }
                  action::add( "timestamp", $published );
                  action::add( "period", sys::create_duration( time(), $published ) );
                  action::add( "datetime", sys::create_datetime( $published ) );
                  $published += sys::timezone() * 60 * 60;
                  action::add( "altered_timestamp", $published );
                  action::add( "altered_datetime", sys::create_datetime( $published ) );
                action::end();
                action::start( "updated" );
                  $updated = strtotime( $row['comic_updated'] );
                  action::add( "timestamp", $updated );
                  action::add( "period", sys::create_duration( time(), $updated ) );
                  action::add( "datetime", sys::create_datetime( $updated ) );
                  $updated += sys::timezone() * 60 * 60;
                  action::add( "altered_timestamp", $updated );
                  action::add( "altered_datetime", sys::create_datetime( $updated ) );
                action::end();
                action::start( "author" );
                  action::add( "id", $row['user_id'] );
                  action::add( "username", $row['user_name'] );
                  action::add( "email", $row['user_email'] );
                action::end();
                action::start( "category" );
                  if( isset( $row['comic_category_name'] ) ) {
                    action::add( "id", $row['comic_category_id'] );
                    action::add( "name", $row['comic_category_name'] );
                    action::add( "title", $row['comic_category_title'] );
                  } else {
                    action::add( "title", lang::phrase( "comics/categories/uncategorized" ) );
                  }
                action::end();
                action::add( "verified", $row['comic_verified'] );
                action::add( "filename", $row['comic_filename'] );
                action::add( "cdn_enabled", $row['comic_cdn_enabled'] );
                action::add( "status", $row['comic_status'] );
              action::end();
            }
          action::end();
        action::end();
      }

      public static function get_comic() {
        $status_filter = sys::input( "comic_status_filter", "" );
        $category_filter = sys::input( "comic_category_name", "" );
        $comic_id = sys::input( "comic_id", 0 );
        $comic_name = sys::input( "comic_name", "" );
        $latest_comic = sys::input( "latest_comic", 0 );
        $comic_year = sys::input( "comic_year", 0 );
        $comic_month = sys::input( "comic_month", 0 );
        $comic_day = sys::input( "comic_day", 0 );
        if( !$comic_id && !$comic_name && !$latest_comic ) {
          sys::message( USER_ERROR, lang::phrase( "error/comics/get_comic/invalid_identifier/title" ), lang::phrase( "error/comics/get_comic/invalid_identifier/title" ) );
        }
        
        if( $comic_id.'' == "new" ) {
          action::resume( "comics/comic" );
            action::add( "title", "Untitled" );
            action::add( "new", 1 );
            action::start( "author" );
              action::add( "id", action::get( "user/id" ) );
              
            action::end();
          action::end();
        } else {
          action::resume( "comics" );
            action::start( "comic" );
              db::open( TABLE_COMICS );
                if( $status_filter ) {
                  db::where( "comic_status", $status_filter );
                }
                if( $comic_id ) {
                  db::where( "comic_id", $comic_id );
                } else if( $comic_year && $comic_month && $comic_day && $comic_name ) {
                  db::where_year ( "comic_published", $comic_year );
                  db::where_month( "comic_published", $comic_month );
                  db::where_day( "comic_published", $comic_day );
                  db::where( "comic_name", $comic_name );
                }
                db::open( TABLE_COMIC_CATEGORIES, $category_filter ? NONE : LEFT );
                  db::link( "comic_category_id" );
                  if( $category_filter ) {
                    db::where( "comic_category_name", $category_filter );
                  }
                db::close();
                if( $latest_comic ) {
                  db::order( "comic_published", "DESC" );
                  db::limit( 0, 1 );
                }
                db::open( TABLE_USERS );
                  db::link( "user_id" );
                db::close();
              $comic = db::result();
              db::clear_result();

              if( !$comic ) {
                sys::message(
                  NOTFOUND_ERROR,
                  lang::phrase( "error/comics/get_comic/not_found/title" ),
                  lang::phrase( "error/comics/get_comic/not_found/body" )
                );
              }

              if( $comic['comic_status'] != "published" ) {
                if(
                  ( $comic['user_id'] != action::get( "user/id" ) && !auth::test( "comics", "view_unpublished_comics" ) ) ||
                  ( $comic['user_id'] == action::get( "user/id" ) && !auth::test( "comics", "view_own_unpublished_comics" ) )
                ) {
                  sys::message(
                    USER_ERROR,
                    lang::phrase( "error/comics/get_comic/comic_not_published/title" ),
                    lang::phrase( "error/comics/get_comic/comic_not_published/body" )
                  );
                }
                tpl::set_restricted_page();
              }

              action::add( "id", $comic['comic_id'] );
              action::add( "title", $comic['comic_title'] );
              action::add( "name", $comic['comic_name'] );
              action::add( "description", $comic['comic_description'] );
              action::start( "created" );
                $created = strtotime( $comic['comic_created'] );
                action::add( "timestamp", $created );
                action::add( "period", sys::create_duration( time(), $created ) );
                action::add( "datetime", sys::create_datetime( $created ) );
                $created += sys::timezone() * 60 * 60;
                action::add( "altered_timestamp", $created );
                action::add( "altered_datetime", sys::create_datetime( $created ) );
              action::end();
              action::start( "published" );
                action::add( "published", $comic['comic_status'] == 'published' ? 1 : 0 );
                $published = strtotime( $comic['comic_published'] );
                if( !$published ) {
                  $published = time();
                } else {
                  action::add( "scheduled", $published > time() ? 1 : 0 );
                }
                action::add( "timestamp", $published );
                action::add( "period", sys::create_duration( time(), $published ) );
                action::add( "datetime", sys::create_datetime( $published ) );
                $published += sys::timezone() * 60 * 60;
                action::add( "altered_timestamp", $published );
                action::add( "altered_datetime", sys::create_datetime( $published ) );
              action::end();
              action::start( "updated" );
                $updated = strtotime( $comic['comic_updated'] );
                action::add( "timestamp", $updated );
                action::add( "period", sys::create_duration( time(), $updated ) );
                action::add( "datetime", sys::create_datetime( $updated ) );
                $updated += sys::timezone() * 60 * 60;
                action::add( "altered_timestamp", $updated );
                action::add( "altered_datetime", sys::create_datetime( $updated ) );
              action::end();
              action::start( "author" );
                action::add( "id", $comic['user_id'] );
                action::add( "username", $comic['user_name'] );
                action::add( "email", $comic['user_email'] );
              action::end();
              if( isset( $comic['comic_category_name'] ) ) {
                action::start( "category" );
                  action::add( "id", $comic['comic_category_id'] );
                  action::add( "name", $comic['comic_category_name'] );
                  action::add( "title", $comic['comic_category_title'] );
                action::end();
              }
              action::add( "verified", $comic['comic_verified'] );
              action::add( "filename", $comic['comic_filename'] );
              action::add( "status", $comic['comic_status'] );
              action::add( "cdn_enabled", $comic['comic_cdn_enabled'] );

              if( $status_filter == "published" ) {
                /**
                 * Retrieve the comic that was published directly after this one
                 * if there is one.
                 */
                db::open( TABLE_COMICS );
                  db::where( "comic_published", $comic['comic_published'], ">" );
                  db::where( "comic_status", "published" );
                  if( $category_filter ) {
                    db::open( TABLE_COMIC_CATEGORIES, LEFT );
                      db::link( "comic_category_id" );
                      db::where( "comic_category_name", $category_filter );
                    db::close();
                  }
                  db::order( "comic_published", "ASC" );
                  db::limit( 0, 1 );
                $next = db::result();
                db::clear_result();
                if( $next ) {
                  action::start( "next" );
                    action::add( "id", $next['comic_id'] );
                    action::add( "name", $next['comic_name'] );
                    action::add( "title", $next['comic_title'] );
                    action::start( "published" );
                      $published = strtotime( $next['comic_published'] );
                      action::add( "timestamp", $published );
                      action::add( "period", sys::create_duration( time(), $published ) );
                      action::add( "datetime", sys::create_datetime( $published ) );
                      $published += sys::timezone() * 60 * 60;
                      action::add( "altered_timestamp", $published );
                      action::add( "altered_datetime", sys::create_datetime( $published ) );
                    action::end();
                    if( isset( $next['comic_category_name'] ) ) {
                      action::start( "category" );
                        action::add( "id", $next['comic_category_id'] );
                        action::add( "name", $next['comic_category_name'] );
                        action::add( "title", $next['comic_category_title'] );
                      action::end();
                    }
                  action::end();
                }

                /**
                 * Retrieve the comic that was published directly before this one
                 * if there is one
                 */
                db::open( TABLE_COMICS );
                  db::where( "comic_published", $comic['comic_published'], "<" );
                  db::where( "comic_status", "published" );
                  if( $category_filter ) {
                    db::open( TABLE_COMIC_CATEGORIES, LEFT );
                      db::link( "comic_category_id" );
                      db::where( "comic_category_name", $category_filter );
                    db::close();
                  }
                  db::order( "comic_published", "DESC" );
                  db::limit( 0, 1 );
                $previous = db::result();
                db::clear_result();
                if( $previous ) {
                  action::start( "previous" );
                    action::add( "id", $previous['comic_id'] );
                    action::add( "name", $previous['comic_name'] );
                    action::add( "title", $previous['comic_title'] );
                    action::start( "published" );
                      $published = strtotime( $previous['comic_published'] );
                      action::add( "timestamp", $published );
                      action::add( "period", sys::create_duration( time(), $published ) );
                      action::add( "datetime", sys::create_datetime( $published ) );
                      $published += sys::timezone() * 60 * 60;
                      action::add( "altered_timestamp", $published );
                      action::add( "altered_datetime", sys::create_datetime( $published ) );
                    action::end();
                    if( isset( $previous['comic_category_name'] ) ) {
                      action::start( "category" );
                        action::add( "id", $previous['comic_category_id'] );
                        action::add( "name", $previous['comic_category_name'] );
                        action::add( "title", $previous['comic_category_title'] );
                      action::end();
                    }
                  action::end();
                }

                /**
                 * Retrieve the first comic published in this set
                 */
                db::open( TABLE_COMICS );
                  db::where( "comic_status", "published" );
                  if( $category_filter ) {
                    db::open( TABLE_COMIC_CATEGORIES, LEFT );
                      db::link( "comic_category_id" );
                      db::where( "comic_category_name", $category_filter );
                    db::close();
                  }
                  db::order( "comic_published", "ASC" );
                  db::limit( 0, 1 );
                $first = db::result();
                db::clear_result();
                if( $first && $first['comic_id'] != $comic['comic_id'] ) {
                  action::start( "first" );
                    action::add( "id", $first['comic_id'] );
                    action::add( "name", $first['comic_name'] );
                    action::add( "title", $first['comic_title'] );
                    action::start( "published" );
                      $published = strtotime( $first['comic_published'] );
                      action::add( "timestamp", $published );
                      action::add( "period", sys::create_duration( time(), $published ) );
                      action::add( "datetime", sys::create_datetime( $published ) );
                      $published += sys::timezone() * 60 * 60;
                      action::add( "altered_timestamp", $published );
                      action::add( "altered_datetime", sys::create_datetime( $published ) );
                    action::end();
                    if( isset( $first['comic_category_name'] ) ) {
                      action::start( "category" );
                        action::add( "id", $first['comic_category_id'] );
                        action::add( "name", $first['comic_category_name'] );
                        action::add( "title", $first['comic_category_title'] );
                      action::end();
                    }
                  action::end();
                }

                /**
                 * Retrieve the last comic published in this set
                 */
                db::open( TABLE_COMICS );
                  db::where( "comic_status", "published" );
                  if( $category_filter ) {
                    db::open( TABLE_COMIC_CATEGORIES, LEFT );
                      db::link( "comic_category_id" );
                      db::where( "comic_category_name", $category_filter );
                    db::close();
                  }
                  db::order( "comic_published", "DESC" );
                  db::limit( 0, 1 );
                $last = db::result();
                db::clear_result();
                if( $last && $last['comic_id'] != $comic['comic_id'] ) {
                  action::start( "last" );
                    action::add( "id", $last['comic_id'] );
                    action::add( "name", $last['comic_name'] );
                    action::add( "title", $last['comic_title'] );
                    action::start( "published" );
                      $published = strtotime( $last['comic_published'] );
                      action::add( "timestamp", $published );
                      action::add( "period", sys::create_duration( time(), $published ) );
                      action::add( "datetime", sys::create_datetime( $published ) );
                      $published += sys::timezone() * 60 * 60;
                      action::add( "altered_timestamp", $published );
                      action::add( "altered_datetime", sys::create_datetime( $published ) );
                    action::end();
                    if( isset( $last['comic_category_name'] ) ) {
                      action::start( "category" );
                        action::add( "id", $last['comic_category_id'] );
                        action::add( "name", $last['comic_category_name'] );
                        action::add( "title", $last['comic_category_title'] );
                      action::end();
                    }
                  action::end();
                }
              }
            action::end();
          action::end();
        }
      }

      public static function list_categories() {
        action::resume( "comics" );
          action::start( "comic_category_list" );
            db::open( TABLE_COMIC_CATEGORIES );
              db::order( "comic_category_name", "ASC" );
            while( $row = db::result() ) {
              action::start( "category" );
                action::add( "title", $row['comic_category_title'] );
                action::add( "name", $row['comic_category_name'] );
                action::add( "id", $row['comic_category_id'] );
              action::end();
            }
          action::end();
        action::end();
      }

      private static function delete_comic_file( $comic_id, $comic_filename, $comic_cdn_enabled ) {
        db::open( TABLE_COMICS );
          db::where( "comic_id", $comic_id );
        $comic = db::result();
        if( $comic_cdn_enabled ) {
          cdn::delete_object( "comics", $comic_filename );
        }
        $year = gmdate( "Y", strtotime( $comic['comic_created'] ) );
        $month = gmdate( "m", strtotime( $comic['comic_created'] ) );
        if( file_exists( "uploads/comics/" . $year . "/" . $month . "/" . $comic_id . "/" . $comic_filename ) ) {
          unlink( "uploads/comics/" . $year . "/" . $month . "/" . $comic_id . "/" . $comic_filename );
        }
      }

      private static function upload_comic( $comic_id, $old_file, $new_file, $include_id = true ) {
        if( !file_exists( $old_file ) ) {
          sys::message(
            USER_ERROR,
            lang::phrase( "error/comics/upload_comic/invalid_file/title" ),
            lang::phrase( "error/comics/upload_comic/invalid_file/body" )
          );
        }
        db::open( TABLE_COMICS );
          db::where( "comic_id", $comic_id );
        $comic = db::result();
        db::clear_result();
        $comic_verified = false;
        if( CONTENT_ENABLED ) {
          $mfp = fopen( $old_file, "r" );
          if( $mfp ) {
            $new_file = ( $include_id ? $comic_id . "-" : "" ) . $new_file;
            if( cdn::add_object( "comics", $new_file, $mfp ) ) {
              $comic_verified = true;
            }
            fclose( $mfp );
          }
        } else {
          $year = gmdate( "Y", strtotime( $comic['comic_created'] ) );
          $month = gmdate( "m", strtotime( $comic['comic_created'] ) );
          if( !file_exists( "uploads/comics/" . $year ) ) {
            mkdir( "uploads/comics/" . $year, 0777 );
          }
          if( !file_exists( "uploads/comics/" . $year . "/" . $month ) ) {
            mkdir( "uploads/comics/" . $year . "/" . $month, 0777 );
          }
          if( !file_exists( "uploads/comics/" . $year . "/" . $month . "/" . $comic_id ) ) {
            mkdir( "uploads/comics/" . $year . "/" . $month . "/" . $comic_id, 0777 );
          }
          if( copy( $old_file, "uploads/comics/" . $year . "/" . $month . "/" . $comic_id . "/" . $new_file ) ) {
            $comic_verified = true;
          }
        }
        $comic_filesize = filesize( $old_file );
        $name_split = explode( ".", $new_file );
        $comic_extension = $name_split[count($name_split)-1];
        
        $info = getimagesize( $old_file );
        $comic_width = $info[0];
        $comic_height = $info[1];
        $comic_mimetype = $info['mime'];
        
        unlink( $old_file );
        if( $comic_verified ) {
          db::open( TABLE_COMICS );
            db::where( "comic_id", $comic_id );
            db::set( "comic_verified", 1 );
            db::set( "comic_filename", $new_file );
            if( CONTENT_ENABLED ) {
              db::set( "comic_cdn_enabled", 1 );
            }
          if( !db::update() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/comic/upload_comic/verify_uploaded_comic/title" ),
              lang::phrase( "error/comic/upload_comic/verify_uploaded_comic/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
        }
      }
		
    }
?>