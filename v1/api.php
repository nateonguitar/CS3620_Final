<?php
/**
 * Created by PhpStorm.
 * User: nathan brooks
 * Date: 11/30/2016
 */
//error_reporting(0);

use CS3620_Final\Http;
use CS3620_Final\Controllers;

require_once 'config.php';
require_once 'vendor/autoload.php';

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) use ($baseURI) {

    //****************************************************************************
    //****************************************************************************
    //****************************************************************************

    $handlePostUser = function($args){
        return (new CS3620_Final\Controllers\UserController)->createUser($args);
    };

    $handleGetUser = function($args){
        return (new CS3620_Final\Controllers\UserController)->getUserByID($args);
    };

    $handleGetAllUsers = function(){
        return (new CS3620_Final\Controllers\UserController)->getAllUsers();
    };

    $handlePutUser = function($args){
        return (new CS3620_Final\Controllers\UserController)->editUser();
    };

    $handleDeleteUser = function($args){
        return (new CS3620_Final\Controllers\UserController)->deleteUser();
    };

    //----------------------------------------------------------------------------

    $handlePostComposer = function($args){
        return (new CS3620_Final\Controllers\ComposerController)->createComposer($args);
    };

    $handleGetComposer = function($args){
        return (new CS3620_Final\Controllers\ComposerController)->getComposerByID($args);
    };

    $handleGetAllComposers = function(){
        return (new CS3620_Final\Controllers\ComposerController)->getAllComposers();
    };

    $handlePutComposer = function(){
        return (new CS3620_Final\Controllers\ComposerController)->editComposer();
    };

    $handleDeleteComposer = function($args){
        return (new CS3620_Final\Controllers\ComposerController)->deleteComposer($args);
    };

    //----------------------------------------------------------------------------

    $handlePostSheet = function($args){
        return (new CS3620_Final\Controllers\SheetController)->createSheet($args);
    };

    $handleGetSheet = function($args){
        return (new CS3620_Final\Controllers\SheetController)->getSheetByID($args);
    };

    $handleGetAllSheets = function(){
        return (new CS3620_Final\Controllers\SheetController)->getAllSheets();
    };

    $handlePutSheet = function($args){
        return (new CS3620_Final\Controllers\SheetController)->editSheet($args);
    };

    $handleDeleteSheet = function($args){
        return (new CS3620_Final\Controllers\SheetController)->deleteSheet($args);
    };

    //----------------------------------------------------------------------------

    $handlePostQualityRating = function(){
        return (new CS3620_Final\Controllers\QualityRatingController)->createQualityRating();
    };

    $handleGetQualityRating = function($id){
        return (new CS3620_Final\Controllers\QualityRatingController)->getQualityRatingByID($id);
    };

    $handleGetAllQualityRatings = function(){
        return (new CS3620_Final\Controllers\QualityRatingController)->getAllQualityRatings();
    };

    $handlePutQualityRating = function($args){
        return (new CS3620_Final\Controllers\QualityRatingController)->editQualityRating($args);
    };

    $handleDeleteQualityRating = function($args){
        return (new CS3620_Final\Controllers\QualityRatingController)->deleteQualityRating($args);
    };

    //----------------------------------------------------------------------------

    $handlePostToken = function ($args) {
        $tokenController = new CS3620_Final\Controllers\TokensController();
        //Is the data via a form?

        // never gets to this point
        if (!empty($_POST['username'])) {
            $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
            $password = $_POST['password'] ?? "";

        } else {
            //Attempt to parse json input
            $json = (object) json_decode(file_get_contents('php://input'));
            if (count((array)$json) >= 2) {

                $username = filter_var($json->username, FILTER_SANITIZE_STRING);
                $password = $json->password;
            } else {
                http_response_code(CS3620_Final\Http\StatusCodes::BAD_REQUEST);
                exit();
            }
        }
        return $tokenController->buildToken($username, $password);
    };


    //****************************************************************************
    //****************************************************************************
    //****************************************************************************

    $r->addRoute('POST',    $baseURI . '/user/',           $handlePostUser);
    $r->addRoute('POST',    $baseURI . '/user',            $handlePostUser);
    $r->addRoute('GET',     $baseURI . '/user/{id:\d+}',   $handleGetUser);
    $r->addRoute('GET',     $baseURI . '/user/',           $handleGetAllUsers);
    $r->addRoute('GET',     $baseURI . '/user',            $handleGetAllUsers);
    $r->addRoute('PUT',     $baseURI . '/user/',           $handlePutUser);
    $r->addRoute('PUT',     $baseURI . '/user',            $handlePutUser);
    $r->addRoute('DELETE',  $baseURI . '/user/',           $handleDeleteUser);
    $r->addRoute('DELETE',  $baseURI . '/user',            $handleDeleteUser);

    //----------------------------------------------------------------------------

    $r->addRoute('POST',    $baseURI . '/composer/',           $handlePostComposer);
    $r->addRoute('POST',    $baseURI . '/composer',            $handlePostComposer);
    $r->addRoute('GET',     $baseURI . '/composer/{id:\d+}',   $handleGetComposer);
    $r->addRoute('GET',     $baseURI . '/composer/',           $handleGetAllComposers);
    $r->addRoute('GET',     $baseURI . '/composer',            $handleGetAllComposers);
    $r->addRoute('PUT',     $baseURI . '/composer/',           $handlePutComposer);
    $r->addRoute('PUT',     $baseURI . '/composer',            $handlePutComposer);
    $r->addRoute('DELETE',  $baseURI . '/composer/',           $handleDeleteComposer);
    $r->addRoute('DELETE',  $baseURI . '/composer',            $handleDeleteComposer);

    //----------------------------------------------------------------------------

    $r->addRoute('POST',    $baseURI . '/sheet/',           $handlePostSheet);
    $r->addRoute('POST',    $baseURI . '/sheet',            $handlePostSheet);
    $r->addRoute('GET',     $baseURI . '/sheet/{id:\d+}',   $handleGetSheet);
    $r->addRoute('GET',     $baseURI . '/sheet/',           $handleGetAllSheets);
    $r->addRoute('GET',     $baseURI . '/sheet',            $handleGetAllSheets);
    $r->addRoute('PUT',     $baseURI . '/sheet/',           $handlePutSheet);
    $r->addRoute('PUT',     $baseURI . '/sheet',            $handlePutSheet);
    $r->addRoute('DELETE',  $baseURI . '/sheet/',           $handleDeleteSheet);
    $r->addRoute('DELETE',  $baseURI . '/sheet',            $handleDeleteSheet);

    //----------------------------------------------------------------------------

    $r->addRoute('POST',    $baseURI . '/qualityRating/',           $handlePostQualityRating);
    $r->addRoute('POST',    $baseURI . '/qualityRating',            $handlePostQualityRating);
    $r->addRoute('GET',     $baseURI . '/qualityRating/{id:\d+}',   $handleGetQualityRating);
    $r->addRoute('GET',     $baseURI . '/qualityRating/',           $handleGetAllQualityRatings);
    $r->addRoute('GET',     $baseURI . '/qualityRating',            $handleGetAllQualityRatings);
    $r->addRoute('PUT',     $baseURI . '/qualityRating/',           $handlePutQualityRating);
    $r->addRoute('PUT',     $baseURI . '/qualityRating',            $handlePutQualityRating);
    $r->addRoute('DELETE',  $baseURI . '/qualityRating/',           $handleDeleteQualityRating);
    $r->addRoute('DELETE',  $baseURI . '/qualityRating',            $handleDeleteQualityRating);

    //----------------------------------------------------------------------------

    $r->addRoute('POST',    $baseURI . '/tokens',          $handlePostToken);

    //****************************************************************************
    //****************************************************************************
    //****************************************************************************
});

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

$pos = strpos($uri, '?');
if ($pos !== false) {
    $uri = substr($uri, 0, $pos);
}

$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($method, $uri);

switch($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        http_response_code(CS3620_Final\Http\StatusCodes::NOT_FOUND);
        //Handle 404
        break;
    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:

        http_response_code(CS3620_Final\Http\StatusCodes::METHOD_NOT_ALLOWED);
        //Handle 403
        break;
    case FastRoute\Dispatcher::FOUND:

        $handler  = $routeInfo[1];
        $vars = $routeInfo[2];

        $response = $handler($vars);
        echo json_encode($response);
        break;
    default:
        break;
}