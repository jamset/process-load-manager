<?php
/**
 * Created by PhpStorm.
 * User: ww
 * Date: 27.09.15
 * Time: 19:51
 */
namespace React\ProcessManager\LoadManager;

use CommandsExecutor\CommandsManager;
use CommandsExecutor\Inventory\CpuMemDto;
use CommandsExecutor\Inventory\PidCpuMemDto;
use React\FractalBasic\Abstracts\BaseSubsidiary;
use React\FractalBasic\Interfaces\ReactManager;
use React\FractalBasic\Inventory\EventsConstants;
use React\FractalBasic\Inventory\LoggingExceptions;
use React\ProcessManager\LoadManager\Interfaces\Management as LmManagement;
use React\ProcessManager\LoadManager\Inventory\ContinueDto;
use React\ProcessManager\LoadManager\Inventory\Exceptions\LoadManagerException;
use React\ProcessManager\LoadManager\Inventory\Exceptions\LoadManagerInvalidArgument;
use React\ProcessManager\LoadManager\Inventory\LmConstants;
use React\ProcessManager\LoadManager\Inventory\LmStateDto;
use React\FractalBasic\Inventory\DtoContainer;
use React\ProcessManager\LoadManager\Inventory\PauseDto;
use React\ProcessManager\LoadManager\Inventory\TerminateDto;
use React\ProcessManager\Interfaces\PmControlDto;
use React\ProcessManager\Inventory\PmStateDto;
use React\ProcessManager\Inventory\ProcessDto;
use React\EventLoop\Timer\Timer;
use React\ProcessManager\LoadManager\Inventory\LoadManagerDto;
use React\ZMQ\Context;
use Log;

class Lm extends BaseSubsidiary implements LmManagement, ReactManager
{

    /**
     * @var \ZMQSocket
     */
    protected $replyToPmSocket;

    /**
     * @var array
     */
    protected $pids = [];

    /**
     * @var array
     */
    protected $selfPids = [];

    /**
     * @var DtoContainer
     */
    protected $dtoContainer;

    /**
     * @var LoadManagerDto
     */
    protected $loadManagerDto;

    /**
     * @var bool
     */
    protected $allProcessesCreated;

    /**
     * @var CommandsManager $commandsManager
     */
    protected $commandsManager;

    /**
     * @var CpuMemDto
     */
    protected $systemInfo;

    /**
     * @var array
     */
    protected $pidsInfo = [];

    /**
     * @var bool
     */
    protected $receivePmInfo;

    /**
     * @var int Kb
     */
    protected $pidsResidentMemorySum = 0;

    /**
     * @var float
     */
    protected $pidsCpuSum = 0.0;

    /**
     * @var int Kb
     */
    //protected $averageMemoryUsagePerPid = 0;

    /**
     * @var array
     */
    protected $averageMemoryUsagePerPidHistory = [];

    /**
     * @var array
     */
    protected $averageCpuUsagePerPidHistory = [];

    /**
     * @var float
     */
    //protected $averageCpuUsagePerPid = 0.0;

    /**
     * @var int Kb
     */
    protected $memFreeLimit = 0;

    /**
     * @var array
     */
    protected $finishedPids = [];

    /**
     * @var \ZMQSocket
     */
    protected $pmReplySocket;

    /**
     * @var bool
     */
    protected $firstMessage = true;

    /**
     * @var int
     */
    protected $coreNumber;

    /**
     * @var int
     */
    protected $coreTotalPercentage;

    /**
     * @var int
     */
    protected $cpuGapPercentage = 1;

    /**
     * @var float | int
     */
    protected $allowingCpuUsage;

    /**
     * @var int | float Kb
     */
    protected $reservedMemory;

    /**
     * @var int Kb
     */
    protected $reservedMemoryAtStart;

    /**
     * @var int Kb
     */
    protected $currentFreeMemoryForUncontrolledPids;

    /**
     * @var int Kb
     */
    protected $freeMemoryForUncontrolledPidsAtStart;

    /**
     * @var int
     */
    protected $freeMemoryForUncontrolledPidsMinPercentage = 10;

    /**
     * @var int | float Kb
     */
    protected $freeMemoryForUncontrolledPidsMinSize;

    /**
     * @var int Kb
     */
    protected $freeMemoryTotalAtStart;

    /**
     * @var int
     */
    protected $allowedPercentageToSubtractFromReserved = 30;

