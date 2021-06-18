<?php
$version = '1.0.0';
define("IN_SCRIPT", true);

if (!extension_loaded('mcrypt')) {
    exit("extension needed, please install 'mcrypt'");
}
if (!extension_loaded('mbstring')) {
    exit("extension needed, please install 'mbstring'");
}

require_once 'conf/config.php';
require_once 'libs/functions.php';
require_once 'libs/easybitcoin.php';

//mysql
// print_r($_SERVER);
// echo $_SERVER['DOCUMENT_ROOT'];
// echo (__FILE__);
// echo dirname(__FILE__);
// echo dirname(dirname(__FILE__));
// print_r($mysql_conf);
$mysqli = new mysqli($config['mysql_host'] . ":" . $config['mysql_port'], $config['mysql_username'], $config['mysql_password']);
if ($mysqli->connect_errno) {
    die("could not connect to the database:\n" . $mysqli->connect_error);
}
// $mysqli->query("set names 'utf8mb4';");
// $mysqli->query("SET time_zone = '-00:00';");
$select_db = $mysqli->select_db($config['mysql_database']);
if (!$select_db) {
    die("could not connect to the db:\n" . $mysqli->error);
}
//connect ok
$tblprefix = $config['mysql_table_prefix'];

//redis
$redis = new Redis();
$redis->connect($config['REDIS_HOST'], $config['REDIS_PORT']) or die("could not connect redis server");
$redis->auth($config['REDIS_AUTH']);
$redis->select($config['REDIS_DB_ID']);

$bitcoinrpc = new Bitcoin($config["rpc_user"], $config["rpc_password"], $config["rpc_host"], $config["rpc_port"]);

//init url path prefix
if ($config["url_rewrite"]) {
    $name = isset($_GET['name']) ? $_GET['name'] : "";
    list($url_param_get_action, $url_param_get_value) = explode("/", $name);
    $url_path["height"] = $config["pool_homepage"] . $config["root_path"] . $config["pool_path"] . 'height/';
    $url_path["blockhash"] = $config["pool_homepage"] . $config["root_path"] . $config["pool_path"] . 'blockhash/';
    $url_path["tx"] = $config["pool_homepage"] . $config["root_path"] . $config["pool_path"] . 'tx/';
    $url_path["block"] = $config["pool_homepage"] . $config["root_path"] . $config["pool_path"] . 'block/';
    $url_path["search"] = $config["pool_homepage"] . $config["root_path"] . $config["pool_path"] . 'search/';
    $url_path["getting-started"] = $config["pool_homepage"] . $config["root_path"] . $config["pool_path"] . 'getting-started/';
    $url_path["mining-software"] = $config["pool_homepage"] . $config["root_path"] . $config["pool_path"] . 'mining-software/';
    $url_path["faq"] = $config["pool_homepage"] . $config["root_path"] . $config["pool_path"] . 'faq/';
    $url_path["terms-of-service"] = $config["pool_homepage"] . $config["root_path"] . $config["pool_path"] . 'terms-of-service/';
    $url_path["pool_homepage"] = $config["pool_homepage"] . $config["root_path"] . $config["pool_path"];
} else {
    $url_param_get_action = isset($_GET['action']) ? $_GET['action'] : "";
    $url_param_get_value = isset($_GET['v']) ? $_GET['v'] : "";
    $url_path["height"] = $config["pool_homepage"] . $config["root_path"] . $config["pool_path"] . '?action=height&v=';
    $url_path["blockhash"] = $config["pool_homepage"] . $config["root_path"] . $config["pool_path"] . '?action=blockhash&v=';
    $url_path["tx"] = $config["pool_homepage"] . $config["root_path"] . $config["pool_path"] . '?action=tx&v=';
    $url_path["block"] = $config["pool_homepage"] . $config["root_path"] . $config["pool_path"] . '?action=block&v=';
    $url_path["search"] = $config["pool_homepage"] . $config["root_path"] . $config["pool_path"] . '?action=search&v=';
    $url_path["getting-started"] = $config["pool_homepage"] . $config["root_path"] . $config["pool_path"] . '?action=getting-started';
    $url_path["mining-software"] = $config["pool_homepage"] . $config["root_path"] . $config["pool_path"] . '?action=mining-software';
    $url_path["faq"] = $config["pool_homepage"] . $config["root_path"] . $config["pool_path"] . '?action=faq';
    $url_path["terms-of-service"] = $config["pool_homepage"] . $config["root_path"] . $config["pool_path"] . '?action=terms-of-service';
    $url_path["pool_homepage"] = $config["pool_homepage"] . $config["root_path"] . $config["pool_path"];
}

