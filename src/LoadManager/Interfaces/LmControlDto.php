<?php
/**
 * Created by PhpStorm.
 * User: ww
 * Date: 02.10.15
 * Time: 21:52
 */
namespace React\ProcessManager\LoadManager\Interfaces;

interface LmControlDto
{

    public function getResidentMemoryUsage();

    public function getCpuUsage();

    public function getActiveProcessesNumber();

}