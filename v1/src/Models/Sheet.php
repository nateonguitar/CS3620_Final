<?php
/**
 * Created by PhpStorm.
 * User: theds
 * Date: 12/6/2016
 * Time: 10:25 AM
 */

namespace CS3620_Final\Models;


class Sheet implements \JsonSerializable
{
    private $SheetID;
    private $SheetTitle;
    private $SheetContents;
    private $SheetUploadDate;
    private $SheetAccepted;
    private $UserID;

    function jsonSerialize()
    {
        return
            array(
                'sheet_id' => $this->SheetID,
                'sheet_title' => $this->SheetTitle,
                'sheet_contents' => $this->SheetContents,
                'sheet_upload_date' => $this->SheetUploadDate,
                'sheet_accepted' => $this->SheetAccepted,
                'user_id' => $this->UserID
            );

    }

    function __construct($SheetID, $SheetTitle, $SheetContents, $SheetUploadDate, $SheetAccepted, $UserID)
    {
        $this->SheetID = $SheetID;
        $this->SheetTitle = $SheetTitle;
        $this->SheetContents = $SheetContents;
        $this->SheetUploadDate = $SheetUploadDate;
        $this->SheetAccepted = $SheetAccepted;
        $this->UserID = $UserID;
    }
}