<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Gs_GoogleSheetsClient
{
    private $credentials;
    private static $token_cache = array();

    public function __construct($service_account_json)
    {
        if (empty($service_account_json)) {
            throw new Exception('Service Account JSON is empty.');
        }
        $this->credentials = json_decode($service_account_json, true);
        if (!is_array($this->credentials) || empty($this->credentials['private_key']) || empty($this->credentials['client_email'])) {
            throw new Exception('Invalid Service Account JSON.');
        }
    }

    public function get_headers($spreadsheet_id, $tab_name = 'Sheet1')
    {
        $range  = rawurlencode($tab_name . '!1:1');
        $data   = $this->_api_get($spreadsheet_id, $range);
        return isset($data['values'][0]) ? $data['values'][0] : array();
    }

    public function get_rows($spreadsheet_id, $tab_name = 'Sheet1')
    {
        $range = rawurlencode($tab_name);
        $data  = $this->_api_get($spreadsheet_id, $range);
        return isset($data['values']) ? $data['values'] : array();
    }

    public function get_token_for_test()
    {
        $email = $this->credentials['client_email'];
        unset(self::$token_cache[$email]);
        $this->_get_access_token();
        return array(
            'client_email' => $email,
            'project_id'   => isset($this->credentials['project_id']) ? $this->credentials['project_id'] : '',
            'expires_in'   => self::$token_cache[$email]['expires'] - time(),
        );
    }

    private function _api_get($spreadsheet_id, $encoded_range)
    {
        $token = $this->_get_access_token();
        $url   = 'https://sheets.googleapis.com/v4/spreadsheets/' . $spreadsheet_id . '/values/' . $encoded_range;

        $ch = curl_init($url);
        curl_setopt_array($ch, $this->_curl_opts() + array(
            CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $token),
        ));
        $response   = curl_exec($ch);
        $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curl_error) {
            throw new Exception('cURL error: ' . $curl_error);
        }

        $body = json_decode($response, true);

        if ($http_code !== 200) {
            $msg = (is_array($body) && isset($body['error']['message']))
                 ? $body['error']['message']
                 : 'HTTP ' . $http_code;
            throw new Exception('Google API error: ' . $msg);
        }

        return $body;
    }

    private function _get_access_token()
    {
        $email = $this->credentials['client_email'];
        $now   = time();

        if (isset(self::$token_cache[$email]) && $now < self::$token_cache[$email]['expires'] - 60) {
            return self::$token_cache[$email]['token'];
        }

        $jwt = $this->_make_jwt(array(
            'iss'   => $email,
            'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ));

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, $this->_curl_opts() + array(
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => http_build_query(array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            )),
            CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
        ));
        $response   = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curl_error) {
            throw new Exception('cURL error getting token: ' . $curl_error);
        }

        $td = json_decode($response, true);
        if (!is_array($td) || empty($td['access_token'])) {
            $msg = isset($td['error_description']) ? $td['error_description'] : (isset($td['error']) ? $td['error'] : 'Unknown');
            throw new Exception('Token error: ' . $msg);
        }

        $exp = isset($td['expires_in']) ? (int)$td['expires_in'] : 3600;
        self::$token_cache[$email] = array(
            'token'   => $td['access_token'],
            'expires' => $now + $exp,
        );

        return $td['access_token'];
    }

    private function _curl_opts()
    {
        $opts = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        );
        $ca = ini_get('curl.cainfo');
        if (empty($ca)) { $ca = ini_get('openssl.cafile'); }
        if (!empty($ca) && is_file($ca)) {
            $opts[CURLOPT_CAINFO] = $ca;
        }
        return $opts;
    }

    private function _make_jwt($payload)
    {
        $h = $this->_b64u(json_encode(array('alg' => 'RS256', 'typ' => 'JWT')));
        $p = $this->_b64u(json_encode($payload));
        $input = $h . '.' . $p;

        $pk = openssl_pkey_get_private($this->credentials['private_key']);
        if (!$pk) {
            throw new Exception('Cannot load private key from Service Account JSON.');
        }
        openssl_sign($input, $sig, $pk, OPENSSL_ALGO_SHA256);

        return $input . '.' . $this->_b64u($sig);
    }

    private function _b64u($d)
    {
        return rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
    }
}
