<?php

/*
Copyright © 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/


    class emailer {

      public static function hook_account_registration_successful( $user_id ) {
        self::register_newsletters( $user_id );
      }

      public static function hook_account_complete_successful( $user_id ) {
        self::register_newsletters( $user_id );
      }

      private static function register_newsletters( $user_id ) {
        $user_site_contact = sys::input( "user_site_contact", 0 );
        $user_partner_contact = sys::input( "user_partner_contact", 0 );
        $current_date = gmdate( "Y/m/d H:i:s", time() );

        if( $user_site_contact ) {
          db::open( TABLE_NEWSLETTER_REGISTRATIONS );
            db::where( "newsletter_id", 1 );
            db::where( "user_id", $user_id );
          $user = db::result();
          if( !$user ) {
            db::open( TABLE_NEWSLETTER_REGISTRATIONS );
              db::set( "newsletter_id", 1 );
              db::set( "user_id", $user_id );
              db::set( "newsletter_registration_date", $current_date );
            if( !db::insert() ) {
              sys::message(
                SYSTEM_ERROR,
                lang::phrase( "error/emailer/register/title" ),
                lang::phrase( "error/emailer/register/body" ),
                __FILE__, __LINE__, __FUNCTION__, __CLASS__
              );
            }
          }
        }
        if( $user_partner_contact ) {
          db::open( TABLE_NEWSLETTER_REGISTRATIONS );
            db::where( "newsletter_id", 2 );
            db::where( "user_id", $user_id );
          $user = db::result();
          if( !$user ) {
            db::open( TABLE_NEWSLETTER_REGISTRATIONS );
              db::set( "newsletter_id", 2 );
              db::set( "user_id", $user_id );
              db::set( "newsletter_registration_date", $current_date );
            if( !db::insert() ) {
              sys::message(
                SYSTEM_ERROR,
                lang::phrase( "error/emailer/register/title" ),
                lang::phrase( "error/emailer/register/body" ),
                __FILE__, __LINE__, __FUNCTION__, __CLASS__
              );
            }
          }
        }
      }

      public static function hook_account_initialized() {
        $emailer_action = sys::input( "emailer_action", false, SKIP_GET );
        $actions = array(
          "contact_site"
        );
        if( in_array( $emailer_action, $actions ) ) {
          $evaluate = "self::$emailer_action();";
          eval( $evaluate );
        }
      }

      private static function contact_site() {
        $contact_name = sys::input( "contact_name", "" );
        $contact_email = sys::input( "contact_email", "" );
        $contact_subject = sys::input( "contact_subject", "" );
        $contact_body = sys::input( "contact_body", "" );
        $success = true;
        action::resume( "emailer/emailer_action" );
          action::add( "action", "contact_site" );
          if( !$contact_name ) {
            action::add( "message", lang::phrase( "error/emailer/contact_site/missing_name" ) );
            $success = false;
          } else if( !$contact_email ) {
            action::add( "message", lang::phrase( "error/emailer/contact_site/missing_email" ) );
            $success = false;
          } else if( !$contact_subject ) {
            action::add( "message", lang::phrase( "error/emailer/contact_site/missing_subject" ) );
            $success = false;
          } else if( !$contact_body ) {
            action::add( "message", lang::phrase( "error/emailer/contact_site/missing_body" ) );
            $success = false;
          } else if( !preg_match( "/([\w\d!#\$%&'\*\+\-\/=?\^_`{\|}~].+)@([\w\d_\-].+)\.([\w\d_\-\.].+)/s", $contact_email ) ) {
            action::add( "message", lang::phrase( "error/emailer/contact_site/invalid_email" ) );
            $success = false;
          }
          action::add( "name", $contact_name );
          action::add( "email", $contact_email );
          action::add( "subject", $contact_subject );
          action::add( "body", $contact_body );
          action::add( "type", $contact_subject );
          if( !$success ) {
            action::add( "success", 0 );
          } else {
            action::add( "message", lang::phrase( "emailer/contact_site/success/body" ) );
            action::add( "success", 1 );
            $email_address = "";
            switch( $contact_subject ) {
              case "website":
                $email_address = "support@darthhater.com";
                break;
              case "editorial":
                $email_address = "editorial@darthhater.com";
                break;
              case "community":
                $email_address = "community@darthhater.com";
                break;
              case "advertising":
                $email_address = "advertising@darthhater.com";
                break;
              case "media":
                $email_address = "press@darthhater.com";
                break;
              case "privacy":
                $email_address = "privacy@darthhater.com";
                break;
            }
            email::send( "contact_site", "html", $email_address, $contact_email, DEFAULT_LANG, lang::phrase( "emailer/contact_site/" . $contact_subject ) );
          }
        action::end();
        if( $success ) {
          $return_page = sys::input( "return_page", "" );
          if( $return_page ) {
            action::resume( "request" );
              action::add( "return_text", lang::phrase( "emailer/contact_site/success/return" ) );
              action::add( "return_page", $return_page );
            action::end();
            sys::message( USER_MESSAGE, lang::phrase( "emailer/contact_site/success/title" ), lang::phrase( "emailer/contact_site/success/body" ) );
          }
        }
      }
		
    }
?>