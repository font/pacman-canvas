<?php header('Content-Type: application/json');
// This path should point to Composer's autoloader
require '../../vendor/autoload.php';

/* IMPORTANT:
 * change this to the main url of where you host the application, otherwise, every entry will be marked as a cheater
*/
$hostdomain = 'pacman.default.federation';

if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'get':
            if(isset($_POST['page'])) {
                echo getHighscore($_POST['page']);
            } else {
                echo getHighscore();
            }
            break;
        case 'add': if(isset($_POST['name']) || isset($_POST['score']) || isset($_POST['level']))
                echo addHighscore($_POST['name'],$_POST['score'], $_POST['level']);
            break;
        case 'reset':
            echo resetHighscore();
            break;
        }
} else if (isset($_GET['action'])) {
    if ($_GET['action'] == 'get') {
        if(isset($_GET['page'])) {
            echo getHighscore($_GET['page']);
        } else {
            echo getHighscore();
        }
    } elseif ($_GET['action'] == 'zone') {
        echo getZone();
    }
} else echo "define action to call";


function getHighscore($page = 1) {

    $client = new MongoDB\Client('mongodb://localhost:27017/',
                                 [
                                     'replicaSet' => 'rs0',
                                     'readPreference' => 'secondaryPreferred',
                                 ]
                                );
    $collection = $client->pacman->highscore;
    $filter = [];
    $options = [
                "sort" => [ 'score' => -1 ],
               ];
    $result = $collection->find($filter, $options);

    $i = 0;
    foreach ($result as $doc) {
        $tmp["name"] = htmlspecialchars($doc['name']);
        $tmp["zone"] = $doc['zone'];
        $tmp["score"] = strval($doc['score']);
        $response[] = $tmp;

        if ($i++ >= 9) {
            break;
        }
    }

    if (!isset($response) || is_null($response)) {
        return "[]";
    } else {
        return json_encode($response);
    }
}

function addHighscore($name, $score, $level) {

    $client = new MongoDB\Client('mongodb://localhost:27017/?replicaSet=rs0');
    $collection = $client->pacman->highscore;
    $date = date('Y-m-d h:i:s', time());

    $ref = isset($_SERVER[ 'HTTP_REFERER']) ? $_SERVER[ 'HTTP_REFERER'] : "";
    $ua = isset($_SERVER[ 'HTTP_USER_AGENT']) ? $_SERVER[ 'HTTP_USER_AGENT'] : "";
    $remA = isset($_SERVER[ 'REMOTE_ADDR']) ? $_SERVER[ 'REMOTE_ADDR'] : "";
    $remH = isset($_SERVER[ 'REMOTE_HOST']) ? $_SERVER[ 'REMOTE_HOST'] : "";

    // some simple checks to avoid cheaters
    $ref_assert = preg_match('/http:\/\/.*' . $hostdomain . '.*/', $ref) > 0;
    $ua_assert = ($ua != "");
    $cheater = 0;
    if (!$ref_assert || !$ua_assert) {
        $cheater = 1;
    }

    $maxlvlpoints_pills = 104 * 10;
    $maxlvlpoints_powerpills = 4 * 50;
    $maxlvlpoints_ghosts = 4 * 4 * 100;
    // check if score is even possible
    if ($level < 1) {
        $cheater = 1;
    } else if (($score / $level) > (1600 + 1240)) {
        $cheater = 1;
    }

    $zone = getFederatedZone();

    $result = $collection->insertOne( [ 'name' => $name, 'zone' => $zone,
                                        'score' => (int) $score, 'level' => $level,
                                        'date' => $date, 'log_referer' => $ref,
                                        'log_user_agent' => $ua,
                                        'log_remote_addr' => $remA, 'log_remote_host' => $remH,
                                        'cheater' => $cheater ] );

    $response['status'] = "success";
    $response['level'] = $level;
    $response['name'] = $name;
    $response['zone'] = $zone;
    $response['score'] = $score;
    $response['cheater'] = $cheater;
    return json_encode($response);
}

function resetHighscore() {
    $client = new MongoDB\Client('mongodb://localhost:27017/?replicaSet=rs0');
    $collection = $client->pacman->highscore;
    $result = $collection->drop();
}

function getFederatedZone() {
    exec("curl \"http://metadata.google.internal/computeMetadata/v1/instance/zone\" -H \"Metadata-Flavor: Google\"", $output, $return_var);

    if (empty($output)) {
        $output = 'unknown';
    }
    $split_output = explode('/', $output[0]);

    # return the last field
    $zone = $split_output[count($split_output) - 1];
    return $zone;
}

function getZone() {
    return json_encode(strtoupper(getFederatedZone()));
}

?>
