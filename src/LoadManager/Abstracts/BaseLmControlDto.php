<?php
/**
 * Created by PhpStorm.
 * User: ww
 * Date: 29.09.15
 * Time: 18:33
 */
namespace React\ProcessManager\LoadManager\Abstracts;

use React\FractalBasic\Interfaces\DtoContainerInterface;
use React\ProcessManager\LoadManager\Interfaces\LmControlDto;

abstract class BaseLmControlDto implements DtoContainerInterface, LmControlDto
{

    /**
     * @var int|float
     */
    protected $cpuUsage;

    /**
     * @var int|float
     */
    protected $residentMemoryUsage;

    /**
     * @var int
     */
    protected $activeProcessesNumber = 0;

    /**
     * @return int
     */
    public function getActiveProcessesNumber()
    {
        return $this->activeProcessesNumber;
    }

    /**
     * @param int $activeProcessesNumber
     */
    public function setActiveProcessesNumber($activeProcessesNumber)
    {
        $this->activeProcessesNumber = $activeProcessesNumber;
    }

    /**
     * @return float|int
     */
    public function getResidentMemoryUsage()
    {
        return $this->residentMemoryUsage;
    }

    /**
     * @param float|int $residentMemoryUsage
     */
    public function setResidentMemoryUsage($residentMemoryUsage)
    {
        $this->residentMemoryUsage = $residentMemoryUsage;
    }

    /**
     * @return float|int
     */
    public function getCpuUsage()
    {
        return $this->cpuUsage;
    }

    /**
     * @param float|int $cpuUsage
     */
    public function setCpuUsage($cpuUsage)
    {
        $this->cpuUsage = $cpuUsage;
    }


}