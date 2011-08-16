<?php

/*
Copyright © 2011 David Talley

Magasi-PHP (This PHP framework) is distributed under the terms of the GNU General Public License
*/

  class googlemaps {

    public static function return_location( $country, $address ) {      
      $posturl = "http://maps.google.com/maps/geo?";
      $posturl .= "key=" . sys::setting( "googlemaps", "api_key" );
      $posturl .= "&sensor=false";
      $posturl .= "&output=xml";
      $posturl .= "&gl=" . $country;
      $posturl .= "&q=" . urlencode( $address );
      $posturl = str_replace( "\n", "", $posturl );
      $posturl = str_replace( "\r", "", $posturl );
      $posturl = str_replace( "\t", "", $posturl );
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $posturl);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type: application/x-www-form-urlencoded"));
      curl_setopt($ch, CURLOPT_HEADER, 1);
      $response = curl_exec($ch);
      $len = strlen($response);
      $bodypos = strpos($response, "\r\n\r\n");
      if ($bodypos <= 0) {
        $bodypos = strpos($response, "\n\n");
      }
      while ($bodypos < $len && $response[$bodypos] != '<') {
        $bodypos++;
      }
      $body = substr($response, $bodypos);
      if( !$rxml = @simplexml_load_string( $body, "SimpleXMLElement", LIBXML_NOWARNING ) ) {
        echo $body;
        exit();
      }
      if( (int) $rxml->Response->Placemark->AddressDetails["Accuracy"] < 4 ) {
        return false;
      } else {
        $country = $rxml->Response->Placemark->AddressDetails->Country->CountryName;
        $city = $rxml->Response->Placemark->AddressDetails->Country->AdministrativeArea->SubAdministrativeArea->Locality->LocalityName;
        $state = $rxml->Response->Placemark->AddressDetails->Country->AdministrativeArea->AdministrativeAreaName;
        if( (int) $rxml->Response->Placemark->AddressDetails["Accuracy"] > 4 ) {
          $postal = $rxml->Response->Placemark->AddressDetails->Country->AdministrativeArea->SubAdministrativeArea->Locality->PostalCode->PostalCodeNumber;
        }
        $info = array(
          "country" => $country,
          "city" => $city,
          "state" => $state,
          "postal" => $postal
        );
        return $info;
      }      
    }

  }

?>
