<?php
return ['version'=>3,'description'=>'0003: Verify Reloaded schema compatibility and preserve existing content and credentials.',
    'apply'=>static function (PloggerUpgrade $upgrade): void { $upgrade->ensureIndexes(); $upgrade->inspect(); }];