    /**
     * @var int
     */
    protected $minAllowedPercentageToSubtractFromReserved = 10;

    /**
     * @var array
     */
    protected $percentageToSubtractHistory = [];

    /**
     * @var int
     */
    protected $consideringToAverageNumber = 150;

    /**
     * @var int
     */
    protected $standardReserveGap = 10;

    /**
     * @var int Kb
     */
    protected $standardMemoryGap = 100000;

    /**
     * @var int Kb
     */
    protected $currentReserveGap;

    /**
     * @var int Kb
     */
    protected $minMemFreeSize = 300000;

    /**
     * @var bool
     */
    protected $minMemFreeSizeExceeded = false;

    /**
     * @var float | int
     */
    protected $criticalCpuUsage;

    /**
     * @var array
     */
    protected $pidsForPause = [];

    /**
     * @var int
     */
    protected $idleZeroValueIterationNumber = 0;

    /**
     * @var bool
     */
    protected $terminateDueToZeroIdle = false;

    /**
     * @var bool
     */
    protected $pauseForbidden = false;

    /**
     * @var bool
     */
    protected $finishingInitialized = false;

    /**
     * @var array
     */
    protected $selfPidsAtStart;

    /**
     * Send periodic signals about CPU and memory status; total and per PID
     */
    public function manage()
    {
        $this->initLoop();

        /**
         * @var \ZMQContext
         */
        $this->context = new Context($this->loop);
        $this->initStreams();

        $this->pmReplySocket = $this->context->getSocket(\ZMQ::SOCKET_REP);

        $this->dtoContainer = new DtoContainer();
        $this->dtoContainer->setDto(new LmStateDto());
        $this->commandsManager = new CommandsManager();

        $this->declareGettingLmParams();

        $this->managingTimer();

        $this->loop->run();

        return null;
    }

    protected function declareGettingLmParams()
    {
        /**
         * Receive sockets params from Pm and init socket connection
         */
        $this->readStream->on(EventsConstants::DATA, function ($loadManagerDto) {

            $lmDto = @unserialize($loadManagerDto);

            if ($lmDto instanceof LoadManagerDto && is_null($this->loadManagerDto)) {

                $this->loadManagerDto = $lmDto;

                $this->reactControlDto = $this->loadManagerDto;
                $this->initStartMethods();

                if ($this->loadManagerDto->getAllowedPercentageToSubtractFromReserved()) {

                    if ($this->loadManagerDto->getAllowedPercentageToSubtractFromReserved()
                        < $this->minAllowedPercentageToSubtractFromReserved
                    ) {
                        throw new LoadManagerException("Allowed percentage to subtract from reserved memory too small.");
                    }

                    $this->allowedPercentageToSubtractFromReserved = $this->loadManagerDto
                        ->getAllowedPercentageToSubtractFromReserved();
                }

                if ((is_int($this->loadManagerDto->getCpuGapPercentage()))
                    && ($this->loadManagerDto->getCpuGapPercentage() > $this->cpuGapPercentage)
                ) {
                    $this->cpuGapPercentage = $this->loadManagerDto->getCpuGapPercentage();
                }

                if ((is_int($this->loadManagerDto->getMinMemFreeSize()))
                    && ($this->loadManagerDto->getMinMemFreeSize() > $this->minMemFreeSize)
                ) {
                    $this->minMemFreeSize = $this->loadManagerDto->getMinMemFreeSize();
                }

                if ((is_int($this->loadManagerDto->getStandardMemoryGap()))
                    && ($this->loadManagerDto->getStandardMemoryGap() > $this->standardMemoryGap)
                ) {
                    $this->standardMemoryGap = $this->loadManagerDto->getStandardMemoryGap();
                }

                if ((is_bool($this->loadManagerDto->isPauseForbidden()))) {
                    $this->pauseForbidden = $this->loadManagerDto->isPauseForbidden();
                }


                $this->declareReplyToPm();

                $this->logger->info("LM start work.\n" . $this->loggerPostfix);
            }
        });

        return null;
    }