switch ($url_param_get_action) {
    case "":
        $sql = "SELECT count(*) as cnt
                FROM `{$tblprefix}account`
                ;
        ";
        $rs_height = $mysqli->query($sql);
        if ($rs_height) {
            $row = $rs_height->fetch_assoc();
            $rs_height->free();
            $output['works'] = $row['cnt'];
        } else {
            $output['works'] = 0;
        }

        $sql = "SELECT count(*) as cnt
                FROM `{$tblprefix}block`
                ;
        ";
        $rs_height = $mysqli->query($sql);
        if ($rs_height) {
            $row = $rs_height->fetch_assoc();
            $rs_height->free();
            $output['total'] = $row['cnt'];
        } else {
            $output['total'] = 0;
        }

        $sql = "SELECT count(*) as cnt
                FROM `{$tblprefix}block`
                WHERE `Orphaned`=1
                ;
        ";
        $rs_height = $mysqli->query($sql);
        if ($rs_height) {
            $row = $rs_height->fetch_assoc();
            $rs_height->free();
            $output['orphaned'] = $row['cnt'];
        } else {
            $output['orphaned'] = 0;
        }

        $sql = "SELECT count(*) as cnt
                FROM `{$tblprefix}block`
                WHERE `Confirmed`=1
                ;
        ";
        $rs_height = $mysqli->query($sql);
        if ($rs_height) {
            $row = $rs_height->fetch_assoc();
            $rs_height->free();
            $output['confirmed'] = $row['cnt'];
        } else {
            $output['confirmed'] = 0;
        }

        // $confirmed = $output['total'] - $output['orphaned'] - 100;
        // $output['confirmed'] = $confirmed > 0 ? $confirmed : 0;

        $sql = "SELECT count(DISTINCT `Block`) as cnt
                FROM `{$tblprefix}payment`
                WHERE `Completed`=0
                ;
        ";
        $rs_height = $mysqli->query($sql);
        if ($rs_height) {
            $row = $rs_height->fetch_assoc();
            $rs_height->free();
            $output['pending'] = $row['cnt'];
        } else {
            $output['pending'] = 0;
        }

        $sql = "SELECT count(DISTINCT `Block`) as cnt
                FROM `{$tblprefix}payment`
                WHERE `Completed`=1
                ;
        ";
        $rs_height = $mysqli->query($sql);
        if ($rs_height) {
            $row = $rs_height->fetch_assoc();
            $rs_height->free();
            $output['paid'] = $row['cnt'];
        } else {
            $output['paid'] = 0;
        }

        $hashrates = $redis->zRangeByScore($config['REDIS_DB_PREFIX'] . "hashrate", time() - $config['hashrate_window'], time());
        // print_r($hashrates);
        $workhashrate = array();
        $totalhashrate = 0;
        foreach ($hashrates as $key => $value) {
            $data = explode(":", $value);
            $share = $data[0];
            $worker = $data[1];

            // if (!in_array($worker,$workhashrate)){
            // $workhashrate[$worker]=0;
            // }else{
            $workhashrate[$worker] += $share;
            $totalhashrate += $share;
            // }
        }
        // print_r($workhashrate);

        $output['hashrate'] = short_number(pow(2, 32) / 1 * $totalhashrate / $config['hashrate_window'], 1000, 3, " ") . "H/s";

        $sql = "SELECT `Height`, `Orphaned`, `Confirmed`, `Accounted`, `Amount`, `CreatedAt`
                FROM `{$tblprefix}block`
                ORDER BY `Height` DESC
                LIMIT 0, 5
                ;
        ";
        $rs_block = $mysqli->query($sql);
        $output['lastblock'] = '';
        $output['mined_block_list_tbody'] = '';
        if ($rs_block) {
            while ($block = $rs_block->fetch_assoc()) {
                if (!$output['lastblock']) {
                    $output['lastblock'] = $block['CreatedAt'];
                }
                $output['mined_block_list_tbody'] .= "<tr><td><a class=\"text-info\" href=\"" . $url_path["block"] . $block["Height"] . "\">" . $block["Height"] . "</a></td><td>" . ($block["Orphaned"] == 1 ? "Orphaned" : $block["Confirmed"] == 1 ? "Confirmed" : "Unconfirmed") . "</td><td>" . $block["CreatedAt"] . "</td><td>" . trim_dotzero($block["Amount"]) . "</td></tr>";
            }
            $rs_block->free();
        }
        if (!$output['mined_block_list_tbody']) {
            $output['mined_block_list_tbody'] = '<tr><td class="text-center" colspan="4">Block list is currently empty.</td></tr>';
        }

        $sql = "SELECT `Block`, `Completed`, SUM(`Amount`) AS amount, `CreatedAt`
                FROM `{$tblprefix}payment` AS p
                WHERE `Completed`=1
                GROUP BY `Block`
                ORDER BY `Id` DESC
                LIMIT 0, 5
                ;
        ";
        $rs_block = $mysqli->query($sql);
        $output['lastpayment'] = '';
        $output['paid_block_list_tbody'] = '';
        if ($rs_block) {
            while ($block = $rs_block->fetch_assoc()) {
                if (!$output['lastpayment']) {
                    $output['lastpayment'] = $block['status_changeat'];
                }
                $output['paid_block_list_tbody'] .= "<tr><td><a class=\"text-info\" href=\"" . $url_path["block"] . $block["Block"] . "\">" . $block["Block"] . "</a></td><td>" . ($block["Completed"] == 1 ? "Paid" : "Pending") . "</td><td>" . $block["CreatedAt"] . "</td><td>" . trim_dotzero($block["amount"]) . "</td></tr>";
            }
            $rs_block->free();
        }
        if (!$output['paid_block_list_tbody']) {
            $output['paid_block_list_tbody'] = '<tr><td class="text-center" colspan="4">Block list is currently empty.</td></tr>';
        }

        if ($config['energy_saving_mode']) {
            $output['energy_saving_mode'] = '&nbsp;&nbsp;&nbsp;&nbsp;<span class="px-2 py-1 bg-success text-white rounded-1">Energy Saving Mode</span>';
        } else {
            $output['energy_saving_mode'] = '';
        }

        $output["title"] = "";
        $output["description"] = $config["pool_name"] . " homepage.";

        exit(get_html("index-body", $output));
        break;
    case "block":
        $height = (int) $url_param_get_value;
        if ($height < 0) {
            send404();
        }
        $where = "WHERE b.Height=$height";
    case "blockhash":
        if (!$where) {
            $blockhash = $url_param_get_value;
            if (!$blockhash || !preg_match('/^[0-9a-f]{64}$/i', $blockhash)) {
                send404();
            }
            $where = " WHERE b.BlockHash='$blockhash'";
        }
    case "tx":
        if (!$where) {
            $txid = $url_param_get_value;
            if (!$txid || !preg_match('/^[0-9a-f]{64}$/i', $txid)) {
                send404();
            }
            $where = " WHERE b.TxHash='$txid'";
        }

        // $sql = "SELECT max(height) as maxheight
        //         FROM `{$tblprefix}block`
        //         ;
        // ";
        // $rs_height = $mysqli->query($sql);
        // $row = $rs_height->fetch_assoc();
        // $rs_height->free();
        // $row['maxheight'];
        // if ($height > $row['maxheight']) {
        //     send404();
        // }

        $sql = "SELECT b.`Height`, `Orphaned`, `Confirmed`, `Accounted`, `BlockHash`, `TxHash`, b.`Amount`, `Reward`, b.`CreatedAt` AS block_createdat,
                       `Completed`, p.`CreatedAt` AS payment_createdat, `status`
                FROM `{$tblprefix}block` AS b
                LEFT JOIN `{$tblprefix}payment` AS p ON p.Block=b.Height
                $where
                ;
        ";
        $rs_block = $mysqli->query($sql);
        // echo $mysqli->error;

        $block = $rs_block->fetch_assoc();
        $rs_block->free();
        if (!$block) {
            send404();
        }

        // $block['maxheight'] = $row['maxheight'];

        $output += get_output_from_block($block);

        exit(get_html("block-body", $output));
        break;
    case "search":
        $search = $url_param_get_value;

        if (preg_match('/^[0-9]{1,6}$/i', $search)) {
            $output["search_result"] = 'Search Block with Height<br><a class="text-info" href="' . $url_path["block"] . $search . '">' . $search . '</a>';
        } else if (preg_match('/^[0-9a-f]{64}$/i', $search)) {
            $output["search_result"] = 'Search Block with Hash<br><a class="text-info" href="' . $url_path["blockhash"] . $search . '">' . $search . '</a>';
            $output["search_result"] .= '<br><br>';
            $output["search_result"] .= 'Search txid<br><a class="text-info" href="' . $url_path["tx"] . $search . '">' . $search . '</a>';
        } else {
            $output["search_result"] = 'Search for some valid data';
        }
        $output["title"] = "Search result for " . $search . " - ";
        $output["description"] = "Search result for " . $search;

        exit(get_html("search-body", $output));
        break;
    case "getting-started":
        $output["title"] = "Getting Started - ";
        $output["description"] = "Getting Started page of " . $config["pool_name"] . ".";
        exit(get_html("getting-started-body", $output));
        break;
    case "mining-software":
        $software = loadfile("conf/software.json");
        $software = json_decode($software, true);
        // print_r($software);
        foreach ($software['miner'] as $key => $value) {
            $output['mining_software_list_tbody'] .= "<tr>";
            $output['mining_software_list_tbody'] .= "<td>" . $value["name"] . "</td>";
            $output['mining_software_list_tbody'] .= "<td>" . $value["version"] . "</td>";
            if (in_array("cpu", $value['platforms'])) {
                $output['mining_software_list_tbody'] .= "<td>✓</td>";
            } else {
                $output['mining_software_list_tbody'] .= "<td></td>";
            }
            if (in_array("ati", $value['platforms'])) {
                $output['mining_software_list_tbody'] .= "<td>✓</td>";
            } else {
                $output['mining_software_list_tbody'] .= "<td></td>";
            }
            if (in_array("nvidia", $value['platforms'])) {
                $output['mining_software_list_tbody'] .= "<td>✓</td>";
            } else {
                $output['mining_software_list_tbody'] .= "<td></td>";
            }
            if (in_array("asic", $value['platforms'])) {
                $output['mining_software_list_tbody'] .= "<td>✓</td>";
            } else {
                $output['mining_software_list_tbody'] .= "<td></td>";
            }
            $output['mining_software_list_tbody'] .= "<td>" . implode(", ", $value["algorithms"]) . "</td>";
            $output['mining_software_list_tbody'] .= "<td><a class=\"text-info\" href=\"" . $value["site"] . "\" target=\"_blank\"><i class=\"fa fa-globe\"></i></a></td>";
            if (isset($value['download']['windows'])) {
                $output['mining_software_list_tbody'] .= "<td><a class=\"text-info\" href=\"" . $value['download']['windows'] . "\" target=\"_blank\"><i class=\"fa fa-download\"></i></a></td>";
            } else {
                $output['mining_software_list_tbody'] .= "<td></td>";
            }
            if (isset($value['download']['linux'])) {
                $output['mining_software_list_tbody'] .= "<td><a class=\"text-info\" href=\"" . $value['download']['linux'] . "\" target=\"_blank\"><i class=\"fa fa-download\"></i></a></td>";
            } else {
                $output['mining_software_list_tbody'] .= "<td></td>";
            }
            if (isset($value['download']['macos'])) {
                $output['mining_software_list_tbody'] .= "<td><a class=\"text-info\" href=\"" . $value['download']['macos'] . "\" target=\"_blank\"><i class=\"fa fa-download\"></i></a></td>";
            } else {
                $output['mining_software_list_tbody'] .= "<td></td>";
            }
        }
        $output["title"] = "Mining Software - ";
        $output["description"] = "Mining Software page of " . $config["pool_name"] . ".";
        exit(get_html("mining-software-body", $output));
        break;
    case "faq":
        $output["title"] = "FAQ - ";
        $output["description"] = "Frequently Asked Questions page of " . $config["pool_name"] . ".";
        exit(get_html("faq-body", $output));
        break;
    case "terms-of-service":
        $output["title"] = "Terms of Service - ";
        $output["description"] = "Terms of Service page of " . $config["pool_name"] . ".";
        exit(get_html("terms-of-service-body", $output));
        break;
    default:
        send404();
        break;
}

