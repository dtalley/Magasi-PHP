<?php

/*
Copyright � 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/

  class forum {

    public static function query_log_recorded( $action, $type, $target ) {
      if( $type == 'forum_post' && $action == 'moderator' ) {
        db::open( TABLE_FORUM_REPORTS );
          db::where( "forum_post_id", $target );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/forum/delete_report/title" ),
            lang::phrase( "error/forum/delete_report/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      }
    }

    public static function query_get_extension_object( $target, $type ) {
      if( $type == "forum_post" ) {
        db::open( TABLE_FORUM_POSTS );
          db::where( "forum_post_id", $target );
          db::open( TABLE_USERS );
            db::link( "user_id" );
          db::close();
          db::open( TABLE_FORUM_TOPICS );
            db::select( "forum_topic_id", "forum_topic_title" );
            db::link( "forum_topic_id" );
            db::open( TABLE_FORUMS );
              db::select( "forum_id", "forum_name", "forum_title" );
              db::link( "forum_id" );
            db::close();
          db::close();
        $post = db::result();
        db::clear_result();
        if( $post ) {
          action::start( "forum_post" );
            action::add( "id", $post['forum_post_id'] );
            action::start( "author" );
              action::add( "id", $post['user_id'] );
              action::add( "name", $post['user_name'] );
            action::end();
            action::start( "topic" );
              action::add( "id", $post['forum_topic_id'] );
              action::add( "title", $post['forum_topic_title'] );
            action::end();
            action::start( "forum" );
              action::add( "id", $post['forum_id'] );
              action::add( "title", $post['forum_title'] );
              action::add( "name", $post['forum_name'] );
            action::end();
          action::end();
        }
      }
    }

    public static function hook_account_initialized() {
      $forum_action = sys::input( "forum_action", false, SKIP_GET );
      $actions = array(
        "edit_forum",
        "order_forum",
        "delete_forum",
        "edit_post",
        "flag_post",
        "delete_posts",
        "delete_transfers",
        "toggle_post",
        "initiate_move",
        "complete_move",
        "mark_topics_read",
        "delete_reports"
      );
      if( in_array( $forum_action, $actions ) ) {
        $evaluate = "self::$forum_action();";
        eval( $evaluate );
      }
    }

    private static function mark_topics_read() {
      $return_page = sys::input( "return_page", "" );
      if( $return_page ) {
        action::resume( "request" );
          action::add( "return_page", $return_page );
          action::add( "return_text", lang::phrase( "forum/return_to_previous" ) );
        action::end();
      }

      $forum_id = sys::input( "forum_id", 0 );
      $user_id = action::get( "user/id" );

      db::open( TABLE_FORUM_VIEWS );
        db::where( "forum_id", $forum_id );
        db::where( "user_id", $user_id );
      $forum = db::result();
      db::clear_result();

      $current_date = gmdate( "Y/m/d H:i:s", time() );
      db::open( TABLE_FORUM_VIEWS );
        db::set( "forum_view_date", $current_date );
        if( $forum ) {
          db::where( "forum_id", $forum['forum_id'] );
          db::where( "user_id", action::get( "user/id" ) );
          if( !db::update() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/forum/update_forum_view/title" ),
              lang::phrase( "error/forum/update_forum_view/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
        } else {
          db::set( "forum_id", $forum_id );
          db::set( "user_id", $user_id );
          if( !db::insert() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/forum/create_forum_view/title" ),
              lang::phrase( "error/forum/create_forum_view/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
        }

      action::resume( "forum/forum_action" );
        action::add( "action", "mark_topics_read" );
        action::add( "success", 1 );
        if( $forum_id ) {
          action::add( "message", lang::phrase( "forum/mark_topics_read/success/body" ) );
        } else {
          action::add( "message", lang::phrase( "forum/mark_forums_read/success/body" ) );
        }
      action::end();

      if( $return_page ) {
        if( $forum_id ) {
          sys::message( USER_MESSAGE, lang::phrase( "forum/mark_topics_read/success/title" ), lang::phrase( "forum/mark_topics_read/success/body" ) );
        } else {
          sys::message( USER_MESSAGE, lang::phrase( "forum/mark_forums_read/success/title" ), lang::phrase( "forum/mark_forums_read/success/body" ) );
        }
      }
    }

    private static function edit_forum() {
      $forum_id = sys::input( "forum_id", 0 );
      if( $forum_id && !auth::test( "forum", "edit_forums" ) ) {
        auth::deny( "forum", "edit_forums" );
      } else if( !$forum_id && !auth::test( "forum", "add_forums" ) ) {
        auth::deny( "forum", "add_forums" );
      }

      $forum_title = sys::input( "forum_title", NULL );
      $auto_forum_name = str_replace( " ", "-", strtolower( $forum_title ) );
      $forum_name = sys::input( "forum_name", false ) ? sys::input( "forum_name", "" ) : $auto_forum_name;
      $forum_name = preg_replace( "/([^a-zA-Z0-9\-]*?)/", "", $forum_name );
      $forum_name = preg_replace( "/(-+?)/", "-", $forum_name );
      $forum_description = sys::input( "forum_description", NULL );
      $forum_parent = sys::input( "forum_parent", -1 );
      $forum_status = sys::input( "forum_status", "closed" );

      $forum_order = 0;
      if( $forum_parent >= 0 ) {
        db::open( TABLE_FORUMS );
          db::where( "forum_parent", $forum_parent );
          db::order( "forum_order", "DESC" );
          db::limit( 0, 1 );
        $last_forum = db::result();
        db::clear_result();
      } else {
        $forum_parent = 0;
      }
      
      db::open ( TABLE_FORUMS );
        db::set( "forum_title", $forum_title );
        db::set( "forum_description", $forum_description );
        if( $forum_parent ) {
          db::set( "forum_parent", $forum_parent );
        }
        db::set( "forum_name", $forum_name );
        db::set( "forum_status", $forum_status );

      if( $forum_id ) {
        db::where( "forum_id", $forum_id );
        if( !db::update() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/forum/edit_forum/title" ),
            lang::phrase( "error/forum/edit_forum/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        action::resume( "forum/forum_action" );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "forum/edit_forum/success" ) );
        action::end();
      } else {
        if( $last_forum ) {
          db::set( "forum_order", $last_forum['forum_order'] + 1 );
        } else {
          db::set( "forum_order", 1 );
        }
        if( !db::insert() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/forum/add_forum/title" ),
            lang::phrase( "error/forum/add_forum/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        auth::authenticate_all_with_permission( "global", "forum", 0, "add_forums", "target", "forum", db::id() );
        action::resume( "forum" );
          action::start( "forum_action" );
            action::add( "success", 1 );
            action::add( "message", lang::phrase( "forum/add_forum/success" ) );
          action::end();
        action::end();
      }
    }

    private static function order_forum() {
      $forum_id = sys::input( "forum_id", 0 );
      db::open( TABLE_FORUMS );
        db::where( "forum_id", $forum_id );
      $forum = db::result();
      db::clear_result();
      if( $forum ) {
        $order_forum_up = sys::input( "order_forum_" . $forum_id . "_up", 0 );
        db::open( TABLE_FORUMS );
          if( $order_forum_up ) {
            db::where( "forum_order", $forum['forum_order'] - 1 );
          } else {
            db::where( "forum_order", $forum['forum_order'] + 1 );
          }
          db::where( "forum_parent", $forum['forum_parent'] );
        $affected = db::result();
        db::clear_result();
        if( $affected ) {
          db::open( TABLE_FORUMS );
            db::where( "forum_id", $forum_id );
            if( $order_forum_up ) {
              db::set( "forum_order", "forum_order-1", false );
            } else {
              db::set( "forum_order", "forum_order+1", false );
            }
          if( !db::update() ) {
            sys::message( SYSTEM_ERROR, lang::phrase( "error/forum/order_target_forum/title" ), lang::phrase( "error/forum/order_target_forum/body" ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
          }
          db::open( TABLE_FORUMS );
            db::where( "forum_id", $affected['forum_id'] );
            if( $order_forum_up ) {
              db::set( "forum_order", "forum_order+1", false );
            } else {
              db::set( "forum_order", "forum_order-1", false );
            }
          if( !db::update() ) {
            sys::message( SYSTEM_ERROR, lang::phrase( "error/forum/order_affected_forum/title" ), lang::phrase( "error/forum/order_affected_forum/body" ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
          }
          action::resume( "forum" );
            action::start( "forum_action" );
              action::add( "action", "order_forum" );
              action::add( "message", lang::phrase( "forum/order_forum/success" ) );
              action::add( "success", 1 );
            action::end();
          action::end();
        }
      }
    }

    private static function delete_forum() {
      $forum_id = sys::input( "forum_id", 0 );
      db::open( TABLE_FORUMS );
        db::where( "forum_id", $forum_id );
        db::open( TABLE_FORUM_TOPICS );
          db::link( "forum_id" );
          db::open( TABLE_FORUM_POSTS );
            db::link( "forum_topic_id" );
      if( !db::delete() ) {
        
      }
      action::resume( "forum/forum_action" );
        action::add( "action", "delete_forum" );
        action::add( "message", lang::phrase( "forum/delete_forum/success" ) );
        action::add( "success", 1 );
      action::end();
    }

    private static function edit_post() {
      $forum_post_id = sys::input( "forum_post_id", 0 );
      $forum_topic_id = sys::input( "forum_topic_id", 0 );
      $forum_id = sys::input( "forum_id", 0 );
      $forum_post_formatting_enabled = 1;
      $post = null;
      if( $forum_post_id ) {
        db::open( TABLE_FORUM_POSTS );
          db::select( "user_id", "forum_post_originator", "forum_post_id" );
          db::where( "forum_post_id", $forum_post_id );
          if( $forum_topic_id ) {
            db::open( TABLE_FORUM_TOPICS );
              db::select( "forum_topic_id", "forum_topic_title" );
              db::link( "forum_topic_id" );
              if( !$forum_id ) {
                db::open( TABLE_FORUMS );
                  db::select( "forum_id", "forum_name", "forum_title" );
                  db::link( "forum_id" );
                db::close();
              }
            db::close();
          }
          db::open( TABLE_USERS, LEFT );
            db::select( "user_name" );
            db::link( "user_id" );
          db::close();
        $post = db::result();
        db::clear_result();
        $mod_edit = false;
        if( $post['forum_topic_status'] == "closed" && !auth::test( "forum", "edit_closed_posts", "target", $forum_id ) ) {
          auth::deny( "forum", "edit_closed_posts" );
        }
        if( $post['user_id'] == action::get( "user/user_id" ) ) {
          if( !auth::test( "forum", "edit_own_posts", "target", $forum_id ) || !auth::test( "forum", "edit_own_posts" ) ) {
            auth::deny( "forum", "edit_own_posts" );
          }
          if( !auth::test( "forum", "edit_own_posts" ) ) {
            auth::deny( "forum", "edit_own_posts" );
          }
        } else {
          if( !auth::test( "forum", "edit_posts", "target", $forum_id ) ) {
            auth::deny( "forum", "edit_posts" );
          }
          $mod_edit = true;
        }
      } else {
        if( !auth::test( "forum", "add_posts", "target", $forum_id ) || !auth::test( "forum", "add_posts" ) ) {
          auth::deny( "forum", "add_posts" );
        }
        if( !auth::test( "forum", "add_posts" ) ) {
          auth::deny( "forum", "add_posts" );
        }
        if( !$forum_topic_id ) {
          if( !auth::test( "forum", "add_topics", "target", $forum_id ) || !auth::test( "forum", "add_topics" ) ) {
            auth::deny( "forum", "add_topics" );
          }
          if( !auth::test( "forum", "add_topics" ) ) {
            auth::deny( "forum", "add_topics" );
          }
        }
        if( !auth::test( "forum", "use_formatting" ) ) {
          $forum_post_formatting_enabled = 0;
        }
      }
      
      $forum_topic_title = sys::input( "forum_topic_title", NULL );
      $forum_topic_title = str_replace( "“", "\"", $forum_topic_title );
      $forum_topic_title = str_replace( "�?", "\"", $forum_topic_title );
      $forum_topic_title = str_replace( "’", "'", $forum_topic_title );
      $forum_topic_title = str_replace( "—", "-", $forum_topic_title );
      $forum_topic_title = preg_replace( "/[^\w\d\s<>\/\-_&%\$#@\[\]\(\)\?\*!\+\.\^\\\"'{}=,;:|]/si", "", $forum_topic_title );
      $forum_topic_type = sys::input( "forum_topic_type", "topic" );
      $forum_topic_status = sys::input( "forum_topic_status", "" );
      $topic_created = false;
      $post_created = false;
      $forum_post_body = sys::input( "forum_post_body", NULL );
      $preview_post = sys::input( "preview_post", 0 );
      action::resume( "forum/post" );
        action::add( "body", $forum_post_body );
        if( $forum_post_id ) {
          action::add( "id", $forum_post_id );
        } else {
          action::add( "id", "new" );
        }
        if( !$forum_topic_id ) {
          action::add( "original", 1 );
        }
      action::end();
      action::resume( "forum/topic" );
        if( $forum_topic_id ) {
          action::add( "id", $forum_topic_id );
        }
        if( $forum_topic_title ) {
          action::add( "title", $forum_topic_title );
        }
        if( $forum_topic_type ) {
          action::add( "type", $forum_topic_type );
        }
        if( $forum_topic_status ) {
          action::add( "status", $forum_topic_status );
        }
      action::end();
      action::resume( "forum/forum" );
        action::add( "id", $forum_id );
      action::end();

      if( !$forum_topic_title && ( !$forum_topic_id || $post['forum_post_originator'] ) ) {
        action::resume( "forum/forum_action" );
          action::add( "action", "edit_post" );
          action::add( "success", 0 );
          action::add( "message", lang::phrase( "forum/edit_post/no_topic_title" ) );
        action::end();
        return;
      }

      switch( $forum_topic_type ) {
        case "announcement":
          if( !auth::test( "forum", "post_announcements", "target", $forum_id ) ) {
            auth::deny( "forum", "post_announcements" );
          }
          break;
        case "sticky":
          if( !auth::test( "forum", "post_stickies", "target", $forum_id ) ) {
            auth::deny( "forum", "post_stickies" );
          }
          break;
      }
      $switch_status = false;
      switch( $forum_topic_status ) {
        case "closed":
        case "open":
          if( !auth::test( "forum", "close_topics", "target", $forum_id ) ) {
            auth::deny( "forum", "close_topics" );
          }
          $switch_status = true;
          break;
      }
      if( !$forum_topic_status ) {
        $forum_topic_status = "open";
      }

      $user_id = action::get( "user/user_id" );
      $forum_post_body = str_replace( "“", "\"", $forum_post_body );
      $forum_post_body = str_replace( "�?", "\"", $forum_post_body );
      $forum_post_body = str_replace( "’", "'", $forum_post_body );
      $forum_post_body = str_replace( "—", "-", $forum_post_body );
      //$forum_post_body = preg_replace( "/&([^&\s]*?);/", "", $forum_post_body );
      $forum_post_body = preg_replace( "/[^\w\d\s<>\/\-_&%\$#@\[\]\(\)\?\*!\+\.\^\\\"'{}=,;:|]/si", "", $forum_post_body );
      $current_date = gmdate( "Y/m/d H:i:s", time() );

      if( !$forum_post_body && !$preview_post ) {
        action::resume( "forum/forum_action" );
          action::add( "action", "edit_post" );
          action::add( "success", 0 );
          action::add( "message", lang::phrase( "forum/edit_post/no_post_body" ) );
        action::end();
        return;
      } else if( $forum_post_body ) {
        libxml_use_internal_errors(true);
        $body = "<root>" . $forum_post_body . "</root>";
        $body = str_replace( "&", "&amp;", $body );
        $test = @simplexml_load_string( $body );
        if( !$test ) {
          action::resume( "forum/forum_action" );
            action::add( "action", "edit_post" );
            action::add( "success", 0 );
            $split = explode( "\n", $forum_post_body );
            $errors = libxml_get_errors();
            $line = '';
            foreach( $errors as $error ) {
              //$line = "(" . $error->code . ") " . $error->message;
              $line = $split[((int)$error->line)-1];
              $line = str_replace( "<", "&amp;lt;", $line );
              $line = str_replace( ">", "&amp;gt;", $line );
              break;
            }
            $body = "forum/edit_post/errors_in_post/general";
            if( in_array( $error->code, array(76,77) ) ) {
              $body = "forum/edit_post/errors_in_post/error_" . $error->code;
            }
            action::add( "message", lang::phrase( "forum/edit_post/errors_in_post/number", count( $errors ) ) . lang::phrase( $body ) . ( $line ? lang::phrase( "forum/edit_post/errors_in_post/line", $line ) : "" ) );
          action::end();
          return;
        }
      }

      if( $preview_post && $forum_post_body ) {
        action::resume( "forum/post" );
          action::add( "formatted_body", self::parse_post( $forum_post_body ) );
          action::add( "preview", 1 );
        action::end();
        return;
      } else if( $preview_post ) {
        return;
      }

      if( !$forum_post_id ) {
        $post_spam = gmdate( "Y/m/d H:i:s", time()-(60*1) );
        $topic_spam = gmdate( "Y/m/d H:i:s", time()-(60*5) );
        if( !$forum_topic_id ) {
          db::open( TABLE_FORUM_POSTS );
            db::where( "user_id", action::get( "user/id" ) );
            db::where( "forum_post_date", $topic_spam, ">" );
            db::where( "forum_post_originator", 1 );
        } else {
          db::open( TABLE_FORUM_POSTS );
            db::where( "user_id", action::get( "user/id" ) );
            db::where( "forum_post_date", $post_spam, ">" );
        }
        $spam = db::result();
        db::clear_result();
        if( $spam ) {
          action::resume( "forum/forum_action" );
            action::add( "action", "edit_post" );
            action::add( "success", 0 );
            if( !$forum_topic_id ) {
              action::add( "message", lang::phrase( "forum/edit_post/topic_flood" ) );
            } else {
              action::add( "message", lang::phrase( "forum/edit_post/post_flood" ) );
            }
          action::end();
          return;
        }
      }

      if( !$forum_topic_id ) {
        db::open( TABLE_FORUM_TOPICS );
          db::set( "forum_id", $forum_id );
          db::set( "forum_topic_title", $forum_topic_title );
          db::set( "forum_topic_type", $forum_topic_type );
          db::set( "forum_topic_status", $forum_topic_status );
        if( !db::insert() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/forum/add_forum_topic/title" ),
            lang::phrase( "error/forum/add_forum_topic/body" )
          );
        }
        $forum_topic_id = db::id();
        $topic_created = true;
      }

      db::open( TABLE_FORUM_POSTS );
        db::set( "forum_post_body", $forum_post_body );

      $message = "";
      if( $forum_post_id ) {
        db::set( "forum_post_edited", $current_date );
        db::set( "forum_post_editor", action::get( "user/id" ) );
        db::where( "forum_post_id", $forum_post_id );
        if( !db::update() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/forum/edit_forum_post/title" ),
            lang::phrase( "error/forum/edit_forum_post/body" )
          );
        }
        if( $post['forum_post_originator'] || $switch_status ) {
          db::open( TABLE_FORUM_TOPICS );
            if( $post['forum_post_originator'] ) {
              db::set( "forum_topic_title", $forum_topic_title );
              if( $forum_topic_type ) {
                db::set( "forum_topic_type", $forum_topic_type );
              }
            }
            if( $switch_status ) {
              db::set( "forum_topic_status", $forum_topic_status );
            }
            db::where( "forum_topic_id", $forum_topic_id );
          if( !db::update() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/forum/edit_forum_topic/title" ),
              lang::phrase( "error/forum/edit_forum_topic/body" )
            );
          }

          if( $forum_topic_status == 'closed' ) {
            db::open( TABLE_FORUM_POST_TRANSFERS );
              db::where( "forum_post_id", $post['forum_post_id'] );
            if( !db::delete() ) {
              sys::message(
                SYSTEM_ERROR,
                lang::phrase( "error/forum/delete_closed_topic_transfer/title" ),
                lang::phrase( "error/forum/delete_closed_topic_transfer/body" )
              );
            }
          }
        }
        if( $mod_edit ) {
          logs::record_log( 
            "forum_post",
            "moderator",
            $post['forum_post_id'],
            "edit",
            lang::phrase(
              "forum/forum_post/edit_post/moderator_action",
              action::get( "user/name" ),
              $post['user_name'],
              $post['forum_topic_title']
            )
          );
        }
        $message = lang::phrase( "forum/forum_post/edit_post/success" );
      } else {
        db::set( "forum_topic_id", $forum_topic_id );
        db::set( "user_id", $user_id );
        db::set( "forum_post_date", $current_date );
        db::set( "forum_post_enabled", 1 );
        db::set( "forum_post_formatting_enabled", $forum_post_formatting_enabled );
        db::set( "forum_post_ip", $_SERVER['REMOTE_ADDR'] );
        if( $topic_created ) {
          db::set( "forum_post_originator", 1 );
        }
        if( !db::insert() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/forum/add_forum_topic/title" ),
            lang::phrase( "error/forum/add_forum_topic/body" )
          );
        }
        $forum_post_id = db::id();
        $post_created = true;
        if( $topic_created ) {
          $message = lang::phrase( "forum/forum_post/create_topic/success" );
        } else {
          $message = lang::phrase( "forum/forum_post/add_reply/success" );
        }
        
        db::open( TABLE_FORUM_TOPICS );
          db::set( "forum_topic_post_count", "forum_topic_post_count+1", false );
          db::set( "forum_post_id", $forum_post_id );
          if( $switch_status ) {
            db::set( "forum_topic_status", $forum_topic_status );
          }
          db::where( "forum_topic_id", $forum_topic_id );
        if( !db::update() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/forum/update_forum_topic_post_count/title" ),
            lang::phrase( "error/forum/update_forum_topic_post_count/body" )
          );
        }

        db::open( TABLE_FORUMS );
          db::set( "forum_post_count", "forum_post_count+1", false );
          db::set( "forum_post_id", $forum_post_id );
          if( $topic_created ) {
            db::set( "forum_topic_count", "forum_topic_count+1", false );
          }
          db::where( "forum_id", $forum_id );
        if( !db::update() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/forum/update_forum_post_count/title" ),
            lang::phrase( "error/forum/update_forum_post_count/body" )
          );
        }
      }

      if( $post['forum_post_originator'] || $forum_topic_status != $post['forum_topic_status'] || $post_created ) {
        /**
         * CLEAR CACHE
         */
        //cache::clear( "", "forum/list_hot_topics" );
      }
      //cache::clear( "", "forum/list_forums" );

      action::resume( "forum/forum_action" );
        action::add( "action", "edit_post" );
        action::add( "success", 1 );
        action::add( "message", $message );
      action::end();

      $show_message = sys::input( "show_message", false );
      if( $show_message ) {
        action::resume( "request" );
          action::add( "return_page", RELATIVE_DIR . "/forum/topic/" . $forum_topic_id . "/post/" . $forum_post_id . "#" . $forum_post_id );
          action::add( "return_text", lang::phrase( "forum/view_post/body" ) );
        action::end();
        sys::message( USER_MESSAGE, lang::phrase( "forum/success" ), $message );
      }
    }

    public static function flag_post() {
      $forum_post_ids = sys::input( "forum_post_id", array() );
      $user_id = action::get( "user/id" );
      $total_posts = count( $forum_post_ids );
      $return_page = sys::input( "return_page", "" );
      $forum_topic_report_reason = sys::input( "forum_topic_report_reason", "" );
      if( $return_page ) {
        action::resume( "request" );
          action::add( "return_page", $return_page );
          action::add( "return_text", lang::phrase( "forum/return_to_previous" ) );
        action::end();
      }
      for( $i = 0; $i < $total_posts; $i++ ) {
        db::open( TABLE_FORUM_REPORTS );
          db::where( "forum_post_id", $forum_post_id );
        $report = db::result();
        db::clear_result();
        $current_date = gmdate( "Y/m/d H:i:s", time() );
        db::open( TABLE_FORUM_REPORTS );
        if( $report ) {
          db::set( "forum_report_additions", "forum_report_additions+1", false );
          db::set( "forum_report_date", $current_date );
          db::where( "forum_post_id", $forum_post_ids[$i] );
          if( !db::update() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/forum/update_report/title" ),
              lang::phrase( "error/forum/update_report/body" )
            );
          }
        } else {
          db::set( "forum_post_id", $forum_post_ids[$i] );
          db::set( "user_id", $user_id );
          db::set( "forum_report_date", $current_date );
          db::set( "forum_report_reason", $forum_topic_report_reason );
          if( !db::insert() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/forum/create_report/title" ),
              lang::phrase( "error/forum/create_report/body" )
            );
          }
        }
      }
      action::resume( "forum/forum_action" );
        action::add( "action", "flag_post" );
        action::add( "success", 1 );
        action::add( "message", lang::phrase( "forum/flag_post/success" ) );
      action::end();
      if( $return_page ) {
        sys::message( USER_MESSAGE, lang::phrase( "forum/flag_post/success/title" ), lang::phrase( "forum/flag_post/success/body" ) );
      }
    }

    private static function delete_transfers() {
      $forum_post_ids = sys::input( "forum_post_id", array() );
      if( !is_array( $forum_post_ids ) ) {
        $forum_post_ids = array( $forum_post_ids );
      }
      $forum_post_remove_reason = sys::input( "forum_post_remove_reason", "" );
      $total_posts = count( $forum_post_ids );
      for( $i = 0; $i < $total_posts; $i++ ) {
        db::open( TABLE_FORUM_POST_TRANSFERS );
          db::where( "forum_post_id", $forum_post_ids[$i] );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/forum/delete_transfers/title" ),
            lang::phrase( "error/forum/delete_transfers/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      }

      action::resume( "forum/forum_action" );
        action::add( "action", "remove_transfers" );
        action::add( "success", 1 );
        action::add( "message", lang::phrase( "forum/remove_transfers/success/body" ) );
      action::end();
      $return_page = sys::input( "return_page", "" );
      if( $return_page ) {
        action::resume( "request" );
          action::add( "return_page", $return_page );
          action::add( "return_text", lang::phrase( "forum/return_to_previous" ) );
        action::end();
        sys::message( USER_MESSAGE, lang::phrase( "forum/remove_transfers/success/title" ), lang::phrase( "forum/remove_transfers/success/body" ) );
      } 
    }

    private static function delete_reports() {
      $forum_post_ids = sys::input( "forum_post_id", array() );
      if( !is_array( $forum_post_ids ) ) {
        $forum_post_ids = array( $forum_post_ids );
      }
      $forum_report_delete_reason = sys::input( "forum_report_delete_reason", "" );
      $total_posts = count( $forum_post_ids );
      $return_page = sys::input( "return_page", "" );
      if( $return_page ) {
        action::resume( "request" );
          action::add( "return_page", $return_page );
          action::add( "return_text", lang::phrase( "forum/return_to_previous" ) );
        action::end();
      }
      for( $i = 0; $i < $total_posts; $i++ ) {
        db::open( TABLE_FORUM_REPORTS );
          db::where( "forum_post_id", $forum_post_ids[$i] );
        $report = db::result();
        db::clear_result();
        db::open( TABLE_FORUM_REPORTS );
          db::where( "forum_post_id", $forum_post_ids[$i] );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/forum/delete_reports/title" ),
            lang::phrase( "error/forum/delete_reports/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      }

      action::resume( "forum/forum_action" );
        action::add( "action", "delete_reports" );
        action::add( "success", 1 );
        action::add( "message", lang::phrase( "forum/delete_reports/success/body" ) );
      action::end();
      if( $return_page ) {
        sys::message( USER_MESSAGE, lang::phrase( "forum/delete_reports/success/title" ), lang::phrase( "forum/delete_reports/success/body" ) );
      }
    }

    private static function delete_posts() {
      $forum_post_ids = sys::input( "forum_post_id", array() );
      if( !is_array( $forum_post_ids ) ) {
        $forum_post_ids = array( $forum_post_ids );
      }
      $forum_post_delete_reason = sys::input( "forum_post_delete_reason", "" );
      $total_posts = count( $forum_post_ids );
      $return_page = sys::input( "return_page", "" );
      if( $return_page ) {
        action::resume( "request" );
          action::add( "return_page", $return_page );
          action::add( "return_text", lang::phrase( "forum/return_to_previous" ) );
        action::end();
      }  
      for( $i = 0; $i < $total_posts; $i++ ) {
        self::process_delete_post( $forum_post_ids[$i], $forum_post_delete_reason );
      }

      /**
       * CLEAR CACHE
       */
      //cache::clear( "", "forum/list_hot_topics" );
      //cache::clear( "", "forum/list_forums" );

      tpl::update_dependency( "forum_hot_topic_list" );

      action::resume( "forum/forum_action" );
        action::add( "action", "delete_posts" );
        action::add( "success", 1 );
        action::add( "message", lang::phrase( "forum/delete_posts/success/body" ) );
      action::end();
      if( $return_page ) {
        sys::message( USER_MESSAGE, lang::phrase( "forum/delete_posts/success/title" ), lang::phrase( "forum/delete_posts/success/body" ) );
      }   
    }

    private static function process_delete_post( $forum_post_id, $reason ) {
      db::open( TABLE_FORUM_POSTS );
        db::where( "forum_post_id", $forum_post_id );
        db::open( TABLE_FORUM_TOPICS );
          db::select( "forum_topic_id", "forum_topic_title", "forum_destination_id" );
          db::link( "forum_topic_id" );
          db::open( TABLE_FORUMS );
            db::select( "forum_id", "forum_title" );
            db::link( "forum_id" );
          db::close();
        db::close();
        db::open( TABLE_USERS, LEFT );
          db::select( "user_name" );
          db::link( "user_id" );
        db::close();
      $post = db::result();
      db::clear_result();
      if( !$post ) {
        sys::message( USER_ERROR, lang::phrase( "error/forum/delete_post/missing_post/title" ), lang::phrase( "error/forum/delete_post/missing_post/body" ) );
      }
      if( !auth::test( "forum", "delete_posts", "target", $post['forum_id'] ) ) {
        auth::deny( "forum", "delete_posts" );
      }

      $forum_id = $post['forum_id'];
      $forum_topic_id = $post['forum_topic_id'];
      $delete_topic = false;
      if( $post['forum_post_originator'] ) {
        $delete_topic = true;
      }
      if( $delete_topic ) {
        //Delete the topic
        db::open( TABLE_FORUM_TOPICS );
          db::where( "forum_topic_id", $forum_topic_id );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/forum/delete_topic/title" ),
            lang::phrase( "error/forum/delete_topic/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        //Get the post count for the topic
        db::open( TABLE_FORUM_POSTS );
          db::where( "forum_topic_id", $forum_topic_id );
          db::select_as( "post_count" );
          db::select_count( "forum_post_id" );
          db::group( "forum_topic_id" );
        $count = db::result();
        db::clear_result();
        $total_posts = $count['post_count'];
        //Delete any transfer request for any of this topic's posts
        db::open( TABLE_FORUM_POST_TRANSFERS );
          db::where( "forum_topic_id", $forum_topic_id );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/forum/delete_topic_transfers/title" ),
            lang::phrase( "error/forum/delete_topic_transfers/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        //Delete all of the posts from the topic
        db::open( TABLE_FORUM_POSTS );
          db::where( "forum_topic_id", $forum_topic_id );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/forum/delete_topic_posts/title" ),
            lang::phrase( "error/forum/delete_topic_posts/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        //Delete all of the topics that point to this topic
        db::open( TABLE_FORUM_TOPICS );
          db::where( "forum_destination_id", $forum_topic_id );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/forum/delete_pointer_topics/title" ),
            lang::phrase( "error/forum/delete_pointer_topics/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        logs::record_log(
          "forum_post",
          "moderator",
          $post['forum_post_id'],
          "delete",
          lang::phrase(
            "forum/forum_topic/delete_topic/moderator_action",
            action::get( "user/name" ),
            $post['user_name'],
            $post['forum_topic_title'],
            $reason
          )
        );
      } else {
        //Delete any transfer requests for this post
        db::open( TABLE_FORUM_POST_TRANSFERS );
          db::where( "forum_post_id", $forum_post_id );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/forum/delete_post_transfer/title" ),
            lang::phrase( "error/forum/delete_post_transfer/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        //Delete the post
        db::open( TABLE_FORUM_POSTS );
          db::where( "forum_post_id", $forum_post_id );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/forum/delete_post/title" ),
            lang::phrase( "error/forum/delete_post/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        logs::record_log(
          "forum_post",
          "moderator",
          $post['forum_post_id'],
          "delete",
          lang::phrase(
            "forum/forum_post/delete_post/moderator_action",
            action::get( "user/name" ),
            $post['user_name'],
            $post['forum_topic_title'],
            $reason
          )
        );
      }

      //Update the forum
      self::update_forum( $forum_id );

      //Update the topic
      if( !$delete_topic ) {
        self::update_forum_topic( $forum_topic_id );
      }

      return $delete_topic;
    }

    private static function update_forum( $forum_id ) {
      db::open( TABLE_FORUM_POSTS );
        db::select_as( "post_count" );
        db::select_count( "forum_post_id" );
        db::where( "forum_post_enabled", 1 );
        db::open( TABLE_FORUM_TOPICS );
          db::select( "forum_topic_id" );
          db::link( "forum_topic_id" );
          db::open( TABLE_FORUM_POSTS );
            db::select_none();
            db::link( "forum_topic_id" );
            db::where( "forum_post_originator", 1 );
            db::where( "forum_post_enabled", 1 );
          db::close();
          db::open( TABLE_FORUMS );
            db::select( "forum_id" );
            db::link( "forum_id" );
            db::where( "forum_id", $forum_id );
            db::group( "forum_id" );
      $post_count = db::result();
      db::clear_result();

      db::open( TABLE_FORUM_TOPICS );
        db::select_as( "topic_count" );
        db::select_count( "forum_topic_id" );
        db::where( "forum_topic_status", "moved", "!=" );
        db::open( TABLE_FORUM_POSTS );
          db::select_none();
          db::link( "forum_topic_id" );
          db::where( "forum_post_originator", 1 );
          db::where( "forum_post_enabled", 1 );
        db::close();
        db::open( TABLE_FORUMS );
          db::select( "forum_id" );
          db::link( "forum_id" );
          db::where( "forum_id", $forum_id );
          db::group( "forum_id" );
      $topic_count = db::result();
      db::clear_result();

      db::open( TABLE_FORUM_POSTS );
        db::select( "forum_post_id" );
        db::where( "forum_post_enabled", 1 );
        db::order( "forum_post_date", "DESC" );
        db::open( TABLE_FORUM_TOPICS );
          db::select( "forum_topic_id" );
          db::link( "forum_topic_id" );
          db::open( TABLE_FORUM_POSTS );
            db::select_as( "first_post_id" );
            db::select( "forum_post_id" );
            db::link( "forum_topic_id" );
            db::where( "forum_post_enabled", 1 );
            db::where( "forum_post_originator", 1 );
          db::close();
          db::open( TABLE_FORUMS );
            db::select( "forum_id" );
            db::link( "forum_id" );
            db::where( "forum_id", $forum_id );
          db::close();
        db::close();
        db::limit( 0, 1 );
      $last_post = db::result();
      db::clear_result();
      
      db::open( TABLE_FORUMS );
        db::where( "forum_id", $forum_id );
        db::set( "forum_topic_count", $topic_count['topic_count'] );
        db::set( "forum_post_count", $post_count['post_count'] );
        db::set( "forum_post_id", $last_post['forum_post_id'] );
      if( !db::update() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/forum/update_forum/title" ),
          lang::phrase( "error/forum/update_forum/body" ),
          __FILE__, __LINE__, __FUNCTION__, __CLASS__
        );
      }
    }

    private static function update_forum_topic( $forum_topic_id ) {
      db::open( TABLE_FORUM_POSTS );
        db::select_as( "post_count" );
        db::select_count( "forum_post_id" );
        db::where( "forum_post_enabled", 1 );
        db::open( TABLE_FORUM_TOPICS );
          db::select( "forum_topic_id" );
          db::link( "forum_topic_id" );
          db::where( "forum_topic_id", $forum_topic_id );
          db::group( "forum_topic_id" );
        db::close();
      $post_count = db::result();
      db::clear_result();

      db::open( TABLE_FORUM_POSTS );
        db::select( "forum_post_id" );
        db::where( "forum_post_enabled", 1 );
        db::order( "forum_post_date", "DESC" );
        db::open( TABLE_FORUM_TOPICS );
          db::select( "forum_topic_id" );
          db::link( "forum_topic_id" );
          db::where( "forum_topic_id", $forum_topic_id );
        db::close();
        db::limit( 0, 1 );
      $last_post = db::result();
      db::clear_result();
      
      db::open( TABLE_FORUM_TOPICS );
        db::where( "forum_topic_id", $forum_topic_id );
        db::set( "forum_topic_post_count", $post_count['post_count'] );
        db::set( "forum_post_id", $last_post['forum_post_id'] );
      if( !db::update() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/forum/update_topic/title" ),
          lang::phrase( "error/forum/update_topic/body" ),
          __FILE__, __LINE__, __FUNCTION__, __CLASS__
        );
      }

      tpl::update_dependency( "forum_hot_topic_list" );
    }

    private static function move_topic() {
      $forum_post_id = sys::input( "forum_post_id", 0 );
      $forum_id = sys::input( "forum_id", 0 );
      db::open( TABLE_FORUM_POSTS );
        db::where( "forum_post_id", $forum_post_id );
        db::open( TABLE_FORUM_TOPICS );
          db::select( "forum_topic_id", "forum_topic_title", "forum_topic_type", "forum_topic_status", "forum_topic_post_count", "forum_topic_view_count" );
          db::select_as( "topic_last_post" );
          db::select( "forum_post_id" );
          db::link( "forum_topic_id" );
          db::open( TABLE_FORUMS );
            db::link( "forum_id" );
      $post = db::result();
      db::clear_result();
      if( !auth::test( "forum", "move_posts", "target", $post['forum_id'] ) ) {
        auth::deny( "forum", "move_posts" );
      }
      db::open( TABLE_FORUM_TOPICS );
        db::set( "forum_id", $forum_id );
        db::set( "forum_topic_title", $post['forum_topic_title'] );
        db::set( "forum_topic_type", $post['forum_topic_type'] );
        db::set( "forum_topic_status", $post['forum_topic_status'] );
        db::set( "forum_topic_post_count", $post['forum_topic_post_count'] );
        db::set( "forum_topic_view_count", $post['forum_topic_view_count'] );
        db::set( "forum_post_id", $post['topic_last_post'] );
      if( !db::insert() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/forum/create_moved_topic/title" ),
          lang::phrase( "error/forum/create_moved_topic/body" )
        );
      }
      $forum_topic_id = db::id();
      db::open( TABLE_FORUM_POSTS );
        db::where( "forum_topic_id", $post['forum_topic_id'] );
        db::set( "forum_topic_id", $forum_topic_id );
      if( !db::update() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/forum/update_moved_posts/title" ),
          lang::phrase( "error/forum/update_moved_posts/body" )
        );
      }
      db::open( TABLE_FORUM_TOPICS );
        db::where( "forum_topic_id", $post['forum_topic_id'] );
        db::set( "forum_topic_status", "moved" );
        db::set( "forum_destination_id", $forum_topic_id );
        db::set( "forum_topic_view_count", 0 );
        db::set( "forum_topic_post_count", 0 );
      if( !db::update() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/forum/update_moved_topic/title" ),
          lang::phrase( "error/forum/update_moved_topic/body" )
        );
      }
      db::open( TABLE_FORUM_TOPICS );
        db::where( "forum_destination_id", $post['forum_topic_id'] );
        db::set( "forum_destination_id", $forum_topic_id );
      if( !db::update() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/forum/update_old_moved_topics/title" ),
          lang::phrase( "error/forum/update_old_moved_topics/body" )
        );
      }
      db::open( TABLE_FORUMS );
        db::where( "forum_id", $forum_id );
        db::open( TABLE_FORUM_TOPICS );
          db::link( "forum_id" );
          db::open( TABLE_FORUM_POSTS );
            db::link( "forum_topic_id" );
            db::order( "forum_post_date", "DESC" );
          db::close();
        db::close();
        db::limit( 0, 1 );
      $last_post = db::result();
      db::clear_result();
      db::open( TABLE_FORUMS );
        db::where( "forum_id", $forum_id );
        db::set( "forum_topic_count", "forum_topic_count+1", false );
        db::set( "forum_post_count", "forum_post_count+" . $post['forum_topic_post_count'], false );
        if( $last_post ) {
          db::set( "forum_post_id", $last_post['forum_post_id'] );
        } else {
          db::set( "forum_post_id", 0 );
        }
      if( !db::update() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/forum/update_giving_forum/title" ),
          lang::phrase( "error/forum/update_giving_forum/body" )
        );
      }
      db::open( TABLE_FORUMS );
        db::where( "forum_id", $post['forum_id'] );
        db::open( TABLE_FORUM_TOPICS );
          db::link( "forum_id" );
          db::open( TABLE_FORUM_POSTS );
            db::link( "forum_topic_id" );
            db::order( "forum_post_date", "DESC" );
          db::close();
        db::close();
        db::limit( 0, 1 );
      $last_post = NULL;
      $last_post = db::result();
      db::clear_result();
      db::open( TABLE_FORUMS );
        db::where( "forum_id", $post['forum_id'] );
        db::set( "forum_topic_count", "forum_topic_count-1", false );
        db::set( "forum_post_count", "forum_post_count-" . $post['forum_topic_post_count'], false );
        if( $last_post ) {
          db::set( "forum_post_id", $last_post['forum_post_id'] );
        } else {
          db::set( "forum_post_id", 0 );
        }
      if( !db::update() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/forum/update_receiving_forum/title" ),
          lang::phrase( "error/forum/update_receiving_forum/body" )
        );
      }
      action::resume( "forum/forum_action" );
        action::add( "action", "move_topic" );
        action::add( "success", 1 );
        action::add( "message", lang::phrase( "forum/move_topic/success" ) );
      action::end();

      $show_message = sys::input( "show_message", false );
      if( $show_message ) {
        action::resume( "request" );
          action::add( "return_page", RELATIVE_DIR . "/forum/topic/" . $forum_topic_id );
          action::add( "return_text", lang::phrase( "forum/view_moved_topic/body" ) );
        action::end();
        sys::message( USER_MESSAGE, lang::phrase( "forum/success" ), lang::phrase( "forum/move_topic/success" ) );
      }
    }

    private static function move_post() {

    }

    private static function toggle_post() {
      $forum_post_id = sys::input( "forum_post_id", 0 );
      db::open( TABLE_FORUM_POSTS );
        db::where( "forum_post_id", $forum_post_id );
        db::open( TABLE_FORUM_TOPICS );
          db::link( "forum_topic_id" );
          db::open( TABLE_FORUMS );
            db::link( "forum_id" );
          db::close();
        db::close();
        db::open( TABLE_USERS, LEFT );
          db::select( "user_name" );
          db::link( "user_id" );
        db::close();
      $post = db::result();
      db::clear_result();
      if( $post['forum_post_enabled'] && !auth::test( "forum", "disable_posts", "target", $post['forum_id'] ) ) {
        auth::deny( "forum", "disable_posts" );
      } else if( !$post['forum_post_enabled'] && !auth::test( "forum", "enable_posts", "target", $post['forum_id'] ) ) {
        auth::deny( "forum", "enable_posts" );
      }
      db::open( TABLE_FORUM_POSTS );
        db::where( "forum_post_id", $forum_post_id );
      if( $post['forum_post_enabled'] ) {
        db::set( "forum_post_enabled", 0 );
      } else {
        db::set( "forum_post_enabled", 1 );
      }
      if( !db::update() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/forum/toggle_post/title" ),
          lang::phrase( "error/forum/toggle_post/body" )
        );
      }

      if( $post['forum_post_enabled'] ) {
        logs::record_log(
          "forum_post",
          "moderator",
          $post['forum_post_id'],
          "disable",
          lang::phrase(
            "forum/forum_post/disable_post/moderator_action",
            action::get( "user/name" ),
            $post['user_name'],
            $post['forum_topic_title']
          )
        );
      } else {
        logs::record_log(
          "forum_post",
          "moderator",
          $post['forum_post_id'],
          "enable",
          lang::phrase(
            "forum/forum_post/enable_post/moderator_action",
            action::get( "user/name" ),
            $post['user_name'],
            $post['forum_topic_title']
          )
        );
      }

      self::update_forum( $post['forum_id'] );
      if( !$post['forum_post_originator'] ) {
        self::update_forum_topic( $post['forum_topic_id'] );
      }

      /**
       * CLEAR CACHE
       */
      cache::clear( "", "forum/list_hot_topics" );
      cache::clear( "", "forum/list_forums" );

      action::resume( "forum/forum_action" );
        action::add( "action", "toggle_post" );
        action::add( "success", 1 );
        action::add( "message", lang::phrase( "forum/toggle_post/success" ) );
      action::end();
      $return_page = sys::input( "return_page", "" );
      if( $return_page ) {
        action::resume( "request" );
          action::add( "return_page", $return_page );
          action::add( "return_text", lang::phrase( "forum/view_modified_post/body" ) );
        action::end();
        sys::message( USER_MESSAGE, lang::phrase( "forum/success" ), lang::phrase( "forum/toggle_post/success" ) );
      }
    }

    private static function initiate_move() {
      $forum_post_ids = sys::input( "forum_post_id", array() );
      if( !is_array( $forum_post_ids ) ) {
        $forum_post_ids = array( $forum_post_ids );
      }
      $forum_post_transfer_reason = sys::input( "forum_post_transfer_reason", "" );
      $current_date = gmdate( "Y/m/d H:i:s", time() );
      $total_posts = count( $forum_post_ids );
      for( $i = 0; $i < $total_posts; $i++ ) {
        db::open( TABLE_FORUM_POST_TRANSFERS );
          db::where( "forum_post_id", $forum_post_ids[$i] );
        $transfer = db::result();
        db::clear_result();
        if( !$transfer ) {
          db::open( TABLE_FORUM_POSTS );
            db::where( "forum_post_id", $forum_post_ids[$i] );
            db::open( TABLE_FORUM_TOPICS );
              db::select( "forum_id" );
              db::link( "forum_topic_id" );
            db::close();
          $post = db::result();
          db::clear_result();
          if( !auth::test( "forum", "move_posts", "target", $post['forum_id'] ) ) {
            auth::deny( "forum", "move_posts" );
          }
          db::open( TABLE_FORUM_POST_TRANSFERS );
            db::set( "forum_post_id", $forum_post_ids[$i] );
            db::set( "forum_topic_id", $post['forum_topic_id'] );
            db::set( "forum_post_transfer_reason", $forum_post_transfer_reason );
            db::set( "forum_post_transfer_date", $current_date );
          if( !db::insert() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/forum/initiate_move/title" ),
              lang::phrase( "error/forum/initiate_move/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
        }
      }

      action::resume( "forum/forum_action" );
        action::add( "action", "initiate_move" );
        action::add( "success", 1 );
        action::add( "message", lang::phrase( "forum/initiate_move/success/body" ) );
      action::end();

      $return_page = sys::input( "return_page", "" );
      if( $return_page ) {
        action::resume( "request" );
          action::add( "return_page", $return_page );
          action::add( "return_text", lang::phrase( "forum/return_to_previous" ) );
        action::end();
        sys::message( USER_MESSAGE, lang::phrase( "forum/initiate_move/success/title" ), lang::phrase( "forum/initiate_move/success/body" ) );
      }
    }

    private static function complete_move() {
      $return_page = sys::input( "return_page", "" );
      if( $return_page ) {
        action::resume( "request" );
          action::add( "return_page", $return_page );
          action::add( "return_text", lang::phrase( "forum/return_to_previous" ) );
        action::end();
      }
      
      $forum_post_ids = sys::input( "forum_post_id", array() );
      if( !is_array( $forum_post_ids ) ) {
        $forum_post_ids = array( $forum_post_ids );
      }
      $destination_type = sys::input( "destination_type", "" );
      $destination_id = sys::input( "destination_id", 0 );
      $total_posts = count( $forum_post_ids );

      for( $i = 0; $i < $total_posts; $i++ ) {
        self::process_complete_move( $forum_post_ids[$i], $destination_type, $destination_id );
      }

      /**
       * CLEAR CACHE
       */
      cache::clear( "", "forum/list_hot_topics" );
      cache::clear( "", "forum/list_forums" );

      action::resume( "forum/forum_action" );
        action::add( "action", "complete_move" );
        action::add( "success", 1 );
        action::add( "message", lang::phrase( "forum/complete_move/success/body" ) );
      action::end();

      if( $return_page ) {
        sys::message( USER_MESSAGE, lang::phrase( "forum/complete_move/success/title" ), lang::phrase( "forum/complete_move/success/body" ) );
      }
    }

    private static function process_complete_move( $forum_post_id, $destination_type, $destination_id ) {
      db::open( TABLE_FORUM_POST_TRANSFERS );
        db::where( "forum_post_id", $forum_post_id );
      $transfer = db::result();
      db::clear_result();
      if( $transfer ) {
        db::open( TABLE_FORUM_POSTS );
          db::where( "forum_post_id", $forum_post_id );
          db::open( TABLE_FORUM_TOPICS );
            db::select( "forum_topic_id", "forum_id", "forum_topic_title", "forum_topic_type", "forum_topic_status", "forum_topic_post_count", "forum_topic_view_count" );
            db::select_as( "last_post_id" );
            db::select( "forum_post_id" );
            db::link( "forum_topic_id" );
            db::open( TABLE_FORUMS );
              db::select( "forum_title" );
              db::link( "forum_id" );
            db::close();
          db::close();
          db::open( TABLE_USERS, LEFT );
            db::select( "user_name" );
            db::link( "user_id" );
          db::close();
        $post = db::result();
        db::clear_result();

        $old_forum_id = $post['forum_id'];
        $old_topic_id = $post['forum_topic_id'];
        $new_forum_id = $old_forum_id;
        $new_topic_id = $old_topic_id;

        if( $destination_type == "topic" ) {
          db::open( TABLE_FORUM_TOPICS );
            db::where( "forum_topic_id", $destination_id );
            db::open( TABLE_FORUM_POSTS );
              db::link( "forum_post_id" );
            db::close();
          $topic = db::result();
          db::clear_result();
          $new_forum_id = $topic['forum_id'];
          $new_topic_id = $topic['forum_topic_id'];
        } else if( $destination_type == "forum" ) {
          db::open( TABLE_FORUMS );
            db::where( "forum_id", $destination_id );
          $forum = db::result();
          db::clear_result();
          $new_forum_id = $forum['forum_id'];
          $new_topic_id = $old_topic_id;
        }

        if( $old_topic_id != $new_topic_id ) {
          if( $post['forum_post_originator'] && !auth::test( "forum", "merge_topics", "target", $new_forum_id ) ) {
            auth::deny( "forum", "merge_topics" );
          } else if( !$post['forum_post_originator'] && !auth::test( "forum", "move_posts", "target", $new_forum_id ) ) {
            auth::deny( "forum", "move_posts" );
          }
          db::open( TABLE_FORUM_POSTS );
            if( $post['forum_post_originator'] ) {
              db::where( "forum_topic_id", $old_topic_id );
              db::set( "forum_post_originator", 0 );
            } else {
              db::where( "forum_post_id", $post['forum_post_id'] );
            }
            db::set( "forum_topic_id", $new_topic_id );
          if( !db::update() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/forum/update_transferred_posts/title" ),
              lang::phrase( "error/forum/update_transferred_posts/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
          self::update_forum_topic( $new_topic_id );
          if( $post['forum_post_originator'] ) {
            db::open( TABLE_FORUM_TOPICS );
              db::where( "forum_topic_id", $old_topic_id );
              db::set( "forum_topic_status", "moved" );
              db::set( "forum_destination_id", $new_topic_id );
            if( !db::update() ) {
              sys::message(
                SYSTEM_ERROR,
                lang::phrase( "error/forum/update_transferred_topic/title" ),
                lang::phrase( "error/forum/update_transferred_topic/body" ),
                __FILE__, __LINE__, __FUNCTION__, __CLASS__
              );
            }
            db::open( TABLE_FORUM_TOPICS );
              db::where( "forum_destination_id", $old_topic_id );
              db::set( "forum_destination_id", $new_topic_id );
            if( !db::update() ) {
              sys::message(
                SYSTEM_ERROR,
                lang::phrase( "error/forum/update_old_moved_topics/title" ),
                lang::phrase( "error/forum/update_old_moved_topics/body" ),
                __FILE__, __LINE__, __FUNCTION__, __CLASS__
              );
            }
          } else {
            self::update_forum_topic( $old_topic_id );
          }
          if( $old_forum_id != $new_forum_id ) {
            self::update_forum( $old_forum_id );
            self::update_forum( $new_forum_id );
          }

          logs::record_log(
            "forum_post",
            "moderator",
            $post['forum_post_id'],
            "move",
            lang::phrase(
              "forum/forum_post/move_post/moderator_action",
              action::get( "user/name" ),
              $post['user_name'],
              $post['forum_topic_title'],
              $topic['forum_topic_title']
            )
          );
        } else if( $old_forum_id != $new_forum_id ) {
          if( !$post['forum_post_originator'] && !auth::test( "forum", "move_posts", "target", $new_forum_id ) ) {
            auth::deny( "forum", "move_posts" );
          }
          db::open( TABLE_FORUM_TOPICS );
            db::set( "forum_post_id", $post['last_post_id'] );
            db::set( "forum_id", $new_forum_id );
            db::set( "forum_topic_title", $post['forum_topic_title'] );
            db::set( "forum_topic_type", $post['forum_topic_type'] );
            db::set( "forum_topic_status", $post['forum_topic_status'] );
            db::set( "forum_topic_post_count", $post['forum_topic_post_count'] );
            db::set( "forum_topic_view_count", $post['forum_topic_view_count'] );
          if( !db::insert() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/forum/create_transferred_topic/title" ),
              lang::phrase( "error/forum/create_transferred_topic/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
          $new_topic_id = db::id();
          $current_date = gmdate( "Y/m/d H:i:s", time() );
          db::open( TABLE_FORUM_TOPICS );
            db::where( "forum_topic_id", $old_topic_id );
            db::set( "forum_destination_id", $new_topic_id );
            db::set( "forum_topic_moved", $current_date );
            db::set( "forum_topic_status", "moved" );
          if( !db::update() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/forum/update_transferred_topic/title" ),
              lang::phrase( "error/forum/update_transferred_topic/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
          db::open( TABLE_FORUM_TOPICS );
            db::where( "forum_destination_id", $old_topic_id );
            db::set( "forum_destination_id", $new_topic_id );
          if( !db::update() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/forum/update_old_moved_topics/title" ),
              lang::phrase( "error/forum/update_old_moved_topics/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
          db::open( TABLE_FORUM_POSTS );
            db::where( "forum_topic_id", $old_topic_id );
            db::set( "forum_topic_id", $new_topic_id );
          if( !db::update() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/forum/update_transferred_posts/title" ),
              lang::phrase( "error/forum/update_transferred_posts/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
          self::update_forum( $old_forum_id );
          self::update_forum( $new_forum_id );

          logs::record_log(
            "forum_post",
            "moderator",
            $post['forum_post_id'],
            "move",
            lang::phrase(
              "forum/forum_topic/move_topic/moderator_action",
              action::get( "user/name" ),
              $post['user_name'],
              $post['forum_topic_title'],
              $post['forum_title'],
              $forum['forum_title']
            )
          );
        }
        
        db::open( TABLE_FORUM_POST_TRANSFERS );
          db::where( "forum_post_transfer_id", $transfer['forum_post_transfer_id'] );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/forum/delete_completed_transfer/title" ),
            lang::phrase( "error/forum/delete_completed_transfer/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      } else {
        sys::message( USER_ERROR, lang::phrase( "error/forum/transer_no_exist/title" ), lang::phrase( "error/forum/transfer_no_exist/body" ) );
      }
    }

    public static function list_forums() {
      $forum = null;
      $forum_name = sys::input( "forum_name", "" );
      $show_invisible = sys::input( "show_invisible", 1 );
      if( $forum_name ) {
        db::open( TABLE_FORUMS );
          db::where( "forum_name", $forum_name );
        $forum = db::result();
        db::clear_result();
      }
      $cache_id = "gfl";
      if( $forum_name ) {
        $cache_id .= "." . $forum_name;
      }
      if( $show_invisible ) {
        $cache_id .= ".showall";
      }
      //if( (int)action::get( "user/logged_in" ) || !CACHE_ENABLED || !$forum_list = cache::get( $cache_id, "forum/list_forums" ) ) {
        action::resume( "forum/forum_list" );
          $current_date = gmdate( "Y/m/d H:i:s", time() - sys::setting( "forum", "topic_expiration" ) );
          db::open( TABLE_FORUMS );
            if( !$show_invisible ) {
              db::where( "forum_visible", 1 );
            }
            db::order( "forum_order", "ASC" );
            db::open( TABLE_FORUM_POSTS, LEFT );
              db::select( "forum_post_id", "forum_post_date" );
              db::link( "forum_post_id" );
              db::open( TABLE_FORUM_TOPICS );
                db::select( "forum_topic_id", "forum_topic_title", "forum_topic_type", "forum_topic_status" );
                db::link( "forum_topic_id" );
                db::where( "forum_topic_status", "moved", "!=" );
              db::close();
              db::open( TABLE_USERS );
                db::select( "user_name", "user_id" );
                db::link( "user_id" );
              db::close();
            db::close();
            if( (int) action::get( "user/logged_in" ) ) {
              db::open( TABLE_FORUM_VIEWS, LEFT );
                db::select( "forum_view_date" );
                db::link( "forum_id" );
                db::where( "user_id", action::get( "user/id" ) );
              db::close();
              db::open( TABLE_FORUM_VIEWS, LEFT );
                db::select_as( "global_view_date" );
                db::select( "forum_view_date" );
                db::where( "forum_id", 0 );
                db::where( "user_id", action::get( "user/id" ) );
              db::close();
            }
          $forum_list = array();
          $forum_ids = array();
          $temp_forum_list = array();
          $total_forums = 0;
          while( $row = db::result() ) {
            if( $forum ) {
              $temp_forum_list[] = $row;
            } else {
              $forum_list[] = $row;
              $total_forums++;
            }
          }
          if( $forum ) {
            for( $i = 0; $i < count( $temp_forum_list ); $i++ ) {
              $temp_forum = $temp_forum_list[$i];
              if( !in_array( $temp_forum['forum_id'], $forum_ids ) ) {
                $add_forum = false;
                if( $temp_forum['forum_parent'] == $forum['forum_id'] ) {
                  $add_forum = true;
                } else if( in_array( $temp_forum['forum_parent'], $forum_ids ) ) {
                  $add_forum = true;
                }
                if( $add_forum ) {
                  $forum_list[] = $temp_forum;
                  $forum_ids[] = $temp_forum['forum_id'];
                  $i = -1;
                  $total_forums++;
                }
              }
            }
          }
          for( $i = 0; $i < $total_forums; $i++ ) {
            if( !auth::test( "forum", "view_forum", "target", $forum_list[$i]['forum_id'] ) ) {
              continue;
            }
            $view_closed = auth::test( "forum", "view_hidden_posts", "target", $forum_list[$i]['forum_id'] );
            action::start( "forum" );
              if( action::get( "user/logged_in" ) ) {
                db::open( TABLE_FORUMS );
                  db::where( "forum_id", $forum_list[$i]['forum_id'] );
                  db::open( TABLE_FORUM_TOPICS, LEFT );
                    db::select_as( "recent_post_count" );
                    db::select_count( "forum_topic_id" );
                    db::link( "forum_id" );
                    db::where( "forum_topic_status", "moved", "!=" );
                    if( !$view_closed ) {
                      db::open( TABLE_FORUM_POSTS );
                        db::select_as( "first_post_id" );
                        db::select( "forum_post_id" );
                        db::link( "forum_topic_id" );
                        db::where( "forum_post_originator", 1 );
                        db::where( "forum_post_enabled", 1 );
                      db::close();
                    }
                    db::open( TABLE_FORUM_POSTS );
                      db::select( "forum_post_originator" );
                      db::link( "forum_post_id" );
                      if( $forum_list[$i]['forum_view_date'] || $forum_list[$i]['global_view_date'] ) {
                        if( $forum_list[$i]['forum_view_date'] ) {
                          db::where( "forum_post_date", $forum_list[$i]['forum_view_date'], ">" );
                        }
                        if( $forum_list[$i]['global_view_date'] ) {
                          db::where( "forum_post_date", $forum_list[$i]['global_view_date'], ">" );
                        }
                      }
                      db::where( "forum_post_date", $current_date, ">" );
                      db::where( "user_id", action::get( "user/id" ), "!=" );
                    db::close();
                    db::open( TABLE_FORUM_TOPIC_VIEWS, LEFT );
                      db::select_as( "recent_view_count" );
                      db::select_count( "forum_topic_view_date" );
                      db::link( "forum_post_id" );
                      if( $forum_list[$i]['forum_view_date'] || $forum_list[$i]['global_view_date'] ) {
                        if( $forum_list[$i]['forum_view_date'] ) {
                          db::where( "forum_topic_view_date", $forum_list[$i]['forum_view_date'], ">" );
                        }
                        if( $forum_list[$i]['global_view_date'] ) {
                          db::where( "forum_topic_view_date", $forum_list[$i]['global_view_date'], ">" );
                        }
                      } else {
                        db::where( "forum_topic_view_date", $current_date, "> " );
                      }
                      db::where( "user_id", action::get( "user/id" ) );
                    db::close();
                  db::close();
                  db::group( "forum_id" );
                  db::limit( 0, 1 );
                $unread_posts = db::result();
                db::clear_result();
                if( (int) action::get( "user/logged_in" ) && $unread_posts['recent_post_count'] > $unread_posts['recent_view_count'] ) {
                  action::add( "unread", 1 );
                  action::add( "recent_posts", $unread_posts['recent_post_count'] );
                  action::add( "recent_views", $unread_posts['recent_view_count'] );
                  action::add( "total_unread", $unread_posts['recent_post_count'] - $unread_posts['recent_view_count'] );
                }
              }
              action::add( "id", $forum_list[$i]['forum_id'] );
              action::add( "order", $forum_list[$i]['forum_order'] );
              action::add( "title", $forum_list[$i]['forum_title'] );
              action::add( "name", $forum_list[$i]['forum_name'] );
              action::add( "description", $forum_list[$i]['forum_description'] );
              action::add( "status", $forum_list[$i]['forum_status'] );
              action::add( "status_title", lang::phrase( "forum/statuses/" . $forum_list[$i]['forum_status'] ) );
              action::add( "topic_count", $forum_list[$i]['forum_topic_count'] );
              action::add( "post_count", $forum_list[$i]['forum_post_count'] );
              action::add( "parent_id", $forum_list[$i]['forum_parent'] );
              action::start( "last_post" );
                action::add( "post_id", $forum_list[$i]['forum_post_id'] );
                action::add( "topic_id", $forum_list[$i]['forum_topic_id'] );
                action::add( "author", $forum_list[$i]['user_name'] );
                action::add( "author_id", $forum_list[$i]['user_id'] );
                action::add( "title", sys::clean_xml( $forum_list[$i]['forum_topic_title'] ? $forum_list[$i]['forum_topic_title'] : 'Untitled' ) );
                $timestamp = strtotime( $forum_list[$i]['forum_post_date'] );
                action::add( "period", sys::create_duration( $timestamp, time() ) );
                $timestamp += ( 60 * 60 ) * sys::timezone();
                action::add( "datetime", $forum_list[$i]['forum_post_date'] );
                action::add( "time", gmdate( "g:i A", $timestamp ) );
                action::add( "long_date", gmdate( "F jS, Y", $timestamp ) );
                action::add( "short_date", gmdate( "n/j/y", $timestamp ) );
              action::end();
            action::end();
          }
        action::end();
        /*if( CACHE_ENABLED && !(int)action::get( "user/logged_in" ) ) {
          $forum_list = action::get( "forum/forum_list" );
          cache::set( $forum_list->asXML(), -1, $cache_id, "forum/list_forums" );
        }*/
      /*} else {
        action::merge( simplexml_load_string( $forum_list ), "forum" );
      }*/
    }

    public static function list_topics() {
      $page = sys::input( "page", 1 );
      $forum_id = sys::input( "forum_id", 0 );
      $forum_name = sys::input( "forum_name", "" );
      if( !$forum_id && !$forum_name ) {
        sys::message( USER_ERROR, lang::phrase( "error/forum/list_topics/invalid_forum/title" ), lang::phrase( "error/forum/list_topics/invalid_forum/body" ) );
      }
      db::open( TABLE_FORUMS );
        if( $forum_name ) {
          db::where( "forum_name", $forum_name );
        } else {
          db::where( "forum_id", $forum_id );
        }
      $forum = db::result();
      db::clear_result();
      if( !$forum_id ) {
        $forum_id = $forum['forum_id'];
      }
      $permissions = auth::test( "forum", "", "target", $forum_id );
      if( !isset( $permissions['view_forum'] ) || !$permissions['view_forum'] ) {
        auth::deny( "forum", "view_forum" );
      }
      $global_permissions = auth::test( "forum", "" );
      foreach( $global_permissions as $key => $val ) {
        if( isset( $permissions[$key] ) && !$val ) {
          $permissions[$key] = 0;
        }
      }
      action::resume( "forum/forum" );
        action::add( "id", $forum['forum_id'] );
        action::add( "title", $forum['forum_title'] );
        action::add( "name", $forum['forum_name'] );
        action::add( "description", $forum['forum_description'] );
        action::add( "topic_count", $forum['forum_topic_count'] );
        action::start( "permissions" );
          foreach( $permissions as $key => $val ) {
            action::add( $key, $val );
          }
          if( (int) action::get( "forum/forum/permissions/edit_posts" ) ||
              (int) action::get( "forum/forum/permissions/move_posts" ) ||
              (int) action::get( "forum/forum/permissions/disable_posts" ) ||
              (int) action::get( "forum/forum/permissions/enable_posts" ) ||
              (int) action::get( "forum/forum/permissions/give_infractions" ) ) {
            action::add( "moderate", 1 );
          } else {
            action::add( "moderate", 0 );
          }
        action::end();
      action::end();
      self::get_forum_tree( $forum_id );
      $total_vars = action::total( "url_variables/var" );
      for( $i = 1; $i < $total_vars; $i++ ) {
        if( action::get( "url_variables/var", $i ) == "page" ) {
          $page = action::get( "url_variables/var", $i+1 );
        }
      }
      if( !$page ) {
        $page = sys::input( "page", 1 );
      }
      if( !$per_page = preferences::get( "forum", "topics_per_page", "account" ) ) {
        $per_page = 15;
      }
      db::open( TABLE_FORUM_TOPICS );
        db::select_as( "total_topics" );
        db::select_count( "forum_topic_id" );
        db::where( "forum_id", $forum_id );
        db::where_or();
        db::where( "forum_topic_type", "announcement" );
        db::where_and();
        if( !auth::test( "forum", "view_hidden_posts", "target", $forum_id ) ) {
          db::open( TABLE_FORUM_POSTS );
            db::select_none();
            db::link( "forum_topic_id" );
            db::where( "forum_post_enabled", 1 );
            db::where( "forum_post_originator", 1 );
          db::close();
        }
      $count = db::result();
      db::clear_result();
      $total_topics = $count['total_topics'];
      if( $page > floor( $total_topics / $per_page ) + 1 ) {
        $page = floor( $total_topics / $per_page ) + 1;
      }

      action::resume( "forum" );
        action::add( "topics_per_page", $per_page );
        action::add( "total_topics", $total_topics );
        action::add( "total_pages", ceil( $total_topics / $per_page ) );
        action::add( "page", $page );
        action::start( "topic_list" );
          db::open( TABLE_FORUM_TOPICS );
            db::where( "forum_id", $forum_id );
            db::where_or();
            db::where( "forum_topic_type", "announcement" );
            db::where_and();
            db::open( TABLE_FORUM_TOPIC_ORDER );
              db::link( "forum_topic_type" );
              db::order( "forum_topic_order", "ASC" );
            db::close();
            if( !auth::test( "forum", "view_hidden_posts", "target", $forum_id ) ) {
              db::open( TABLE_FORUM_POSTS );
                db::select_none();
                db::link( "forum_topic_id" );
                db::where( "forum_post_enabled", 1 );
                db::where( "forum_post_originator", 1 );
              db::close();
            }
            db::open( TABLE_FORUM_POSTS, LEFT );
              db::link( "forum_post_id" );
              db::open( TABLE_USERS );
                db::link( "user_id" );
              db::close();
            db::close();
            db::open( TABLE_FORUM_POSTS, LEFT );
              db::select_as( "forum_topic_enabled" );
              db::select( "forum_post_enabled" );
              db::select_as( "first_post_id" );
              db::select( "forum_post_id" );
              db::select_as( "first_post_date" );
              db::select( "forum_post_date" );
              db::link( "forum_topic_id" );
              db::where( "forum_post_originator", 1 );
              db::open( TABLE_FORUM_POST_TRANSFERS, LEFT );
                db::select( "forum_post_transfer_id" );
                db::link( "forum_post_id" );
              db::close();
              db::open( TABLE_USERS, LEFT );
                db::select_as( "first_post_user_id" );
                db::select( "user_id" );
                db::select_as( "first_post_user_name" );
                db::select( "user_name" );
                db::link( "user_id" );
              db::close();
            db::close();
            if( (int) action::get( "user/logged_in" ) ) {
              db::open( TABLE_FORUM_VIEWS, LEFT );
                db::select( "forum_view_date" );
                db::link( "forum_id" );
                db::where( "user_id", action::get( "user/id" ) );
              db::close();
              db::open( TABLE_FORUM_VIEWS, LEFT );
                db::select_as( "global_view_date" );
                db::select( "forum_view_date" );
                db::where( "forum_id", 0 );
                db::where( "user_id", action::get( "user/id" ) );
              db::close();
              db::open( TABLE_FORUM_TOPIC_VIEWS, LEFT );
                db::select( "forum_topic_view_date" );
                db::link( "forum_topic_id" );
                db::where( "user_id", action::get( "user/id" ) );
              db::close();
              db::open( TABLE_FORUM_POSTS, LEFT );
                db::select_as( "user_post_id" );
                db::select( "forum_post_id" );
                db::link( "forum_topic_id" );
                db::where( "user_id", action::get( "user/id" ) );
              db::close();
            }
            db::order( "forum_post_id", "DESC" );
            db::group( "forum_topic_id" );
            db::limit( $per_page * ( $page - 1 ), $per_page );
          $topic_list = array();
          while( $row = db::result() ) {
            if( $row['forum_topic_enabled'] || $permissions['view_hidden_posts'] ) {
              $topic_list[] = $row;
            }
          }
          $total_topics = count( $topic_list );
          for( $i = 0; $i < $total_topics; $i++ ) {
            action::start( "topic" );
              action::add( "id", $topic_list[$i]['forum_topic_id'] );
              action::add( "title", sys::clean_xml( $topic_list[$i]['forum_topic_title'] ? $topic_list[$i]['forum_topic_title'] : 'Untitled' ) );
              action::add( "type", $topic_list[$i]['forum_topic_type'] );
              action::add( "type_title", lang::phrase( "forum/forum_topic/type/" . $topic_list[$i]['forum_topic_type'] ) );
              action::add( "status", $topic_list[$i]['forum_topic_status'] );
              action::add( "status_title", lang::phrase( "forum/forum_topic/status/" . $topic_list[$i]['forum_topic_status'] ) );
              action::add( "post_count", $topic_list[$i]['forum_topic_post_count'] );
              action::add( "view_count", $topic_list[$i]['forum_topic_view_count'] );
              action::add( "destination", $topic_list[$i]['forum_destination_id'] );
              $timestamp = strtotime( $topic_list[$i]['first_post_date'] );
              if( $topic_list[$i]['first_post_id'] ) {
                if( $topic_list[$i]['forum_post_transfer_id'] ) {
                  action::add( "move_pending", 1 );
                } else {
                  action::add( "move_pending", 0 );
                }
                action::add( "author", $topic_list[$i]['first_post_user_name'] );
                action::add( "author_id", $topic_list[$i]['first_post_user_id'] );
                action::add( "period", sys::create_duration( $timestamp, time() ) );
                $timestamp += ( 60 * 60 ) * sys::timezone();
                action::add( "datetime", sys::create_datetime( $timestamp ) );
                action::add( "time", gmdate( "g:i A", $timestamp ) );
                action::add( "long_date", gmdate( "F jS, Y", $timestamp ) );
                action::add( "short_date", gmdate( "n/j/y", $timestamp ) );
                action::add( "originator", $topic_list[$i]['first_post_id'] );
                action::add( "enabled", $topic_list[$i]['forum_topic_enabled'] );
              }
              if( $topic_list[$i]['forum_post_id'] ) {
                action::start( "last_post" );
                  action::add( "id", $topic_list[$i]['forum_post_id'] );
                  action::add( "author", $topic_list[$i]['user_name'] );
                  action::add( "author_id", $topic_list[$i]['user_id'] );
                  $timestamp = strtotime( $topic_list[$i]['forum_post_date'] );
                  action::add( "period", sys::create_duration( $timestamp, time() ) );
                  $timestamp += ( 60 * 60 ) * sys::timezone();
                  action::add( "datetime", sys::create_datetime( $timestamp ) );
                  action::add( "time", gmdate( "g:i A", $timestamp ) );
                  action::add( "long_date", gmdate( "F jS, Y", $timestamp ) );
                  action::add( "short_date", gmdate( "n/j/y", $timestamp ) );
                action::end();
                if( $topic_list[$i]['user_post_id'] ) {
                  action::add( "contributed", 1 );
                }
                $forum_viewed = 0;
                if( $topic_list[$i]['forum_view_date'] && strtotime( $topic_list[$i]['forum_post_date'] ) < strtotime( $topic_list[$i]['forum_view_date'] ) ) {
                  $forum_viewed = 1;
                }
                if( !$forum_viewed && $topic_list[$i]['global_view_date'] && strtotime( $topic_list[$i]['forum_post_date'] ) < strtotime( $topic_list[$i]['global_view_date'] ) ) {
                  $forum_viewed = 1;
                }
                if( (int) action::get( "user/logged_in" ) &&
                    ( !$topic_list[$i]['forum_topic_view_date'] ||
                        strtotime( $topic_list[$i]['forum_post_date'] ) > strtotime( $topic_list[$i]['forum_topic_view_date'] ) ) &&
                    strtotime( $topic_list[$i]['forum_post_date'] ) > time() - sys::setting( "forum", "topic_expiration" ) &&
                    $topic_list[$i]['user_id'] != action::get( "user/id" ) &&
                    $topic_list[$i]['forum_topic_status'] != "moved" &&
                    !$forum_viewed ) {
                  action::add( "unread", 1 );
                }
              }
            action::end();
          }
         action::end();
      action::end();
    }

    public static function list_posts() {
      $page = sys::input( "page", 1 );
      $forum_topic_id = sys::input( "forum_topic_id", 0 );
      $forum_post_id = sys::input( "forum_post_id", 0 );
      $expired_time = gmdate( "Y/m/d H:i:s", time()-(60*60*24*2) );
      if( $forum_topic_id && $forum_topic_id > 0 ) {
        db::open( TABLE_FORUM_TOPICS );
          db::where( "forum_destination_id", $forum_topic_id );
          db::where( "forum_topic_moved", $expired_time, "<" );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/forum/delete_old_moved_topics/title" ),
            lang::phrase( "error/forum/delete_old_moved_topics/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      }
      db::open( TABLE_FORUM_TOPICS );
        db::where( "forum_topic_id", $forum_topic_id );
        db::open( TABLE_FORUM_POSTS );
          db::select_as( "forum_topic_enabled" );
          db::select( "forum_post_enabled" );
          db::select_as( "forum_topic_author_id" );
          db::select( "user_id" );
          db::link( "forum_topic_id" );
          db::where( "forum_post_originator", 1 );
        db::close();
        db::open( TABLE_FORUMS );
          db::select( "forum_title", "forum_name" );
          db::link( "forum_id" );
        db::close();
        if( (int) action::get( "user/logged_in" ) ) {
          db::open( TABLE_FORUM_TOPIC_VIEWS, LEFT );
            db::select( "forum_topic_view_date" );
            db::link( "forum_post_id" );
            db::where( "forum_topic_id", $forum_topic_id );
            db::where( "user_id", action::get( "user/id" ) );
          db::close();
        }
      $topic = db::result();
      db::clear_result();
      $forum_id = $topic['forum_id'];
      $permissions = auth::test( "forum", "", "target", $topic['forum_id'] );
      if( !isset( $permissions['view_forum'] ) || !$permissions['view_forum'] ) {
        auth::deny( "forum", "view_forum" );
      } else if( !isset( $permissions['view_topics'] ) || !$permissions['view_topics'] ) {
        auth::deny( "forum", "view_topics" );
      } else if( !$topic['forum_topic_enabled'] && ( !isset( $permissions['view_hidden_posts'] ) || !$permissions['view_hidden_posts'] ) ) {
        auth::deny( "forum", "view_hidden_posts" );
      }
      $global_permissions = auth::test( "forum", "" );
      foreach( $global_permissions as $key => $val ) {
        if( isset( $permissions[$key] ) && !$val ) {
          $permissions[$key] = 0;
        }
      }
      action::resume( "forum/topic" );
        action::add( "id", $topic['forum_topic_id'] );
        action::add( "title", sys::clean_xml( $topic['forum_topic_title'] ? $topic['forum_topic_title'] : 'Untitled' ) );
        action::add( "status", $topic['forum_topic_status'] );
        action::add( "type", $topic['forum_topic_type'] );
        db::open( TABLE_FORUM_TOPICS );
          db::where( "forum_topic_id", $topic['forum_topic_id'] );
          db::set( "forum_topic_view_count", "forum_topic_view_count+1", false );
        if( !db::update() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/forum/update_topic_view_count/title" ),
            lang::phrase( "error/forum/update_topic_view_count/body" )
          );
        }
      action::end();
      action::resume( "forum/forum" );
        action::add( "id", $topic['forum_id'] );
        action::add( "title", $topic['forum_title'] );
        action::add( "name", $topic['forum_name'] );
        action::start( "permissions" );
          foreach( $permissions as $key => $val ) {
            action::add( $key, $val );
          }
          if( (int) action::get( "forum/forum/permissions/edit_posts" ) ||
              (int) action::get( "forum/forum/permissions/move_posts" ) ||
              (int) action::get( "forum/forum/permissions/disable_posts" ) ||
              (int) action::get( "forum/forum/permissions/enable_posts" ) ||
              (int) action::get( "forum/forum/permissions/give_infractions" ) ) {
            action::add( "moderate", 1 );
          } else {
            action::add( "moderate", 0 );
          }
        action::end();
      action::end();
      self::get_forum_tree( $topic['forum_id'] );
      if( !$per_page = preferences::get( "forum", "posts_per_page", "account" ) ) {
        $per_page = sys::input( "per_page", 15 );
      }

      $target_post_id = 0;
      if( $forum_post_id ) {
        $view = null;
        if( $forum_post_id == "new" ) {
          db::open( TABLE_FORUM_TOPIC_VIEWS );
            db::where( "user_id", action::get( "user/id" ) );
            db::where( "forum_topic_id", $forum_topic_id );
          $view = db::result();
          db::clear_result();
        }
        db::open( TABLE_FORUM_POSTS );
          db::select( "forum_post_id", "forum_post_date" );
          if( $forum_post_id != "new" ) {
            db::where( "forum_post_id", $forum_post_id );
          } else if( $view ) {
            db::where( "forum_post_date", $view['forum_topic_view_date'], ">" );
            db::order( "forum_post_date", "ASC" );
            db::limit( 0, 1 );
          }
          db::open_subquery(); {
              db::open( TABLE_FORUM_POSTS ); {
                db::select_as( "row_number" );
                db::select_manual( "@row:=@row+1" );
                db::open_subquery(); {
                    db::open( TABLE_FORUM_POSTS );
                      db::select( "forum_post_id" );
                      db::where( "forum_topic_id", $forum_topic_id );
                      db::order( "forum_post_date", "ASC" );
                      db::open_subquery(); {
                          db::open( TABLE_FORUM_POSTS );
                            db::select_manual( "@row:=0" );
                            db::limit( 0, 1 );
                          db::close();
                        db::close_subquery();
                        db::select_none();
                      } db::close();
                    db::close();
                    db::link( "forum_post_id" );
                  db::close_subquery();
                  db::link( "forum_post_id" );
                } db::close();
                db::close_subquery();
                db::link( "forum_post_id" );
              } db::close();
              db::close_subquery();
            db::select( "row_number" );
          } db::close();
        $post = db::result();
        db::clear_result();
        if( $post && ( $post['forum_post_id'] == $forum_post_id || $forum_post_id == "new" ) ) {
          $page = floor( ( $post['row_number'] - 1 ) / $per_page ) + 1;
          $target_post_id = $post['forum_post_id'];
        }
      }

      db::open( TABLE_FORUM_POSTS );
        db::select_as( "total_posts" );
        db::select_count( "forum_post_id" );
        db::where( "forum_topic_id", $forum_topic_id );
        if( !auth::test( "forum", "view_hidden_posts", "target", $forum_id ) ) {
          db::where( "forum_post_enabled", 1 );
        }
        db::group( "forum_topic_id" );
      $count = db::result();
      db::clear_result();
      $total_posts = $count['total_posts'];
      if( $page > floor( $total_posts / $per_page ) + 1 ) {
        $page = floor( $total_posts / $per_page ) + 1;
      }

      action::resume( "forum" );
        action::add( "posts_per_page", $per_page );
        action::add( "total_posts", $total_posts );
        action::add( "total_pages", ceil( $total_posts / $per_page ) );
        action::add( "page", $page );
        action::start( "post_list" );
          db::open( TABLE_FORUM_POSTS );
            db::where( "forum_topic_id", $forum_topic_id );
            if( !auth::test( "forum", "view_hidden_posts", "target", $forum_id ) ) {
              db::where( "forum_post_enabled", 1 );
            }
            db::open( TABLE_USERS, LEFT );
              db::select( "user_name" );
              db::link( "user_id" );
            db::close();
            db::open( TABLE_FORUM_POST_TRANSFERS, LEFT );
              db::select( "forum_post_transfer_id" );
              db::link( "forum_post_id" );
            db::close();
            db::order( "forum_post_date", "ASC" );
            db::limit( $per_page * ( $page - 1 ), $per_page );
          $post_list = array();
          while( $row = db::result() ) {
            $post_list[] = $row;
          }
          $total_posts = count( $post_list );
          $total_profiles = 0;
          $profile_list = array();
          for( $i = 0; $i < $total_posts; $i++ ) {
            if( (int) action::get( "user/logged_in" ) &&
                $post_list[$i]['forum_post_id'] == $topic['forum_post_id'] &&
                !$topic['forum_topic_view_date'] &&
                $post_list[$i]['user_id'] != action::get( 'user/id' ) ) {
              $current_date = gmdate( "Y/m/d H:i:s", time() );
              db::open( TABLE_FORUM_TOPIC_VIEWS );
                db::where( "forum_topic_id", $topic['forum_topic_id'] );
                db::where( "user_id", action::get( "user/id" ) );
              if( !db::delete() ) {
                sys::message(
                  SYSTEM_ERROR,
                  lang::phrase( "error/forum/delete_old_forum_views/title" ),
                  lang::phrase( "error/forum/delete_old_forum_views/body" ),
                  __FILE__, __LINE__, __FUNCTION__, __CLASS__
                );
              }
              db::open( TABLE_FORUM_TOPIC_VIEWS );
                db::set( "forum_post_id", $topic['forum_post_id'] );
                db::set( "forum_topic_id", $topic['forum_topic_id'] );
                db::set( "forum_topic_view_date", $current_date );
                db::set( "user_id", action::get( "user/id" ) );
              if( !db::insert() ) {
                sys::message(
                  SYSTEM_ERROR,
                  lang::phrase( "error/forum/update_forum_view/title" ),
                  lang::phrase( "error/forum/update_forum_view/body" ),
                  __FILE__, __LINE__, __FUNCTION__, __CLASS__
                );
              }
            }
            if( !in_array( $post_list[$i]['user_id'], $profile_list ) ) {
              $profile_list[] = $post_list[$i]['user_id'];
            }
            action::start( "post" );
              if( $post_list[$i]['forum_post_id'] == $target_post_id ) {
                action::add( "newest", 1 );
              } else {
                action::add( "newest", 0 );
              }
              action::add( "id", $post_list[$i]['forum_post_id'] );
              action::start( "author" );
                action::add( "id", $post_list[$i]['user_id'] );
                action::add( "name", $post_list[$i]['user_name'] );
              action::end();
              action::add( "original", $post_list[$i]['forum_post_originator'] );
              if( $post_list[$i]['forum_post_transfer_id'] ) {
                action::add( "move_pending", 1 );
              } else {
                action::add( "move_pending", 0 );
              }
              $post_list[$i]['forum_post_body'] = preg_replace( "/&([^;]*?)(\s+)/", "&amp;$1$2", $post_list[$i]['forum_post_body'] );
              action::add( "enabled", $post_list[$i]['forum_post_enabled'] );
              action::add( "body", $post_list[$i]['forum_post_body'] );
              action::add( "formatted_body", self::parse_post( $post_list[$i]['forum_post_body'], $post_list[$i]['forum_post_formatting_enabled'] ), true );
              $timestamp = strtotime( $post_list[$i]['forum_post_date'] );
              action::add( "period", sys::create_duration( $timestamp, time() ) );
              $timestamp += ( 60 * 60 ) * sys::timezone();
              action::add( "datetime", sys::create_datetime( $timestamp ) );
              action::add( "time", gmdate( "g:i A", $timestamp ) );
              action::add( "long_date", gmdate( "F jS, Y", $timestamp ) );
              action::add( "short_date", gmdate( "n/j/y", $timestamp ) );
              if( $post_list[$i]['forum_post_editor'] > 0 ) {
                action::add( "edited", 1 );
                $timestamp = strtotime( $post_list[$i]['forum_post_edited'] );
                $timestamp += ( 60 * 60 ) * sys::timezone();
                action::add( "edited_datetime", sys::create_datetime( $timestamp ) );
                db::open( TABLE_USERS );
                  db::where( "user_id", $post_list[$i]['forum_post_editor'] );
                $editor = db::result();
                db::clear_result();
                if( $editor ) {
                  action::add( "editor", $editor['user_name'] );
                }
              }
              sys::query( "get_item_info", "forum_post", $post_list[$i]['forum_post_id'] );
            action::end();
          }
         action::end();
      action::end();
    }

    public static function list_hot_topics() {
      $total_hot_topics = sys::input( "total_hot_topics", 0 );
      $hot_topic_range = sys::input( "hot_topic_range", 60 * 60 * 24 * 7 );

      $forum_list = array();
      db::open( TABLE_FORUMS );
        db::order( "forum_id", "ASC" );
      while( $row = db::result() ) {
        if( action::get( "authentication/permissions/forum/target/forum-" . $row['forum_id'] . "/view_topics" ) ) {
          $forum_list[] = (int) $row['forum_id'];
        }
      }
      $cache_id = "ht" . implode( ".", $forum_list );
      if( $forum_list ) {
        if( CACHE_ENABLED || !$hot_topic_list = cache::get( $cache_id, "forum/list_hot_topics" ) ) {
          action::resume( "forum/hot_topic_list" );
            db::open( TABLE_FORUM_TOPICS ); 
              db::select( "forum_topic_title", "forum_post_id", "forum_id" );
              db::open_subquery();
                db::open( TABLE_FORUM_POSTS );
                  db::select_as( "hot_topic_post_count" );
                  db::select_count_all();
                  db::select( "forum_topic_id" );
                  db::where( "forum_post_date", sys::create_datetime( time() - $hot_topic_range ), ">" );
                  db::group( "forum_topic_id" );
                db::close();
              db::close_subquery();
                db::link( "forum_topic_id" );
                db::order( "hot_topic_post_count", "DESC" );
              db::close();
              db::open( TABLE_FORUMS );
                db::select( "forum_name", "forum_title" );
                db::link( "forum_id" );
              db::close();
              db::limit( 0, $total_hot_topics );
            while( $row = db::result() ) {
              action::start( "topic" );
                action::add( "id", $row['forum_topic_id'] );
                action::add( "post", $row['forum_post_id'] );
                action::add( "title", $row['forum_topic_title'] );
                action::add( "recent_posts", $row['hot_topic_post_count'] );
                action::start( "forum" );
                  action::add( "id", $row['forum_id'] );
                  action::add( "name", $row['forum_name'] );
                  action::add( "title", $row['forum_title'] );
                action::end();
              action::end();
            }
          action::end();
          $hot_topic_list = action::xpath( "forum/hot_topic_list" );
          if( CACHE_ENABLED ) {
            cache::set( $hot_topic_list->ownerDocument->saveXML($hot_topic_list), -1, $cache_id, "forum/list_hot_topics" );
          }
        } else {
          action::merge( simplexml_load_string( $hot_topic_list ), "forum" );
        }
      }
    }

    public static function list_reported_posts() {
      if( !auth::test( "forum", "view_reports" ) ) {
        auth::deny( "forum", "view_reports" );
      }
      $forum_list = array();
      db::open( TABLE_FORUMS );
      while( $row = db::result() ) {
        $permissions = auth::test( "forum", "", "target", $row['forum_id'] );
        if( $permissions['edit_posts'] || $permissions['move_posts'] || $permissions['give_infractions'] || $permissions['close_topics'] ) {
          $forum_list[] = (int) $row['forum_id'];
        }
      }
      $page = sys::input( "page", 1 );
      $per_page = sys::input( "per_page", 14 );

      db::open( TABLE_FORUM_REPORTS );
        db::select_as( "total_posts" );
        db::select_count( "forum_report_id" );
        db::open( TABLE_USERS );
          db::link( "user_id" );
        db::close();
        db::open( TABLE_FORUM_POSTS );
          db::link( "forum_post_id" );
          db::open( TABLE_USERS );
            db::link( "user_id" );
          db::close();
          db::open( TABLE_FORUM_TOPICS );
            db::link( "forum_topic_id" );
            db::open( TABLE_FORUMS );
              db::where_in( "forum_id", $forum_list );
              db::link( "forum_id" );
      $count = db::result();
      db::clear_result();
      $total_posts = $count['total_posts'];

      action::resume( "forum" );
        action::add( "posts_per_page", $per_page );
        action::add( "total_posts", $total_posts );
        action::add( "total_pages", ceil( $total_posts / $per_page ) );
        action::add( "page", $page );
        action::start( "post_list" );
          db::open( TABLE_FORUM_REPORTS );
            db::order( "forum_report_additions", "DESC" );
            db::order( "forum_report_date", "ASC" );
            db::limit( ($page-1)*$per_page, $per_page );
            db::open( TABLE_USERS );
              db::select_as( "reporter_id" );
              db::select( "user_id" );
              db::select_as( "reporter_name" );
              db::select( "user_name" );
              db::link( "user_id" );
            db::close();
            db::open( TABLE_FORUM_POSTS );
              db::select( "forum_post_date", "forum_post_body", "forum_post_enabled" );
              db::link( "forum_post_id" );
              db::open( TABLE_USERS );
                db::select_as( "poster_id" );
                db::select( "user_id" );
                db::select_as( "poster_name" );
                db::select( "user_name" );
                db::link( "user_id" );
              db::close();
              db::open( TABLE_FORUM_TOPICS );
                db::select( "forum_topic_id", "forum_topic_title" );
                db::link( "forum_topic_id" );
                db::open( TABLE_FORUMS );
                  db::select( "forum_id", "forum_title", "forum_name" );
                  db::where_in( "forum_id", $forum_list );
                  db::link( "forum_id" );
          while( $row = db::result() ) {
            $permissions = auth::test( "forum", "", "target", $row['forum_id'] );
            $global_permissions = auth::test( "forum", "" );
            foreach( $global_permissions as $key => $val ) {
              if( isset( $permissions[$key] ) && $permissions[$key] && !$val ) {
                $permissions[$key] = 0;
              }
            }
            action::start( "post" );
              action::add( "id", $row['forum_post_id'] );
              action::add( "body", sys::clean_xml( $row['forum_post_body'] ) );
              action::add( "reason", $row['forum_report_reason'] );
              action::add( "enabled", $row['forum_post_enabled'] );
              action::add( "additions", $row['forum_report_additions'] );
              action::start( "topic" );
                action::add( "id", $row['forum_topic_id'] );
                action::add( "title", $row['forum_topic_title'] );
              action::end();
              action::start( "forum" );
                action::add( "id", $row['forum_id'] );
                action::add( "title", $row['forum_title'] );
                action::start( "permissions" );
                  foreach( $permissions as $key => $val ) {
                    action::add( $key, $val );
                  }
                action::end();
              action::end();
              action::start( "author" );
                action::add( "id", $row['poster_id'] );
                action::add( "name", $row['poster_name'] );
              action::end();
              action::start( "reporter" );
                action::add( "id", $row['reporter_id'] );
                action::add( "name", $row['reporter_name'] );
              action::end();
              $timestamp = strtotime( $row['forum_report_date'] );
              action::add( "period", sys::create_duration( $timestamp, time() ) );
              $timeadd = ( 60 * 60 ) * sys::timezone();
              $timestamp += $timeadd;
              action::add( "datetime", sys::create_datetime( $timestamp ) );
              $timestamp = strtotime( $row['forum_post_date'] );
              action::start( "info" );
                action::add( "period", sys::create_duration( $timestamp, time() ) );
                $timestamp += $timeadd;
                action::add( "datetime", sys::create_datetime( $timestamp ) );
              action::end();
            action::end();
          }
         action::end();
      action::end();
    }

    public static function list_transferred_topics() {
      $forum_id = sys::input( "forum_id", 0 );
      $forum_topic_id = sys::input( "forum_topic_id", 0 );
      if( !$forum_id && !$forum_topic_id ) {
        sys::message( USER_ERROR, lang::phrase( "error/forum/list_transferred_topics/invalid_identifiers/title" ), lang::phrase( "error/forum/list_transferred_topics/invalid_identifiers/body" ) );
      }
      $page = sys::input( "page", 1 );
      $per_page = sys::input( "per_page", 20 );

      $topic = null;
      if( $forum_topic_id ) {
        db::open( TABLE_FORUM_TOPICS );
          db::select( "forum_topic_id", "forum_topic_title", "forum_id" );
          db::where( "forum_topic_id", $forum_topic_id );
          db::open( TABLE_FORUM_POSTS );
            db::link( "forum_topic_id" );
            db::where( "forum_post_originator", 1 );
          db::close();
        $topic = db::result();
        db::clear_result();
        $forum_id = $topic['forum_id'];
      }
      if( $forum_id ) {
        db::open( TABLE_FORUMS );
          db::where( "forum_id", $forum_id );
        $forum = db::result();
        db::clear_result();
      }

      $permissions = auth::test( "forum", "", "target", $forum_id );
      if( !isset( $permissions['move_posts'] ) || !$permissions['move_posts'] ) {
        auth::deny( "forum", "move_posts" );
      }
      $global_permissions = auth::test( "forum", "" );
      foreach( $global_permissions as $key => $val ) {
        if( isset( $permissions[$key] ) && !$val ) {
          $permissions[$key] = 0;
        }
      }
      action::resume( "forum/forum" );
        action::add( "id", $forum['forum_id'] );
        action::add( "title", $forum['forum_title'] );
        action::add( "name", $forum['forum_name'] );
        action::add( "description", $forum['forum_description'] );
        action::start( "permissions" );
          foreach( $permissions as $key => $val ) {
            action::add( $key, $val );
          }
        action::end();
      action::end();

      if( $forum_topic_id ) {
        action::resume( "forum/topic" );
          action::add( "id", $topic['forum_topic_id'] );
          action::add( "title", $topic['forum_topic_title'] );
          $timestamp = strtotime( $topic['forum_post_date'] );
          $timestamp += ( 60 * 60 ) * sys::timezone();
          action::add( "datetime", sys::create_datetime( $timestamp ) );
        action::end();
      }

      db::open( TABLE_FORUM_POST_TRANSFERS );
        db::select_as( "topic_count" );
        db::select_count( "forum_post_transfer_id" );
        db::open( TABLE_FORUM_POSTS );
          db::link( "forum_post_id" );
          db::where( "forum_post_originator", 1 );
          if( $topic ) {
            db::where( "forum_post_date", $topic['forum_post_date'], ">" );
            db::where( "forum_topic_id", $forum_topic_id, "!=" );
          }
        db::close();
      $count = db::result();
      db::clear_result();
      $total_topics = $count['topic_count'];

      action::resume( "forum/transfers" );
        action::add( "total_topics", $total_topics );
      action::end();

      action::resume( "forum/topic_list" );
        db::open( TABLE_FORUM_POST_TRANSFERS );
          db::open( TABLE_FORUM_POSTS );
            db::link( "forum_post_id" );
            db::where( "forum_post_originator", 1 );
            if( $topic ) {
              db::where( "forum_post_date", $topic['forum_post_date'], ">" );
              db::where( "forum_topic_id", $forum_topic_id, "!=" );
            }
            db::open( TABLE_USERS, LEFT );
              db::link( "user_id" );
            db::close();
            db::open( TABLE_FORUM_TOPICS );
              db::select( "forum_topic_id", "forum_topic_title", "forum_topic_post_count" );
              db::link( "forum_topic_id" );
              db::open( TABLE_FORUMS );
                db::select( "forum_id", "forum_name", "forum_title" );
                db::link( "forum_id" );
              db::close();
            db::close();
          db::close();
          db::limit( $page-1, $per_page );
        while( $row = db::result() ) {
          action::start( "topic" );
            action::add( "id", $row['forum_topic_id'] );
            action::add( "title", $row['forum_topic_title'] );
            action::start( "author" );
              action::add( "id", $row['user_id'] );
              action::add( "name", $row['user_name'] );
            action::end();
            action::add( "post_count", $row['forum_topic_post_count'] );
            $timestamp = strtotime( $row['forum_post_date'] );
            action::add( "period", sys::create_duration( $timestamp, time() ) );
            $timestamp += ( 60 * 60 ) * sys::timezone();
            action::add( "datetime", sys::create_datetime( $timestamp ) );
            action::add( "time", gmdate( "g:i A", $timestamp ) );
            action::add( "long_date", gmdate( "F jS, Y", $timestamp ) );
            action::add( "short_date", gmdate( "n/j/y", $timestamp ) );
            action::add( "originator", $row['forum_post_id'] );
            action::add( "reason", $row['forum_post_transfer_reason'] );
            action::start( "forum" );
              action::add( "id", $row['forum_id'] );
              action::add( "name", $row['forum_name'] );
              action::add( "title", $row['forum_title'] );
            action::end();
          action::end();
        }
      action::end();
    }

    public static function list_transferred_posts() {
      $forum_id = sys::input( "forum_id", 0 );
      $forum_topic_id = sys::input( "forum_topic_id", 0 );
      if( !$forum_id && !$forum_topic_id ) {
        sys::message( USER_ERROR, lang::phrase( "error/forum/list_transferred_posts/invalid_identifier/title" ), lang::phrase( "error/forum/list_transferred_posts/invalid_identifier/body" ) );
      }
      $page = sys::input( "page", 1 );
      $per_page = sys::input( "per_page", 20 );

      if( $forum_topic_id ) {
        db::open( TABLE_FORUM_TOPICS );
          db::select( "forum_id", "forum_topic_id", "forum_topic_title" );
          db::where( "forum_topic_id", $forum_topic_id );
          db::open( TABLE_FORUM_POSTS );
            db::link( "forum_topic_id" );
            db::where( "forum_post_originator", 1 );
          db::close();
        $topic = db::result();
        db::clear_result();
        if( $topic && isset( $topic['forum_id'] ) ) {
          $forum_id = $topic['forum_id'];
        }
      }
      if( $forum_id ) {
        db::open( TABLE_FORUMS );
          db::where( "forum_id", $forum_id );
        $forum = db::result();
        db::clear_result();
      }

      $permissions = auth::test( "forum", "", "target", $forum_id );
      if( !isset( $permissions['move_posts'] ) || !$permissions['move_posts'] ) {
        auth::deny( "forum", "move_posts" );
      }
      $global_permissions = auth::test( "forum", "" );
      foreach( $global_permissions as $key => $val ) {
        if( isset( $permissions[$key] ) && !$val ) {
          $permissions[$key] = 0;
        }
      }
      action::resume( "forum/forum" );
        action::add( "id", $forum['forum_id'] );
        action::add( "title", $forum['forum_title'] );
        action::add( "name", $forum['forum_name'] );
        action::add( "description", $forum['forum_description'] );
        action::start( "permissions" );
          foreach( $permissions as $key => $val ) {
            action::add( $key, $val );
          }
        action::end();
      action::end();

      if( $forum_topic_id ) {
        action::resume( "forum/topic" );
          action::add( "id", $topic['forum_topic_id'] );
          action::add( "title", $topic['forum_topic_title'] );
          $timestamp = strtotime( $topic['forum_post_date'] );
          $timestamp += ( 60 * 60 ) * sys::timezone();
          action::add( "datetime", sys::create_datetime( $timestamp ) );
        action::end();
      }

      db::open( TABLE_FORUM_POST_TRANSFERS );
        db::select_as( "post_count" );
        db::select_count( "forum_post_transfer_id" );
        db::open( TABLE_FORUM_POSTS );
          db::link( "forum_post_id" );
          db::where( "forum_post_originator", 0 );
          if( $topic ) {
            db::where( "forum_post_date", $topic['forum_post_date'], ">" );
            db::where( "forum_topic_id", $forum_topic_id, "!=" );
          }
        db::close();
      $count = db::result();
      db::clear_result();
      $total_posts = $count['post_count'];

      action::resume( "forum/transfers" );
        action::add( "total_posts", $total_posts );
      action::end();

      action::resume( "forum/post_list" );
        db::open( TABLE_FORUM_POST_TRANSFERS );
          db::open( TABLE_FORUM_POSTS );
            db::link( "forum_post_id" );
            db::where( "forum_post_originator", 0 );
            if( $topic ) {
              db::where( "forum_post_date", $topic['forum_post_date'], ">" );
              db::where( "forum_topic_id", $forum_topic_id, "!=" );
            }
            db::open( TABLE_USERS, LEFT );
              db::link( "user_id" );
            db::close();
            db::open( TABLE_FORUM_TOPICS );
              db::select( "forum_topic_id", "forum_topic_title" );
              db::link( "forum_topic_id" );
              db::open( TABLE_FORUMS );
                db::select( "forum_id", "forum_name", "forum_title" );
                db::link( "forum_id" );
              db::close();
            db::close();
          db::close();
          db::limit( $page-1, $per_page );
        while( $row = db::result() ) {
          action::start( "post" );
            action::add( "id", $row['forum_post_id'] );
            action::add( "body", sys::clean_xml( $row['forum_post_body'] ) );
            action::start( "topic" );
              action::add( "title", $row['forum_topic_title'] );
              action::add( "id", $row['forum_topic_id'] );
            action::end();
            action::start( "author" );
              action::add( "id", $row['user_id'] );
              action::add( "name", $row['user_name'] );
            action::end();
            $timestamp = strtotime( $row['forum_post_date'] );
            action::add( "period", sys::create_duration( $timestamp, time() ) );
            $timestamp += ( 60 * 60 ) * sys::timezone();
            action::add( "datetime", sys::create_datetime( $timestamp ) );
            action::add( "time", gmdate( "g:i A", $timestamp ) );
            action::add( "long_date", gmdate( "F jS, Y", $timestamp ) );
            action::add( "short_date", gmdate( "n/j/y", $timestamp ) );
            action::start( "forum" );
              action::add( "id", $row['forum_id'] );
              action::add( "name", $row['forum_name'] );
              action::add( "title", $row['forum_title'] );
            action::end();
          action::end();
        }
      action::end();
    }

    public static function get_forum() {
      $forum_id = sys::input( "forum_id", 0 );
      if( !$forum_id ) {
        sys::message( USER_ERROR, lang::phrase( "error/forum/get_forum/invalid_forum/title" ), lang::phrase( "error/forum/get_forum/invalid_forum/body" ) );
      }
      $permissions = auth::test( "forum", "", "target", $forum_id );
      if( !isset( $permissions['view_forum'] ) || !$permissions['view_forum'] ) {
        auth::deny( "forum", "view_forum" );
      }
      $global_permissions = auth::test( "forum", "" );
      foreach( $global_permissions as $key => $val ) {
        if( isset( $permissions[$key] ) && !$val ) {
          $permissions[$key] = 0;
        }
      }
      self::get_forum_tree( $forum_id );
      db::open( TABLE_FORUMS );
        db::where( "forum_id", $forum_id );
        db::open( TABLE_FORUM_POSTS, LEFT );
          db::link( "forum_post_id" );
          db::open( TABLE_FORUM_TOPICS, LEFT );
            db::select( "forum_topic_id", "forum_topic_title" );
            db::link( "forum_topic_id" );
          db::close();
          db::open( TABLE_USERS, LEFT );
            db::link( "user_id" );
      $forum = db::result();
      db::clear_result();
      action::resume( "forum/forum" );
        action::add( "id", $forum['forum_id'] );
        action::add( "title", $forum['forum_title'] );
        action::add( "name", $forum['forum_name'] );
        action::start( "permissions" );
          foreach( $permissions as $key => $val ) {
            action::add( $key, $val );
          }
        action::end();
      action::end();

      $forum_post_ids = sys::input( "forum_post_id", array() );
      if( !is_array( $forum_post_ids ) ) {
        $forum_post_ids = array( $forum_post_ids );
      }
      $total_posts = count( $forum_post_ids );
      $form_post_body = "";
      for( $i = 0; $i < $total_posts; $i++ ) {
        db::open( TABLE_FORUM_POSTS );
          db::where( "forum_post_id", $forum_post_ids[$i] );
          db::open( TABLE_USERS, LEFT );
            db::link( "user_id" );
        $post = db::result();
        db::clear_result();
        $forum_post_body .= "<quote author=\"" . $post['user_name'] . "\">" . $post['forum_post_body'] . "</quote>\n\n";
      }
      if( $total_posts > 0 ) {
        action::resume( "forum/post" );
          action::add( "id", "new" );
          action::add( "body", $forum_post_body );
        action::end();
      }
    }

    public static function get_topic() {
      $forum_topic_id = sys::input( "forum_topic_id", 0 );
      if( !$forum_topic_id ) {
        sys::message( USER_ERROR, lang::phrase( "error/forum/get_topic/invalid_topic/title" ), lang::phrase( "error/forum/get_topic/invalid_topic/body" ) );
      }
      db::open( TABLE_FORUM_TOPICS );
        db::where( "forum_topic_id", $forum_topic_id );
        db::open( TABLE_FORUMS );
          db::link( "forum_id" );
      $topic = db::result();
      db::clear_result();
      $permissions = auth::test( "forum", "", "target", $topic['forum_id'] );
      if( !isset( $permissions['view_forum'] ) || !$permissions['view_forum'] ) {
        auth::deny( "forum", "view_forum" );
      }
      $global_permissions = auth::test( "forum", "" );
      foreach( $global_permissions as $key => $val ) {
        if( isset( $permissions[$key] ) && !$val ) {
          $permissions[$key] = 0;
        }
      }
      action::resume( "forum/topic" );
        action::add( "id", $topic['forum_topic_id'] );
        action::add( "title", sys::clean_xml( $topic['forum_topic_title'] ? $topic['forum_topic_title'] : 'Untitled' ) );
        action::add( "status", $topic['forum_topic_status'] );
        action::add( "type", $topic['forum_topic_type'] );
      action::end();
      action::resume( "forum/forum" );
        action::add( "id", $topic['forum_id'] );
        action::add( "title", $topic['forum_title'] );
        action::add( "name", $topic['forum_name'] );
        action::start( "permissions" );
          foreach( $permissions as $key => $val ) {
            action::add( $key, $val );
          }
        action::end();
      action::end();
      self::get_forum_tree( $topic['forum_id'] );
    }

    public static function get_post() {
      $forum_post_id = sys::input( "forum_post_id", 0 );
      if( !$forum_post_id ) {
        sys::message( USER_ERROR, lang::phrase( "error/forum/get_post/invalid_post/title" ), lang::phrase( "error/forum/get_post/invalid_post/body" ) );
      }
      db::open( TABLE_FORUM_POSTS );
        db::where( "forum_post_id", $forum_post_id );
        db::open( TABLE_USERS, LEFT );
          db::link( "user_id" );
        db::close();
        db::open( TABLE_FORUM_POST_TRANSFERS, LEFT );
          db::select( "forum_post_transfer_id" );
          db::link( "forum_post_id" );
        db::close();
        db::open( TABLE_FORUM_TOPICS );
          db::select( "forum_topic_id", "forum_topic_title", "forum_topic_status", "forum_topic_type" );
          db::link( "forum_topic_id" );
          db::open( TABLE_FORUMS );
            db::select( "forum_id", "forum_title", "forum_name" );
            db::link( "forum_id" );
      $post = db::result();
      db::clear_result();
      $permissions = auth::test( "forum", "", "target", $post['forum_id'] );
      if( !isset( $permissions['view_forum'] ) || !$permissions['view_forum'] ) {
        auth::deny( "forum", "view_forum" );
      }
      $global_permissions = auth::test( "forum", "" );
      foreach( $global_permissions as $key => $val ) {
        if( isset( $permissions[$key] ) && !$val ) {
          $permissions[$key] = 0;
        }
      }
      if( !action::get( "profiles/profile_" . $post['user_id'] ) ) {
        action::resume( "profiles/profile_" . $post['user_id'] );
          sys::query( "get_user_information", $post['user_id'] );
        action::end();
      }
      action::resume( "forum/post" );
        action::add( "id", $post['forum_post_id'] );
        action::start( "author" );
          sys::query( "get_user_information", $post['user_id'] );
          action::add( "profile", 0 );
        action::end();
        action::add( "author_id", $post['user_id'] );
        action::add( "author", $post['user_name'] );
        action::add( "original", $post['forum_post_originator'] );
        if( $post['forum_post_transfer_id'] ) {
          action::add( "move_pending", 1 );
        } else {
          action::add( "move_pending", 0 );
        }
        action::add( "body", $post['forum_post_body'] );
        action::add( "formatted_body", self::parse_post( $post['forum_post_body'], $post['forum_post_formatting_enabled'] ), true );
        $timestamp = strtotime( $post['forum_post_date'] );
        action::add( "period", sys::create_duration( $timestamp, time() ) );
        $timestamp += ( 60 * 60 ) * sys::timezone();
        action::add( "datetime", sys::create_datetime( $timestamp ) );
        action::add( "time", gmdate( "g:i A", $timestamp ) );
        action::add( "long_date", gmdate( "F jS, Y", $timestamp ) );
        action::add( "short_date", gmdate( "n/j/y", $timestamp ) );
      action::end();
      action::resume( "forum/topic" );
        action::add( "id", $post['forum_topic_id'] );
        action::add( "title", sys::clean_xml( $post['forum_topic_title'] ? $post['forum_topic_title'] : 'Untitled' ) );
        action::add( "status", $post['forum_topic_status'] );
        action::add( "type", $post['forum_topic_type'] );
      action::end();
      action::resume( "forum/forum" );
        action::add( "id", $post['forum_id'] );
        action::add( "title", $post['forum_title'] );
        action::add( "name", $post['forum_name'] );
        action::start( "permissions" );
          foreach( $permissions as $key => $val ) {
            action::add( $key, $val );
          }
        action::end();
      action::end();
      self::get_forum_tree( $post['forum_id'] );
    }

    private static function get_forum_tree( $id ) {
      db::open( TABLE_FORUMS );
        db::where( "forum_id", $id );
      $forum = db::result();
      db::clear_result();
      $forum_parent = $forum['forum_parent'];
      $forum_tree = array();
      while( $forum_parent > 0 ) {
        db::open( TABLE_FORUMS );
          db::where( "forum_id", $forum_parent );
        $parent = db::result();
        db::clear_result();
        if( $parent ) {
          array_unshift( $forum_tree, $parent );
          $forum_parent = $parent['forum_parent'];
        } else {
          $forum_parent = 0;
        }
      }
      $total_forums = count( $forum_tree );
      action::resume( "forum/forum_tree" );
        for( $i = 0; $i < $total_forums; $i++ ) {
          if( $forum_tree[$i]['forum_status'] == 'open' ) {
            action::start( "forum" );
              action::add( "id", $forum_tree[$i]['forum_id'] );
              action::add( "title", $forum_tree[$i]['forum_title'] );
              action::add( "name", $forum_tree[$i]['forum_name'] );
              action::add( "status", $forum_tree[$i]['forum_status'] );
            action::end();
          }
        }
      action::end();
    }

    public static function get_statistics() {
      action::resume( "forum" );
        action::start( "statistics" );
          db::open( TABLE_FORUM_POSTS );
            db::select_as( "post_count" );
            db::select_count( "forum_post_id" );
          $count = db::result();
          db::clear_result();
          action::add( "post_count", $count['post_count'] );
          db::open( TABLE_FORUM_TOPICS );
            db::select_as( "topic_count" );
            db::select_count( "forum_topic_id" );
          $count = db::result();
          db::clear_result();
          action::add( "topic_count", $count['topic_count'] );
          db::open( TABLE_FORUM_POSTS );
            db::select_as( "user_count" );
            db::select_count_distinct( "user_id" );
          $count = db::result();
          db::clear_result();
          action::add( "user_count", $count['user_count'] );
        action::end();
      action::end();
    }

    private static function is_child_of_forum( $child_id, $parent_id ) {
      db::open( TABLE_FORUMS );
        db::where( "forum_parent", $parent_id );
      db::open( TABLE_FORUMS );
        db::where( "forum_id", $child_id );
      $forum = db::result();
      db::clear_result();
      $forum_parent = $forum['forum_parent'];
      while( $forum_parent > 0 ) {
        db::open( TABLE_FORUMS );
          db::where( "forum_id", $forum_parent );
        $parent = db::result();
        db::clear_result();
        if( $parent['forum_id'] == $parent_id ) {
          return true;
        }
        $forum_parent = $parent['forum_parent'];
      }
      return false;
    }

    public static function parse_post( $text, $format = true ) {
      if( $format ) {
        $text = format::process( EXTENSIONS_DIR . "/forum", "post_formatting", $text );
      } else {
        $text = format::process( EXTENSIONS_DIR . "/forum", "strip_formatting", $text );
      }
      $tempstring = "";
      while( strlen( $tempstring ) < 20 ) {
        $tempstring .= chr( rand( 65, 90 ) );
      }
      $text = str_replace( "&amp;lt;", "<$tempstring", $text );
      $text = str_replace( "&amp;gt;", ">$tempstring", $text );
      $text = preg_replace( "/([^=\"\'])((https?|ftp|gopher|telnet|file|notes|ms-help):((\/\/)|(\\\\))+[^<>\"\s]+)/", "$1<a rel=\"nofollow\" target=\"_blank\" href=\"$2\">$2</a>", ' ' . $text );
      $text = substr( $text, 1 );
      $text = str_replace( "<$tempstring", "&amp;lt;", $text );
      $text = str_replace( ">$tempstring", "&amp;gt;", $text );
      return $text;
    }

  }

?>