    protected function declareReplyToPm()
    {
        $this->replyToPmSocket = $this->context->getSocket(\ZMQ::SOCKET_REP);
        $this->replyToPmSocket->bind($this->loadManagerDto->getPmLmSocketsParams()->getPmLmRequestAddress());

        $this->replyToPmSocket->on(EventsConstants::ERROR, function (\Exception $e) {
            $this->logger->error(LoggingExceptions::getExceptionString($e));
        });

        $this->replyToPmSocket->on(EventsConstants::MESSAGE, function ($receivedDtoContainer) {

            /**
             * @var DtoContainer $dtoContainer
             */
            $dtoContainer = unserialize($receivedDtoContainer);

            $this->processReceivedControlDto($dtoContainer->getDto());

            $this->receivePmInfo = true;

            $this->replyToPmSocket->send(serialize($this->dtoContainer));
        });

        return null;
    }

    public function processReceivedControlDto(PmControlDto $receivedControlDto)
    {
        switch (true) {
            case($receivedControlDto instanceof ProcessDto):

                if ($this->firstMessage) {
                    $this->firstMessage = false;

                    /**
                     * @var ProcessDto $receivedControlDto
                     */
                    if (!(empty($receivedControlDto->getAllPids()))) {
                        $this->logger->emergency("Received first message from PM: " . serialize($receivedControlDto->getAllPids()));

                        foreach ($receivedControlDto->getAllPids() as $pid) {
                            $this->selfPids[$pid] = $pid;
                        }

                        $this->selfPidsAtStart = $this->selfPids;
                    }

                } else {

                    $this->logger->info("LM receive from PM:" . serialize($receivedControlDto));

                    /**
                     * @var ProcessDto $receivedControlDto
                     */
                    if (!(empty($receivedControlDto->getAllPids()))) {
                        $this->logger->emergency("PIDS from PM (ProcessDto): " . serialize($receivedControlDto->getAllPids()));

                        foreach ($receivedControlDto->getAllPids() as $pid) {
                            $this->pids[$pid] = $pid;
                        }
                    }
                }

                break;
            case($receivedControlDto instanceof PmStateDto):

                $this->logger->info("LM receive from PM:" . serialize($receivedControlDto));

                /**
                 * @var PmStateDto $receivedControlDto

                if ($receivedControlDto->isLoopStop()) {
                 * $this->loop->stop();
                 * }*/

                if ($receivedControlDto->isAllProcessesCreated()) {
                    $this->logger->info("LM receive from PM all processes created.");
                    $this->allProcessesCreated = $receivedControlDto->isAllProcessesCreated();
                }
                break;
            default:
                throw new LoadManagerInvalidArgument("Unknown control dto: " . serialize($receivedControlDto));
        }

        return null;
    }

    protected function managingTimer()
    {
        $this->loop->addPeriodicTimer(0.01, function (Timer $timer) {

            if ($this->receivePmInfo) {

                //to protect against periodic income before send and thus against calculation duplicate
                $this->receivePmInfo = false;

                $this->systemInfo = $this->commandsManager->getSystemLoadInfo();
                $this->checkCpuIdle();

                $this->pidsInfo = [];

                $this->logger->info("Pids contain before tryGetPidInfo (number): " . count($this->pids));

                if (!empty($this->pids)) {
                    foreach ($this->pids as $key => $pid) {
                        $this->tryGetPidInfo($key, $pid);
                    }
                }

                foreach ($this->selfPids as $key => $pid) {
                    $this->tryGetPidInfo($key, $pid, true);
                }

                $this->logger->info("Pids contain after tryGetPidInfo (number): " . count($this->pids));
                $this->logger->info("PidsInfo contain (number): " . count($this->pidsInfo));
                //$this->logger->info("Self pids at start: " . serialize($this->selfPidsAtStart));

                if (empty($this->pids) === false) {

                    $this->logger->info("Come to resolve pids info.");
                    $this->resolveLoadInfo();

                } elseif ($this->allProcessesCreated === true) {

                    if (!$this->finishingInitialized) {

                        $this->logger->critical("Come to finish dto.");

                        $this->logger->critical("Pids: " . serialize($this->pids));
                        $this->logger->critical("PidsInfo: " . serialize($this->pidsInfo));
                        $this->logger->critical("FinishedPids: " . serialize($this->finishedPids));
                        $this->logger->critical("SelfPids: " . serialize($this->selfPids));

                        $this->initFinishDto();
                    }
                } else {

                    $this->logger->warning("LM pids empty. Check if exceeded minMemLimit");

                    if ($this->systemInfo->getMemFree() < $this->minMemFreeSize) {

                        $reason = "LM pids empty and MemFree is on critical level: " . $this->systemInfo->getMemFree()
                            . " Init critical finishing.";

                        $this->logger->warning($reason);
                        $this->initCriticalFinishDto($reason);
                    } else {
                        $this->dtoContainer->setDto(new LmStateDto());
                    }

                }
            }
        });

        return null;
    }

