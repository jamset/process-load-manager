<?php
/**
 * Created by PhpStorm.
 * User: ww
 * Date: 20.10.15
 * Time: 8:03
 */
namespace React\ProcessManager;

use React\FractalBasic\Abstracts\BaseExecutor;
use React\ProcessManager\Interfaces\Pauseable;
use React\ProcessManager\Inventory\Exceptions\ProcessManagerException;
use React\ProcessManager\Inventory\Exceptions\TerminatorPauseStanderException;
use React\ProcessManager\Inventory\TerminatorPauseStanderConstants;
use React\ProcessManager\Inventory\TerminatorPauseStanderDto;
use Monolog\Logger;

class TerminatorPauseStander extends BaseExecutor implements Pauseable
{
    /**
     * @var string
     */
    protected $publisherPmSocketAddress;

    /**
     * @var string
     */
    protected $subscriberSocket;

    /**
     * @var int microseconds
     */
    protected $uSleepTime = 5000000;

    /**
     * @var int
     */
    protected $pid;

    /**
     * @var array
     */
    protected $subscriptionMessage;

    /**
     * @var bool
     */
    protected $iAmInPause = false;

    /**
     * @var bool
     */
    protected $mustStandOnPause = false;

    /**
     * @var bool
     */
    protected $canContinue = false;

    public function __construct(TerminatorPauseStanderDto $pauseStanderDto)
    {
        parent::__construct($pauseStanderDto);
        $this->pid = getmypid();

        return null;
    }

    /**
     * @return string
     */
    public function getPublisherPmSocketAddress()
    {
        return $this->publisherPmSocketAddress;
    }

    /**
     * @param string $publisherPmSocketAddress
     */
    public function setPublisherPmSocketAddress($publisherPmSocketAddress)
    {
        $this->publisherPmSocketAddress = $publisherPmSocketAddress;
    }

    /**
     * @return int
     */
    public function getUSleepTime()
    {
        return $this->uSleepTime;
    }

    /**
     * @param int $uSleepTime
     */
    public function setUSleepTime($uSleepTime)
    {
        $this->uSleepTime = $uSleepTime;
    }

    /**
     * @return null
     * @throws ProcessManagerException
     */
    public function checkPmCommand()
    {
        $this->checkSubscription();
        $this->standOnPauseIfMust();

        return null;
    }

    /**Check if subscription contain pid of process
     * @return mixed
     */
    public function checkSubscription()
    {
        if (!$this->subscriberSocket) {
            $this->subscriberSocket = $this->context->getSocket(\ZMQ::SOCKET_SUB);
            $this->subscriberSocket->connect($this->publisherPmSocketAddress);
            $this->subscriberSocket->setSockOpt(\ZMQ::SOCKOPT_SUBSCRIBE, "");
        }

        $this->subscriptionMessage = @json_decode($this->subscriberSocket->recv(\ZMQ::MODE_DONTWAIT), true);

        if (($this->subscriptionMessage !== false) && ($this->subscriptionMessage !== null)
            && (is_array($this->subscriptionMessage) === false)
        ) {
            throw new ProcessManagerException("Not correct param from ProcessManager to TerminatorPauseStander: "
                . serialize($this->subscriptionMessage));
        }

        if (isset($this->subscriptionMessage)) {
            $this->logger->info("Receive subscription message: " . serialize($this->subscriptionMessage));
            $this->resolveAction();
        }

        return null;
    }

    /**Go into infinite loop with periodic checking of the subscription if it allows to continue
     * @return mixed
     */
    public function standOnPauseIfMust()
    {
        if ($this->mustStandOnPause) {

            $this->iAmInPause = true;

            while ($this->canContinue === false) {

                $this->logger->info(getmypid() . " come to usleep for microseconds: " . $this->uSleepTime);
                usleep($this->uSleepTime);
                $this->logger->info(getmypid() . " wake up and start check subscription.");
                $this->checkSubscription();

                $this->logger->info(getmypid() . " _CONTINUE_ from infinity while. CanContinue: "
                    . serialize($this->canContinue));
            }

            $this->continueExecution();
        }

        return null;
    }

    /**Exit from infinite loop
     * @return mixed
     */
    public function continueExecution()
    {
        $this->logger->info("TerminatorPauseStander " . getmypid() . " CONTINUED.");

        $this->iAmInPause = false;
        $this->mustStandOnPause = false;
        $this->canContinue = false;

        return null;
    }

    protected function resolveAction()
    {
        if ($this->checkPidExist($this->subscriptionMessage[TerminatorPauseStanderConstants::PIDS_FOR_TERMINATE])) {
            $msg = "Process " . $this->pid . " receive command to die.";
            $this->logger->info($msg);
            throw new TerminatorPauseStanderException($msg);
        }

        if (!$this->iAmInPause) {

            $this->logger->info("Start check in pids for pause pid: " . getmypid());

            if ($this->checkPidExist($this->subscriptionMessage[TerminatorPauseStanderConstants::PIDS_FOR_PAUSE])) {
                $this->logger->info("Pid " . getmypid() . " must be __PAUSED__.");
                $this->mustStandOnPause = true;
            }

        } else {

            $this->logger->info("Pid " . getmypid() . " in pause. Start check if allow to continue.");

            if ($this->checkPidExist($this->subscriptionMessage[TerminatorPauseStanderConstants::PIDS_FOR_CONTINUE])) {
                $this->logger->info("Pid " . getmypid() . " can be __CONTINUED__.");
                $this->canContinue = true;
            }
        }

        return null;
    }

    protected function checkPidExist($pidsArr)
    {
        $result = false;

        foreach ($pidsArr as $pid) {
            if ($pid === $this->pid) {
                $result = true;
                break;
            }
        }

        return $result;
    }


}
