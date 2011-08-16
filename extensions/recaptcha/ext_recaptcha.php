<?php

/*
Copyright © 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/


    class recaptcha {
		
      public static function hook_account_registration() {
        self::check_recaptcha_input();
      }

      public static function hook_contact_form() {
        self::check_recaptcha_input();
      }

      private static function check_recaptcha_input() {
        $user_ip = $_SERVER['REMOTE_ADDR'];
        $recaptcha_challenge = sys::input( "recaptcha_challenge_field", "" );
        $recaptcha_response = sys::input( "recaptcha_response_field", "" );
        $api_key = sys::setting( "recaptcha", "private_key" );

        if( !$recaptcha_challenge || strlen( $recaptcha_challenge ) == 0 || !$recaptcha_response || strlen( $recaptcha_response ) == 0 ) {
          action::add( "extension_failed", 1 );
          action::add( "message", lang::phrase( "error/recaptcha/missing_recaptcha_information" ) );
          action::add( "recaptcha_message", lang::phrase( "error/recaptcha/missing_recaptcha_information" ) );
        }

        $data = array(
          'privatekey' => $api_key,
          'remoteip' => $user_ip,
          'challenge' => $recaptcha_challenge,
          'response' => $recaptcha_response
        );
        $response = self::check_recaptcha_response( "api-verify.recaptcha.net", "/verify", $data );
        $answers = explode( "\n", $response[1] );
        if( trim( $answers[0] != "true" ) ) {
          action::add( "extension_failed", 1 );
          action::add( "message", lang::phrase( "error/recaptcha/incorrect_recaptcha_information" ) );
          action::add( "recaptcha_message", lang::phrase( "error/recaptcha/incorrect_recaptcha_information" ) );
        }
      }

      private static function encode_response( $data ) {
        $req = "";
        foreach ( $data as $key => $value )
                $req .= $key . '=' . urlencode( stripslashes($value) ) . '&';

        // Cut the last '&'
        $req=substr($req,0,strlen($req)-1);
        return $req;
      }

      private static function check_recaptcha_response( $host, $path, $data, $port = 80 ) {
        $req = self::encode_response($data);

        $http_request  = "POST $path HTTP/1.0\r\n";
        $http_request .= "Host: $host\r\n";
        $http_request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
        $http_request .= "Content-Length: " . strlen($req) . "\r\n";
        $http_request .= "User-Agent: reCAPTCHA/PHP\r\n";
        $http_request .= "\r\n";
        $http_request .= $req;

        $response = '';
        if( false == ( $fs = @fsockopen($host, $port, $errno, $errstr, 10) ) ) {
                die ('Could not open socket');
        }

        fwrite($fs, $http_request);

        while ( !feof($fs) )
                $response .= fgets($fs, 1160); // One TCP-IP packet
        fclose($fs);
        $response = explode("\r\n\r\n", $response, 2);

        return $response;
      }
		
    }
?>