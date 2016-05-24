<?php
/**
 * Created by PhpStorm.
 * User: ww
 * Date: 27.09.15
 * Time: 19:31
 */
namespace React\ProcessManager;

use CommandsExecutor\CommandsManager;
use CommandsExecutor\Inventory\PidDto;
use React\FractalBasic\Abstracts\BaseReactControl;
use React\FractalBasic\Interfaces\ReactManager;
use React\FractalBasic\Inventory\DtoContainer;
use React\FractalBasic\Inventory\EventsConstants;
use React\FractalBasic\Inventory\Exceptions\ExceptionsConstants;
use React\FractalBasic\Inventory\Exceptions\ReactManagerException;
use React\FractalBasic\Inventory\LoggingExceptions;
use React\ProcessManager\Inventory\TerminatorPauseStanderConstants;
use React\ProcessManager\Inventory\StartLmProcessDto;
use React\ProcessManager\LoadManager\Interfaces\LmControlDto;
use React\ProcessManager\LoadManager\Inventory\ContinueDto;
use React\ProcessManager\LoadManager\Inventory\LmStateDto;
use React\ProcessManager\LoadManager\Inventory\PauseDto;
use React\ProcessManager\LoadManager\Inventory\TerminateDto;
use React\ProcessManager\Interfaces\Management as PmManagement;
use React\ProcessManager\Inventory\Exceptions\ProcessManagerException;
use React\ProcessManager\Inventory\Exceptions\ProcessManagerInvalidArgument;
use React\ProcessManager\Inventory\ProcessDto;
use React\ProcessManager\Inventory\ProcessManagerDto;
use React\ProcessManager\Inventory\PmStateDto;
use React\ProcessManager\Inventory\DataTransferConstants;
use TasksInspector\Inventory\ExecutionDto;
use React\ChildProcess\Process;
use React\EventLoop\Timer\Timer;
use React\ZMQ\Context;

class Pm extends BaseReactControl implements PmManagement, ReactManager
{
    /**
     * @var array
     */
    protected $pids = [];

    /**
     * @var DtoContainer
     */
    protected $dtoContainer;

    /**
     * @var ProcessManagerDto
     */
    protected $processManagerDto;

    /**
     * @var \ZMQSocket
     */
    protected $lmRequesterSocket;

    /**
     * @var \ZMQSocket
     */
    protected $workersPublisherSocket;

    /**
     * @var array
     */
    protected $processes = [];

    /**
     * @var int
     */
    protected $tasks;

    /**
     * @var CommandsManager
     */
    protected $commandsManager;

    /**
     * @var int
     */
    protected $requestNumber = 0;

    /**
     * @var int
     */
    protected $currentCpuUsage = 0;

    /**
     * @var int
     */
    protected $currentResidentMemoryUsage = 0;

    /**
     * @var int seconds
     */
    protected $interMessageInterval = 1;

    /**
     * @var Process
     */
    protected $loadManagerProcess;

    /**
     * @var bool
     */
    protected $allowSending = true;

    /**
     * @var ExecutionDto
     */
    protected $executionDto;

    /**
     * @var int
     */
    protected $loadManagerPid;

    /**
     * @var bool
     */
    protected $allTasksCreated = false;

    /**
     * @var bool
     */
    protected $sigTermBlockingAgent;

    /**
     * @return boolean
     */
    public function isSigTermBlockingAgent()
    {
        return $this->sigTermBlockingAgent;
    }

    /**
     * @param boolean $sigTermBlockingAgent
     */
    public function setSigTermBlockingAgent($sigTermBlockingAgent)
    {
        $this->sigTermBlockingAgent = $sigTermBlockingAgent;
    }

    /**
     * @return boolean
     */
    public function isAllTasksCreated()
    {
        return $this->allTasksCreated;
    }

    /**
     * @return ExecutionDto
     */
    public function getExecutionDto()
    {
        return $this->executionDto;
    }

    /**
     * @param ExecutionDto $executionDto
     */
    public function setExecutionDto($executionDto)
    {
        $this->executionDto = $executionDto;
    }

