<?php

/*
Copyright © 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/


    class polls {
		
      public static function hook_account_initialized() {
        $polls_action = sys::input( "polls_action", false, SKIP_GET );
        $actions = array(
          "edit_poll",
          "edit_poll_option",
          "delete_poll_option",
          "add_vote"
        );
        if( in_array( $polls_action, $actions ) ) {
          $evaluate = "self::$polls_action();";
          eval( $evaluate );
        }
      }

      private static function edit_poll() {
        sys::check_return_page();
        $poll_id = sys::input( "poll_id", 0 );
        $poll_title = sys::input( "poll_title", "" );
        $poll_description = sys::input( "poll_description", "" );
        $auto_poll_name = str_replace( " ", "-", strtolower( $poll_title ) );
        $auto_poll_name = preg_replace( "/[^a-zA-Z0-9\-]/", "", $auto_poll_name );
        $poll_name = sys::input( "poll_name", false ) ? sys::input( "poll_name", "" ) : $auto_poll_name;
        $poll_name = preg_replace( "/[^a-zA-Z0-9\-_'\"%]/", "", $poll_name );
        $poll_name = preg_replace( "/(-+?)/", "-", $poll_name );
        $current_date = gmdate( "Y/m/d H:i:s", time() );
        $poll_date = sys::input( "poll_date", $current_date );
        $poll_open = sys::input( "poll_open", 1 );
        
        db::open( TABLE_POLLS );
          if( $poll_id ) {
            db::where( "poll_id", $poll_id );
          }
          db::set( "poll_title", $poll_title );
          db::set( "poll_name", $poll_name );
          db::set( "poll_description", $poll_description );
          db::set( "poll_date", $poll_date );
          db::set( "poll_open", $poll_open );

        $poll_created = false;
        if( $poll_id ) {
          if( !db::update() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/poll/edit_poll/title" ),
              lang::phrase( "error/poll/edit_poll/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
        } else {
          if( !db::insert() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/poll/add_poll/title" ),
              lang::phrase( "error/poll/add_poll/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
          $poll_id = db::id();
          $poll_created = true;
        }

        action::resume( "polls/actions" );
          action::start( "action" );
            action::add( "name", "edit_poll" );
            action::add( "title", lang::phrase( "polls/actions/edit_poll/title" ) );
            action::add( "success", 1 );
            action::add( "message", lang::phrase( "polls/actions/edit_poll/success/body" ) );
          action::end();
        action::end();

        if( action::get( "request/return_page" ) ) {
          if( $poll_created ) {
            $return_page = action::get( "request/return_page" );
            $return_page = str_replace( "[poll_id]", $poll_id, $return_page );
            sys::replace_return_page( $return_page );
          }
          sys::message(
            USER_MESSAGE,
            lang::phrase( "polls/actions/edit_poll/success/title" ),
            lang::phrase( "polls/actions/edit_poll/success/body" )
          );
        }
      }

      private static function edit_poll_option() {
        sys::check_return_page();
        $poll_option_id = sys::input( "poll_option_id", 0 );
        $poll_id = sys::input( "poll_id", 0 );
        $poll_option_title = sys::input( "poll_option_title", "" );
        db::open( TABLE_POLL_OPTIONS );
          if( $poll_option_id ) {
            db::where( "poll_option_id", $poll_option_id );
          } else {
            db::set( "poll_id", $poll_id );
          }
          db::set( "poll_option_title", $poll_option_title );
        $message = "";
        if( $poll_option_id ) {
          if( !db::update() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/poll/edit_poll_option/title" ),
              lang::phrase( "error/poll/edit_poll_option/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
        } else {
          if( !db::insert() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/poll/add_poll_option/title" ),
              lang::phrase( "error/poll/add_poll_option/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
        }

        action::resume( "polls/actions" );
          action::start( "action" );
            action::add( "name", "edit_poll_option" );
            action::add( "title", lang::phrase( "polls/actions/edit_poll_option/title" ) );
            action::add( "success", 1 );
            action::add( "message", lang::phrase( "polls/actions/edit_poll_option/success/body" ) );
          action::end();
        action::end();
      }

      private static function delete_poll_option() {
        sys::check_return_page();
        $poll_id = sys::input( "poll_id", 0 );
        $poll_option_id = sys::input( "poll_option_id", 0 );
        db::open( TABLE_POLL_OPTIONS );
          db::where( "poll_id", $poll_id );
          db::where( "poll_option_id", $poll_option_id );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/poll/delete_poll_option/title" ),
            lang::phrase( "error/poll/delete_poll_option/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        db::open( TABLE_POLL_VOTES );
          db::where( "poll_option_id", $poll_option_id );
          db::where( "poll_option_id", $poll_option_id );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/poll/delete_poll_option/title" ),
            lang::phrase( "error/poll/delete_poll_option/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }

        action::resume( "polls/actions" );
          action::start( "action" );
            action::add( "name", "delete_poll_option" );
            action::add( "title", lang::phrase( "polls/actions/delete_poll_option/title" ) );
            action::add( "success", 1 );
            action::add( "message", lang::phrase( "polls/actions/delete_poll_option/success/body" ) );
          action::end();
        action::end();
      }

      public static function add_vote() {
        $poll_id = sys::input( "poll_id", 0 );
        $poll_option_id = sys::input( "poll_option_id", 0 );
        $user_id = action::get( "user/id" );
        $user_ip = $_SERVER['REMOTE_ADDR'];
        if( !auth::test( "polls", "vote_in_polls" ) ) {
          auth::deny( "polls", "vote_in_polls" );
        }
        $success = true;
        $message = "";
        if( !$poll_id ) {
          $message = lang::phrase( "error/polls/add_vote/no_poll_id" );
          $success = false;
        } else if( !$poll_option_id ) {
          $message = lang::phrase( "error/polls/add_vote/no_poll_option_id" );
          $success = false;
        }
        db::open( TABLE_POLLS );
          db::where( "poll_id", $poll_id );
        $poll = db::result();
        db::clear_result();
        if( !$poll['poll_open'] ) {
          $message = lang::phrase( "error/polls/actions/add_vote/poll_closed/body" );
          $success = false;
        }
        if( $success ) {
          db::open( TABLE_POLL_VOTES );
            db::where( "user_ip", $user_ip );
            db::where( "poll_id", $poll_id );
          $vote = db::result();
          db::clear_result();
          if( $vote ) {
            $message = lang::phrase( "error/polls/add_vote/already_voted" );
            $success = false;
          }
        }
        if( $success ) {
          db::open( TABLE_POLL_VOTES );
            db::set( "poll_id", $poll_id );
            db::set( "poll_option_id", $poll_option_id );
            db::set( "user_id", $user_id );
            db::set( "user_ip", $user_ip );
          if( !db::insert() ) {
            sys::message(
              SYSTEM_ERROR,
              lang::phrase( "error/poll/add_vote/title" ),
              lang::phrase( "error/poll/add_vote/body" ),
              __FILE__, __LINE__, __FUNCTION__, __CLASS__
            );
          }
        }
        action::resume( "polls/polls_action" );
          action::add( "action", "add_vote" );
          if( $success ) {
            $message = lang::phrase( "polls/add_vote/success/body" );
            $title = lang::phrase( "polls/add_vote/success/title" );
            action::add( "success", 1 );
            action::add( "message", $message );
          } else {
            $title = lang::phrase( "polls/add_vote/failure/title" );
            action::add( "success", 0 );
            action::add( "message", $message );
          }
        action::end();
        action::resume( "polls/poll" );
          action::start( "option_list" );
            db::open( TABLE_POLL_OPTIONS );
              db::where( "poll_id", $poll_id );
              db::open( TABLE_POLL_VOTES, LEFT );
                db::link( "poll_option_id" );
                db::select_as( "vote_count" );
                db::select_count( "poll_vote_id" );
              db::close();
              db::group( "poll_option_id" );
            $total_votes = 0;
            while( $row = db::result() ) {
              action::start( "option" );
                action::add( "id", $row['poll_option_id'] );
                action::add( "title", $row['poll_option_title'] );
                action::add( "votes", $row['vote_count'] );
              action::end();
              $total_votes += $row['vote_count'];
            }
          action::end();
          action::add( "total_votes", $total_votes );
        action::end();
        $return_page = sys::input( "return_page", 0 );
        if( $return_page ) {
          action::resume( "request" );
            action::add( "return_text", lang::phrase( "polls/add_vote/success/return" ) );
            action::add( "return_page", $return_page );
          action::end();
          sys::message( USER_MESSAGE, $title, $message );
        }
      }

      public static function list_polls() {
        $page = sys::input( "page", 1 );
        $per_page = sys::input( "per_page", 12 );
        db::open( TABLE_POLLS );
          db::select_as( "total_polls" );
          db::select_count( "poll_id" );
        $count = db::result();
        db::clear_result();
        $total_polls = $count['total_polls'];

        action::resume( "polls" );
          action::add( "page", $page );
          action::add( "per_page", $per_page );
          action::add( "total_pages", ceil( $total_polls / $per_page ) );
          action::add( "total_polls", $total_polls );
          action::start( "poll_list" );
            db::open( TABLE_POLLS );
              db::limit( ($page-1)*$per_page, $per_page );
              db::order( "poll_date", "DESC" );
            while( $row = db::result() ) {
              action::start( "poll" );
                $total_votes = 0;
                $total_options = 0;
                db::open( TABLE_POLL_OPTIONS );
                  db::where( "poll_id", $row['poll_id'] );
                  db::open( TABLE_POLL_VOTES, LEFT );
                    db::link( "poll_option_id" );
                    db::select_as( "vote_count" );
                    db::select_count( "poll_vote_id" );
                  db::close();
                  db::group( "poll_option_id" );
                while( $row2 = db::result() ) {
                  $total_votes += $row2['vote_count'];
                  $total_options++;
                }
                action::add( "id", $row['poll_id'] );
                action::add( "title", $row['poll_title'] );
                action::add( "name", $row['poll_name'] );
                action::add( "description", $row['poll_description'] );
                $timestamp = strtotime( $row['poll_date'] );
                $timestamp += ( 60 * 60 ) * sys::timezone();
                action::add( "datetime", $row['poll_date'] );
                action::add( "total_votes", $total_votes );
                action::add( "total_options", $total_options );
              action::end();
            }
          action::end();
        action::end();
      }

      public static function get_poll() {
        $poll_id = sys::input( "poll_id", 0 );
        self::output_poll( $poll_id );
      }

      private static function output_poll( $poll_id ) {
        if( $poll_id != "new" ) {
          db::open( TABLE_POLLS );
            db::where( "poll_id", $poll_id );
          $poll = db::result();
          db::clear_result();
        }
        if( $poll || $poll_id == "new" ) {
          action::resume( "polls/poll" );
            if( $poll_id == "new" ) {
              action::add( "title", "Untitled" );
              action::add( "description", "Type a description here..." );
              action::add( "datetime", gmdate( "Y-m-d H:i:s", time() ) );
              action::add( "new", 1 );
            } else {
              action::add( "id", $poll['poll_id'] );
              action::add( "title", $poll['poll_title'] );
              action::add( "name", $poll['poll_name'] );
              action::add( "description", $poll['poll_description'] );
              action::add( "datetime", $poll['poll_date'] );
              action::add( "open", $poll['poll_open'] );
              db::open( TABLE_POLL_VOTES );
                db::where( "poll_id", $poll_id );
                db::where( "user_ip", $_SERVER['REMOTE_ADDR'] );
              $vote = db::result();
              db::clear_result();
              action::add( "voted", ( $vote ? 1 : 0 ) );
              action::start( "option_list" );
                db::open( TABLE_POLL_OPTIONS );
                  db::where( "poll_id", $poll_id );
                    db::open( TABLE_POLL_VOTES, LEFT );
                    db::link( "poll_option_id" );
                    db::select_as( "vote_count" );
                    db::select_count( "poll_vote_id" );
                  db::close();
                  db::group( "poll_option_id" );
                $total_votes = 0;
                while( $row = db::result() ) {
                  action::start( "option" );
                    action::add( "id", $row['poll_option_id'] );
                    action::add( "title", $row['poll_option_title'] );
                    action::add( "selected", ( $vote && $vote['poll_option_id'] == $row['poll_option_id'] ? 1 : 0 ) );
                    action::add( "votes", $row['vote_count'] );
                  action::end();
                  $total_votes += $row['vote_count'];
                }
              action::end();
              action::add( "total_votes", $total_votes );
            }
          action::end();
        } else {
          sys::message( USER_ERROR, lang::phrase( "error/polls/get_poll/poll_not_found/title" ), lang::phrase( "error/polls/get_poll/poll_not_found/body" ) );
        }
      }
		
    }
?>