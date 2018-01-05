<?php

require_once __DIR__ . '/../vendor/autoload.php';

$configFile = __DIR__ . '/../config.yaml';

if (!is_file($configFile)) {
    echo "Please create a configuration file - see details in config.yaml.sample file.";
    die();
}

$configData = file_get_contents($configFile);
$config = yaml_parse($configData);

$app = new \Slim\App();
$container = $app->getContainer();
$container['view'] = function ($container) {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates/');
};

$app->get('/[{param}]', function ($request, $response, $args) {
    global $config;
    $statData = getStatData($config);
    $statData += $args;
    return $this->view->render($response, 'index.html', $statData);
});

// Run app
$app->run();

// --- get details ---
function getStatData($config)
{
    $statData = array();

    $statData += getEthosData($config);
    $statData += getEthermineData($config);
    $statData += getNanopoolSiaData($config);
    $statData += getMiningPoolHubCoinValues($config);

    return $statData;
}

function getEthosData($config)
{
    $statData = array();

    // -- get ethos data ---
    $ethosPanelId = $config['ethos']['panel'];
    $ethosApi = "http://$ethosPanelId.ethosdistro.com/?json=yes";
    $ethosData = json_decode(file_get_contents($ethosApi), true);
    $excludedRigs = $config['ethos']['excluded_rigs'];
    $rigs = $ethosData['rigs'];
    $totalGpuNum = 0;
    $totalMinersNum = 0;
    $totalEtHash = 0;
    $totalEquiHash = 0;
    $maxTemp = 0;
    foreach ($rigs as $rigId => $rig) {
        $etHash = 0;
        $equiHash = 0;
        if (!in_array($rigId, $excludedRigs)) {
            $gpuNum = $rig['gpus'];
            $miners = $rig['miner_instance'];

            if (in_array($rig['miner'], array('claymore'))) {
                $etHash = $rig['hash'];
            }

            if (in_array($rig['miner'], array('ewbf-zcash', 'dstm-zcash'))) {
                $equiHash = $rig['hash'];
            }

            $maxTemp = max(explode(' ', $rig['temp'] . ' ' . $maxTemp));

            $totalGpuNum += $gpuNum;
            $totalMinersNum += $miners;
            $totalEtHash += $etHash;
            $totalEquiHash += $equiHash;
        }
    }
    $statData['total_gpu'] = $totalGpuNum;
    $statData['total_miner'] = $totalMinersNum;
    $statData['total_ethash'] = $totalEtHash;
    $statData['total_equihash'] = $totalEquiHash;
    $statData['max_temp'] = $maxTemp;

    return $statData;
}

function getEthermineData($config)
{
    $statData = array();

    // -- get ethermine data --
    $ethermineWallets = $config['pools']['ethermine'];
    $usdPerMin = 0;
    $unpaid = array();
    $minPayout = array();
    foreach ($ethermineWallets as $ethermineWallet) {
        $minerStatsUrl = "https://api.ethermine.org/miner/$ethermineWallet/currentStats";
        $etherMineData = json_decode(file_get_contents($minerStatsUrl), true);
        $data = $etherMineData['data'];
        if ($data['activeWorkers'] !== null) {
            $usdPerMin += $data['usdPerMin'];
            $unpaid[$ethermineWallet] = $data['unpaid'];
            $minerSettingsURL = "https://api.ethermine.org/miner/$ethermineWallet/settings";
            $ethermineSettings = json_decode(file_get_contents($minerSettingsURL), true);
            $minPayout[$ethermineWallet] = $ethermineSettings['data']['minPayout'];
        }
    }
    $usdPerMonth = $usdPerMin * 60 * 24 * date('t');
    $statData['usd_per_month'] = $usdPerMonth;
    $statData['eth_unpaid'] = $unpaid;
    $statData['eth_min_payout'] = $minPayout;

    $poolStatsUrl = "https://api.ethermine.org/poolStats";
    $poolStatsData = json_decode(file_get_contents($poolStatsUrl), true);
    $ethPrice = $poolStatsData['data']['price']['usd'];
    $statData['eth_price'] = $ethPrice;

    return $statData;
}

function getNanopoolSiaData($config)
{
    $statData = array();

    // -- get sia.nanopool data --
    $nanopoolSiaWallets = $config['pools']['nanopool-sia'];

    $totalHashRate = 0;
    foreach ($nanopoolSiaWallets as $nanopoolSiaWallet) {
        $nanopoolSiaUrl = "https://api.nanopool.org/v1/sia/hashrate/$nanopoolSiaWallet";
        $nanopoolSiaData = json_decode(file_get_contents($nanopoolSiaUrl), true);
        $hashRate = $nanopoolSiaData['data'];
        $totalHashRate += $hashRate;
    }

    $nanopoolSiaCalculatorUrl = "https://api.nanopool.org/v1/sia/approximated_earnings/$totalHashRate";
    $nanopoolSiaCalculatorData = json_decode(file_get_contents($nanopoolSiaCalculatorUrl), true);
    $usdEarnings = $nanopoolSiaCalculatorData['data']['month']['dollars'];

    $nanopoolSiaPriceUrl = "https://api.nanopool.org/v1/sia/prices";
    $nanopoolSiaPriceData = json_decode(file_get_contents($nanopoolSiaPriceUrl), true);
    $siaPrice = $nanopoolSiaPriceData['data']['price_usd'];

    $statData['sia_hash'] = $totalHashRate;
    $statData['sia_usd_per_month'] = $usdEarnings;
    $statData['sia_price'] = $siaPrice;

    return $statData;
}

function getMiningPoolHubCoinValues($config)
{
    $statData = array();

    $coins = $config['pools']['miningpoolhub']['coins'];
    $apiKey = $config['pools']['miningpoolhub']['apikey'];
    foreach ($coins as $coin) {
        $coinMarketCapUrl = "https://api.coinmarketcap.com/v1/ticker/$coin/";
        $coinMarketCapData = json_decode(file_get_contents($coinMarketCapUrl), true);
        $coinPrice = $coinMarketCapData[0]['price_usd'];
        $statData[$coin . "_price"] = $coinPrice;

        $miningPoolCoinStatsUrl = "https://$coin.miningpoolhub.com/index.php?page=api&action=getdashboarddata&api_key=$apiKey";
        $miningPoolCoinStatsData = json_decode(file_get_contents($miningPoolCoinStatsUrl), true);
        $dashboardData = $miningPoolCoinStatsData['getdashboarddata']['data'];
        $recentCredits24H = $dashboardData['recent_credits_24hours']['amount'];

        $statData[$coin . '_usd_per_month'] = $recentCredits24H * date('t') * $coinPrice;
    }
    return $statData;
}
