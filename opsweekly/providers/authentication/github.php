<?php
class GithubAuthentication {

  function __construct(){
  }

  // Check tha the user is logged in
  function checkLogin(){
   if($this->session('access_token')) {
     $user = $this->apiRequest($GLOBALS['apiURLBase'] . 'user');
     if (empty($user) || $user->name == ""){
       header('Location: ' . $GLOBALS['protocol'] . $GLOBALS['hostname'] . '?action=login');
       exit();
     } else {
       return true;
     }
   }
   header('Location: ' . $GLOBALS['protocol'] . $GLOBALS['hostname'] . '?action=login');
   exit;
  }

  // Make an api request to Github for authentication
  function apiRequest($url, $post=FALSE, $headers=array()) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    if($post)
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));

    $headers[] = 'Accept: application/json';
    if($this->session('access_token'))
      $headers[] = 'Authorization: Bearer ' . $this->session('access_token');
      $headers[] = 'User-Agent: Awesome-Octocat-App';

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    return json_decode($response);
  }

  // Method to retrieve a key in an array
  function get($key, $default=NULL) {
    return array_key_exists($key, $_GET) ? $_GET[$key] : $default;
  }

  // Methid to initialize and update the session
  function session($key, $default=NULL) {
    return array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
  }

  // Check for parameters
  function checkAction(){
    // Start the login process by sending the user to Github's authorization page
    if($this->get('action') == 'login') {
      // Generate a random hash and store in the session for security
      $_SESSION['state'] = hash('sha256', microtime(TRUE).rand().$_SERVER['REMOTE_ADDR']);
      unset($_SESSION['access_token']);
      $params = array(
        'client_id' => $GLOBALS['oauth_client_id'],
        'redirect_uri' => $GLOBALS['protocol'] . $GLOBALS['hostname'] . $_SERVER['PHP_SELF'],
        'scope' => 'user',
        'state' => $_SESSION['state']
      );
      // Redirect the user to Github's authorization page
      header('Location: ' . $GLOBALS['authorizeURL'] . '?' . http_build_query($params));
      die();
    }
    // When Github redirects the user back here, there will be a "code" and "state" parameter in the query string
    if($this->get('code')) {
      // Verify the state matches our stored state
      if(!$this->get('state') || $_SESSION['state'] != $this->get('state')) {
        header('Location: ' . $_SERVER['PHP_SELF']);
        die();
      }
      // Exchange the auth code for a token
      $token = $this->apiRequest($GLOBALS['tokenURL'], array(
        'client_id' => $GLOBALS['oauth_client_id'],
        'client_secret' => $GLOBALS['oauth_client_secret'],
        'redirect_uri' => $GLOBALS['protocol'] . $GLOBALS['hostname'] . $_SERVER['PHP_SELF'],
        'state' => $_SESSION['state'],
        'code' => $this->get('code')
      ));
      $_SESSION['access_token'] = $token->access_token;
      header('Location: ' . $_SERVER['PHP_SELF']);
    }
  }
};

$authenticator = new GithubAuthentication();
