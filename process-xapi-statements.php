<?php
/**
 * Created by IntelliJ IDEA.
 * User: dsgraham
 * Date: 8/16/17
 * Time: 3:50 PM
 */


function getWpLoadPath() {
    $path=$_SERVER['SCRIPT_FILENAME'];

    for ($i=0; $i<4; $i++)
        $path=dirname($path);

    return $path."/wp-load.php";
}

require_once getWpLoadPath();

$s=json_decode(stripslashes($_REQUEST["data"]),TRUE);
$blogid = $s['blog'];
$postid = $s['post'];
$gradingScheme = $s['grading'];
$userData = array();
$statements = $s['statements'];

$questions = array();
$maxTotalGrade = 0;

foreach ($statements as $statement){
    $info = new stdClass();
    $info->timestamp = $statement['timestamp'];
    $info->score = $statement['result']['score']['scaled'];
    $info->maxScore = $statement['result']['score']['max'];
    $info->rawScore = $statement['result']['score']['raw'];
    $info->question = $statement['object']['definition']['name']['en-US'];
    if ($statement['result']['score']['max'] != "0") {
        if (is_null($info->score)){
            $info->score = floatval($statement['result']['score']['raw'])/floatval($statement['result']['score']['max']);
        }
        $userData[$statement['actor']['name']][$statement['object']['id']][] = $info;
        $questions[$statement['object']['id']] = new stdClass();
        $questions[$statement['object']['id']]->maxScore = $info->maxScore;
        $questions[$statement['object']['id']]->title = $statement['object']['definition']['name']['en-US'];
    }
}

foreach($questions as $question){
    $maxTotalGrade += $question->maxScore;
}

if (sizeof($questions) == 0){
    $return = new stdClass();
    $return->error = "No statements found";
    echo json_encode($return);
    exit;
}


if ($maxTotalGrade == 0){
    $return = new stdClass();
    $return->error = "No scores found";
    $return->questions = $questions;
    echo json_encode($return);
    exit;
}

foreach($userData as $user => $info) {
    $userScore = 0;
    $userId = get_user_by("login", $user);

    foreach($info as $object => $object_statements){
        usort($userData[$user][$object], function ($a, $b) {
            return strtotime($a->timestamp) - strtotime($b->timestamp);
        });

        $target = null;
        if ($gradingScheme == 'first'){
            $target = reset($userData[$user][$object]);
        } else if ($gradingScheme == 'last'){
            $target = end($userData[$user][$object]);
        } else { // Best
            foreach ($userData[$user][$object] as $statement){
                if (is_null($target) || floatval($target->rawScore) < floatval($statement->rawScore)){
                    $target = $statement;
                }
            }
        }
        $userScore += $target->rawScore;
        $userData[$user][$object]['target'] = $target;
    }

    $userData[$user]['totalScore'] = $userScore;
    $percentScore =  floatval($userScore/$maxTotalGrade);
    $userData[$user]['percentScore'] = $percentScore;
    do_action('lti_outcome', $percentScore , $userId->ID, $postid, $blogid);
    // usleep(500000);

}

$return = new stdClass();
$return->maxGrade = $maxTotalGrade;
$return->userData = $userData;
$return->questions = $questions;
echo json_encode($return);

//do_action('lti_outcome', $s['score'] , $s['userID'], $s['pageID'], $s['blogID']);
//$a = 1;
