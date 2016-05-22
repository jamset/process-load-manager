<?php
/**
 * Created by PhpStorm.
 * User: ww
 * Date: 29.09.15
 * Time: 19:23
 */
namespace React\ProcessManager\Inventory;

use React\FractalBasic\Interfaces\DtoContainerInterface;
use React\ProcessManager\Interfaces\PmControlDto;

class ProcessDto implements DtoContainerInterface, PmControlDto
{
    /**
     * @var int
     */
    protected $pid;

    /**
     * @var int
     */
    protected $childPid;

    /**
     * @return int
     */
    public function getChildPid()
    {
        return $this->childPid;
    }

    /**
     * @param int $childPid
     */
    public function setChildPid($childPid)
    {
        $this->childPid = $childPid;
    }

    /**
     * @return int
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * @param int $pid
     */
    public function setPid($pid)
    {
        $this->pid = $pid;
    }

    public function getAllPids()
    {
        $allPids = [];
        $allPids[] = $this->pid;
        $allPids[] = $this->childPid;

        return $allPids;
    }


}