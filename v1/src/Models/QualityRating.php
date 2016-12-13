<?php
/**
 * Created by PhpStorm.
 * User: theds
 * Date: 12/6/2016
 * Time: 10:25 AM
 */

namespace CS3620_Final\Models;


class QualityRating implements \JsonSerializable
{
    private $QualityRatingID;
    private $UserID;
    private $SheetID;
    private $QualityRating;

    function jsonSerialize()
    {
        return
            array(
                'quality_rating_id' => $this->QualityRatingID,
                'user_id' => $this->UserID,
                'sheet_id' => $this->SheetID,
                'quality_rating' => $this->QualityRating
            );

    }

    function __construct($QualityRatingID, $UserID, $SheetID, $QualityRating)
    {
        $this->QualityRatingID = $QualityRatingID;
        $this->UserID = $UserID;
        $this->SheetID = $SheetID;
        $this->QualityRating = $QualityRating;
    }

    public function getQualityRatingID(){
        return $this->QualityRatingID;
    }
    public function getUserID(){
        return $this->UserID;
    }
    public function getSheetID(){
        return $this->SheetID;
    }
    public function getQualityRating(){
        return $this->QualityRating;
    }
}