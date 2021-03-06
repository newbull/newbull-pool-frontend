<?php
if (!defined('IN_SCRIPT')) {die('Invalid attempt!');}
$config = array(
    "name" => "NewBull", // Coin name/title
    "symbol" => "NB", // Coin symbol
    "algorithm" => "sha256d", // Coin algorithm
    "description" => "You could browse the NewBull block detail and transaction detail with NewBull Block Explorer.",
    "homepage" => "https://newbull.org/",
    "pool_homepage" => "http://127.0.0.1:8019", //do not end with '/'
    "root_path" => "/", //start with '/', end with '/'
    "copy_name" => "newbull.org",
    "start_year" => 2016,
    "pool_name" => "NewBull Pool",
    "pool_path" => "pool-1.0.0/", //do not start with '/',  but end with '/', if root write ""
    "theme" => "theme1",
    "url_rewrite" => true,
    "rpc_host" => "127.0.0.1", // Host/IP for the daemon
    "rpc_port" => 10102, // RPC port for the daemon
    "rpc_user" => "newbullrpc", // 'rpcuser' from the coin's .conf
    "rpc_password" => "newbullpassword", // 'rpcpassword' from the coin's .conf
    "proofof" => "pow", //pow,pos
    "total_amount" => 2100000000000,
    "block_reward" => 128000,
    "genesis_block_timestamp" => 1466861400,
    "nTargetTimespan" => 1209600, //14 * 24 * 60 * 60
    "nTargetSpacing" => 180, //3 * 60
    "retarget_diff_since" => 0,
    "blocks_per_page" => 10,
    "date_format" => "Y-m-d H:i:s",
    "refresh_interval" => 180, //seconds
    "mysql_host" => "localhost",
    "mysql_port" => "3306",
    "mysql_database" => "pool",
    "mysql_username" => "root",
    "mysql_password" => "",
    "mysql_table_prefix" => "",
    "hashrate_window" => 300,
    'REDIS_HOST' => '127.0.0.1',
    'REDIS_PORT' => '6379',
    'REDIS_AUTH' => '',
    "REDIS_DB_ID" => 0,
    'REDIS_DB_PREFIX' => 'newbull:',
    "explorer_url_blockhash" => "https://explorer.newbull.org/blockhash/",
    "explorer_url_tx" => "https://explorer.newbull.org/tx/",
    "explorer_url" => "https://explorer.newbull.org/",
    "pool_url" => "https://pool.newbull.org/",
    "community_url" => "https://community.newbull.org/",
    "energy_saving_mode" => true,
);
