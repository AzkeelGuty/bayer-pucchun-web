<?php
require dirname(__DIR__,2).'/config/bootstrap.php';
try { db()->query('SELECT 1'); echo "OK database\n"; } catch(Throwable $e) { echo "ERROR database: {$e->getMessage()}\n"; exit(1); }
