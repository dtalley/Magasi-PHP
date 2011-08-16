<?php

/*
Copyright © 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/

  define( "BLOG_POST_TAG", "blog-post" );

  class blog {

    public static function hook_account_initialized() {
      $blog_action = sys::input( "blog_action", false, SKIP_GET );
      $actions = array(
        "edit_post",
        "delete_posts",
        "toggle_publish",
        "add_tag",
        "delete_tag",
        "add_page",
        "delete_page",
        "reorder_page",
        "add_comment",
        "edit_comment",
        "delete_comment",
        "toggle_approval",
        "toggle_spam",
        "pingback",
        "edit_featured_post",
        "process_featured_posts"
      );
      if( in_array( $blog_action, $actions ) ) {
        call_user_func( "self::$blog_action" );
      }
    }

    public static function query_get_extension_object( $target, $type ) {
      if( $type == "blog_post" ) {
        db::open( TABLE_BLOG_POST_DATA );
          db::where( "blog_post_id", $target );
          db::open( TABLE_BLOG_POSTS );
            db::link( "blog_post_id" );
        $post = db::result();
        db::clear_result();
        if( $post ) {
          action::start( "blog_post" );
            action::add( "id", $target );
            action::add( "name", $post['blog_post_name'] );
            action::add( "title", $post['blog_post_title'] );
          action::end();
        }
      } else if( $type == "blog_comments" ) {
        action::start( "blog_comments" );
          db::open( TABLE_BLOG_POST_DATA );
            db::select( "blog_post_name", "blog_post_title" );
            db::where( "blog_post_id", $target );
            db::open( TABLE_BLOG_POSTS );
              db::select( "blog_post_published" );
              db::link( "blog_post_id" );
          $post = db::result();
          db::clear_result();
          action::add( "id", $target );
          action::add( "title", $post['blog_post_title'] );
          action::add( "name", $post['blog_post_name'] );
          action::start( "published" );
            $published = strtotime( $post['blog_post_published'] );
            action::add( "timestamp", $published );
            action::add( "period", sys::create_duration( time(), $published ) );
            action::add( "datetime", sys::create_datetime( $published ) );
          action::end();
        action::end();
        action::resume( "blog/comment_list" );
          self::get_post_comments( $target );
        action::end();
      }
    }

    private static function edit_post() {
      sys::check_return_page();
      $blog_post_id = sys::input( "blog_post_id", 0 );
      $blog_post_created = false;
      $post = NULL;
      if( $blog_post_id ) {
        db::open( TABLE_BLOG_POSTS );
          db::select( "user_id" );
          db::select( "blog_post_status" );
          db::where( "blog_post_id", $blog_post_id );
          db::open( TABLE_BLOG_FEATURED_POSTS, LEFT );
            db::select( "blog_featured_post_title" );
            db::link( "blog_post_id" );
        $post = db::result();
        db::clear_result();
        if( !auth::test( "blog", "edit_blog_posts" ) ) {
          if( $post['user_id'] != action::get( "user/id" ) || !auth::test( "blog", "edit_own_blog_posts" ) ) {
            auth::deny( "blog", "edit_blog_posts" );
          }
        }
      } else {
        if( !auth::test( "blog", "add_blog_posts" ) ) {
          auth::deny( "blog", "add_blog_posts" );
        }
      }

      $blog_post_page = sys::input( "blog_post_page", 0 );
      $blog_post_title = sys::input( "blog_post_title", "" );
      $blog_post_preface = sys::input( "blog_post_preface", "" );
      $blog_post_body = sys::input( "blog_post_body", "" );
      $blog_post_title = self::clean_text( $blog_post_title );
      $blog_post_preface = self::clean_text( $blog_post_preface );      
      $blog_post_body = self::clean_text( $blog_post_body );
      $auto_blog_post_name = str_replace( " ", "-", strtolower( $blog_post_title ) );
      $auto_blog_post_name = preg_replace( "/[^a-zA-Z0-9\-]/", "", $auto_blog_post_name );
      $blog_post_name = sys::input( "blog_post_name", false ) ? sys::input( "blog_post_name", "" ) : $auto_blog_post_name;
      $blog_post_name = preg_replace( "/[^a-zA-Z0-9\-_'\"%]/", "", $blog_post_name );
      $blog_post_name = preg_replace( "/(-+)/", "-", $blog_post_name );
      $blog_post_source_title = sys::input( "blog_post_source_title", "" );
      $blog_post_source_link = sys::input( "blog_post_source_link", "" );
      $blog_post_comments = sys::input( "blog_post_comments", false ) ? 1 : 0;
      $blog_post_pingbacks = sys::input( "blog_post_pingbacks", false ) ? 1 : 0;
      $current_date = gmdate( "Y/m/d H:i:s", time() );
      $blog_post_published = sys::input( "blog_post_published", false );
      $manual_publish_date = sys::input( "manual_publish_date", false );
      $publish_timezone_enabled = sys::input( "publish_timezone_enabled", false );
      $blog_post_sort = sys::input( "blog_post_sort", false );
      $manual_sort_date = sys::input( "manual_sort_date", false );
      $sort_timezone_enabled = sys::input( "sort_timezone_enabled", false );
      $blog_category_name = sys::input( "blog_category_name", "" );
      $user_id = sys::input( "user_id", "" );
      $editor_id = action::get( "user/id" );

      db::open( TABLE_BLOG_POSTS );
        db::set( "blog_category_name", $blog_category_name );
        db::set( "user_id", $user_id );
        if( $manual_publish_date && $blog_post_published ) {
          if( $publish_timezone_enabled ) {
            $timestamp = strtotime( $blog_post_published );
            $timestamp -= ( 60 * 60 ) * sys::timezone();
            $blog_post_published = gmdate( "Y/m/d H:i:s", $timestamp );
          }
          db::set( "blog_post_published", $blog_post_published );
        }
        if( $manual_sort_date && $blog_post_sort ) {
          if( $sort_timezone_enabled ) {
            $timestamp = strtotime( $blog_post_sort );
            $timestamp -= ( 60 * 60 ) * sys::timezone();
            $blog_post_sort = gmdate( "Y/m/d H:i:s", $timestamp );
          }
          db::set( "blog_post_sort", $blog_post_sort );
        }
      if( $blog_post_id ) {
        db::set( "blog_post_updated", $current_date );
        db::where( "blog_post_id", $blog_post_id );
        if( !db::update() ) {
          sys::message( SYSTEM_ERROR, lang::phrase( "error/blog/edit_post/title" ), lang::phrase( "error/blog/edit_post/body", db::error() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
        }
      } else if( !$blog_post_page ) {
        db::set( "blog_post_created", $current_date );
        db::set( "blog_post_published", $current_date );
        db::set( "blog_post_sort", $current_date );
        db::set( "blog_post_status", "draft" );
        if( !db::insert() ) {
          sys::message( SYSTEM_ERROR, lang::phrase( "error/blog/add_post/title" ), lang::phrase( "error/blog/add_post/body", db::error() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
        }
        $blog_post_created = true;
        $blog_post_id = db::id();
      }


      db::open( TABLE_BLOG_POST_DATA );
        db::set( "blog_post_id", $blog_post_id );
        if( !$blog_post_page ) {
          db::set( "blog_post_title", $blog_post_title );
        }
        if( !$blog_post_page ) {
          db::set( "blog_post_preface", $blog_post_preface );
          db::set( "blog_post_body", $blog_post_body );
        }
        db::set( "blog_post_name", $blog_post_name );
        db::set( "blog_post_source_title", $blog_post_source_title );
        db::set( "blog_post_source_link", $blog_post_source_link );
        db::set( "blog_post_comments", $blog_post_comments );
        db::set( "blog_post_pingbacks", $blog_post_pingbacks );
      if( !$blog_post_created ) {
        db::where( "blog_post_id", $blog_post_id );
        if( !db::update() ) {
          sys::message( SYSTEM_ERROR, lang::phrase( "error/blog/edit_post/title" ), lang::phrase( "error/blog/edit_post/body", db::error() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
        }
      } else if( !$blog_post_page ) {
        if( !db::insert() ) {
          echo db::error();
          exit();
          //sys::message( SYSTEM_ERROR, lang::phrase( "error/blog/add_post/title" ), lang::phrase( "error/blog/add_post/body", db::error() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
        }
      }

      if( $blog_post_page ) {
        db::open( TABLE_BLOG_POST_PAGES );
          db::where( "blog_post_page_id", $blog_post_page );
          db::set( "blog_post_page_body", $blog_post_body );
          db::set( "blog_post_page_title", $blog_post_title );
        if( !db::update() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/blog/edit_blog_page/title" ),
            lang::phrase( "error/blog/edit_blog_page/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      }

      db::open( TABLE_BLOG_POST_SNAPSHOTS );
        db::set( "user_id", $editor_id );
        db::set( "blog_post_snapshot_date", $current_date );
        db::set( "blog_post_id", $blog_post_id );
        db::set( "blog_post_preface", $blog_post_preface );
        db::set( "blog_post_body", $blog_post_body );
        if( $blog_post_page ) {
          db::set( "blog_post_page", $blog_post_page );
        }
      if( !db::insert() ) {
        sys::message (
          SYSTEM_ERROR,
          lang::phrase( "error/blog/actions/edit_post/save_snapshot/title" ),
          lang::phrase( "error/blog/actions/edit_post/save_snapshot/body" )
        );
      }

      //cache::clear( "", "blog/list_posts" );
      //cache::clear( "", "blog/list_hot_posts" );

      tpl::update_dependency( "blog_post_list" );
      tpl::update_dependency( "blog_post_" . $blog_post_id );
      if( $post && $post['blog_featured_post_title'] ) {
        tpl::update_dependency( "blog_featured_post_list" );
        tpl::update_dependency( "blog_featured_post_" . $blog_post_id );
      }

      action::resume( "blog/actions" );
        action::start( "action" );
          action::add( "name", "edit_post" );
          action::add( "title", lang::phrase( "blog/actions/edit_post/title" ) );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "blog/actions/edit_post/success/body" ) );
        action::end();
      action::end();

      if( action::get( "request/return_page" ) ) {
        if( $blog_post_created ) {
          $return_page = action::get( "request/return_page" );
          $return_page = str_replace( "[blog_post_id]", $blog_post_id, $return_page );
          sys::replace_return_page( $return_page );
        }
        sys::message(
          USER_MESSAGE,
          lang::phrase( "blog/actions/edit_post/success/title" ),
          lang::phrase( "blog/actions/edit_post/success/body" )
        );
      }
    }
    
    private static function clean_text( $text ) {
      $text = htmlentities( $text );
      $text = str_replace( "&lsquo;", "'", $text );
      $text = str_replace( "&rsquo;", "'", $text );
      $text = str_replace( "&ldquo;", "\"", $text );
      $text = str_replace( "&rdquo;", "\"", $text );
      $text = str_replace( "&ndash;", "-", $text );
      $text = str_replace( "&mdash;", "-", $text );
      $text = str_replace( "&hellip;", "...", $text );
      $text = html_entity_decode($text);
      $text = preg_replace( "/[^\w\d\s<>\/\-_&%\$#@\[\]\(\)\?\*!\+\.\^\\\"'{}=,;:|]/si", "", $text );
      return $text;
    }

    private static function delete_posts() {
      sys::check_return_page();
      $blog_post_ids = sys::input( "blog_post_id", array() );
      if( !is_array( $blog_post_ids ) ) {
        $blog_post_ids = array( $blog_post_ids );
      }
      foreach( $blog_post_ids as $blog_post_id ) {
        self::process_delete_post( $blog_post_id );
      }
      action::resume( "blog/actions" );
        action::start( "action" );
          action::add( "name", "delete_post" );
          action::add( "title", lang::phrase( "blog/actions/delete_posts/title" ) );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "blog/actions/delete_posts/success/body" ) );
        action::end();
      action::end();

      //cache::clear( "", "blog/list_posts" );
      //cache::clear( "", "blog/list_hot_posts" );

      tpl::update_dependency( "blog_post_list" );
      tpl::update_dependency( "blog_featured_post_list" );

      if( action::get( "request/return_page" ) ) {
        sys::message(
          USER_MESSAGE,
          lang::phrase( "blog/actions/delete_posts/success/title" ),
          lang::phrase( "blog/actions/delete_posts/success/body" )
        );
      }
    }
    
    private static function process_delete_post( $blog_post_id ) {
      db::open( TABLE_BLOG_POSTS );
        db::where( "blog_post_id", $blog_post_id );
      $post = db::result();
      db::clear_result();
      if( !$post ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/blog/delete_posts/invalid_post/title" ),
          lang::phrase( "error/blog/delete_posts/invalid_post/body" )
        );
      }
      if( !auth::test( "blog", "delete_posts" ) ) {
        if( $post['user_id'] != action::get( "user/id" ) || !auth::test( "blog", "delete_own_blog_posts" ) ) {
          auth::deny( "blog", "delete_posts" );
        }
      }
      //Delete the post itself
      db::open( TABLE_BLOG_POSTS );
        db::where( "blog_post_id", $blog_post_id );
      if( !db::delete() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/blog/actions/delete_posts/could_not_delete_post/title" ),
          lang::phrase( "error/blog/actions/delete_posts/could_not_delete_post/body" ),
          __FILE__, __LINE__, __FUNCTION__, __CLASS__
        );
      }
      //Delete all of the post's comments
      db::open( TABLE_BLOG_COMMENTS );
        db::where( "blog_post_id", $blog_post_id );
      if( !db::delete() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/blog/actions/delete_posts/could_not_delete_post_comments/title" ),
          lang::phrase( "error/blog/actions/delete_posts/could_not_delete_post_comments/body" ),
          __FILE__, __LINE__, __FUNCTION__, __CLASS__
        );
      }
      //Delete the featured post entry for this post
      db::open( TABLE_BLOG_FEATURED_POSTS );
        db::where( "blog_post_id", $blog_post_id );
      if( !db::delete() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/blog/actions/delete_posts/could_not_delete_featured_post/title" ),
          lang::phrase( "error/blog/actions/delete_posts/could_not_delete_featured_post/body" ),
          __FILE__, __LINE__, __FUNCTION__, __CLASS__
        );
      }
      //Delete all of the post's snapshots
      db::open( TABLE_BLOG_POST_SNAPSHOTS );
        db::where( "blog_post_id", $blog_post_id );
      if( !db::delete() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/blog/actions/delete_posts/could_not_delete_post_snapshots/title" ),
          lang::phrase( "error/blog/actions/delete_posts/could_not_delete_post_snapshots/body" ),
          __FILE__, __LINE__, __FUNCTION__, __CLASS__
        );
      }

      assoc::clear_associations( "blog_post", $blog_post_id );
      assoc::clear_associations( "blog_comments", $blog_post_id );

      tpl::delete_dependency( "blog_post_" . $blog_post_id );
      tpl::delete_dependency( "blog_featured_post_" . $blog_post_id );
    }

    private static function toggle_publish() {
      $blog_post_id = sys::input( "blog_post_id", 0 );
      $post = null
      if( $blog_post_id ) {
        db::open( TABLE_BLOG_POSTS );
          db::select( "user_id", "blog_post_status", "blog_post_published" );
          db::where( "blog_post_id", $blog_post_id );
        $post = db::result();
        db::clear_result();
      }
      if( !$post ) {
        sys::message( 
          USER_MESSAGE, 
          lang::phrase( "error/blog/toggle_publish/missing_id/title" ), 
          lang::phrase( "error/blog/toggle_publish/missing_id/body" )
        );
      }
      if( $post['blog_post_status'] == "published" ) {
        if( !auth::test( "blog", "unpublish_blog_posts" ) ) {
          if( $post['user_id'] != action::get( "user/id" ) || !auth::test( "unpublish_own_blog_posts" ) ) {
            auth::deny( "blog", "unpublish_blog_posts" );
          }
        }
      } else if( $post['blog_post_status'] == "draft" || $post['blog_post_status'] == "unpublished" ) {
        if( !auth::test( "blog", "publish_blog_posts" ) ) {
          if( $post['user_id'] != action::get( "user/id" ) || !auth::test( "publish_own_blog_posts" ) ) {
            auth::deny( "blog", "publish_blog_posts" );
          }
        }
      }
      $current_date = gmdate( "Y-m-d H:i:s" );
      db::open( TABLE_BLOG_POSTS );
        db::where( "blog_post_id", $blog_post_id );
        if( $post['blog_post_status'] == "published" ) {
          db::set( "blog_post_status", "unpublished" );
        } else {
          db::set( "blog_post_status", "published" );
        }
        if( !isset( $post['blog_post_published'] ) || !strtotime( $post['blog_post_published'] ) || $post['blog_post_published'] == $post['blog_post_created'] ) {
          db::set( "blog_post_published", $current_date );
        }
        db::set( "blog_post_sort", $current_date );
      if( !db::update() ) {
        sys::message( APPLICATION_ERROR, lang::phrase( "error/blog/post/toggle_publish/title" ), lang::phrase( "error/blog/post/toggle_publish/body", db::error() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
      }

      //cache::clear( "", "blog/list_posts" );
      //cache::clear( "", "blog/list_hot_posts" );

      tpl::update_dependency( "blog_post_list" );
      tpl::update_dependency( "blog_post_" . $blog_post_id );
      tpl::update_dependency( "blog_hot_post_list" );
      
      action::resume( "blog/blog_action" );
        action::add( "success", 1 );
        action::add( "message", lang::phrase( "blog/post/toggle_publish/success" ) );
      action::end();
    }

    private static function add_tag() {
      sys::check_return_page();
      $blog_post_id = sys::input( "blog_post_id", 0 );
      $post = null;
      if( $blog_post_id ) {
        db::open( TABLE_BLOG_POSTS );
          db::select( "user_id" );
          db::select( "blog_post_status" );
          db::where( "blog_post_id", $blog_post_id );
          db::open( TABLE_BLOG_FEATURED_POSTS, LEFT );
            db::select( "blog_featured_post_title" );
            db::link( "blog_post_id" );
        $post = db::result();
        db::clear_result();        
      }
      if( !$post ) {
        sys::message( USER_ERROR, lang::phrase( "error/blog/add_tag/missing_post_id/title" ), lang::phrase( "error/blog/add_tag/missing_post_id/body" ) );
      }
      if( !auth::test( "blog", "add_tags" ) ) {
        if( $post['user_id'] != action::get( "user/id" ) || !auth::test( "blog", "add_tags_to_own" ) ) {
          auth::deny( "blog", "add_tags" );
        }
      }
      if( $blog_post_id ) {
        $blog_post_tag_title = sys::input( "blog_post_tag_title", "" );
        if( $blog_post_tag_title ) {
          $blog_post_tag_name = str_replace( " ", "-", strtolower( $blog_post_tag_title ) );
          $blog_post_tag_name = preg_replace( "/([^a-zA-Z0-9\-]*?)/", "", $blog_post_tag_name );
          $blog_post_tag_name = preg_replace( "/(-+?)/", "-", $blog_post_tag_name );

          db::open( TABLE_BLOG_POST_TAGS );
            db::set( "blog_post_id", $blog_post_id );
            db::set( "blog_post_tag_title", $blog_post_tag_title );
            db::set( "blog_post_tag_name", $blog_post_tag_name );
          if( !db::insert() ) {
            sys::message( APPLICATION_ERROR, lang::phrase( "error/blog/tag/add_tag/title" ), lang::phrase( "error/blog/tag/add_tag/body", db::error() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
          }
        } else {
          sys::message( USER_MESSAGE, lang::phrase( "error/blog/add_tag/missing_title/title" ), lang::phrase( "error/blog/add_tag/missing_title/body" ) );
        }
      } else {
        sys::message( USER_MESSAGE, lang::phrase( "error/blog/post/no_id/title" ), lang::phrase( "error/blog/post/no_id/body" ) );
      }

      //cache::clear( "", "blog/list_posts" );
      //cache::clear( "", "blog/list_tags" );

      tpl::update_dependency( "blog_post_list" );
      tpl::update_dependency( "blog_post_" . $blog_post_id . "_tag_list" );
      tpl::update_dependency( "master_tag_list" );

      action::resume( "blog/actions" );
        action::start( "action" );
          action::add( "name", "add_tag" );
          action::add( "title", lang::phrase( "blog/actions/add_tag/title" ) );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "blog/actions/add_tag/success/body" ) );
        action::end();
      action::end();
    }

    private static function delete_tag() {
      $blog_post_id = sys::input( "blog_post_id", 0 );
      $post = null;
      if( $blog_post_id ) {
        db::open( TABLE_BLOG_POSTS );
          db::select( "user_id" );
          db::select( "blog_post_status" );
          db::where( "blog_post_id", $blog_post_id );
          db::open( TABLE_BLOG_FEATURED_POSTS, LEFT );
            db::select( "blog_featured_post_title" );
            db::link( "blog_post_id" );
        $post = db::result();
        db::clear_result();        
      }
      if( !$post ) {
        sys::message( USER_ERROR, lang::phrase( "error/blog/add_tag/missing_post_id/title" ), lang::phrase( "error/blog/add_tag/missing_post_id/body" ) );
      }
      if( !auth::test( "blog", "delete_tags" ) ) {
        if( $post['user_id'] != action::get( "user/id" ) || !auth::test( "blog", "delete_tags_from_own" ) ) {
          auth::deny( "blog", "delete_tags" );
        }
      }
      $blog_post_tag_id = sys::input( "blog_post_tag_id", " " );
      if( $blog_post_tag_id ) {
        db::open( TABLE_BLOG_POST_TAGS );
          db::where( "blog_post_tag_name", $blog_post_tag_id );
          db::where( "blog_post_id", $blog_post_id );
        if( !db::delete() ) {
          sys::message( APPLICATION_ERROR, lang::phrase( "error/blog/tag/delete_tag/title" ), lang::phrase( "error/blog/tag/delete_tag/body", db::error() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
        }
      } else {
        sys::message( USER_MESSAGE, lang::phrase( "error/blog/delete_tag/missing_id/title" ), lang::phrase( "error/blog/delete_tag/missing_id/body" ) );
      }

      //cache::clear( "", "blog/list_posts" );
      //cache::clear( "", "blog/list_tags" );
      
      tpl::update_dependency( "blog_post_" . $blog_post_id );
      tpl::update_dependency( "blog_post_" . $blog_post_id . "_tag_list" );
      tpl::update_dependency( "master_tag_list" );

      action::resume( "blog/actions" );
        action::start( "action" );
          action::add( "name", "delete_tag" );
          action::add( "title", lang::phrase( "blog/actions/delete_tag/title" ) );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "blog/actions/delete_tag/success/body" ) );
        action::end();
      action::end();
    }

    private static function add_page() {
      $blog_post_id = sys::input( "blog_post_id", 0 );
      $post = null;
      if( $blog_post_id ) {
        db::open( TABLE_BLOG_POSTS );
          db::select( "user_id" );
          db::select( "blog_post_status" );
          db::where( "blog_post_id", $blog_post_id );
        $post = db::result();
        db::clear_result();
      }
      if( !$post ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/blog/add_page/missing_post_id/title" ),
          lang::phrase( "error/blog/add_page/missing_post_id/body" )
        );
      }
      if( !auth::test( "blog", "edit_blog_posts" ) ) {
        if( $post['user_id'] != action::get( "user/id" ) || !auth::test( "blog", "edit_own_blog_posts" ) ) {
          auth::deny( "blog", "edit_blog_posts" );
        }
      }        
      db::open( TABLE_BLOG_POST_PAGES );
        db::where( "blog_post_id", $blog_post_id );
        db::order( "blog_post_page_order", "DESC" );
        db::limit( 0, 1 );
      $last_page = db::result();
      if( $last_page ) {
        $blog_post_page_order = $last_page['blog_post_page_order'] + 1;
      } else {
        $blog_post_page_order = 1;
      }

      $blog_post_page_title = sys::input( "blog_post_page_title", "" );
      db::open( TABLE_BLOG_POST_PAGES );
        db::set( "blog_post_id", $blog_post_id );
        db::set( "blog_post_page_title", $blog_post_page_title );
        db::set( "blog_post_page_body", "Content here..." );
        db::set( "blog_post_page_order", $blog_post_page_order );
      if( !db::insert() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/blog/add_page/title" ),
          lang::phrase( "error/blog/add_page/body" ),
          __FILE__, __LINE__, __FUNCTION__, __CLASS__
        );
      }

      tpl::update_dependency( "blog_post_" . $blog_post_id );

      action::resume( "blog/actions" );
        action::start( "action" );
          action::add( "name", "add_page" );
          action::add( "title", lang::phrase( "blog/actions/add_page/title" ) );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "blog/actions/add_page/success/body" ) );
        action::end();
      action::end();
    }

    private static function delete_page() {
      $blog_post_id = sys::input( "blog_post_id", 0 );
      $post = null;
      if( $blog_post_id ) {
        db::open( TABLE_BLOG_POSTS );
          db::select( "user_id" );
          db::select( "blog_post_status" );
          db::where( "blog_post_id", $blog_post_id );
        $post = db::result();
        db::clear_result();
      }
      if( !$post ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/blog/delete_page/missing_post_id/title" ),
          lang::phrase( "error/blog/delete_page/missing_post_id/body" )
        );
      }
      if( !auth::test( "blog", "edit_blog_posts" ) ) {
        if( $post['user_id'] != action::get( "user/id" ) || !auth::test( "blog", "edit_own_blog_posts" ) ) {
          auth::deny( "blog", "edit_blog_posts" );
        }
      }
      $blog_post_page_id = sys::input( "blog_post_page_id", 0 );
      db::open( TABLE_BLOG_POST_PAGES );
        db::where( "blog_post_id", $blog_post_id );
        db::where( "blog_post_page_id", $blog_post_page_id );
      $page = db::result();
      db::open( TABLE_BLOG_POST_PAGES );
        db::where( "blog_post_id", $blog_post_id );
        db::where( "blog_post_page_order", $page['blog_post_page_order'], ">" );
        db::set( "blog_post_page_order", "blog_post_page_order-1" );
      if( !db::update() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/blog/delete_page/could_not_reorder_pages/title" ),
          lang::phrase( "error/blog/delete_page/could_not_reorder_pages/body" ),
          __FILE__, __LINE__, __FUNCTION__, __CLASS__
        );
      }

      db::open( TABLE_BLOG_POST_PAGES );
        db::where( "blog_post_id", $blog_post_id );
        db::where( "blog_post_page_id", $blog_post_page_id );
      if( !db::delete() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/blog/delete_page/could_not_delete_page/title" ),
          lang::phrase( "error/blog/delete_page/could_not_delete_page/body" ),
          __FILE__, __LINE__, __FUNCTION__, __CLASS__
        );
      }

      tpl::update_dependency( "blog_post_" . $blog_post_id );

      action::resume( "blog/actions" );
        action::start( "action" );
          action::add( "name", "delete_page" );
          action::add( "title", lang::phrase( "blog/actions/delete_page/title" ) );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "blog/actions/delete_page/success/body" ) );
        action::end();
      action::end();
    }

    private static function reorder_page() {
      $blog_post_page_id = sys::input( "blog_post_page_id", 0 );
      $page = null;
      if( $blog_post_page_id ) {
        db::open( TABLE_BLOG_POST_PAGES );
          db::where( "blog_post_page_id", $blog_post_page_id );
          db::open( TABLE_BLOG_POSTS );
            db::link( "blog_post_id" );
        $page = db::result();
      }
      if( !$page ) {
        sys::message(
          NOTFOUND_ERROR,
          lang::phrase( "error/blog/reorder_page/page_not_found/title" ),
          lang::phrase( "error/blog/reorder_page/page_not_found/body" )
        );
      }
      if( !auth::test( "blog", "edit_blog_posts" ) ) {
        if( $page['user_id'] != action::get( "user/id" ) || !auth::test( "blog", "edit_own_blog_posts" ) ) {
          auth::deny( "blog", "edit_blog_posts" );
        }
      }
      if( $page['blog_post_page_order'] == 0 ) {
        db::open( TABLE_BLOG_POST_PAGES );
          db::where( "blog_post_id", $page['blog_post_id'] );
          db::order( "blog_post_page_id", "ASC" );
        $current_page = 1;
        while( $row = db::result() ) {
          db::open( TABLE_BLOG_POST_PAGES );
            db::where( "blog_post_page_id", $row['blog_post_page_id'] );
            db::set( "blog_post_page_order", $current_page );
          if( !db::update() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/blog/reorder_page/could_not_set_initial_order/title" ),
              lang::phrase( "error/blog/reorder_page/could_not_set_initial_order/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
          if( $row['blog_post_page_id'] == $page['blog_post_page_id'] ) {
            $page['blog_post_page_order'] = $current_page;
          }
          $current_page++;
        }
      }
      $blog_post_page_order = (int)sys::input( "blog_post_page_order", 0 );
      if( $blog_post_page_order == -1 ) {
        db::open( TABLE_BLOG_POST_PAGES );
          db::where( "blog_post_id", $page['blog_post_id'] );
          db::where( "blog_post_page_order", $page['blog_post_page_order'], "<" );
          db::order( "blog_post_page_order", "DESC" );
          db::limit( 0, 1 );
        $previous = db::result();
        db::clear_result();
        if( $previous ) {
          db::open( TABLE_BLOG_POST_PAGES );
            db::where( "blog_post_page_id", $page['blog_post_page_id'] );
            db::set( "blog_post_page_order", $previous['blog_post_page_order'] );
          if( !db::update() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/blog/reorder_page/could_not_reorder_target_page/title" ),
              lang::phrase( "error/blog/reorder_page/could_not_reorder_target_page/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
          db::open( TABLE_BLOG_POST_PAGES );
            db::where( "blog_post_page_id", $previous['blog_post_page_id'] );
            db::set( "blog_post_page_order", $page['blog_post_page_order'] );
          if( !db::update() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/blog/reorder_page/could_not_reorder_collateral_page/title" ),
              lang::phrase( "error/blog/reorder_page/could_not_reorder_collateral_page/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
        } else {
          sys::action( "blog", "reorder_page", 0 );
          return false;
        }
      } else if( $blog_post_page_order == 1 ) {
        db::open( TABLE_BLOG_POST_PAGES );
          db::where( "blog_post_id", $page['blog_post_id'] );
          db::where( "blog_post_page_order", $page['blog_post_page_order'], ">" );
          db::order( "blog_post_page_order", "ASC" );
          db::limit( 0, 1 );
        $next = db::result();
        db::clear_result();
        if( $next ) {
          db::open( TABLE_BLOG_POST_PAGES );
            db::where( "blog_post_page_id", $page['blog_post_page_id'] );
            db::set( "blog_post_page_order", $next['blog_post_page_order'] );
          if( !db::update() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/blog/reorder_page/could_not_reorder_target_page/title" ),
              lang::phrase( "error/blog/reorder_page/could_not_reorder_target_page/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
          db::open( TABLE_BLOG_POST_PAGES );
            db::where( "blog_post_page_id", $next['blog_post_page_id'] );
            db::set( "blog_post_page_order", $page['blog_post_page_order'] );
          if( !db::update() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/blog/reorder_page/could_not_reorder_collateral_page/title" ),
              lang::phrase( "error/blog/reorder_page/could_not_reorder_collateral_page/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
        } else {
          sys::action( "blog", "reorder_page", 0 );
          return false;
        }
      }
      tpl::update_dependency( "blog_post_" . $blog_post_id );
      sys::action( "blog", "reorder_page", 1 );
      return true;
    }

    private static function add_comment() {
      if( !auth::test( "blog", "add_comments" ) ) {
        auth::deny( "blog", "add_comments" );
      }

      $blog_post_id = sys::input( "blog_post_id", 0 );
      $blog_comment_parent = sys::input( "blog_comment_parent", 0 );
      $blog_comment_author = sys::input( "blog_comment_author", "" );
      $blog_comment_author_email = sys::input( "blog_comment_author_email", "" );
      $blog_comment_body = sys::input( "blog_comment_body", "" );
      $blog_comment_body = str_replace( "\\", "\\\\", $blog_comment_body );
      $blog_comment_body = preg_replace( "/[^\^\w\d\s<>\/\-_&%\$#@[\]()?+.\\\\\"'{}=,;:|!*]/si", "", $blog_comment_body );
      $user_id = sys::input( "user_id", ANONYMOUS );
      if( !$user_id ) {
        $user_id = ANONYMOUS;
      }

      if( !$blog_comment_author ) {
        sys::message( USER_ERROR, lang::phrase( "error/blog/comment/error" ), lang::phrase( "error/blog/comment/add_comment/missing_author" ) );
      }
      if( !$blog_comment_author_email ) {
        sys::message( USER_ERROR, lang::phrase( "error/blog/comment/error" ), lang::phrase( "error/blog/comment/add_comment/missing_author_email" ) );
      }
      if( !$blog_comment_body ) {
        sys::message( USER_ERROR, lang::phrase( "error/blog/comment/error" ), lang::phrase( "error/blog/comment/add_comment/missing_body" ) );
      }

      db::open( TABLE_BLOG_POST_DATA );
        db::where( "blog_post_id", $blog_post_id );
        db::open( TABLE_BLOG_POSTS );
          db::link( "blog_post_id" );
      $post = db::result();
      db::clear_result();
      $timestamp = strtotime( $post['blog_post_published'] );
      $timestamp += ( 60 * 60 ) * sys::timezone();
      $blog_post_permalink = "http://" . action::get( "settings/site_domain" ) . "/" . action::get( "settings/script_path" );
      $blog_post_permalink .= "/" . gmdate( "Y", $timestamp ) . "/" . gmdate( "n", $timestamp ) . "/" . gmdate( "j", $timestamp );
      $blog_post_permalink .= "/" . $post['blog_post_name'];

      db::open( TABLE_BLOG_COMMENTS );
        db::set( "blog_post_id", $blog_post_id );
        db::set( "blog_comment_parent", $blog_comment_parent );
        db::set( "blog_comment_author", $blog_comment_author );
        db::set( "blog_comment_author_email", $blog_comment_author_email );
        db::set( "blog_comment_body", $blog_comment_body );
        db::set( "blog_comment_date", "UTC_TIMESTAMP()", false );
        db::set( "blog_comment_author_ip", isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : "" );
        db::set( "blog_comment_author_agent", isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : "" );
        db::set( "blog_comment_author_referrer", isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : "" );
        db::set( "user_id", $user_id );
      if( !db::insert() ) {
        sys::message( APPLICATION_ERROR, lang::phrase( "error/blog/comment/add_comment/title" ), lang::phrase( "error/blog/comment/add_comment/body", db::error() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
      }

      $blog_comment_id = db::id();
      $blog_comment_approved = 1;
      $blog_comment_spam = 0;

      action::start( "add_comment" );
        action::add( "id", $blog_comment_id );
        action::add( "body", $blog_comment_body );
        action::add( "date", time() );
        action::add( "author", $blog_comment_author );
        action::add( "author_email", $blog_comment_author_email );
        action::add( "permalink", $blog_post_permalink );
        action::add( "author_ip", isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : "" );
        action::add( "author_agent", isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : "" );
        action::add( "author_referrer", isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : "" );
      action::end();

      sys::hook( "check_spam", "blog_comment", "add_comment" );

      if( action::get( "check_spam_response/spam" ) == "true" ) {
        $blog_comment_approved = 0;
        $blog_comment_spam = 1;
      }

      db::open( TABLE_BLOG_COMMENTS );
        db::where( "blog_comment_author_email", $blog_comment_author_email );
        db::where( "blog_comment_approved", 1 );
        db::order( "blog_comment_id", "DESC" );
        db::limit( 0, 1 );
      if( !db::result() ) {
        $blog_comment_approved = 0;
      }

      db::open( TABLE_BLOG_COMMENTS );
        db::where( "blog_comment_id", $blog_comment_id );
        db::set( "blog_comment_approved", $blog_comment_approved );
        db::set( "blog_comment_spam", $blog_comment_spam );
      if( !db::update() ) {
        sys::message( APPLICATION_ERROR, lang::phrase( "error/blog/comment/add_comment/title" ), lang::phrase( "error/blog/comment/add_comment/body", db::error() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
      }

      if( $blog_comment_spam || !$blog_comment_approved ) {
        action::resume( "request" );
          action::add( "return_text", lang::phrase( "blog/post/return_to_post" ) );
          action::add( "return_page", action::get( "request/self" ) );
        action::end();
      }
      if( $blog_comment_spam ) {
        sys::message( USER_MESSAGE, lang::phrase( "blog/comment/add_comment/marked_as_spam/title" ), lang::phrase( "blog/comment/add_comment/marked_as_spam/body" ) );
      } else if( !$blog_comment_approved ) {
        sys::message( USER_MESSAGE, lang::phrase( "blog/comment/add_comment/needs_approval/title" ), lang::phrase( "blog/comment/add_comment/needs_approval/body" ) );
      }

      action::resume( "blog/actions" );
        action::start( "action" );
          action::add( "name", "add_comment" );
          action::add( "title", lang::phrase( "blog/actions/add_comment/title" ) );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "blog/actions/add_comment/success/body" ) );
        action::end();
      action::end();

      //cache::clear( "", "blog/list_posts" );
      //cache::clear( "", "blog/list_hot_posts" );

      tpl::update_dependency( "blog_post_" . $blog_post_id . "_comment_list" );
      tpl::update_dependency( "blog_hot_post_list" );

      $return_page = sys::input( "return_page", "" );
      if( $return_page ) {
        action::resume( "request" );
          action::add( "return_text", lang::phrase( "blog/add_comment/success/return" ) );
          action::add( "return_page", $return_page . "#" . $blog_comment_id );
        action::end();
        sys::message( USER_MESSAGE, lang::phrase( "blog/add_comment/success/title" ), lang::phrase( "blog/add_comment/success/body" ) );
      }
    }

    private static function edit_comment() {
      sys::check_return_page();
      $blog_comment_id = sys::input( "blog_comment_id", 0 );
      $delete_comment = sys::input( "blog_comment_delete", false );
      db::open( TABLE_BLOG_COMMENTS );
        db::where( "blog_comment_id", $blog_comment_id );
      $comment = db::result();
      db::clear_result();
      $user_id = action::get( "user/id" );
      if( !auth::test( "blog", "edit_comments" ) ) {
        if( $comment['user_id'] != $user_id || !auth::test( "blog", "edit_own_comments" ) ) {
          auth::deny( "blog", "edit_comments" );
        }
      }
      $blog_comment_body = sys::input( "blog_comment_body", "" );
      $blog_comment_body = str_replace( "\\", "\\\\", $blog_comment_body );
      $blog_comment_body = preg_replace( "/[^\w\d\s<>\/\-_&%\$#@[\]()?+.\^\\\\\"'{}=,;:|!*]/si", "", $blog_comment_body );

      db::open( TABLE_BLOG_COMMENTS );
        db::where( "blog_comment_id", $blog_comment_id );
      if( $delete_comment ) {
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/blog/edit_delete_comment/title" ),
            lang::phrase( "error/blog/edit_delete_comment/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        self::delete_comment_children( $blog_comment_id );
      } else {
        db::set( "blog_comment_body", $blog_comment_body );
        if( !db::update() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/blog/edit_comment/title" ),
            lang::phrase( "error/blog/edit_comment/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      }

      tpl::update_dependency( "blog_hot_post_list" );
      tpl::update_dependency( "blog_post_" . $comment['blog_post_id'] . "_comment_list" );

      action::resume( "blog/actions" );
        action::start( "action" );
          action::add( "name", "edit_comment" );
          action::add( "title", lang::phrase( "blog/actions/edit_comment/title" ) );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "blog/actions/edit_comment/success/body" ) );
        action::end();
      action::end();

      if( action::get( "request/return_page" ) ) {
        sys::message(
          USER_MESSAGE,
          lang::phrase( "blog/actions/edit_comment/success/title" ),
          lang::phrase( "blog/actions/edit_comment/success/body" )
        );
      }
    }

    private static function delete_comment() {
      $blog_comment_id = sys::input( "blog_comment_id", 0 );
      $comment = null;
      if( $blog_comment_id ) {
        db::open( TABLE_BLOG_COMMENTS );
          db::where( "blog_comment_id", $blog_comment_id );
        $comment = db::result();
        db::clear_result();
      }
      if( !$comment ) {
        sys::message( 
          USER_ERROR, 
          lang::phrase( "error/blog/comment/no_id/title" ), 
          lang::phrase( "error/blog/toggle_approval/no_id/body" )
        );
      }
      if( !auth::test( "blog", "delete_comments" ) ) {
        if( $comment['user_id'] != action::get( "user/id" ) || !auth::test( "blog", "delete_own_comments" ) ) {
          auth::deny( "blog", "delete_comments" );
        }
      }
      db::open( TABLE_BLOG_COMMENTS );
        db::where( "blog_comment_id", $blog_comment_id );
      if( !db::delete() ) {
        sys::message( APPLICATION_ERROR, lang::phrase( "error/blog/comment/delete_comment/title" ), lang::phrase( "error/blog/comment/delete_comment/body", db::error() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
      }
      self::delete_comment_children( $blog_comment_id );
      
      tpl::update_dependency( "blog_hot_post_list" );

      action::resume( "blog/blog_action" );
        action::add( "success", 1 );
        action::add( "message", lang::phrase( "blog/post/delete_comment/success" ) );
      action::end();
    }

    private static function delete_comment_children( $blog_comment_id ) {
      db::open( TABLE_BLOG_COMMENTS );
        db::where( "blog_comment_parent", $blog_comment_id );
      while( $row = db::result() ) {
        $blog_comment_id = $row['blog_comment_id'];
        db::open( TABLE_BLOG_COMMENTS );
          db::where( "blog_comment_id", $blog_comment_id );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/blog/delete_comment_children/title" ),
            lang::phrase( "error/blog/delete_comment_children/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        self::delete_comment_children( $blog_comment_id );
      }
    }

    private static function toggle_approval() {
      $blog_comment_id = sys::input( "blog_comment_id", 0 );
      if( $blog_comment_id ) {
        db::open( TABLE_BLOG_COMMENTS );
          db::where( "blog_comment_id", $blog_comment_id );
        $comment = db::result();
        db::clear_result();
        if( $comment['blog_comment_approved'] && !auth::test( "blog", "unapprove_comments" ) ) {
          sys::message( AUTHENTICATION_ERROR, lang::phrase( "authentication/denied" ), lang::phrase( "authentication/blog/unapprove_comments/denied" ) );
        } else if( !$comment['blog_comment_approved'] && !auth::test( "blog", "approve_comments" ) ) {
          sys::message( AUTHENTICATION_ERROR, lang::phrase( "authentication/denied" ), lang::phrase( "authentication/blog/approve_comments/denied" ) );
        }
      } else {
        sys::message( USER_MESSAGE, lang::phrase( "error/blog/comment/no_id/title" ), lang::phrase( "error/blog/toggle_approval/no_id/body" ) );
      }
      db::open( TABLE_BLOG_COMMENTS );
        db::where( "blog_comment_id", $blog_comment_id );
        if( $comment['blog_comment_approved'] ) {
          db::set( "blog_comment_approved", 0 );
        } else {
          db::set( "blog_comment_approved", 1 );
        }
      if( !db::update() ) {
        sys::message( APPLICATION_ERROR, lang::phrase( "error/blog/comment/toggle_approval/title" ), lang::phrase( "error/blog/comment/toggle_approval/body", db::error() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
      }
      action::resume( "blog/blog_action" );
        action::add( "success", 1 );
        action::add( "message", lang::phrase( "blog/comment/toggle_approval/success/body" ) );
      action::end();

      //cache::clear( "", "blog/list_posts" );
      //cache::clear( "", "blog/list_hot_posts" );

      tpl::update_dependency( "blog_hot_post_list" );
      tpl::update_dependency( "blog_post_" . $comment['blog_post_id'] . "_comment_list" );

      $return_page = sys::input( "return_page", "" );
      if( $return_page ) {
        action::resume( "request" );
          action::add( "return_text", lang::phrase( "blog/comment/toggle_approval/success/return" ) );
          action::add( "return_page", $return_page );
        action::end();
        sys::message( USER_MESSAGE, lang::phrase( "blog/comment/toggle_approval/success/title" ), lang::phrase( "blog/comment/toggle_approval/body" ) );
      }
    }

    private static function toggle_spam() {
      $blog_comment_id = sys::input( "blog_comment_id", 0 );
      if( $blog_comment_id ) {
        db::open( TABLE_BLOG_COMMENTS );
          db::where( "blog_comment_id", $blog_comment_id );
        $comment = db::result();
        db::clear_result();
        if( $comment['blog_comment_spam'] && !auth::test( "blog", "unflag_comments" ) ) {
          sys::message( AUTHENTICATION_ERROR, lang::phrase( "authentication/denied" ), lang::phrase( "authentication/blog/unflag_comments/denied" ) );
        } else if( !$comment['blog_comment_spam'] && !auth::test( "blog", "flag_comments" ) ) {
          sys::message( AUTHENTICATION_ERROR, lang::phrase( "authentication/denied" ), lang::phrase( "authentication/blog/flag_comments/denied" ) );
        }
      } else {
        sys::message( USER_MESSAGE, lang::phrase( "error/blog/comment/no_id/title" ), lang::phrase( "error/blog/toggle_spam/no_id/body" ) );
      }

      action::start( "toggle_comment_spam" );
        action::add( "id", $comment['blog_comment_id'] );
        action::add( "body", $comment['blog_comment_body'] );
        action::add( "author", $comment['blog_comment_author'] );
        action::add( "author_email", $comment['blog_comment_author_email'] );
        action::add( "author_ip", $comment['blog_comment_author_ip'] );
        action::add( "author_agent", $comment['blog_comment_author_agent'] );
        action::add( "author_referrer", $comment['blog_comment_author_referrer'] );
      action::end();

      if( $comment['blog_comment_spam'] ) {
        sys::hook( "mark_ham", "blog_comment", $blog_comment_id, "toggle_comment_spam" );
      } else {
        sys::hook( "mark_spam", "blog_comment", $blog_comment_id, "toggle_comment_spam" );
      }
      db::open( TABLE_BLOG_COMMENTS );
        db::where( "blog_comment_id", $blog_comment_id );
        if( $comment['blog_comment_spam'] ) {
          db::set( "blog_comment_spam", 0 );
        } else {
          db::set( "blog_comment_spam", 1 );
        }
      if( !db::update() ) {
        sys::message( APPLICATION_ERROR, lang::phrase( "error/blog/comment/toggle_spam/title" ), lang::phrase( "error/blog/comment/toggle_spam/body", db::error() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
      }
      cache::clear( "", "blog/list_posts" );
      cache::clear( "", "blog/list_hot_posts" );
    }

    private static function pingback() {
      global $HTTP_RAW_POST_DATA;
      $server = xmlrpc_server_create();
      xmlrpc_server_register_method( $server, "pingback.ping", "blog::add_pingback" );
      if( $response = xmlrpc_server_call_method( $server, $HTTP_RAW_POST_DATA, null ) ) {
        header( "Content-Type: text/xml" );
        echo $response;
        exit();
      }
    }

    private static function edit_featured_post() {
      if( !auth::test( "blog", "publish_blog_posts" ) ) {
        auth::deny( "blog", "edit_featured_posts" );
      }
      $blog_post_id = sys::input( "blog_post_id", 0 );
      $blog_featured_post_id = sys::input( "blog_featured_post_id", 0 );
      $blog_featured_post_title = sys::input( "blog_featured_post_title", "" );
      $blog_featured_post_size = sys::input( "blog_featured_post_size", "" );
      $blog_featured_post_active = sys::input( "blog_featured_post_active", "" );
      $blog_featured_post_image = sys::file( "blog_featured_post_image" );

      if( !$blog_featured_post_id && !$blog_featured_post_image ) {
        action::resume( "blog/blog_action" );
          action::add( "action", "edit_featured_post" );
          action::add( "success", 0 );
          action::add( "message", lang::phrase( "blog/edit_featured_post/missing_image" ) );
        action::end();
        return;
      }

      if( $blog_featured_post_image['name'] ) {
        $target_dir = ROOT_DIR . "/uploads/featured_posts";
        if( !sys::copy_file( "blog_featured_post_image", $target_dir, $blog_post_id . ".jpg" ) ) {
          sys::message( APPLICATION_ERROR, lang::phrase( "error/blog/upload_featured_image/title" ), lang::phrase( "error/blog/upload_featured_image/body" ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
        }
      }

      db::open( TABLE_BLOG_FEATURED_POSTS );
        if( !$blog_featured_post_id ) {
          db::set( "blog_post_id", $blog_post_id );
        } else {
          db::where( "blog_featured_post_id", $blog_featured_post_id );
        }
        db::set( "blog_featured_post_title", $blog_featured_post_title );
        db::set( "blog_featured_post_size", $blog_featured_post_size );
        db::set( "blog_featured_post_active", $blog_featured_post_active );
      if( $blog_featured_post_id ) {
        if( !db::update() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/blog/edit_featured_post/title" ),
            lang::phrase( "error/blog/edit_featured_post/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      } else {
        if( !db::insert() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/blog/create_featured_post/title" ),
            lang::phrase( "error/blog/create_featured_post/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      }

      //cache::clear( "", "blog/list_featured_posts" );

      tpl::update_dependency( "blog_featured_post_list" );

      action::resume( "blog/blog_action" );
        action::add( "action", "edit_featured_post" );
        action::add( "success", 1 );
        if( !$blog_featured_post_id ) {
          action::add( "message", lang::phrase( "blog/create_featured_post/success" ) );
        } else {
          action::add( "message", lang::phrase( "blog/edit_featured_post/success" ) );
        }
      action::end();
    }

    private static function process_featured_posts() {
      if( !auth::test( "blog", "publish_blog_posts" ) ) {
        auth::deny( "blog", "process_featured_posts" );
      }
      $total_posts = sys::input( "total_posts", 0 );
      for( $i = 0; $i < $total_posts; $i++ ) {
        $blog_featured_post_id = sys::input( "blog_featured_post_id_" . ($i+1), 0 );
        $blog_featured_post_active = sys::input( "blog_featured_post_active_" . ($i+1), false );
        $blog_featured_post_delete = sys::input( "blog_featured_post_delete_" . ($i+1), 0 );
        db::open( TABLE_BLOG_FEATURED_POSTS );
          db::where( "blog_featured_post_id", $blog_featured_post_id );
        $post = db::result();
        db::clear_result();
        db::open( TABLE_BLOG_FEATURED_POSTS );
          db::where( "blog_featured_post_id", $blog_featured_post_id );
        if( $blog_featured_post_delete ) {
          if( !db::delete() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/blog/delete_featured_post/title" ),
              lang::phrase( "error/blog/delete_featured_post/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
          $target_file = ROOT_DIR . "/uploads/featured_posts/" . $post['blog_post_id'] . ".jpg";
          if( file_exists( $target_file ) ) {
            unlink( $target_file );
          }
        } else {
          db::set( "blog_featured_post_active", ( $blog_featured_post_active ? 1 : 0 ) );
          if( !db::update() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/blog/edit_featured_post/title" ),
              lang::phrase( "error/blog/edit_featured_post/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
        }
      }

      tpl::update_dependency( "blog_featured_post_list" );
      //cache::clear( "", "blog/list_featured_posts" );

      action::resume( "blog/blog_action" );
        action::add( "action", "process_featured_posts" );
        action::add( "success", 1 );
        action::add( "message", lang::phrase( "blog/process_featured_posts/success", $total_posts ) );
      action::end();
    }

    public static function add_pingback( $method, $params, $extra ) {
      list( $source_uri, $target_uri ) = $params;

      $source = file_get_contents( $source_uri );
      if( !$source ) {
        return 16;
      }

      $source = html_entity_decode( $source );
      if( !$position = strpos( $source, $target_uri ) ) {
        return 17;
      }

      if( !$uri = parse_url( $target_uri ) ) {
        return 16;
      }
      $path = preg_replace( "/" . str_replace( "/", "\/", action::get( "settings/script_path" ) . "/" ) . "/si", "", $uri['path'], 1 );
      $path_split = explode( "/", $path );
      $blog_post_name = $path_split[3];
      db::open( TABLE_BLOG_POSTS );
        db::where( "blog_post_name", $blog_post_name );
      if( !$post = db::result() ) {
        return 33;
      }

      db::open( TABLE_PINGBACKS_RECEIVED );
        db::where( "blog_pingback_source", $source_uri );
        db::where( "blog_post_id", $post['blog_post_id'] );
      if( $pingback = db::result() ) {
        return 48;
      }

      $line_split = explode( "\n", substr( $source, $position-500, 1000 ) );
      $total_lines = count( $line_split );
      $pingback_content = "";
      for( $i = 0; $i < $total_lines; $i++ ) {
        if( strpos( $line_split[$i], $target_uri ) ) {
          $pingback_content = $line_split[$i];
        }
      }

      db::open( TABLE_BLOG_PINGBACKS_RECEIVED );
        db::set( "blog_pingback_source", $source_uri );
        db::set( "blog_pingback_target", $target_uri );
        db::set( "blog_pingback_content", $pingback_content );
        db::set( "blog_post_id", $post['blog_post_id'] );
      if( !db::insert() ) {
        sys::message( SYSTEM_ERROR, lang::phrase( "error/blog/pingback/error" ), lang::phrase( "error/blog/pingback/add_pingback/failed", db::error() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
      }

      $blog_pingback_id = db::id();

      action::start( "add_pingback" );
        action::add( "id", $blog_pingback_id );
        action::add( "body", $pingback_content );
        action::add( "date", time() );
        action::add( "author", $uri['host'] );
      action::end();

      sys::hook( "check_spam", "blog_pingback", "add_pingback" );

      if( action::get( "check_spam_response/spam" ) == "true" ) {
        db::open( TABLE_BLOG_PINGBACKS_RECEIVED );
          db::set( "blog_pingback_spam", 1 );
          db::where( "blog_pingback_id", $blog_pingback_id );
        if( !db::update() ) {
          sys::message( SYSTEM_ERROR, lang::phrase( "error/blog/pingback/error" ), lang::phrase( "error/blog/pingback/add_pingback/spam_failed", db::error() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
        }
        return 49;
      }

      return "Pingback received, thank you!";
    }

    public static function list_snapshots() {
      $blog_post_id = sys::input( "blog_post_id", "" );
      $page = sys::input( "page", 0 );
      action::resume( "blog/snapshot_list" );
        db::open( TABLE_BLOG_POST_SNAPSHOTS );
          db::order( "blog_post_snapshot_date", "DESC" );
          if( $page <= 1 ) {
            db::where( "blog_post_page", 1, "<=" );
          }
          db::where( "blog_post_id", $blog_post_id );
          db::open( TABLE_USERS );
            db::link( "user_id" );
        while( $row = db::result() ) {
          action::start( "snapshot" );
            action::add( "id", $row['blog_post_snapshot_id'] );

            action::start( "date" );
              $date = strtotime( $row['blog_post_snapshot_date'] );
              action::add( "timestamp", $date );
              action::add( "period", sys::create_duration( time(), $date ) );
              action::add( "datetime", sys::create_datetime( $date ) );
              $date += sys::timezone() * 60 * 60;
              action::add( "altered_timestamp", $date );
              action::add( "altered_datetime", sys::create_datetime( $date ) );
            action::end();
            
            action::start( "user" );
              action::add( "id", $row['user_id'] );
              action::add( "name", $row['user_name'] );
            action::end();
          action::end();
        }
      action::end();
    }

    public static function list_posts() {
      $page = sys::input( "page", 1 );
      $user_name = sys::input( "user_name", "" );
      $user = NULL;
      if( $user_name ) {
        db::open( TABLE_USERS );
          db::where( "user_name", $user_name );
        $user = db::result();
        db::clear_result();
        if( !$user ) {
          sys::message(
            NOTFOUND_ERROR,
            lang::phrase( "error/blog/list_posts/author_not_found/title" ),
            lang::phrase( "error/blog/list_posts/author_not_found/body" )
          );
        }
      }
      $blog_category_name = sys::input( "blog_category_name", "" );
      $blog_category = NULL;
      if( $blog_category_name ) {
        db::open( TABLE_BLOG_CATEGORIES );
          db::where( "blog_category_name", $blog_category_name );
        $blog_category = db::result();
        db::clear_result();
        if( !$blog_category ) {
          sys::message(
            NOTFOUND_ERROR,
            lang::phrase( "error/blog/list_posts/category_not_found/title" ),
            lang::phrase( "error/blog/list_posts/category_not_found/body" )
          );
        }
      }
      $blog_post_tag_name = sys::input( "blog_post_tag_name", "" );
      $blog_post_tag = NULL;
      if( $blog_post_tag_name ) {
        db::open( TABLE_BLOG_POST_TAGS );
          db::where( "blog_post_tag_name", $blog_post_tag_name );
        $blog_post_tag = db::result();
        db::clear_result();
        if( !$blog_post_tag ) {
          sys::message(
            NOTFOUND_ERROR,
            lang::phrase( "error/blog/list_posts/tag_not_found/title" ),
            lang::phrase( "error/blog/list_posts/tag_not_found/body" )
          );
        }
      }
      $blog_comment_approved = sys::input( "blog_comment_approved", -1 );
      $blog_post_status_filter = sys::input( "blog_post_status_filter", "" );
      $blog_post_year = sys::input( "blog_post_year", 0 );
      $blog_post_month = sys::input( "blog_post_month", 0 );
      $blog_post_day = sys::input( "blog_post_day", 0 );
      if( !$per_page = preferences::get( "blog", "posts_per_page", "account" ) ) {
        $per_page = sys::input( "per_page", 10 );
      }

      db::open( TABLE_BLOG_POSTS );
        db::select_as( "total_posts" );
        db::select_count( "blog_post_id" );
        if( $blog_post_status_filter ) {
          $status_split = explode( ",", $blog_post_status_filter );
          $total_filters = count( $status_split );
          for( $i = 0; $i < $total_filters; $i++ ) {
            db::where( "blog_post_status", $status_split[$i] );
          }
          if( $blog_post_status_filter == "published" ) {
            db::where( "blog_post_published", sys::create_datetime( time() ), "<=" );
          } else if( !auth::test( "blog", "view_unpublished_posts" ) ) {
            if( auth::test( "blog", "view_own_unpublished_posts" ) ) {
              db::where( "user_id", action::get( "user/id" ) );
            } else {
              db::where( "blog_post_published", sys::create_datetime( time() ), "<=" );
            }
          }
        }
        if( $user ) {
          db::open( TABLE_USERS );
            db::where( "user_name", $user_name );
            db::link( "user_id" );
          db::close();
        }
        if( $blog_category ) {
          db::open( TABLE_BLOG_CATEGORIES );
            db::where( "blog_category_name", $blog_category_name );
            db::link( "blog_category_name" );
          db::close();
        }
        if( $blog_post_tag ) {
          db::open( TABLE_BLOG_POST_TAGS );
            db::where( "blog_post_tag_name", $blog_post_tag_name );
            db::link( "blog_post_id" );
          db::close();
        }
        if( $blog_post_year ) {
          db::where_year( "blog_post_published", $blog_post_year );
        }
        if( $blog_post_year && $blog_post_month ) {
          db::where_month( "blog_post_published", $blog_post_month );
        }
        if( $blog_post_year && $blog_post_month && $blog_post_day ) {
          db::where_day( "blog_post_published", $blog_post_day );
        }
        
      $count = db::result();
      db::clear_result();
      $total_posts = $count['total_posts'];

      if( $total_posts == 0 ) {
        sys::message(
          NOTFOUND_ERROR,
          lang::phrase( "error/blog/list_posts/not_found/title" ),
          lang::phrase( "error/blog/list_posts/not_found/body" )
        );
      }

      // BEGIN blog
      action::resume( "blog" );
        // BEGIN post_list
        action::start( "post_list" );
          action::add( "per_page", $per_page );
          action::add( "total", $total_posts );
          action::add( "pages", ceil( $total_posts / $per_page ) );
          action::add( "page", $page );
          if( $user ) {
            action::start( "author" );
              action::add( "id", $user['user_id'] );
              action::add( "name", $user['user_name'] );
            action::end();
          }
          if( $blog_category ) {
            action::start( "category" );
              action::add( "name", $blog_category['blog_category_name'] );
              action::add( "title", $blog_category['blog_category_title'] );
            action::end();
          }
          if( $blog_post_tag ) {
            action::start( "tag" );
              action::add( "name", $blog_post_tag['blog_post_tag_name'] );
              action::add( "title", $blog_post_tag['blog_post_tag_title'] );
            action::end();
          }
          if( $blog_post_year ) {
            action::add( "year", $blog_post_year );
          }
          if( $blog_post_year && $blog_post_month ) {
            action::add( "month", $blog_post_month );
          }
          if( $blog_post_year && $blog_post_month && $blog_post_date ) {
            action::add( "date", $blog_post_date );
          }
          db::open( TABLE_BLOG_POST_DATA );
            db::open_subquery();
              db::open( TABLE_USERS );
                db::select( "user_name", "user_email" );
                if( $user ) {
                  db::where( "user_name", $user_name );
                }
                db::open_subquery();
                  db::open( TABLE_BLOG_POSTS );
                    if( $blog_post_status_filter ) {
                      $status_split = explode( ",", $blog_post_status_filter );
                      $total_filters = count( $status_split );
                      if( $total_filters > 1 ) {
                        db::where_in( "blog_post_status", $status_split );
                      } else {
                        db::where( "blog_post_status", $status_split[0] );
                      }
                      if( $blog_post_status_filter == "published" ) {
                        db::where( "blog_post_published", sys::create_datetime( time() ), "<=" );
                      } else if( !auth::test( "blog", "view_unpublished_posts" ) ) {
                        if( auth::test( "blog", "view_own_unpublished_posts" ) ) {
                          db::where( "user_id", action::get( "user/id" ) );
                        } else {
                          db::where( "blog_post_published", sys::create_datetime( time() ), "<=" );
                        }
                      }
                    }
                    if( $blog_post_year ) {
                      db::where_year( "blog_post_published", $blog_post_year );
                    }
                    if( $blog_post_year && $blog_post_month ) {
                      db::where_month( "blog_post_published", $blog_post_month );
                    }
                    if( $blog_post_year && $blog_post_month && $blog_post_day ) {
                      db::where_day( "blog_post_published", $blog_post_day );
                    }
                    db::order( "blog_post_sort", "DESC" );
                  db::close();
                db::close_subquery();
                  db::link( "user_id" );
                  db::open( TABLE_BLOG_CATEGORIES );
                    db::select( "blog_category_title", "blog_category_color" );
                    db::link( "blog_category_name" );
                    if( $blog_category ) {
                      db::where( "blog_category_name", $blog_category_name );
                    }
                  db::close();
                  if( $blog_post_tag ) {
                    db::open( TABLE_BLOG_POST_TAGS );
                      db::select_none();
                      db::link( "blog_post_id" );
                      db::where( "blog_post_tag_name", $blog_post_tag_name );
                    db::close();
                  }
                db::close();
                db::limit( $per_page*($page-1), $per_page );
              db::close();
            db::close_subquery();
            db::link( "blog_post_id" );
          while( $row = db::result() ) {

            // BEGIN post
            action::start( "post" );
              action::add( "id", $row['blog_post_id'] );
              action::add( "title", $row['blog_post_title'] );
              //$row['blog_post_preface'] = str_replace( "&", "&amp;", $row['blog_post_preface'] );
              action::add( "preface", $row['blog_post_preface'] );
              action::add( "formatted_preface", self::parse_post( $row['blog_post_preface'] ), true );

              action::start( "created" );
                $created = strtotime( $row['blog_post_created'] );
                action::add( "timestamp", $created );
                action::add( "period", sys::create_duration( time(), $created ) );
                action::add( "datetime", sys::create_datetime( $created ) );
                $created += sys::timezone() * 60 * 60;
                action::add( "altered_timestamp", $created );
                action::add( "altered_datetime", sys::create_datetime( $created ) );
              action::end();
              action::start( "published" );
                action::add( "published", $row['blog_post_status'] == 'published' ? 1 : 0 );
                $published = strtotime( $row['blog_post_published'] );
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
              action::start( "sortdate" );
                $sortdate = strtotime( $row['blog_post_sort'] );
                if( !$sortdate ) {
                  $sortdate = time();
                }
                action::add( "timestamp", $sortdate );
                action::add( "period", sys::create_duration( time(), $sortdate ) );
                action::add( "datetime", sys::create_datetime( $sortdate ) );
                $sortdate += ( 60 * 60 ) * sys::timezone();
                action::add( "altered_timestamp", $sortdate );
                action::add( "altered_datetime", sys::create_datetime( $sortdate ) );
                action::add( "updated", ( $sortdate > $published ) ? 1 : 0 );
              action::end();
              action::start( "updated" );
                $updated = strtotime( $row['blog_post_updated'] );
                action::add( "timestamp", $updated );
                action::add( "period", sys::create_duration( time(), $updated ) );
                action::add( "datetime", sys::create_datetime( $updated ) );
                $updated += sys::timezone() * 60 * 60;
                action::add( "altered_timestamp", $updated );
                action::add( "altered_datetime", sys::create_datetime( $updated ) );
              action::end();
              
              if( strlen( $row['blog_post_body'] ) > 0 ) {
                action::add( "body", 1 );
              }
              if( $row['blog_post_source_link'] ) {
                action::add( "source_link", $row['blog_post_source_link'] );
                if( $row['blog_post_source_title'] ) {
                  action::add( "source_title", $row['blog_post_source_title'] );
                } else {
                  action::add( "source_title", "Source" );
                }
              }
              action::add( "name", urlencode( $row['blog_post_name'] ) );
              action::add( "compacted", $row['blog_post_compacted'] );
              action::add( "status", $row['blog_post_status'] );
              action::add( "status_title", lang::phrase( "blog/status/" . $row['blog_post_status'] ) );
              action::add( "author_id", $row['user_id'] );
              action::add( "author_username", $row['user_name'] );
              action::add( "author_email", $row['user_email'] );
              action::add( "category_name", $row['blog_category_name'] );
              action::add( "category_title", $row['blog_category_title'] );
              action::add( "category_color", $row['blog_category_color'] );
              action::add( "comments", $row['blog_post_comments'] );

              action::start( "tag_list" );
                db::open( TABLE_BLOG_POST_TAGS );
                  db::where( "blog_post_id", $row['blog_post_id'] );
                while( $row = db::result() ) {
                  action::start( "tag" );
                    action::add( "title", $row['blog_post_tag_title'] );
                    action::add( "name", $row['blog_post_tag_name'] );
                  action::end();
                }
              action::end();
              
            action::end();
            // END post

          }
        action::end();
        // END post_list
        
      action::end();
      // END blog

    }

    public static function list_hot_posts() {
      $total_hot_posts = sys::input( "total_hot_posts", 0 );
      $hot_post_range = sys::input( "hot_post_range", 60 * 60 * 24 * 7 );
      $blog_comment_approved = sys::input( "blog_comment_approved", -1 );

      $cache_id = "tp" . $total_hot_posts . ".rg" . $hot_post_range;
      if( $blog_comment_approved ) {
        $cache_id .= ".ca";
      }

      if( CACHE_ENABLED || !$hot_post_list = cache::get( $cache_id, "blog/list_hot_posts" ) ) {
        action::resume( "blog/hot_post_list" );
          db::open( TABLE_BLOG_POSTS );
            db::select( "blog_post_published" );
            db::open( TABLE_BLOG_CATEGORIES );
              db::link( "blog_category_name" );
            db::close();
            db::open( TABLE_BLOG_POST_DATA );
              db::select( "blog_post_name", "blog_post_title" );
              db::link( "blog_post_id" );
            db::close();
            db::open_subquery();
              db::open( TABLE_BLOG_COMMENTS );
                db::select_as( "hot_post_comment_count" );
                db::select_count_all();
                db::select( "blog_post_id" );
                db::where( "blog_comment_date", sys::create_datetime( time() - $hot_post_range ), ">" );
                if( $blog_comment_approved >= 0 ) {
                  db::where( "blog_comment_approved", $blog_comment_approved );
                }
                db::group( "blog_post_id" );
              db::close();
            db::close_subquery();
              db::link( "blog_post_id" );
              db::order( "hot_post_comment_count", "DESC" );
              db::limit( 0, $total_hot_posts );
          while( $row = db::result() ) {
            action::start( "post" );
              action::add( "id", $row['blog_post_id'] );
              action::add( "title", $row['blog_post_title'] );
              action::add( "name", $row['blog_post_name'] );
              action::add( "recent_comments", $row['hot_post_comment_count'] );
              action::add( "datetime", $row['blog_post_published'] );
              action::start( "category" );
                action::add( "name", $row['blog_category_name'] );
              action::end();
            action::end();
          }
        action::end();
        $hot_post_list = action::xpath( "blog/hot_post_list" );
        if( CACHE_ENABLED && $hot_post_list ) {
          cache::set( $hot_post_list->ownerDocument->saveXML(), -1, $cache_id, "blog/list_hot_posts" );
        }
      } else {
        action::merge( simplexml_load_string( $hot_post_list ), "blog" );
      }
    }

    public static function list_featured_posts() {
      $blog_featured_post_active = sys::input( "featured_post_status", -1 );
      $cache_id = "fp";
      if( $blog_featured_post_active >= 0 ) {
        $cache_id .= ".active";
      }
      action::resume( "blog/featured_post_list" );
        db::open( TABLE_BLOG_FEATURED_POSTS );
          if( $blog_featured_post_active >= 0 ) {
            db::where( "blog_featured_post_active", $blog_featured_post_active );
          }
          db::open( TABLE_BLOG_POSTS );
            db::select( "blog_post_published" );
            db::link( "blog_post_id" );
            db::order( "blog_post_sort", "DESC" );
            db::open( TABLE_BLOG_POST_DATA );
              db::select( "blog_post_name" );
              db::link( "blog_post_id" );
        while( $row = db::result() ) {
          action::start( "post" );
            action::add( "id", $row['blog_featured_post_id'] );
            action::add( "post", $row['blog_post_id'] );
            action::add( "title", $row['blog_featured_post_title'] );
            action::add( "active", $row['blog_featured_post_active'] );
            action::add( "name", $row['blog_post_name'] );
            action::add( "size", $row['blog_featured_post_size'] );
            action::add( "datetime", $row['blog_post_published'] );
            if( file_exists( $row['blog_featured_post_filename'] ) ) {
              action::add( "filename", $row['blog_featured_post_filename'] );
            } else {
              action::add( "filename", $row['blog_post_id'] . ".jpg" );
            }
          action::end();
        }
      action::end();

      $total_featured_posts = action::total( "blog/featured_post_list/post" );
      for( $i = 0; $i < $total_featured_posts; $i++ ) {
        $timestamp = strtotime( action::get( "blog/featured_post_list/post/datetime", $i ) );
        action::resume( "blog/featured_post_list/post", $i );
          action::add( "period", sys::create_duration( $timestamp-(60*60), time() ) );
        action::end();
      }
    }

    public static function loop_comments( $path, $targets = array() ) {
      if( count( $targets ) == 0 ) {
        $total_items = action::total( $path );
        for( $i = 0; $i < $total_items; $i++ ) {
          $targets[] = action::get( $path, $i );
        }
      }
      action::resume( "blog" );
        foreach( $targets as $target ) {
          action::start( "comment_list" );
            self::get_post_comments( $target );
          action::end();
        }
      action::end();
    }

    public static function loop_comment_counts( $path, $targets = array() ) {
      if( count( $targets ) == 0 ) {
        $total_items = action::total( $path );
        for( $i = 0; $i < $total_items; $i++ ) {
          $targets[] = action::get( $path, $i );
        }
      }

      action::resume( "blog" );
        action::start( "comment_count_list" );
          db::open( TABLE_BLOG_POSTS );
            db::where_in( "blog_post_id", $targets );
            db::open( TABLE_BLOG_COMMENTS, LEFT );
              db::select_as( "total_comments" );
              db::select_count( "blog_comment_id" );
              db::select_as( "total_approved_comments" );
              db::select_sum( "blog_comment_approved" );
              db::select_as( "total_spam_comments" );
              db::select_sum( "blog_comment_spam" );
              db::link( "blog_post_id" );
            db::close();
            db::group( "blog_post_id" );
          while( $row = db::result() ) {
            action::start( "comment_count" );
              action::add( "id", $row['blog_post_id'] );
              action::add( "total", $row['total_comments'] );
              action::add( "approved", $row['total_approved_comments'] );
              action::add( "spam", $row['total_spam_comments'] );
              action::add( "unapproved", $row['total_comments'] - $row['total_approved_comments'] - $row['total_spam_comments'] );
            action::end();
          }
        action::end();
      action::end();
    }

    public static function get_post() {
      $blog_post_id = sys::input( "blog_post_id", 0 );
      $blog_post_year = sys::input( "blog_post_year", 0 );
      $blog_post_month = sys::input( "blog_post_month", 0 );
      $blog_post_day = sys::input( "blog_post_day", 0 );
      $blog_post_name = sys::input( "blog_post_name", 0 );
      if( !$blog_post_id && ( !$blog_post_year || !$blog_post_month || !$blog_post_day || !$blog_post_name ) ) {
        sys::message( USER_ERROR, lang::phrase( "error/blog/get_post/invalid_identifier/title" ), lang::phrase( "error/blog/get_post/invalid_identifier/body" ) );
      }
      $page = sys::input( "page", 1 );
      if( $blog_post_id.'' == "new" ) {
        action::resume( "blog/blog_post" );
          action::add( "title", "Untitled" );
          action::add( "status", "draft" );
          action::add( "status_title", "Draft" );
          action::add( "author_username", action::get( "user/user_name" ) );
          action::add( "author_id", action::get( "user/user_id" ) );
          action::add( "compacted", 0 );
          action::add( "new", 1 );
        action::end();
      } else {
        action::resume( "blog" );
          action::start( "blog_post" );
            db::open( TABLE_BLOG_POST_DATA );
              if( !$blog_post_id ) {
                db::where( "blog_post_name", $blog_post_name );
              }
              db::open( TABLE_BLOG_POSTS );
                db::link( "blog_post_id" );
                if( $blog_post_id ) {
                  db::where( "blog_post_id", $blog_post_id );
                } else {
                  db::where_year ( "blog_post_published", $blog_post_year );
                  db::where_month( "blog_post_published", $blog_post_month );
                  db::where_day( "blog_post_published", $blog_post_day );
                }
                db::open( TABLE_USERS );
                  db::link( "user_id" );
                db::close();
                db::open( TABLE_BLOG_CATEGORIES, LEFT );
                  db::link( "blog_category_name" );
                db::close();
            $post = db::result();
            db::clear_result();
            if( !$post ) {
              sys::message(
                NOTFOUND_ERROR,
                lang::phrase( "error/blog/get_post/not_found/title" ),
                lang::phrase( "error/blog/get_post/not_found/body" )
              );
            }
            $post_time = strtotime( $post['blog_post_published'] );
            $post_published = $post['blog_post_status'] == "published" && $post_time < time();
            if( !$post_published ) {
              if( $post['user_id'] == action::get( "user/id" ) ) {
                if( !auth::test( "blog", "view_own_unpublished_posts" ) ) {
                  auth::deny( "blog", "view_own_unpublished_posts" );
                }
              } else {
                if( !auth::test( "blog", "view_unpublished_posts" ) ) {
                  auth::deny( "blog", "view_unpublished_posts" );
                }
              }
              tpl::set_restricted_page();
            }
            if( $page > 1 ) {
              db::open( TABLE_BLOG_POST_PAGES );
                db::where( "blog_post_id", $post['blog_post_id'] );
                db::order( "blog_post_page_order", "ASC" );
                db::order( "blog_post_page_id", "ASC" );
                db::limit( $page-2, 1 );
              $page_body = db::result();
              db::clear_result();
              if( $page_body ) {
                $post['blog_post_preface'] = "";
                $post['blog_post_body'] = $page_body['blog_post_page_body'];
                action::start( "page" );
                  action::add( "id", $page_body['blog_post_page_id'] );
                  action::add( "title", $page_body['blog_post_page_title'] );
                  action::add( "number", $page );
                action::end();
              }
            } else {
              action::add( "page", 1 );
            }
            action::start( "created" );
              $created = strtotime( $post['blog_post_created'] );
              action::add( "timestamp", $created );
              action::add( "period", sys::create_duration( time(), $created ) );
              action::add( "datetime", sys::create_datetime( $created ) );
              $created += ( 60 * 60 ) * sys::timezone();
              action::add( "altered_timestamp", $created );
              action::add( "altered_datetime", sys::create_datetime( $created ) );
            action::end();
            action::start( "published" );
              action::add( "published", $post['blog_post_status'] == 'published' ? 1 : 0 );
              $published = strtotime( $post['blog_post_published'] );
              if( !$published ) {
                $published = time();
              }
              action::add( "timestamp", $published );
              action::add( "period", sys::create_duration( time(), $published ) );
              action::add( "datetime", sys::create_datetime( $published ) );
              $published += ( 60 * 60 ) * sys::timezone();
              action::add( "altered_timestamp", $published );
              action::add( "altered_datetime", sys::create_datetime( $published ) );
            action::end();
            action::start( "sortdate" );
              $sortdate = strtotime( $post['blog_post_sort'] );
              if( !$sortdate ) {
                $sortdate = time();
              }
              action::add( "timestamp", $sortdate );
              action::add( "period", sys::create_duration( time(), $sortdate ) );
              action::add( "datetime", sys::create_datetime( $sortdate ) );
              $sortdate += ( 60 * 60 ) * sys::timezone();
              action::add( "altered_timestamp", $sortdate );
              action::add( "altered_datetime", sys::create_datetime( $sortdate ) );
              action::add( "updated", ( $sortdate > $published ) ? 1 : 0 );
            action::end();
            action::start( "updated" );
              $updated = strtotime( $post['blog_post_updated'] );
              action::add( "timestamp", $updated );
              action::add( "period", sys::create_duration( time(), $updated ) );
              action::add( "datetime", sys::create_datetime( $updated ) );
              $updated += ( 60 * 60 ) * sys::timezone();
              action::add( "altered_timestamp", $updated );
              action::add( "altered_datetime", sys::create_datetime( $updated ) );
            action::end();
            action::add( "id", $post['blog_post_id'] );
            action::add( "title", $post['blog_post_title'] );
            //$post['blog_post_preface'] = str_replace( "&", "&amp;", $post['blog_post_preface'] );
            //$post['blog_post_body'] = str_replace( "&", "&amp;", $post['blog_post_body'] );
            if( strlen( $post['blog_post_preface'] ) > 0 ) {
              action::add( "preface", $post['blog_post_preface'] );
              action::add( "formatted_preface", self::parse_post( $post['blog_post_preface'] ), true );
              action::add( "stripped_preface", self::strip_post( $post['blog_post_preface'] ), true );
            }
            if( strlen( $post['blog_post_body'] ) > 0 ) {
              action::add( "body", $post['blog_post_body'] );
              action::add( "formatted_body", self::parse_post( $post['blog_post_body'] ), true );
            }
            if( $post['blog_post_source_link'] ) {
              action::add( "source_link", $post['blog_post_source_link'] );
              if( $post['blog_post_source_title'] ) {
                action::add( "source_title", $post['blog_post_source_title'] );
              } else {
                action::add( "source_title", "Source" );
              }
            }
            action::add( "name", $post['blog_post_name'] );
            action::add( "status", $post['blog_post_status'] );
            action::add( "status_title", lang::phrase( "blog/status/" . $post['blog_post_status'] ) );
            action::add( "author_id", $post['user_id'] );
            action::add( "author_username", $post['user_name'] );
            action::add( "author_email", $post['user_email'] );
            action::add( "category_name", $post['blog_category_name'] );
            action::add( "category_title", $post['blog_category_title'] );
            action::add( "compacted", 0 );
            action::add( "category_color", $post['blog_category_color'] );
            action::add( "comments", $post['blog_post_comments'] );
            if( $post['blog_post_comments'] ) {
              action::add( "allow_comments", 1 );
            }
            if( $post['blog_post_pingbacks'] ) {
              action::add( "allow_pingbacks", 1 );
            }

            action::start( "tag_list" );
              db::open( TABLE_BLOG_POST_TAGS );
                db::where( "blog_post_id", $post['blog_post_id'] );
              while( $row = db::result() ) {
                action::start( "tag" );
                  action::add( "title", $row['blog_post_tag_title'] );
                  action::add( "name", $row['blog_post_tag_name'] );
                action::end();
              }
            action::end();

            action::start( "page_list" );
              db::open( TABLE_BLOG_POST_PAGES );
                db::select( "blog_post_page_title", "blog_post_page_id" );
                db::where( "blog_post_id", $post['blog_post_id'] );
                db::order( "blog_post_page_order", "ASC" );
                db::order( "blog_post_page_id", "ASC" );
              $current_page = 2;
              while( $row2 = db::result() ) {
                action::start( "page" );
                  action::add( "id", $row2['blog_post_page_id'] );
                  action::add( "number", $current_page );
                  action::add( "title", $row2['blog_post_page_title'] );
                action::end();
                $current_page++;
              }
            action::end();

          action::end();
        action::end();
      }
    }

    public static function list_comments() {
      $blog_post_id = sys::input( "blog_post_id", 0 );
      $blog_post_year = sys::input( "blog_post_year", 0 );
      $blog_post_month = sys::input( "blog_post_month", 0 );
      $blog_post_day = sys::input( "blog_post_day", 0 );
      $blog_post_name = sys::input( "blog_post_name", 0 );
      if( !$blog_post_id && ( !$blog_post_year || !$blog_post_month || !$blog_post_day || !$blog_post_name ) ) {
        sys::message( USER_ERROR, lang::phrase( "error/blog/get_post/invalid_identifier/title" ), lang::phrase( "error/blog/get_post/invalid_identifier/body" ) );
      }

      if( !$blog_post_id ) {
        db::open( TABLE_BLOG_POST_DATA );
          db::select( "blog_post_id" );
          db::where( "blog_post_name", $blog_post_name );
          db::open( TABLE_BLOG_POSTS );
            db::select_none();
            db::link( "blog_post_id" );
            db::where_year ( "blog_post_published", $blog_post_year );
            db::where_month( "blog_post_published", $blog_post_month );
            db::where_day( "blog_post_published", $blog_post_day );
        $post = db::result();
        db::clear_result();
        $blog_post_id = $post['blog_post_id'];
      }
      
      action::resume( "blog" );
        action::start( "comment_list" );
          self::get_post_comments( $blog_post_id );
        action::end();
      action::end();
    }

    private static function get_post_comments( $blog_post_id ) {
      db::open( TABLE_BLOG_POST_DATA );
        db::where( "blog_post_id", $blog_post_id );
      $post = db::result();
      db::clear_result();
      action::add( "id", $post['blog_post_id'] );
      action::add( "enabled", $post['blog_post_comments'] );
      
      $comments_page = sys::input( "comments_page", 0 );
      $comments_per_page = sys::input( "comments_per_page", 40 );
      $blog_comment_approved = sys::input( "blog_comment_approved", -1 );
      db::open( TABLE_BLOG_COMMENTS );
        if( $blog_comment_approved >= 0 ) {
          db::where( "blog_comment_approved", $blog_comment_approved );
        }
        db::where( "blog_post_id", $blog_post_id );
        if( $comments_page > 0 ) {
          db::limit( $comments_per_page*($comments_page-1), $comments_per_page );
        }
        db::order( "blog_comment_date", "DESC" );
        db::open( TABLE_USERS, LEFT );
          db::select( "user_name", "user_email" );
          db::link( "user_id" );
        db::close();
      while( $row = db::result() ) {
        action::start( "comment" );
          action::add( "id", $row['blog_comment_id'] );
          action::start( "created" );
            action::add( "datetime", $row['blog_comment_date'] );
            $created = strtotime( $row['blog_comment_date'] );
            action::add( "timestamp", $created );
            action::add( "period", sys::create_duration( time(), $created ) );
            $created += ( 60 * 60 ) * sys::timezone();
            action::add( "altered_timestamp", $created );
            action::add( "altered_datetime", sys::create_datetime( $created ) );
          action::end();
          action::add( "body", sys::clean_xml( $row['blog_comment_body'] ) );
          action::add( "formatted_body", self::parse_comment( sys::clean_xml( $row['blog_comment_body'] ) ) );
          action::add( "author", $row['blog_comment_author'] );
          action::add( "author_email", $row['blog_comment_author_email'] );
          action::add( "author_id", $row['user_id'] );
          if( $row['user_id'] && $row['user_id'] != ANONYMOUS ) {
            action::start( "user" );
              action::add( "id", $row['user_id'] );
              action::add( "name", $row['user_name'] );
            action::end();
          }
          action::add( "parent", $row['blog_comment_parent'] );
          action::add( "approved", $row['blog_comment_approved'] );
          action::add( "spam", $row['blog_comment_spam'] );
        action::end();
      }
    }

    public static function get_featured_post() {
      $blog_post_id = action::get( "url_variables/var" );
      if( is_bool( $blog_post_id ) ) {
        $blog_post_id = sys::input( "blog_post_id", 0 );
      }
      db::open( TABLE_BLOG_FEATURED_POSTS );
        db::where( "blog_post_id", $blog_post_id );
      $post = db::result();
      db::clear_result();
      action::resume( "blog/featured_post" );
        action::add( "id", $post['blog_featured_post_id'] );
        action::add( "title", $post['blog_featured_post_title'] );
        action::add( "size", $post['blog_featured_post_size'] );
        action::add( "active", $post['blog_featured_post_active'] );
      action::end();
    }

    private static function get_post_info( $id ) {
      db::open( TABLE_BLOG_POSTS );
        db::where( "blog_post_id", $id );
        db::open( TABLE_USERS );
          db::link( "user_id" );
        db::close();
        db::open( TABLE_BLOG_CATEGORIES, LEFT );
          db::link( "blog_category_name" );
        db::close();
      $post = db::result();
      db::clear_result();
      return $post;
    }

    public static function list_categories() {
      action::resume( "blog" );
        action::start( "blog_category_list" );
          db::open( TABLE_BLOG_CATEGORIES );
            db::order( "blog_category_name", "ASC" );
          while( $row = db::result() ) {
            action::start( "category" );
              action::add( "title", $row['blog_category_title'] );
              action::add( "name", $row['blog_category_name'] );
            action::end();
          }
        action::end();
      action::end();
    }

    public static function list_tags() {
      $tag_filter = sys::input( "tag_filter", "" );
      $tag_limit = sys::input( "tag_limit", 40 );
      $cache_id = "lt";
      if( $tag_filter ) {
        $cache_id .= ".tf" . $tag_filter;
      }
      if( $tag_limit ) {
        $cache_id .= ".tl" . $tag_limit;
      }
      if( CACHE_ENABLED || !$tag_list = cache::get( $cache_id, "blog/list_tags" ) ) {
        action::resume( "blog" );
          action::start( "tag_list" );
            db::open( TABLE_BLOG_POST_TAGS );
              if( $tag_filter ) {
                db::where( "blog_post_tag_title", $tag_filter . "%", "LIKE" );
              }
              db::select_as( "blog_post_tag_count" );
              db::select_count( "blog_post_tag_name" );
              db::select( "blog_post_tag_name", "blog_post_tag_title" );
              db::group( "blog_post_tag_name" );
              db::order( "blog_post_tag_count", "DESC", false );
              if( $tag_limit ) {
                db::limit( 0, $tag_limit );
              }
            $tag_list = array();
            while( $row = db::result() ) {
              $tag_list[] = $row;
            }
            if( $tag_limit == 0 ) {
              $tag_limit = count( $tag_list );
            }
            usort( $tag_list, "blog::sort_tags_by_name" );

            $high_count = 0;
            $low_count = 0;
            $tag_count = count( $tag_list );
            for( $i = 0; $i < $tag_count; $i++ ) {
              action::start( "tag" );
                action::add( "name", $tag_list[$i]['blog_post_tag_name'] );
                action::add( "title", $tag_list[$i]['blog_post_tag_title'] );
                action::add( "count", $tag_list[$i]['blog_post_tag_count'] );
              action::end();
              if( $tag_list[$i]['blog_post_tag_count'] > $high_count ) {
                $high_count = $tag_list[$i]['blog_post_tag_count'];
              }
              if( $tag_list[$i]['blog_post_tag_count'] < $low_count || $low_count == 0 ) {
                $low_count = $tag_list[$i]['blog_post_tag_count'];
              }
            }
            action::add( "top_tag", $high_count );
            action::add( "bottom_tag", $low_count );
          action::end();
        action::end();
        $tag_list = action::xpath( "blog/tag_list" );
        if( CACHE_ENABLED && $tag_list ) {
          cache::set( $tag_list->ownerDocument->saveXML(), -1, $cache_id, "blog/list_tags" );
        }
      } else {
        action::merge( simplexml_load_string( $tag_list ), "blog" );
      }
    }

    public static function sort_tags_by_count( $a, $b ) {
      if( $a['blog_post_tag_count'] > $b['blog_post_tag_count'] ) {
        return -1;
      } else if( $a['blog_post_tag_count'] == $b['blog_post_tag_count'] ) {
        return 0;
      } else if( $a['blog_post_tag_count'] < $b['blog_post_tag_count'] ) {
        return 1;
      }
    }

    public static function sort_tags_by_name( $a, $b ) {
      $test = array( $a['blog_post_tag_title'], $b['blog_post_tag_title'] );
      sort( $test );
      if( $test[0] == $a['blog_post_tag_title'] ) {
        return -1;
      } else {
        return 1;
      }
    }

    public static function parse_post( $text ) {
      $text = format::process( EXTENSIONS_DIR . "/blog", "post_formatting", $text );
      return $text;
    }

    public static function strip_post( $text ) {
      $text = format::process( EXTENSIONS_DIR . "/blog", "strip_formatting", $text, false );
      $text = str_replace( "\r\n", " ", $text );
      $text = str_replace( "\r", " ", $text );
      $text = str_replace( "\n", " ", $text );
      return $text;
    }

    public static function parse_comment( $text ) {
      $text = preg_replace( "/([^=\"\'])((https?|ftp|gopher|telnet|file|notes|ms-help):((\/\/)|(\\\\))+[\w\d:#@%\/;$()~_?\+-=\\\.&]*)/", "\\1<a rel=\"nofollow\" href=\"\\2\">\\2</a>", ' ' . $text );
      $text = format::process( EXTENSIONS_DIR . "/blog", "comment_formatting", $text );
      return $text;
    }

  }
?>