function get_output_from_block($block)
{
    global $config, $url_path;
    $output = array();
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\" style=\"width:30%\">Height</th><td class=\"text-start\">" . $block["height"] . "</td></tr>";
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">BlockHash</th><td class=\"text-start\"><!--<a class=\"text-info\" href=\"" . $config["explorer_url_blockhash"] . $block["blockhash"] . "\" target=\"_blank\">-->" . $block["blockhash"] . "<!--</a>--></td></tr>";
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">TxID</th><td class=\"text-start\"><!--<a class=\"text-info\" href=\"" . $config["explorer_url_tx"] . $block["txid"] . "\" target=\"_blank\">-->" . $block["txid"] . "<!--</a>--></td></tr>";
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Block Time</th><td class=\"text-start\">" . $block["block_createdat"] . " UTC</td></tr>";
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Amount</th><td class=\"text-start\">" . $block["amount"] . "</td></tr>";
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Reward</th><td class=\"text-start\">" . $block["reward"] . "</td></tr>";
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Status</th><td class=\"text-start\">" . ($block["orphaned"] == 1 ? "Orphaned" : $block["confirmed"] == 1 ? "Confirmed" : "Pending") . "</td></tr>";
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Payment Time</th><td class=\"text-start\">" . ($block["payment_createdat"] ? $block["payment_createdat"] . " UTC" : "") . "</td></tr>";
    if (isset($block['status']) && $block['status'] == 1) {
        $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Payment Status</th><td class=\"text-start\">Fullfilled</td></tr>";
    } else if (isset($block['status']) && $block['status'] == 2) {
        $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Payment Status</th><td class=\"text-start\">Paid</td></tr>";
    } else if (isset($block['completed']) && $block['completed'] == 1) {
        $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Payment Status</th><td class=\"text-start\">Paid</td></tr>";
    } else {
        $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Payment Status</th><td class=\"text-start\">Pending</td></tr>";
    }
    // $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Fullfilled Time</th><td class=\"text-start\">" . $block["transaction_createdat"] . " UTC</td></tr>";
    // $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Fullfilled Status</th><td class=\"text-start\">" . ($block['Completed'] ? '<span class="px-2 py-1 bg-success text-white rounded-1">Yes</span>' : '<span class="px-2 py-1 bg-warning text-white rounded-1">No</span>') . "</td></tr>";

    $output["height"] = $block["height"];
    $output["title"] = $block["height"] . " Block Detail - ";
    $output["description"] = "This block's height is " . $block["height"] . ", and the block hash is " . $block["blockhash"] . ". It was mined at " . $block["createdat"] . " UTC.";

    return $output;
}
function send404()
{
    global $config;
    // header('HTTP/1.1 404 Not Found');
    // header("status: 404 Not Found");
    http_response_code(404);
    $output["title"] = "Oops! 404 Not Found - ";
    $output["description"] = "Oops! 404 Not Found";

    exit(get_html("404", $output));
}

