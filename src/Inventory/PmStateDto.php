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

class PmStateDto implements DtoContainerInterface, PmControlDto
{
    /**
     * @var bool
     */
    protected $loopStop;

    /**
     * @var bool
     */
    protected $allProcessesCreated;

    /**
     * @return boolean
     */
    public function isLoopStop()
    {
        return $this->loopStop;
    }

    /**
     * @param boolean $loopStop
     */
    public function setLoopStop($loopStop)
    {
        $this->loopStop = $loopStop;
    }

    /**
     * @return boolean
     */
    public function isAllProcessesCreated()
    {
        return $this->allProcessesCreated;
    }

    /**
     * @param boolean $allProcessesCreated
     */
    public function setAllProcessesCreated($allProcessesCreated)
    {
        $this->allProcessesCreated = $allProcessesCreated;
    }


}