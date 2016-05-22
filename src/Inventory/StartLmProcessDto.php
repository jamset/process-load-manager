<?php
/**
 * Created by PhpStorm.
 * User: ww
 * Date: 21.10.15
 * Time: 13:38
 */
namespace React\ProcessManager\Inventory;

class StartLmProcessDto extends ProcessDto
{
    /**
     * @var int
     */
    protected $pmPid;

    /**
     * @return int
     */
    public function getPmPid()
    {
        return $this->pmPid;
    }

    /**
     * @param int $pmPid
     */
    public function setPmPid($pmPid)
    {
        $this->pmPid = $pmPid;
    }

    public function getAllPids()
    {
        $pids = parent::getAllPids();
        $pids[] = $this->pmPid;

        return $pids;
    }


}