    /**
     * @return ProcessManagerDto
     */
    public function getProcessManagerDto()
    {
        return $this->processManagerDto;
    }

    /**
     * @param ProcessManagerDto $processManagerDto
     */
    public function setProcessManagerDto($processManagerDto)
    {
        $this->processManagerDto = $processManagerDto;
    }

    /**Manage processes creation and termination during to load manager info and tasks number
     * @param ProcessManagerDto $processManagerDto
     */
    public function manage()
    {
        if (!($this->processManagerDto instanceof ProcessManagerDto)) {
            throw new ProcessManagerException("ProcessManagerDto wasn't set.");
        }

        $this->executionDto = new ExecutionDto();
        $this->reactControlDto = $this->processManagerDto;
        $this->initStartMethods();
        $this->context = new Context($this->loop);

        $this->dtoContainer = new DtoContainer();
        $this->tasks = $this->processManagerDto->getTasksNumber();
        $this->commandsManager = new CommandsManager();
        $this->logger->info("PM start work." . $this->loggerPostfix);

        $this->initWorkersPublisher();
        $this->declareLmRequester();

        $this->initLoadManager();
        $this->initLoadManagingControl();

        $this->loop->run();

        return null;
    }

    protected function initWorkersPublisher()
    {
        $this->workersPublisherSocket = $this->context->getSocket(\ZMQ::SOCKET_PUB);
        $this->workersPublisherSocket->bind($this->processManagerDto->getPerformerSocketsParams()->getPublisherPmSocketAddress());

        return null;
    }

    protected function initLoadManager()
    {
        $this->loop->addTimer(0.1, function (Timer $timer) {

            $this->loadManagerProcess = new Process($this->processManagerDto->getLoadManagerProcessCommand());
            $this->loadManagerProcess->start($this->loop);

            $this->loadManagerProcess->stderr->on(EventsConstants::DATA, function ($data) {
                $this->logger->critical("LM STDERR data event." . serialize($data) . $this->loggerPostfix);

                throw new ProcessManagerException("Unknown error in Lm. " . serialize($data));
            });

            $this->loadManagerProcess->stdout->on(EventsConstants::DATA, function ($data) {
                $prefix = "LM STDOUT data event.";
                $stdOutData = @json_decode($data);
                if ($stdOutData) {
                    //$this->logger->warning($prefix . serialize($stdOutData) . $this->loggerPostfix);
                } else {
                    //$this->logger->critical($prefix . serialize($data) . $this->loggerPostfix);
                }
            });

            $this->loadManagerProcess->stdin->write(serialize($this->processManagerDto->getLoadManagerDto()));
            $this->loadManagerPid = $this->loadManagerProcess->getPid();

            $this->logger->info("Sent LoadManagerDto to LM by STDIN: " . serialize($this->processManagerDto->getLoadManagerDto())
                . $this->loggerPostfix);
        });

        return null;
    }

    protected function initLoadManagingControl()
    {
        $this->loop->addTimer(2, function () {

            if (is_null($this->loadManagerPid)) {
                $this->tryTerminateProcess($this->loadManagerProcess);
                throw new ProcessManagerException("LoadManager Process can't getPid.");
            }

            /**
             * @var StartLmProcessDto $processDto
             */
            $processDto = $this->getProcessDto($this->loadManagerPid, new StartLmProcessDto());
            $processDto->setPmPid(posix_getpid());

            $this->dtoContainer->setDto($processDto);

            //request, that will init connection and start load managing control
            $this->lmRequesterSocket->send(serialize($this->dtoContainer));

            $this->logger->info("Sent init LM control request " . serialize($this->dtoContainer)
                . $this->requestNumber . $this->loggerPostfix);

            $this->requestNumber++;
        });

        return null;
    }

