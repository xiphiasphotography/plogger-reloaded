<?php
/*
This file will load all the configuration elements from the database
and place them into a global associative array called $config

SVN keyword tags:
$LastChangedDate$ (Date)
$LastChangedRevision$ (Revision)
$LastChangedBy$ (Author)
$HeadURL$ (URL)
$Id$

Note that SVN keywords are only propset enabled for plog-load-config.php!
Variables enabled: LastChangedDate LastChangedRevision LastChangedBy HeadURL Id
*/

// Retain the legacy entry point while using the shared configuration loader.
require_once dirname(__DIR__).'/plog-load-config.php';
