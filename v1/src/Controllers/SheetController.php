<?php
/**
 * Created by PhpStorm.
 * User: theds
 * Date: 12/6/2016
 * Time: 10:32 AM
 */

namespace CS3620_Final\Controllers;

use PDO;
use CS3620_Final\Models\Sheet;
use CS3620_Final\Models\Token;
use CS3620_Final\Http\StatusCodes;
use CS3620_Final\Utilities\DatabaseConnection;

class SheetController
{
    public static $date_regex = "/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/";

    public static function deleteSheet($data){
        $data = (object)json_decode(file_get_contents('php://input'));
        $token = Token::getRoleFromToken();
        $db = DatabaseConnection::getInstance();

        if ($token != Token::ROLE_ADMIN) {
            http_response_code(StatusCodes::UNAUTHORIZED);
            return "You don't have permission for that, honey!";
            die();
        }

        if (!ctype_digit($data->id)) {
            http_response_code(StatusCodes::BAD_REQUEST);
            echo "Bad request";
            die();
        }

        $id = $data->id;

        $query_delete_sheet = '
            DELETE FROM TabSiteSheet
            WHERE SheetID = :id
        ';

        $stmt_delete_sheet = $db->prepare($query_delete_sheet);
        $stmt_delete_sheet->bindValue(':id', $id);

        $deleteSheetWorked = $stmt_delete_sheet->execute();

        if($deleteSheetWorked){
            $rowsDeleted = $stmt_delete_sheet->rowCount();
            if ($rowsDeleted == 0) {
                http_response_code(StatusCodes::GONE);
                echo 'No rows deleted. Please check the ID.';
                die();
            } else {
                http_response_code(StatusCodes::OK);
                $returned_array = array();
                $returned_array['rowsDeleted'] = $rowsDeleted;
                return $returned_array;
            }
        } else {

            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            echo 'Hey dawg, that didn\'t work!';
            die();
        }



    }

    public static function editSheet($data)
    {
        $data = (object)json_decode(file_get_contents('php://input'));
        $token = Token::getRoleFromToken();
        $db = DatabaseConnection::getInstance();

        if ($token != Token::ROLE_ADMIN) {
            http_response_code(StatusCodes::UNAUTHORIZED);
            return "You don't have permission for that, honey!";
            die();
        }

        if (!ctype_digit($data->id)) {
            http_response_code(StatusCodes::BAD_REQUEST);
            echo "Bad request";
            die();
        }

        // store only lower case, it's easy to format correctly on front end
        // first name must be only letters
        if(empty($data->name) || !ctype_alpha($data->name)){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'First name not formatted correctly';
            die();
        }

        // see if username is already in use (if it's not already this user's username)
        $queryCheckIfSheetExists = 'SELECT * FROM TabSiteSheet WHERE SheetName = :name';
        $stmtCheckIfSheetExists = $db->prepare($queryCheckIfSheetExists);
        $stmtCheckIfSheetExists->bindValue(':name', trim(strtolower($data->name)));
        $CheckIfSheetExistsWorked = $stmtCheckIfSheetExists->execute();

        if($CheckIfSheetExistsWorked){
            $sheet = $stmtCheckIfSheetExists->fetch(PDO::FETCH_ASSOC);

            if($stmtCheckIfSheetExists->rowCount() == 1 && $sheet['SheetID'] != $data->id){
                http_response_code(StatusCodes::BAD_REQUEST);
                echo 'That username already exist for a different user';
                die();
            }
        }
        else{
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            echo 'wha happened?';
            die();
        }

        $id = $data->id;
        $name = trim(strtolower($data->name));

        $query_update_sheet = '
            UPDATE TabSiteSheet
            SET SheetName = :name
            WHERE SheetID = :id
        ';


        $statement_update_sheet = $db->prepare($query_update_sheet);
        $statement_update_sheet->bindValue(':id',    $id);
        $statement_update_sheet->bindValue(':name',  $name);

        $update_sheet_worked = $statement_update_sheet->execute();

        if ($update_sheet_worked) {
            http_response_code(StatusCodes::OK);

            $returned_data = SheetController::getSheetByID($id);
            return $returned_data;
        } else {
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            return 'Hey dawg, that didn\'t work!';
        }
    }

