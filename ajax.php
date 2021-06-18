<?php
define("IN_SCRIPT", true);

require_once 'conf/config.php';
require_once 'libs/functions.php';
require_once 'libs/easybitcoin.php';

$mysqli = new mysqli($config['mysql_host'] . ":" . $config['mysql_port'], $config['mysql_username'], $config['mysql_password']);
if ($mysqli->connect_errno) {
    die("could not connect to the database:\n" . $mysqli->connect_error); //诊断连接错误
}
// $mysqli->query("set names 'utf8';"); //编码转化
// $mysqli->query("SET time_zone = '-08:00';"); //设置时区
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

//init url path prefix
if ($config["url_rewrite"]) {
    // $name = isset($_GET['name']) ? $_GET['name'] : "";
    // list($url_param_get_action, $url_param_get_value) = explode("/", $name);
    $url_path["height"] = $config["explorer_path"] . 'height/';
    $url_path["blockhash"] = $config["explorer_path"] . 'blockhash/';
    $url_path["tx"] = $config["explorer_path"] . 'tx/';
    $url_path["block"] = $config["explorer_path"] . 'block/';
    $url_path["search"] = $config["explorer_path"] . 'search/';
} else {
    // $url_param_get_action = isset($_GET['action']) ? $_GET['action'] : "";
    // $url_param_get_value = isset($_GET['v']) ? $_GET['v'] : "";
    $url_path["height"] = $config["explorer_path"] . '?action=height&v=';
    $url_path["blockhash"] = $config["explorer_path"] . '?action=blockhash&v=';
    $url_path["tx"] = $config["explorer_path"] . '?action=tx&v=';
    $url_path["block"] = $config["explorer_path"] . '?action=block&v=';
    $url_path["search"] = $config["explorer_path"] . '?action=search&v=';
}

$bitcoinrpc = new Bitcoin($config["rpc_user"], $config["rpc_password"], $config["rpc_host"], $config["rpc_port"]);

