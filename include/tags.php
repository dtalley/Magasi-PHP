<?php

/*
Copyright © 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/

	class tags {

    public static function hook_account_initialized() {
      $tags_action = sys::input( "tags_action", false, SKIP_GET );
      $actions = array(
        "add_tags",
        "delete_tags",
        "delete_tag_data",
        "edit_tag"
      );
      if( in_array( $tags_action, $actions ) ) {
        $evaluate = "self::$tags_action();";
        eval( $evaluate );
      }
    }

		public static function add_tags() {
      sys::check_return_page();
      $tag_type = sys::input( "tag_type", "" );
      $tag_target = sys::input( "tag_target", 0 );
      $total_tags = sys::input( "total_tags", 0 );
      if( !$tag_type || !$tag_target ) {
        sys::message( 
          USER_ERROR,
          lang::phrase( "error/tags/actions/add_tags/missing_type_or_target/title" ),
          lang::phrase( "error/tags/actions/add_tags/missing_type_or_target/body" )
        );
      }
      for( $i = 0; $i < $total_tags; $i++ ) {
        $tag_title = sys::input( "tag_title_" . ( $i + 1 ), "" );
        $tag_name = sys::input( "tag_name_" . ( $i + 1 ), "" );
        if( $tag_title ) {
          if( !$tag_name ) {
            $tag_name = sys::create_tag( $tag_title );
          }
          db::open( TABLE_TAG_DATA );
            db::where( "tag_title", $tag_title );
            db::where( "tag_name", $tag_name );
          $tag_data = db::result();
          db::clear_result();
          if( $tag_data ) {
            $tag_id = $tag_data['tag_id'];
          } else {
            db::open( TABLE_TAG_DATA );
              db::set( "tag_title", $tag_title );
              db::set( "tag_name", $tag_name );
            if( !db::insert() ) {
              sys::message(
                SYSTEM_ERROR,
                lang::phrase( "error/tags/actions/add_tags/could_not_add_data/title" ),
                lang::phrase( "error/tags/actions/add_tags/could_not_add_data/body" ),
                __FILE__, __LINE__, __FUNCTION__, __CLASS__
              );
            }
            $tag_id = db::id();
          }
          db::open( TABLE_TAGS );
            db::where( "tag_id", $tag_id );
            db::where( "tag_type", $tag_type );
            db::where( "tag_target", $tag_target );
          $tag = db::result();
          db::clear_result();
          if( !$tag ) {
            db::open( TABLE_TAGS );
              db::set( "tag_id", $tag_id );
              db::set( "tag_type", $tag_type );
              db::set( "tag_target", $tag_target );
            if( !db::insert() ) {
              sys::message(
                SYSTEM_ERROR,
                lang::phrase( "error/tags/actions/add_tags/could_not_add_tag/title" ),
                lang::phrase( "error/tags/actions/add_tags/could_not_add_tag/body" ),
                __FILE__, __LINE__, __FUNCTION__, __CLASS__
              );
            }
          }
        }
      }
      action::resume( "tags/actions" );
        action::start( "action" );
          action::add( "title", lang::phrase( "tags/actions/add_tags/title" ) );
          action::add( "name", "add_tags" );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "tags/actions/add_tags/success/body" ) );
        action::end();
      action::end();
      if( action::get( "request/return_page" ) ) {
        sys::message(
          USER_MESSAGE,
          lang::phrase( "tags/actions/add_tags/success/title" ),
          lang::phrase( "tags/actions/add_tags/success/body" )
        );
      }
    }

    public static function edit_tag() {
      sys::check_return_page();
      $tag_id = sys::input( "tag_id", 0 );
      $tag_title = sys::input( "tag_title", "" );
      $tag_name = sys::input( "tag_name", "" );
      if( !$tag_name ) {
        $tag_name = sys::create_tag( $tag_title );
      }
      db::open( TABLE_TAG_DATA );
        db::set( "tag_title", $tag_title );
        db::set( "tag_name", $tag_name );
        db::where( "tag_id", $tag_id );
      if( !db::update() ) {
        sys::message(
          SYSTEM_ERROR,
          lang::phrase( "error/tags/actions/edit_tag/could_not_update/title" ),
          lang::phrase( "error/tags/actions/edit_tag/could_not_update/body" ),
          __FILE__, __LINE__, __FUNCTION__, __CLASS__
        );
      }
      action::resume( "tags/actions" );
        action::start( "action" );
          action::add( "title", lang::phrase( "tags/actions/edit_tag/title" ) );
          action::add( "name", "edit_tag" );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "tags/actions/edit_tag/success/body" ) );
        action::end();
      action::end();
      if( action::get( "request/return_page" ) ) {
        sys::message(
          USER_MESSAGE,
          lang::phrase( "tags/actions/edit_tag/success/title" ),
          lang::phrase( "tags/actions/edit_tag/success/body" )
        );
      }
    }

    public static function delete_tags() {
      sys::check_return_page();
      $total_tags = sys::input( "total_tags", 0 );
      $tag_type = sys::input( "tag_type", "" );
      $tag_target = sys::input( "tag_target", "" );
      if( !$tag_type || !$tag_target ) {
        sys::message(
          USER_ERROR,
          lang::phrase( "error/tags/actions/delete_tags/missing_type_or_target/title" ),
          lang::phrase( "error/tags/actions/delete_tags/missing_type_or_target/body" )
        );
      }
      for( $i = 0; $i < $total_tags; $i++ ) {
        $tag_id = sys::input( "tag_id_" . ( $i + 1 ) );
        db::open( TABLE_TAGS );
          db::where( "tag_type", $tag_type );
          db::where( "tag_target", $tag_target );
          db::where( "tag_id", $tag_id );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/tags/actions/delete_tags/could_not_delete/title" ),
            lang::phrase( "error/tags/actions/delete_tags/could_not_delete/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      }
      action::resume( "tags/actions" );
        action::start( "action" );
          action::add( "title", lang::phrase( "tags/actions/delete_tags/title" ) );
          action::add( "name", "delete_tags" );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "tags/actions/delete_tags/success/body" ) );
        action::end();
      action::end();
      if( action::get( "request/return_page" ) ) {
        sys::message(
          USER_MESSAGE,
          lang::phrase( "tags/actions/delete_tags/success/title" ),
          lang::phrase( "tags/actions/delete_tags/success/body" )
        );
      }
    }

    public static function delete_tag_data() {
      sys::check_return_page();
      $total_tags = sys::input( "total_tags", 0 );
      for( $i = 0; $i < $total_tags; $i++ ) {
        $tag_id = sys::input( "tag_id_" . ( $i + 1 ) );
        db::open( TABLE_TAGS );
          db::where( "tag_id", $tag_id );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/tags/actions/delete_tags/could_not_delete_tags/title" ),
            lang::phrase( "error/tags/actions/delete_tags/could_not_delete_tags/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
        db::open( TABLE_TAG_DATA );
          db::where( "tag_id", $tag_id );
        if( !db::delete() ) {
          sys::message(
            SYSTEM_ERROR,
            lang::phrase( "error/tags/actions/delete_tags/could_not_delete_tag_data/title" ),
            lang::phrase( "error/tags/actions/delete_tags/could_not_delete_tag_data/body" ),
            __FILE__, __LINE__, __FUNCTION__, __CLASS__
          );
        }
      }
      action::resume( "tags/actions" );
        action::start( "action" );
          action::add( "title", lang::phrase( "tags/actions/delete_tag_data/title" ) );
          action::add( "name", "delete_tag_data" );
          action::add( "success", 1 );
          action::add( "message", lang::phrase( "tags/actions/delete_tag_data/success/body" ) );
        action::end();
      action::end();
      if( action::get( "request/return_page" ) ) {
        sys::message(
          USER_MESSAGE,
          lang::phrase( "tags/actions/delete_tag_data/success/title" ),
          lang::phrase( "tags/actions/delete_tag_data/success/body" )
        );
      }
    }
		
	}

?>