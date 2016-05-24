<?php
/**
 * Created by PhpStorm.
 * User: ww
 * Date: 27.09.15
 * Time: 19:52
 */
namespace React\ProcessManager\LoadManager\Inventory;

use React\FractalBasic\Interfaces\ManagerDto;
use React\ProcessManager\Abstracts\BasePmLmDto;

class LoadManagerDto extends BasePmLmDto implements ManagerDto
{
    /**
     * @var array
     */
    protected $pids;

    /**
     * @var int
     */
    protected $memFreeUsagePercentageLimit = 50;

    /**
     * @var int
     */
    protected $memFreeUsagePercentageLimitPerProcess;

    /**
     * @var int
     */
    protected $cpuUsagePercentageLimit = 100;

    /**
     * @var int
     */
    protected $cpuGapPercentage;

    /**Allow to set size, after which LM would not make reserved memory smaller
     * @var int
     */
    protected $allowedPercentageToSubtractFromReserved = 10;

    /**
     * @var int
     */
    protected $idleZeroValueCheckSizeBeforeAttention = 200;

    /**
     * @var bool
     */
    protected $pauseForbidden;

    /**
     * @var int Kb
     */
    protected $minMemFreeSize;

    /**
     * @var int Kb
     */
    protected $standardMemoryGap;

    /**
     * @return int
     */
    public function getStandardMemoryGap()
    {
        return $this->standardMemoryGap;
    }

    /**In Kb, allow to prevent creating new processes when load size will achieve size equal to "limit size - gap size"
     * @param int $standardMemoryGap
     */
    public function setStandardMemoryGap($standardMemoryGap)
    {
        $this->standardMemoryGap = $standardMemoryGap;
    }

    /**
     * @return int
     */
    public function getMinMemFreeSize()
    {
        return $this->minMemFreeSize;
    }

    /**
     * @param int $minMemFreeSize
     */
    public function setMinMemFreeSize($minMemFreeSize)
    {
        $this->minMemFreeSize = $minMemFreeSize;
    }

    /**
     * @return boolean
     */
    public function isPauseForbidden()
    {
        return $this->pauseForbidden;
    }

    /**
     * @param boolean $pauseForbidden
     */
    public function setPauseForbidden($pauseForbidden)
    {
        $this->pauseForbidden = $pauseForbidden;
    }

    /**
     * @return int
     */
    public function getIdleZeroValueCheckSizeBeforeAttention()
    {
        return $this->idleZeroValueCheckSizeBeforeAttention;
    }

    /**
     * @param int $idleZeroValueCheckSizeBeforeAttention
     */
    public function setIdleZeroValueCheckSizeBeforeAttention($idleZeroValueCheckSizeBeforeAttention)
    {
        $this->idleZeroValueCheckSizeBeforeAttention = $idleZeroValueCheckSizeBeforeAttention;
    }


    /**
     * @return int
     */
    public function getCpuGapPercentage()
    {
        return $this->cpuGapPercentage;
    }

    /**
     * @param int $cpuGapPercentage
     */
    public function setCpuGapPercentage($cpuGapPercentage)
    {
        $this->cpuGapPercentage = $cpuGapPercentage;
    }

    /**
     * @return int
     */
    public function getAllowedPercentageToSubtractFromReserved()
    {
        return $this->allowedPercentageToSubtractFromReserved;
    }

    /**
     * @param int $allowedPercentageToSubtractFromReserved
     */
    public function setAllowedPercentageToSubtractFromReserved($allowedPercentageToSubtractFromReserved)
    {
        $this->allowedPercentageToSubtractFromReserved = $allowedPercentageToSubtractFromReserved;
    }

    /**
     * @return array
     */
    public function getPids()
    {
        return $this->pids;
    }

    /**
     * @param array $pids
     */
    public function setPids($pids)
    {
        $this->pids = $pids;
    }

    /**
     * @return int
     */
    public function getMemFreeUsagePercentageLimit()
    {
        return $this->memFreeUsagePercentageLimit;
    }

    /**
     * @param int $memFreeUsagePercentageLimit
     */
    public function setMemFreeUsagePercentageLimit($memFreeUsagePercentageLimit)
    {
        $this->memFreeUsagePercentageLimit = $memFreeUsagePercentageLimit;
    }

    /**
     * @return int
     */
    public function getMemFreeUsagePercentageLimitPerProcess()
    {
        return $this->memFreeUsagePercentageLimitPerProcess;
    }

    /**
     * @param int $memFreeUsagePercentageLimitPerProcess
     */
    public function setMemFreeUsagePercentageLimitPerProcess($memFreeUsagePercentageLimitPerProcess)
    {
        $this->memFreeUsagePercentageLimitPerProcess = $memFreeUsagePercentageLimitPerProcess;
    }

    /**
     * @return int
     */
    public function getCpuUsagePercentageLimit()
    {
        return $this->cpuUsagePercentageLimit;
    }

    /**
     * @param int $cpuUsagePercentageLimit
     */
    public function setCpuUsagePercentageLimit($cpuUsagePercentageLimit)
    {
        $this->cpuUsagePercentageLimit = $cpuUsagePercentageLimit;
    }

}