function html_replace_common($html)
{
    global $config, $version, $url_path;
    $common["name"] = $config["name"];
    $common["currency"] = $config["name"];
    $common["symbol"] = $config["symbol"];
    $common["algorithm"] = $config["algorithm"];
    $common["pool_name"] = $config["pool_name"];
    $common["pool_path"] = $config["pool_path"];
    $common["theme_path"] = $config["root_path"] . $config["pool_path"] . "themes/" . $config["theme"] . "/";
    $common["homepage"] = $config["homepage"];
    $common["root_path"] = $config["root_path"];
    $common["copy_name"] = $config["copy_name"];
    $common["start_year"] = $config["start_year"];
    $common["year"] = date("Y", time());
    $common["version"] = $version;
    $common["search_url"] = $url_path["search"];
    $common["ajax_url"] = $config["root_path"] . $config["pool_path"] . "ajax.php";
    $common["pool_url_tx"] = $url_path["tx"];
    $common["user_center_url"] = $config["user_center_url"];
    $common["explorer_url_blockhash"] = $config["explorer_url_blockhash"];
    $common["explorer_url_tx"] = $config["explorer_url_tx"];
    $common["pool_url"] = $config["pool_url"];
    $common["community_url"] = $config["community_url"];
    $common["explorer_url"] = $config["explorer_url"];
    $common["admin_urls"] = $url_path["admin_urls"];

    return html_replace($html, $common);
}

function html_replace($html, $output)
{
    $keys = array();
    foreach ($output as $key => $value) {
        $keys[] = '{$' . $key . '}';
    }
    return str_replace(
        $keys,
        array_values($output),
        $html);
}

function get_html($filename, $output)
{
    global $config;
    $header = loadfile("themes/" . $config["theme"] . "/tpl/header.html");
    $body = loadfile("themes/" . $config["theme"] . "/tpl/" . $filename . ".html");
    $footer = loadfile("themes/" . $config["theme"] . "/tpl/footer.html");
    $html = $header . $body . $footer;
    $html = html_replace_common($html);
    $html = html_replace($html, $output);
    // return $html;
    return clean_html($html);
}
exit;
