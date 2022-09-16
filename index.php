<?php

$execTime = $_GET['exec_time'] ?? 1;

sleep((int)$execTime);

echo "exec complete in {$execTime}s";


