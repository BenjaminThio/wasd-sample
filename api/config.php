<?php
session_start();

define('FIREBASE_PROJECT_ID', 'YOUR_PROJECT_ID');
define('FIREBASE_API_KEY', 'YOUR_API_KEY');

class FirestoreClient {
    private $base_url;

    public function __construct() {
        $this->base_url = "https://firestore.googleapis.com/v1/projects/" . FIREBASE_PROJECT_ID . "/databases/(default)/documents/";
    }

    private function request($endpoint, $method = 'GET', $data = null) {
        $url = $this->base_url . $endpoint . "?key=" . FIREBASE_API_KEY;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
        
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    // Map raw Firestore document formats to clean PHP arrays
    private function parseFields($fields) {
        $output = [];
        if (!$fields) return $output;
        foreach ($fields as $key => $value) {
            if (isset($value['stringValue'])) $output[$key] = $value['stringValue'];
            elseif (isset($value['integerValue'])) $output[$key] = (int)$value['integerValue'];
            elseif (isset($value['doubleValue'])) $output[$key] = (float)$value['doubleValue'];
            elseif (isset($value['booleanValue'])) $output[$key] = (bool)$value['booleanValue'];
        }
        return $output;
    }

    public function getDocument($collection, $id) {
        $res = $this->request("$collection/$id");
        if (isset($res['error'])) return null;
        $doc = $this->parseFields($res['fields'] ?? []);
        $doc['id'] = $id;
        return $doc;
    }

    public function getCollection($collection) {
        $res = $this->request($collection);
        $documents = [];
        if (isset($res['documents'])) {
            foreach ($res['documents'] as $doc) {
                $id = basename($doc['name']);
                $parsed = $this->parseFields($doc['fields'] ?? []);
                $parsed['id'] = $id;
                $documents[] = $parsed;
            }
        }
        return $documents;
    }

    public function saveDocument($collection, $id, $data) {
        $fields = [];
        foreach ($data as $key => $val) {
            if (is_int($val)) $fields[$key] = ['integerValue' => (string)$val];
            elseif (is_float($val)) $fields[$key] = ['doubleValue' => $val];
            elseif (is_bool($val)) $fields[$key] = ['booleanValue' => $val];
            else $fields[$key] = ['stringValue' => (string)$val];
        }
        return $this->request("$collection/$id", 'PATCH', ['fields' => $fields]);
    }

    public function deleteDocument($collection, $id) {
        return $this->request("$collection/$id", 'DELETE');
    }
}

$db = new FirestoreClient();

/* ---------- Utilities ---------- */
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function current_user($db) {
    static $user = false;
    if ($user !== false) return $user;
    $user = null;
    if (isset($_SESSION['user_id'])) {
        $user = $db->getDocument('users', $_SESSION['user_id']);
        if ($user === null) unset($_SESSION['user_id']);
    }
    return $user;
}

function require_login($db) {
    if (!current_user($db)) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        set_flash('Log in to continue.', 'error');
        header('Location: login.php');
        exit;
    }
}

function require_admin($db) {
    $user = current_user($db);
    if (!$user || !($user['is_admin'] ?? false)) {
        set_flash('That area is for store admins.', 'error');
        header('Location: index.php');
        exit;
    }
}

function set_flash($message, $type = 'success') {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function final_price($price, $discount) {
    return $discount > 0 ? $price * (100 - $discount) / 100 : $price;
}

function rm($amount) {
    return 'RM ' . number_format((float)$amount, 2);
}

function stars($rating) {
    $rating = (int)round($rating);
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}

function cart_count($db) {
    $user = current_user($db);
    if (!$user) return 0;
    // In NoSQL layouts, it is best practice to keep an item count inside the user profile directly
    return (int)($user['cart_item_count'] ?? 0);
}