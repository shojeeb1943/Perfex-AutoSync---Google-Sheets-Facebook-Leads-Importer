<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Lightweight Google Sheets API v4 client using a Service Account.
 * No Composer dependencies — uses PHP built-in openssl + cURL.
 */
class Gs_GoogleSheetsClient
{
    private $credentials;
    private static $token_cache = [];

    public function __construct($service_account_json)
    {
        if (empty($service_account_json)) {
            throw new Exception('Google Service Account JSON is not configured.');
        }
        $this->credentials = json_decode($service_account_json, true);
        if (!is_array($this->credentials) || empty($this->credentials['private_key']) || empty($this->credentials['client_email'])) {
            throw new Exception('Invalid Service Account JSON — missing private_key or client_email.');
        }
    }

    /**
     * Get the header row (first row) of a sheet tab.
     */
    public function get_headers($spreadsheet_id, $tab_name = 'Sheet1')
    {
        $range  = rawurlencode($tab_name . '!1:1');
        $data   = $this->_api_get($spreadsheet_id, $range);
        $values = isset($data['values']) ? $data['values'] : [];
        return isset($values[0]) ? $values[0] : [];
    }

    /**
     * Get all rows (including header) from a sheet tab.
     */
    public function get_rows($spreadsheet_id, $tab_name = 'Sheet1')
    {
        $range = rawurlencode($tab_name);
        $data  = $this->_api_get($spreadsheet_id, $range);
        return isset($data['values']) ? $data['values'] : [];
    }

    /**
     * Force a fresh OAuth round-trip — used by "Test Connection" button.
     */
    public function get_token_for_test()
    {
        $email = $this->credentials['client_email'];
        unset(self::$token_cache[$email]);
        $this->_get_access_token();
        return [
            'client_email' => $email,
            'project_id'   => isset($this->credentials['project_id']) ? $this->credentials['project_id'] : null,
            'expires_in'   => self::$token_cache[$email]['expires'] - time(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function _api_get($spreadsheet_id, $encoded_range)
    {
        $token = $this->_get_access_token();
        $url   = 'https://sheets.googleapis.com/v4/spreadsheets/' . $spreadsheet_id . '/values/' . $encoded_range;

        $ch = curl_init($url);
        curl_setopt_array($ch, $this->_curl_defaults() + [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
        ]);
        $response   = curl_exec($ch);
        $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curl_error) {
            throw new Exception($this->_friendly_curl_error($curl_error));
        }

        $body = json_decode($response, true);

        if ($http_code !== 200) {
            $msg = (is_array($body) && isset($body['error']['message']))
                ? $body['error']['message']
                : 'HTTP ' . $http_code;
            throw new Exception('Google Sheets API error: ' . $msg);
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

        $payload = [
            'iss'   => $email,
            'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $jwt = $this->_make_jwt($payload);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, $this->_curl_defaults() + [
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response   = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curl_error) {
            throw new Exception($this->_friendly_curl_error($curl_error));
        }

        $token_data = json_decode($response, true);
        if (!is_array($token_data) || empty($token_data['access_token'])) {
            $msg = isset($token_data['error_description'])
                ? $token_data['error_description']
                : (isset($token_data['error']) ? $token_data['error'] : 'Unknown token error');
            throw new Exception('Failed to get Google access token: ' . $msg);
        }

        $expires_in = isset($token_data['expires_in']) ? (int) $token_data['expires_in'] : 3600;
        self::$token_cache[$email] = [
            'token'   => $token_data['access_token'],
            'expires' => $now + $expires_in,
        ];

        return $token_data['access_token'];
    }

    private function _curl_defaults()
    {
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        $cainfo = ini_get('curl.cainfo');
        if (empty($cainfo)) {
            $cainfo = ini_get('openssl.cafile');
        }
        if (!empty($cainfo) && is_file($cainfo)) {
            $opts[CURLOPT_CAINFO] = $cainfo;
        }
        return $opts;
    }

    private function _friendly_curl_error($curl_error)
    {
        if (stripos($curl_error, 'SSL') !== false || stripos($curl_error, 'CA') !== false) {
            return 'cURL SSL error: ' . $curl_error
                . ' — Your PHP may not have a CA bundle. Download cacert.pem from https://curl.se/ca/cacert.pem'
                . ' and set curl.cainfo in php.ini.';
        }
        return 'cURL error: ' . $curl_error;
    }

    private function _make_jwt($payload)
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];

        $b64h = $this->_base64url(json_encode($header));
        $b64p = $this->_base64url(json_encode($payload));
        $input = $b64h . '.' . $b64p;

        $pk = openssl_pkey_get_private($this->credentials['private_key']);
        if (!$pk) {
            throw new Exception('Failed to load private key from Service Account JSON.');
        }

        openssl_sign($input, $sig, $pk, OPENSSL_ALGO_SHA256);

        return $input . '.' . $this->_base64url($sig);
    }

    private function _base64url($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
