<?php
/* ============================================================
   Minimal Firestore client — talks to the Firestore REST API
   directly over cURL. No Composer / gRPC extension required,
   which is important because Vercel's PHP runtime does not
   ship the grpc extension that the official google/cloud-firestore
   SDK needs.

   Auth: uses your Firebase project's Web API key (the same one
   from the "Web app" config in the Firebase console), appended as
   a ?key=... query parameter on every request. This is simpler
   than a service account (no JWT signing, no OAuth token exchange)
   but it means Firestore Security Rules — not Google-verified
   server identity — are the ONLY thing standing between the
   internet and your data. See README.md's security warning:
   this setup requires Security Rules open enough to allow
   unauthenticated read/write, which exposes every document in
   your database (including password hashes) to anyone who has
   the project id + API key. Acceptable for a personal/demo
   project; not recommended for anything handling real user data.
   ============================================================ */

class Firestore
{
    private $projectId;
    private $apiKey;
    private $baseUrl;

    /* Per-request read cache. Pages like games.php/index.php call
       all('games') or where(...) more than once while building a page
       (catalog listing + genre filter list, etc.) — without this, every
       one of those calls is a fresh HTTPS round-trip to Firestore, which
       is what made pages feel slow. Writes (set/update/delete/add) clear
       the relevant cache entries so you never read stale data back. */
    private $cache = array();

    /* One curl handle reused for every request this instance makes.
       Closing and reopening a connection forces a fresh TCP + TLS
       handshake for every single Firestore call — on a page that makes
       6-8 calls, that's 6-8 handshakes instead of 1. Reusing the handle
       lets curl keep the underlying connection to firestore.googleapis.com
       alive and reuse it for subsequent calls in the same request. */
    private $ch = null;

    public function __construct($projectId, $apiKey)
    {
        $this->projectId = $projectId;
        $this->apiKey = $apiKey;
        $this->baseUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents";
    }

    /** Build a Firestore client straight from environment variables. */
    public static function fromEnv()
    {
        $projectId = getenv('FIRESTORE_PROJECT_ID');
        $apiKey    = getenv('FIRESTORE_API_KEY');

        if (!$projectId || !$apiKey) {
            die('Firestore credentials are missing. Set FIRESTORE_PROJECT_ID and FIRESTORE_API_KEY as environment variables.');
        }
        return new self($projectId, $apiKey);
    }

    /** Append ?key=... (or &key=... if the URL already has a query string). */
    private function withKey($url)
    {
        return $url . (strpos($url, '?') !== false ? '&' : '?') . 'key=' . urlencode($this->apiKey);
    }

    /* ============================================================
       LOW-LEVEL HTTP
       ============================================================ */

    /** Lazily create (once) the shared curl handle used for every call. */
    private function curlHandle()
    {
        if ($this->ch === null) {
            $this->ch = curl_init();
            curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 5);  // fail fast instead of hanging
            curl_setopt($this->ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($this->ch, CURLOPT_TCP_KEEPALIVE, 1);
        }
        return $this->ch;
    }

    private function request($method, $url, $body = null)
    {
        $ch = $this->curlHandle();
        curl_setopt($ch, CURLOPT_URL, $this->withKey($url));
        curl_setopt($ch, CURLOPT_HTTPGET, true); // reset any leftover method/body from a prior call on this handle
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        $headers = ['Content-Type: application/json'];
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        if ($result === false) {
            $err = curl_error($ch);
            die("cURL request to $url failed: $err\n"
              . "This usually means the curl PHP extension isn't enabled, or curl can't find a valid "
              . "SSL certificate bundle. See the README's Windows troubleshooting notes.\n");
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $decoded = json_decode($result, true);
        return ['status' => $status, 'body' => $decoded];
    }

    public function __destruct()
    {
        if ($this->ch !== null) curl_close($this->ch);
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
        $key = "get:{$collection}/{$id}";
        if (array_key_exists($key, $this->cache)) return $this->cache[$key];

        $res = $this->request('GET', "{$this->baseUrl}/{$collection}/" . rawurlencode($id));
        if ($res['status'] !== 200 || !isset($res['body']['fields'])) {
            $this->cache[$key] = null;
            return null;
        }
        $doc = $this->decodeFields($res['body']['fields']);
        $doc['_id'] = $id;
        $this->cache[$key] = $doc;
        return $doc;
    }

    /** Drop cached reads for a collection (and one doc id, if given) after a write. */
    private function invalidate($collection, $id = null)
    {
        if ($id !== null) unset($this->cache["get:{$collection}/{$id}"]);
        foreach (array_keys($this->cache) as $key) {
            if ($key === "all:{$collection}" || strpos($key, "where:{$collection}:") === 0) {
                unset($this->cache[$key]);
            }
        }
    }

    /** Create or fully overwrite a document at a specific id. */
    public function set($collection, $id, $data)
    {
        $body = ['fields' => $this->encodeFields($data)];
        $url = "{$this->baseUrl}/{$collection}/" . rawurlencode($id);
        $this->request('PATCH', $url, $body);
        $this->invalidate($collection, $id);
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
        $this->invalidate($collection, $id);
        return true;
    }

    /** Create a document with an auto-generated id. Returns the new id. */
    public function add($collection, $data)
    {
        $body = ['fields' => $this->encodeFields($data)];
        $res = $this->request('POST', "{$this->baseUrl}/{$collection}", $body);
        $this->invalidate($collection);
        if (!isset($res['body']['name'])) return null;
        return $this->idFromName($res['body']['name']);
    }

    /** Delete a document by id. */
    public function delete($collection, $id)
    {
        $this->request('DELETE', "{$this->baseUrl}/{$collection}/" . rawurlencode($id));
        $this->invalidate($collection, $id);
        return true;
    }

    /**
     * Fetch every document in a collection (fine for small/medium collections
     * like this store's catalog — Firestore has no server-side JOIN/LIKE/AVG,
     * so search, sort, and rating averages are done in PHP after fetching).
     */
    public function all($collection)
    {
        $key = "all:{$collection}";
        if (array_key_exists($key, $this->cache)) return $this->cache[$key];

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

        $this->cache[$key] = $out;
        return $out;
    }

    /** Query a collection for documents where $field == $value (uses :runQuery). */
    public function where($collection, $field, $op, $value)
    {
        $key = "where:{$collection}:{$field}:{$op}:" . json_encode($value);
        if (array_key_exists($key, $this->cache)) return $this->cache[$key];

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

        $this->cache[$key] = $out;
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
