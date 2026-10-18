<?php
return ['version'=>4, 'description'=>'0004: Add nullable optional album provider ID and original photograph pixel dimensions; originals are never modified.',
    'apply'=>static function (PloggerUpgrade $upgrade): void { $upgrade->ensurePhotographActions(); }];