    protected function declareLmRequester()
    {
        $this->lmRequesterSocket = $this->context->getSocket(\ZMQ::SOCKET_REQ);
        $this->lmRequesterSocket->connect($this->processManagerDto->getPmLmSocketsParams()->getPmLmRequestAddress());

        $this->lmRequesterSocket->on(EventsConstants::ERROR, function (\Exception $e) {
            $this->logger->error(LoggingExceptions::getExceptionString($e));
        });

        $this->lmRequesterSocket->on(EventsConstants::MESSAGE, function ($receivedDtoContainer) {
            $this->logger->alert("Received dto from LM." . $this->loggerPostfix);
            usleep($this->processManagerDto->getInterMessageInterval());

            /**
             * @var DtoContainer $dtoContainer
             */
            $dtoContainer = unserialize($receivedDtoContainer);

            $this->processReceivedControlDto($dtoContainer->getDto());

            if ($this->allowSending) {
                $this->lmRequesterSocket->send(serialize($this->dtoContainer));
                $this->logger->alert("Send a request " . $this->requestNumber . $this->loggerPostfix);
                $this->requestNumber++;
            }
        });
    }

    protected function initFinishing()
    {
        $pmStateDto = new PmStateDto();
        $pmStateDto->setLoopStop(true);

        $this->dtoContainer->setDto($pmStateDto);

        $this->loop->nextTick(function () {
            $this->loop->stop();
        });

        return null;
    }

    public function processReceivedControlDto(LmControlDto $receivedControlDto)
    {
        $this->currentCpuUsage = $receivedControlDto->getCpuUsage();
        $this->currentResidentMemoryUsage = $receivedControlDto->getResidentMemoryUsage();

        switch (true) {
            case($receivedControlDto instanceof LmStateDto):
                $this->logger->emergency("PM got lmStateDto. Allow generate: " . serialize($receivedControlDto->isAllowGenerate()));
                /**
                 * @var LmStateDto $receivedControlDto
                 */
                if ($receivedControlDto->isAllowGenerate()) {
                    $this->generateProcessOrSkipGeneration($receivedControlDto);
                } elseif ($receivedControlDto->isAllPidsFinished()) {
                    $this->initFinishing();
                } elseif ($receivedControlDto->isCriticalFinish()) {

                    $this->executionDto->setErrorExist(true);
                    $this->executionDto->setCriticalError(true);
                    $this->executionDto->setErrorMessage($receivedControlDto->getCriticalFinishReason());

                    $this->initFinishing();
                    $this->logger->warning("PM go to stop due to critical finishing, with reason: "
                        . $receivedControlDto->getCriticalFinishReason());
                } else {
                    $this->dtoContainer->setDto(new PmStateDto());
                }
                break;
            case($receivedControlDto instanceof TerminateDto):

                /**
                 * @var TerminateDto $receivedControlDto
                 */
                $pidsToTerminateOrSigTerm = $receivedControlDto->getPidsForTerminate();

                $this->logger->warning("PM receive PIDs to terminate: " . serialize($receivedControlDto));

                if (!empty($pidsToTerminateOrSigTerm)) {
                    $processesToTerminate = [];

                    /**
                     * @var Process $process
                     */
                    foreach ($this->processes as $parentPid => $process) {
                        foreach ($pidsToTerminateOrSigTerm as $key => $pid) {
                            if ($pid === $parentPid) {

                                $processesToTerminate[$pid] = $process;

                                unset($pidsToTerminateOrSigTerm[$key]);
                                unset($this->processes[$pid]);
                            }
                        }
                    }

                    unset($process);
                    /**
                     * @var Process $process
                     */
                    foreach ($processesToTerminate as $process) {
                        $this->tryTerminateProcess($process);
                    }

                    if ($this->sigTermBlockingAgent) {

                        $this->logger->critical("Pids to terminate: " . serialize($pidsToTerminateOrSigTerm));

                        $jsonPids = json_encode($this->prepareTerminatePids($pidsToTerminateOrSigTerm));

                        $this->logger->critical("PM send json: " . $jsonPids);
                        $this->workersPublisherSocket->send($jsonPids);
                    } else {
                        foreach ($pidsToTerminateOrSigTerm as $pid) {
                            $this->trySendSigTerm($pid);
                        }
                    }
                }

                $this->dtoContainer->setDto(new PmStateDto());
                break;
            case($receivedControlDto instanceof PauseDto):

                $this->logger->emergency("PM receive PauseDto: " . serialize($receivedControlDto));

                $this->workersPublisherSocket->send(json_encode($this->preparePausePids($receivedControlDto)));

                $this->dtoContainer->setDto(new PmStateDto());
                break;
            case($receivedControlDto instanceof ContinueDto):
                $this->logger->emergency("PM receive ContinueDto: " . serialize($receivedControlDto));

                $this->workersPublisherSocket->send(json_encode($this->prepareContinuePids($receivedControlDto)));

                $this->dtoContainer->setDto(new PmStateDto());
                break;
            default:
                throw new ProcessManagerInvalidArgument("Unknown control dto: " . serialize($receivedControlDto));
        }

        return null;
    }

