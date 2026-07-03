<?php
/* ============================================================
   Minimal Firestore client — talks to the Firestore REST API
   directly over cURL. No Composer / gRPC extension required,
   which is important because Vercel's PHP runtime does not
   ship the grpc extension that the official google/cloud-firestore
   SDK needs.

   Auth: uses a Google service account (JSON key) and signs its
   own JWT with openssl to get an OAuth2 access token. The token
   is cached to a tmp file for the lifetime of the container.
   ============================================================ */

class Firestore
{
    private $projectId;
    private $clientEmail;
    private $privateKey;
    private $baseUrl;
    private static $accessToken = null;
    private static $accessTokenExpires = 0;

    public function __construct($projectId, $clientEmail, $privateKey)
    {
        $this->projectId = $projectId;
        $this->clientEmail = $clientEmail;
        // Environment variables often store the key with literal "\n" — turn them back into real newlines.
        $this->privateKey = str_replace('\n', "\n", $privateKey);
        $this->baseUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents";
    }

    /** Build a Firestore client straight from environment variables. */
    public static function fromEnv()
    {
        $projectId   = getenv('FIRESTORE_PROJECT_ID');
        $clientEmail = getenv('FIRESTORE_CLIENT_EMAIL');
        $privateKey  = getenv('FIRESTORE_PRIVATE_KEY');

        if (!$projectId || !$clientEmail || !$privateKey) {
            die('Firestore credentials are missing. Set FIRESTORE_PROJECT_ID, FIRESTORE_CLIENT_EMAIL, '
              . 'and FIRESTORE_PRIVATE_KEY as environment variables.');
        }
        return new self($projectId, $clientEmail, $privateKey);
    }

    /* ============================================================
       AUTH — service account JWT bearer flow
       ============================================================ */

    private function getAccessToken()
    {
        $cacheFile = sys_get_temp_dir() . '/firestore_token_' . md5($this->clientEmail) . '.json';

        // in-memory (same request) cache
        if (self::$accessToken && time() < self::$accessTokenExpires - 30) {
            return self::$accessToken;
        }
        // cross-request cache in /tmp (best effort — survives while the serverless container is warm)
        if (is_readable($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached && time() < $cached['expires'] - 30) {
                self::$accessToken = $cached['token'];
                self::$accessTokenExpires = $cached['expires'];
                return self::$accessToken;
            }
        }

        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss'   => $this->clientEmail,
            'scope' => 'https://www.googleapis.com/auth/datastore',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $segments = [
            $this->base64url(json_encode($header)),
            $this->base64url(json_encode($claims)),
        ];
        $signingInput = implode('.', $segments);

        $signature = '';
        $ok = openssl_sign($signingInput, $signature, $this->privateKey, 'SHA256');
        if (!$ok) {
            die('Firestore auth failed: could not sign JWT. Check FIRESTORE_PRIVATE_KEY.');
        }
        $segments[] = $this->base64url($signature);
        $jwt = implode('.', $segments);

        $response = $this->httpPost('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ], true);

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            die('Firestore auth failed: ' . $response);
        }

        self::$accessToken = $data['access_token'];
        self::$accessTokenExpires = $now + (int)$data['expires_in'];
        @file_put_contents($cacheFile, json_encode([
            'token' => self::$accessToken,
            'expires' => self::$accessTokenExpires,
        ]));

        return self::$accessToken;
    }