    protected function checkCpuIdle()
    {
        if ($this->systemInfo->getCpuIdle() < LmConstants::IDLE_MIN_SIZE) {

            $this->logger->emergency("CpuIdle less than idle min size(" . LmConstants::IDLE_MIN_SIZE . "): "
                . $this->systemInfo->getCpuIdle());
            $this->idleZeroValueIterationNumber++;

            if ($this->idleZeroValueIterationNumber === $this->loadManagerDto->getIdleZeroValueCheckSizeBeforeAttention()) {
                $this->logger->emergency("Idle is zero due to " .
                    $this->loadManagerDto->getIdleZeroValueCheckSizeBeforeAttention()
                    . " attempts. Please check CPU usage.");
            }

        } else {
            if ($this->idleZeroValueIterationNumber !== 0) {
                $this->idleZeroValueIterationNumber = 0;
            }
        }

        return null;
    }

    protected function tryGetPidInfo($key, $pid, $self = null)
    {
        $attempts = 0;
        $maxAttempts = 3;

        do {

            try {

                $pidInfo = $this->commandsManager->getPidLoadInfo($pid);

                if ($self) {
                    $this->pidsInfo[LmConstants::SELF_PID . "$key"] = $pidInfo;
                } else {
                    $this->pidsInfo[] = $pidInfo;

                }

                $attempts = $maxAttempts;

            } catch (\Exception $e) {

                $attempts++;
                $this->logger->error("Attempts to check info for PID $pid: $attempts");

                if ($attempts === $maxAttempts) {
                    $this->logger->error(LoggingExceptions::getExceptionString($e));
                    $this->finishedPids[] = $pid;
                    unset($this->pids[$key]);
                }

            }

        } while ($attempts < $maxAttempts);

        return null;
    }

    protected function resolveLoadInfo()
    {
        $this->logger->emergency("PidsInfo (number): " . count($this->pidsInfo));
        $this->logger->emergency("SystemInfo: " . serialize($this->systemInfo));

        $this->getPidsUsageSum();
        $this->calculateAverageUsage();
        $this->resolveValuesAndLimits();

        return null;
    }

    protected function getPidsUsageSum()
    {
        /**
         * @var PidCpuMemDto $pidInfo
         */
        foreach ($this->pidsInfo as $pidInfo) {
            $this->pidsResidentMemorySum += $pidInfo->getPidResidentMemoryUsage();
            $this->pidsCpuSum += $pidInfo->getPidCpuUsage();
        }

        return null;
    }

    protected function calculateAverageUsage()
    {
        $pidsNumber = count($this->pids);

        if ($pidsNumber > 0) {

            $this->handleHistoryArray("averageMemoryUsagePerPidHistory", $this->pidsResidentMemorySum / $pidsNumber);
            $this->handleHistoryArray("averageCpuUsagePerPidHistory", $this->pidsCpuSum / $pidsNumber);

        }

        return null;
    }

