<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Lightweight Google Sheets API v4 client using a Service Account.
 * No Composer or vendor dependencies — uses PHP's built-in openssl and cURL.
 */
class GoogleSheetsClient
{
    private $credentials;
    private $access_token;
    private $token_expires = 0;

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

    /**
     * Returns the header row (first row) of the sheet.
     */
    public function get_headers($spreadsheet_id, $tab_name = 'Sheet1')
    {
        $range = rawurlencode($tab_name . '!1:1');
        $data  = $this->_api_get($spreadsheet_id, $range);
        $values = $data['values'] ?? [];
        return $values[0] ?? [];
    }

    /**
     * Returns all rows. Index 0 is the header row, the rest are data rows.
     */
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
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            throw new Exception('cURL error: ' . $curl_error);
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
        // Reuse token if still valid (with 60s buffer)
        if ($this->access_token && time() < $this->token_expires - 60) {
            return $this->access_token;
        }

        $now = time();
        $payload = [
            'iss'   => $this->credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $jwt = $this->_make_jwt($payload);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            throw new Exception('cURL error fetching token: ' . $curl_error);
        }

        $token_data = json_decode($response, true);
        if (empty($token_data['access_token'])) {
            $msg = $token_data['error_description'] ?? $token_data['error'] ?? 'Unknown token error';
            throw new Exception('Failed to get Google access token: ' . $msg);
        }

        $this->access_token  = $token_data['access_token'];
        $this->token_expires = $now + ($token_data['expires_in'] ?? 3600);

        return $this->access_token;
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
