<?php
require_once __DIR__.'/plog-database.php';
require_once __DIR__.'/plog-schema.php';
require_once __DIR__.'/plog-security.php';
require_once __DIR__.'/plog-auth.php';
require_once __DIR__.'/plog-upgrade-config.php';
require_once __DIR__.'/plog-theme-check.php';

/** Upgrade only a known gallery schema; never load its application bootstrap. */
final class PloggerUpgrade {
    public const VERSION = 3;
    private string $driver;
    private string $prefix;
    private string $root;

    public function __construct(private PDO $db, string $prefix, string $root) {
        $this->driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!in_array($this->driver,['mysql','pgsql'],true)) throw new RuntimeException('Unsupported upgrade driver.');
        $this->prefix = plogger_db_prefix($prefix);
        plogger_db_identifier($prefix.'schema_migrations');
        $resolved = realpath($root);
        if ($resolved === false || !is_dir($resolved)) throw new RuntimeException('Installation directory is missing.');
        $this->root = $resolved;
    }

    public function table(string $name): string {
        if (!in_array($name,['config','collections','albums','pictures','comments','thumbnail_config','schema_migrations'],true)) throw new RuntimeException('Unknown upgrade table.');
        return plogger_db_identifier($this->prefix.$name);
    }

    /** @param array<array-key, mixed> $parameters */

    public function query(string $sql, array $parameters=[]): PDOStatement {
        return plogger_db_execute($this->db,$sql,$parameters);
    }

    /** @return array<array-key, mixed> */

    public function columns(string $table): array {
        $rows=$this->query('SELECT column_name, data_type, is_nullable, column_default, character_maximum_length FROM information_schema.columns WHERE table_schema = '
            .($this->driver==='mysql'?'DATABASE()':'current_schema()').' AND table_name = ?',[$this->prefix.$table])->fetchAll();
        $columns=[];
        foreach($rows as $row) $columns[$row['column_name']]=$row;
        return $columns;
    }

    /** @return array<array-key, mixed> */

    public function config(): array {
        $rows=$this->query('SELECT * FROM '.$this->table('config').' LIMIT 2')->fetchAll();
        if(count($rows)!==1) throw new RuntimeException('Upgrade requires exactly one gallery configuration row.');
        return $rows[0];
    }

    public function version(): int {
        $config=$this->config();
        $version=$config['schema_version'] ?? 0;
        if (!ctype_digit((string)$version) || (int)$version>self::VERSION) throw new RuntimeException('Invalid or newer schema version; use matching application code.');
        return (int)$version;
    }

    /** @return array<array-key, mixed> */

    public function snapshot(): array {
        $counts=[];
        foreach(['collections','albums','pictures','comments','thumbnail_config'] as $table) {
            $counts[$table]=(int)$this->query('SELECT COUNT(*) FROM '.$this->table($table))->fetchColumn();
        }
        $orphans=[];
        foreach([['albums','parent_id','collections'],['pictures','parent_album','albums'],
            ['pictures','parent_collection','collections'],['comments','parent_id','pictures']] as [$table,$column,$parent]) {
            $orphans[$table.'.'.$column]=(int)$this->query('SELECT COUNT(*) FROM '.$this->table($table).' c LEFT JOIN '.$this->table($parent)
                .' p ON c.'.plogger_db_identifier($column).' = p."id" WHERE p."id" IS NULL')->fetchColumn();
        }
        $config=$this->config();
        unset($config['schema_version'],$config['cdn_url'],$config['show_exif']);
        ksort($config);
        return ['counts'=>$counts,'orphans'=>$orphans,'configuration_hash'=>hash('sha256',serialize($config))];
    }

    /** No writes, including lock files, occur during inspection. */
    /** @return array<array-key, mixed> */
    public function inspect(): array {
        $schema=plogger_schema_tables($this->driver); $observed=[];
        foreach($schema as $table=>[$definition]) {
            if($table==='schema_migrations') continue;
            $columns=$this->columns($table);
            $observed[$table]=$columns;
            foreach($observed[$table] as $column=>&$metadata) {
                if(preg_match('/pass|secret|activation_key|token/i',$column)) $metadata['column_default']='[redacted]';
            }
            unset($metadata);
            if(!$columns) throw new RuntimeException('Missing required gallery table: '.$table);
            preg_match_all('/^\s*"?([a-z_]+)"?\s+(?:varchar|text|longtext|int|smallint|tinyint|serial|timestamp|datetime|char|inet)\b/mi',$definition,$matches);
            foreach($matches[1] as $column) {
                if(!isset($columns[$column]) && !($table==='config' && in_array($column,['schema_version','cdn_url','show_exif'],true))) {
                    throw new RuntimeException('Unsupported/incomplete schema: missing '.$table.'.'.$column.'. Restore/review on staging; no repair was attempted.');
                }
            }
            foreach($columns as $column=>$metadata) {
                if(!preg_match('/^\s*"?'.preg_quote($column,'/').'"?\s+(varchar|text|longtext|char|inet|serial|int|smallint|tinyint|timestamp|datetime)\b/mi',$definition,$type)) continue;
                $expected=strtolower($type[1]); $actual=$metadata['data_type'];
                if(in_array($expected,['serial','int','smallint','tinyint'],true)) $valid=in_array($actual,['int','integer','smallint','tinyint','bigint','mediumint'],true);
                elseif(in_array($expected,['timestamp','datetime'],true)) $valid=in_array($actual,['timestamp','datetime','timestamp without time zone'],true);
                elseif($expected==='inet') $valid=$actual==='inet';
                else $valid=in_array($actual,['varchar','char','text','longtext','mediumtext','tinytext','character','character varying'],true);
                if(!$valid) throw new RuntimeException('Unsupported column type: '.$table.'.'.$column.'. No coercion was attempted.');
            }
            $primary=$this->query('SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = '
                .($this->driver==='mysql'?'DATABASE()':'current_schema()').' AND table_name = ? AND constraint_type = ?',[$this->prefix.$table,'PRIMARY KEY'])->fetchColumn();
            if((int)$primary!==1) throw new RuntimeException('Missing primary key on '.$table.'; review schema integrity manually.');
        }
        $date=$this->columns('pictures')['exif_date_taken']['data_type'];
        if(!in_array($date,['timestamp','timestamp without time zone','datetime'],true)) {
            throw new RuntimeException('Legacy EXIF date text requires a reviewed data conversion on staging; no column is dropped or coerced.');
        }
        $config=$this->config(); $version=$this->version();
        if(isset($this->columns('config')['schema_version'])
            && !in_array($this->columns('config')['schema_version']['data_type'],['int','integer','smallint','bigint'],true)) throw new RuntimeException('Unsupported schema_version column type.');
        $this->validateHistory($version);
        $source=$version>0?'reloaded':(isset($config['cdn_url'])?'php7/php8-compatible':'original/RC1-compatible');
        $theme=$config['theme_dir'];
        if(!is_string($theme) || !plogger_safe_relative_path($theme) || str_contains($theme,'/')) throw new RuntimeException('Unsafe active theme directory.');
        $themePath=$this->root.'/plog-content/themes/'.$theme;
        if(!plogger_path_within($themePath,$this->root.'/plog-content/themes')) throw new RuntimeException('Active theme is missing or outside the gallery.');
        $themeReport=plogger_check_theme($themePath);
        $samples=[];
        foreach($this->query('SELECT "path" FROM '.$this->table('pictures').' ORDER BY "id" LIMIT 20')->fetchAll() as $picture) {
            $relative=$picture['path']; $full=$this->root.'/plog-content/images/'.$relative;
            $samples[]=['path'=>$relative,'readable'=>is_string($relative) && plogger_safe_relative_path($relative)
                && plogger_path_within($full,$this->root.'/plog-content/images') && is_readable($full)];
        }
        $pending=[];
        foreach($this->migrations() as $migration) if($migration['version']>$version) $pending[]=$migration['description'];
        return ['source'=>$source,'schema_version'=>$version,'target_version'=>self::VERSION,'pending'=>$pending,'schema'=>$observed,
            'snapshot'=>$this->snapshot(),'theme'=>$theme,'theme_check'=>$themeReport,'original_samples'=>$samples,
            'free_bytes'=>disk_free_space($this->root),'indexes'=>$this->indexes(),'missing_indexes'=>$this->missingIndexes(),
            'privileges'=>'Read access verified. ALTER/CREATE/INSERT/UPDATE must be granted; no write probe is run in dry-run.'];
    }

    /** @return array<array-key, mixed> */

    public function indexes(): array {
        $tables=array_map(fn($name)=>$this->prefix.$name,['config','collections','albums','pictures','comments','thumbnail_config','schema_migrations']);
        $placeholders=implode(',',array_fill(0,count($tables),'?'));
        if($this->driver==='pgsql') return $this->query('SELECT tablename, indexname, indexdef FROM pg_indexes WHERE schemaname = current_schema() AND tablename IN ('.$placeholders.')',$tables)->fetchAll();
        return $this->query('SELECT table_name, index_name, column_name, non_unique, seq_in_index FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name IN ('.$placeholders.')',$tables)->fetchAll();
    }

    /** @return list<array{table:string,column:string,name:string}> */
    public function missingIndexes(): array {
        $missing=[]; $indexes=$this->indexes();
        foreach(plogger_schema_indexes() as $table=>$columns) foreach($columns as $column) {
            $found=false; $name=$this->prefix.$table.'_'.$column.'_idx'; plogger_db_identifier($name);
            foreach($indexes as $index) {
                if(($index['tablename'] ?? $index['table_name'])!==$this->prefix.$table) continue;
                if($this->driver==='mysql') $found=$found || ($index['column_name']===$column && (int)$index['seq_in_index']===1);
                else $found=$found || (bool)preg_match('/\(\s*"?'.preg_quote($column,'/').'"?\s*(?:,|\))/',$index['indexdef']);
            }
            if(!$found) {
                $collision=$this->driver==='pgsql'
                    ? $this->query('SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND indexname = ?',[$name])->fetchColumn()
                    : $this->query('SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',[$this->prefix.$table,$name])->fetchColumn();
                if($collision!==false) throw new RuntimeException('Conflicting index name. Review indexes manually; none were replaced.');
                $missing[]=['table'=>$table,'column'=>$column,'name'=>$name];
            }
        }
        return $missing;
    }

    public function ensureIndexes(): void {
        foreach($this->missingIndexes() as $index) $this->query('CREATE INDEX '.plogger_db_identifier($index['name']).' ON '
            .$this->table($index['table']).' ('.plogger_db_identifier($index['column']).')');
    }

    private function validateHistory(int $version): void {
        if($version===0) return;
        if(!$this->columns('schema_migrations')) throw new RuntimeException('Schema version has no migration history. Stop and review the interrupted upgrade.');
        foreach($this->migrations() as $migration) if($migration['version']<=$version) {
            $checksum=$this->query('SELECT "checksum" FROM '.$this->table('schema_migrations').' WHERE "version" = ?',[$migration['version']])->fetchColumn();
            if(!is_string($checksum) || !hash_equals($migration['checksum'],$checksum)) throw new RuntimeException('Migration history checksum mismatch. Restore matching code or review on staging.');
        }
    }

    public function ensurePasswordCapacity(): void {
        $column=$this->columns('config')['admin_password'];
        if($column['character_maximum_length']!==null && (int)$column['character_maximum_length']<64) {
            $this->query('ALTER TABLE '.$this->table('config').($this->driver==='mysql'
                ? ' MODIFY COLUMN "admin_password" VARCHAR(64) NOT NULL' : ' ALTER COLUMN "admin_password" TYPE VARCHAR(64)'));
        }
    }

    public function ensureColumn(string $column, string $type): void {
        if(!in_array($column,['schema_version','cdn_url','show_exif'],true)
            || !in_array($type,["INTEGER NOT NULL DEFAULT 0","VARCHAR(255) NOT NULL DEFAULT ''","SMALLINT DEFAULT 1"],true)) throw new RuntimeException('Unknown migration column.');
        if(!isset($this->columns('config')[$column])) $this->query('ALTER TABLE '.$this->table('config').' ADD COLUMN '.plogger_db_identifier($column).' '.$type);
    }

    public function ensureTracking(): void {
        $this->ensureColumn('schema_version','INTEGER NOT NULL DEFAULT 0');
        if(!$this->columns('schema_migrations')) $this->query('CREATE TABLE '.$this->table('schema_migrations')
            .' ('.plogger_schema_tables($this->driver)['schema_migrations'][0].')');
    }

    public function initializeFresh(): void {
        if($this->version()!==0) return;
        foreach(['collections','albums','pictures','comments'] as $table) {
            if((int)$this->query('SELECT COUNT(*) FROM '.$this->table($table))->fetchColumn()!==0) throw new RuntimeException('Fresh-install initialization refuses an existing gallery.');
        }
        $this->ensureTracking();
        foreach($this->migrations() as $migration) {
            $this->query('INSERT INTO '.$this->table('schema_migrations').' ("version","checksum","evidence","applied_at") VALUES (?, ?, ?, CURRENT_TIMESTAMP)',
                [$migration['version'],$migration['checksum'],'{"source":"fresh-install"}']);
        }
        $this->query('UPDATE '.$this->table('config').' SET "schema_version" = ?',[self::VERSION]);
    }

    /** @return array<array-key, mixed> */

    public function migrations(): array {
        $migrations=[];
        foreach(['0001_tracking.php','0002_configuration.php','0003_finalize.php'] as $name) {
            $path=dirname(__DIR__).'/plog-admin/migrations/'.$name;
            $migration=require $path;
            $migration['checksum']=self::migrationChecksum($path);
            $migrations[]=$migration;
        }
        return $migrations;
    }

    public static function migrationChecksum(string $path): string {
        $source=file_get_contents($path);
        if($source===false) throw new RuntimeException('Migration source is unavailable.');
        // Git may check out CRLF on Windows and LF on the deployment host.
        return hash('sha256',str_replace(["\r\n","\r"],"\n",$source));
    }

    /** Return after each numbered step for bounded web progress and restartability. */
    /** @param array<array-key, mixed> $backup
     * @return array<array-key, mixed> */
    public function apply(array $backup, bool $confirmed, ?string $reviewedPlan=null, int $steps=1): array {
        if(!$confirmed || !is_string($backup['database'] ?? null) || trim($backup['database'])===''
            || !is_string($backup['files'] ?? null) || trim($backup['files'])==='' || ($backup['restore_tested'] ?? null)!==true) {
            throw new RuntimeException('Confirm verified external database and originals/config backups and a tested restore before applying.');
        }
        $database=$this->query($this->driver==='mysql'?'SELECT DATABASE()':'SELECT current_database() || current_schema()')->fetchColumn();
        $lock='plogger-upgrade-'.substr(hash('sha256',$database.'|'.$this->prefix),0,32);
        $locked=$this->driver==='mysql'
            ? (int)$this->query('SELECT GET_LOCK(?, 0)',[$lock])->fetchColumn()===1
            : $this->query('SELECT pg_try_advisory_lock(hashtext(?))',[$lock])->fetchColumn();
        if(!$locked) throw new RuntimeException('Another upgrade is running.');
        try {
            $report=$this->inspect();
            if($reviewedPlan!==null && !hash_equals($reviewedPlan,self::planHash($report))) throw new RuntimeException('Installation changed since preview; run dry-run again.');
            foreach($report['original_samples'] as $sample) if(!$sample['readable']) throw new RuntimeException('An original image sample is missing/unreadable; review the staging copy first.');
            if($report['theme_check']['errors']) throw new RuntimeException('Active theme has syntax errors; review the theme before upgrading.');
            $before=$report['snapshot']; $performed=0;
            foreach($this->migrations() as $migration) {
                if($migration['version']<=$this->version()) {
                    $row=$this->query('SELECT "checksum" FROM '.$this->table('schema_migrations').' WHERE "version" = ?',[$migration['version']])->fetchColumn();
                    if(!is_string($row) || !hash_equals($migration['checksum'],$row)) throw new RuntimeException('Migration history is missing or differs from application code.');
                    continue;
                }
                ($migration['apply'])($this);
                if($before!==$this->snapshot()) throw new RuntimeException('Integrity check failed. Stop and restore the verified backup on staging.');
                $exists=$this->query('SELECT "checksum" FROM '.$this->table('schema_migrations').' WHERE "version" = ?',[$migration['version']])->fetchColumn();
                if($exists===false) $this->query('INSERT INTO '.$this->table('schema_migrations').' ("version","checksum","evidence","applied_at") VALUES (?, ?, ?, CURRENT_TIMESTAMP)',
                [$migration['version'],$migration['checksum'],json_encode(['source'=>$report['source'],'schema_before'=>$report['schema'],'before'=>$before,'backup_attestation'=>[
                    'database_reference_hash'=>hash('sha256',$backup['database']),'files_reference_hash'=>hash('sha256',$backup['files']),
                    'restore_tested'=>true]],JSON_THROW_ON_ERROR)]);
                elseif(!hash_equals($migration['checksum'],$exists)) throw new RuntimeException('Migration history checksum mismatch.');
                $this->query('UPDATE '.$this->table('config').' SET "schema_version" = ? WHERE "schema_version" = ?',[$migration['version'],$migration['version']-1]);
                if($this->version()!==$migration['version']) throw new RuntimeException('Cannot record migration version.');
                if(++$performed>=max(1,min(3,$steps))) break;
            }
            return $this->inspect();
        } finally {
            $this->query($this->driver==='mysql'?'SELECT RELEASE_LOCK(?)':'SELECT pg_advisory_unlock(hashtext(?))',[$lock]);
        }
    }

    /** @param array<array-key, mixed> $report */

    public static function planHash(array $report): string {
        return hash('sha256',serialize([$report['schema_version'],$report['snapshot'],$report['schema'],$report['theme'],$report['theme_check'],$report['pending'],$report['missing_indexes']]));
    }
}
