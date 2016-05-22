<?php
/**
 * Created by PhpStorm.
 * User: ww
 * Date: 29.09.15
 * Time: 21:16
 */
namespace React\ProcessManager\Interfaces;

use React\ProcessManager\LoadManager\Interfaces\LmControlDto;

interface Management
{
    /**
     * @param LmControlDto $receivedControlDto
     * @return mixed
     */
    public function processReceivedControlDto(LmControlDto $receivedControlDto);


}