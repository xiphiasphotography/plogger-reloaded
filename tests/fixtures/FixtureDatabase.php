<?php

declare(strict_types=1);

// A deterministic PDO test double, never a connection to a real database.
final class FixtureDatabase extends PDO
{
    public const DESCRIPTION_PAYLOAD = "Stored café &amp; text\n<strong onclick=\"storedFixture()\">Bold</strong><br />\n"
        . '<script>storedFixture()</script><img src=x onerror="storedFixture()">'
        . '<a href="javascript:storedFixture()">link</a>';
    public const TEXT_PAYLOAD = '\"><script>storedTextFixture()</script><img src=x onerror="storedTextFixture()">';
    public array $statements = [];

    public function __construct() {}

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $statement = new FixtureStatement($this, $query);
        $statement->execute();
        return $statement;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new FixtureStatement($this, $query);
    }

    public function quote(string $string, int $type = PDO::PARAM_STR): string|false
    {
        return "'" . str_replace("'", "''", $string) . "'";
    }

    public function lastInsertId(?string $name = null): string|false { return '1'; }

    public function getAttribute(int $attribute): mixed { return 'fixture'; }

    public function rows(string $query, array $parameters): array
    {
        $unsafeText = isset($_GET['fixture_stored_text']) ? self::TEXT_PAYLOAD : null;
        $this->statements[] = [$query, $parameters];
        $hashFile = ($_SERVER['DOCUMENT_ROOT'] ?? sys_get_temp_dir()).'/fixture-auth-hash.json';
        $cdnFile = ($_SERVER['DOCUMENT_ROOT'] ?? sys_get_temp_dir()).'/fixture-cdn.json';
        if (preg_match('/^UPDATE "fixture_config" SET "admin_password" = \? WHERE "admin_password" = \?$/', $query)) {
            $current = is_file($hashFile) ? json_decode(file_get_contents($hashFile), true) : md5('fixture-password');
            if ($current !== $parameters[1]) return [];
            file_put_contents($hashFile, json_encode($parameters[0]));
            return [['affected' => 1]];
        }
        if (isset($_GET['fixture_csrf_trace']) && preg_match('/^\s*(INSERT|UPDATE|DELETE|ALTER|DROP)\b/i', $query)) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/csrf-writes.log', $query . "\n", FILE_APPEND);
        }
        if (stripos($query, 'information_schema') !== false) return [['column_name' => 'id']];
        if (preg_match('/^UPDATE "fixture_config" SET /', $query)) {
            preg_match_all('/"([a-z_]+)" = \?/', $query, $columns);
            $position = array_search('cdn_url', $columns[1], true);
            if ($position !== false) file_put_contents($cdnFile, json_encode($parameters[$position]));
        }
        if (stripos($query, 'pg_indexes') !== false) return [];
        if (!preg_match('/^\s*SELECT\b/i', $query)) return [];
        if (stripos($query, 'GROUP BY') === false && preg_match('/COUNT\([^)]*\)\s+AS\s+"?([a-z_]+)/i', $query, $match)) {
            return [[$match[1] => str_contains($query, 'comments') ? 0 : 1]];
        }
        if (stripos($query, 'thumbnail_config') !== false) {
            $rows = [];
            foreach ([1 => 100, 2 => 500, 3 => 400, 4 => 60] as $id => $size) {
                $rows[] = ['id' => $id, 'max_size' => $size, 'update_timestamp' => 0,
                    'disabled' => 0, 'resize_option' => $id == 1 || $id == 4 ? 3 : 2];
            }
            return $rows;
        }
        if (preg_match('/\bFROM\s+"?fixture_config"?/i', $query)) {
            $row = [
                'gallery_name' => $unsafeText ?? 'Synthetic gallery', 'gallery_url' => 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/',
                'cdn_url' => is_file($cdnFile) ? json_decode(file_get_contents($cdnFile), true) : '',
                'admin_username' => 'fixture', 'admin_email' => 'fixture@example.invalid',
                'admin_password' => is_file($hashFile) ? json_decode(file_get_contents($hashFile), true) : md5('fixture-password'), 'activation_key' => '', 'date_format' => 'n.j.Y',
                'compression' => 75, 'thumb_num' => 20, 'default_sortby' => 'date', 'default_sortdir' => 'DESC',
                'album_sortby' => 'alpha', 'album_sortdir' => 'ASC', 'collection_sortby' => 'alpha',
                'collection_sortdir' => 'ASC', 'allow_dl' => isset($_GET['fixture_disable_download']) ? 0 : 1, 'allow_comments' => 1, 'allow_print' => isset($_GET['fixture_disable_print']) ? 0 : 1,
                'truncate' => 0, 'feed_num_entries' => 15, 'feed_title' => 'Fixture feed', 'feed_content' => 1,
                'use_mod_rewrite' => 0, 'comments_notify' => 0, 'comments_moderate' => 0,
                'theme_dir' => $_GET['fixture_theme'] ?? 'default', 'thumb_nav_range' => 0,
                'allow_fullpic' => 1, 'show_exif' => 1, 'schema_version' => 3,
            ];
            return [$row];
        }
        if (stripos($query, 'comments') !== false) return [];
        if (preg_match('/\bFROM\s+"?fixture_pictures"?/i', $query) && stripos($query, ' AS "album_id"') === false) {
            if ($parameters && (int) $parameters[0] !== 1) return [];
            return [[
                'id' => 1, 'parent_album' => 1, 'parent_collection' => 1,
                'path' => isset($_GET['fixture_missing_image']) ? 'fixture/album/missing.jpg' : 'fixture/album/photo.jpg',
                'caption' => $unsafeText ?? 'Fixture caption', 'description' => isset($_GET['fixture_stored_description']) ? self::DESCRIPTION_PAYLOAD : "Fixture description\nsecond line", 'allow_comments' => 1,
                'num_comments' => 0, 'num_pictures' => 1, 'imagecount' => 1,
                'collection_path' => 'fixture', 'album_path' => 'album',
                'date_modified' => '2020-01-02 03:04:05', 'date_submitted' => '2020-01-02 03:04:05',
                'unix_date_modified' => 1577934245, 'unix_date_submitted' => 1577934245, 'pubdate' => 1577934245,
                'unix_exif_date_taken' => null, 'exif_date_taken' => null, 'exif_camera' => $unsafeText ?? '',
                'exif_shutterspeed' => '', 'exif_focallength' => '', 'exif_flash' => '', 'exif_aperture' => '', 'exif_iso' => '',
            ]];
        }
        if (preg_match('/\bFROM\s+"?fixture_albums"?/i', $query) || stripos($query, ' AS "album_id"') !== false) {
            return [['id' => 1, 'parent_id' => 1, 'name' => $unsafeText ?? 'Fixture album', 'description' => isset($_GET['fixture_stored_description']) ? self::DESCRIPTION_PAYLOAD : '',
                'path' => 'album', 'thumbnail_id' => 0, 'num_pictures' => 1, 'albumcount' => 1,
                'collection_name' => $unsafeText ?? 'Fixture collection', 'album_name' => $unsafeText ?? 'Fixture album',
                'collection_path' => 'fixture', 'collection_id' => 1, 'album_path' => 'album', 'album_id' => 1]];
        }
        if (stripos($query, 'collections') !== false) {
            return [['id' => 1, 'name' => $unsafeText ?? 'Fixture collection', 'description' => isset($_GET['fixture_stored_description']) ? self::DESCRIPTION_PAYLOAD : '', 'path' => 'fixture',
                'thumbnail_id' => 0, 'num_albums' => 1]];
        }
        return [];
    }
}

final class FixtureStatement extends PDOStatement
{
    private array $bindings = [];
    private array $rows = [];
    private int $position = 0;

    public function __construct(private FixtureDatabase $database, private string $sql) {}

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->bindings[$param] = $value;
        return true;
    }

    public function execute(?array $params = null): bool
    {
        $this->rows = $this->database->rows($this->sql, $params ?? array_values($this->bindings));
        $this->position = 0;
        return true;
    }

    public function rowCount(): int { return count($this->rows); }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        $row = $this->rows[$this->position++] ?? false;
        return $row && $mode === PDO::FETCH_NUM ? array_values($row) : $row;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $rows = array_slice($this->rows, $this->position);
        $this->position = count($this->rows);
        return $rows;
    }
}