    protected function resolveValuesAndLimits()
    {
        $sentDto = null;

        $this->handleReservedMemory();

        if (!$this->coreNumber) {
            $this->initCoreUsageInfo();
        }

        if (!$this->criticalCpuUsage) {
            $this->initCriticalCpuUsage();
        }

        $this->logResolveInfo();

        $this->logger->emergency("Come to resolve values and limits.");

        $this->logger->emergency("Current cpu usage limit: " . $this->loadManagerDto->getCpuUsagePercentageLimit());
        $this->logger->emergency("Current cpu core total: " . $this->coreTotalPercentage);
        $this->logger->emergency("Current allowing cpu usage: " . $this->allowingCpuUsage);
        $this->logger->emergency("PIDs sum: " . $this->pidsCpuSum);

        $sentDto = new LmStateDto();
        $sentDto->setAllowGenerate(false);

        if ($this->systemInfo->getMemFree() < $this->minMemFreeSize) {

            $this->handleCriticalMemFreeSize();

        } elseif (($this->pidsResidentMemorySum < $this->reservedMemory)
            && ($this->pidsCpuSum <= $this->allowingCpuUsage)
        ) {

            $averagePercentageToSubtraction = $this->getAveragePercentageToSubtraction();
            $this->currentReserveGap = ($this->reservedMemory * $averagePercentageToSubtraction) / 100;

            $this->logger->emergency("Average percentage to subtraction: " . $averagePercentageToSubtraction);
            $this->logger->emergency("Reserve gap (Kb): " . $this->currentReserveGap);

            if (
                ($this->pidsResidentMemorySum
                    + ((array_sum($this->averageMemoryUsagePerPidHistory)
                            / count($this->averageMemoryUsagePerPidHistory)) * 2)
                    + $this->standardMemoryGap
                )
                < ($this->reservedMemory - $this->currentReserveGap)
                && (((array_sum($this->averageCpuUsagePerPidHistory) / count($this->averageCpuUsagePerPidHistory))
                        + $this->pidsCpuSum) <= $this->allowingCpuUsage)
                && (empty($this->pidsForPause))
            ) {
                $sentDto->setAllowGenerate(true);
                $this->logLmStateSentDto($sentDto);

            } else {

                if (!empty($this->pidsForPause)) {

                    $this->logger->emergency("Come into continue if.");
                    $this->logger->emergency("Pause pids: " . serialize($this->pidsForPause));
                    /**
                     * @var ContinueDto $continueDto
                     */
                    $continueDto = $this->calculateProcessesNumberToContinue();

                    if (!empty($continueDto->getPidsForContinue())) {

                        $this->logger->emergency("Pids for continue: " . serialize($continueDto));
                        $sentDto = $continueDto;
                    }

                } else {
                    $sentDto->setAllowGenerate(false);
                    $this->logLmStateSentDto($sentDto);
                }
            }

        } else {

            $this->logger->emergency("Come into pause/termination if.");

            if ($this->pidsResidentMemorySum > $this->reservedMemory) {
                $this->logger->emergency("Come into termination if.");

                $sentDto = $this->calculateProcessesNumberToTermination();

            } elseif (
                ($this->pidsCpuSum >= $this->criticalCpuUsage)
                && ($this->idleZeroValueIterationNumber > $this->loadManagerDto->getIdleZeroValueCheckSizeBeforeAttention()
                )
            ) {

                $this->logger->emergency("Come into termination due to CPU.");
                $sentDto = $this->calculateProcessesNumberToTerminationDueToCpu();

            } else {

                if ($this->pauseForbidden === false) {
                    $this->logger->emergency("Come into pause if.");
                    $sentDto = $this->calculateProcessesNumberToPause();
                }

            }
        }

        $sentDto->setActiveProcessesNumber(count($this->pids));
        $sentDto->setCpuUsage($this->pidsCpuSum);
        $sentDto->setResidentMemoryUsage($this->pidsResidentMemorySum);

        $this->dtoContainer->setDto($sentDto);

        $this->clearIterationInfo();

        return null;
    }

    protected function handleCriticalMemFreeSize()
    {
        $this->logger->emergency("Free mem become less than min size");
        $this->minMemFreeSizeExceeded = true;
        $this->calculateProcessesNumberToTermination();

        return null;
    }

    protected function calculateProcessesNumberToTerminationDueToIdle()
    {
        $terminateDto = new TerminateDto();

        $terminateDto->setPidsForTerminate($this->getMaxCpuUsagePid());

        return $terminateDto;
    }

    protected function getMaxCpuUsagePid()
    {
        $pids = [];

        $sortedPids = $this->getSortedPids();
        $this->logger->emergency("SORTED PIDS INFO: " . serialize($sortedPids));

        $pids[] = array_shift($sortedPids);

        return $pids;
    }

    protected function getSortedPids()
    {
        $pidsInfoDuplicate = $this->pidsInfo;
        usort($pidsInfoDuplicate, function ($a, $b) {

            /**
             * @var PidCpuMemDto $a
             * @var PidCpuMemDto $b
             */
            if ($a->getPidCpuUsage() === $b->getPidCpuUsage()) {
                return 0;
            }

            return ($a->getPidCpuUsage() < $b->getPidCpuUsage()) ? 1 : -1;
        });


        return $pidsInfoDuplicate;
    }

