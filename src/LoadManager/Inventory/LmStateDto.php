<?php
/**
 * Created by PhpStorm.
 * User: ww
 * Date: 29.09.15
 * Time: 8:00
 */
namespace React\ProcessManager\LoadManager\Inventory;

use React\ProcessManager\LoadManager\Abstracts\BaseLmControlDto;

class LmStateDto extends BaseLmControlDto
{
    /**
     * @var bool
     */
    protected $allowGenerate = true;

    /**
     * @var bool
     */
    protected $allPidsFinished;

    /**
     * @var bool
     */
    protected $criticalFinish;

    /**
     * @var string
     */
    protected $criticalFinishReason;

    /**
     * @return string
     */
    public function getCriticalFinishReason()
    {
        return $this->criticalFinishReason;
    }

    /**
     * @param string $criticalFinishReason
     */
    public function setCriticalFinishReason($criticalFinishReason)
    {
        $this->criticalFinishReason = $criticalFinishReason;
    }

    /**
     * @return boolean
     */
    public function isCriticalFinish()
    {
        return $this->criticalFinish;
    }

    /**
     * @param boolean $criticalFinish
     */
    public function setCriticalFinish($criticalFinish)
    {
        $this->criticalFinish = $criticalFinish;
    }

    /**
     * @return boolean
     */
    public function isAllPidsFinished()
    {
        return $this->allPidsFinished;
    }

    /**
     * @param boolean $allPidsFinished
     */
    public function setAllPidsFinished($allPidsFinished)
    {
        $this->allPidsFinished = $allPidsFinished;
    }


    /**
     * @return boolean
     */
    public function isAllowGenerate()
    {
        return $this->allowGenerate;
    }

    /**
     * @param boolean $allowGenerate
     */
    public function setAllowGenerate($allowGenerate)
    {
        $this->allowGenerate = $allowGenerate;
    }

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


}