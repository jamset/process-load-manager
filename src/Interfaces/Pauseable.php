<?php
/**
 * Created by PhpStorm.
 * User: ww
 * Date: 05.10.15
 * Time: 1:18
 */
namespace React\ProcessManager\Interfaces;

interface Pauseable
{
    /**Check if subscription contain pid of process
     * @return mixed
     */
    public function checkSubscription();

    /**Go into infinite loop with periodic checking of the subscription if it allows to continue
     * @return mixed
     */
    public function standOnPauseIfMust();

    /**Exit from infinite loop
     * @return mixed
     */
    public function continueExecution();


}