    protected function calculateProcessesNumberToContinue()
    {
        $continueDto = new ContinueDto();

        $possiblePidUsage = 0;
        $pidsForContinue = [];

        /**
         * @var PidCpuMemDto $pidInfo
         */
        foreach ($this->pidsForPause as $pidInfo) {
            if (($possiblePidUsage + $pidInfo->getPidCpuUsage()) < $this->allowingCpuUsage) {
                $possiblePidUsage += $pidInfo->getPidCpuUsage();
                $pidsForContinue[] = $pidInfo->getPid();
                unset($this->pidsForPause[$pidInfo->getPid()]);
            } else {
                break;
            }
        }

        $continueDto->setPidsForContinue($pidsForContinue);

        return $continueDto;
    }

    protected function initCoreUsageInfo()
    {
        $this->coreNumber = $this->commandsManager->getCoreNumber();
        $this->coreTotalPercentage = $this->coreNumber * 100;
        $this->allowingCpuUsage = $this->coreTotalPercentage * $this->loadManagerDto->getCpuUsagePercentageLimit() / 100;

        $this->logger->emergency("Core number: " . $this->coreNumber);

        return null;
    }

    protected function calculateProcessesNumberToTerminationDueToCpu()
    {
        $terminateDto = new TerminateDto();

        $cpuExceeded = $this->pidsCpuSum - $this->allowingCpuUsage;
        if ($cpuExceeded > 0) {
            $pidsForTerminate = $this->getPidsForAction($cpuExceeded, LmConstants::CPU);

            $this->logActionDueToCpuInfo($cpuExceeded, 'termination');
            $terminateDto->setPidsForTerminate($pidsForTerminate);
        }

        return $terminateDto;
    }

    protected function calculateProcessesNumberToPause()
    {
        $pauseDto = new PauseDto();

        $cpuExceeded = $this->pidsCpuSum - $this->allowingCpuUsage;

        if ($cpuExceeded > 0) {

            $pidsForPause = $this->getPidsForAction($cpuExceeded, LmConstants::CPU, 'pause');

            $this->logActionDueToCpuInfo($cpuExceeded, 'pause');
            $this->logPause();

            $pauseDto->setPidsForPause($pidsForPause);
        }

        return $pauseDto;
    }

    protected function getPidsForAction($paramExceeded, $usageType, $pidsForPause = null)
    {
        $pidsUsage = 0;
        $pidsForAction = [];

        if (empty($this->pidsInfo)) {
            throw new LoadManagerException("PidsInfo empty, but PIDs exceeded usage limits. Unknown error.");
        }

        /**
         * @var PidCpuMemDto $pidInfo
         */
        foreach ($this->pidsInfo as $key => $pidInfo) {

            if (strpos($key, LmConstants::SELF_PID) !== false) {
                $this->logger->emergency("Continue due to self pids.");
                continue;
            }

            $pidsUsage += ($usageType === LmConstants::CPU) ? $pidInfo->getPidCpuUsage() : $pidInfo->getPidResidentMemoryUsage();
            $pidsForAction[] = $pidInfo->getPid();

            if ($pidsForPause) {
                $this->pidsForPause[$pidInfo->getPid()] = $pidInfo;
            }

            if ($pidsUsage > $paramExceeded) {
                break;
            }
        }

        return $pidsForAction;
    }

