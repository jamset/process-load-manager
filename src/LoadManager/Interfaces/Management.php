<?php
/**
 * Created by PhpStorm.
 * User: ww
 * Date: 29.09.15
 * Time: 21:16
 */
namespace React\ProcessManager\LoadManager\Interfaces;

use React\ProcessManager\Interfaces\PmControlDto;

interface Management
{

    /**
     * @param PmControlDto $receivedControlDto
     * @return mixed
     */
    public function processReceivedControlDto(PmControlDto $receivedControlDto);


}