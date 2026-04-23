<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Lightweight Google Sheets API v4 client using a Service Account.
 * No Composer or vendor dependencies — uses PHP's built-in openssl and cURL.
 *
 * Token cache is static so that multiple sheet syncs in a single request
 * share one OAuth round-trip (keyed by service-account client_email).
 */
class Gs_GoogleSheetsClient
{
    private $credentials;

    private static $token_cache = []; // [client_email => ['token' => ..., 'expires' => ts]]

    public function __construct($service_account_json)
    {
        if (empty($service_account_json)) {
            throw new Exception('Google Service Account JSON is not configured.');
        }
        $this->credentials = json_decode($service_account_json, true);
        if (!$this->credentials || empty($this->credentials['private_key']) || empty($this->credentials['client_email'])) {
            throw new Exception('Invalid Google Service Account JSON — missing private_key or client_email.');
        }
    }

    public function get_headers($spreadsheet_id, $tab_name = 'Sheet1')
    {
        $range  = rawurlencode($tab_name . '!1:1');
        $data   = $this->_api_get($spreadsheet_id, $range);
        $values = $data['values'] ?? [];
        return $values[0] ?? [];
    }

    public function get_rows($spreadsheet_id, $tab_name = 'Sheet1')
    {
        $range = rawurlencode($tab_name);
        $data  = $this->_api_get($spreadsheet_id, $range);
        return $data['values'] ?? [];
    }

    // -------------------------------------------------------------------------

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
            $msg = $body['error']['message'] ?? ('HTTP ' . $http_code);
            throw new Exception('Google Sheets API error: ' . $msg);
        }

        return $body;
    }

    private function _get_access_token()
    {
        $email = $this->credentials['client_email'];
        $now   = time();

        if (isset(self::$token_cache[$email])) {
            $cached = self::$token_cache[$email];
            if ($now < $cached['expires'] - 60) {
                return $cached['token'];
            }
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
        if (empty($token_data['access_token'])) {
            $msg = $token_data['error_description'] ?? $token_data['error'] ?? 'Unknown token error';
            throw new Exception('Failed to get Google access token: ' . $msg);
        }

        self::$token_cache[$email] = [
            'token'   => $token_data['access_token'],
            'expires' => $now + ($token_data['expires_in'] ?? 3600),
        ];

        return $token_data['access_token'];
    }

    private function _curl_defaults()
    {
        $defaults = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        // If PHP has a CA bundle configured, pass it explicitly so cURL on
        // Windows/XAMPP (which often has no default) verifies correctly.
        $cainfo = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
        if ($cainfo && is_file($cainfo)) {
            $defaults[CURLOPT_CAINFO] = $cainfo;
        }
        return $defaults;
    }

    private function _friendly_curl_error($curl_error)
    {
        if (stripos($curl_error, 'SSL certificate') !== false
            || stripos($curl_error, 'unable to get local issuer') !== false
            || stripos($curl_error, 'CA') !== false) {
            return 'cURL SSL error: ' . $curl_error
                . ' — Your PHP install has no CA bundle. On Windows/XAMPP, download cacert.pem from https://curl.se/ca/cacert.pem '
                . 'and set curl.cainfo in php.ini to its path. See README Troubleshooting.';
        }
        return 'cURL error: ' . $curl_error;
    }

    private function _make_jwt($payload)
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];

        $b64_header  = $this->_base64url(json_encode($header));
        $b64_payload = $this->_base64url(json_encode($payload));
        $signing_input = $b64_header . '.' . $b64_payload;

        $private_key = openssl_pkey_get_private($this->credentials['private_key']);
        if (!$private_key) {
            throw new Exception('Failed to load private key from Service Account JSON.');
        }

        openssl_sign($signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256);

        return $signing_input . '.' . $this->_base64url($signature);
    }

    private function _base64url($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
