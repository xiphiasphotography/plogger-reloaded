<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
require_once __DIR__.'/fixtures/FixtureDatabase.php';

final class DatabaseTest extends TestCase
{
    public function testDriverConnectionSettingsAndNonDefaultPorts(): void
    {
        self::assertSame('mysql:host=localhost;dbname=gallery;port=33336;charset=utf8', plogger_db_dsn('mysql', 'localhost', 'gallery', 33336));
        self::assertSame('pgsql:host=localhost;dbname=gallery;port=33337', plogger_db_dsn('pgsql', 'localhost', 'gallery', '33337'));
        $this->expectException(InvalidArgumentException::class);
        plogger_db_dsn('mysql', 'localhost;dbname=other', 'gallery');
    }
    public function testPreparedValuesNeverBecomeSqlSyntax(): void
    {
        $connection = new FixtureDatabase();
        $value = "O'Brien ' OR 1=1 -- café\nline";
        plogger_db_execute($connection, 'SELECT * FROM "fixture_albums" WHERE "name" = ?', [$value]);
        self::assertSame('SELECT * FROM "fixture_albums" WHERE "name" = ?', $connection->statements[0][0]);
        self::assertSame([$value], $connection->statements[0][1]);
    }

    public function testIdentifiersAreValidatedSeparatelyFromValues(): void
    {
        self::assertSame('"custom_123_albums"', plogger_db_identifier('custom_123_albums'));
        foreach (['', 'albums; DROP TABLE config', 'a.b', 'a"b', str_repeat('a', 64)] as $name) {
            try { plogger_db_identifier($name); self::fail('Unsafe identifier accepted'); }
            catch (InvalidArgumentException $exception) { self::assertSame('Invalid database identifier', $exception->getMessage()); }
        }
    }

    public function testNonScalarBindingsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        plogger_db_execute(new FixtureDatabase(), 'SELECT ?', [['injection']]);
    }

    public function testSortFieldsCannotIntroduceSqlSyntax(): void
    {
        self::assertSame(' ORDER BY "name" ASC', plogger_db_order('name', 'asc'));
        self::assertSame(' ORDER BY "id" DESC', plogger_db_order('name; DROP TABLE pictures', 'DESC; --'));
    }

    public function testTablePrefixesAreValidatedWithoutDatabaseAccess(): void
    {
        foreach (['', 'Custom_42_', 'bad; DROP TABLE albums'] as $prefix) {
            $code = 'define("PLOGGER_TABLE_PREFIX",'.var_export($prefix, true).'); require '
                .var_export(dirname(__DIR__).'/plog-includes/plog-database.php', true)
                .'; try { echo plogger_db_table("albums"); } catch (InvalidArgumentException $error) { echo "rejected"; }';
            $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $errors);
            self::assertSame(str_contains($prefix, ';') ? 'rejected' : '"'.$prefix.'albums"', $output);
        }
    }
}