$action = isset($_POST['action']) ? $_POST['action'] : "";
switch ($action) {
    case "get_memory_pool":
        $getrawmempool = $bitcoinrpc->getrawmempool();
        if ($bitcoinrpc->status !== 200 && $bitcoinrpc->error !== '') {
            $response = array();
            $response['s'] = 0;
            $response['e'] = $bitcoinrpc->error;
            exit(json_encode($response));
        }

        foreach ($getrawmempool as $key => $tx) {
            $transaction_detail = array();
            $transaction_detail['tx'] = $tx;
            $rawtransaction = $bitcoinrpc->getrawtransaction($tx, 1);
            if ($rawtransaction === false) {
                continue;
            }

            foreach ($rawtransaction['vout'] as $vout) {
                if ($vout['value'] > 0.0) {
                    $transaction_detail['vout'][$vout['n']]['addresses'] = $vout['scriptPubKey']['addresses'];
                    $transaction_detail['vout'][$vout['n']]['value'] = trim_dotzero($vout['value']);
                }
            }

            $output['transactions'][] = $transaction_detail;
        }

        $response = array();
        $response['s'] = 1;
        $response['d'] = $output['transactions'];
        exit(json_encode($response));
    case "get_baseinfo":
        //baseinfo
        $mininginfo = $bitcoinrpc->getmininginfo();
        if ($bitcoinrpc->status !== 200 && $bitcoinrpc->error !== '') {
            $response = array();
            $response['s'] = 0;
            $response['e'] = $bitcoinrpc->error;
            exit(json_encode($response));
        }
        $getnetworkinfo = $bitcoinrpc->getnetworkinfo();
        if ($bitcoinrpc->status !== 200 && $bitcoinrpc->error !== '') {
            $response = array();
            $response['s'] = 0;
            $response['e'] = $bitcoinrpc->error;
            exit(json_encode($response));
        }

        $output['difficulty'] = number_format3($mininginfo['difficulty']);
        $output['hashrate'] = short_number($mininginfo['networkhashps'], 1000, 3, " ") . "H/s";
        $output['chain'] = $mininginfo['chain'];
        $output['blocks'] = $mininginfo['blocks'];
        $output['version'] = $getnetworkinfo['version'];
        $output['healthy'] = $getnetworkinfo['connections'] > 0 ? "Yes" : "No";
        $nTarget = $config["nTargetTimespan"] / $config["nTargetSpacing"];
        $output['nextdiff_blocks'] = $nTarget - ($mininginfo['blocks'] - $config["retarget_diff_since"]) % $nTarget;
        // $output['nextdiff_timeline'] = gmdate($config["date_format"], time() + $output['nextdiff_blocks'] * $config["nTargetSpacing"]);
        $output['nextdiff_timeline'] = $output['nextdiff_blocks'] * $config["nTargetSpacing"];

        $response = array();
        $response['s'] = 1;
        $response['d'] = $output;
        exit(json_encode($response));
        break;
    case "get_nextdiff":
        $last_block_height = $bitcoinrpc->getblockcount();
        if ($bitcoinrpc->status !== 200 && $bitcoinrpc->error) {
            $response = array();
            $response['s'] = 0;
            $response['e'] = $bitcoinrpc->error;
            exit(json_encode($response));
        }

        $first_block_height = $last_block_height - $config["nTargetTimespan"] / $config["nTargetSpacing"];

        $last_block_hash = $bitcoinrpc->getblockhash($last_block_height);
        if ($bitcoinrpc->status !== 200 && $bitcoinrpc->error) {
            $response = array();
            $response['s'] = 0;
            $response['e'] = $bitcoinrpc->error;
            exit(json_encode($response));
        }

        $first_block_hash = $bitcoinrpc->getblockhash($first_block_height);
        if ($bitcoinrpc->status !== 200 && $bitcoinrpc->error) {
            $response = array();
            $response['s'] = 0;
            $response['e'] = $bitcoinrpc->error;
            exit(json_encode($response));
        }

        $last_block = $bitcoinrpc->getblock($last_block_hash);
        if ($bitcoinrpc->status !== 200 && $bitcoinrpc->error) {
            $response = array();
            $response['s'] = 0;
            $response['e'] = $bitcoinrpc->error;
            exit(json_encode($response));
        }

        $first_block = $bitcoinrpc->getblock($first_block_hash);
        if ($bitcoinrpc->status !== 200 && $bitcoinrpc->error) {
            $response = array();
            $response['s'] = 0;
            $response['e'] = $bitcoinrpc->error;
            exit(json_encode($response));
        }

        $nActualTimespan = $last_block["time"] - $first_block["time"];
        if ($nActualTimespan < $config["nTargetTimespan"] / 4) {
            $nActualTimespan = $config["nTargetTimespan"] / 4;
        }
        if ($nActualTimespan > $config["nTargetTimespan"] * 4) {
            $nActualTimespan = $config["nTargetTimespan"] * 4;
        }

        $bnNew = $last_block["difficulty"];
        $bnNew /= $nActualTimespan;
        $bnNew *= $config["nTargetTimespan"];

        $response = array();
        $response['s'] = 1;
        $response['d'] = $bnNew;
        // $response['n'] = $nActualTimespan;
        // $response['l'] = $last_block;
        // $response['f'] = $first_block;
        // $response['lt'] = gmdate($config["date_format"], $last_block["time"]);
        // $response['ft'] = gmdate($config["date_format"], $first_block["time"]);
        exit(json_encode($response));
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

        $header = loadfile("templates/header.html");
        $body = loadfile("templates/search-body.html");
        $footer = loadfile("templates/footer.html");
        $html = $header . $body . $footer;
        $html = html_replace_common($html);
        $html = html_replace($html, $output);
        echo clean_html($html);
        break;
    default:
        send404();
        break;
}

