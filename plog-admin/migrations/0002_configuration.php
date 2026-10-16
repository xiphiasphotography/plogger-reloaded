<?php
return ['version'=>2,'description'=>'0002: Add missing CDN/EXIF settings and widen short password storage without changing credentials.',
    'apply'=>static function (PloggerUpgrade $upgrade): void {
        $upgrade->ensureColumn('cdn_url',"VARCHAR(255) NOT NULL DEFAULT ''");
        $upgrade->ensureColumn('show_exif','SMALLINT DEFAULT 1');
        $upgrade->ensurePasswordCapacity();
    }];
