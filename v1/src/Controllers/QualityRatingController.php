<?php
/**
 * Created by PhpStorm.
 * User: theds
 * Date: 12/6/2016
 * Time: 10:32 AM
 */

namespace CS3620_Final\Controllers;

use PDO;
use CS3620_Final\Models\QualityRating;
use CS3620_Final\Models\Token;
use CS3620_Final\Http\StatusCodes;
use CS3620_Final\Utilities\DatabaseConnection;

class QualityRatingController
{
    public static function deleteQualityRating(){
        // can call this function via DELETE http method with a body like:
        //
        // {
        //      "id":"9"
        // }

        $data = (object)json_decode(file_get_contents('php://input'));
        $token = Token::getRoleFromToken();
        $db = DatabaseConnection::getInstance();

        if ($token != Token::ROLE_ADMIN) {
            http_response_code(StatusCodes::UNAUTHORIZED);
            die();
        }

        if (!ctype_digit($data->id)) {
            http_response_code(StatusCodes::BAD_REQUEST);
            die();
        }

        $id = $data->id;

        $queryDeleteRating = '
            DELETE FROM TabSiteQualityRating
            WHERE QualityRatingID = :id
        ';

        $stmtDeleteRating = $db->prepare($queryDeleteRating);
        $stmtDeleteRating->bindValue(':id', $id);

        if(!$stmtDeleteRating->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        $rowsDeleted = $stmtDeleteRating->rowCount();

        if ($rowsDeleted == 0) {
            http_response_code(StatusCodes::GONE);
            die();
        }

        http_response_code(StatusCodes::OK);
        return array('rowsDeleted' => $rowsDeleted);
    }

    public static function  editQualityRating($passed_in_rating_id = null, $passed_in_rating = null)
    {
        // this can either be called internally like:
        // QualityRatingController::editQualityRating($existingID, $qualityRating);
        // OR
        // via PUT http method with a body like
        /*
        {
            "rating_id"     :"18",
            "quality_rating":"4"
        }
        */

        $data = (object)json_decode(file_get_contents('php://input'));
        $token = Token::getRoleFromToken();
        $db = DatabaseConnection::getInstance();

        if ($token != Token::ROLE_ADMIN) {
            http_response_code(StatusCodes::UNAUTHORIZED);
            die();
        }


        // all parameters required
        // if using internally
        // here's a bunch of validation
        if(
               !is_null($passed_in_rating_id) && !is_null($passed_in_rating)
            && ctype_digit($passed_in_rating_id) && ctype_digit($passed_in_rating)
        ){
            $ratingID = $passed_in_rating_id;
            $rating = $passed_in_rating;

        }
        // if accessed via PUT http request with json
        else if(
            !empty($data)
            && ctype_digit($data->rating_id)
            && ctype_digit($data->quality_rating)
        ){
            $ratingID = $data->rating_id;
            $rating = $data->quality_rating;
        }
        else{
            // didn't access this function properly
            http_response_code(StatusCodes::BAD_REQUEST);
            die();
        }

        // see if rating already exists
        $queryCheckIfRatingExists = 'SELECT * FROM TabSiteQualityRating WHERE QualityRatingID = :ratingID';
        $stmtCheckIfRatingExists = $db->prepare($queryCheckIfRatingExists);
        $stmtCheckIfRatingExists->bindValue(':ratingID', $ratingID);

        if(!$stmtCheckIfRatingExists->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        // if no rating was found
        if($stmtCheckIfRatingExists->rowCount() != 1){
            http_response_code(StatusCodes::NOT_FOUND);
            die();
        }

        $queryUpdateRating = '
            UPDATE TabSiteQualityRating
            SET QualityRating = :rating
            WHERE QualityRatingID = :ratingID
        ';


        $statementUpdateRating = $db->prepare($queryUpdateRating);
        $statementUpdateRating->bindValue(':ratingID',    $ratingID);
        $statementUpdateRating->bindValue(':rating',  $rating);

        if (!$statementUpdateRating->execute()) {
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        http_response_code(StatusCodes::OK);
        return QualityRatingController::getQualityRAtingByID($ratingID);

    }

    public static function createQualityRating()
    {
        // you can create a QualityRating in the DB through POST with body:
        /*
        {
            "user_id"       :    "40",
            "sheet_id"      :    "79",
            "quality_rating":    "10"
        }
        */

        // only accepts qualities 0 - 10

        // because a user can only rate the quality of a sheet once, this function will also update the old rating first

        $data = (object) json_decode(file_get_contents('php://input'));

        $token = Token::getRoleFromToken();
        $db = DatabaseConnection::getInstance();

        if ($token != Token::ROLE_ADMIN && $token != Token::ROLE_MODERATOR) {
            http_response_code(StatusCodes::UNAUTHORIZED);
            die();
        }

        // some validation
        if(
            empty($data->user_id) || !ctype_digit($data->user_id)                   // bad user_id data type
            || empty($data->sheet_id) || !ctype_digit($data->sheet_id)              // bad sheet_id data type
            || empty($data->quality_rating) || !ctype_digit($data->quality_rating)  // bad quality_rating data type
            || $data->quality_rating < 0 || $data->quality_rating > 10              // quality_rating valid values

        ){
            http_response_code(StatusCodes::BAD_REQUEST);
            die();
        }

        $userID = $data->user_id;
        $sheetID = $data->sheet_id;
        $qualityRating = $data->quality_rating;

        // see if this quality rating already exists:

        $querySeeIfRatingExists = 'SELECT * FROM TabSiteQualityRating WHERE SheetID = :sheetID AND UserID = :userID';
        $stmtSeeIfRatingExists = $db->prepare($querySeeIfRatingExists);
        $stmtSeeIfRatingExists->bindValue(':sheetID', $sheetID);
        $stmtSeeIfRatingExists->bindValue(':userID', $userID);

        if(!$stmtSeeIfRatingExists->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        // if it exists use our editQualityRating function.
        if($stmtSeeIfRatingExists->rowCount() == 1){
            $existingID = $stmtSeeIfRatingExists->fetch(PDO::FETCH_ASSOC)['QualityRatingID'];
            $returnRating = QualityRatingController::editQualityRating($existingID, $sheetID, $userID, $qualityRating);
        }
        else{

            // see if user exists
            $querySeeIfUserExists = 'SELECT * FROM TabSiteUser WHERE UserID = :userID';
            $stmtSeeIfUserExists = $db->prepare($querySeeIfUserExists);
            $stmtSeeIfUserExists->bindValue(':userID', $userID);

            if(!$stmtSeeIfUserExists->execute()){
                http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
                die();
            }

            if($stmtSeeIfUserExists->rowCount() != 1){
                http_response_code(StatusCodes::BAD_REQUEST);
                echo 'user does not exist';
                die();
            }

            // see if sheet exists
            $querySeeIfSheetExists = 'SELECT * FROM TabSiteSheet WHERE SheetID = :sheetID';
            $stmtSeeIfSheetExists = $db->prepare($querySeeIfSheetExists);
            $stmtSeeIfSheetExists->bindValue(':sheetID', $sheetID);

            if(!$stmtSeeIfSheetExists->execute()){
                http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
                die();
            }

            if($stmtSeeIfSheetExists->rowCount() != 1){
                http_response_code(StatusCodes::BAD_REQUEST);
                echo 'sheet does not exist';
                die();
            }

            // if all parameters are ok:
            $queryInsertRating = '
                INSERT INTO TabSiteQualityRating ( UserID, SheetID, QualityRating )
                VALUES ( :userID, :sheetID, :qualityRating )
            ';

            $statementInsertRating = $db->prepare($queryInsertRating);
            $statementInsertRating->bindValue(':userID', $userID);
            $statementInsertRating->bindValue(':sheetID', $sheetID);
            $statementInsertRating->bindValue(':qualityRating', $qualityRating);

            if(!$statementInsertRating->execute()){
                http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
                die();
            }

            $returnRating = QualityRatingController::getQualityRatingByID($db->lastInsertId());
        }

        http_response_code(StatusCodes::CREATED);
        return $returnRating;
    }

    public static function getAllQualityRatings(){
        // this can be called by giving a GET request without an ID like:
        // https://icarus.cs.weber.edu/~nb06777/CS3620_Final/v1/qualityRating/
        // or
        // https://icarus.cs.weber.edu/~nb06777/CS3620_Final/v1/qualityRating

        $db = DatabaseConnection::getInstance();
        $role = Token::getRoleFromToken();

        if ($role != Token::ROLE_MODERATOR && $role != Token::ROLE_ADMIN){
            http_response_code(StatusCodes::UNAUTHORIZED);
            die();
        }

        $queryGetAllRatings = '
                    SELECT * FROM TabSiteQualityRating
                ';

        $stmtGetAllRatings = $db->prepare($queryGetAllRatings);

        if(!$stmtGetAllRatings->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        $allRatings = $stmtGetAllRatings->fetchAll(PDO::FETCH_ASSOC);
        http_response_code(StatusCodes::OK);
        return $allRatings;
    }

    public static function getQualityRatingByID($passedInRatingID = null){
        // you can either call the API like this:
        // https://icarus.cs.weber.edu/~nb06777/CS3620_Final/v1/qualityRating/2
        // or the API can internally get a rating like:
        // QualityRatingController::getQualityRatingByID(3);

        $db = DatabaseConnection::getInstance();
        $role = Token::getRoleFromToken();
        $data = (object) json_decode(file_get_contents('php://input'));


        if ($role != Token::ROLE_MODERATOR && $role != Token::ROLE_ADMIN){
            http_response_code(StatusCodes::UNAUTHORIZED);
            die();
        }

        // get the rating's id

        // if neither way of getting the ID is set error out
        if(is_null($passedInRatingID) && empty($data->id)){
            http_response_code(StatusCodes::BAD_REQUEST);
            die();
        }

        // some validation
        if(!is_null($passedInRatingID) && ctype_digit($passedInRatingID)){
            $ratingID = $passedInRatingID;
        }
        else if(!is_null($passedInRatingID) && is_array($passedInRatingID)){
            $ratingID = $passedInRatingID['id'];
        }
        else if(is_null($passedInRatingID) && !empty($data->id) && ctype_digit($data->id)){
            $ratingID = $data->id;
        }
        else if(is_array($passedInRatingID)){
            $ratingID = $passedInRatingID['id'];
        }
        else{
            http_response_code(StatusCodes::BAD_REQUEST);
            die();
        }


        $queryGetRating = 'SELECT * FROM TabSiteQualityRating WHERE QualityRatingID = :id';

        $stmtGetRating = $db->prepare($queryGetRating);
        $stmtGetRating->bindValue(':id', $ratingID);

        if(!$stmtGetRating->execute()){
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            die();
        }

        if($stmtGetRating->rowCount() != 1){
            http_response_code(StatusCodes::GONE);
            die();
        }

        $returnData = $stmtGetRating->fetch(PDO::FETCH_ASSOC);

        return new QualityRating(
            $returnData['QualityRatingID'],
            $returnData['UserID'],
            $returnData['SheetID'],
            $returnData['QualityRating']
        );
    }
}