    private function base64url($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /* ============================================================
       LOW-LEVEL HTTP
       ============================================================ */

    private function httpPost($url, $body, $isForm = false)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $isForm ? http_build_query($body) : json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $isForm
            ? ['Content-Type: application/x-www-form-urlencoded']
            : ['Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken()]);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    private function request($method, $url, $body = null)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        $headers = ['Authorization: Bearer ' . $this->getAccessToken(), 'Content-Type: application/json'];
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = json_decode($result, true);
        return ['status' => $status, 'body' => $decoded];
    }

    /* ============================================================
       VALUE ENCODING  (PHP array  <->  Firestore "fields" format)
       ============================================================ */

    private function encodeValue($value)
    {
        if ($value === null) return ['nullValue' => null];
        if (is_bool($value)) return ['booleanValue' => $value];
        if (is_int($value)) return ['integerValue' => (string)$value];
        if (is_float($value)) return ['doubleValue' => $value];
        if (is_array($value)) {
            // sequential array => Firestore array; associative => Firestore map
            if (array_values($value) === $value) {
                return ['arrayValue' => ['values' => array_map([$this, 'encodeValue'], $value)]];
            }
            $fields = [];
            foreach ($value as $k => $v) $fields[$k] = $this->encodeValue($v);
            return ['mapValue' => ['fields' => $fields]];
        }
        return ['stringValue' => (string)$value];
    }

    private function encodeFields($assoc)
    {
        $fields = [];
        foreach ($assoc as $k => $v) $fields[$k] = $this->encodeValue($v);
        return $fields;
    }

    private function decodeValue($value)
    {
        if (!is_array($value)) return null;
        if (array_key_exists('nullValue', $value)) return null;
        if (array_key_exists('booleanValue', $value)) return $value['booleanValue'];
        if (array_key_exists('integerValue', $value)) return (int)$value['integerValue'];
        if (array_key_exists('doubleValue', $value)) return (float)$value['doubleValue'];
        if (array_key_exists('stringValue', $value)) return $value['stringValue'];
        if (array_key_exists('timestampValue', $value)) return $value['timestampValue'];
        if (array_key_exists('arrayValue', $value)) {
            $out = [];
            if (!empty($value['arrayValue']['values'])) {
                foreach ($value['arrayValue']['values'] as $v) $out[] = $this->decodeValue($v);
            }
            return $out;
        }
        if (array_key_exists('mapValue', $value)) {
            return $this->decodeFields(isset($value['mapValue']['fields']) ? $value['mapValue']['fields'] : []);
        }
        return null;
    }

    private function decodeFields($fields)
    {
        $out = [];
        foreach ($fields as $k => $v) $out[$k] = $this->decodeValue($v);
        return $out;
    }

    /** Pull the doc id (last path segment) out of a Firestore document "name". */
    private function idFromName($name)
    {
        $parts = explode('/', $name);
        return end($parts);
    }

    /* ============================================================
       PUBLIC CRUD API
       ============================================================ */

    /** Get one document by id. Returns assoc array (with '_id') or null if missing. */
    public function get($collection, $id)
    {
        $res = $this->request('GET', "{$this->baseUrl}/{$collection}/" . rawurlencode($id));
        if ($res['status'] !== 200 || !isset($res['body']['fields'])) return null;
        $doc = $this->decodeFields($res['body']['fields']);
        $doc['_id'] = $id;
        return $doc;
    }

    /** Create or fully overwrite a document at a specific id. */
    public function set($collection, $id, $data)
    {
        $body = ['fields' => $this->encodeFields($data)];
        $url = "{$this->baseUrl}/{$collection}/" . rawurlencode($id);
        $this->request('PATCH', $url, $body);
        $data['_id'] = $id;
        return $data;
    }

    /** Merge-update only the given fields on an existing document. */
    public function update($collection, $id, $data)
    {
        $mask = implode('&', array_map(function ($k) {
            return 'updateMask.fieldPaths=' . rawurlencode($k);
        }, array_keys($data)));
        $url = "{$this->baseUrl}/{$collection}/" . rawurlencode($id) . '?' . $mask;
        $body = ['fields' => $this->encodeFields($data)];
        $this->request('PATCH', $url, $body);
        return true;
    }

    /** Create a document with an auto-generated id. Returns the new id. */
    public function add($collection, $data)
    {
        $body = ['fields' => $this->encodeFields($data)];
        $res = $this->request('POST', "{$this->baseUrl}/{$collection}", $body);
        if (!isset($res['body']['name'])) return null;
        return $this->idFromName($res['body']['name']);
    }

    /** Delete a document by id. */
    public function delete($collection, $id)
    {
        $this->request('DELETE', "{$this->baseUrl}/{$collection}/" . rawurlencode($id));
        return true;
    }

    /**
     * Fetch every document in a collection (fine for small/medium collections
     * like this store's catalog — Firestore has no server-side JOIN/LIKE/AVG,
     * so search, sort, and rating averages are done in PHP after fetching).
     */
    public function all($collection)
    {
        $out = [];
        $pageToken = null;
        do {
            $url = "{$this->baseUrl}/{$collection}?pageSize=300";
            if ($pageToken) $url .= '&pageToken=' . urlencode($pageToken);
            $res = $this->request('GET', $url);
            if (!empty($res['body']['documents'])) {
                foreach ($res['body']['documents'] as $doc) {
                    $row = $this->decodeFields($doc['fields']);
                    $row['_id'] = $this->idFromName($doc['name']);
                    $out[] = $row;
                }
            }
            $pageToken = isset($res['body']['nextPageToken']) ? $res['body']['nextPageToken'] : null;
        } while ($pageToken);
        return $out;
    }

    /** Query a collection for documents where $field == $value (uses :runQuery). */
    public function where($collection, $field, $op, $value)
    {
        $filterValue = $this->encodeValue($value);
        $body = [
            'structuredQuery' => [
                'from' => [['collectionId' => $collection]],
                'where' => [
                    'fieldFilter' => [
                        'field' => ['fieldPath' => $field],
                        'op' => $op, // EQUAL, LESS_THAN, GREATER_THAN, etc.
                        'value' => $filterValue,
                    ],
                ],
            ],
        ];
        $url = str_replace('/documents', '', $this->baseUrl) . '/documents:runQuery';
        $res = $this->request('POST', $url, $body);
        $out = [];
        if (is_array($res['body'])) {
            foreach ($res['body'] as $entry) {
                if (!empty($entry['document'])) {
                    $row = $this->decodeFields($entry['document']['fields']);
                    $row['_id'] = $this->idFromName($entry['document']['name']);
                    $out[] = $row;
                }
            }
        }
        return $out;
    }

    /**
     * Thread-unsafe but good-enough auto-increment counter, so the app can keep
     * short, human-friendly numeric ids (game.php?id=5, order #WASD-00005, etc.)
     * instead of long random Firestore ids. For a busier production store you'd
     * want to do this inside a real Firestore transaction to avoid rare
     * duplicate-id races under concurrent writes.
     */
    public function nextId($counterName)
    {
        $doc = $this->get('counters', $counterName);
        $next = $doc ? ((int)$doc['value'] + 1) : 1;
        $this->set('counters', $counterName, ['value' => $next]);
        return $next;
    }
}
