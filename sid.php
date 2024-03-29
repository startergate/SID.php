<?php
  // SID LIBRARY
  // ------------------------------------------------------
  // Copyright by 2017 ~ 2019 STARTERGATE
  // This library follows CC BY-SA 4.0. Please refer to ( https://creativecommons.org/licenses/by-sa/4.0/ )
  class SID
  {
      private $clientName;

      // basic curl
      private function curlPost($url, $data, $method='POST')
      {
          $ch = curl_init();

          curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_URL, $url);

          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
          curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

          curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
          curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Content-Type: application/json']);
          curl_setopt($ch, CURLOPT_VERBOSE, true);
          $response = curl_exec($ch);

          $body = null;
          // error
          if (!$response) {
              $body = curl_error($ch);
              // HostNotFound, No route to Host, etc  Network related error
              $http_status = -1;
          } else {
              //parsing http status code
              $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
              $body = $response;
          }
          curl_close($ch);

          return $body;
      }

      // Construct and Destruct Function
      public function __construct()
      {
          if (func_get_args()) {
              $this->clientName = func_get_args()[0];

              session_start();

              return;
          } else {
              self::__destruct();
          }
      }

      private function __destruct()
      {
      }

      // Login functions
      public function login($clientid, $id, $pw)
      {
          try {
              $userdata = $this->curlPost('http://sid.donote.co:3000/api/v1/session', json_encode([
                  'type'        => 'login',
                  'clientid'    => $clientid,
                  'userid'      => $id,
                  'password'    => $pw,
                  'isPermanent' => false,
                  'isWeb'       => true,
              ]));
              $userdata = json_decode($userdata);
              if ($userdata->type === 'error') {
                  return 0;
              }

              $_SESSION['sid_sessid'] = $userdata->response_data[0];
              $_SESSION['sid_pid'] = $userdata->response_data[1];
              $_SESSION['sid_nickname'] = strip_tags($userdata->response_data[2]);

              $timedata = explode(' ', $userdata->response_data[3]);
              $datedata = explode('-', $timedata);
              $timedata = explode(':', $timedata);
              setcookie('sid_sessid', $userdata->response_data[0], gmmktime($timedata[2], $timedata[1], $timedata[0], $datedata[2], $datedata[1], $datedata[0]), '/');

              return 1;
          } catch (\Exception $e) {
              return -1;
          }
      }

      public function logout($clientid, $sessid)
      {
          $userdata = $this->curlPost('http://sid.donote.co:3000/api/v1/session', json_encode([
              'type'     => 'logout',
              'clientid' => $clientid,
              'sessid'   => $sessid,
          ]), 'DELETE');
          $userdata = json_decode($userdata);
          if ($userdata->type === 'error') {
              return 0;
          }
          if (!$userdata->is_succeed) {
              return 0;
          }
          session_destroy();

          // legacy support
          setcookie('sidAutorizeRikka', 0, time() - 3600, '/');
          setcookie('sidAutorizeYuuta', 0, time() - 3600, '/');

          return 1;
      }

      public function register(String $clientid, String $id, String $pw, String $nickname = 'User')
      {
          try {
              $userdata = $this->curlPost('http://sid.donote.co:3000/api/v1/user', json_encode([
                  'type'     => 'register',
                  'clientid' => $clientid,
                  'userid'   => $id,
                  'nickname' => $nickname,
                  'password' => $pw,
              ]));
              $userdata = json_decode($userdata);
              if ($userdata->type === 'error') {
                  return 0;
              }
              if (!$userdata->is_succeed) {
                  return 0;
              }

              return $userdata->private_id;
          } catch (\Exception $e) {
              return -1;
          }
      }

      // User Info Getter
      public function getUserNickname($clientid, $sessid)
      {
          try {
              $userdata = $this->curlPost("http://sid.donote.co:3000/api/v1/$clientid/$sessid/usname", false, 'GET');
              $userdata = json_decode($userdata);
              if ($userdata->type === 'error') {
                  return '';
              }
              if (!$userdata->is_valid) {
                  return '';
              }

              return $userdata->response_data;
          } catch (\Exception $e) {
              return '';
          }
      }

      public function authCheck($clientid, $sessid)
      {
          try {
              $userdata = $this->curlPost('http://sid.donote.co:3000/api/v1/session', json_encode([
                'type'     => 'login',
                'clientid' => $clientid,
                'sessid'   => $sessid,
            ]));
              $userdata = json_decode($userdata);
              if ($userdata->type === 'error') {
                  return 0;
              }
              $_SESSION['sid_sessid'] = $userdata->response_data[0];
              $_SESSION['sid_pid'] = $userdata->response_data[1];
              $_SESSION['sid_nickname'] = strip_tags($userdata->response_data[2]);

              $timedata = explode(' ', $userdata->response_data[3]);
              $datedata = explode('-', $timedata);
              $timedata = explode(':', $timedata);
              setcookie('sid_sessid', $userdata->response_data[0], gmmktime($timedata[2], $timedata[1], $timedata[0], $datedata[2], $datedata[1], $datedata[0]), '/');

              return 1;
          } catch (\Exception $e) {
              return -1;
          }
      }

      // Editional checking functions
      public function loginCheck($target)
      {
          ob_start();
          if (empty($_SESSION['sid_sessid'])) {
              header('Location: '.$target);
              exit;
          }

          return 1;
      }

      public function passwordCheck(String $clientid, String $sessid, String $pw)
      {
          try {
              $userdata = $this->curlPost('http://sid.donote.co:3000/api/v1/password/verify', json_encode([
                'type'     => 'verify',
                'data'     => 'password',
                'clientid' => $clientid,
                'sessid'   => $sessid,
                'value'    => $pw,
            ]));

              $userdata = json_decode($userdata);
              if ($userdata->type === 'error') {
                  return 0;
              }
              if ($userdata->is_valid === 'false') {
                  return 0;
              }

              return 1;
          } catch (\Exception $e) {
              return -1;
          }
      }

      // Editional Etc functions
      public function getClientName()
      {
          return $this->$clientName;
      }
  }
