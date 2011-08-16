<?php

/*
Copyright © 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/

    class akismet {
		
      private static $version = "1.1";
      private static $server = "rest.akismet.com";
      private static $debug = true;

      private static $ignore = array('HTTP_COOKIE',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_FORWARDED_HOST',
                'HTTP_MAX_FORWARDS',
                'HTTP_X_FORWARDED_SERVER',
                'REDIRECT_STATUS',
                'SERVER_PORT',
                'PATH',
                'DOCUMENT_ROOT',
                'SERVER_ADMIN',
                'QUERY_STRING',
                'PHP_SELF' );

      public static function hook_check_spam( $type, $group ) {
        $vars = self::get_vars( $type, $group );

        $response = self::request( "comment-check", $vars );
        if( $response[1] == 'invalid' ) {
          self::validate_key();
        }

        $spam = ($response[1] == 'true');
        $id = action::get( $group . "/id" );

        if( $response ) {
          db::open( TABLE_SPAM );
            db::set( "spam_type", $type );
            db::set( "spam_target", $id );
            db::set( "spam_signature", "" );
            db::set( "spam_flagged", $spam ? 1 : 0 );
            db::set( "spam_probability", 1 );
          if( !db::insert() ) {
            sys::message( APPLICATION_ERROR, lang::phrase( "error/defensio/title" ), lang::phrase( "error/defensio/check_spam", db::error() ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
          }
          action::start( "check_spam_response" );
            action::add( "spam", $spam ? "true" : "false" );
          action::end();
        } else {
          sys::message( APPLICATION_ERROR, lang::phrase( "error/defensio/title" ), lang::phrase( "error/defensio/check_spam", print_r($success) ), __FILE__, __LINE__, __FUNCTION__, __CLASS__ );
        }
      }

      public static function hook_mark_spam( $type, $id, $group ) {
        $vars = self::get_vars( $type, $group );
        $response = self::request( "submit-spam", $vars );
        if( $response[1] == 'invalid' ) {
          self::validate_key();
        }
      }

      public static function hook_mark_ham( $type, $id, $group ) {
        $vars = self::get_vars( $type, $group );
        $response = self::request( "submit-ham", $vars );
        if( $response[1] == 'invalid' ) {
          self::validate_key();
        }
      }

      private static function validate_api_key() {
        $vars = array();
        $response = self::request( "verify-key", $vars );
        if( $response[1] == "invalid" ) {
          sys::message(
            APPLICATION_ERROR,
            lang::phrase( "akismet/validate_api_key/failed/title" ),
            lang::phrase( "akismet/validate_api_key/failed/body" )
          );
        }
      }

      private static function get_vars( $type, $group ) {
        if( !$type ) {
          sys::message( USER_ERROR, lang::phrase( "error/defensio/title" ), lang::phrase( "error/defensio/no_type" ) );
        }
        if( !$group ) {
          sys::message( USER_ERROR, lang::phrase( "error/defensio/title" ), lang::phrase( "error/defensio/no_group" ) );
        }
        $id = action::get( $group . "/id" );
        $body = action::get( $group . "/body" );
        $date = (int) action::get( $group . "/date" );
        $date -= 60 * 60 * sys::setting( "global", "default_timezone" );
        $date = gmdate( "Y", $date ) . "/" . gmdate( "m", $date ) . "/" . gmdate( "d", $date );
        $author = action::get( $group . "/author" );
        $author_email = action::get( $group . "/author_email" );
        $author_ip = action::get( $group . "/author_ip" );
        $author_agent = action::get( $group . "/author_agent" );
        $author_referrer = action::get( $group . "/author_referrer" );
        $permalink = action::get( $group . "/permalink" );
        if( !$id ) {
          sys::message( USER_ERROR, lang::phrase( "error/defensio/title" ), lang::phrase( "error/defensio/no_id" ) );
        }
        if( !$date ) {
          sys::message( USER_ERROR, lang::phrase( "error/defensio/title" ), lang::phrase( "error/defensio/no_date" ) );
        }
        if( !$author ) {
          sys::message( USER_ERROR, lang::phrase( "error/defensio/title" ), lang::phrase( "error/defensio/no_author" ) );
        }

        $comment_type = $type;
        if( preg_match( "/comment/", $type ) ) {
          $comment_type = "comment";
        } else if( preg_match( "/pingback/", $type ) ) {
          $comment_type = "pingback";
        } else if( preg_match( "/trackback/", $type ) ) {
          $comment_type = "trackback";
        } else {
          $comment_type = "other";
        }
        $vars = array(
          "blog" => sys::setting( "akismet", "site_url" ),
          "comment_author" => $author,
          "comment_type" => $comment_type
        );
        if( $permalink ) {
          $vars['permalink'] = $permalink;
        }
        if( $author_email ) {
          $vars['comment_author_email'] = $author_email;
        }
        if( $author_ip ) {
          $vars['user_ip'] = $author_ip;
        }
        if( $author_agent ) {
          $vars['user_agent'] = $author_agent;
        }
        if( $author_referrer ) {
          $vars['referrer'] = $author_referrer;
        }
        if( $body ) {
          $vars['comment_content'] = $body;
        }
        return $vars;
      }

      private static function request( $action, $vars ) {
        if( !in_array( $action, array( 'verify-key', 'comment-check', 'submit-spam', 'submit-ham' ) ) ) {
          sys::message( APPLICATION_ERROR, lang::phrase( "error/akismet/title" ), lang::phrase( "error/akismet/invalid_action", $action ) );
        }

        $formatted_vars = "";
        $current_var = 0;
        foreach( $vars as $key => $val ) {
          if( $current_var > 0 ) {
            $formatted_vars .= "&";
          }
          $formatted_vars .= $key . "=" . urlencode( $val );
          $current_var++;
        }
        $url = "http://" . sys::setting( "akismet", "api_key" ) . ".rest.akismet.com/1.1/" . $action;
        $parsed_url = parse_url( $url );

        $socket = @fsockopen( $parsed_url['host'], 80, $error_code, $error_body, 10 );
        if( !$socket ) {
          sys::message( APPLICATION_ERROR, lang::phrase( "error/akismet/title" ), lang::phrase( "error/akismet/socket_error" ) );
        }

        fwrite( $socket, "POST " . $parsed_url['path'] . " HTTP/1.1\r\n" );
        //fwrite( $socket, "User-Agent: " . sys::setting( "akismet", "site_name" ) . "Defensio Connector (" . self::$version . "; " . sys::setting( "defensio", "domain" ) . "; " . sys::setting( "defensio", "email" ) . "; " . ( self::$debug ? 'debug' : 'release' ) . ")\r\n" );
        fwrite( $socket, "Content-Type: application/x-www-form-urlencoded; charset=UTF8\r\n" );
        fwrite( $socket, "Host: " . $parsed_url['host'] . "\r\n" );
        fwrite( $socket, "Connection: close \r\n" );
        fwrite( $socket, "Content-length: " . strlen( $formatted_vars ) . "\r\n\r\n" );
        fwrite( $socket, $formatted_vars );

        $response = "";
        while( !feof( $socket ) ) {
          $response .= fgets( $socket, 1160 );
        }
        fclose( $socket );
        return explode("\r\n\r\n", $response, 2);
      }
		
    }
?>