    protected function handleReservedMemory()
    {
        if (!$this->freeMemoryTotalAtStart) {
            $this->freeMemoryTotalAtStart = $this->systemInfo->getMemFree();

            $this->logger->emergency("Free memory total at start: " . $this->freeMemoryTotalAtStart);
        }

        if (!$this->reservedMemory) {

            $this->reservedMemory = $this->getReservingMemory();
            $this->logger->emergency("Set reservedMemory: " . $this->reservedMemory);

            if (!$this->reservedMemoryAtStart) {
                $this->reservedMemoryAtStart = $this->reservedMemory;

                $this->logger->emergency("Set reservedMemory at Start: " . $this->reservedMemory);
            }
        }

        if (!$this->freeMemoryForUncontrolledPidsAtStart) {
            $this->freeMemoryForUncontrolledPidsAtStart = $this->freeMemoryTotalAtStart - $this->reservedMemoryAtStart;

            $this->freeMemoryForUncontrolledPidsMinSize =
                ($this->freeMemoryForUncontrolledPidsAtStart * $this->freeMemoryForUncontrolledPidsMinPercentage) / 100;
        }

        $this->logger->emergency("Free memory for uncontrolled pids as START: " . $this->freeMemoryForUncontrolledPidsAtStart);
        $this->logger->emergency("Free memory for uncontrolled pids min size: " . $this->freeMemoryForUncontrolledPidsMinSize);

        $this->currentFreeMemoryForUncontrolledPids = $this->systemInfo->getMemFree();

        $this->logger->emergency("Current free memory for uncontrolled pids: " . $this->currentFreeMemoryForUncontrolledPids);

        if ($this->currentFreeMemoryForUncontrolledPids < $this->freeMemoryForUncontrolledPidsMinSize) {

            $this->logger->emergency("Free memory for uncontrolled PIDs become smaller than min size for it.");
            $uncontrolledFreeMemDiffSize = $this->freeMemoryForUncontrolledPidsMinSize - $this->currentFreeMemoryForUncontrolledPids;

            $this->logger->emergency("Uncontrolled free mem diff size: " . $uncontrolledFreeMemDiffSize);

            $percentageToSubtraction = ($uncontrolledFreeMemDiffSize * 100) / $this->reservedMemoryAtStart;

            $this->handleHistoryArray("percentageToSubtractHistory", $percentageToSubtraction);

            $this->logger->emergency("Percentage to subtraction: " . $percentageToSubtraction);

            if ($percentageToSubtraction > $this->allowedPercentageToSubtractFromReserved) {
                $this->logger->emergency("Percentage to subtraction from reserve bigger "
                    . "than allowed. Percentage to subtraction: " . $percentageToSubtraction
                    . ". Allowed percentage to subtraction: " . $this->allowedPercentageToSubtractFromReserved . "
                    Subtract min size: " . $this->freeMemoryForUncontrolledPidsMinSize);
                $this->reservedMemory -= $this->freeMemoryForUncontrolledPidsMinSize;
            } else {
                $this->reservedMemory -= $uncontrolledFreeMemDiffSize;
                $this->logger->emergency("Reserved memory was reduced: " . $this->reservedMemory);
                $this->logger->emergency("Reserved memory before reducing: " . $this->reservedMemoryAtStart);
                $this->logger->emergency("Allowed percentage to subtraction: " . $this->allowedPercentageToSubtractFromReserved);
            }
        } elseif ($this->currentFreeMemoryForUncontrolledPids >= $this->freeMemoryForUncontrolledPidsAtStart) {

            $canBeReserved = $this->getReservingMemory();

            //for case, when uncontrolled PIDs terminated or make free memory bigger during execution
            if ($canBeReserved > $this->reservedMemory) {
                $this->reservedMemory = $canBeReserved;
                $this->logger->emergency("Reserved memory was increased.");
            }

        }

        return null;
    }

    protected function handleHistoryArray($historyArrName, $item)
    {
        if (count($this->{$historyArrName}) < $this->consideringToAverageNumber) {
            $this->{$historyArrName}[] = $item;
        } else {
            array_shift($this->{$historyArrName});
            $this->{$historyArrName}[] = $item;
        }

        return null;
    }

    protected function getAveragePercentageToSubtraction()
    {
        $result = null;

        $percentageToSubtractSum = array_sum($this->percentageToSubtractHistory);
        $percentageToSubtractNumber = count($this->percentageToSubtractHistory);

        $this->logger->emergency("Percentage to subtraction sum: " . $percentageToSubtractSum);
        $this->logger->emergency("Percentage to subtraction number: " . $percentageToSubtractNumber);

        if ($percentageToSubtractNumber) {
            $result = ($percentageToSubtractSum / $percentageToSubtractNumber) / 2;
            $this->logger->emergency("Use percentage to subtract params");
        } else {
            $this->logger->emergency("Use standard reserve gap: " . $this->standardReserveGap);
            $result = $this->standardReserveGap;
        }

        return $result;
    }

    protected function getReservingMemory()
    {
        return ($this->systemInfo->getMemFree() * $this->loadManagerDto->getMemFreeUsagePercentageLimit()) / 100;
    }

    protected function calculateProcessesNumberToTermination()
    {
        $terminateDto = new TerminateDto();

        if ($this->minMemFreeSizeExceeded) {
            $memExceeded = ($this->minMemFreeSize - $this->systemInfo->getMemFree()) + $this->currentReserveGap;
            $this->logger->emergency("Exceeded mem: " . $memExceeded);

        } else {
            $memExceeded = $this->pidsResidentMemorySum - $this->reservedMemory;
        }

        if ($memExceeded > 0) {

            $pidsForTerminate = $this->getPidsForAction($memExceeded, LmConstants::MEMORY);
            $this->logTerminationInfo($memExceeded);

            $terminateDto->setPidsForTerminate($pidsForTerminate);
        }

        return $terminateDto;
    }

    protected function initCriticalCpuUsage()
    {
        $this->criticalCpuUsage = ($this->coreTotalPercentage - (($this->coreTotalPercentage * $this->cpuGapPercentage) / 100));

        return null;
    }

    protected function initCriticalFinishDto($reason = null)
    {
        $lmStateDto = new LmStateDto();
        $lmStateDto->setAllowGenerate(false);
        $lmStateDto->setCriticalFinish(true);
        $lmStateDto->setCriticalFinishReason($reason);

        $this->initFinish($lmStateDto);

        return null;
    }

    protected function initFinishDto()
    {
        $lmStateDto = new LmStateDto();

        $lmStateDto->setAllowGenerate(false);
        $lmStateDto->setAllPidsFinished(true);

        $this->initFinish($lmStateDto);

        return null;
    }

    protected function initFinish(LmStateDto $lmStateDto)
    {
        $this->dtoContainer->setDto($lmStateDto);

        $this->loop->nextTick(function () {
            $this->loop->stop();
        });

        $this->finishingInitialized = true;

        return null;
    }

    protected function clearIterationInfo()
    {
        $this->pidsResidentMemorySum = 0;
        $this->pidsCpuSum = 0;
        $this->memFreeLimit = 0;
        $this->minMemFreeSizeExceeded = false;

        return null;
    }

    protected function logTerminationInfo($memExceeded)
    {
        $this->logger->emergency("Come into termination processes calculation.\n");
        $this->logger->emergency("PidsMemorySum = " . $this->pidsResidentMemorySum);
        $this->logger->emergency("Reserved memory = " . $this->reservedMemory);
        $this->logger->emergency("MemFree from systemInfo = " . $this->systemInfo->getMemFree());
        $this->logger->emergency("MemExceeded: " . $memExceeded);

        return null;
    }

    protected function logActionDueToCpuInfo($cpuExceeded, $action = 'action')
    {
        $this->logger->emergency("Come into $action due to cpu usage.\n");
        $this->logger->emergency("Pids cpu sum before termination: " . $this->pidsCpuSum);
        $this->logger->emergency("CpuExceeded: " . $cpuExceeded);

        return null;
    }

    protected function logPause()
    {
        $this->logger->emergency("Paused processes number: " . count($this->pidsForPause));

        return null;
    }

    protected function logResolveInfo()
    {
        $this->logger->emergency("CpuIdle from systemInfo = " . $this->systemInfo->getCpuIdle());
        $this->logger->emergency("Idle zero value iteration number: " . $this->idleZeroValueIterationNumber);
        $this->logger->emergency("PidsNumber = " . count($this->pids));
        $this->logger->emergency("StandardMemoryGap (Kb) = " . $this->standardMemoryGap);
        $this->logger->emergency("PidsMemorySum = " . $this->pidsResidentMemorySum);
        $this->logger->emergency("Reserved memory = " . $this->reservedMemory);
        $this->logger->emergency("Reserved memory at start = " . $this->reservedMemoryAtStart);
        $this->logger->emergency("Memory for uncontrolled PIDs at start = " . $this->freeMemoryForUncontrolledPidsAtStart);
        $this->logger->emergency("MemFree at start: " . $this->freeMemoryTotalAtStart);
        $this->logger->emergency("MemFree from systemInfo = " . $this->systemInfo->getMemFree());
        $this->logger->emergency("AverageMemoryHistory: " . (array_sum($this->averageMemoryUsagePerPidHistory) / count($this->averageMemoryUsagePerPidHistory)));
        $this->logger->emergency("AverageCpuHistory: " . (array_sum($this->averageCpuUsagePerPidHistory) / count($this->averageCpuUsagePerPidHistory)));

        return null;
    }

    protected function logLmStateSentDto(LmStateDto $lmStateDto)
    {
        $this->logger->emergency("Allow generate new process: " . serialize($lmStateDto->isAllowGenerate()));

        return null;
    }


}