    public static function createSheet($param)
    {
        $data = (object)json_decode(file_get_contents('php://input'));
        $token = Token::getRoleFromToken();
        $db = DatabaseConnection::getInstance();

        if ($token != Token::ROLE_ADMIN) {
            http_response_code(StatusCodes::UNAUTHORIZED);
            return "You don't have permission for that, honey!";
            die();
        }

        if(empty($data->sheet_title)){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'Sheet title is empty';
            die();
        }

        if(empty($data->sheet_contents)){
            http_response_code(StatusCodes::BAD_REQUEST);
            echo 'Sheet is empty';
            die();
        }

        if(empty($data->sheet_upload_date) || !preg_match(TimeframeController::$date_regex, $data->sheet_upload_date)){
            http_response_code(400);
            echo "<h1>Error: Bad Request</h1>";
            exit();
        }


        $name = trim(strtolower($data->name));

        $query_insert_sheet = '
        INSERT INTO TabSiteSheet
        (
            SheetName
        )
        VALUES
        (
            :name
        )
    ';
        $statement_insert_sheet = $db->prepare($query_insert_sheet);
        $statement_insert_sheet->bindValue(':name',      $name);

        $insert_sheet_worked = $statement_insert_sheet->execute();

        if ($insert_sheet_worked) {
            $inserted_id = $db->lastInsertId();
            $returned_sheet = new Sheet(
                $inserted_id,
                $name
            );
            http_response_code(StatusCodes::CREATED);

            return $returned_sheet;
        } else {
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            return 'Hey dawg, that didn\'t work!';
        }

    }

    public static function getAllSheets(){
        $db = DatabaseConnection::getInstance();
        $role = Token::getRoleFromToken();

        if ($role != Token::ROLE_MODERATOR && $role != Token::ROLE_ADMIN){
            http_response_code(StatusCodes::UNAUTHORIZED);
            echo  'You don\'t have permission for that, honey!';
            die();
        }

        $query_get_all_sheets = '
                    SELECT * FROM TabSiteSheet
                ';

        $stmt_get_all_comoposers = $db->prepare($query_get_all_sheets);

        $getAllSheetsWorked = $stmt_get_all_comoposers->execute();

        if($getAllSheetsWorked){
            $allSheetsFormatted = array();
            $allSheets = $stmt_get_all_comoposers->fetchAll(PDO::FETCH_ASSOC);
            foreach($allSheets as $sheet){
                $sheetToAdd = new Sheet(
                    $sheet['SheetID'],
                    $sheet['SheetName']
                );

                array_push($allSheetsFormatted, $sheet);
            }

            http_response_code(StatusCodes::OK);
            return $allSheetsFormatted;
        }
    }

    public static function getSheetByID($sheetID = null){
        $db = DatabaseConnection::getInstance();
        $role = Token::getRoleFromToken();

        if ($role != Token::ROLE_MODERATOR && $role != Token::ROLE_ADMIN){
            http_response_code(StatusCodes::UNAUTHORIZED);
            echo  'You don\'t have permission for that, honey!';
            die();
        }

        // get the sheetID
        if (!empty($sheetID) && is_array($sheetID)) {
            $sheetID = $sheetID['id'];
        } else if ($sheetID == null) {
            $data = (object)json_decode(file_get_contents('php://input'));
            if(!empty($data->id)){
                $sheetID = $data->id;
            }
        }

        // make sure we have a proper integer
        if (!ctype_digit($sheetID)) {
            http_response_code(400);
            echo "<h1>Error: Bad Request</h1>";
            die();
        }



        $queryGetSheet = 'SELECT * FROM TabSiteSheet WHERE SheetID = :id';

        $stmtGetSheet = $db->prepare($queryGetSheet);
        $stmtGetSheet->bindValue(':id', $sheetID);
        $getSheetWorked = $stmtGetSheet->execute();


        if ($getSheetWorked) {
            if($stmtGetSheet->rowCount() != 1){
                http_response_code(StatusCodes::NOT_FOUND);
                echo 'That user does not exist';
                die();
            }

            $returned_data = $stmtGetSheet->fetch(PDO::FETCH_ASSOC);
            $returned_sheet = new Sheet(
                $sheetID,
                $returned_data['SheetName']
            );
            http_response_code(StatusCodes::OK);
            return $returned_sheet;
        } else {
            http_response_code(StatusCodes::INTERNAL_SERVER_ERROR);
            return 'That didn\'t work';
        }

    }
}