function get_output_from_block($block)
{
    global $config, $url_path, $bitcoinrpc;
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\" style=\"width:30%\">Height</th><td class=\"text-start\">" . $block["height"] . "</td></tr>";
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Hash</th><td class=\"text-start\">" . $block["hash"] . "</td></tr>";
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Time</th><td class=\"text-start\">" . gmdate($config["date_format"], $block["time"]) . " UTC</td></tr>";
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Version</th><td class=\"text-start\">" . $block["version"] . "</td></tr>";
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Size</th><td class=\"text-start\">" . short_number($block["size"], 1024, 3, " ") . "B" . "</td></tr>";
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Confirmations</th><td class=\"text-start\">" . $block["confirmations"] . "</td></tr>";
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Difficulty</th><td class=\"text-start\">" . short_number($block["difficulty"], 1000, 3, "") . "</td></tr>";
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Bits</th><td class=\"text-start\">" . $block["bits"] . "</td></tr>";
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Nonce</th><td class=\"text-start\">" . $block["nonce"] . "</td></tr>";
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Chainwork</th><td class=\"text-start\">" . $block["chainwork"] . "</td></tr>";
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Merkleroot</th><td class=\"text-start\">" . $block["merkleroot"] . "</td></tr>";
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Previous block</th><td class=\"text-start\">" . ($block["previousblockhash"] ? "<a class=\"text-info\" href=\"" . $url_path["blockhash"] . $block["previousblockhash"] . "\">" . $block["previousblockhash"] . "</a>" : "") . "</td></tr>";
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Next block</th><td class=\"text-start\">" . ($block["nextblockhash"] ? "<a class=\"text-info\" href=\"" . $url_path["blockhash"] . $block["nextblockhash"] . "\">" . $block["nextblockhash"] . "</a>" : "") . "</td></tr>";
    $output['block_detail_tbody'] .= "<tr><th class=\"text-end\">Transactions</th><td class=\"text-start\">" . count($block["tx"]) . "</td></tr>";

    if (count($block["tx"]) > 0) {
        foreach ($block["tx"] as $tx) {
            $transaction_detail = array();
            $transaction_detail['tx'] = $tx;
            $rawtransaction = $bitcoinrpc->getrawtransaction($tx, 1);
            if ($rawtransaction === false) {
                continue;
            }
            $transaction_detail['time'] = $rawtransaction["time"];
            if (isset($rawtransaction['vin'][0]['coinbase'])) {
                $transaction_detail['coinbase'] = $rawtransaction['vin'][0]['coinbase'];
            } else {
                $transaction_detail['coinbase'] = "";
                $transaction_detail['vin_count'] = count($rawtransaction['vin']);
            }

            foreach ($rawtransaction['vout'] as $vout) {
                if ($vout['value'] > 0.0) {
                    $transaction_detail['vout'][$vout['n']]['addresses'] = $vout['scriptPubKey']['addresses'];
                    $transaction_detail['vout'][$vout['n']]['value'] = trim_dotzero($vout['value']);
                }
            }

            $output['transactions'][] = $transaction_detail;
        }
    }

    // echo json_encode($output);
    // exit;

    if (count($output['transactions']) > 0) {
        foreach ($output['transactions'] as $value) {
            $output['block_detail_tbody'] .= '<tr><th class="text-end">tx</th><td class="text-start">';
            $output['block_detail_tbody'] .= '<a class="text-info" href="' . $url_path["tx"] . $value["tx"] . '">' . $value["tx"] . '</a>';
            $output['block_detail_tbody'] .= '</td></tr>';
            $output['block_detail_tbody'] .= '<tr><th class="text-end"></th><td class="text-start">';
            $output['block_detail_tbody'] .= '<table class="table table-borderless table-sm w-75"><tbody>';
            if ($value["coinbase"]) {
                $reward = " <span class=\"text-muted\">*</span>";
            } else {
                $reward = "";
            }
            foreach ($value["vout"] as $vout) {
                $output['block_detail_tbody'] .= '<tr><td class="text-start">' . $reward . $vout["value"] . ' ' . $config["symbol"] . '</td><td class="text-start">';
                foreach ($vout["addresses"] as $address) {
                    $output['block_detail_tbody'] .= $address . '<br>';
                }
                $output['block_detail_tbody'] .= '</td></tr>';
            }
            $output['block_detail_tbody'] .= '</tbody></table>';
        }
    }

    $output["height"] = $block["height"];
    $output["title"] = $block["height"] . " Block Detail - ";
    $output["description"] = "This block's height is " . $block["height"] . ", and the block hash is " . $block["hash"] . ". It was mined at " . gmdate($config["date_format"], $block["time"]) . " UTC.";

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
    $output["body"] = "Oops! 404 Not Found<br><br><a class=\"btn btn-primary\" href=\"" . $config["explorer_path"] . "\">go home</a>";
    $header = loadfile('./templates/header.html');
    $htmlbody = loadfile('./templates/404.html');
    $footer = loadfile('./templates/footer.html');
    $html = $header . $htmlbody . $footer;
    $html = html_replace_common($html);
    $html = html_replace($html, $output);
    echo clean_html($html);
    exit();
}

function html_replace_common($html)
{
    global $config, $version, $url_path;
    $common["name"] = $config["name"];
    $common["currency"] = $config["name"];
    $common["symbol"] = $config["symbol"];
    $common["explorer_name"] = $config["explorer_name"];
    $common["explorer_path"] = $config["explorer_path"];
    $common["homepage"] = $config["homepage"];
    $common["copy_name"] = $config["copy_name"];
    $common["start_year"] = $config["start_year"];
    $common["year"] = date("Y", time());
    $common["version"] = $version;
    $common["search_url"] = $url_path["search"];

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
exit;
