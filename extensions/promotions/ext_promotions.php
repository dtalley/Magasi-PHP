<?php

/*
Copyright © 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/

  class promotions {

    public static function hook_account_initialized() {
      $promotions_action = sys::input( "promotions_action", false, SKIP_GET );
      $actions = array(
        "enter_promotion"
      );
      if( in_array( $promotions_action, $actions ) ) {
        $evaluate = "self::$promotions_action();";
        eval( $evaluate );
      }
    }

    private static function enter_promotion() {
      $promotion_id = sys::input( "promotion_id", 0 );
      db::open( TABLE_PROMOTIONS );
        db::where( "promotion_id", $promotion_id );
      $promotion = db::result();
      db::clear_result();
      if( !$promotion ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/promotions/invalid_promotion_id/title" ),
          lang::phrase( "error/promotions/invalid_promotion_id/body", $promotion_id )
        );
      }
      $start_time = strtotime( $promotion['promotion_start'] );
      $end_time = strtotime( $promotion['promotion_end'] );
      $current_time = time();
      if( $start_time > $current_time ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/promotions/enter_promotion/promotion_not_started/title" ),
          lang::phrase( "error/promotions/enter_promotion/promotion_not_started/body" )
        );
      } else if( $end_time < $current_time ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/promotions/enter_promotion/promotion_ended/title" ),
          lang::phrase( "error/promotions/enter_promotion/promotion_ended/body" )
        );
      }
      $promotion_entry_field1 = sys::input( "promotion_entry_field1", "" );
      $promotion_entry_field2 = sys::input( "promotion_entry_field2", "" );
      $promotion_entry_field3 = sys::input( "promotion_entry_field3", "" );
      $promotion_entry_field4 = sys::input( "promotion_entry_field4", "" );
      $promotion_entry_field5 = sys::input( "promotion_entry_field5", "" );
      $promotion_entry_field6 = sys::input( "promotion_entry_field6", "" );
      $promotion_entry_field7 = sys::input( "promotion_entry_field7", "" );
      $promotion_entry_field8 = sys::input( "promotion_entry_field8", "" );
      $promotion_entry_field9 = sys::input( "promotion_entry_field9", "" );
      $promotion_entry_field1_required = sys::input( "promotion_entry_field1_required", false );
      $promotion_entry_field2_required = sys::input( "promotion_entry_field2_required", false );
      $promotion_entry_field3_required = sys::input( "promotion_entry_field3_required", false );
      $promotion_entry_field4_required = sys::input( "promotion_entry_field4_required", false );
      $promotion_entry_field5_required = sys::input( "promotion_entry_field5_required", false );
      $promotion_entry_field6_required = sys::input( "promotion_entry_field6_required", false );
      $promotion_entry_field7_required = sys::input( "promotion_entry_field7_required", false );
      $promotion_entry_field8_required = sys::input( "promotion_entry_field8_required", false );
      $promotion_entry_field9_required = sys::input( "promotion_entry_field9_required", false );
      $promotion_entry_field1_nodupes = sys::input( "promotion_entry_field1_nodupes", false );
      $promotion_entry_field2_nodupes = sys::input( "promotion_entry_field2_nodupes", false );
      $promotion_entry_field3_nodupes = sys::input( "promotion_entry_field3_nodupes", false );
      $promotion_entry_field4_nodupes = sys::input( "promotion_entry_field4_nodupes", false );
      $promotion_entry_field5_nodupes = sys::input( "promotion_entry_field5_nodupes", false );
      $promotion_entry_field6_nodupes = sys::input( "promotion_entry_field6_nodupes", false );
      $promotion_entry_field7_nodupes = sys::input( "promotion_entry_field7_nodupes", false );
      $promotion_entry_field8_nodupes = sys::input( "promotion_entry_field8_nodupes", false );
      $promotion_entry_field9_nodupes = sys::input( "promotion_entry_field9_nodupes", false );

      if( $field1missing = ( !$promotion_entry_field1 && $promotion_entry_field1_required ) ||
           $field2missing = ( !$promotion_entry_field2 && $promotion_entry_field2_required ) ||
           $field3missing = ( !$promotion_entry_field3 && $promotion_entry_field3_required ) ||
           $field4missing = ( !$promotion_entry_field4 && $promotion_entry_field4_required ) ||
           $field5missing = ( !$promotion_entry_field5 && $promotion_entry_field5_required ) ||
           $field6missing = ( !$promotion_entry_field6 && $promotion_entry_field6_required ) ||
           $field7missing = ( !$promotion_entry_field7 && $promotion_entry_field7_required ) ||
           $field8missing = ( !$promotion_entry_field8 && $promotion_entry_field8_required ) ||
           $field9missing = ( !$promotion_entry_field9 && $promotion_entry_field9_required )
      ) {
        action::resume( "promotions/promotions_action" );
          action::add( "action", "enter_promotion" );
          action::add( "success", 0 );
          action::add( "message", lang::phrase( "promotions/enter_promotion/incomplete_form" ) );
          action::add( "field1", $promotion_entry_field1 );
          action::add( "field2", $promotion_entry_field2 );
          action::add( "field3", $promotion_entry_field3 );
          action::add( "field4", $promotion_entry_field4 );
          action::add( "field5", $promotion_entry_field5 );
          action::add( "field6", $promotion_entry_field6 );
          action::add( "field7", $promotion_entry_field7 );
          action::add( "field8", $promotion_entry_field8 );
          action::add( "field9", $promotion_entry_field9 );
        action::end();
        return;
      }

      $check_existing = false;
      db::open( TABLE_PROMOTION_ENTRIES );
        db::where( "promotion_id", $promotion_id );
        if( $promotion_entry_field1_nodupes ) {
          db::where( "promotion_entry_field1", $promotion_entry_field1 );
          $check_existing = true;
        }
        if( $promotion_entry_field2_nodupes ) {
          db::where( "promotion_entry_field2", $promotion_entry_field2 );
          $check_existing = true;
        }
        if( $promotion_entry_field3_nodupes ) {
          db::where( "promotion_entry_field3", $promotion_entry_field3 );
          $check_existing = true;
        }
        if( $promotion_entry_field4_nodupes ) {
          db::where( "promotion_entry_field4", $promotion_entry_field4 );
          $check_existing = true;
        }
        if( $promotion_entry_field5_nodupes ) {
          db::where( "promotion_entry_field5", $promotion_entry_field5 );
          $check_existing = true;
        }
        if( $promotion_entry_field6_nodupes ) {
          db::where( "promotion_entry_field6", $promotion_entry_field6 );
          $check_existing = true;
        }
        if( $promotion_entry_field7_nodupes ) {
          db::where( "promotion_entry_field7", $promotion_entry_field7 );
          $check_existing = true;
        }
        if( $promotion_entry_field8_nodupes ) {
          db::where( "promotion_entry_field8", $promotion_entry_field8 );
          $check_existing = true;
        }
        if( $promotion_entry_field9_nodupes ) {
          db::where( "promotion_entry_field9", $promotion_entry_field9 );
          $check_existing = true;
        }
      $existing = db::result();
      db::clear_result();
      if( $existing && $check_existing ) {
        action::resume( "promotions/promotions_action" );
          action::add( "action", "enter_promotion" );
          action::add( "success", 0 );
          action::add( "message", lang::phrase( "promotions/enter_promotion/duplicate_entry" ) );
          action::add( "field1", $promotion_entry_field1 );
          action::add( "field2", $promotion_entry_field2 );
          action::add( "field3", $promotion_entry_field3 );
          action::add( "field4", $promotion_entry_field4 );
          action::add( "field5", $promotion_entry_field5 );
          action::add( "field6", $promotion_entry_field6 );
          action::add( "field7", $promotion_entry_field7 );
          action::add( "field8", $promotion_entry_field8 );
          action::add( "field9", $promotion_entry_field9 );
        action::end();
        return;
      }

      db::open( TABLE_PROMOTION_ENTRIES );
        db::set( "promotion_id", $promotion_id );
        db::set( "promotion_entry_date", sys::create_datetime( time() ) );
        db::set( "promotion_entry_field1", $promotion_entry_field1 );
        db::set( "promotion_entry_field2", $promotion_entry_field2 );
        db::set( "promotion_entry_field3", $promotion_entry_field3 );
        db::set( "promotion_entry_field4", $promotion_entry_field4 );
        db::set( "promotion_entry_field5", $promotion_entry_field5 );
        db::set( "promotion_entry_field6", $promotion_entry_field6 );
        db::set( "promotion_entry_field7", $promotion_entry_field7 );
        db::set( "promotion_entry_field8", $promotion_entry_field8 );
        db::set( "promotion_entry_field9", $promotion_entry_field9 );
      if( !db::insert() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/promotions/enter_promotion/unable_to_add_entry/title" ),
          lang::phrase( "error/promotions/enter_promotion/unable_to_add_entry/body" )
        );
      }

      db::open( TABLE_PROMOTIONS );
        db::where( "promotion_id", $promotion_id );
        db::set( "promotion_entries", "promotion_entries+1", false );
      if( !db::update() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/promotions/enter_promotion/unable_to_update_promotion/title" ),
          lang::phrase( "error/promotions/enter_promotion/unable_to_update_promotion/body" )
        );
      }

      action::resume( "request" );
        action::add( "return_text", lang::phrase( "promotions/enter_promotion/return_to_main" ) );
        action::add( "return_page", "http://" . action::get( "settings/site_domain" ) . action::get( "settings/script_path" ) );
      action::end();
      sys::message(
        USER_MESSAGE,
        lang::phrase( "promotions/enter_promotion/entry_added/title" ),
        lang::phrase( "promotions/enter_promotion/entry_added/body" )
      );
    }

  }

?>