    protected function prepareTerminatePids(array $pids)
    {
        $pauseStanderArr = $this->getTerminatorPauseStanderArr();
        $pauseStanderArr[TerminatorPauseStanderConstants::PIDS_FOR_TERMINATE] = $pids;

        return $pauseStanderArr;
    }

    protected function preparePausePids(PauseDto $pauseDto)
    {
        $pauseStanderArr = $this->getTerminatorPauseStanderArr();
        $pauseStanderArr[TerminatorPauseStanderConstants::PIDS_FOR_PAUSE] = $pauseDto->getPidsForPause();

        return $pauseStanderArr;
    }

    protected function prepareContinuePids(ContinueDto $continueDto)
    {
        $pauseStanderArr = $this->getTerminatorPauseStanderArr();
        $pauseStanderArr[TerminatorPauseStanderConstants::PIDS_FOR_CONTINUE] = $continueDto->getPidsForContinue();

        return $pauseStanderArr;
    }

    protected function getTerminatorPauseStanderArr()
    {
        $arr = [];
        $arr[TerminatorPauseStanderConstants::PIDS_FOR_TERMINATE] = [];
        $arr[TerminatorPauseStanderConstants::PIDS_FOR_PAUSE] = [];
        $arr[TerminatorPauseStanderConstants::PIDS_FOR_CONTINUE] = [];

        return $arr;
    }

    protected function generateProcessOrSkipGeneration(LmStateDto $receivedControlDto)
    {
        $processesNumber = count($this->processes);

        if (($processesNumber < $this->tasks) && (
            ($this->processManagerDto->getMaxSimultaneousProcesses() > 1)
                ? $processesNumber < $this->processManagerDto->getMaxSimultaneousProcesses() : true)
        ) {

            $this->logger->info("PM come to create new process.");
            $this->logger->info("Tasks number:" . serialize($this->tasks));
            $this->logger->info("Processes number:" . serialize(count($this->processes)));

            $workerProcess = new Process($this->processManagerDto->getWorkerProcessCommand());

            $workerProcess->start($this->loop);

            $workerProcessPid = $workerProcess->getPid();

            //to protect from bug of proc_get_status
            if (!(is_null($workerProcessPid))) {

                if ($this->processManagerDto->getPerformerSocketsParams()) {
                    $workerProcess->stdin->write(json_encode($this->getPreparedSocketParams()));
                }

                $workerProcess->stdout->on(EventsConstants::DATA, function ($data) use (&$workerProcessPid) {
                    //$data = @json_decode($data);
                    //$this->logger->warning("Worker STDOUT: " . serialize($data));
                });

                $workerProcess->on(EventsConstants::PROCESS_EXIT, function ($exitCode, $termSignal) {
                    $this->logger->warning("Worker sub-process exit with code: "
                        . serialize($exitCode) . " | and term signal: " . serialize($termSignal));
                });

                $processDto = $this->getProcessDto($workerProcessPid);

                $this->processes[$workerProcessPid] = $workerProcess;

                $this->dtoContainer->setDto($processDto);

            } else {

                $this->dtoContainer->setDto(new PmStateDto());

            }

        } else { //all processes already created, but not finished yet

            if (!$this->allTasksCreated) {
                $this->allTasksCreated = true;
            }

            $pmStateDto = new PmStateDto();
            $pmStateDto->setAllProcessesCreated(true);
            $this->dtoContainer->setDto($pmStateDto);

            $this->logger->info("PM send all processes created.");
            $this->logger->info("Tasks number:" . serialize($this->tasks));
            $this->logger->info("Processes number:" . count($this->processes));
        }

        return null;
    }

