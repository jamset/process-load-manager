<?php
/**
 * Created by PhpStorm.
 * User: ww
 * Date: 27.09.15
 * Time: 19:34
 */
namespace React\ProcessManager\Inventory;

use React\FractalBasic\Interfaces\ManagerDto;
use React\ProcessManager\Abstracts\BasePmLmDto;
use React\ProcessManager\LoadManager\Inventory\LoadManagerDto;
use App\FWIndependent\React\PublisherPulsar\Inventory\PerformerSocketsParamsDto;

class ProcessManagerDto extends BasePmLmDto implements ManagerDto
{
    /**
     * @var int
     */
    protected $tasksNumber;

    /**
     * @var string
     */
    protected $workerProcessCommand;

    /**
     * @var string
     */
    protected $loadManagerProcessCommand;

    /**
     * @var LoadManagerDto
     */
    protected $loadManagerDto;

    /**
     * @var PerformerSocketsParamsDto
     */
    protected $performerSocketsParams;

    /**Allow to limit processes number per ProcessManager
     * (per container/server if one PM launches per one node)
     * @var int
     */
    protected $maxSimultaneousProcesses;

    /**Interval of PM/LM message communication
     * @var int microseconds
     */
    protected $interMessageInterval = 1000000;

    /**
     * @return int
     */
    public function getInterMessageInterval()
    {
        return $this->interMessageInterval;
    }

    /**
     * @param int $interMessageInterval
     */
    public function setInterMessageInterval($interMessageInterval)
    {
        $this->interMessageInterval = $interMessageInterval;
    }

    /**
     * @return int
     */
    public function getMaxSimultaneousProcesses()
    {
        return $this->maxSimultaneousProcesses;
    }

    /**
     * @param int $maxSimultaneousProcesses
     */
    public function setMaxSimultaneousProcesses($maxSimultaneousProcesses)
    {
        $this->maxSimultaneousProcesses = $maxSimultaneousProcesses;
    }

    /**
     * @return PerformerSocketsParamsDto
     */
    public function getPerformerSocketsParams()
    {
        return $this->performerSocketsParams;
    }

    /**
     * @param PerformerSocketsParamsDto $performerSocketsParams
     */
    public function setPerformerSocketsParams(PerformerSocketsParamsDto $performerSocketsParams)
    {
        $this->performerSocketsParams = $performerSocketsParams;
    }

    /**
     * @return LoadManagerDto
     */
    public function getLoadManagerDto()
    {
        return $this->loadManagerDto;
    }

    /**
     * @param LoadManagerDto $loadManagerDto
     */
    public function setLoadManagerDto(LoadManagerDto $loadManagerDto)
    {
        $this->loadManagerDto = $loadManagerDto;
    }

    /**
     * @return string
     */
    public function getLoadManagerProcessCommand()
    {
        return $this->loadManagerProcessCommand;
    }

    /**
     * @param string $loadManagerProcessCommand
     */
    public function setLoadManagerProcessCommand($loadManagerProcessCommand)
    {
        $this->loadManagerProcessCommand = $loadManagerProcessCommand;
    }


    /**
     * @return int
     */
    public function getTasksNumber()
    {
        return $this->tasksNumber;
    }

    /**
     * @param int $tasksNumber
     */
    public function setTasksNumber($tasksNumber)
    {
        $this->tasksNumber = $tasksNumber;
    }

    /**
     * @return string
     */
    public function getWorkerProcessCommand()
    {
        return $this->workerProcessCommand;
    }

    /**
     * @param string $workerProcessCommand
     */
    public function setWorkerProcessCommand($workerProcessCommand)
    {
        $this->workerProcessCommand = $workerProcessCommand;
    }


}