<?php
return ['version'=>1,'description'=>'0001: Add schema version and migration evidence ledger.',
    'apply'=>static function (PloggerUpgrade $upgrade): void { $upgrade->ensureTracking(); }];
