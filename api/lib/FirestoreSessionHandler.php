<?php
/* ============================================================
   Firestore-backed session handler.

   Why this exists: PHP's default session handler writes session
   data to a local temp file. That works fine on a normal server,
   but on Vercel each request can be handled by a different
   serverless function instance with its own throwaway filesystem
   — so a session written during login might never be seen again,
   which looks like "logging in does nothing."

   Storing sessions in Firestore instead means every invocation,
   on any container, reads/writes the same place.
   ============================================================ */

class FirestoreSessionHandler implements SessionHandlerInterface
{
    private $conn;
    private $lifetime;

    public function __construct($conn, $lifetimeSeconds = null)
    {
        $this->conn = $conn;
        $this->lifetime = $lifetimeSeconds ?: (int)(ini_get('session.gc_maxlifetime') ?: 1440);
    }

    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read($id): string
    {
        $doc = $this->conn->get('sessions', $id);
        if (!$doc) return '';
        if (isset($doc['expires_at']) && $doc['expires_at'] < time()) {
            $this->conn->delete('sessions', $id);
            return '';
        }
        return isset($doc['data']) ? $doc['data'] : '';
    }

    public function write($id, $data): bool
    {
        $this->conn->set('sessions', $id, array(
            'data' => $data,
            'expires_at' => time() + $this->lifetime,
        ));
        return true;
    }

    public function destroy($id): bool
    {
        $this->conn->delete('sessions', $id);
        return true;
    }

    public function gc($max_lifetime): int|false
    {
        // Expired session cleanup is left to a Firestore TTL policy on the
        // "sessions" collection's expires_at field (set once, in the console,
        // under Firestore -> TTL) rather than scanning here on every request.
        return 0;
    }
}
