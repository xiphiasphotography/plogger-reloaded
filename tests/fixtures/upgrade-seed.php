<?php
/** Only called after the isolated test harness validates generated database/root names. */
function plogger_seed_upgrade_fixture(PDO $db, string $driver, string $prefix, string $source, string $galleryUrl): void {
    if(!in_array($source,['rc1','php7','php8'],true)) throw new RuntimeException('Unknown synthetic source.');
    $manifest=json_decode(file_get_contents(__DIR__.'/upgrades/'.$source.'.json'),true,512,JSON_THROW_ON_ERROR);
    $sql=str_replace('{prefix}',plogger_db_prefix($prefix),file_get_contents(__DIR__.'/upgrades/'.$manifest[$driver]));
    foreach(explode(';',$sql) as $statement) if(trim($statement)!=='') plogger_db_execute($db,$statement);
    $columns=plogger_db_execute($db,'SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = '
        .($driver==='mysql'?'DATABASE()':'current_schema()').' AND table_name = ?',[$prefix.'config'])->fetchAll();
    $values=[];
    foreach($columns as $column) $values[$column['column_name']]=preg_match('/int/',$column['data_type'])?0:'';
    $values=array_replace($values,['gallery_name'=>'Synthetic gallery','gallery_url'=>$galleryUrl,'admin_username'=>'fixture',
        'admin_password'=>md5('fixture-password'),'admin_email'=>'fixture@example.invalid','theme_dir'=>'default',
        'thumb_num'=>20,'compression'=>75,'default_sortby'=>'date','default_sortdir'=>'DESC',
        'album_sortby'=>'alpha','album_sortdir'=>'ASC','collection_sortby'=>'alpha','collection_sortdir'=>'ASC',
        'allow_dl'=>1,'allow_fullpic'=>1,'allow_comments'=>1,'allow_print'=>1,'feed_num_entries'=>15,
        'feed_title'=>'Fixture feed','feed_content'=>1,'thumb_nav_range'=>0,'date_format'=>'n.j.Y']);
    $insert=static function(string $table,array $row) use($db,$prefix): void {
        plogger_db_execute($db,'INSERT INTO '.plogger_db_identifier($prefix.$table).' ('.implode(',',array_map('plogger_db_identifier',array_keys($row))).') VALUES ('
            .implode(',',array_fill(0,count($row),'?')).')',array_values($row));
    };
    $insert('config',$values);
    $insert('collections',['id'=>1,'name'=>'Fixture','description'=>'Synthetic collection','path'=>'fixture','thumbnail_id'=>1]);
    $insert('albums',['id'=>1,'parent_id'=>1,'name'=>'Album','description'=>"Album description\nSecond line",'path'=>'album','thumbnail_id'=>1]);
    $insert('pictures',['id'=>1,'parent_album'=>1,'parent_collection'=>1,'path'=>'fixture/album/photo.jpg','caption'=>'Fixture caption',
        'description'=>"Fixture description\nsecond line",'date_submitted'=>'2020-01-02 03:04:05','date_modified'=>'2020-01-02 03:04:05',
        'exif_date_taken'=>'2019-12-01 12:13:14','exif_camera'=>'Fixture Camera','exif_shutterspeed'=>'1/125 sec',
        'exif_focallength'=>'50 mm','exif_aperture'=>'f/2.8','exif_flash'=>'No Flash','exif_iso'=>'200','allow_comments'=>1]);
    foreach([1=>100,2=>500,3=>400,4=>60] as $id=>$size) $insert('thumbnail_config',
        ['id'=>$id,'update_timestamp'=>0,'max_size'=>$size,'disabled'=>0,'resize_option'=>($id===1||$id===4)?3:2]);
    if($driver==='pgsql') foreach(['collections','albums','pictures','thumbnail_config'] as $table) {
        plogger_db_execute($db,'SELECT setval(?, (SELECT MAX("id") FROM '.plogger_db_identifier($prefix.$table).'))',[$prefix.$table.'_id_seq']);
    }
}