    protected function getPreparedSocketParams()
    {
        $socketParamsArr = [];
        $socketParamsArr[DataTransferConstants::REQUEST_PULSAR_RS] = $this->processManagerDto
            ->getPerformerSocketsParams()->getRequestPulsarRsSocketAddress();

        $socketParamsArr[DataTransferConstants::PUBLISHER_PULSAR] = $this->processManagerDto
            ->getPerformerSocketsParams()->getPublisherPulsarSocketAddress();

        $socketParamsArr[DataTransferConstants::PUSH_PULSAR] = $this->processManagerDto
            ->getPerformerSocketsParams()->getPushPulsarSocketAddress();

        $socketParamsArr[DataTransferConstants::PUBLISHER_PM] = $this->processManagerDto
            ->getPerformerSocketsParams()->getPublisherPmSocketAddress();

        return $socketParamsArr;
    }

    protected function getProcessDto($workerProcessPid, $processDto = null)
    {
        if (!(is_int($workerProcessPid))) {
            throw new ProcessManagerInvalidArgument("Worker process PID is not int: " . serialize($workerProcessPid));
        }

        $processDto = ($processDto instanceof ProcessDto) ? $processDto : new ProcessDto();
        $processDto->setPid($workerProcessPid);

        try {

            /**
             * @var PidDto $pidDto
             */
            $pidDto = $this->commandsManager->getPidByPpid($workerProcessPid);
            $processDto->setChildPid($pidDto->getPid());

        } catch (\Exception $e) {
            $this->logger->error(ExceptionsConstants::ERROR_IN_DETECT_PID_BY_PPID
                . " | " . LoggingExceptions::getExceptionString($e));
        }

        return $processDto;
    }

    protected function terminateLoadManager()
    {
        $this->logger->info('Try to terminate LM.');

        try {

            /**
             * @var PidDto $lmPidDto
             */
            $lmPidDto = $this->commandsManager->getPidByPpid($this->loadManagerProcess->getPid());
            $this->trySendSigTerm($lmPidDto->getPid());

        } catch (\Exception $e) {
            $this->logger->debug(LoggingExceptions::getExceptionString($e));
        }

        $this->tryTerminateProcess($this->loadManagerProcess);

        return null;
    }

    protected function logAndSendSigKill($pid, \Exception $e)
    {
        $this->logger->error(ExceptionsConstants::SEND_KILL_SIGNAL
            . "PID: " . $pid . " | " . LoggingExceptions::getExceptionString($e));

        $this->commandsManager->sendSig($pid, SIGKILL);

        return null;
    }

    protected function trySendSigTerm($pid)
    {
        $this->logger->info('Try to send sigTerm for PID: ' . $pid);

        try {

            $sendSigResult = $this->commandsManager->sendSig($pid, SIGTERM);

            $this->logger->info("SigTerm return: " . serialize($sendSigResult));

            if (!$sendSigResult) {
                throw new ReactManagerException("SigTerm for PID " . $pid . " wasn't success.");
            }

        } catch (\Exception $e) {

            try {

                $this->logAndSendSigKill($pid, $e);

            } catch (\Exception $e) {

                $this->logger->warning("Child process with PID $pid can't be sigKilled.");

            }
        }

        return null;
    }

    public function tryTerminateProcess(Process $process)
    {
        try {

            $terminationResult = $process->terminate();

            if (!$terminationResult) {
                throw new ReactManagerException("Process termination for PID " . $process->getPid() . " wasn't success");
            }

        } catch (\Exception $e) {

            try {

                $this->logAndSendSigKill($process->getPid(), $e);

            } catch (\Exception $e) {

                $this->logger->warning("Parent process can't be sigKilled.");

            }
        }

        return null;
    }

    /**
     * @return Process
     */
    public function getLoadManagerProcess()
    {
        return $this->loadManagerProcess;
    }

}
