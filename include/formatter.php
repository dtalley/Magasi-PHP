<?php

/*
Copyright © 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/


	class format {
		
		public static function process( $dir, $file, $block, $breaks = true ) {
      libxml_use_internal_errors( true );
      $_xsl = xsl::load_xsl( $file, $dir );
			$_xml = "<formatting>";
      $tempstring = "";
      while( strlen( $tempstring ) < 20 ) {
        $tempstring .= chr( rand( 65, 90 ) );
      }
      if( $breaks ) {
        $block = preg_replace( "/[\n]/", "$tempstring", $block );
        $block = preg_replace( "/[\n\r]/", "", $block );
      }
      $_xml .= $block;
			$_xml .= "</formatting>";
      $_xml = preg_replace( "/&(#?([^\s;]*)\s)/", "&amp;$1 ", $_xml );
      //$_xml = str_replace( "<", "&lt;", $_xml );
      /*$_xml = str_replace( " &", " &amp;", $_xml );
      $_xml = str_replace( " & ", " &amp; ", $_xml );
      $_xml = str_replace( "& ", "&amp; ", $_xml );*/
      $_xmldoc = DOMDocument::loadXML( $_xml );
			$return = xsl::apply( $_xmldoc, $_xsl );
      if( $breaks ) {
        $return = str_replace( "$tempstring", "<br />", $return );
        $return = preg_replace( "/<\/div>(\s*?)<br \/>/", "</div>", $return );
        $return = preg_replace( "/<\/div>(\s*?)<br>/", "</div>", $return );
        $return = preg_replace( "/<\/br>/", "", $return );
        $return = preg_replace( "/<br>/", "<br \/>", $return );
        $return = preg_replace( "/<br \/>(\s*?)<\/div>/", "</div>", $return );
      }
      libxml_use_internal_errors( false );
			return $return;
		}
		
	}
	
?>