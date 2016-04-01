<?php

/*  IdP Options
 *
 *  This function reads the IdP configuration file used by SimpleSAMLPHP and parses the configured IdPs.
 *  It then presents the user with an interface to modify the IdP details.
 *
 */

 function _get_idp_details($settings)
 {
     $config_path = constant('SAMLAUTH_CONF') . '/config/saml20-idp-remote.ini';
     $idp_details = null;
     $idp_settings = $settings->get_idp_details();
     if($idp_settings) {
         $idp_details = parse_ini_string($idp_settings, true);
     }
     elseif(file_exists($config_path))
     {
         $idp_details = parse_ini_file($config_path, true);
     }
     else
     {
         $idp_details = parse_ini_string("[https://your-idp.net]\nname = Your IdP\nSingleSignOnService = https://your-idp.net/SSOService\nSingleLogoutService = https://your-idp.net/SingleLogoutService\ncertFingerprint = 0000000000000000000000000000000000000000", true);
     }

     return $idp_details;
 }

if ( isset($_POST['fetch_metadata']) && wp_verify_nonce($_POST['_wpnonce'],'sso_idp_metadata'))
{
  $m = wp_remote_get($_POST['metadata_url'],array('sslverify' => false));

  if( array_key_exists('body',$m) )
  {
    $x = xml_parser_create();

    $d = array();
    $i = array();

    if( xml_parse_into_struct($x,$m['body'],$d,$i) )
    {
      preg_match('@^(?:https?://)?([^/]+)@i',$_POST['metadata_url'],$idp_name);

      $idp_data = array('idp_name' => $idp_name[1]);

      if(array_key_exists('ENTITYDESCRIPTOR',$i))
      {
        $idp_data['idp_identifier'] = $d[$i['ENTITYDESCRIPTOR'][0]]['attributes']['ENTITYID'];
      }
      elseif( array_key_exists('MD:ENTITYDESCRIPTOR',$i) )
      {
        $idp_data['idp_identifier'] = $d[$i['MD:ENTITYDESCRIPTOR'][0]]['attributes']['ENTITYID'];
      }
      else
      {
        $idp_data['idp_identifier'] = '';
      }

      if( array_key_exists('SINGLESIGNONSERVICE',$i) )
      {
        $idp_data['idp_signon'] = $d[$i['SINGLESIGNONSERVICE'][0]]['attributes']['LOCATION'];
      }
      elseif( array_key_exists('MD:SINGLESIGNONSERVICE',$i) )
      {
        $idp_data['idp_signon'] = $d[$i['MD:SINGLESIGNONSERVICE'][0]]['attributes']['LOCATION'];
      }
      else
      {
        $idp_data['idp_signon'] = '';
      }

      if( array_key_exists('SINGLELOGOUTSERVICE',$i) )
      {
        $idp_data['idp_logout'] = $d[$i['SINGLELOGOUTSERVICE'][0]]['attributes']['LOCATION'];
      }
      elseif( array_key_exists('MD:SINGLELOGOUTSERVICE',$i) )
      {
        $idp_data['idp_logout'] = $d[$i['MD:SINGLELOGOUTSERVICE'][0]]['attributes']['LOCATION'];
      }
      else
      {
        $idp_data['idp_logout'] = '';
      }

      if ( array_key_exists('DS:X509CERTIFICATE',$i) )
      {
        $idp_data['idp_fingerprint'] = sha1( base64_decode( $d[$i['DS:X509CERTIFICATE'][0]]['value'] ) );
      }
      elseif ( array_key_exists('X509CERTIFICATE',$i) )
      {
        $idp_data['idp_fingerprint'] = sha1( base64_decode( $d[$i['X509CERTIFICATE'][0]]['value'] ) );
      }
      else
      {
        $idp_data['idp_fingerprint'] = '0000000000000000000000000000000000000000';
      }

      $contents =  '[' . $idp_data['idp_identifier'] . ']'."\n";
      $contents .= '  name = "' . $idp_data['idp_name'] . '"'."\n";
      $contents .= '  SingleSignOnService = "' . $idp_data['idp_signon'] . '"'."\n";
      $contents .= '  SingleLogoutService = "' . $idp_data['idp_logout'] . '"'."\n";
      $contents .= '  certFingerprint = "' . str_replace(':','',$idp_data['idp_fingerprint']) . '"'."\n";

      $this->settings->enable_cache();
      $this->settings->set_idp_details($contents);
      $this->settings->set_idp($idp_data['idp_identifier']);
      $this->settings->disable_cache();
    }
  }
}
elseif (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'],'sso_idp_manual') )
{
    $old_ini = _get_idp_details($this->settings);

    foreach($old_ini as $key => $val)
    {
        if($key != $_POST['idp_identifier'])
        {
          $this->settings->set_idp($_POST['idp_identifier']);
        }
    }

    $contents =  '[' . $_POST['idp_identifier'] . ']'."\n";
    $contents .= '  name = "' . $_POST['idp_name'] . '"'."\n";
    $contents .= '  SingleSignOnService = "' . $_POST['idp_signon'] . '"'."\n";
    $contents .= '  SingleLogoutService = "' . $_POST['idp_logout'] . '"'."\n";
    $contents .= '  certFingerprint = "' . str_replace(':','',$_POST['idp_fingerprint']) . '"'."\n";

    $this->settings->enable_cache();
    $this->settings->set_idp_details($contents);
    $this->settings->disable_cache();
}

$status = $this->get_saml_status();
$metadata = array(); // the variable used in the idp file.
$ini = _get_idp_details($this->settings);

foreach($ini as $key => $array)
{
    $metadata[$key] = array(
            'name' => array(
                    'en' => $array['name']
            ),
            'SingleSignOnService'  => $array['SingleSignOnService'],
            'certFingerprint'      => $array['certFingerprint']
    );

    if( trim($array['SingleLogoutService']) != '' )
    {
      $metadata[$key]['SingleLogoutService'] = $array['SingleLogoutService'];
    }
}

  include(constant('SAMLAUTH_ROOT') . '/lib/views/nav_tabs.php');
  include(constant('SAMLAUTH_ROOT') . '/lib/views/sso_idp.php');
?>
